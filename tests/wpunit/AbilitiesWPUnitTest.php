<?php

namespace BLU;

use BLU\Abilities\SiteInfo;

/**
 * Tests for MCP ability registration (when wp_register_ability may be unavailable).
 *
 * @covers \BLU\Abilities\SiteInfo
 */
class AbilitiesWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Verifies SiteInfo can be instantiated and registers on wp_abilities_api_init.
	 *
	 * When WP 6.9+ Abilities API is available, the ability category must be
	 * registered on wp_abilities_api_categories_init before abilities that use it.
	 *
	 * @return void
	 */
	public function test_site_info_ability_instantiates() {
		$ability = null;

		// WP 6.9+ requires the category to be registered before abilities that use it.
		add_action(
			'wp_abilities_api_categories_init',
			function () {
				if ( function_exists( 'wp_register_ability_category' ) ) {
					wp_register_ability_category(
						'blu-mcp',
						array(
							'label'       => 'Bluehost MCP',
							'description' => 'Bluehost-specific abilities for use with MCP',
						)
					);
				}
			},
			5
		);
		add_action(
			'wp_abilities_api_init',
			function () use ( &$ability ) {
				$ability = new SiteInfo();
			},
			5
		);

		do_action( 'wp_abilities_api_categories_init' );
		do_action( 'wp_abilities_api_init' );
		$this->assertInstanceOf( SiteInfo::class, $ability );
	}
}
