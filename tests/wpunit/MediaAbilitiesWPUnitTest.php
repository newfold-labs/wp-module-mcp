<?php

namespace BLU;

/**
 * Smoke tests for media abilities and WooAnalytics registration.
 *
 * @coversNothing
 */
class MediaAbilitiesWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Verifies blu/search-media is registered as a deprecated alias of list-media.
	 *
	 * @return void
	 */
	public function test_search_media_is_registered_as_alias(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		$search = wp_get_ability( 'blu/search-media' );
		$list   = wp_get_ability( 'blu/list-media' );

		$this->assertNotNull( $search );
		$this->assertNotNull( $list );
		$this->assertStringContainsString( 'deprecated', strtolower( $search->get_description() ) );
		$this->assertStringContainsString( 'blu/list-media', $search->get_description() );
	}

	/**
	 * Verifies WooAnalytics report abilities are registered when WooCommerce is active.
	 *
	 * @return void
	 */
	public function test_woo_analytics_abilities_registered_when_woocommerce_active(): void {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is not active.' );
		}

		$ability = wp_get_ability( 'blu/wc-reports-orders-totals' );

		$this->assertNotNull( $ability );
		$this->assertSame( 'Get WooCommerce Orders Report', $ability->get_label() );
	}
}
