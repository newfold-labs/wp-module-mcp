<?php
/**
 * This class manage the Abilities managed like Prompts
 *
 * @package BLU\Abilities
 */

namespace BLU\Abilities;

use WP_Error;

/**
 * The class
 */
class Prompts {
	/**
	 * Constructor - registers WooCommerce product abilities if WooCommerce is active.
	 */
	public function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->register_guided_product_creation_prompt();
		$this->register_prompt_description();
		$this->register_smart_product_prompt();
		$this->register_prompt_categories();
		$this->register_prompt_tags();
		$this->register_prompt_brands();
	}

	/**
	 * Create a prompt to instruct AI all step required to add a new WooCommerce Product
	 *
	 * @return void
	 */
	private function register_guided_product_creation_prompt() {
		blu_register_ability(
			'blu/guided-product-creation-prompt',
			array(
				'label'               => 'Guided Product Creation',
				'description'         => 'Step-by-step wizard that guides the merchant through enriching and publishing a WooCommerce product. Calls blu tools directly for categories, tags, descriptions, and variations — no sub-prompts.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'product_name'  => array(
							'type'        => 'string',
							'description' => 'The name of the product to create.',
						),
						'price'         => array(
							'type'        => 'string',
							'description' => 'Regular price (e.g. 29.99). If omitted, the assistant will suggest a market price.',
						),
						'extra_details' => array(
							'type'        => 'string',
							'description' => 'Any additional product context the merchant already has (type, material, specs, etc.).',
						),
					),
					'required'   => array( 'product_name' ),
				),
				'execute_callback'    => function ( $input ) {

					$product_name  = sanitize_text_field( $input['product_name'] );
					$price         = isset( $input['price'] ) && '' !== $input['price'] && is_numeric( $input['price'] ) ? (float) $input['price'] : null;
					$extra_details = sanitize_textarea_field( isset( $input['extra_details'] ) ? $input['extra_details'] : '' );

					$price_display = null !== $price
						? wc_price( $price, array( 'in_span' => false ) )
						: '(not set — you will suggest one)';

					$product_name_safe = addslashes( $product_name );
					$price_safe        = addslashes( $price_display );
					$details_safe      = addslashes( $extra_details );
					$system_text       = include_once __DIR__ . '/../instructions/product-full-flow.php';
					$price_line        = null !== $price
						? sprintf( 'price: **$%.2f**', $price )
						: 'price: **not set yet** — I\'ll suggest one';

					$intro_text = sprintf(
						"Let's add **%s** (%s) to your WooCommerce store!\n\n" .
						'How would you like to proceed?\n\n' .
						"**A)** Add the product now with only the details you provided.\n" .
						"**B)** Enrich the product first — I'll suggest categories, tags, " .
						"description, and/or variations.\n\n" .
						'Which option would you prefer?',
						esc_html( $product_name ),
						$price_line
					);

					return array(
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => array(
									'type'        => 'text',
									'text'        => $system_text,
									'annotations' => array(
										'audience' => array( 'assistant' ),
										'priority' => 1.0,
									),
								),
							),
							array(
								'role'    => 'assistant',
								'content' => array(
									'type'        => 'text',
									'text'        => $intro_text,
									'annotations' => array(
										'audience' => array( 'user' ),
										'priority' => 0.9,
									),
								),
							),
						),
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'audience'     => array( 'user', 'assistant' ),
						'priority'     => 1.0,
						'lastModified' => gmdate( 'c' ),
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'prompt',
					),
				),
			)
		);
	}

	/**
	 * Create a prompt to instruct the AI the steps to follow to generate/improve a new  long and short description
	 *
	 * @return void
	 */
	private function register_prompt_description() {
		blu_register_ability(
			'blu/suggest-product-description',
			array(
				'label'               => 'Guided Product Description Generation',
				'category'            => 'blu-mcp',
				'description'         => 'Improves or generates the description and short description for a WooCommerce product. When a product ID is provided the existing content, categories and tags are used as context. Without an ID it generates descriptions from scratch using the product name.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'product_id'   => array(
							'type'        => 'integer',
							'description' => 'Existing WooCommerce product ID. When provided the prompt loads the product and uses its data as context.',
							'minimum'     => 1,
						),
						'product_name' => array(
							'type'        => 'string',
							'description' => 'Product name — required when product_id is not supplied.',
						),
						'tone'         => array(
							'type'        => 'string',
							'description' => 'Writing tone for the generated descriptions.',
							'enum'        => array( 'formal', 'technical', 'empathetic', 'persuasive' ),
							'default'     => 'formal',
						),
					),
				),
				'execute_callback'    => function ( $input ) {
					$product_id   = isset( $input['product_id'] ) ? (int) $input['product_id'] : null;
					$product_name = sanitize_text_field( isset( $input['product_name'] ) ? $input['product_name'] : '' );
					$tone         = isset( $input['tone'] ) ? $input['tone'] : '';
					$tone         = in_array(
						$tone,
						array(
							'formal',
							'technical',
							'empathetic',
							'persuasive',
						),
						true
					)
						? $input['tone']
						: 'formal';

					$has_id = null !== $product_id && $product_id > 0;
					$mode   = $has_id ? 'improve' : 'create';

					$product_id_safe   = $has_id ? (string) $product_id : '(none)';
					$product_name_safe = addslashes( $product_name );
					$tone_safe         = addslashes( $tone );
					$mode_safe         = $mode;
					$instruction       = include_once __DIR__ . '/../instructions/product-description-improvement.php';

					if ( $has_id ) {
						$intro_text = sprintf(
							"Let's improve the descriptions for product **#%d**.\n\n" .
							"I'll load the current content, categories, and tags — then use them as context to generate better copy.\n\n" .
							"**Tone:** %s%s\n\n" .
							'Loading product data now…',
							$product_id,
							ucfirst( $tone ),
							'formal' === $tone ? ' *(default)*' : ''
						);
					} else {
						$intro_text = sprintf(
							"Let's write descriptions for **%s**.\n\n" .
							"Since no product ID was provided, I'll generate fresh copy from scratch.\n\n" .
							"**Tone:** %s%s\n\n" .
							"Do you have any categories or tags you'd like me to factor in? " .
							'*(Reply with them or say \"none\" to skip.)*',
							! empty( $product_name ) ? esc_html( $product_name ) : 'your product',
							ucfirst( $tone ),
							'formal' === $tone ? ' *(default)*' : ''
						);
					}

					return array(
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => array(
									'type'        => 'text',
									'text'        => $instruction,
									'annotations' => array(
										'audience' => array( 'assistant' ),
										'priority' => 1.0,
									),
								),
							),
							array(
								'role'    => 'assistant',
								'content' => array(
									'type'        => 'text',
									'text'        => $intro_text,
									'annotations' => array(
										'audience' => array( 'user' ),
										'priority' => 0.9,
									),
								),
							),
						),
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'audience'     => array( 'user', 'assistant' ),
						'priority'     => 1.0,
						'lastModified' => gmdate( 'c' ),
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'prompt',
					),
				),
			)
		);
	}


	/**
	 * Create a prompt to instruct the AI the step to follow to suggest the categories
	 *
	 * @return void
	 */
	private function register_prompt_categories() {
		blu_register_ability(
			'blu/suggest-product-categories',
			array(
				'label'               => 'Suggest Product Categories',
				'category'            => 'blu-mcp',
				'description'         => 'Suggest WooCommerce and Google taxonomy categories using an existing product ID, or a product name when the product is not in the store yet.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'product_id' => array(
							'type'        => 'integer',
							'description' => 'Existing WooCommerce product ID. When set, the assistant loads the product and uses its data to build category search patterns.',
							'minimum'     => 1,
						),
						'name'       => array(
							'type'        => 'string',
							'description' => 'Product name — use when there is no product ID yet, or as a hint alongside troubleshooting.',
							'default'     => '',
						),
					),
				),
				'execute_callback'    => function ( $input ) {
					$product_id   = isset( $input['product_id'] ) ? (int) $input['product_id'] : 0;
					$product_name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

					$has_id = $product_id > 0;
					$mode   = $has_id ? 'existing' : 'planned';

					$product_id_safe   = $has_id ? (string) $product_id : '(none)';
					$product_name_safe = addslashes( $product_name );
					$mode_safe         = $mode;

					$instruction = include_once __DIR__ . '/../instructions/product-categories-suggester.php';

					if ( $has_id ) {
						$intro_text = sprintf(
							"Let's suggest categories for **product #%d**.\n\n" .
							"I'll load the product, then match your store categories with the Google Product Taxonomy.\n\n" .
							'%s',
							$product_id,
							! empty( $product_name )
								? sprintf( "*(You also provided the name \"%s\" — I'll treat it as extra context if it differs.)*\n\n", esc_html( $product_name ) )
								: ''
						) . 'Loading product data now…';
					} else {
						$intro_text = sprintf(
							"Let's suggest categories for **%s**.\n\n" .
							'No product ID was given, so I will work from the name only (the product may not exist in WooCommerce yet).',
							! empty( $product_name ) ? esc_html( $product_name ) : 'your product'
						);
					}

					return array(
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => array(
									'type'        => 'text',
									'text'        => $instruction,
									'annotations' => array(
										'audience' => array( 'assistant' ),
										'priority' => 0.9,
									),
								),
							),
							array(
								'role'    => 'assistant',
								'content' => array(
									'type'        => 'text',
									'text'        => $intro_text,
									'annotations' => array(
										'audience' => array( 'user' ),
										'priority' => 0.9,
									),
								),
							),
						),
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'prompt',
					),
				),
			)
		);
	}

	/**
	 * Create a prompt to instruct the AI the step to follow to suggest the tag
	 *
	 * @return void
	 */
	private function register_prompt_tags() {
		blu_register_ability(
			'blu/suggest-product-tag',
			array(
				'label'               => 'Suggest Product Tags',
				'category'            => 'blu-mcp',
				'description'         => 'Suggest WooCommerce product tags using an existing product ID, or a product name when the product is not in the store yet (same workflow as guided product flow Step 3-B).',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'product_id' => array(
							'type'        => 'integer',
							'description' => 'Existing WooCommerce product ID. When set, the assistant loads the product and uses its data to build tag search patterns.',
							'minimum'     => 1,
						),
						'name'       => array(
							'type'        => 'string',
							'description' => 'Product name — use when there is no product ID yet, or as extra context alongside an ID.',
							'default'     => '',
						),
					),
				),
				'execute_callback'    => function ( $input ) {
					$product_id   = isset( $input['product_id'] ) ? (int) $input['product_id'] : 0;
					$product_name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

					$has_id = $product_id > 0;
					$mode   = $has_id ? 'existing' : 'planned';

					$product_id_safe   = $has_id ? (string) $product_id : '(none)';
					$product_name_safe = addslashes( $product_name );
					$mode_safe         = $mode;

					$instruction = include_once __DIR__ . '/../instructions/product-tags-suggester.php';

					if ( $has_id ) {
						$intro_text = sprintf(
							"Let's suggest tags for **product #%d**.\n\n" .
							"I'll load the product, list matching store tags, and fill in SEO ideas if needed.\n\n" .
							'%s',
							$product_id,
							! empty( $product_name )
								? sprintf( "*(You also provided the name \"%s\" — I'll treat it as extra context if it differs.)*\n\n", esc_html( $product_name ) )
								: ''
						) . 'Loading product data now…';
					} else {
						$intro_text = sprintf(
							"Let's suggest tags for **%s**.\n\n" .
							'No product ID was given, so I will work from the name only (the product may not exist in WooCommerce yet).',
							! empty( $product_name ) ? esc_html( $product_name ) : 'your product'
						);
					}

					return array(
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => array(
									'type'        => 'text',
									'text'        => $instruction,
									'annotations' => array(
										'audience' => array( 'assistant' ),
										'priority' => 0.9,
									),
								),
							),
							array(
								'role'    => 'assistant',
								'content' => array(
									'type'        => 'text',
									'text'        => $intro_text,
									'annotations' => array(
										'audience' => array( 'user' ),
										'priority' => 0.9,
									),
								),
							),
						),
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'prompt',
					),
				),
			)
		);
	}

	/**
	 * Create a prompt to instruct the AI the step to follow to suggest the brand
	 *
	 * @return void
	 */
	private function register_prompt_brands() {
		blu_register_ability(
			'blu/suggest-product-brand',
			array(
				'label'               => 'Suggest Product Brands',
				'category'            => 'blu-mcp',
				'description'         => 'Generate a list of product brands based on product details',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'product_id' => array(
							'type'        => 'integer',
							'description' => 'Existing WooCommerce product ID. When set, the assistant loads the product and uses its data to build tag search patterns.',
							'minimum'     => 1,
						),
						'name'       => array(
							'type'        => 'string',
							'description' => 'Product name — use when there is no product ID yet, or as extra context alongside an ID.',
							'default'     => '',
						),
					),
				),
				'execute_callback'    => function ( $input ) {
					$product_id   = isset( $input['product_id'] ) ? (int) $input['product_id'] : 0;
					$product_name = isset( $input['name'] ) ? sanitize_text_field( $input['name'] ) : '';

					$has_id = $product_id > 0;
					$mode   = $has_id ? 'existing' : 'planned';

					$product_id_safe   = $has_id ? (string) $product_id : '(none)';
					$product_name_safe = addslashes( $product_name );
					$mode_safe         = $mode;

					$instruction = include_once __DIR__ . '/../instructions/product-brands-suggester.php';

					if ( $has_id ) {
						$intro_text = sprintf(
							"Let's suggest brands for **product #%d**.\n\n" .
											"I'll load the product, list matching store brands, and fill in SEO ideas if needed.\n\n" .
											'%s',
							$product_id,
							! empty( $product_name )
												? sprintf( "*(You also provided the name \"%s\" — I'll treat it as extra context if it differs.)*\n\n", esc_html( $product_name ) )
												: ''
						) . 'Loading product data now…';
					} else {
						$intro_text = sprintf(
							"Let's suggest brands for **%s**.\n\n" .
							'No product ID was given, so I will work from the name only (the product may not exist in WooCommerce yet).',
							! empty( $product_name ) ? esc_html( $product_name ) : 'your product'
						);
					}

					return array(
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => array(
									'type'        => 'text',
									'text'        => $instruction,
									'annotations' => array(
										'audience' => array( 'assistant' ),
										'priority' => 0.9,
									),
								),
							),
							array(
								'role'    => 'assistant',
								'content' => array(
									'type'        => 'text',
									'text'        => $intro_text,
									'annotations' => array(
										'audience' => array( 'user' ),
										'priority' => 0.9,
									),
								),
							),
						),
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'prompt',
					),
				),
			)
		);
	}

	/**
	 * Register the prompt for the smart product details
	 *
	 * @return void
	 */
	private function register_smart_product_prompt() {
		blu_register_ability(
			'blu/smart-product-details',
			array(
				'label'               => 'Merchant Content Intelligence Generator',
				'category'            => 'blu-mcp',
				'description'         => 'A compact all‑in‑one prompt for merchants that uses the product ID and basic product details to automatically generate all key listing content — required materials, size charts, care instructions, warranty info, and ingredient lists — ensuring every product page is complete and compliant.',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Product ID.',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					if ( ! isset( $input['id'] ) ) {
						return blu_standardize_rest_response(
							new WP_Error(
								400,
								'Miss required Product ID.',
							)
						);
					}
					$product_id = $input['id'];
					$product    = wc_get_product( $input['id'] );
					if ( ! $product ) {
						return blu_standardize_rest_response(
							new WP_Error(
								400,
								'Invalid Product ID.',
							)
						);
					}

					$sections    = isset( $input['sections'] ) ? $input['sections'] : array();
					$name        = $product->get_title();
					$description = $product->get_description();
					$categories  = wc_get_product_category_list( $product->get_id() );
					$tags        = wc_get_product_tag_list( $product->get_id() );
					$append_mode = empty( $description ) ? 'replace' : 'append';

					$append_label = 'replace' === $append_mode
						? 'Replace existing description'
						: 'Append to existing description';

					$sections_list = ! empty( $sections )
						? implode( ', ', $sections )
						: '(auto-detect based on product data)';

					$desc_preview = mb_strlen( $description ) > 120
						? mb_substr( strip_tags( $description ), 0, 120 ) . '…'
						: ( strip_tags( $description ) ? strip_tags( $description ) : '(empty)' );

					// Safe versions for heredoc injection
					$name_safe        = addslashes( $name );
					$description_safe = addslashes( $description );
					$categories_safe  = addslashes( $categories ? $categories : '(none)' );
					$tags_safe        = addslashes( $tags ? $tags : '(none)' );

					$instruction = include_once __DIR__ . '/../instructions/smart-product-details.php';

					$sections_note = "I'll auto-detect which content sections apply based on the product data.";

					$intro_text = sprintf(
						"🔍 Generating supplementary content for **%s** (ID: #%d).\n\n" .
						"| Field       | Value |\n" .
						"|-------------|-------|\n" .
						"| Categories  | %s |\n" .
						"| Tags        | %s |\n" .
						"| Description | %s |\n\n" .
						"%s\n\n" .
						"**Save mode:** %s\n\n" .
						'Analysing product data now…',
						esc_html( $name ),
						$product_id,
						esc_html( $categories ? $categories : '(none)' ),
						esc_html( $tags ? $tags : '(none)' ),
						esc_html( $desc_preview ),
						$sections_note,
						$append_label
					);

					return array(
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => array(
									'type'        => 'text',
									'text'        => $instruction,
									'annotations' => array(
										'audience' => array( 'assistant' ),
										'priority' => 1.0,
									),
								),
							),
							array(
								'role'    => 'assistant',
								'content' => array(
									'type'        => 'text',
									'text'        => $intro_text,
									'annotations' => array(
										'audience' => array( 'user' ),
										'priority' => 0.9,
									),
								),
							),
						),
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'   => true,
						'idempotent' => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'prompt',
					),
				),
			)
		);
	}
}
