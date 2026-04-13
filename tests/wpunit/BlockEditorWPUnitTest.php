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
	 * Verifies edit-block returns 400 when neither block_content nor pattern_slug is provided.
	 */
	public function test_edit_block_requires_content_or_pattern() {
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

	/**
	 * Verifies edit-block returns 200 with pattern_slug.
	 */
	public function test_edit_block_success_with_pattern_slug() {
		$result = $this->execute_ability(
			'blu/edit-block',
			array(
				'client_id'    => 'abc-123',
				'pattern_slug' => 'hero-bold',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'edit_block', $result['message']['action'] );
	}

	// ── blu/add-section ───────────────────────────────────────────────

	/**
	 * Verifies add-section returns 400 when neither pattern_slug nor block_content is provided.
	 */
	public function test_add_section_requires_content_or_pattern() {
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
	 * Verifies add-section returns 200 with pattern_slug.
	 */
	public function test_add_section_success_with_pattern_slug() {
		$result = $this->execute_ability(
			'blu/add-section',
			array(
				'pattern_slug'    => 'hero-split',
				'after_client_id' => 'abc-123',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'hero-split', $result['message']['pattern_slug'] );
	}

	/**
	 * Verifies add-section forwards image_urls when provided.
	 */
	public function test_add_section_forwards_image_urls() {
		$urls   = array( 'https://example.com/img1.jpg', 'https://example.com/img2.jpg' );
		$result = $this->execute_ability(
			'blu/add-section',
			array(
				'pattern_slug'    => 'hero-split',
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

	// ── blu/rewrite-text ──────────────────────────────────────────────

	/**
	 * Verifies rewrite-text returns 400 when required fields are missing.
	 */
	public function test_rewrite_text_requires_fields() {
		$result = $this->execute_ability( 'blu/rewrite-text', array( 'client_id' => 'abc-123' ) );

		$this->assertSame( 400, $result['statusCode'] );
	}

	/**
	 * Verifies rewrite-text returns 200 with valid input.
	 */
	public function test_rewrite_text_success() {
		$result = $this->execute_ability(
			'blu/rewrite-text',
			array(
				'client_id'    => 'abc-123',
				'instructions' => 'Make it about coffee',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'rewrite_text', $result['message']['action'] );
		$this->assertSame( 'abc-123', $result['message']['client_id'] );
		$this->assertSame( 'Make it about coffee', $result['message']['instructions'] );
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
}
