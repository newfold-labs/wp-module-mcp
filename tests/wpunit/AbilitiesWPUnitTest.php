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
	 * Verifies SiteInfo can be instantiated without error.
	 *
	 * @return void
	 */
	public function test_site_info_ability_instantiates() {
		$ability = new SiteInfo();
		$this->assertInstanceOf( SiteInfo::class, $ability );
	}
}
