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
	 * Helper: execute a registered ability by name with an admin user.
	 *
	 * @param string $ability_name The registered ability name.
	 * @param array  $input        Input to pass to the ability.
	 * @return mixed The result of the ability execution.
	 */
	private function execute_ability( string $ability_name, array $input ) {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		new BlockEditor();

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$ability = blu_get_ability( $ability_name );
		$this->assertNotNull( $ability, "Ability {$ability_name} should be registered." );

		return $ability->execute( $input );
	}

	// ── Constructor ───────────────────────────────────────────────────

	/**
	 * Verifies constructor registers all abilities without fatal.
	 */
	public function test_constructor_does_not_fatal() {
		$instance = new BlockEditor();
		$this->assertInstanceOf( BlockEditor::class, $instance );
	}

	// ── blu/edit-block ────────────────────────────────────────────────

	/**
	 * Verifies edit-block returns 400 when client_id is missing.
	 */
	public function test_edit_block_requires_client_id() {
		$result = $this->execute_ability( 'blu/edit-block', array( 'block_content' => '<!-- wp:paragraph --><p>Test</p><!-- /wp:paragraph -->' ) );

		$this->assertSame( 400, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );
	}

	/**
	 * Verifies edit-block returns 400 when block_content is missing.
	 */
	public function test_edit_block_requires_block_content() {
		$result = $this->execute_ability( 'blu/edit-block', array( 'client_id' => 'abc-123' ) );

		$this->assertSame( 400, $result['statusCode'] );
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
	 * Verifies add-section returns 400 when block_content is missing.
	 */
	public function test_add_section_requires_block_content() {
		$result = $this->execute_ability( 'blu/add-section', array( 'after_client_id' => 'abc-123' ) );

		$this->assertSame( 400, $result['statusCode'] );
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
	 * Verifies delete-block returns 400 when client_id is missing.
	 */
	public function test_delete_block_requires_client_id() {
		$result = $this->execute_ability( 'blu/delete-block', array() );

		$this->assertSame( 400, $result['statusCode'] );
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
	 * Verifies move-block returns 400 when client_id is missing.
	 */
	public function test_move_block_requires_client_id() {
		$result = $this->execute_ability(
			'blu/move-block',
			array(
				'target_client_id' => 'def-456',
				'position'         => 'after',
			)
		);

		$this->assertSame( 400, $result['statusCode'] );
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
	 * Verifies move-block returns 400 when position is invalid.
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

		$this->assertSame( 400, $result['statusCode'] );
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
	 * Verifies get-block-markup returns 400 when client_id is missing.
	 */
	public function test_get_block_markup_requires_client_id() {
		$result = $this->execute_ability( 'blu/get-block-markup', array() );

		$this->assertSame( 400, $result['statusCode'] );
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
	 * Verifies highlight-block returns 400 when client_id is missing.
	 */
	public function test_highlight_block_requires_client_id() {
		$result = $this->execute_ability( 'blu/highlight-block', array() );

		$this->assertSame( 400, $result['statusCode'] );
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
	 * Verifies update-block-attrs returns 400 when required fields are missing.
	 */
	public function test_update_block_attrs_requires_fields() {
		$result = $this->execute_ability( 'blu/update-block-attrs', array( 'client_id' => 'abc-123' ) );

		$this->assertSame( 400, $result['statusCode'] );
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
	 * Verifies duplicate-block returns 400 when client_id is not a valid UUID.
	 */
	public function test_duplicate_block_rejects_invalid_uuid() {
		$result = $this->execute_ability( 'blu/duplicate-block', array( 'client_id' => 'not-a-uuid' ) );

		$this->assertSame( 400, $result['statusCode'] );
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
	 * Verifies insert-inner-block returns 400 when parent_client_id is missing.
	 */
	public function test_insert_inner_block_requires_parent_client_id() {
		$result = $this->execute_ability(
			'blu/insert-inner-block',
			array( 'block_content' => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->' )
		);

		$this->assertSame( 400, $result['statusCode'] );
	}

	/**
	 * Verifies insert-inner-block returns 400 when block_content is missing.
	 */
	public function test_insert_inner_block_requires_block_content() {
		$result = $this->execute_ability(
			'blu/insert-inner-block',
			array( 'parent_client_id' => 'abc-123' )
		);

		$this->assertSame( 400, $result['statusCode'] );
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
	 * Verifies insert-inner-block ignores non-integer index values.
	 */
	public function test_insert_inner_block_ignores_non_integer_index() {
		$result = $this->execute_ability(
			'blu/insert-inner-block',
			array(
				'parent_client_id' => 'abc-123',
				'block_content'    => '<!-- wp:paragraph --><p>Hi</p><!-- /wp:paragraph -->',
				'index'            => 'not-an-int',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertArrayNotHasKey( 'index', $result['message'] );
	}
}
