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

	/**
	 * The blu-mcp category is registered so callers can attach abilities to it.
	 * Mirrors the wp_register_ability_category contract used by the Abilities API:
	 * the registry must report it as registered after the call.
	 *
	 * @return void
	 */
	public function test_register_ability_categories_registers_blu_mcp() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			$this->markTestSkipped( 'WP Ability Categories API is not available.' );
		}

		// The category is only registerable during the categories_init action; hook in
		// and fire the action manually so the registration runs inside its window.
		$server = new McpServer();
		add_action( 'wp_abilities_api_categories_init', array( $server, 'register_ability_categories' ) );
		do_action( 'wp_abilities_api_categories_init' );

		$registry = \WP_Ability_Categories_Registry::get_instance();
		$this->assertNotNull( $registry );
		$this->assertTrue( $registry->is_registered( 'blu-mcp' ) );
	}

	/**
	 * Every BLU ability class is instantiated by register_abilities. Many early-return
	 * when their backing plugin (WooCommerce, etc.) is absent, but the call itself must
	 * succeed without fatals. We invoke it inside wp_abilities_api_init so that any
	 * blu_register_ability calls fall inside the action window the Abilities API requires.
	 *
	 * @return void
	 */
	public function test_register_abilities_runs_without_fatals() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		$server = new McpServer();
		$called = false;
		$cb     = function () use ( $server, &$called ) {
			$server->register_abilities();
			$called = true;
		};
		add_action( 'wp_abilities_api_init', $cb, 5 );

		$abilities_init_count_before = did_action( 'wp_abilities_api_init' );
		$abilities_registry          = \WP_Abilities_Registry::get_instance();
		if (
			$abilities_registry
			&& did_action( 'wp_abilities_api_init' ) === $abilities_init_count_before
		) {
			do_action( 'wp_abilities_api_init', $abilities_registry );
		}
		remove_action( 'wp_abilities_api_init', $cb, 5 );

		$this->assertTrue( $called, 'register_abilities should have executed inside wp_abilities_api_init.' );
	}

	/**
	 * After register_abilities runs successfully under wp_abilities_api_init, the
	 * gateway tools should be registered on the abilities registry.
	 *
	 * @return void
	 */
	public function test_register_abilities_registers_gateway_tools() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		$server = new McpServer();
		add_action(
			'wp_abilities_api_init',
			function () use ( $server ) {
				$server->register_abilities();
			},
			5
		);

		$abilities_init_count_before = did_action( 'wp_abilities_api_init' );
		$abilities_registry          = \WP_Abilities_Registry::get_instance();
		if (
			$abilities_registry
			&& did_action( 'wp_abilities_api_init' ) === $abilities_init_count_before
		) {
			do_action( 'wp_abilities_api_init', $abilities_registry );
		}

		$this->assertNotNull( blu_get_ability( 'blu/list-abilities' ) );
		$this->assertNotNull( blu_get_ability( 'blu/get-ability-schema' ) );
		$this->assertNotNull( blu_get_ability( 'blu/call-ability' ) );
	}
}
