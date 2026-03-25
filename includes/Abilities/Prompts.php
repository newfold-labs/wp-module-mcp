<?php
/**
 * This class manage the Abilities managed like Prompts
 *
 * @package BLU\Abilities
 */

namespace BLU\Abilities;

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

		$this->register_improve_prompt_description();
		$this->register_prompt_description();
		$this->register_prompt_categories();
		$this->register_prompt_tags();
		$this->register_prompt_brands();
		$this->register_smart_product_prompt();
		$this->register_prompt_variation_attributes();
	}

	/**
	 * Create a prompt to instruct the AI the steps to follow to suggest the long and short description
	 *
	 * @return void
	 */
	private function register_prompt_description() {
		blu_register_ability(
			'blu/suggest-product-description',
			array(
				'label'               => 'Suggest Product Description',
				'category'            => 'blu-mcp',
				'description'         => 'Generate a description and a short description on product details',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'       => array(
							'type'        => 'string',
							'description' => 'Product name',
							'default'     => '',
						),
						'categories' => array(
							'type'        => 'string',
							'description' => 'Category',
						),
						'tags'       => array(
							'type'        => 'string',
							'description' => 'Tags',
						),
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$name        = $input['name'] ?? '';
					$instruction = include_once __DIR__ . '/../instructions/product-description-suggester.php';

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
				),
			)
		);
	}


	/**
	 * Create a prompt to instruct the AI the steps to follow to improve the long and short description
	 *
	 * @return void
	 */
	private function register_improve_prompt_description() {
		blu_register_ability(
			'blu/improve-product-description',
			array(
				'label'               => 'Improve Product Description',
				'category'            => 'blu-mcp',
				'description'         => 'Improve the existing description and short description for a product',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array(
							'type'        => 'integer',
							'description' => 'Product ID.',
						),
						'tone' => array(
							'type'        => 'string',
							'description' => 'User tone.',
							'enum'        => array( 'formal', 'technical', 'empathetic', 'persuasive' ),
							'default'     => 'formal',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {

					$product           = wc_get_product( $input['id'] );
					$tone              = $input['tone'] ?? 'formal';
					$name              = $product->get_title();
					$description       = $product->get_description();
					$short_description = $product->get_short_description();
					$instruction       = include_once __DIR__ . '/../instructions/product-description-improvement.php';

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
				'description'         => 'Generate a list of product categories based on product details',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Product name',
							'default'     => '',
						),
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$product_name = $input['name'] ?? '';

					$instruction = include_once __DIR__ . '/../instructions/product-categories-suggester.php';

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
						'categories'  => array(
							'type'        => 'string',
							'description' => 'A comma separated product categories list',
						),
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$product_name       = $input['name'] ?? '';
					$product_desc       = $input['description'] ?? '';
					$product_desc       = ! empty( $product_desc ) ? '.\n Here a short description for this product :' . $product_desc . '.\n' : '';
					$product_categories = ! empty( $input['categories'] ) ? '\n The product has these categories :' . $input['categories'] : '';

					$instruction = include_once __DIR__ . '/../instructions/product-tags-suggester.php';

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
						'name'        => array(
							'type'        => 'string',
							'description' => 'Product name',
							'default'     => '',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Product description',
						),
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$name = $input['name'] ?? '';
					$desc = $input['description'] ?? '';
					$desc = ! empty( $desc ) ? 'and these details :' . $desc : '';

					return array(
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => array(
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
									'annotations' => array(
										'audience' => array( 'assistant' ),
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

					$product     = wc_get_product( $input['id'] );
					$name        = $product->get_title();
					$description = $product->get_description();
					$categories  = wc_get_product_category_list( $product->get_id() );
					$tags        = wc_get_product_tag_list( $product->get_id() );
					$instruction = include_once __DIR__ . '/../instructions/smart-product-details.php';

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
				),
			)
		);
	}

	/**
	 * Create a prompt to instruct the AI the step to follow to suggest the attributes for variations.
	 *
	 * @return void
	 */
	private function register_prompt_variation_attributes() {
		blu_register_ability(
			'blu/suggest-product-variation-attributes',
			array(
				'label'               => 'Suggest product variation attributes',
				'category'            => 'blu-mcp',
				'description'         => 'Generate a list of product terms and attributes based on product details to be used for variations',
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
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$name = $input['name'] ?? '';
					$desc = $input['description'] ?? '';

					return array(
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => array(
									'type' => 'text',
									'text' => sprintf(
										'You are an expert WooCommerce product manager.
									You are given:
									- Product name: "%1$s"
									- Product description (may be empty): "%2$s"
									
									Your task is to determine whether this product should realistically be sold as a VARIABLE product in WooCommerce.
									
									Decision rules:
									- Use BOTH the product name and the description to make the decision.
									- ONLY suggest variations if they would normally generate different SKUs in a real e-commerce store.
									- If the product is typically sold as a single, fixed item (e.g. books, services, gift cards, digital products, simple accessories), return an empty JSON array: [].
									- Be conservative: if variations are unclear, optional, or not explicitly suggested by the description, return [].
									- Do NOT invent attributes that are not supported or implied by the product name or description.
									
									If variations make sense:
									- Suggest up to 3 variation attributes.
									- Each attribute can have up to 8 terms.
									- Attributes must be suitable as WooCommerce variation attributes.
									- Prefer concrete, measurable or selectable attributes (e.g. size, color, capacity, format).
									- Avoid non-variant attributes (e.g. brand, compatibility lists, marketing labels).
									
									Output rules:
									- Return ONLY valid JSON.
									- No explanations, no comments, no extra text.
									
									Output format:
									{
										"variation_attributes": [
										  {
											"name": "Attribute name 1",
											"terms": ["Term 1", "Term 2", "Term 3"]
										  },
										  {
											"name": "Attribute name 2",
											"terms": ["Term 4", "Term 5"]
										  }
										]
									}',
										$name,
										$desc
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
				),
			)
		);
	}
}
