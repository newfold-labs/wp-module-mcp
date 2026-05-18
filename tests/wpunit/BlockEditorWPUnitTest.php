<?php

namespace BLU;

use BLU\Abilities\BlockEditor;

/**
 * Tests for BlockEditor abilities.
 *
 * @covers \BLU\Abilities\BlockEditor
 */
class BlockEditorWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Ability names registered during the test for cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array(
		'blu/edit-block',
		'blu/add-section',
		'blu/delete-block',
		'blu/duplicate-block',
		'blu/insert-inner-block',
		'blu/move-block',
		'blu/get-block-markup',
		'blu/highlight-block',
		'blu/update-block-attrs',
	);

	/**
	 * Whether abilities have been registered in this test instance.
	 *
	 * @var bool
	 */
	private $abilities_initialized = false;

	/**
	 * Skip the suite when the Abilities API isn't available, otherwise set up
	 * an admin user and ensure the blu-mcp category exists on the registry.
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

		$cat_registry = \WP_Ability_Categories_Registry::get_instance();
		if ( $cat_registry && ! $cat_registry->is_registered( 'blu-mcp' ) ) {
			$cat_registry->register(
				'blu-mcp',
				array(
					'label'       => 'Bluehost MCP',
					'description' => 'Bluehost-specific abilities for use with MCP',
				)
			);
		}
	}

	/**
	 * Remove abilities registered by these tests so they don't leak between cases.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$registry = \WP_Abilities_Registry::get_instance();
		if ( $registry ) {
			foreach ( $this->registered_abilities as $name ) {
				if ( $registry->is_registered( $name ) ) {
					blu_unregister_ability( $name );
				}
			}
		}
		$this->abilities_initialized = false;
		parent::tear_down();
	}

	/**
	 * Register BlockEditor abilities through the proper wp_abilities_api_init hook.
	 *
	 * WordPress 6.9+ rejects wp_register_ability calls made outside that action with
	 * a doing_it_wrong notice that the test framework treats as a failure.
	 *
	 * @return void
	 */
	private function ensure_abilities_registered(): void {
		if ( $this->abilities_initialized ) {
			return;
		}

		$cb = function () {
			new BlockEditor();
		};
		add_action( 'wp_abilities_api_init', $cb, 10 );

		$init_count_before = did_action( 'wp_abilities_api_init' );
		$registry          = \WP_Abilities_Registry::get_instance();
		if ( $registry && did_action( 'wp_abilities_api_init' ) === $init_count_before ) {
			do_action( 'wp_abilities_api_init', $registry );
		}

		remove_action( 'wp_abilities_api_init', $cb, 10 );
		$this->abilities_initialized = true;
	}

	/**
	 * Helper: execute a registered ability by name.
	 *
	 * @param string $ability_name The registered ability name.
	 * @param array  $input        Input to pass to the ability.
	 * @return mixed The result of the ability execution.
	 */
	private function execute_ability( string $ability_name, array $input ) {
		$this->ensure_abilities_registered();

		$ability = blu_get_ability( $ability_name );
		$this->assertNotNull( $ability, "Ability {$ability_name} should be registered." );

		return $ability->execute( $input );
	}

	// ── blu/edit-block ────────────────────────────────────────────────

	/**
	 * Verifies the schema rejects edit-block calls without a client_id.
	 */
	public function test_edit_block_requires_client_id() {
		$result = $this->execute_ability( 'blu/edit-block', array( 'block_content' => '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies the schema rejects edit-block calls without block_content.
	 */
	public function test_edit_block_requires_block_content() {
		$result = $this->execute_ability( 'blu/edit-block', array( 'client_id' => 'abc-123' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies edit-block returns 200 with block_content.
	 */
	public function test_edit_block_success_with_block_content() {
		$result = $this->execute_ability(
			'blu/edit-block',
			array(
				'client_id'     => 'abc-123',
				'block_content' => '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 'edit_block', $result['message']['action'] );
		$this->assertSame( 'abc-123', $result['message']['client_id'] );
	}

	// ── blu/add-section ───────────────────────────────────────────────

	/**
	 * Verifies the schema rejects add-section calls without block_content.
	 */
	public function test_add_section_requires_block_content() {
		$result = $this->execute_ability( 'blu/add-section', array( 'after_client_id' => 'abc-123' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies add-section returns 400 when both after and before are provided.
	 */
	public function test_add_section_mutually_exclusive_position() {
		$result = $this->execute_ability(
			'blu/add-section',
			array(
				'block_content'    => '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->',
				'after_client_id'  => 'abc-123',
				'before_client_id' => 'def-456',
			)
		);

		$this->assertSame( 400, $result['statusCode'] );
	}

	/**
	 * Verifies add-section returns 200 with after_client_id.
	 */
	public function test_add_section_success_with_after() {
		$result = $this->execute_ability(
			'blu/add-section',
			array(
				'block_content'   => '<!-- wp:paragraph --><p>New</p><!-- /wp:paragraph -->',
				'after_client_id' => 'abc-123',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'add_section', $result['message']['action'] );
		$this->assertSame( 'abc-123', $result['message']['after_client_id'] );
	}

	/**
	 * Verifies add-section returns 200 with before_client_id.
	 */
	public function test_add_section_success_with_before() {
		$result = $this->execute_ability(
			'blu/add-section',
			array(
				'block_content'    => '<!-- wp:paragraph --><p>New</p><!-- /wp:paragraph -->',
				'before_client_id' => 'def-456',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'add_section', $result['message']['action'] );
		$this->assertSame( 'def-456', $result['message']['before_client_id'] );
	}

	/**
	 * Verifies add-section forwards image_urls when provided.
	 */
	public function test_add_section_forwards_image_urls() {
		$urls   = array( 'https://example.com/img1.jpg', 'https://example.com/img2.jpg' );
		$result = $this->execute_ability(
			'blu/add-section',
			array(
				'block_content'   => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
				'after_client_id' => 'abc-123',
				'image_urls'      => $urls,
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertCount( 2, $result['message']['image_urls'] );
	}

	/**
	 * Verifies add-section with null after_client_id (top of page).
	 */
	public function test_add_section_null_after_inserts_at_top() {
		$result = $this->execute_ability(
			'blu/add-section',
			array(
				'block_content'   => '<!-- wp:paragraph --><p>Top</p><!-- /wp:paragraph -->',
				'after_client_id' => null,
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertArrayNotHasKey( 'after_client_id', $result['message'] );
		$this->assertArrayNotHasKey( 'before_client_id', $result['message'] );
	}

	// ── blu/delete-block ──────────────────────────────────────────────

	/**
	 * Verifies the schema rejects delete-block calls without a client_id.
	 */
	public function test_delete_block_requires_client_id() {
		$result = $this->execute_ability( 'blu/delete-block', array() );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies delete-block returns 200 with valid input.
	 */
	public function test_delete_block_success() {
		$result = $this->execute_ability( 'blu/delete-block', array( 'client_id' => 'abc-123' ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'delete_block', $result['message']['action'] );
		$this->assertSame( 'abc-123', $result['message']['client_id'] );
	}

	// ── blu/move-block ────────────────────────────────────────────────

	/**
	 * Verifies the schema rejects move-block calls without a client_id.
	 */
	public function test_move_block_requires_client_id() {
		$result = $this->execute_ability(
			'blu/move-block',
			array(
				'target_client_id' => 'def-456',
				'position'         => 'after',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies move-block returns 400 when target_client_id is missing.
	 */
	public function test_move_block_requires_target_client_id() {
		$result = $this->execute_ability(
			'blu/move-block',
			array(
				'client_id' => 'abc-123',
				'position'  => 'after',
			)
		);

		$this->assertSame( 400, $result['statusCode'] );
	}

	/**
	 * Verifies the schema rejects move-block calls with a position outside the enum.
	 */
	public function test_move_block_requires_valid_position() {
		$result = $this->execute_ability(
			'blu/move-block',
			array(
				'client_id'        => 'abc-123',
				'target_client_id' => 'def-456',
				'position'         => 'inside',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies move-block returns 200 with valid input.
	 */
	public function test_move_block_success() {
		$result = $this->execute_ability(
			'blu/move-block',
			array(
				'client_id'        => 'abc-123',
				'target_client_id' => 'def-456',
				'position'         => 'before',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'move_block', $result['message']['action'] );
		$this->assertSame( 'abc-123', $result['message']['client_id'] );
		$this->assertSame( 'def-456', $result['message']['target_client_id'] );
		$this->assertSame( 'before', $result['message']['position'] );
	}

	// ── blu/get-block-markup ──────────────────────────────────────────

	/**
	 * Verifies the schema rejects get-block-markup calls without a client_id.
	 */
	public function test_get_block_markup_requires_client_id() {
		$result = $this->execute_ability( 'blu/get-block-markup', array() );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies get-block-markup returns 200 with valid input.
	 */
	public function test_get_block_markup_success() {
		$result = $this->execute_ability( 'blu/get-block-markup', array( 'client_id' => 'abc-123' ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'get_block_markup', $result['message']['action'] );
		$this->assertSame( 'abc-123', $result['message']['client_id'] );
	}

	// ── blu/highlight-block ───────────────────────────────────────────

	/**
	 * Verifies the schema rejects highlight-block calls without a client_id.
	 */
	public function test_highlight_block_requires_client_id() {
		$result = $this->execute_ability( 'blu/highlight-block', array() );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies highlight-block returns 200 with valid input.
	 */
	public function test_highlight_block_success() {
		$result = $this->execute_ability( 'blu/highlight-block', array( 'client_id' => 'abc-123' ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'highlight_block', $result['message']['action'] );
	}

	// ── blu/update-block-attrs ────────────────────────────────────────

	/**
	 * Verifies the schema rejects update-block-attrs calls missing required fields.
	 */
	public function test_update_block_attrs_requires_fields() {
		$result = $this->execute_ability( 'blu/update-block-attrs', array( 'client_id' => 'abc-123' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies update-block-attrs returns 200 with valid input.
	 */
	public function test_update_block_attrs_success() {
		$attrs  = array(
			'backgroundColor' => 'accent-1',
			'textColor'       => 'contrast',
		);
		$result = $this->execute_ability(
			'blu/update-block-attrs',
			array(
				'client_id'  => 'abc-123',
				'attributes' => $attrs,
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'update_block_attrs', $result['message']['action'] );
		$this->assertSame( $attrs, $result['message']['attributes'] );
	}

	// ── blu/duplicate-block ───────────────────────────────────────────

	/**
	 * Verifies duplicate-block returns 400 when neither client_id nor kind is provided.
	 */
	public function test_duplicate_block_requires_client_id_or_kind() {
		$result = $this->execute_ability( 'blu/duplicate-block', array() );

		$this->assertSame( 400, $result['statusCode'] );
	}

	/**
	 * Verifies the schema rejects duplicate-block calls with a client_id that fails the UUID pattern.
	 */
	public function test_duplicate_block_rejects_invalid_uuid() {
		$result = $this->execute_ability( 'blu/duplicate-block', array( 'client_id' => 'not-a-uuid' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies duplicate-block returns 200 in explicit mode with a valid UUID.
	 */
	public function test_duplicate_block_explicit_mode_success() {
		$uuid   = '12345678-1234-1234-1234-1234567890ab';
		$result = $this->execute_ability( 'blu/duplicate-block', array( 'client_id' => $uuid ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'duplicate', $result['message']['action'] );
		$this->assertSame( $uuid, $result['message']['client_id'] );
		$this->assertArrayNotHasKey( 'kind', $result['message'] );
	}

	/**
	 * Verifies duplicate-block returns 200 in intent mode with a kind only.
	 */
	public function test_duplicate_block_intent_mode_success() {
		$result = $this->execute_ability( 'blu/duplicate-block', array( 'kind' => 'column' ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'duplicate', $result['message']['action'] );
		$this->assertSame( 'column', $result['message']['kind'] );
		$this->assertArrayNotHasKey( 'client_id', $result['message'] );
	}

	/**
	 * Verifies duplicate-block forwards scope and position in intent mode.
	 */
	public function test_duplicate_block_forwards_scope_and_position() {
		$scope  = '87654321-4321-4321-4321-ba0987654321';
		$result = $this->execute_ability(
			'blu/duplicate-block',
			array(
				'kind'     => 'card',
				'scope'    => $scope,
				'position' => 'first',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'card', $result['message']['kind'] );
		$this->assertSame( $scope, $result['message']['scope'] );
		$this->assertSame( 'first', $result['message']['position'] );
	}

	/**
	 * Verifies duplicate-block preserves integer position values.
	 */
	public function test_duplicate_block_preserves_integer_position() {
		$result = $this->execute_ability(
			'blu/duplicate-block',
			array(
				'kind'     => 'button',
				'position' => 2,
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 2, $result['message']['position'] );
	}

	// ── blu/insert-inner-block ────────────────────────────────────────

	/**
	 * Verifies the schema rejects insert-inner-block calls without parent_client_id.
	 */
	public function test_insert_inner_block_requires_parent_client_id() {
		$result = $this->execute_ability(
			'blu/insert-inner-block',
			array( 'block_content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies the schema rejects insert-inner-block calls without block_content.
	 */
	public function test_insert_inner_block_requires_block_content() {
		$result = $this->execute_ability(
			'blu/insert-inner-block',
			array( 'parent_client_id' => 'abc-123' )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies insert-inner-block returns 200 with valid input.
	 */
	public function test_insert_inner_block_success() {
		$result = $this->execute_ability(
			'blu/insert-inner-block',
			array(
				'parent_client_id' => 'abc-123',
				'block_content'    => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'insert_inner_block', $result['message']['action'] );
		$this->assertSame( 'abc-123', $result['message']['parent_client_id'] );
		$this->assertArrayNotHasKey( 'index', $result['message'] );
	}

	/**
	 * Verifies insert-inner-block forwards the index when provided as an integer.
	 */
	public function test_insert_inner_block_forwards_integer_index() {
		$result = $this->execute_ability(
			'blu/insert-inner-block',
			array(
				'parent_client_id' => 'abc-123',
				'block_content'    => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
				'index'            => 2,
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 2, $result['message']['index'] );
	}

	/**
	 * Verifies the schema rejects insert-inner-block calls with a non-integer index.
	 */
	public function test_insert_inner_block_rejects_non_integer_index() {
		$result = $this->execute_ability(
			'blu/insert-inner-block',
			array(
				'parent_client_id' => 'abc-123',
				'block_content'    => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
				'index'            => 'not-an-int',
			)
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
	}
}
