<?php
/**
 * Block Editor Abilities
 *
 * Provides abilities for editing WordPress block content in the site editor.
 * These abilities return action data that the client executes via blockEditorService.js.
 *
 * @package BLU
 */

declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * BlockEditor class
 *
 * Registers abilities for editing, adding, deleting, and moving blocks.
 * Each ability is a single-purpose tool designed for clear AI tool selection.
 */
class BlockEditor {

	/**
	 * Constructor - registers block editor abilities.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register all block editor abilities.
	 *
	 * @return void
	 */
	private function register_abilities(): void {
		$this->register_edit_block();
		$this->register_add_section();
		$this->register_delete_block();
		$this->register_move_block();
		$this->register_get_block_markup();
		$this->register_highlight_block();
	}

	/**
	 * Register ability to edit block content (rewrite mode only)
	 *
	 * Replaces the entire content of a block with new WordPress block markup.
	 *
	 * @return void
	 */
	private function register_edit_block(): void {
		blu_register_ability(
			'blu/edit-block',
			array(
				'label'               => 'Edit Block Content',
				'description'         => 'Replace the entire content of an existing block (and its inner blocks) with new WordPress block markup. Use this when the user asks to change text, styles, layout, or content of a specific block. The block_content MUST be valid WordPress block markup with proper block comments (<!-- wp:blockname {...} -->...<!-- /wp:blockname -->). Always include all inner blocks if the target block has children. Use the client_id from the block tree context. TEMPLATE PARTS: When editing a core/template-part, provide ONLY the inner blocks markup — do NOT wrap it in <!-- wp:template-part --> comments. You can wrap blocks in core/group, core/stack, or similar container blocks when needed for layout or styling.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'client_id'     => array(
							'type'        => 'string',
							'description' => 'The clientId of the block to edit, from the block tree context',
						),
						'block_content' => array(
							'type'        => 'string',
							'description' => 'Complete WordPress block markup with block comments. Must include <!-- wp:blockname {...} --> opening and <!-- /wp:blockname --> closing comments. Include all inner blocks if the target has children.',
						),
					),
					'required'   => array( 'client_id', 'block_content' ),
				),
				'execute_callback'    => function ( $input ) {
					// Validate required fields
					if ( empty( $input['client_id'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'client_id is required' ) );
					}
					if ( empty( $input['block_content'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'block_content is required' ) );
					}

					// Return action data for client-side execution
					return blu_prepare_ability_response(
						200,
						array(
							'action'        => 'edit_block',
							'client_id'     => sanitize_text_field( $input['client_id'] ),
							'block_content' => $input['block_content'], // Don't sanitize - it's block HTML
							'message'       => 'Block edit ready for execution',
						)
					);
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Register ability to add a new section
	 *
	 * Inserts new block(s) at a specific position in the page.
	 *
	 * @return void
	 */
	private function register_add_section(): void {
		blu_register_ability(
			'blu/add-section',
			array(
				'label'               => 'Add New Section',
				'description'         => 'Insert new block content at a specific position in the page. Use this when the user asks to add a new section, component, or content area. Set after_client_id to a block\'s clientId to insert after it, or null to insert at the very top of the page. The block_content MUST be valid WordPress block markup. You can include multiple blocks (e.g., a group with inner blocks). Always generate complete, well-structured sections with proper nesting.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'after_client_id' => array(
							'type'        => array( 'string', 'null' ),
							'description' => 'The clientId of the block to insert after (from the block tree context). Use null to insert at the very top of the page.',
						),
						'block_content'   => array(
							'type'        => 'string',
							'description' => 'Complete WordPress block markup for the new section with proper block comments. Can include multiple blocks with nesting (e.g., a group containing headings, paragraphs, columns).',
						),
					),
					'required'   => array( 'block_content' ),
				),
				'execute_callback'    => function ( $input ) {
					// Validate required fields
					if ( empty( $input['block_content'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'block_content is required' ) );
					}

					// after_client_id can be null (top of page) or a string
					$after_client_id = isset( $input['after_client_id'] ) && ! empty( $input['after_client_id'] )
						? sanitize_text_field( $input['after_client_id'] )
						: null;

					// Return action data for client-side execution
					return blu_prepare_ability_response(
						200,
						array(
							'action'          => 'add_section',
							'after_client_id' => $after_client_id,
							'block_content'   => $input['block_content'], // Don't sanitize - it's block HTML
							'message'         => 'Section add ready for execution',
						)
					);
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Register ability to delete a block
	 *
	 * Removes a block and all its inner blocks from the page.
	 *
	 * @return void
	 */
	private function register_delete_block(): void {
		blu_register_ability(
			'blu/delete-block',
			array(
				'label'               => 'Delete Block',
				'description'         => 'Remove a block and ALL of its inner blocks from the page. Use this when the user asks to remove, delete, or get rid of a section or block. This action is irreversible without undo. Only delete the specific block the user refers to — do not delete parent blocks unless explicitly asked.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'client_id' => array(
							'type'        => 'string',
							'description' => 'The clientId of the block to delete, from the block tree context',
						),
					),
					'required'   => array( 'client_id' ),
				),
				'execute_callback'    => function ( $input ) {
					// Validate required fields
					if ( empty( $input['client_id'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'client_id is required' ) );
					}

					// Return action data for client-side execution
					return blu_prepare_ability_response(
						200,
						array(
							'action'    => 'delete_block',
							'client_id' => sanitize_text_field( $input['client_id'] ),
							'message'   => 'Block delete ready for execution',
						)
					);
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Register ability to move a block
	 *
	 * Moves a block (with all inner blocks) to a new position.
	 *
	 * @return void
	 */
	private function register_move_block(): void {
		blu_register_ability(
			'blu/move-block',
			array(
				'label'               => 'Move Block',
				'description'         => 'Move a block (with all its inner blocks) to a new position relative to another block. Use this when the user asks to reorder sections, move content up/down, or rearrange the page layout. The moved block keeps all its content and inner blocks intact.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'client_id'        => array(
							'type'        => 'string',
							'description' => 'The clientId of the block to move, from the block tree context',
						),
						'target_client_id' => array(
							'type'        => 'string',
							'description' => 'The clientId of the target block to position relative to, from the block tree context',
						),
						'position'         => array(
							'type'        => 'string',
							'enum'        => array( 'before', 'after' ),
							'description' => 'Where to place the block relative to the target: "before" or "after"',
						),
					),
					'required'   => array( 'client_id', 'target_client_id', 'position' ),
				),
				'execute_callback'    => function ( $input ) {
					// Validate required fields
					if ( empty( $input['client_id'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'client_id is required' ) );
					}
					if ( empty( $input['target_client_id'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'target_client_id is required' ) );
					}
					if ( empty( $input['position'] ) || ! in_array( $input['position'], array( 'before', 'after' ), true ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'position must be "before" or "after"' ) );
					}

					// Return action data for client-side execution
					return blu_prepare_ability_response(
						200,
						array(
							'action'           => 'move_block',
							'client_id'        => sanitize_text_field( $input['client_id'] ),
							'target_client_id' => sanitize_text_field( $input['target_client_id'] ),
							'position'         => sanitize_text_field( $input['position'] ),
							'message'          => 'Block move ready for execution',
						)
					);
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Register ability to highlight a block
	 *
	 * Selects and flashes a block in the editor to draw the user's attention.
	 * This is a read-only, non-destructive tool — no undo needed.
	 *
	 * @return void
	 */
	private function register_highlight_block(): void {
		blu_register_ability(
			'blu/highlight-block',
			array(
				'label'               => 'Highlight Block',
				'description'         => 'Highlight and scroll to a specific block in the editor. Use this when the user asks where a block is, asks you to point to something, or when you want to draw attention to a specific block while explaining something. The block will be selected and briefly flash to draw the user\'s eye.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'client_id' => array(
							'type'        => 'string',
							'description' => 'The clientId of the block to highlight, from the block tree context',
						),
					),
					'required'   => array( 'client_id' ),
				),
				'execute_callback'    => function ( $input ) {
					if ( empty( $input['client_id'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'client_id is required' ) );
					}

					return blu_prepare_ability_response(
						200,
						array(
							'action'    => 'highlight_block',
							'client_id' => sanitize_text_field( $input['client_id'] ),
							'message'   => 'Block highlight ready for execution',
						)
					);
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Register ability to get block markup
	 *
	 * Fetches the full HTML markup of a specific block and its inner blocks.
	 * This is a read-only tool used before editing non-selected blocks.
	 *
	 * @return void
	 */
	private function register_get_block_markup(): void {
		blu_register_ability(
			'blu/get-block-markup',
			array(
				'label'               => 'Get Block Markup',
				'description'         => 'Get the full HTML markup of a specific block and its inner blocks. Use this BEFORE calling blu/edit-block when you need to see the current markup of a block that is NOT the selected block. This ensures you can produce accurate replacement markup that preserves existing content the user didn\'t ask to change. You do NOT need to call this for the selected block — its markup is already provided in the context.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'client_id' => array(
							'type'        => 'string',
							'description' => 'The clientId of the block to get markup for, from the block tree context',
						),
					),
					'required'   => array( 'client_id' ),
				),
				'execute_callback'    => function ( $input ) {
					// Validate required fields
					if ( empty( $input['client_id'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'client_id is required' ) );
					}

					// Return action data for client-side execution
					// The client intercepts this and returns serialized block markup directly
					return blu_prepare_ability_response(
						200,
						array(
							'action'    => 'get_block_markup',
							'client_id' => sanitize_text_field( $input['client_id'] ),
							'message'   => 'Block markup request ready for execution',
						)
					);
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}
}
