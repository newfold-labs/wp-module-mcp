<?php

declare( strict_types=1 );

namespace BLU;

use BLU\Abilities\BlockEditor;
use BLU\Abilities\AbilityGateway;
use BLU\Abilities\CustomPostTypes;
use BLU\Abilities\GlobalStyles;
use BLU\Abilities\Media;
use BLU\Abilities\Pages;
use BLU\Abilities\Posts;
use BLU\Abilities\Prompts;
use BLU\Abilities\Resources;
use BLU\Abilities\RestApiCrud;
use BLU\Abilities\Settings;
use BLU\Abilities\SiteInfo;
use BLU\Abilities\Users;
use BLU\Abilities\WooOrders;
use BLU\Abilities\WooProducts;
use BLU\Abilities\Themes;
use BLU\Abilities\ImageGen;
use BLU\Abilities\LogoGen;

use BLU\Integrations\WooCommerceAbilities;
use BLU\Validation\McpValidation;
use Bluehost\Plugin\WP\MCP\Core\McpAdapter;
use Bluehost\Plugin\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use Bluehost\Plugin\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use Bluehost\Plugin\WP\MCP\Servers\DefaultServerFactory;
use Bluehost\Plugin\WP\MCP\Transport\HttpTransport;

/**
 * MCP Server registration for Bluehost abilities.
 */
class McpServer {

	/**
	 * Initializes the class by setting up actions to register the server and abilities
	 * during the respective initialization hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'mcp_adapter_init', array( $this, 'register_server' ) );
		// Runs after register_server() and the prefixed DefaultServerFactory hook so the
		// first (own-adapter) firing has completed successfully before we clean up.
		add_action( 'mcp_adapter_init', array( $this, 'suppress_sibling_default_server_refire' ), 999 );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_categories' ) );

		// Compat shims for sibling plugins that lazy-load their MCP machinery against
		// their own transport route. Each one self-registers its hooks.
		new WooCommerceAbilities();
	}

	/**
	 * Registers a server with specified configurations, including abilities, transports, and handlers,
	 * for the Blue host MCP server functionality.
	 *
	 * The mcp_adapter_init action name is a plain string and therefore shared across every
	 * vendored copy of wordpress/mcp-adapter on the site (e.g. the unprefixed copy WooCommerce
	 * ships). Without the guard below we would re-enter create_server() on each foreign
	 * adapter's do_action, hitting a duplicate_server_id error attributed to the Bluehost
	 * Strauss-prefixed McpAdapter class.
	 *
	 * @param McpAdapter|null $adapter Adapter instance that fired the action.
	 *
	 * @return void If the server creation is successful
	 * @throws \Exception If the server creation fails.
	 */
	public function register_server( $adapter = null ): void {

		if ( ! $adapter instanceof McpAdapter || McpAdapter::instance() !== $adapter ) {
			return;
		}

		$use_gateway = apply_filters( 'blu_mcp_use_gateway', true );

		if ( $use_gateway ) {
			$abilities = AbilityGateway::GATEWAY_ABILITIES;
		} else {
			// Legacy: expose all individual tools directly.
			$abilities = blu_get_ability_by_type( 'tool' );
		}

		$prompts   = blu_get_ability_by_type( 'prompt' );
		$resources = blu_get_ability_by_type( 'resource' );
		$adapter->create_server(
			'blu-mcp', // server_id
			'blu', // server_route_namespace
			'mcp', // server_route
			'Bluehost MCP Server', // server_name
			'MCP server exposing Bluehost WordPress abilities', // server_description
			'1.0.0', // server_version
			array( HttpTransport::class ), // mcp_transports
			ErrorLogMcpErrorHandler::class, // error_handler
			NullMcpObservabilityHandler::class, // observability_handler
			$abilities, // tools,
			$resources, // resources
			$prompts, // prompts
			function ( \WP_REST_Request $request ) {
				// transport_permission_callback
				return ( new McpValidation( $request ) )->is_authenticated();
			}
		);
	}

	/**
	 * Detaches the Strauss-prefixed DefaultServerFactory::create hook once our own
	 * mcp_adapter_init firing has completed, so that a sibling adapter's later firing
	 * of the same shared-string action does not re-trigger it and emit a
	 * duplicate_server_id _doing_it_wrong attributed to our prefixed namespace.
	 *
	 * @param McpAdapter|null $adapter Adapter instance that fired the action.
	 *
	 * @return void
	 */
	public function suppress_sibling_default_server_refire( $adapter = null ): void {
		if ( ! $adapter instanceof McpAdapter || McpAdapter::instance() !== $adapter ) {
			return;
		}
		remove_action(
			'mcp_adapter_init',
			array( DefaultServerFactory::class, 'create' )
		);
	}

	/**
	 * Registers various abilities by initializing their respective classes.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		// Gateway tools (list/schema/call) must be registered before other abilities
		// so they are available when register_server() runs.
		new AbilityGateway();
		// Initialize all ability classes
		new Prompts();
		new Resources();
		new Posts();
		new Pages();
		new Media();
		new Users();
		new SiteInfo();
		new Settings();
		new CustomPostTypes();
		new RestApiCrud();
		new GlobalStyles();
		new WooProducts();
		new WooOrders();
		new Themes();
		new BlockEditor();
		new ImageGen();
		new LogoGen();
	}

	/**
	 * Registers ability categories for the Bluehost MCP, including a label and description for categorization.
	 *
	 * @return void
	 */
	public function register_ability_categories(): void {
		wp_register_ability_category(
			'blu-mcp',
			array(
				'label'       => 'Bluehost MCP',
				'description' => 'Bluehost-specific abilities for use with MCP',
			)
		);
	}
}
