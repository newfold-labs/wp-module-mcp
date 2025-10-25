<?php

use BLU\McpServer;
use WP\MCP\Core\McpAdapter;

if ( function_exists( 'add_action' ) ) {

	add_action(
		'plugins_loaded',
		function () {
			// Initialize MCP adapter (required to register rest_api_init hook)
			McpAdapter::instance();

			// Initialize MCP server
			new McpServer();
		}
	);

}