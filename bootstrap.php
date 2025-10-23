<?php
// Initialize the blu module.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/init.php';

// Initialize BLU MCP
if ( function_exists( 'add_action' ) ) {
	add_action(
		'plugins_loaded',
		function () {
			new BLU_MCP();
		}
	);
}