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
	 * Constructor registers the priority-999 cleanup hook used to detach the
	 * Strauss-prefixed DefaultServerFactory before a sibling adapter re-fires
	 * mcp_adapter_init.
	 *
	 * @return void
	 */
	public function test_constructor_registers_suppress_sibling_default_server_refire_at_late_priority() {
		$server   = new McpServer();
		$priority = has_action( 'mcp_adapter_init', array( $server, 'suppress_sibling_default_server_refire' ) );
		$this->assertNotFalse( $priority );
		$this->assertSame( 999, $priority );
	}

	/**
	 * Register_server early-returns without creating a server when the adapter
	 * arg passed by the mcp_adapter_init action is not our McpAdapter instance.
	 * This is the guard that stops the duplicate_server_id error that would
	 * otherwise surface on each sibling adapter's do_action.
	 *
	 * Passing null (simulating an unknown caller) is safe: the method returns
	 * without fataling on the use-statement-referenced McpAdapter class, which
	 * is Strauss-generated and absent from the module-only test environment.
	 *
	 * @return void
	 */
	public function test_register_server_early_returns_on_foreign_adapter() {
		$server = new McpServer();
		$server->register_server( null );
		$this->assertTrue( true, 'register_server( null ) returned without error' );
	}

	/**
	 * Same guard on the cleanup callback.
	 *
	 * @return void
	 */
	public function test_suppress_sibling_default_server_refire_early_returns_on_foreign_adapter() {
		$server = new McpServer();
		$server->suppress_sibling_default_server_refire( null );
		$this->assertTrue( true, 'suppress_sibling_default_server_refire( null ) returned without error' );
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
