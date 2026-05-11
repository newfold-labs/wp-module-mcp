<?php

declare( strict_types=1 );

namespace BLU\Integrations;

/**
 * Compatibility bridge for WooCommerce's native Abilities API integration.
 *
 * WooCommerce 10.3+ ships its own MCP server at `/wp-json/woocommerce/mcp` and gates
 * the registration of its native abilities on a URI match against that exact route:
 *
 *   if ( ! \Automattic\WooCommerce\Internal\MCP\MCPAdapterProvider::is_mcp_request() ) {
 *       return;
 *   }
 *
 * Because the Bluehost MCP transport is mounted at `/blu/mcp`, WC's check fails and
 * none of its native abilities (`woocommerce/products-list`, `woocommerce/orders-get`,
 * etc.) get registered. The gateway then cannot see or invoke any of them — even
 * though both the `woocommerce/` namespace and the `woocommerce-rest` category are
 * whitelisted in {@see \BLU\Abilities\AbilityGateway::get_whitelisted_abilities()}.
 *
 * This class force-registers WC's abilities during `wp_abilities_api_init` by briefly
 * spoofing `$_SERVER['REQUEST_URI']` so WC's gate passes. The original URI is restored
 * inside a `finally` block before any other code observes the change.
 *
 * The fix is scoped: by default it only fires when the current request is being
 * handled by the Bluehost MCP transport. Non-MCP requests (admin pages, regular
 * REST calls, etc.) don't pay the controller-instantiation cost — which matches
 * WC's own lazy-load intent.
 *
 * Filterable via `blu_mcp_register_woocommerce_abilities`. The default value
 * passed in is the result of {@see self::is_blu_mcp_request()}.
 */
class WooCommerceAbilities {

	/**
	 * Fully-qualified class name of WC's `AbilitiesRestBridge`.
	 *
	 * @var string
	 */
	private const WC_BRIDGE_CLASS = '\\Automattic\\WooCommerce\\Internal\\Abilities\\AbilitiesRestBridge';

	/**
	 * REQUEST_URI value that satisfies WC's `is_mcp_request()` check.
	 *
	 * Mirrors WC's `MCPAdapterProvider::MCP_NAMESPACE . '/' . MCPAdapterProvider::MCP_ROUTE`.
	 *
	 * @var string
	 */
	private const SPOOF_URI = '/wp-json/woocommerce/mcp';

	/**
	 * Path fragment that identifies a Bluehost MCP transport request.
	 *
	 * @var string
	 */
	private const BLU_MCP_PATH = '/blu/mcp';

	/**
	 * Hooks the force-registration callback on `wp_abilities_api_init` and the
	 * permission delegate on `woocommerce_check_rest_ability_permissions_for_method`.
	 */
	public function __construct() {
		// Priority 5 so we run before WC's own gated callback at default priority 10.
		// WC's callback at priority 10 will see the (restored) original URI, its gate
		// returns false, and it short-circuits — so registration happens exactly once.
		add_action( 'wp_abilities_api_init', array( $this, 'maybe_register_abilities' ), 5 );

		// WC's RestAbilityFactory::check_permission() runs this filter with a default
		// of `false`; permission is only granted if some hook flips it to `true`. WC's
		// own `WooCommerceRestTransport` adds that hook, but only when the transport is
		// instantiated for `/woocommerce/mcp`. On `/blu/mcp` requests the transport
		// never loads, the filter is never hooked, and every WC ability fails its
		// permission check. We supply the missing hook here, scoped to the Bluehost
		// transport, and delegate the actual authorization to the underlying REST
		// controller's own permission_callback (which runs inside rest_do_request()).
		add_filter( 'woocommerce_check_rest_ability_permissions_for_method', array( $this, 'check_ability_permission' ), 10, 3 );
	}

	/**
	 * Conditionally force-register WooCommerce's native abilities.
	 *
	 * Idempotent within a single request via a static guard so repeated firings of
	 * `wp_abilities_api_init` (e.g. from both the modern and the legacy
	 * `abilities_api_init` action) do not re-register and emit duplicate warnings.
	 *
	 * @return void
	 */
	public function maybe_register_abilities(): void {
		static $done = false;
		if ( $done ) {
			return;
		}

		$should_register = $this->is_blu_mcp_request();

		/**
		 * Filter whether to force-register WooCommerce's native abilities on this request.
		 *
		 * Default is `true` when the current request URI contains `/blu/mcp` (so the
		 * gateway can see `woocommerce/*` abilities through `blu-list-abilities` /
		 * `blu-call-ability`), `false` otherwise (so non-MCP requests don't incur the
		 * controller-instantiation cost that WC's lazy-load was designed to avoid).
		 *
		 * @param bool $should_register Default decision based on the current request URI.
		 */
		$should_register = apply_filters( 'blu_mcp_register_woocommerce_abilities', $should_register );
		if ( ! $should_register ) {
			return;
		}

		if ( ! class_exists( self::WC_BRIDGE_CLASS ) ) {
			return;
		}
		if ( ! is_callable( array( self::WC_BRIDGE_CLASS, 'register_abilities' ) ) ) {
			return;
		}

		$original_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$_SERVER['REQUEST_URI'] = self::SPOOF_URI;
		try {
			call_user_func( array( self::WC_BRIDGE_CLASS, 'register_abilities' ) );
		} finally {
			if ( null === $original_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $original_uri;
			}
		}

		$done = true;
	}

	/**
	 * Approve WooCommerce ability execution at the abilities-API layer for requests
	 * served by the Bluehost MCP transport. The underlying REST controller's own
	 * `permission_callback` still gates every dispatched request inside
	 * `RestAbilityFactory::execute_operation()`, so real authorization
	 * (e.g. `current_user_can( 'manage_woocommerce' )`) is enforced one layer down.
	 *
	 * Outside `/blu/mcp` we return `$allowed` unchanged so WC's own logic — including
	 * `WooCommerceRestTransport::check_ability_permission()` — is not overridden.
	 *
	 * @param bool   $allowed    Current decision propagated through the filter chain.
	 * @param string $method     HTTP method (GET, POST, PUT, PATCH, DELETE, OPTIONS).
	 * @param object $controller REST controller instance for the ability.
	 *
	 * @return bool
	 */
	public function check_ability_permission( $allowed, $method, $controller ): bool {
		unset( $method, $controller );

		if ( $allowed ) {
			return true;
		}

		if ( ! $this->is_blu_mcp_request() ) {
			return (bool) $allowed;
		}

		/**
		 * Filter the per-method decision for WC ability execution on the Bluehost MCP transport.
		 *
		 * Default `true` defers the real authorization to the underlying REST controller's
		 * permission_callback, which runs inside rest_do_request(). Sites that want a stricter
		 * gate at the abilities-API layer can return `false` here.
		 *
		 * @param bool $allowed Whether to approve execution at the abilities-API layer.
		 */
		return (bool) apply_filters( 'blu_mcp_woocommerce_ability_permission', true );
	}

	/**
	 * Whether the current request is being handled by the Bluehost MCP transport.
	 *
	 * @return bool
	 */
	private function is_blu_mcp_request(): bool {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! is_string( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return false !== strpos( $_SERVER['REQUEST_URI'], self::BLU_MCP_PATH );
	}
}
