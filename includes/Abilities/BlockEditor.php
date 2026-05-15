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
		$this->register_duplicate();
		$this->register_insert_inner_block();
		$this->register_move_block();
		$this->register_get_block_markup();
		$this->register_highlight_block();
		$this->register_update_block_attrs();
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
		LAST RESORT — prefer a cheaper tool first. This tool forces you to re-emit the block's ENTIRE subtree markup (all inner blocks included) and is by far the slowest operation, so pick one of the alternatives below whenever they fit:
		- "add another <child> with the same design as these" → blu/duplicate-block (clones an existing sibling, zero markup regeneration).
		- "insert a <new child> into this container" (columns, group, row, stack) → blu/insert-inner-block.
		- "change color / spacing / padding / alignment / font / any attribute" on any block (top-level or nested) → blu/update-block-attrs with the block's own client_id.
		- "add a new top-level section on the page" → blu/add-section.
		- "delete / move / reorder" → blu/delete-block or blu/move-block.

		Use edit-block ONLY when the user asks for structural content rewrites that cannot be expressed as a duplicate, insert, attribute patch, add, delete, or move — for example replacing a paragraph's text, swapping an image, or rebuilding a block's inner markup from scratch.

		Replace the entire content of an existing block (and its inner blocks) with new WordPress block markup. The block_content MUST be valid WordPress block markup with proper block comments (<!-- wp:blockname {...} -->...<!-- /wp:blockname -->). Always include all inner blocks if the target block has children. Use the client_id from the block tree context. TEMPLATE PARTS: When editing a core/template-part, provide ONLY the inner blocks markup — do NOT wrap it in <!-- wp:template-part --> comments. You can wrap blocks in core/group, core/stack, or similar container blocks when needed for layout or styling.

		ADDITIONAL RULES:
		- CONTENT PRESERVATION (CRITICAL): Copy ALL existing text, link URLs, image sources, and inner blocks from the original markup into your replacement. NEVER substitute existing content with different or generic text. If the button says "Start Creating for Free Today!" and you are changing its color, your output MUST keep that exact text — changing it to "Contact Us", "Button", or anything else is a critical error. For style-only changes (color, font, spacing), modify ONLY the block comment JSON and the corresponding HTML classes/styles — copy everything else character-for-character.
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
		- TEMPLATE PARTS: NEVER use edit-block on a template part for COLOR, STYLE, or ATTRIBUTE changes — it will lose blocks like the site-logo. Use blu/update-block-attrs on the SPECIFIC inner block instead. Only use edit-block on a template part to REPLACE ALL content with a completely new design. When ADDING content to a template part, use blu/add-section with before/after_client_id pointing INSIDE the template part.
		- IMAGES: When replacing an image already on the page, rewrite its <img src="…"> to use a __IMG_1__ (or __IMG_2__, …) placeholder and pass a descriptive prompt per placeholder in image_prompts (preferred — client generates and substitutes). Use image_urls only when you already have resolved URLs. NEVER embed a full image URL directly in multi-block block_content.
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
						'image_prompts' => array(
							'type'        => 'array',
							'items'       => array(
								'oneOf' => array(
									array( 'type' => 'string' ),
									array(
										'type'       => 'object',
										'properties' => array(
											'prompt'      => array( 'type' => 'string' ),
											'orientation' => array( 'type' => 'string' ),
											'width'       => array( 'type' => 'integer' ),
											'height'      => array( 'type' => 'integer' ),
										),
										'required'   => array( 'prompt' ),
									),
								),
							),
							'description' => 'Preferred image parameter. One entry per __IMG_N__ placeholder in block_content (in order). Each entry is either a string prompt or {prompt, orientation?, width?, height?}. The client calls blu/generate-image per entry and substitutes each placeholder with the returned URL.',
						),
						'image_urls'    => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Fallback path when resolved image URLs are already available. Ignored if image_prompts is provided. Replaces __IMG_1__, __IMG_2__, … in order.',
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
					$response_data = array(
						'action'        => 'edit_block',
						'client_id'     => sanitize_text_field( $input['client_id'] ),
						'block_content' => $input['block_content'], // Don't sanitize - it's block HTML
						'message'       => 'Block edit ready for execution',
					);

					// Forward image inputs to the client for placeholder substitution.
					if ( ! empty( $input['image_prompts'] ) && is_array( $input['image_prompts'] ) ) {
						$response_data['image_prompts'] = $input['image_prompts'];
					}
					if ( ! empty( $input['image_urls'] ) && is_array( $input['image_urls'] ) ) {
						$response_data['image_urls'] = array_map( 'esc_url_raw', $input['image_urls'] );
					}

					return blu_prepare_ability_response( 200, $response_data );
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
		Insert new block content at a specific position in the page. Use after_client_id to insert after a block, before_client_id to insert before a block, or set after_client_id to null (and omit before_client_id) to insert at the very top of the page.

		ADDITIONAL RULES:
		- VALID MARKUP: Every block_content you provide MUST be valid WordPress block markup with proper <!-- wp:name {attrs} --> comments. Never output plain HTML without block comments.
		- CONTAINER BLOCKS: Container blocks (core/group, core/columns, core/column, core/cover, core/row, core/stack, core/buttons) render content using WordPress InnerBlocks. ALL visible content inside them MUST be wrapped in proper block comments. NEVER put raw HTML like <p>, <h2>, <ul>, or <figure> directly inside a container wrapper — it will be silently stripped and the block will appear empty. WRONG: <!-- wp:group --> <div class="wp-block-group"><p>Hello</p></div> <!-- /wp:group -->. RIGHT: <!-- wp:group --> <div class="wp-block-group"><!-- wp:paragraph --> <p>Hello</p> <!-- /wp:paragraph --></div> <!-- /wp:group -->.
		- ADDING SECTIONS: You can insert content before or after ANY block at any nesting depth — not just top-level blocks. Use after_client_id to insert after a specific block, or before_client_id to insert before it. When the user does NOT specify a position, insert at the top level of the page (use after_client_id of the last top-level block in the tree, or null for the very top).
		- TEMPLATE PARTS: When adding content to a template part (e.g., adding a top bar above a header, or a banner inside a footer), use blu/add-section with before_client_id or after_client_id pointing to a block INSIDE the template part. This preserves all existing blocks and their layout. Do NOT rewrite the entire template part with blu/edit-block just to add new content — rewriting risks losing layout attributes (flex, gap, etc.) and breaking the design. Only use blu/edit-block on a template part when replacing ALL of its content with a completely different design.
		- COLORS: Use the site's theme palette slugs (base, contrast, accent-1..6) via "backgroundColor"/"textColor". Only use custom hex when the user explicitly requests a specific color. In the style object use "var:preset|color|<slug>". In inline CSS use var(--wp--preset--color--<slug>). In HTML use has-<slug>-background-color / has-<slug>-color classes.
		- IMAGES: Use __IMG_1__, __IMG_2__, … as URL placeholders in block_content and provide a descriptive prompt for each placeholder in image_prompts (preferred — the client generates and substitutes URLs automatically). Fall back to image_urls only when you already have resolved URLs on hand. NEVER embed full image URLs directly in multi-block block_content — it will be truncated.
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
						'block_content'    => array(
							'type'        => 'string',
							'description' => 'Complete WordPress block markup for the new section.',
						),
						'image_prompts'    => array(
							'type'        => 'array',
							'items'       => array(
								'oneOf' => array(
									array( 'type' => 'string' ),
									array(
										'type'       => 'object',
										'properties' => array(
											'prompt'      => array( 'type' => 'string' ),
											'orientation' => array( 'type' => 'string' ),
											'width'       => array( 'type' => 'integer' ),
											'height'      => array( 'type' => 'integer' ),
										),
										'required'   => array( 'prompt' ),
									),
								),
							),
							'description' => 'Preferred image parameter. One entry per __IMG_N__ placeholder in block_content (in order). Each entry is either a string prompt ("A bright cafe interior, wide angle") or an object {prompt, orientation?, width?, height?}. The client calls blu/generate-image per entry and substitutes each placeholder with the returned URL. The count must match the number of unique __IMG_N__ placeholders.',
						),
						'image_urls'       => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => 'Fallback path for when you already have resolved image URLs. Ignored if image_prompts is provided. Replaces __IMG_1__, __IMG_2__, … in order.',
						),
					),
					'required'   => array( 'block_content' ),
				),
				'execute_callback'    => function ( $input ) {
					// Validate: block_content is required
					if ( empty( $input['block_content'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'block_content is required' ) );
					}

					// Validate: after_client_id and before_client_id are mutually exclusive.
					// Only a non-empty string counts as "set" — explicit null on after_client_id
					// is the documented way to request "top of page" and must not conflict.
					$has_after_param  = ! empty( $input['after_client_id'] ) && is_string( $input['after_client_id'] );
					$has_before_param = ! empty( $input['before_client_id'] ) && is_string( $input['before_client_id'] );
					if ( $has_after_param && $has_before_param ) {
						return blu_prepare_ability_response(
							400,
							array(
								'message' => 'after_client_id and before_client_id are mutually exclusive. Provide only one.',
							)
						);
					}

					// after_client_id can be null (top of page) or a string
					$after_client_id = isset( $input['after_client_id'] ) && '' !== $input['after_client_id']
						? sanitize_text_field( $input['after_client_id'] )
						: null;

					// before_client_id is used to insert BEFORE a given block
					$before_client_id = isset( $input['before_client_id'] ) && '' !== $input['before_client_id']
						? sanitize_text_field( $input['before_client_id'] )
						: null;

					// Return action data for client-side execution
					$response_data = array(
						'action'        => 'add_section',
						'block_content' => $input['block_content'],
						'message'       => 'Section add ready for execution',
					);

					// Forward positional information based on which parameter is used
					if ( null !== $after_client_id ) {
						$response_data['after_client_id'] = $after_client_id;
					}
					if ( null !== $before_client_id ) {
						$response_data['before_client_id'] = $before_client_id;
					}

					// Forward image inputs to the client for placeholder substitution.
					// image_prompts is the preferred path (client generates via blu/generate-image);
					// image_urls is a fallback when the caller already has URLs.
					if ( ! empty( $input['image_prompts'] ) && is_array( $input['image_prompts'] ) ) {
						$response_data['image_prompts'] = $input['image_prompts'];
					}
					if ( ! empty( $input['image_urls'] ) && is_array( $input['image_urls'] ) ) {
						$response_data['image_urls'] = array_map( 'esc_url_raw', $input['image_urls'] );
					}

					return blu_prepare_ability_response( 200, $response_data );
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
	 * Register the unified blu/duplicate-block ability.
	 *
	 * Dual-mode: caller passes EITHER `client_id` (explicit target) OR `kind`
	 * (intent-based; client resolves to a concrete block via the block lexicon
	 * and target resolver). Callers should use `kind` whenever the user phrasing
	 * is "another X" / "one more X", and `client_id` only when the user points
	 * at a specific block with "this" / "that".
	 *
	 * @return void
	 */
	private function register_duplicate(): void {
		$description = <<<'DESC'
		Duplicate a block with its exact existing design. Much faster than edit-block — no markup is regenerated.

		TWO CALLING MODES. Pick based on how the user phrased the request:

		1. Intent mode (PREFERRED when the user says "another X", "one more X", "add a <kind>" where <kind> matches an existing block on the page):
		   Pass { kind: "<word>" }. The system finds the right block deterministically.
		   - "add another column"      → { kind: "column" }
		   - "add another card"        → { kind: "card" }
		   - "add another button"      → { kind: "button" }
		   - "add another menu item"   → { kind: "menu-item" }
		   - "add one more testimonial"→ { kind: "testimonial" }
		   You may optionally add `scope` (a clientId bounding the search, e.g. a specific section) and `position` ("last" | "first" | integer; default "last").

		2. Explicit mode (only when the user points at a specific block — "duplicate THIS", "duplicate that card", or the selection IS exactly what they want another of):
		   Pass { client_id: "<UUID>" } — copy the UUID EXACTLY from the block tree's `id:<UUID>` field. Never invent placeholder strings.

		Rule of thumb: if you can name what the user said ("column", "card", "button", …), use Intent mode. Only use Explicit mode when the user is clearly referring to one specific block.

		Known kinds: column, button, buttons, image, heading, paragraph, list, list-item, menu-item, card, testimonial, team-member, pricing-tier, faq-item, section, row. Common aliases (col, cols, btn, cta, img, text, p, cards, testimonials, reviews, member, plan, tier, faq, sections) are normalized automatically. If you're unsure which kind a user's word maps to, pass it as-is — the resolver returns a list of known kinds if it doesn't match.

		The clone is inserted immediately after the resolved block as a sibling.
		DESC;

		blu_register_ability(
			'blu/duplicate-block',
			array(
				'label'               => 'Duplicate Block',
				'description'         => $description,
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'client_id' => array(
							'type'        => 'string',
							'description' => 'Explicit mode: the exact clientId (UUID) of the block to duplicate, copied from the block tree. Use only when the user points at a specific block.',
							'pattern'     => '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$',
						),
						'kind'      => array(
							'type'        => 'string',
							'description' => 'Intent mode: the user-facing word for what they want another of ("column", "card", "button", "menu-item", "testimonial", …). Preferred over client_id for "another X" requests.',
						),
						'scope'     => array(
							'type'        => 'string',
							'description' => 'Intent mode (optional): clientId bounding the search — usually a section/container. Defaults to the selected block\'s ancestors.',
							'pattern'     => '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$',
						),
						'position'  => array(
							'description' => 'Intent mode (optional): which matching sibling to clone. "last" (default), "first", or a 0-based integer index.',
							'oneOf'       => array(
								array(
									'type' => 'string',
									'enum' => array( 'last', 'first' ),
								),
								array( 'type' => 'integer' ),
							),
						),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => function ( $input ) {
					$has_client_id = ! empty( $input['client_id'] );
					$has_kind      = ! empty( $input['kind'] );

					if ( ! $has_client_id && ! $has_kind ) {
						return blu_prepare_ability_response(
							400,
							array(
								'message' => 'Pass EITHER { kind: "<word>" } (intent mode, preferred for "another X") OR { client_id: "<UUID>" } (explicit mode). Neither was supplied.',
							)
						);
					}

					$response = array(
						'action'  => 'duplicate',
						'message' => 'Block duplicate ready for execution',
					);

					if ( $has_client_id ) {
						if ( ! preg_match( '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $input['client_id'] ) ) {
							return blu_prepare_ability_response(
								400,
								array(
									'message' => sprintf(
										'client_id must be a real UUID from the block tree. Got: "%s". Either switch to intent mode with { kind: "..." } or re-read the block tree and copy the UUID from id:<UUID>.',
										$input['client_id']
									),
								)
							);
						}
						$response['client_id'] = sanitize_text_field( $input['client_id'] );
					}

					if ( $has_kind ) {
						$response['kind'] = sanitize_text_field( $input['kind'] );
						if ( ! empty( $input['scope'] ) ) {
							$response['scope'] = sanitize_text_field( $input['scope'] );
						}
						if ( isset( $input['position'] ) ) {
							$response['position'] = is_int( $input['position'] ) ? $input['position'] : sanitize_text_field( (string) $input['position'] );
						}
					}

					return blu_prepare_ability_response( 200, $response );
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
	 * Register ability to insert a new block as a child of an existing container.
	 *
	 * Use when the user wants to add a NEW child to a container (core/columns,
	 * core/group, core/row, core/stack, core/buttons, etc.) without rewriting the
	 * whole parent. Only the new child's markup is emitted — the parent and its
	 * other children are untouched.
	 *
	 * @return void
	 */
	private function register_insert_inner_block(): void {
		blu_register_ability(
			'blu/insert-inner-block',
			array(
				'label'               => 'Insert Inner Block',
				'description'         => 'Insert a new block as a child of an existing container block (core/columns, core/group, core/row, core/stack, core/buttons, etc.). Use this when the user asks to insert a NEW item inside a container whose design does NOT already exist on the page — e.g. "add a heading at the top of this group", "insert a button in this buttons row". If a sibling with the same design already exists, prefer blu/duplicate-block instead. Only emit the new child block_content; the parent and its existing children are not touched. Provide parent_client_id (the container) and the new block_content. Optional: index (0-based position; omit to append at the end).',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'parent_client_id' => array(
							'type'        => 'string',
							'description' => 'The clientId of the CONTAINER block that will receive the new child (from the block tree context).',
						),
						'block_content'    => array(
							'type'        => 'string',
							'description' => 'Valid WordPress block markup for the new child block. Must include proper <!-- wp:blockname {...} --> comments. Only the new child — do NOT re-emit the parent.',
						),
						'index'            => array(
							'type'        => 'integer',
							'description' => 'Optional 0-based insert position within the parent. Omit to append at the end.',
						),
					),
					'required'   => array( 'parent_client_id', 'block_content' ),
				),
				'execute_callback'    => function ( $input ) {
					if ( empty( $input['parent_client_id'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'parent_client_id is required' ) );
					}
					if ( empty( $input['block_content'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'block_content is required' ) );
					}
					$response_data = array(
						'action'           => 'insert_inner_block',
						'parent_client_id' => sanitize_text_field( $input['parent_client_id'] ),
						'block_content'    => $input['block_content'],
						'message'          => 'Inner block insert ready for execution',
					);
					if ( isset( $input['index'] ) && is_int( $input['index'] ) ) {
						$response_data['index'] = $input['index'];
					}
					return blu_prepare_ability_response( 200, $response_data );
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
	 * Register ability to move a block
	 *
	 * Moves a block (with all inner blocks) to a new position.
	 * Supports sibling mode (before/after another block) and child mode (into a container block).
	 *
	 * @return void
	 */
	private function register_move_block(): void {
		blu_register_ability(
			'blu/move-block',
			array(
				'label'               => 'Move Block',
				'description'         => 'Move a block (with all its inner blocks) to a new position. Two modes: (1) Sibling mode: provide target_client_id + position to place before/after another block. (2) Child mode: provide as_child_of to move the block inside a container block (e.g. move a column into a columns block). Use this when the user asks to reorder sections, move content up/down, rearrange page layout, or restructure blocks into new containers.',
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
							'description' => 'Sibling mode: the clientId of the block to position relative to. Required when using position.',
						),
						'position'         => array(
							'type'        => 'string',
							'enum'        => array( 'before', 'after' ),
							'description' => 'Sibling mode: where to place the block relative to the target: "before" or "after"',
						),
						'as_child_of'      => array(
							'type'        => 'string',
							'description' => 'Child mode: the clientId of the container block to move into (e.g. a core/columns block). The block is appended as the last child. Use this to restructure layouts, e.g. moving columns between different columns blocks.',
						),
					),
					'required'   => array( 'client_id' ),
				),
				'execute_callback'    => function ( $input ) {
					// Validate required fields
					if ( empty( $input['client_id'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'client_id is required' ) );
					}

					$has_sibling_mode = ! empty( $input['target_client_id'] ) && ! empty( $input['position'] );
					$has_child_mode   = ! empty( $input['as_child_of'] );

					if ( $has_sibling_mode && $has_child_mode ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'Cannot use both sibling mode and child mode' ) );
					}

					if ( ! $has_sibling_mode && ! $has_child_mode ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'Provide either (target_client_id + position) for sibling mode or as_child_of for child mode' ) );
					}

					if ( $has_sibling_mode && ! in_array( $input['position'], array( 'before', 'after' ), true ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'position must be "before" or "after"' ) );
					}

					$response_data = array(
						'action'    => 'move_block',
						'client_id' => sanitize_text_field( $input['client_id'] ),
						'message'   => 'Block move ready for execution',
					);

					if ( $has_child_mode ) {
						$response_data['as_child_of'] = sanitize_text_field( $input['as_child_of'] );
					} else {
						$response_data['target_client_id'] = sanitize_text_field( $input['target_client_id'] );
						$response_data['position']         = sanitize_text_field( $input['position'] );
					}

					// Return action data for client-side execution
					return blu_prepare_ability_response( 200, $response_data );
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
	 * Register ability to update block attributes without replacing markup.
	 *
	 * Deep-merges new attributes into the existing block — no need to read
	 * or rewrite the full markup. Use for colors, font sizes, alignment,
	 * spacing, overlays, gradients, layout, and any block comment JSON change.
	 *
	 * @return void
	 */
	private function register_update_block_attrs(): void {
		$description = <<<'DESC'
		Patch attributes on an existing block — deep-merged into whatever is already there. No markup rewrite. Works for any block comment JSON: colors, spacing, layout, border, className, typography, content (text on core/paragraph, core/heading, core/button), url/alt on media blocks, and anything else in the block's attributes. Set a value to null to remove it.

		Use this for ALL tweaks to an existing block: style, content, borders, images, etc. Chain multiple calls in the same turn if you need to customize several blocks (e.g. after duplicate or insert, patch the new block's text, icon, and styling to match the user's request). Much faster than edit-block for incremental changes.

		When the user references text inside a container (a paragraph inside a column, a button inside a group), target the LEAF block's clientId — not the container's.

		If the block uses nfd-* utility classes that control the property you're changing, include "className" with that class removed in the same patch — the CSS class otherwise silently overrides your attribute.
		DESC;

		blu_register_ability(
			'blu/update-block-attrs',
			array(
				'label'               => 'Update Block Attributes',
				'description'         => $description,
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'client_id'  => array(
							'type'        => 'string',
							'description' => 'The clientId of the block to update.',
						),
						'attributes' => array(
							'type'        => 'object',
							'description' => 'An object containing the attributes to merge into the block. All attribute keys (style, className, color, fontSize, etc.) MUST be nested inside this "attributes" object — never place them at the top level alongside client_id. Nested objects are deep-merged. Set a key to null to remove it. Correct: {"attributes": {"style": {"color": {"background": "#fff"}}}}. Wrong: {"style": {"color": {"background": "#fff"}}}.',
						),
					),
					'required'   => array( 'client_id', 'attributes' ),
				),
				'execute_callback'    => function ( $input ) {
					if ( empty( $input['client_id'] ) || empty( $input['attributes'] ) ) {
						return blu_prepare_ability_response( 400, array( 'message' => 'client_id and attributes are required' ) );
					}

					return blu_prepare_ability_response(
						200,
						array(
							'action'     => 'update_block_attrs',
							'client_id'  => sanitize_text_field( $input['client_id'] ),
							'attributes' => $input['attributes'],
							'message'    => 'Block attribute update ready for execution',
						)
					);
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
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
	 * Register ability to get block markup.
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
