<?php

namespace BLU;

use BLU\Abilities\CustomPostTypes;

/**
 * Tests for the CPT abilities, focused on friendly post_type resolution and the
 * id-first response projections.
 *
 * The fixture post type deliberately gives the slug, REST base and labels four
 * different strings so a test asserting on one form cannot pass by accident.
 *
 * @covers \BLU\Abilities\CustomPostTypes
 */
class CustomPostTypesWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Slug of the fixture post type registered for these tests.
	 */
	const CPT_SLUG = 'bmcp_book';

	/**
	 * Names of abilities registered during tests that need cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array();

	/**
	 * Set up: ensure the abilities API exists, log in as admin, register the
	 * category, the fixture post type, and the CPT abilities.
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
		$this->register_cpt_abilities();
	}

	/**
	 * Tear down: unregister the abilities and the fixture post type so neither
	 * leaks into other tests.
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

		if ( post_type_exists( self::CPT_SLUG ) ) {
			unregister_post_type( self::CPT_SLUG );
		}

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
	 * Register the fixture post type. Slug, REST base, plural label, singular
	 * label and menu name are all distinct strings.
	 *
	 * @return void
	 */
	private function register_fixture_post_type(): void {
		register_post_type(
			self::CPT_SLUG,
			array(
				'public'       => true,
				'show_in_rest' => true,
				'rest_base'    => 'books',
				'labels'       => array(
					'name'          => 'Books',
					'singular_name' => 'Book',
					'menu_name'     => 'Book Library',
				),
			)
		);
	}

	/**
	 * Register the CPT abilities via the wp_abilities_api_init hook.
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
	 * Create a published fixture post of the fixture post type.
	 *
	 * @param string $title Post title.
	 *
	 * @return int
	 */
	private function create_book( string $title ): int {
		return self::factory()->post->create(
			array(
				'post_type'    => self::CPT_SLUG,
				'post_title'   => $title,
				'post_content' => 'Contents of ' . $title,
				'post_status'  => 'publish',
			)
		);
	}

	/**
	 * Convenience: execute an ability and return the full standardized response.
	 *
	 * @param string $ability Ability name.
	 * @param array  $input   Input arguments.
	 *
	 * @return array
	 */
	private function execute( string $ability, array $input ): array {
		return blu_get_ability( $ability )->execute( $input );
	}

	/**
	 * Every identifier form a caller might reasonably pass for the fixture type.
	 *
	 * @return array<string, array{0:string}>
	 */
	public function post_type_identifier_provider(): array {
		return array(
			'exact slug'        => array( 'bmcp_book' ),
			'rest base'         => array( 'books' ),
			'plural label'      => array( 'Books' ),
			'singular label'    => array( 'Book' ),
			'menu name'         => array( 'Book Library' ),
			'lowercase plural'  => array( 'books' ),
			'uppercase slug'    => array( 'BMCP_BOOK' ),
			'mixed-case label'  => array( 'bOoK' ),
			'surrounding space' => array( '  Books  ' ),
		);
	}

	/**
	 * Every identifier form maps onto the canonical slug.
	 *
	 * @dataProvider post_type_identifier_provider
	 *
	 * @param string $identifier The caller-supplied identifier.
	 *
	 * @return void
	 */
	public function test_resolve_post_type_accepts_identifier_forms( string $identifier ): void {
		$this->assertSame( self::CPT_SLUG, blu_resolve_post_type( $identifier ) );
	}

	/**
	 * Unknown and empty identifiers resolve to null rather than a wrong type.
	 *
	 * @return void
	 */
	public function test_resolve_post_type_returns_null_for_unresolvable_input(): void {
		$this->assertNull( blu_resolve_post_type( 'definitely_not_a_post_type' ) );
		$this->assertNull( blu_resolve_post_type( '' ) );
		$this->assertNull( blu_resolve_post_type( '   ' ) );
	}

	/**
	 * Built-in types still resolve by slug and by label, so the helper does not
	 * regress callers that were already passing "post" or "page".
	 *
	 * @return void
	 */
	public function test_resolve_post_type_handles_built_in_types(): void {
		$this->assertSame( 'post', blu_resolve_post_type( 'post' ) );
		$this->assertSame( 'page', blu_resolve_post_type( 'page' ) );
		$this->assertSame( 'page', blu_resolve_post_type( 'Pages' ) );
	}

	/**
	 * The not-found response is a 400 that names the offending input and lists
	 * the registered types, which is what lets the model self-correct.
	 *
	 * @return void
	 */
	public function test_post_type_not_found_response_lists_available_types(): void {
		$result = blu_post_type_not_found_response( 'Widgets' );

		$this->assertSame( 400, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );
		$this->assertStringContainsString( 'Widgets', $result['message'] );
		$this->assertStringContainsString( self::CPT_SLUG, $result['message'] );
		$this->assertStringContainsString( '"Books"', $result['message'] );
		$this->assertStringContainsString( 'post', $result['message'] );
	}

	/**
	 * The summary projection leads with an integer id and carries the fields the
	 * search response is expected to expose.
	 *
	 * @return void
	 */
	public function test_project_post_summary_leads_with_id(): void {
		$post_id = $this->create_book( 'Dune' );
		$summary = blu_project_post_summary( get_post( $post_id ) );

		$this->assertSame( 'id', array_key_first( $summary ) );
		$this->assertSame( $post_id, $summary['id'] );
		$this->assertIsInt( $summary['id'] );
		$this->assertSame( 'Dune', $summary['title'] );
		$this->assertSame( self::CPT_SLUG, $summary['type'] );
		$this->assertSame( 'publish', $summary['status'] );

		foreach ( array( 'id', 'title', 'status', 'type', 'slug', 'author', 'date', 'modified', 'excerpt', 'link' ) as $key ) {
			$this->assertArrayHasKey( $key, $summary );
		}

		// The projection is slim by design: raw content stays out of list results.
		$this->assertArrayNotHasKey( 'content', $summary );
	}

	/**
	 * The full projection is the summary plus raw content, still id-first.
	 *
	 * @return void
	 */
	public function test_project_post_full_adds_content_to_summary(): void {
		$post_id = $this->create_book( 'Neuromancer' );
		$full    = blu_project_post_full( get_post( $post_id ) );

		$this->assertSame( 'id', array_key_first( $full ) );
		$this->assertSame( 'Contents of Neuromancer', $full['content'] );
		$this->assertSame(
			blu_project_post_summary( get_post( $post_id ) ),
			array_diff_key( $full, array( 'content' => '' ) )
		);
	}

	/**
	 * Searching by a label returns the item and every result carries a top-level id.
	 *
	 * @return void
	 */
	public function test_cpt_search_by_label_returns_results_with_top_level_id(): void {
		$post_id = $this->create_book( 'Hyperion' );

		$result = $this->execute( 'blu/cpt-search', array( 'post_type' => 'Books' ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( self::CPT_SLUG, $result['message']['post_type'] );
		$this->assertNotEmpty( $result['message']['results'] );

		foreach ( $result['message']['results'] as $item ) {
			$this->assertArrayHasKey( 'id', $item );
			$this->assertIsInt( $item['id'] );
		}

		$this->assertContains( $post_id, wp_list_pluck( $result['message']['results'], 'id' ) );
	}

	/**
	 * Every identifier form finds the same item, so slug callers and label
	 * callers get identical results.
	 *
	 * @dataProvider post_type_identifier_provider
	 *
	 * @param string $identifier The caller-supplied identifier.
	 *
	 * @return void
	 */
	public function test_cpt_search_accepts_identifier_forms( string $identifier ): void {
		$post_id = $this->create_book( 'Solaris' );

		$result = $this->execute( 'blu/cpt-search', array( 'post_type' => $identifier ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( self::CPT_SLUG, $result['message']['post_type'] );
		$this->assertContains( $post_id, wp_list_pluck( $result['message']['results'], 'id' ) );
	}

	/**
	 * Search reports pagination and honors the search term.
	 *
	 * @return void
	 */
	public function test_cpt_search_paginates_and_filters_by_term(): void {
		$this->create_book( 'Foundation' );
		$this->create_book( 'Foundation and Empire' );
		$this->create_book( 'Ubik' );

		$paged = $this->execute(
			'blu/cpt-search',
			array(
				'post_type' => 'Books',
				'per_page'  => 2,
				'page'      => 1,
			)
		);

		$this->assertSame( 200, $paged['statusCode'] );
		$this->assertCount( 2, $paged['message']['results'] );
		$this->assertSame( 3, $paged['message']['total'] );
		$this->assertSame( 2, $paged['message']['pages'] );
		$this->assertSame( 1, $paged['message']['page'] );
		$this->assertSame( 2, $paged['message']['per_page'] );

		$searched = $this->execute(
			'blu/cpt-search',
			array(
				'post_type' => 'Books',
				'search'    => 'Foundation',
			)
		);

		$this->assertSame( 2, $searched['message']['total'] );
	}

	/**
	 * Drafts are excluded by default and included when status is "any".
	 *
	 * @return void
	 */
	public function test_cpt_search_defaults_to_published_items(): void {
		$this->create_book( 'Published Book' );
		self::factory()->post->create(
			array(
				'post_type'   => self::CPT_SLUG,
				'post_title'  => 'Drafted Book',
				'post_status' => 'draft',
			)
		);

		$default = $this->execute( 'blu/cpt-search', array( 'post_type' => 'Books' ) );
		$this->assertSame( 1, $default['message']['total'] );

		$any = $this->execute(
			'blu/cpt-search',
			array(
				'post_type' => 'Books',
				'status'    => 'any',
			)
		);
		$this->assertSame( 2, $any['message']['total'] );
	}

	/**
	 * Get resolves a label and returns the full projection.
	 *
	 * @return void
	 */
	public function test_get_cpt_by_label_returns_full_projection(): void {
		$post_id = $this->create_book( 'Blindsight' );

		$result = $this->execute(
			'blu/get-cpt',
			array(
				'post_type' => 'Book',
				'id'        => $post_id,
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( $post_id, $result['message']['id'] );
		$this->assertSame( 'Blindsight', $result['message']['title'] );
		$this->assertSame( 'Contents of Blindsight', $result['message']['content'] );
	}

	/**
	 * An id belonging to a different post type is a 404, not a cross-type read.
	 *
	 * @return void
	 */
	public function test_get_cpt_rejects_id_from_another_post_type(): void {
		$unrelated = self::factory()->post->create( array( 'post_type' => 'post' ) );

		$result = $this->execute(
			'blu/get-cpt',
			array(
				'post_type' => 'Books',
				'id'        => $unrelated,
			)
		);

		$this->assertSame( 404, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );
	}

	/**
	 * Add resolves a label, defaults to draft, and returns the new id.
	 *
	 * @return void
	 */
	public function test_add_cpt_by_label_creates_item_and_returns_id(): void {
		$result = $this->execute(
			'blu/add-cpt',
			array(
				'post_type' => 'Books',
				'title'     => 'A New Book',
				'content'   => 'Some content',
			)
		);

		$this->assertSame( 201, $result['statusCode'] );
		$this->assertSame( 'id', array_key_first( $result['message'] ) );
		$this->assertIsInt( $result['message']['id'] );

		$created = get_post( $result['message']['id'] );
		$this->assertSame( self::CPT_SLUG, $created->post_type );
		$this->assertSame( 'A New Book', $created->post_title );
		$this->assertSame( 'draft', $created->post_status );
	}

	/**
	 * Update resolves a label and applies only the fields provided.
	 *
	 * @return void
	 */
	public function test_update_cpt_by_label_changes_only_supplied_fields(): void {
		$post_id = $this->create_book( 'Old Title' );

		$result = $this->execute(
			'blu/update-cpt',
			array(
				'post_type' => 'books',
				'id'        => $post_id,
				'title'     => 'New Title',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( $post_id, $result['message']['id'] );
		$this->assertSame( 'New Title', $result['message']['title'] );

		$updated = get_post( $post_id );
		$this->assertSame( 'New Title', $updated->post_title );
		$this->assertSame( 'Contents of Old Title', $updated->post_content );
	}

	/**
	 * Delete resolves a label and removes the item.
	 *
	 * @return void
	 */
	public function test_delete_cpt_by_label_removes_item(): void {
		$post_id = $this->create_book( 'Doomed Book' );

		$result = $this->execute(
			'blu/delete-cpt',
			array(
				'post_type' => 'Book Library',
				'id'        => $post_id,
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertTrue( $result['message']['deleted'] );
		$this->assertSame( $post_id, $result['message']['id'] );
		$this->assertSame( self::CPT_SLUG, $result['message']['post_type'] );
		$this->assertNull( get_post( $post_id ) );
	}

	/**
	 * Abilities that take a post_type, paired with the minimum extra input each
	 * needs to reach the resolution step.
	 *
	 * @return array<string, array{0:string,1:array}>
	 */
	public function post_type_ability_provider(): array {
		return array(
			'cpt-search' => array( 'blu/cpt-search', array() ),
			'get-cpt'    => array( 'blu/get-cpt', array( 'id' => 1 ) ),
			'add-cpt'    => array(
				'blu/add-cpt',
				array(
					'title'   => 'T',
					'content' => 'C',
				),
			),
			'update-cpt' => array( 'blu/update-cpt', array( 'id' => 1 ) ),
			'delete-cpt' => array( 'blu/delete-cpt', array( 'id' => 1 ) ),
		);
	}

	/**
	 * An unknown post type is a 400 listing the valid options on every ability,
	 * rather than a silent empty result.
	 *
	 * @dataProvider post_type_ability_provider
	 *
	 * @param string $ability Ability name.
	 * @param array  $extra   Additional required input.
	 *
	 * @return void
	 */
	public function test_unknown_post_type_returns_400_with_available_types( string $ability, array $extra ): void {
		$result = $this->execute( $ability, array_merge( array( 'post_type' => 'Widgets' ), $extra ) );

		$this->assertSame( 400, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );
		$this->assertStringContainsString( 'Unknown post type "Widgets"', $result['message'] );
		$this->assertStringContainsString( self::CPT_SLUG, $result['message'] );
	}

	/**
	 * All CPT abilities are registered.
	 *
	 * @return void
	 */
	public function test_cpt_abilities_are_registered(): void {
		foreach ( $this->registered_abilities as $name ) {
			$this->assertNotNull( blu_get_ability( $name ), $name . ' should be registered' );
		}
	}
}
