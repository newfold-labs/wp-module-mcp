<?php

namespace BLU;

/**
 * Module loading wpunit tests.
 *
 * @coversNothing
 */
class ModuleLoadingWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Verify WordPress factory is available.
	 *
	 * @return void
	 */
	public function test_wordpress_factory_available() {
		$this->assertTrue( function_exists( 'get_option' ) );
		$this->assertNotEmpty( get_option( 'blogname' ) );
	}

	/**
	 * Verify add_action exists (bootstrap uses it).
	 *
	 * @return void
	 */
	public function test_wordpress_hooks_available() {
		$this->assertTrue( function_exists( 'add_action' ) );
		$this->assertTrue( function_exists( 'add_filter' ) );
	}

	/**
	 * Verify McpServer class exists.
	 *
	 * @return void
	 */
	public function test_mcp_server_class_exists() {
		$this->assertTrue( class_exists( 'BLU\McpServer' ) );
	}

	/**
	 * Verify blu_get_abilities function exists.
	 *
	 * @return void
	 */
	public function test_blu_get_abilities_function_exists() {
		$this->assertTrue( function_exists( 'blu_get_abilities' ) );
	}

	/**
	 * Verify blu_get_abilities returns array.
	 *
	 * @return void
	 */
	public function test_blu_get_abilities_returns_array() {
		$abilities = blu_get_abilities();
		$this->assertIsArray( $abilities );
	}
}
