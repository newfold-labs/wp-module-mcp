<?php

namespace BLU;

use BLU\Integrations\WooCommerceAbilities;

/**
 * Tests for the WooCommerce abilities compatibility bridge.
 *
 * The real `\Automattic\WooCommerce\Internal\Abilities\AbilitiesRestBridge` is not
 * available in the test environment. These tests cover the bridge's
 * environment-independent behavior — hook registration, URI scoping, the
 * `blu_mcp_register_woocommerce_abilities` filter, and the no-op path when WC is
 * absent. End-to-end verification with a live WC install is manual.
 *
 * @covers \BLU\Integrations\WooCommerceAbilities
 */
class WooCommerceAbilitiesIntegrationWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Snapshot of $_SERVER['REQUEST_URI'] so each test sees a clean slate.
	 *
	 * @var string|null
	 */
	private $original_request_uri;

	/**
	 * Snapshot REQUEST_URI so each test sees a clean slate.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$this->original_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : null;
	}

	/**
	 * Restore REQUEST_URI and remove any filters added during the test.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		if ( null === $this->original_request_uri ) {
			unset( $_SERVER['REQUEST_URI'] );
		} else {
			$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		}
		remove_all_filters( 'blu_mcp_register_woocommerce_abilities' );
		parent::tear_down();
	}

	/**
	 * Verifies constructing the bridge wires the `wp_abilities_api_init` callback at
	 * priority 5 — before WooCommerce's own callback at default priority 10.
	 *
	 * @return void
	 */
	public function test_constructor_registers_hook_at_priority_5(): void {
		$bridge   = new WooCommerceAbilities();
		$priority = has_action( 'wp_abilities_api_init', array( $bridge, 'maybe_register_abilities' ) );

		$this->assertSame( 5, $priority );
	}

	/**
	 * Verifies the no-op path: when WC's bridge class is absent (as in the test env)
	 * the method must not throw, must not mutate $_SERVER, and must not register anything.
	 *
	 * @return void
	 */
	public function test_method_is_noop_when_woocommerce_absent(): void {
		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp';

		$bridge = new WooCommerceAbilities();
		$bridge->maybe_register_abilities();

		// REQUEST_URI must be untouched — both for the spoof and for the restore.
		$this->assertSame( '/wp-json/blu/mcp', $_SERVER['REQUEST_URI'] );

		// And no woocommerce/* abilities should have been registered.
		$registry = \WP_Abilities_Registry::get_instance();
		$this->assertFalse( $registry->is_registered( 'woocommerce/products-list' ) );
	}

	/**
	 * Verifies the request-scoping default: outside a `/blu/mcp` request the bridge
	 * doesn't even attempt to load WC's class. We assert by sending a non-MCP URI and
	 * a filter callback that would fail loudly if the codepath continued past the
	 * URI check.
	 *
	 * @return void
	 */
	public function test_does_not_attempt_registration_outside_blu_mcp_request(): void {
		$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';

		$filter_received = null;
		add_filter(
			'blu_mcp_register_woocommerce_abilities',
			function ( $value ) use ( &$filter_received ) {
				$filter_received = $value;
				return $value;
			}
		);

		( new WooCommerceAbilities() )->maybe_register_abilities();

		// The filter must have been invoked with the contextual default (false here).
		$this->assertFalse( $filter_received );
	}

	/**
	 * Verifies the filter can force-enable registration even outside a `/blu/mcp` request
	 * (e.g. for tests, or for cron jobs that need WC abilities visible).
	 *
	 * @return void
	 */
	public function test_filter_can_force_enable_outside_blu_mcp_request(): void {
		$_SERVER['REQUEST_URI'] = '/wp-admin/admin-ajax.php';

		$filter_received = null;
		add_filter(
			'blu_mcp_register_woocommerce_abilities',
			function () use ( &$filter_received ) {
				$filter_received = true;
				return true;
			}
		);

		// Even with the filter forced true the method short-circuits at the WC class
		// check (WC is absent in the test env) — so this should not throw.
		( new WooCommerceAbilities() )->maybe_register_abilities();

		$this->assertTrue( $filter_received );
		// REQUEST_URI must still be restored even when the class check fails.
		$this->assertSame( '/wp-admin/admin-ajax.php', $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Verifies the filter can force-disable registration on a `/blu/mcp` request.
	 *
	 * @return void
	 */
	public function test_filter_can_force_disable_on_blu_mcp_request(): void {
		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp';
		add_filter( 'blu_mcp_register_woocommerce_abilities', '__return_false' );

		$bridge = new WooCommerceAbilities();
		$bridge->maybe_register_abilities();

		// REQUEST_URI must remain untouched (we never reached the spoof step).
		$this->assertSame( '/wp-json/blu/mcp', $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Verifies the contextual default passed to the filter is `true` when the request
	 * URI matches the Bluehost MCP transport path.
	 *
	 * @return void
	 */
	public function test_default_filter_value_is_true_on_blu_mcp_request(): void {
		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp';

		$filter_received = null;
		add_filter(
			'blu_mcp_register_woocommerce_abilities',
			function ( $value ) use ( &$filter_received ) {
				$filter_received = $value;
				return $value;
			}
		);

		( new WooCommerceAbilities() )->maybe_register_abilities();

		$this->assertTrue( $filter_received );
	}

	/**
	 * Verifies CLI invocations (where REQUEST_URI is not set at all) are treated as
	 * non-MCP requests and don't leak a spurious key into $_SERVER.
	 *
	 * @return void
	 */
	public function test_cli_invocation_with_no_request_uri_is_treated_as_non_mcp(): void {
		unset( $_SERVER['REQUEST_URI'] );

		$filter_received = null;
		add_filter(
			'blu_mcp_register_woocommerce_abilities',
			function ( $value ) use ( &$filter_received ) {
				$filter_received = $value;
				return $value;
			}
		);

		( new WooCommerceAbilities() )->maybe_register_abilities();

		$this->assertFalse( $filter_received, 'Unset REQUEST_URI must be treated as non-MCP' );
		$this->assertArrayNotHasKey( 'REQUEST_URI', $_SERVER, 'Must not leak the spoofed key when filter rejects' );
	}

	/**
	 * Verifies a non-string REQUEST_URI (e.g. set by a non-standard web server or
	 * malformed middleware) is defensively treated as non-MCP rather than triggering
	 * a TypeError inside strpos().
	 *
	 * @return void
	 */
	public function test_non_string_request_uri_is_treated_as_non_mcp(): void {
		$_SERVER['REQUEST_URI'] = array( 'unexpected' );

		$filter_received = null;
		add_filter(
			'blu_mcp_register_woocommerce_abilities',
			function ( $value ) use ( &$filter_received ) {
				$filter_received = $value;
				return $value;
			}
		);

		( new WooCommerceAbilities() )->maybe_register_abilities();

		$this->assertFalse( $filter_received );
	}

	/**
	 * Verifies all URI shapes that should be recognized as the Bluehost MCP transport.
	 *
	 * @return void
	 */
	public function test_recognizes_various_blu_mcp_uri_shapes(): void {
		$matching_uris = array(
			'/wp-json/blu/mcp',
			'/wp-json/blu/mcp/',
			'/wp-json/blu/mcp?session=abc',
			'/?rest_route=/blu/mcp',
			'/index.php?rest_route=/blu/mcp',
		);

		foreach ( $matching_uris as $uri ) {
			$_SERVER['REQUEST_URI'] = $uri;
			$received               = null;

			$cb = function ( $value ) use ( &$received ) {
				$received = $value;
				return $value;
			};
			add_filter( 'blu_mcp_register_woocommerce_abilities', $cb );

			( new WooCommerceAbilities() )->maybe_register_abilities();

			$this->assertTrue( $received, "URI {$uri} should be recognized as a Bluehost MCP request" );
			remove_filter( 'blu_mcp_register_woocommerce_abilities', $cb );
		}
	}

	/**
	 * Verifies URIs that should NOT trigger WC registration — including WooCommerce's
	 * own MCP transport, which must never be matched (that would force-register on a
	 * real WC MCP request and could double-register abilities).
	 *
	 * @return void
	 */
	public function test_does_not_match_unrelated_or_woocommerce_mcp_uris(): void {
		$non_matching = array(
			'/wp-json/wp/v2/posts',
			'/wp-admin/index.php',
			'/wp-json/woocommerce/mcp',
			'/wp-json/woocommerce/mcp/something',
			'/',
			'',
		);

		foreach ( $non_matching as $uri ) {
			$_SERVER['REQUEST_URI'] = $uri;
			$received               = null;

			$cb = function ( $value ) use ( &$received ) {
				$received = $value;
				return $value;
			};
			add_filter( 'blu_mcp_register_woocommerce_abilities', $cb );

			( new WooCommerceAbilities() )->maybe_register_abilities();

			$this->assertFalse( $received, "URI {$uri} must not be recognized as a Bluehost MCP request" );
			remove_filter( 'blu_mcp_register_woocommerce_abilities', $cb );
		}
	}
}
