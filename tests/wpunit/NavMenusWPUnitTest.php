<?php

namespace BLU;

use BLU\Abilities\NavMenus;

/**
 * Classic navigation menu ability regression tests.
 *
 * @covers \BLU\Abilities\NavMenus
 */
class NavMenusWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * ID of the test page.
	 *
	 * @var int
	 */
	private $page_id;
	/**
	 * ID of the test menu.
	 *
	 * @var int
	 */
	private $menu_id;
	/**
	 * Whether abilities were registered by this test.
	 *
	 * @var bool
	 */
	private $registered = false;

	/** Create a page and classic navigation menu. */
	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$categories = \WP_Ability_Categories_Registry::get_instance();
		if ( ! $categories->is_registered( 'blu-mcp' ) ) {
			$categories->register( 'blu-mcp', array( 'label' => 'Bluehost MCP' ) );
		}
		$this->page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_title'  => 'AI Eval Landing Page',
				'post_status' => 'publish',
			)
		);
		$this->menu_id = wp_create_nav_menu( 'AI Eval Primary' );
		if ( ! \WP_Abilities_Registry::get_instance()->is_registered( 'blu/add-page-to-menu' ) ) {
			$register = static function () {
				new NavMenus();
			};
			add_action( 'wp_abilities_api_init', $register );
			do_action( 'wp_abilities_api_init', \WP_Abilities_Registry::get_instance() );
			remove_action( 'wp_abilities_api_init', $register );
			$this->registered = true;
		}
	}

	/** Remove test abilities and menu. */
	public function tear_down(): void {
		if ( $this->registered ) {
			blu_unregister_ability( 'blu/add-page-to-menu' );
			blu_unregister_ability( 'blu/remove-page-from-menu' );
		}
		if ( $this->menu_id && ! is_wp_error( $this->menu_id ) ) {
			wp_delete_nav_menu( $this->menu_id );
		}
		parent::tear_down();
	}

	/** Add and remove the exact page link using menu and page names. */
	public function test_add_and_remove_page_link(): void {
		$input = array(
			'menu' => 'AI Eval Primary',
			'page' => 'AI Eval Landing Page',
		);
		$added = blu_get_ability( 'blu/add-page-to-menu' )->execute( $input );
		$this->assertContains( $added['statusCode'], array( 200, 201 ) );
		$items = wp_get_nav_menu_items( $this->menu_id );
		$this->assertCount( 1, $items );
		$this->assertSame( (int) $this->page_id, (int) $items[0]->object_id );

		$removed = blu_get_ability( 'blu/remove-page-from-menu' )->execute( $input );
		$this->assertSame( 200, $removed['statusCode'] );
		$this->assertEmpty( wp_get_nav_menu_items( $this->menu_id ) );
	}

	/** Adding the same page twice must not create duplicate menu links. */
	public function test_adding_page_twice_is_idempotent(): void {
		$input = array(
			'menu' => 'AI Eval Primary',
			'page' => 'AI Eval Landing Page',
		);
		blu_get_ability( 'blu/add-page-to-menu' )->execute( $input );
		$second = blu_get_ability( 'blu/add-page-to-menu' )->execute( $input );
		$this->assertSame( 200, $second['statusCode'] );
		$this->assertCount( 1, wp_get_nav_menu_items( $this->menu_id ) );
	}
}
