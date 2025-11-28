<?php

use BLU\McpServer;
use BLU\SessionAuthenticator;
use Bluehost\Plugin\WP\MCP\Core\McpAdapter;

if ( function_exists( 'add_action' ) ) {

	add_action(
		'plugins_loaded',
		function () {
			// Check if McpAdapter class is available (prefixed by Strauss)
			if ( ! class_exists( '\Bluehost\Plugin\WP\MCP\Core\McpAdapter' ) ) {
				return;
			}

			// Initialize session-based authentication for MCP endpoints
			// This allows using just the session ID without Basic Auth
			new SessionAuthenticator();

			// Initialize MCP adapter (required to register rest_api_init hook)
			McpAdapter::instance();

			// Initialize MCP server
			new McpServer();
		}
	);

}