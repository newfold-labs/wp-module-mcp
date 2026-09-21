<?php

namespace BLU;

use BLU\Abilities\CustomPostTypes;

/**
 * Permission tests for the post-type abilities (PRESS0-5133).
 *
 * These abilities reach for posts directly rather than through the REST
 * controllers, so they inherit none of WordPress's checks. They used to gate
 * on a blanket `edit_posts` and then act on whatever ID they were handed,
 * which let a user who may edit their own items read, rewrite and permanently
 * destroy anyone else's.
 *
 * Two layers are pinned here. The coarse gate has to ask for the post type's
 * own capability, because a type with a custom `capability_type` does not use
 * `edit_posts` at all. The per-object check has to run even when the coarse
 * gate passes, which is the only thing standing between an author and another
 * author's work on a type that does use the generic capabilities.
 *
 * @covers \BLU\Abilities\CustomPostTypes
 * @covers ::blu_can_use_post_type
 * @covers ::blu_current_user_can_act_on_post
 * @covers ::blu_filter_posts_by_read_permission
 */
class CustomPostTypePermissionsWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Post type with its own capability_type, so `edit_posts` is the wrong question.
	 */
	const OWN_CAPS_CPT = 'bmcp_ledger';

	/**
	 * Names of abilities registered during tests that need cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array();

	/**
	 * Administrator who owns the content under test.
	 *
	 * @var int
	 */
	private $owner_id;

	/**
	 * Author who should be kept away from it.
	 *
	 * @var int
	 */
	private $author_id;

	/**
	 * Set up abilities, the fixture post type and the two users.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		$this->owner_id  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $this->owner_id );

		$this->ensure_category();
		$this->register_fixture_post_type();
		$this->register_cpt_abilities();
	}

	/**
	 * Tear down abilities and the fixture post type.
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
		unregister_post_type( self::OWN_CAPS_CPT );
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
	 * A post type whose capabilities are its own, the way WooCommerce products are.
	 *
	 * @return void
	 */
	private function register_fixture_post_type(): void {
		register_post_type(
			self::OWN_CAPS_CPT,
			array(
				'public'          => true,
				'show_in_rest'    => true,
				'capability_type' => array( 'ledger', 'ledgers' ),
				'map_meta_cap'    => true,
				'labels'          => array(
					'name'          => 'Ledgers',
					'singular_name' => 'Ledger',
				),
			)
		);
	}

	/**
	 * Register the post-type abilities on the correct action.
	 *
	 * @return void
	 */
	private function register_cpt_abilities(): void {
		$cb = function () {
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
			'blu/list-post-types',
			'blu/cpt-search',
			'blu/get-cpt',
			'blu/add-cpt',
			'blu/update-cpt',
			'blu/delete-cpt',
		);
	}

	/**
	 * Create a post owned by someone.
	 *
	 * @param string $title     Post title.
	 * @param string $status    Post status.
	 * @param int    $author_id Owner.
	 * @param string $post_type Post type.
	 *
	 * @return int
	 */
	private function make_post( string $title, string $status, int $author_id, string $post_type = 'post' ): int {
		$post_id = self::factory()->post->create(
			array(
				'post_title'  => $title,
				'post_status' => 'trash' === $status ? 'publish' : $status,
				'post_type'   => $post_type,
				'post_author' => $author_id,
			)
		);
		if ( 'trash' === $status ) {
			wp_trash_post( $post_id );
		}

		return (int) $post_id;
	}

	/**
	 * Run an ability and describe the outcome as allowed or refused.
	 *
	 * @param string $ability Ability name.
	 * @param array  $input   Ability input.
	 *
	 * @return string One of "gate" (refused by permission_callback), "403"
	 *                (refused per object), or "allowed".
	 */
	private function outcome( string $ability, array $input ): string {
		$result = blu_get_ability( $ability )->execute( $input );
		if ( is_wp_error( $result ) ) {
			return 'gate';
		}

		return 403 === $result['statusCode'] ? '403' : 'allowed';
	}

	/**
	 * Search and report whether one id came back.
	 *
	 * @param array $input   Ability input.
	 * @param int   $post_id Id to look for.
	 *
	 * @return bool
	 */
	private function search_sees( array $input, int $post_id ): bool {
		$result = blu_get_ability( 'blu/cpt-search' )->execute( $input );
		if ( is_wp_error( $result ) ) {
			return false;
		}
		foreach ( (array) ( $result['message']['results'] ?? array() ) as $entry ) {
			if ( is_array( $entry ) && (int) ( $entry['id'] ?? 0 ) === $post_id ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * A post type with its own capabilities must be gated on those, not on the
	 * generic ones. An author holds `edit_posts` and not `edit_ledgers`.
	 *
	 * @return void
	 */
	public function test_coarse_gate_uses_the_post_types_own_capability(): void {
		$item_id = $this->make_post( 'Owner Ledger', 'publish', $this->owner_id, self::OWN_CAPS_CPT );
		wp_set_current_user( $this->author_id );

		$this->assertTrue( current_user_can( 'edit_posts' ), 'Precondition: an author holds the generic capability.' );
		$this->assertFalse( current_user_can( 'edit_ledgers' ), 'Precondition: an author does not hold the type capability.' );

		$this->assertSame(
			'gate',
			$this->outcome(
				'blu/get-cpt',
				array(
					'post_type' => self::OWN_CAPS_CPT,
					'id'        => $item_id,
				)
			)
		);
		$this->assertSame(
			'gate',
			$this->outcome(
				'blu/update-cpt',
				array(
					'post_type' => self::OWN_CAPS_CPT,
					'id'        => $item_id,
					'title'     => 'Rewritten',
				)
			)
		);
		$this->assertSame(
			'gate',
			$this->outcome(
				'blu/delete-cpt',
				array(
					'post_type' => self::OWN_CAPS_CPT,
					'id'        => $item_id,
				)
			)
		);
	}

	/**
	 * On a type that does use the generic capabilities the coarse gate passes,
	 * so only the per-object check can refuse. Reading, rewriting and deleting
	 * another user's item must all be refused, and `blu/delete-cpt` force
	 * deletes past the trash, so a miss here is unrecoverable.
	 *
	 * @return void
	 */
	public function test_per_object_check_refuses_another_users_item(): void {
		$post_id = $this->make_post( 'Owner Post', 'publish', $this->owner_id );
		wp_set_current_user( $this->author_id );

		$this->assertTrue( current_user_can( 'edit_posts' ), 'Precondition: the coarse gate passes.' );
		$this->assertFalse( current_user_can( 'edit_post', $post_id ), 'Precondition: core refuses this object.' );

		$this->assertSame(
			'403',
			$this->outcome(
				'blu/get-cpt',
				array(
					'post_type' => 'post',
					'id'        => $post_id,
				)
			)
		);
		$this->assertSame(
			'403',
			$this->outcome(
				'blu/update-cpt',
				array(
					'post_type' => 'post',
					'id'        => $post_id,
					'title'     => 'Rewritten',
				)
			)
		);
		$this->assertSame(
			'403',
			$this->outcome(
				'blu/delete-cpt',
				array(
					'post_type' => 'post',
					'id'        => $post_id,
				)
			)
		);

		$this->assertSame( 'Owner Post', get_post_field( 'post_title', $post_id ), 'The item must be untouched.' );
		$this->assertNotNull( get_post( $post_id ), 'The item must still exist.' );
	}

	/**
	 * The checks must not lock people out of their own content.
	 *
	 * @return void
	 */
	public function test_caller_can_still_act_on_their_own_item(): void {
		wp_set_current_user( $this->author_id );
		$post_id = $this->make_post( 'Author Post', 'publish', $this->author_id );

		$this->assertSame(
			'allowed',
			$this->outcome(
				'blu/get-cpt',
				array(
					'post_type' => 'post',
					'id'        => $post_id,
				)
			)
		);
		$this->assertSame(
			'allowed',
			$this->outcome(
				'blu/update-cpt',
				array(
					'post_type' => 'post',
					'id'        => $post_id,
					'title'     => 'Author Renamed',
				)
			)
		);
		$this->assertSame( 'Author Renamed', get_post_field( 'post_title', $post_id ) );
	}

	/**
	 * Administrators are what the Hiive JWT transport authenticates as, so
	 * every path has to stay open for them, including on other users' content.
	 *
	 * @return void
	 */
	public function test_an_administrator_is_unaffected(): void {
		$post_id   = $this->make_post( 'Their Post', 'publish', $this->author_id );
		$ledger_id = $this->make_post( 'Their Ledger', 'publish', $this->author_id, self::OWN_CAPS_CPT );
		$draft_id  = $this->make_post( 'Their Draft', 'draft', $this->author_id );
		$doomed_id = $this->make_post( 'Their Doomed Post', 'publish', $this->author_id );

		wp_set_current_user( $this->owner_id );

		$this->assertSame(
			'allowed',
			$this->outcome(
				'blu/get-cpt',
				array(
					'post_type' => 'post',
					'id'        => $post_id,
				)
			)
		);
		$this->assertSame(
			'allowed',
			$this->outcome(
				'blu/get-cpt',
				array(
					'post_type' => self::OWN_CAPS_CPT,
					'id'        => $ledger_id,
				)
			)
		);
		$this->assertSame(
			'allowed',
			$this->outcome(
				'blu/update-cpt',
				array(
					'post_type' => 'post',
					'id'        => $post_id,
					'title'     => 'Admin Renamed',
				)
			)
		);
		$this->assertSame(
			'allowed',
			$this->outcome(
				'blu/delete-cpt',
				array(
					'post_type' => 'post',
					'id'        => $doomed_id,
				)
			)
		);
		$this->assertTrue(
			$this->search_sees(
				array(
					'post_type' => 'post',
					'search'    => 'Their Draft',
					'status'    => 'draft',
				),
				$draft_id
			),
			'An administrator must still see other users\' drafts.'
		);
	}

	/**
	 * Search must not hand over another user's unpublished work, including via
	 * `status=any`, which WP_Query's own `perm` handling does not cover.
	 *
	 * @return void
	 */
	public function test_search_hides_another_users_non_public_items(): void {
		$draft_id   = $this->make_post( 'Hidden Draft', 'draft', $this->owner_id );
		$private_id = $this->make_post( 'Hidden Private', 'private', $this->owner_id );
		$trashed_id = $this->make_post( 'Hidden Trashed', 'trash', $this->owner_id );

		wp_set_current_user( $this->author_id );

		$this->assertFalse(
			$this->search_sees(
				array(
					'post_type' => 'post',
					'search'    => 'Hidden Draft',
					'status'    => 'draft',
				),
				$draft_id
			)
		);
		$this->assertFalse(
			$this->search_sees(
				array(
					'post_type' => 'post',
					'search'    => 'Hidden Private',
					'status'    => 'private',
				),
				$private_id
			)
		);
		$this->assertFalse(
			$this->search_sees(
				array(
					'post_type' => 'post',
					'search'    => 'Hidden Trashed',
					'status'    => 'trash',
				),
				$trashed_id
			)
		);
		$this->assertFalse(
			$this->search_sees(
				array(
					'post_type' => 'post',
					'search'    => 'Hidden Draft',
					'status'    => 'any',
				),
				$draft_id
			),
			'status=any must not slip past the filter.'
		);
		$this->assertFalse(
			$this->search_sees(
				array(
					'post_type' => 'post',
					'search'    => 'Hidden Trashed',
				),
				$trashed_id
			),
			'The trash fallback must not slip past the filter.'
		);
	}

	/**
	 * The filter is about permission, not about hiding the site. Another user's
	 * published work stays visible, which is what the REST-backed searches do,
	 * and the caller's own drafts stay visible too.
	 *
	 * @return void
	 */
	public function test_search_still_shows_public_items_and_the_callers_own_drafts(): void {
		$published_id = $this->make_post( 'Visible Published', 'publish', $this->owner_id );

		wp_set_current_user( $this->author_id );
		$own_draft_id = $this->make_post( 'Visible Own Draft', 'draft', $this->author_id );

		$this->assertTrue(
			$this->search_sees(
				array(
					'post_type' => 'post',
					'search'    => 'Visible Published',
				),
				$published_id
			),
			'Another user\'s published post must stay visible.'
		);
		$this->assertTrue(
			$this->search_sees(
				array(
					'post_type' => 'post',
					'search'    => 'Visible Own Draft',
					'status'    => 'draft',
				),
				$own_draft_id
			),
			'The caller\'s own draft must stay visible.'
		);
	}

	/**
	 * A filtered page must not report the unfiltered total, or the caller is
	 * told about items it cannot see.
	 *
	 * @return void
	 */
	public function test_totals_describe_what_the_caller_actually_received(): void {
		$this->make_post( 'Countable Draft One', 'draft', $this->owner_id );
		$this->make_post( 'Countable Draft Two', 'draft', $this->owner_id );

		wp_set_current_user( $this->author_id );

		$result = blu_get_ability( 'blu/cpt-search' )->execute(
			array(
				'post_type' => 'post',
				'search'    => 'Countable Draft',
				'status'    => 'draft',
			)
		);

		$this->assertSame( array(), $result['message']['results'] );
		$this->assertSame( 0, $result['message']['total'], 'The total must not count items that were filtered out.' );
	}
}
