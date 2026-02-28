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
		// phpcs:disable Generic.Files.LineLength.TooLong -- Tool description includes inline rules for AI context.
		$description = <<<'DESC'
		Replace the entire content of an existing block (and its inner blocks) with new WordPress block markup. Use this when the user asks to change text, styles, layout, or content of a specific block. The block_content MUST be valid WordPress block markup with proper block comments (<!-- wp:blockname {...} -->...<!-- /wp:blockname -->). Always include all inner blocks if the target block has children. Use the client_id from the block tree context. TEMPLATE PARTS: When editing a core/template-part, provide ONLY the inner blocks markup — do NOT wrap it in <!-- wp:template-part --> comments. You can wrap blocks in core/group, core/stack, or similar container blocks when needed for layout or styling.

		ADDITIONAL RULES:
		- VALID MARKUP: Every block_content you provide MUST be valid WordPress block markup with proper <!-- wp:name {attrs} --> comments. Never output plain HTML without block comments.
		- INNER BLOCKS: When editing a block that has inner blocks, include ALL inner blocks in your replacement markup unless the user specifically asked to remove them.
		- CONTAINER BLOCKS: Container blocks (core/group, core/columns, core/column, core/cover, core/row, core/stack, core/buttons) render content using WordPress InnerBlocks. ALL visible content inside them MUST be wrapped in proper block comments. NEVER put raw HTML like <p>, <h2>, <ul>, or <figure> directly inside a container wrapper — it will be silently stripped and the block will appear empty. WRONG: <!-- wp:group --> <div class="wp-block-group"><p>Hello</p></div> <!-- /wp:group -->. RIGHT: <!-- wp:group --> <div class="wp-block-group"><!-- wp:paragraph --> <p>Hello</p> <!-- /wp:paragraph --></div> <!-- /wp:group -->.
		- COLORS: ALWAYS use the site's theme palette colors — NEVER invent arbitrary hex colors (like yellow #ffcc00 or blue #0066cc). The available palette slugs are: base, contrast, accent-1, accent-2, accent-3, accent-4, accent-5, accent-6. Apply them via "backgroundColor" and "textColor" attributes (e.g., "backgroundColor":"accent-1", "textColor":"contrast"). Only use custom hex values when the user explicitly requests a specific color. To reference a palette color in the style object use "var:preset|color|<slug>". In inline CSS use var(--wp--preset--color--<slug>). In HTML, use has-<slug>-background-color / has-<slug>-color classes. If the existing markup has an invalid slug (e.g., "backgroundColor":"red"), fix it by replacing with the nearest palette slug or removing the attribute and using the style object with a HEX value: {"style":{"color":{"background":"#ff0000"}}}. This rule applies to EVERY block in your output — scan the ENTIRE block_content before returning it.
		- NFD UTILITY CLASSES: Do NOT add new nfd-* classes to blocks. When editing a block that has existing nfd-* classes, PRESERVE all nfd-* classes unless the user specifically asks to change the property they control. If the user asks to change a property controlled by an nfd-* class (e.g., "change the padding"), remove the nfd-* class for that property and apply the styling using WordPress block attributes instead. If the editor context includes an nfd class reference section, use it to understand what each class does. Key rules: NEVER remove nfd-container (controls container width), nfd-theme-* (controls color scheme), nfd-wb-*/nfd-delay-* (controls animations), nfd-bg-effect-* (controls decorative backgrounds), nfd-divider-* (controls section dividers). When replacing an nfd-* spacing/color/typography class, use the resolved CSS value from the reference (not a guess) to set the equivalent WordPress block attribute. Preserve: nfd-bg-surface, nfd-bg-primary, nfd-bg-subtle, nfd-text-faded, nfd-text-contrast, nfd-text-primary, nfd-btn-*, nfd-rounded-*, nfd-shadow-*.
		- IMAGE ASPECT RATIO: When the user asks to change an image's aspect ratio, use the "aspectRatio" and "scale" attributes — NEVER set fixed "width"/"height" in pixels. Valid aspect ratios: "1/1", "4/3", "3/4", "3/2", "2/3", "16/9", "9/16". Example: <!-- wp:image {"aspectRatio":"16/9","scale":"cover","sizeSlug":"full"} --> <figure class="wp-block-image size-full"><img src="..." alt="" style="aspect-ratio:16/9;object-fit:cover"/></figure> <!-- /wp:image -->. The inline style on the <img> tag MUST match: style="aspect-ratio:{ratio};object-fit:{scale}". Remove any existing "width" and "height" attributes and "is-resized" class when switching to aspect ratio.
		- COVER BLOCK OVERLAY: The cover block overlay color is controlled ONLY through block comment attributes — NEVER add inline styles to the overlay <span>. The <span> must only have classes, no style attribute. For theme palette colors: use "overlayColor":"<slug>" in the block comment and add class has-<slug>-background-color to the span. For custom colors: use "customOverlayColor":"#hex" in the block comment. The span gets NO inline style — WordPress handles it. Overlay opacity is set via "dimRatio" (0-100) in the block comment. The span class reflects it: has-background-dim-{value} has-background-dim. WRONG: style="background-color:rgba(...)" on the span — this causes block validation failure.
		- GRADIENTS: To add a gradient background to a block, use the style.color.gradient attribute in the block comment — NEVER put background-image in the inline style. Block comment: {"style":{"color":{"gradient":"linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%)"}}}. HTML: style="background:linear-gradient(135deg, #a1c4fd 0%, #c2e9fb 100%)" (use background: not background-image:). Add has-background class. For theme presets use: {"gradient":"vivid-cyan-blue-to-vivid-purple"}. WRONG: {"style":{"elements":{"background":{"backgroundImage":"..."}}}} or style="background-image:linear-gradient(...)".
		- FONT SIZE: When changing a block's font size, ALWAYS remove any existing font-size selection first — then apply the new one. Preset slugs and custom values are mutually exclusive; combining them causes the preset to silently win via CSS specificity. CUSTOM size: REMOVE "fontSize" attribute and has-*-font-size class, then set "style":{"typography":{"fontSize":"4.5rem"}} and style="font-size:4.5rem". PRESET size: REMOVE style.typography.fontSize and inline font-size, then set "fontSize":"x-large" and add has-x-large-font-size class. WRONG: {"fontSize":"x-large","style":{"typography":{"fontSize":"4.5rem"}}}.
		- ALIGNMENT & CENTERING: The core/group block does NOT support the align attribute for centering — do NOT set "align":"center" on a group. For flex containers (core/columns, core/buttons): use "align":"center" directly. For core/row or core/stack: set "layout":{"type":"flex","justifyContent":"center"}. For content inside a group: set alignment on inner blocks — core/image and core/buttons support "align":"center"; core/heading and core/paragraph use "textAlign":"center". WRONG: <!-- wp:group {"align":"center"} -->.
		- TEMPLATE PARTS: When REPLACING an entire header or footer design, use edit-block on the template-part clientId (the core/template-part block) with pattern_slug or block_content. When ADDING content to a template part (e.g., a top bar above the header), use blu/add-section with before_client_id or after_client_id pointing to a block INSIDE the template part — do NOT rewrite the entire template part just to add content, because rewriting loses layout attributes and breaks the design.
		DESC;
		// phpcs:enable Generic.Files.LineLength.TooLong

		blu_register_ability(
			'blu/edit-block',
			array(
				'label'               => 'Edit Block Content',
				'description'         => $description,
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
						'pattern_slug'  => array(
							'type'        => 'string',
							'description' => 'Slug of a pattern from the library to use as the new content. The system fetches the markup directly — do NOT pass block_content when using this.',
						),
					),
					'required'   => array( 'client_id' ),
				),
				'execute_callback'    => function ( $input ) {
					// Validate required fields
					if ( empty( $input['client_id'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'client_id is required' ) );
					}
					if ( empty( $input['block_content'] ) && empty( $input['pattern_slug'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'Either block_content or pattern_slug is required' ) );
					}

					// Return action data for client-side execution
					return blu_prepare_ability_response(
						200,
						array(
							'action'        => 'edit_block',
							'client_id'     => sanitize_text_field( $input['client_id'] ),
							'block_content' => $input['block_content'] ?? '', // Don't sanitize - it's block HTML
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
		// phpcs:disable Generic.Files.LineLength.TooLong -- Tool description includes inline rules for AI context.
		$description = <<<'DESC'
		Insert new block content at a specific position in the page. Use this when the user asks to add a new section, component, or content area. Use after_client_id to insert after a block, before_client_id to insert before a block, or set both to null to insert at the very top of the page. Provide EITHER pattern_slug (to insert a pattern from the library) OR block_content (to insert custom markup). When using a pattern, always prefer pattern_slug — the system will fetch the markup directly.

		ADDITIONAL RULES:
		- VALID MARKUP: Every block_content you provide MUST be valid WordPress block markup with proper <!-- wp:name {attrs} --> comments. Never output plain HTML without block comments.
		- CONTAINER BLOCKS: Container blocks (core/group, core/columns, core/column, core/cover, core/row, core/stack, core/buttons) render content using WordPress InnerBlocks. ALL visible content inside them MUST be wrapped in proper block comments. NEVER put raw HTML like <p>, <h2>, <ul>, or <figure> directly inside a container wrapper — it will be silently stripped and the block will appear empty. WRONG: <!-- wp:group --> <div class="wp-block-group"><p>Hello</p></div> <!-- /wp:group -->. RIGHT: <!-- wp:group --> <div class="wp-block-group"><!-- wp:paragraph --> <p>Hello</p> <!-- /wp:paragraph --></div> <!-- /wp:group -->.
		- ADDING SECTIONS: You can insert content before or after ANY block at any nesting depth — not just top-level blocks. Use after_client_id to insert after a specific block, or before_client_id to insert before it. When the user does NOT specify a position, insert at the top level of the page (use after_client_id of the last top-level block in the tree, or null for the very top).
		- TEMPLATE PARTS: When adding content to a template part (e.g., adding a top bar above a header, or a banner inside a footer), use blu/add-section with before_client_id or after_client_id pointing to a block INSIDE the template part. This preserves all existing blocks and their layout. Do NOT rewrite the entire template part with blu/edit-block just to add new content — rewriting risks losing layout attributes (flex, gap, etc.) and breaking the design. Only use blu/edit-block on a template part when replacing ALL of its content with a completely different design.
		- COLORS: ALWAYS use the site's theme palette colors — NEVER invent arbitrary hex colors (like yellow #ffcc00 or blue #0066cc). The available palette slugs are: base, contrast, accent-1, accent-2, accent-3, accent-4, accent-5, accent-6. Apply them via "backgroundColor" and "textColor" attributes (e.g., "backgroundColor":"accent-1", "textColor":"contrast"). Only use custom hex values when the user explicitly requests a specific color. To reference a palette color in the style object use "var:preset|color|<slug>". In inline CSS use var(--wp--preset--color--<slug>). In HTML, use has-<slug>-background-color / has-<slug>-color classes. This rule applies to EVERY block in your output — scan the ENTIRE block_content before returning it.
		- PATTERN LIBRARY: When the user asks to add a new section, layout, or design element (hero, pricing, testimonials, FAQ, CTA, features, team, gallery, contact, etc.), follow this exact sequence: a) Search the pattern library with blu/search-patterns. b) Review ALL returned results — pick the one whose title and description best fit the user's request. If the user has previously used a pattern, pick a DIFFERENT one to provide variety. c) Insert the chosen pattern using blu/add-section with the pattern_slug parameter. Do NOT call blu/get-pattern-markup or pass block_content — the system fetches the markup and automatically customizes the text to fit the page. If the search returns zero results, generate the section markup from scratch using block_content — do NOT tell the user no patterns were found, just build it yourself. Only skip the pattern library for very simple requests (e.g., "add a paragraph").
		DESC;
		// phpcs:enable Generic.Files.LineLength.TooLong

		blu_register_ability(
			'blu/add-section',
			array(
				'label'               => 'Add New Section',
				'description'         => $description,
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'after_client_id'  => array(
							'type'        => array( 'string', 'null' ),
							'description' => 'The clientId of the block to insert AFTER. Use null to insert at the very top of the page. Mutually exclusive with before_client_id.',
						),
						'before_client_id' => array(
							'type'        => 'string',
							'description' => 'The clientId of the block to insert BEFORE. Use this when adding content above the first block inside a template part or at the start of a section. Mutually exclusive with after_client_id.',
						),
						'pattern_slug'     => array(
							'type'        => 'string',
							'description' => 'Slug of a pattern from the library to insert. The system fetches the full markup directly — do NOT pass block_content when using this.',
						),
						'block_content'   => array(
							'type'        => 'string',
							'description' => 'Complete WordPress block markup for custom (non-pattern) sections. Only use this when not using pattern_slug.',
						),
					),
					'required'   => array(),
				),
				'execute_callback'    => function ( $input ) {
					// Validate: need either pattern_slug or block_content
					if ( empty( $input['pattern_slug'] ) && empty( $input['block_content'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'Either pattern_slug or block_content is required' ) );
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
		// phpcs:disable Generic.Files.LineLength.TooLong -- Tool description includes inline rules for AI context.
		$description = <<<'DESC'
		Highlight and scroll to a specific block in the editor. Use this when the user asks where a block is, asks you to point to something, or when you want to draw attention to a specific block while explaining something. The block will be selected and briefly flash to draw the user's eye.

		ADDITIONAL RULES:
		- HIGHLIGHTING: When the user asks where a block is, what a block looks like, or asks you to point to something, use blu/highlight-block to select and flash the block. This scrolls it into view and adds a brief visual pulse. Do NOT use this on every tool call — only when the user is asking about location or you need to draw attention to a specific block.
		DESC;
		// phpcs:enable Generic.Files.LineLength.TooLong

		blu_register_ability(
			'blu/highlight-block',
			array(
				'label'               => 'Highlight Block',
				'description'         => $description,
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
