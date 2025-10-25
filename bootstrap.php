<?php

use BLU\McpServer;

if ( function_exists( 'add_action' ) ) {

	add_action(
		'plugins_loaded',
		function () {
			// Initialize MCP server
			new McpServer();
		}
	);

}