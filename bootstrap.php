<?php

/**
 * Ensure Composer autoloader is available for this plugin.
 * This makes classes like Firebase\JWT\JWT available.
 */
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

use BLU\McpServer;
use BLU\Validation\McpValidation;
use WP\MCP\Core\McpAdapter;

if ( function_exists( 'add_action' ) ) {

	add_action(
		'plugins_loaded',
		function () {
			// Initialize MCP adapter (required to register rest_api_init hook)
			McpAdapter::instance();

			// Initialize Validation
			new McpValidation();
			// Initialize MCP server
			new McpServer();
		}
	);

}