<?php

// Do not allow multiple copies of the module to be active
if ( defined( 'BLU_MCP_MODULE_VERSION' ) ) {
	return;
}

define( 'BLU_MCP_MODULE_VERSION', '1.0.0' );

// Initialize the blu module.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/includes/BLUMcp.php';

// Initialize BLU MCP
if ( function_exists( 'add_action' ) ) {
	add_action(
		'plugins_loaded',
		function () {
			// Define module constants
			if ( ! defined( 'BLU_MCP_PLUGIN_DIR' ) ) {
				define( 'BLU_MCP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
			}
			if ( ! defined( 'BLU_MCP_PLUGIN_URL' ) ) {
				define( 'BLU_MCP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
			}
			if ( ! defined( 'BLU_MCP_PLUGIN_BASENAME' ) ) {
				define( 'BLU_MCP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
			}

			// Kick things off
			new BLUMcp();
		}
	);
}