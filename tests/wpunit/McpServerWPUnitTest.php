<?php

namespace BLU;

/**
 * Tests for McpServer.
 *
 * @covers \BLU\McpServer
 */
class McpServerWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Constructor registers mcp_adapter_init action.
	 *
	 * @return void
	 */
	public function test_constructor_registers_mcp_adapter_init() {
		$server = new McpServer();
		$this->assertNotFalse( has_action( 'mcp_adapter_init', array( $server, 'register_server' ) ) );
	}

	/**
	 * Constructor registers wp_abilities_api_init action.
	 *
	 * @return void
	 */
	public function test_constructor_registers_wp_abilities_api_init() {
		$server = new McpServer();
		$this->assertNotFalse( has_action( 'wp_abilities_api_init', array( $server, 'register_abilities' ) ) );
	}

	/**
	 * Constructor registers wp_abilities_api_categories_init action.
	 *
	 * @return void
	 */
	public function test_constructor_registers_wp_abilities_api_categories_init() {
		$server = new McpServer();
		$this->assertNotFalse( has_action( 'wp_abilities_api_categories_init', array( $server, 'register_ability_categories' ) ) );
	}
}
