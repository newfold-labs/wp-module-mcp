<?php

namespace BLU;

use BLU\Abilities\Resources;

/** Tests for the resource ability schemas. */
class ResourcesWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {
	/** The Google taxonomy ability requires a list of patterns. */
	public function test_google_taxonomy_patterns_are_required(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is unavailable.' );
		}
		$categories = \WP_Ability_Categories_Registry::get_instance();
		if ( ! $categories->is_registered( 'blu-mcp' ) ) {
			$categories->register( 'blu-mcp', array( 'label' => 'Bluehost MCP' ) );
		}
		$registered = ! \WP_Abilities_Registry::get_instance()->is_registered( 'blu/google-product-taxonomy' );
		if ( $registered ) {
			$register = static function () {
				new Resources();
			};
			add_action( 'wp_abilities_api_init', $register );
			do_action( 'wp_abilities_api_init', \WP_Abilities_Registry::get_instance() );
			remove_action( 'wp_abilities_api_init', $register );
		}
		try {
			$schema = blu_get_ability( 'blu/google-product-taxonomy' )->get_input_schema();
			$this->assertSame( array( 'patterns' ), $schema['required'] );
			$this->assertArrayNotHasKey( 'required', $schema['properties'] );
		} finally {
			if ( $registered ) {
				blu_unregister_ability( 'blu/google-product-taxonomy' );
			}
		}
	}
}
