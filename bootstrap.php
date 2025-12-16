<?php

use BLU\McpServer;
use BLU\Validation\McpValidation;
use Bluehost\Plugin\WP\MCP\Core\McpAdapter;


if ( function_exists( 'add_action' ) ) {

	add_action(
		'plugins_loaded',
		function () {
			// Initialize MCP adapter (required to register rest_api_init hook).
			McpAdapter::instance();

			// Initialize authentication handlers (hooks into determine_current_user and rest_authentication_errors).
			// This must happen early, before any REST requests are processed.
			McpValidation::instance()->init();

			// Initialize MCP server (registers routes and tools).
			new McpServer();
		}
	);

}