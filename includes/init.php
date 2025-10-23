<?php

namespace BLU;

use BLU\Abilities\CustomPostTypes;
use BLU\Abilities\Media;
use BLU\Abilities\Pages;
use BLU\Abilities\Posts;
use BLU\Abilities\RestApiCrud;
use BLU\Abilities\Settings;
use BLU\Abilities\SiteInfo;
use BLU\Abilities\Users;
use BLU\Abilities\WooOrders;
use BLU\Abilities\WooProducts;
use BLU\McpServer;

/**
 * BLU MCP initialization class
 */
class BLU_MCP {

	public function __construct() {
		$this->init();
	}
	
	public function init() {
		add_action( 'abilities_api_categories_init', array( $this, 'register_blu_ability_category' ) );
		add_action( 'abilities_api_init', array( $this, 'initialize_abilities' ) );
		$this->McpServer = new McpServer();
	}

	/**
	 * Register ability category
	 */
	public function register_blu_ability_category() {
		wp_register_ability_category(
			'blu-mcp',
			array(
				'label'       => 'Bluehost MCP',
				'description' => 'Bluehost-specific abilities for use with MCP',
			)
		);
	}

	/**
	 * Initialize abilities
	 */
	public function initialize_abilities() {
		// Initialize all ability classes
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
}
