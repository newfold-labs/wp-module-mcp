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
	 * When WP 6.9+ Abilities API is available, abilities must be registered during
	 * wp_abilities_api_init to avoid incorrect usage notices.
	 *
	 * @return void
	 */
	public function test_site_info_ability_instantiates() {
		$ability = null;
		add_action(
			'wp_abilities_api_init',
			function () use ( &$ability ) {
				$ability = new SiteInfo();
			},
			5
		);
		do_action( 'wp_abilities_api_init' );
		$this->assertInstanceOf( SiteInfo::class, $ability );
	}
}
