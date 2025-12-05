<?php
/**
 * This class manage the Abilities managed like Prompts
 *
 * @package BLU\Abilities
 */

namespace BLU\Abilities;

class Prompts {
	/**
	 * Constructor - registers WooCommerce product abilities if WooCommerce is active.
	 */
	public function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->register_prompt_categories();
		$this->register_prompt_tags();
		$this->register_prompt_brands();
	}

	/**
	 * Create a prompt to instruct the AI the step to follow to suggest the categories
	 *
	 * @return void
	 */
	private function register_prompt_categories() {
		blu_register_ability( 'blu/suggest-product-categories', [
			'label'               => 'Suggest Product Categories',
			'category'            => 'blu-mcp',
			'description'         => 'Generate a list of product categories based on product details',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'name'        => array(
						'type'        => 'string',
						'description' => 'Product name',
						'default'     => '',
					),
					'description' => array(
						'type'        => 'string',
						'description' => 'Product description',
					)
				),
				'required'   => array( 'name' )
			),
			'execute_callback'    => function ( $input ) {
				$name = $input['name'] ?? '';
				$desc = $input['description'] ?? '';
				$desc = !empty( $desc ) ? 'and these details :'.$desc : '';
				return [
					'messages' => [
						[
							'role'    => 'user',
							'content' => [
								'type'        => 'text',
								'text'        => "Using **only** the resources returned by abilities `blu/wc-list-product-categories` and `blu/google-product-taxonomy`:

1. **Step 1 – Store Categories First**
   - Always call `blu/wc-list-product-categories` analyze the store categories returned by the ability and:.
   - From the store categories, filter and present only those categories that are relevant to the product `$name` and `$desc`.
   - Build the COMPLETE hierarchical structure by tracing all parent-child relationships using the 'parent' field
   - For each relevant category, show the FULL path from root to leaf (e.g., Parent > Child > Grandchild)
   - Present ALL levels of the hierarchy, not just the top-level categories
   - The return must always be an array named `categories` with this format:
     { categories: [ 'cat1', 'cat2' ] }
   - Ask the customer if they want to use one or more of these store categories.
   - **Important:** If the customer chooses from store categories, do NOT call any add category ability. Simply return their selection.

2. **Step 2 – Google Product Taxonomy (Optional)**
   - If the customer prefers to check Google taxonomy, then proceed with the taxonomy verification process:
     - Identify the most relevant categories for the product `$name $desc`.
     - Always return the **complete category path** exactly as listed in the resource.
     - Verify each parent-child relationship step-by-step in the JSON structure.
     - Document the navigation path: Root → Level1 → Level2 → … → Leaf.
     - Only return paths where every step is confirmed.
     - Never combine categories from different branches.
     - Do not generate, suggest, or accept any custom or user-defined categories.

3. **Step 3 – Confidence Scoring**
   - For each valid Google taxonomy entry, calculate a numeric confidence score.
   - Sort results by confidence score (highest first).
   - Present the sorted list to the customer and require them to select one or more categories.

4. **Step 4 – Confirmation**
   - Do not automatically add categories to the store.
   - Always ask the customer for confirmation.

5. **Step 5 – Output Format**
   - Return the customer’s selection strictly as an array named `categories`, containing only the selected full path(s).
   - Include fields:
     - `'is_google_tax': 'true'`
     - `'hierarchical': 'true'`

**Example of correct verification:**
- For 'Pens': Verify Office Supplies exists → Verify Office Instruments exists under Office Supplies → Verify Writing & Drawing Instruments exists under Office Instruments → Continue until Pens.
- If any step fails, that path is INVALID and must not be returned.

**Output format example for google product taxonomy:**
{
  'categories': [
    'Food, Beverages & Tobacco > Beverages > Coffee > Coffee Beans'
  ],
  'is_google_tax': 'true',
  'hierarchical': 'true'
}
",
								'annotations' => [
									'audience' => [ 'assistant' ],
									'priority' => 0.9
								]
							]
						]
					]
				];
			},
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'meta'                => [
				'arguments'   => [
					[
						'name'        => 'name',
						'description' => 'Product name to check',
						'required'    => true
					],
					[
						'name'        => 'description',
						'description' => 'Product description',
						'required'    => false
					]
				],
				'annotations' => [
					'readOnlyHint'   => true,
					'idempotentHint' => true
				],
				'mcp'         => [
					'public' => true,   // Expose this ability via MCP
					'type'   => 'prompt' // Mark as prompt for auto-discovery
				]
			]
		] );
	}

	/**
	 * Create a prompt to instruct the AI the step to follow to suggest the tag
	 *
	 * @return void
	 */
	private function register_prompt_tags() {
		blu_register_ability( 'blu/suggest-product-tag', [
			'label'               => 'Suggest Product Tag',
			'category'            => 'blu-mcp',
			'description'         => 'Generate a list of product tag based on product details',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'name'        => array(
						'type'        => 'string',
						'description' => 'Product name',
						'default'     => '',
					),
					'description' => array(
						'type'        => 'string',
						'description' => 'Product description',
					),
					'categories' => array(
						'type'        => 'string',
						'description' => 'A comma separated product categories list',
					)
				),
				'required'   => array( 'name' )
			),
			'execute_callback'    => function ( $input ) {
				$name = $input['name'] ?? '';
				$desc = $input['description'] ?? '';
				$desc = !empty( $desc ) ? '.\n Here a short description for this product :'.$desc.'.\n' : '';
				$categories = !empty( $input['categories'] )  ? '\n The product has these categories :'.$input['categories']: '';
				return [
					'messages' => [
						[
							'role'    => 'user',
							'content' => [
								'type'        => 'text',
								'text'        => "Generate SEO‑optimized tags for the product $name $desc $categories.\n 
												- Focus on keywords that improve search visibility and relevance. 
												- Limit the number of tags to between 5 and 7 items only. 
												- Do not include unrelated or generic terms.
												- Include both short‑tail and long‑tail keywords. 
												- Show the list as numeric list.
												- Ensure tags are product‑specific, customer‑oriented, and aligned with common search queries. 
												- Require to customer to select one or more tag from it
												- Return the customer’s selection strictly as an array named `tags`. 
											Output format example:
												{
												  'tags': [
												    'tag1',
												    'tag2',
												    'tag3',
												  ]
												}
											",
								'annotations' => [
									'audience' => [ 'assistant' ],
									'priority' => 0.9
								]
							]
						]
					]
				];
			},
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'meta'                => [
				'arguments'   => [
					[
						'name'        => 'name',
						'description' => 'Product name to check',
						'required'    => true
					],
					[
						'name'        => 'description',
						'description' => 'Product description',
						'required'    => false
					]
				],
				'annotations' => [
					'readOnlyHint'   => true,
					'idempotentHint' => true
				],
				'mcp'         => [
					'public' => true,   // Expose this ability via MCP
					'type'   => 'prompt' // Mark as prompt for auto-discovery
				]
			]
		] );
	}

	/**
	 * Create a prompt to instruct the AI the step to follow to suggest the brand
	 *
	 * @return void
	 */
	private function register_prompt_brands() {
		blu_register_ability( 'blu/suggest-product-brand', [
			'label'               => 'Suggest Product Brands',
			'category'            => 'blu-mcp',
			'description'         => 'Generate a list of product brands based on product details',
			'input_schema'        => array(
				'type'       => 'object',
				'properties' => array(
					'name'        => array(
						'type'        => 'string',
						'description' => 'Product name',
						'default'     => '',
					),
					'description' => array(
						'type'        => 'string',
						'description' => 'Product description',
					)
				),
				'required'   => array( 'name' )
			),
			'execute_callback'    => function ( $input ) {
				$name = $input['name'] ?? '';
				$desc = $input['description'] ?? '';
				$desc = !empty( $desc ) ? 'and these details :'.$desc : '';
				return [
					'messages' => [
						[
							'role'    => 'user',
							'content' => [
								'type'        => 'text',
								'text'        => "Generate SEO‑optimized brand references for the product $name $desc.\n 
												- Use only well‑known, relevant brands associated with this product category. 
												- Focus on brands that customers commonly search for in relation to this product. 
												- Limit the number of brands to between 3 and 5 items only. 
												- Do not invent or include non‑existent brands.
												- Require to customer to select one or more brand from it
												- Return the customer’s selection strictly as an array named `brands`. 
											Output format example:
												{
												  'brands': [
												    'brand1',
												    'brand2',
												    'brand3',
												  ]
												}
												",
								'annotations' => [
									'audience' => [ 'assistant' ],
									'priority' => 0.9
								]
							]
						]
					]
				];
			},
			'permission_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
			'meta'                => [
				'arguments'   => [
					[
						'name'        => 'name',
						'description' => 'Product name to check',
						'required'    => true
					],
					[
						'name'        => 'description',
						'description' => 'Product description',
						'required'    => false
					]
				],
				'annotations' => [
					'readOnlyHint'   => true,
					'idempotentHint' => true
				],
				'mcp'         => [
					'public' => true,   // Expose this ability via MCP
					'type'   => 'prompt' // Mark as prompt for auto-discovery
				]
			]
		] );
	}


}