<?php

namespace BLU;

use BLU\Abilities\CustomPostTypes;
use BLU\Abilities\Pages;
use BLU\Abilities\Posts;

/**
 * Regression tests for PRESS0-4902, "AI Help Chat: Loses post state after delete".
 *
 * Trashing keeps a post's title but moves it out of every status the search
 * abilities query by default, so looking an item up by name straight after a
 * delete returned nothing and the caller concluded it had never existed. That
 * is the only lookup path a deletion has, because targets resolve by search and
 * not by raw ID, so the "already in the trash" recovery was unreachable.
 *
 * These tests pin both halves of the contract: a targeted search finds the
 * trashed item and reports its `trash` status, and an unfiltered listing still
 * does not surface trash.
 *
 * @covers \BLU\Abilities\Posts
 * @covers \BLU\Abilities\Pages
 * @covers ::blu_should_retry_in_trash
 */
class TrashSearchFallbackWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Fixture post type used by the capability test.
	 */
	const CPT_SLUG = 'bmcp_trashbook';

	/**
	 * Names of abilities registered during tests that need cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array();

	/**
	 * Set up: ensure abilities API exists, log in as admin, register category and abilities.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->ensure_category();
		$this->register_fixture_post_type();
		$this->register_abilities();
	}

	/**
	 * Register the custom post type the capability test searches.
	 *
	 * @return void
	 */
	private function register_fixture_post_type(): void {
		register_post_type(
			self::CPT_SLUG,
			array(
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => 'trashbooks',
				'labels'       => array(
					'name'          => 'Trash Books',
					'singular_name' => 'Trash Book',
				),
			)
		);
	}

	/**
	 * Register the Posts and Pages abilities.
	 *
	 * Abilities must be registered on the `wp_abilities_api_init` action, which has
	 * usually already fired by the time a test runs, so instantiating the classes
	 * directly raises a `_doing_it_wrong` notice and registers nothing. Hook the
	 * constructors on, fire the action if it has not run yet, then unhook.
	 *
	 * @return void
	 */
	private function register_abilities(): void {
		$cb = function () {
			new Posts();
			new Pages();
			new CustomPostTypes();
		};
		add_action( 'wp_abilities_api_init', $cb, 10 );

		$count_before = did_action( 'wp_abilities_api_init' );
		$registry     = \WP_Abilities_Registry::get_instance();
		if ( $registry && did_action( 'wp_abilities_api_init' ) === $count_before ) {
			do_action( 'wp_abilities_api_init', $registry );
		}
		remove_action( 'wp_abilities_api_init', $cb, 10 );

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
	 * Tear down: unregister abilities registered by these tests.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$registry = \WP_Abilities_Registry::get_instance();
		foreach ( $this->registered_abilities as $name ) {
			if ( $registry && $registry->is_registered( $name ) ) {
				blu_unregister_ability( $name );
			}
		}
		$this->registered_abilities = array();
		unregister_post_type( self::CPT_SLUG );
		parent::tear_down();
	}

	/**
	 * Ensure the blu-mcp ability category exists.
	 *
	 * @return void
	 */
	private function ensure_category(): void {
		$registry = \WP_Ability_Categories_Registry::get_instance();
		if ( ! $registry || $registry->is_registered( 'blu-mcp' ) ) {
			return;
		}
		$registry->register(
			'blu-mcp',
			array(
				'label'       => 'Bluehost MCP',
				'description' => 'Bluehost-specific abilities for use with MCP',
			)
		);
	}

	/**
	 * Execute an ability and return its result list.
	 *
	 * @param string $ability Ability name.
	 * @param array  $input   Ability input.
	 *
	 * @return array The `message` payload, or an empty array when it is not a list.
	 */
	private function search( string $ability, array $input ): array {
		$result = blu_get_ability( $ability )->execute( $input );
		$this->assertIsArray( $result, "$ability returned a non-array result." );
		return is_array( $result['message'] ) ? $result['message'] : array();
	}

	/**
	 * Find one entry by post ID in a search result list.
	 *
	 * @param array $results Result list.
	 * @param int   $post_id Post ID to look for.
	 *
	 * @return array|null The matching entry, or null.
	 */
	private function find_by_id( array $results, int $post_id ): ?array {
		foreach ( $results as $entry ) {
			if ( is_array( $entry ) && (int) ( $entry['id'] ?? 0 ) === $post_id ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * The ticket's exact path: rename a post, trash it, then look it up by its
	 * current title. Before the fix this returned an empty list and the chat
	 * reported the post as nonexistent.
	 *
	 * @return void
	 */
	public function test_trashed_post_is_found_by_title_and_reports_trash_status(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'AI Workflow Post',
				'post_status' => 'publish',
			)
		);
		wp_update_post(
			array(
				'ID'         => $post_id,
				'post_title' => 'AI Workflow Updated',
			)
		);
		wp_trash_post( $post_id );

		$results = $this->search( 'blu/posts-search', array( 'search' => 'AI Workflow Updated' ) );
		$found   = $this->find_by_id( $results, $post_id );

		$this->assertNotNull( $found, 'A trashed post must still be findable by its title.' );
		$this->assertSame( 'trash', $found['status'], 'The result must carry the trash status so the caller can offer restore or force-delete.' );
	}

	/**
	 * The trash retry must not widen an ordinary listing.
	 *
	 * @return void
	 */
	public function test_listing_without_a_search_term_does_not_surface_trash(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Listing Should Hide This',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $post_id );

		$results = $this->search( 'blu/posts-search', array( 'per_page' => 100 ) );

		$this->assertNull( $this->find_by_id( $results, $post_id ), 'An unfiltered listing must not include trashed posts.' );
	}

	/**
	 * A search that matches a live post must return only that post. The retry
	 * fires only when the first query came back empty.
	 *
	 * @return void
	 */
	public function test_search_matching_a_live_post_does_not_pull_in_trashed_matches(): void {
		$live_id = self::factory()->post->create(
			array(
				'post_title'  => 'Quarterly Report Live',
				'post_status' => 'publish',
			)
		);
		$dead_id = self::factory()->post->create(
			array(
				'post_title'  => 'Quarterly Report Dead',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $dead_id );

		$results = $this->search( 'blu/posts-search', array( 'search' => 'Quarterly Report Live' ) );

		$this->assertNotNull( $this->find_by_id( $results, $live_id ), 'The live post must still be returned.' );
		$this->assertNull( $this->find_by_id( $results, $dead_id ), 'A trashed post must not appear when live results exist.' );
	}

	/**
	 * An explicit status filter is honored as given. The retry must not
	 * override a caller who asked for specific statuses.
	 *
	 * @return void
	 */
	public function test_explicit_status_filter_is_not_widened_to_trash(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Explicit Status Case',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $post_id );

		$results = $this->search(
			'blu/posts-search',
			array(
				'search' => 'Explicit Status Case',
				'status' => 'draft',
			)
		);

		$this->assertSame( array(), $results, 'An explicit status=draft search must stay empty rather than fall back to trash.' );
	}

	/**
	 * Asking for the trash directly keeps working.
	 *
	 * @return void
	 */
	public function test_explicit_trash_status_still_returns_trashed_posts(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Explicit Trash Case',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $post_id );

		$results = $this->search(
			'blu/posts-search',
			array(
				'search' => 'Explicit Trash Case',
				'status' => 'trash',
			)
		);

		$this->assertNotNull( $this->find_by_id( $results, $post_id ), 'An explicit status=trash search must return the trashed post.' );
	}

	/**
	 * A title that matches nothing anywhere still reports nothing.
	 *
	 * @return void
	 */
	public function test_absent_title_returns_no_results(): void {
		$results = $this->search( 'blu/posts-search', array( 'search' => 'No Such Post Exists Anywhere 8f2c' ) );

		$this->assertSame( array(), $results, 'A genuinely absent title must return an empty list.' );
	}

	/**
	 * Pages share the delete-and-look-up flow, so they share the contract.
	 *
	 * @return void
	 */
	public function test_trashed_page_is_found_by_title_and_reports_trash_status(): void {
		$page_id = self::factory()->post->create(
			array(
				'post_title'  => 'Retired Landing Page',
				'post_status' => 'publish',
				'post_type'   => 'page',
			)
		);
		wp_trash_post( $page_id );

		$results = $this->search( 'blu/pages-search', array( 'search' => 'Retired Landing Page' ) );
		$found   = $this->find_by_id( $results, $page_id );

		$this->assertNotNull( $found, 'A trashed page must still be findable by its title.' );
		$this->assertSame( 'trash', $found['status'], 'The page result must carry the trash status.' );
	}

	/**
	 * A retry that fails must not replace the original response. Turning an
	 * empty-but-successful search into a 4xx the caller never caused would be a
	 * worse answer than "nothing matched".
	 *
	 * @return void
	 */
	public function test_a_failing_trash_retry_leaves_the_empty_success_intact(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Retry Failure Case',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $post_id );

		$fail_trash_query = static function ( $result, $server, $request ) {
			if ( '/wp/v2/posts' === $request->get_route()
				&& false !== strpos( (string) $request->get_param( 'status' ), 'trash' ) ) {
				return new \WP_Error( 'rest_forbidden_status', 'Status is forbidden.', array( 'status' => 403 ) );
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $fail_trash_query, 10, 3 );
		$result = blu_get_ability( 'blu/posts-search' )->execute( array( 'search' => 'Retry Failure Case' ) );
		remove_filter( 'rest_pre_dispatch', $fail_trash_query, 10 );

		$this->assertSame( 200, $result['statusCode'], 'A failed retry must not surface its own error status.' );
		$this->assertSame( array(), $result['message'], 'A failed retry must leave the original empty result in place.' );
	}

	/**
	 * The same guard via a route that really happens: asking for a page past
	 * the end of the trashed set makes the retry return
	 * `rest_post_invalid_page_number`, which must not escape.
	 *
	 * @return void
	 */
	public function test_paged_search_past_the_end_does_not_surface_a_retry_error(): void {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => 'Paged Retry Case',
				'post_status' => 'publish',
			)
		);
		wp_trash_post( $post_id );

		$result = blu_get_ability( 'blu/posts-search' )->execute(
			array(
				'search'   => 'Paged Retry Case',
				'page'     => 2,
				'per_page' => 10,
			)
		);

		$this->assertSame( 200, $result['statusCode'], 'A page past the end must not return the retry\'s page-number error.' );
		$this->assertSame( array(), $result['message'] );
	}

	/**
	 * `blu/cpt-search` runs a bare WP_Query, which applies no capability check
	 * of its own, so the retry scopes itself with `perm`. An author must not be
	 * handed another user's trashed item just by searching for its title.
	 *
	 * @return void
	 */
	public function test_cpt_trash_retry_does_not_expose_another_users_trashed_item(): void {
		$owner_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$item_id  = self::factory()->post->create(
			array(
				'post_title'  => 'Someone Elses Trashed Book',
				'post_status' => 'publish',
				'post_type'   => self::CPT_SLUG,
				'post_author' => $owner_id,
			)
		);
		wp_trash_post( $item_id );

		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author_id );

		$result  = blu_get_ability( 'blu/cpt-search' )->execute(
			array(
				'post_type' => self::CPT_SLUG,
				'search'    => 'Someone Elses Trashed Book',
			)
		);
		$results = $result['message']['results'] ?? array();

		$this->assertNull(
			$this->find_by_id( $results, $item_id ),
			'The trash retry must not return an item the current user cannot edit.'
		);
	}

	/**
	 * The owner of a trashed CPT item still finds it, so the `perm` scoping
	 * does not defeat the fix it guards.
	 *
	 * @return void
	 */
	public function test_cpt_trash_retry_still_finds_the_callers_own_trashed_item(): void {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author_id );

		$item_id = self::factory()->post->create(
			array(
				'post_title'  => 'My Own Trashed Book',
				'post_status' => 'publish',
				'post_type'   => self::CPT_SLUG,
				'post_author' => $author_id,
			)
		);
		wp_trash_post( $item_id );

		$result  = blu_get_ability( 'blu/cpt-search' )->execute(
			array(
				'post_type' => self::CPT_SLUG,
				'search'    => 'My Own Trashed Book',
			)
		);
		$results = $result['message']['results'] ?? array();
		$found   = $this->find_by_id( $results, $item_id );

		$this->assertNotNull( $found, 'The caller must still find their own trashed item.' );
		$this->assertSame( 'trash', $found['status'] );
	}

	/**
	 * The retry predicate is the shared gate for all four search abilities, so
	 * pin its decisions directly.
	 *
	 * @return void
	 */
	public function test_retry_predicate_only_fires_for_an_empty_unfiltered_term_search(): void {
		$this->assertTrue(
			blu_should_retry_in_trash( array( 'search' => 'thing' ), array() ),
			'A term search that returned nothing should retry.'
		);
		$this->assertFalse(
			blu_should_retry_in_trash( array( 'search' => 'thing' ), array( array( 'id' => 1 ) ) ),
			'A term search with live results should not retry.'
		);
		$this->assertFalse(
			blu_should_retry_in_trash( array( 'per_page' => 10 ), array() ),
			'A listing with no search term should not retry.'
		);
		$this->assertFalse(
			blu_should_retry_in_trash(
				array(
					'search' => 'thing',
					'status' => 'draft',
				),
				array()
			),
			'A caller-supplied status should not be widened.'
		);
		$this->assertFalse(
			blu_should_retry_in_trash( array( 'search' => '   ' ), array() ),
			'A whitespace-only search term is not a targeted lookup.'
		);
		$this->assertFalse(
			blu_should_retry_in_trash( null, array() ),
			'Non-array input should not retry.'
		);
	}
}
