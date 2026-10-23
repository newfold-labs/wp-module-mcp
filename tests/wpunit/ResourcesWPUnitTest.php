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

	/** Taxonomy matches keep Google category IDs and match only the path. */
	public function test_google_taxonomy_filter_returns_ids_and_paths(): void {
		$taxonomy = "187 - Apparel & Accessories > Shoes\n"
			. "1856 - Apparel & Accessories > Shoe Accessories\n"
			. "not a category line\n"
			. "5322 - Sporting Goods > Athletics\n";
		$filter   = new \ReflectionMethod( Resources::class, 'filter_google_taxonomies' );
		$filter->setAccessible( true );
		$resources = ( new \ReflectionClass( Resources::class ) )->newInstanceWithoutConstructor();

		$this->assertSame(
			array(
				array(
					'id'   => 187,
					'path' => 'Apparel & Accessories > Shoes',
				),
			),
			$filter->invoke( $resources, $taxonomy, array( 'shoes' ) )
		);
		$this->assertSame( array(), $filter->invoke( $resources, $taxonomy, array( '5322' ) ) );
		$this->assertSame( array( 5322 ), array_column( $filter->invoke( $resources, $taxonomy, array( '/^sporting/' ) ), 'id' ) );
	}
}
