<?php

declare( strict_types=1 );

namespace BLU;

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

use BLU\Validation\McpValidation;
use Bluehost\Plugin\WP\MCP\Core\McpAdapter;
use Bluehost\Plugin\WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use Bluehost\Plugin\WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
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
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_ability_categories' ) );
	}

	/**
	 * Registers a server with specified configurations, including abilities, transports, and handlers,
	 * for the Blue host MCP server functionality.
	 *
	 * @return void If the server creation is successful
	 * @throws \Exception If the server creation fails.
	 */
	public function register_server(): void {

		$abilities = array_map(
			function ( $ability ) {
				return $ability->get_name();
			},
			blu_get_abilities_by_category( 'blu-mcp' )
		);
		// Get the MCP adapter instance
		$adapter = McpAdapter::instance();

		// Create the server
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
			array(), // resources
			array(), // prompts
			function ( \WP_REST_Request $request ) {
				// transport_permission_callback
				return ( new McpValidation( $request ) )->is_authenticated();
			}
		);
	}

	/**
	 * Registers various abilities by initializing their respective classes.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
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
