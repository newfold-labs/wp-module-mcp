<?php

declare( strict_types=1 );

namespace BLU;

use BLU\Abilities\CustomPostTypes;
use BLU\Abilities\Media;
use BLU\Abilities\Pages;
use BLU\Abilities\Posts;
use BLU\Abilities\RestApiCrud;
use BLU\Abilities\Settings;
use BLU\Abilities\SiteInfo;
use BLU\Abilities\Users;
use BLU\Abilities\WooOrders;
use BLU\Abilities\WooProducts;
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
		add_action( 'mcp_adapter_init', [ $this, 'register_server' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_ability_categories' ] );
	}

	/**
	 * Registers a server with specified configurations, including abilities, transports, and handlers,
	 * for the Bluehost MCP server functionality.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function register_server(): void {

		// Get all abilities in the blu-mcp category
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
			server_id: 'blu-mcp',
			server_route_namespace: 'blu',
			server_route: 'mcp',
			server_name: 'Bluehost MCP Server',
			server_description: 'MCP server exposing Bluehost WordPress abilities',
			server_version: '1.0.0',
			mcp_transports: [ HttpTransport::class ],
			error_handler: ErrorLogMcpErrorHandler::class,
			observability_handler: NullMcpObservabilityHandler::class,
			tools: $abilities
		);
	}

	/**
	 * Registers various abilities by initializing their respective classes.
	 *
	 * @return void
	 */
	public function register_abilities(): void {
		// Initialize all ability classes
		new Posts();
		new Pages();
		new Media();
		new Users();
		new SiteInfo();
		new Settings();
		new CustomPostTypes();
		new RestApiCrud();
		new WooProducts();
		new WooOrders();
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
