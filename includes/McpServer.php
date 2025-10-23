<?php
declare( strict_types=1 );

namespace BLU;

use WP\MCP\Core\McpAdapter;
use WP\MCP\Infrastructure\ErrorHandling\ErrorLogMcpErrorHandler;
use WP\MCP\Infrastructure\Observability\NullMcpObservabilityHandler;
use WP\MCP\Transport\Http\RestTransport;

/**
 * MCP Server registration for Bluehost abilities.
 */
class McpServer {

	/**
	 * Constructor - registers the MCP server.
	 */
	public function __construct() {
		add_action( 'mcp_adapter_init', array( $this, 'register_server' ) );
	}

	/**
	 * Register the MCP server with all blu abilities.
	 */
	public function register_server(): void {

		// Get all abilities in the blu namespace
		$abilities = blu_get_abilities_by_namespace( 'blu' );

		// Extract ability names
		$ability_names = array();
		foreach ( $abilities as $ability ) {
			// Only register abilities in the blu-mcp category
			if ( $ability->get_category() === 'blu-mcp' ) {
				$ability_names[] = $ability->get_name();
			}
		}

		// Get the MCP adapter instance
		$adapter = McpAdapter::instance();

		// Configure REST transport
		$transports = array(
			RestTransport::class,
		);

		// Create the server
		$adapter->create_server(
			server_id: 'blu-mcp',
			server_route_namespace: 'blu',
			server_route: 'mcp',
			server_name: 'Bluehost MCP Server',
			server_description: 'MCP server exposing Bluehost WordPress abilities',
			server_version: '1.0.0',
			mcp_transports: $transports,
			error_handler: ErrorLogMcpErrorHandler::class,
			observability_handler: NullMcpObservabilityHandler::class,
			tools: $ability_names
		);
	}
}
