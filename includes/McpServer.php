<?php

declare( strict_types=1 );

namespace BLU;

use BLU\Abilities\CustomPostTypes;
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


		// Get the MCP adapter instance
		$adapter = McpAdapter::instance();

		$tools     = $this->discover_abilities_by_type(  );
		$resources = $this->discover_abilities_by_type( 'resource' );
		$prompts   = $this->discover_abilities_by_type(  'prompt' );
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
			$tools,
			$resources,
			$prompts,
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

	/**
	 * Discover abilities by MCP type.
	 *
	 * Scans all registered abilities and returns those with the specified type
	 * and public MCP exposure.
	 *
	 * @param string $type The MCP type to filter by ('tool', 'resource', or 'prompt').
	 *
	 * @return array Array of ability names matching the specified type.
	 */
	private function discover_abilities_by_type(  $type = 'tool' ): array {
		$filtered = array();

		$abilities = blu_get_abilities_by_category( 'blu-mcp' );
		foreach ( $abilities as $ability ) {
			$ability_name = $ability->get_name();
			$meta         = $ability->get_meta();

			$public = $meta['mcp']['public'] ?? true;
			// Skip if not publicly exposed
			if ( !$public  ) {
				continue;
			}

			// Get the type (defaults to 'tool' if not specified)
			$ability_type = $meta['mcp']['type'] ?? 'tool';

			// Add to filtered list if type matches
			if ( $ability_type !== $type ) {
				continue;
			}

			$filtered[] = $ability_name;
		}

		return $filtered;
	}
}
