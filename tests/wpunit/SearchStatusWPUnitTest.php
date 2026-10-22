<?php

namespace BLU;

use BLU\Abilities\CustomPostTypes;
use BLU\Abilities\Pages;
use BLU\Abilities\Posts;
use BLU\Abilities\WooProducts;

/**
 * Search defaults and explicit trash lookups used to resolve deleted items.
 */
class SearchStatusWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/** Fixture custom post type. */
	const CPT_SLUG = 'bmcp_status_book';

	/**
	 * Ability names registered by these tests.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array();

	/** Prepare the WordPress user, ability category, and fixture post type. */
	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$categories = \WP_Ability_Categories_Registry::get_instance();
		if ( ! $categories->is_registered( 'blu-mcp' ) ) {
			$categories->register(
				'blu-mcp',
				array(
					'label'       => 'Bluehost MCP',
					'description' => 'Bluehost abilities',
				)
			);
		}
		register_post_type(
			self::CPT_SLUG,
			array(
				'public'       => true,
				'show_in_rest' => true,
			)
		);
		rest_get_server();
	}

	/** Remove fixture abilities and post type after each test. */
	public function tear_down(): void {
		foreach ( $this->registered_abilities as $name ) {
			if ( \WP_Abilities_Registry::get_instance()->is_registered( $name ) ) {
				blu_unregister_ability( $name );
			}
		}
		unregister_post_type( self::CPT_SLUG );
		parent::tear_down();
	}

	/** Register the search abilities on the required Abilities API hook. */
	private function register_search_abilities(): void {
		$registry = \WP_Abilities_Registry::get_instance();
		if ( $registry->is_registered( 'blu/posts-search' ) && $registry->is_registered( 'blu/pages-search' ) && $registry->is_registered( 'blu/cpt-search' ) ) {
			return;
		}
		$register = static function () {
			new Posts();
			new Pages();
			new CustomPostTypes();
		};
		add_action( 'wp_abilities_api_init', $register );
		do_action( 'wp_abilities_api_init', \WP_Abilities_Registry::get_instance() );
		remove_action( 'wp_abilities_api_init', $register );
		$this->registered_abilities = array(
			'blu/posts-search',
			'blu/get-post',
			'blu/add-post',
			'blu/update-post',
			'blu/delete-post',
			'blu/list-categories',
			'blu/add-category',
			'blu/update-category',
			'blu/delete-category',
			'blu/list-tags',
			'blu/add-tag',
			'blu/update-tag',
			'blu/delete-tag',
			'blu/pages-search',
			'blu/get-page',
			'blu/add-page',
			'blu/update-page',
			'blu/delete-page',
			'blu/list-post-types',
			'blu/cpt-search',
			'blu/get-cpt',
			'blu/add-cpt',
			'blu/update-cpt',
			'blu/delete-cpt',
		);
	}

	/**
	 * Verify published default, explicit any and trash searches, and result statuses.
	 *
	 * @dataProvider core_search_cases
	 * @param string $post_type Fixture post type.
	 * @param string $ability   Search ability name.
	 */
	public function test_core_search_defaults_to_publish_and_returns_status( string $post_type, string $ability ): void {
		$this->register_search_abilities();
		$published = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Status Search Published',
				'post_status' => 'publish',
			)
		);
		$draft     = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Status Search Draft',
				'post_status' => 'draft',
			)
		);
		$trashed   = self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_title'  => 'Status Search Deleted',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $trashed );

		$input   = 'blu/cpt-search' === $ability ? array( 'post_type' => self::CPT_SLUG ) : array();
		$default = blu_get_ability( $ability )->execute( $input );
		$rows    = 'blu/cpt-search' === $ability ? $default['message']['results'] : $default['message'];
		$by_id   = array_column( $rows, null, 'id' );
		$this->assertArrayHasKey( $published, $by_id );
		$this->assertSame( 'publish', $by_id[ $published ]['status'] );
		if ( 'blu/cpt-search' !== $ability ) {
			$this->assertSame(
				array(
					'id'     => $published,
					'title'  => 'Status Search Published',
					'status' => 'publish',
					'slug'   => 'status-search-published',
				),
				$by_id[ $published ],
				'Post and page searches should return compact results with plain titles.'
			);
		}
		$this->assertArrayNotHasKey( $draft, $by_id );
		$this->assertArrayNotHasKey( $trashed, $by_id );
		$any  = blu_get_ability( $ability )->execute( array_merge( $input, array( 'status' => 'any' ) ) );
		$rows = 'blu/cpt-search' === $ability ? $any['message']['results'] : $any['message'];
		$this->assertSame( 'draft', array_column( $rows, null, 'id' )[ $draft ]['status'] );

		$explicit = blu_get_ability( $ability )->execute(
			array_merge(
				$input,
				array(
					'search' => 'Status Search Deleted',
					'status' => 'trash',
				)
			)
		);
		$rows     = 'blu/cpt-search' === $ability ? $explicit['message']['results'] : $explicit['message'];
		$by_id    = array_column( $rows, null, 'id' );
		$this->assertArrayHasKey( $trashed, $by_id );
		$this->assertSame( 'trash', $by_id[ $trashed ]['status'] );
		$implicit = blu_get_ability( $ability )->execute( array_merge( $input, array( 'search' => 'Status Search Deleted' ) ) );
		$rows     = 'blu/cpt-search' === $ability ? $implicit['message']['results'] : $implicit['message'];
		$this->assertArrayNotHasKey( $trashed, array_column( $rows, null, 'id' ), 'A search without status must not return deleted items.' );

		$description = blu_get_ability( $ability )->get_input_schema()['properties']['status']['description'];
		$this->assertStringContainsString( 'any', $description );
		$this->assertStringContainsString( 'trash', $description );
		$this->assertStringContainsString( 'future (scheduled)', $description );
		$this->assertStringContainsString( '/wp/v2/statuses', $description );
	}

	/** Ensure the broader CPT default does not reveal another user's draft. */
	public function test_cpt_explicit_any_does_not_expose_another_users_draft(): void {
		$this->register_search_abilities();
		$admin_id  = get_current_user_id();
		$draft_id  = self::factory()->post->create(
			array(
				'post_type'   => self::CPT_SLUG,
				'post_title'  => 'Admin Draft',
				'post_status' => 'draft',
				'post_author' => $admin_id,
			)
		);
		$public_id = self::factory()->post->create(
			array(
				'post_type'   => self::CPT_SLUG,
				'post_title'  => 'Admin Published',
				'post_status' => 'publish',
				'post_author' => $admin_id,
			)
		);
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$own_id    = self::factory()->post->create(
			array(
				'post_type'   => self::CPT_SLUG,
				'post_title'  => 'Own Draft',
				'post_status' => 'draft',
				'post_author' => $author_id,
			)
		);
		wp_set_current_user( $author_id );

		$result = blu_get_ability( 'blu/cpt-search' )->execute(
			array(
				'post_type' => self::CPT_SLUG,
				'status'    => 'any',
			)
		);
		$by_id  = array_column( $result['message']['results'], null, 'id' );
		$this->assertArrayNotHasKey( $draft_id, $by_id );
		$this->assertArrayHasKey( $public_id, $by_id );
		$this->assertArrayHasKey( $own_id, $by_id );
	}

	/** Inaccessible matches must not consume a page or truncate the visible total. */
	public function test_cpt_search_paginates_visible_items(): void {
		$this->register_search_abilities();
		$admin_id        = get_current_user_id();
		$public_id       = self::factory()->post->create(
			array(
				'post_type'   => self::CPT_SLUG,
				'post_status' => 'publish',
				'post_date'   => '2026-01-01 12:00:00',
			)
		);
		$older_public_id = self::factory()->post->create(
			array(
				'post_type'   => self::CPT_SLUG,
				'post_status' => 'publish',
				'post_date'   => '2025-12-31 12:00:00',
			)
		);
		self::factory()->post->create(
			array(
				'post_type'   => self::CPT_SLUG,
				'post_status' => 'draft',
				'post_author' => $admin_id,
				'post_date'   => '2026-01-02 12:00:00',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'author' ) ) );

		$result = blu_get_ability( 'blu/cpt-search' )->execute(
			array(
				'post_type' => self::CPT_SLUG,
				'status'    => 'any',
				'per_page'  => 1,
				'page'      => 1,
			)
		);
		$this->assertSame( array( $public_id ), array_column( $result['message']['results'], 'id' ) );
		$this->assertSame( 2, $result['message']['total'] );
		$this->assertSame( 2, $result['message']['pages'] );
		$second_page = blu_get_ability( 'blu/cpt-search' )->execute(
			array(
				'post_type' => self::CPT_SLUG,
				'status'    => 'any',
				'per_page'  => 1,
				'page'      => 2,
			)
		);
		$this->assertSame( array( $older_public_id ), array_column( $second_page['message']['results'], 'id' ) );
	}

	/** Scheduled posts use the future status slug and appear on explicit request. */
	public function test_posts_search_finds_scheduled_items(): void {
		$this->register_search_abilities();
		$scheduled_id = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_title'  => 'Scheduled Search Item',
				'post_status' => 'future',
				'post_date'   => '2030-01-01 12:00:00',
			)
		);
		$result = blu_get_ability( 'blu/posts-search' )->execute( array( 'status' => 'future' ) );
		$by_id  = array_column( $result['message'], null, 'id' );
		$this->assertSame( 'future', $by_id[ $scheduled_id ]['status'] );
		$this->assertSame( 'Scheduled Search Item', $by_id[ $scheduled_id ]['title'] );
	}

	/** Missing optional REST fields must not raise notices in compact results. */
	public function test_compact_post_search_handles_missing_fields(): void {
		$result = blu_compact_post_search_response(
			array(
				'statusCode' => 200,
				'status'     => 'success',
				'message'    => array( array( 'id' => 42 ) ),
			)
		);
		$this->assertSame( '', $result['message'][0]['title'] );
		$this->assertSame( '', $result['message'][0]['status'] );
		$this->assertSame( '', $result['message'][0]['slug'] );
	}

	/** Search requests should avoid preparing REST fields discarded from results. */
	public function test_posts_search_requests_only_summary_fields(): void {
		$this->register_search_abilities();
		$fields  = null;
		$capture = static function ( $result, $server, $request ) use ( &$fields ) {
			if ( '/wp/v2/posts' === $request->get_route() ) {
				$fields = $request->get_param( '_fields' );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $capture, 10, 3 );
		blu_get_ability( 'blu/posts-search' )->execute( array() );
		remove_filter( 'rest_pre_dispatch', $capture, 10 );
		$this->assertSame( 'id,title,slug,status', $fields );
	}

	/** Administrators respect custom type capabilities when no role holds them. */
	public function test_cpt_explicit_any_respects_custom_capability_types(): void {
		$this->register_search_abilities();
		$slug = 'bmcp_status_ledger';
		register_post_type(
			$slug,
			array(
				'public'          => true,
				'capability_type' => 'ledger',
			)
		);
		$item_id = self::factory()->post->create(
			array(
				'post_type'   => $slug,
				'post_title'  => 'Ledger Draft',
				'post_status' => 'draft',
			)
		);
		$result  = blu_get_ability( 'blu/cpt-search' )->execute(
			array(
				'post_type' => $slug,
				'status'    => 'any',
			)
		);
		unregister_post_type( $slug );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/** Provide the three core post type search cases. */
	public function core_search_cases(): array {
		return array(
			'post' => array( 'post', 'blu/posts-search' ),
			'page' => array( 'page', 'blu/pages-search' ),
			'cpt'  => array( self::CPT_SLUG, 'blu/cpt-search' ),
		);
	}

	/** Verify WooCommerce request status and response status passthrough. */
	public function test_woo_search_passes_publish_any_or_trash_and_preserves_result_status(): void {
		$register = static function () {
			$method = new \ReflectionMethod( WooProducts::class, 'register_product_abilities' );
			$method->setAccessible( true );
			$method->invoke( new WooProducts() );
		};
		add_action( 'wp_abilities_api_init', $register );
		do_action( 'wp_abilities_api_init', \WP_Abilities_Registry::get_instance() );
		remove_action( 'wp_abilities_api_init', $register );
		$this->registered_abilities = array( 'blu/wc-products-search', 'blu/wc-get-product', 'blu/wc-add-product', 'blu/wc-update-product', 'blu/wc-delete-product' );
		$user                       = wp_get_current_user();
		$user->add_cap( 'edit_products' );

		$statuses = array();
		$fake_woo = static function ( $result, $server, $request ) use ( &$statuses ) {
			if ( '/wc/v3/products' !== $request->get_route() ) {
				return $result;
			}
			$status     = $request->get_param( 'status' );
			$statuses[] = $status;
			return new \WP_REST_Response(
				array(
					array(
						'id'     => 42,
						'status' => 'any' === $status ? 'draft' : $status,
					),
				),
				200
			);
		};
		add_filter( 'rest_pre_dispatch', $fake_woo, 10, 3 );
		$default = blu_get_ability( 'blu/wc-products-search' )->execute( array() );
		$any     = blu_get_ability( 'blu/wc-products-search' )->execute( array( 'status' => 'any' ) );
		$trash   = blu_get_ability( 'blu/wc-products-search' )->execute(
			array(
				'search' => 'Deleted Product',
				'status' => 'trash',
			)
		);
		remove_filter( 'rest_pre_dispatch', $fake_woo, 10 );

		$this->assertSame( array( 'publish', 'any', 'trash' ), $statuses );
		$this->assertSame( 'publish', $default['message'][0]['status'] );
		$this->assertSame( 'draft', $any['message'][0]['status'] );
		$this->assertSame( 'trash', $trash['message'][0]['status'] );
		$description = blu_get_ability( 'blu/wc-products-search' )->get_input_schema()['properties']['status']['description'];
		$this->assertStringContainsString( 'any', $description );
		$this->assertStringContainsString( 'trash', $description );
		$this->assertStringContainsString( 'future (scheduled)', $description );
	}
}
