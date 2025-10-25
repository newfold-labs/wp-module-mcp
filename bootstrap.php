<?php

use BLU\McpServer;

if ( function_exists( 'add_action' ) && function_exists( 'did_action' ) ) {

	if ( ! did_action( 'plugins_loaded' ) ) {
		return;
	}

	// Initialize MCP server
	new McpServer();

}