<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * WooProducts abilities for WooCommerce products.
 */
class WooProducts {

	/**
	 * Constructor - registers WooCommerce product abilities if WooCommerce is active.
	 */
	public function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->register_product_abilities();
		$this->register_category_abilities();
		$this->register_tag_abilities();
		$this->register_brand_abilities();
	}

	/**
	 * Register product abilities.
	 */
	private function register_product_abilities(): void {
		// Search products
		blu_register_ability(
			'blu/wc-products-search',
			array(
				'label'               => 'Search WooCommerce Products',
				'description'         => 'Search and filter WooCommerce products with pagination',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array(
							'type'        => 'string',
							'description' => 'Search term',
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page number',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Products per page',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/products' );
					if ( $input ) {
						$request->set_query_params( $input );
					}
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Get product
		blu_register_ability(
			'blu/wc-get-product',
			array(
				'label'               => 'Get WooCommerce Product',
				'description'         => 'Get a WooCommerce product by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Product ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$request  = new \WP_REST_Request( 'GET', '/wc/v3/products/' . $input['id'] );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Add product
		blu_register_ability(
			'blu/wc-add-product',
			array(
				'label'               => 'Add WooCommerce Product',
				'description'         => 'Create a WooCommerce product, or start the guided add-product flow. If ready is false or omitted, no product is created—the response returns assistant-only steps (A/B options, suggestions).',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'        => 'object',
					'properties'  => array(
						'name'                 => array(
							'type'        => 'string',
							'description' => 'Product name',
						),
						'type'                 => array(
							'type'        => 'string',
							'description' => 'Product type',
						),
						'description'          => array(
							'type'        => 'string',
							'description' => 'Product description',
						),
						'short_description'    => array(
							'type'        => 'string',
							'description' => 'Product short description',
						),
						'regular_price'        => array(
							'type'        => 'string',
							'description' => 'Product price',
						),
						'sale_price'           => array(
							'type'        => 'string',
							'description' => 'Product sale price',
						),
						'categories'           => array(
							'type'        => 'array',
							'description' => 'List of categories',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id' => array(
										'description' => 'Category ID.',
										'type'        => 'integer',
									),
								),
							),
						),
						'tags'                 => array(
							'type'        => 'array',
							'description' => 'List of tags',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id' => array(
										'description' => 'Tag ID.',
										'type'        => 'integer',
									),
								),
							),
						),
						'brands'               => array(
							'type'        => 'array',
							'description' => 'List of brands',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id' => array(
										'description' => 'Brand ID.',
										'type'        => 'integer',
									),
								),
							),
						),
						'variation_attributes' => array(
							'type'        => 'array',
							'description' => 'List of variation attributes',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'name'  => array(
										'description' => 'Attribute name',
										'type'        => 'string',
									),
									'terms' => array(
										'type'        => 'array',
										'items'       => array( 'type' => 'string' ),
										'description' => 'Attribute name',
									),
								),
							),
						),
						'status'               => array(
							'description' => 'Product status (post status).',
							'type'        => 'string',
							'default'     => 'draft',
							'enum'        => array_merge(
								array_keys( get_post_statuses() ),
								array(
									'future',
									'auto-draft',
									'trash',
								)
							),
						),
					),
					'required'    => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {

					$request = new \WP_REST_Request( 'POST', '/wc/v3/products' );

					$variation_attributes = $input['variation_attributes'] ?? array();
					if ( $variation_attributes ) {
						$input['type'] = 'variable';
					}

					if ( isset( $input['variation_attributes'] ) ) {
						unset( $input['variation_attributes'] );
					}

					$request->set_body_params( $input );
					$response = rest_do_request( $request );

					if ( ! $response->is_error() && (bool) $variation_attributes ) {
						$data       = $response->get_data();
						$product_id = absint( $data['id'] ?? 0 );

						if ( $product_id ) {
							$product            = wc_get_product( $product_id );
							$position           = 0;
							$product_attributes = array();

							foreach ( $variation_attributes as $attribute ) {
								$attribute_id   = 0;
								$attribute_name = wc_clean( esc_html( $attribute['name'] ) );

								$terms = wc_get_text_attributes( implode( WC_DELIMITER, $attribute['terms'] ) );

								$product_attribute = new \WC_Product_Attribute();
								$product_attribute->set_id( $attribute_id );
								$product_attribute->set_name( $attribute_name );
								$product_attribute->set_options( $terms );
								$product_attribute->set_position( $position );
								$product_attribute->set_visible( true );
								$product_attribute->set_variation( true );

								$product_attributes[] = $product_attribute;

								$position++;
							}

							$product->set_attributes( $product_attributes );
							$product->save();

							/**
							 * The variable product
							 *
							 * @var $variation \WC_Product_Variation
							 */
							$variation = wc_get_product_object( 'variation' );
							if ( isset( $input['regular_price'] ) ) {
								$variation->set_regular_price( $input['regular_price'] );
							}
							$variation->set_parent_id( $product->get_id() );
							$variation->save();

							$request  = new \WP_REST_Request( 'GET', '/wc/v3/products/' . $product_id );
							$response = rest_do_request( $request );
						}
					}

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			),
		);

		// Update product
		blu_register_ability(
			'blu/wc-update-product',
			array(
				'label'               => 'Update WooCommerce Product',
				'description'         => 'Update a WooCommerce product by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'                => array(
							'type'        => 'integer',
							'description' => 'Product ID',
						),
						'name'              => array(
							'type'        => 'string',
							'description' => 'Product name',
						),
						'description'       => array(
							'type'        => 'string',
							'description' => 'Product description',
						),
						'short_description' => array(
							'type'        => 'string',
							'description' => 'Product short description',
						),
						'regular_price'     => array(
							'type'        => 'string',
							'description' => 'Product price',
						),
						'sale_price'        => array(
							'type'        => 'string',
							'description' => 'Product sale price',
						),
						'categories'        => array(
							'type'        => 'array',
							'description' => 'List of categories',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id' => array(
										'description' => 'Category ID.',
										'type'        => 'integer',
									),
								),
							),
						),
						'tags'              => array(
							'type'        => 'array',
							'description' => 'List of tags',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id' => array(
										'description' => 'Tag ID.',
										'type'        => 'integer',
									),
								),
							),
						),
						'brands'            => array(
							'type'        => 'array',
							'description' => 'List of brands',
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'id' => array(
										'description' => 'Brand ID.',
										'type'        => 'integer',
									),
								),
							),
						),
						'status'            => array(
							'description' => 'Product status (post status).',
							'type'        => 'string',
							'default'     => 'draft',
							'enum'        => array_merge(
								array_keys( get_post_statuses() ),
								array(
									'future',
									'auto-draft',
									'trash',
								)
							),
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );

					if ( isset( $input['categories'] ) && count( $input['categories'] ) > 0 ) {
						$stored_category     = $this->get_product_taxonomy_ids( $id );
						$input['categories'] = array_merge( $input['categories'], $stored_category );
					}

					if ( isset( $input['tags'] ) && count( $input['tags'] ) > 0 ) {
						$stored_tag    = $this->get_product_taxonomy_ids( $id, 'tags' );
						$input['tags'] = array_merge( $input['tags'], $stored_tag );
					}

					if ( isset( $input['brands'] ) && count( $input['brands'] ) > 0 ) {
						$stored_brand    = $this->get_product_taxonomy_ids( $id, 'brands' );
						$input['brands'] = array_merge( $input['brands'], $stored_brand );
					}

					$request = new \WP_REST_Request( 'PUT', '/wc/v3/products/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Delete product
		blu_register_ability(
			'blu/wc-delete-product',
			array(
				'label'               => 'Delete WooCommerce Product',
				'description'         => 'Delete a WooCommerce product by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Product ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'DELETE', '/wc/v3/products/' . $input['id'] );
					$request->set_param( 'force', true );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'delete_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);
	}

	/**
	 * Register product category abilities.
	 */
	private function register_category_abilities(): void {
		// List categories

		blu_register_ability(
			'blu/wc-list-product-categories',
			array(
				'label'               => 'List WooCommerce Product Categories',
				'description'         => 'List all WooCommerce product categories',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'patterns' => array(
							'type'        => 'array',
							'description' => 'List of relevant categories and regex based on product name',
							'items'       => array( 'type' => 'string' ),
							'maxItems'    => 5,
						),
					),
				),
				'execute_callback'    => function ( $input ) {
					$page       = 1;
					$categories = array();
					$request    = new \WP_REST_Request( 'GET', '/wc/v3/products/categories' );
					do {
						$request->set_query_params( array( 'page' => $page ) );
						$response = rest_do_request( $request );
						if ( is_wp_error( $response ) ) {
							return blu_standardize_rest_response( $response );
						}
						$data  = $response->get_data();
						$total = count( $data );
						foreach ( $data as $category ) {
							$categories[] = array(
								'id'     => $category['id'],
								'name'   => $category['name'],
								'parent' => $category['parent'],
							);
						}
						$page++;
					} while ( $total > 0 );

					$patterns = $input['patterns'] ?? array();

					$is_valid = blu_is_valid_input_array( $patterns, 'patterns', 0, 5 );
					if ( is_wp_error( $is_valid ) ) {
						return blu_standardize_rest_response( $is_valid );
					}

					blu_filter_terms_by_patterns( $patterns, $categories );

					return blu_prepare_ability_response( 200, $categories );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Add category
		blu_register_ability(
			'blu/wc-add-product-category',
			array(
				'label'               => 'Add WooCommerce Product Category',
				'description'         => 'Add one or more new WooCommerce product categories',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'categories'    => array(
							'type'        => 'array',
							'description' => 'List of product categories name',
							'items'       => array( 'type' => 'string' ),
							'minItems'    => 1,
						),
						'hierarchical'  => array(
							'type'        => 'boolean',
							'description' => 'Add the category hierarchically or not.',
							'default'     => false,
						),
						'is_google_tax' => array(
							'type'        => 'boolean',
							'description' => 'Define is a google taxonomy or not',
							'default'     => false,
						),
					),
					'required'   => array( 'categories' ),
				),
				'execute_callback'    => function ( $input ) {

					$all_categories = $input['categories'] ?? array();
					$is_google_tax  = $input['is_google_tax'] ?? false;
					$hierarchical   = $input['hierarchical'] ?? false;

					$is_valid = blu_is_valid_input_array( $all_categories, 'categories', 1 );

					if ( is_wp_error( $is_valid ) ) {
						return blu_standardize_rest_response( $is_valid );
					}
					if ( $is_google_tax ) {
						$created  = array();
						$existing = array();
						foreach ( $all_categories as $category_path ) {
							$categories = explode( '>', $category_path );

							$resp = $this->add_product_taxonomies( $categories, 'categories', true );
							if ( ! in_array( $resp['statusCode'], array( 200, 201 ) ) ) {
								return $resp;
							}

							$created  = array_merge( $created, $resp['message']['created'] );
							$existing = array_merge( $existing, $resp['message']['existing'] );
						}

						return array(
							'statusCode' => count( $created ) > 0 ? 201 : 200,
							'status'     => 'success',
							'message'    => array(
								'created'  => $created,
								'existing' => $existing,
								'total'    => count( $created ) + count( $existing ),
							),
						);
					} else {
						return $this->add_product_taxonomies( $all_categories, 'categories', $hierarchical );
					}
				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		// Update category
		blu_register_ability(
			'blu/wc-update-product-category',
			array(
				'label'               => 'Update WooCommerce Product Category',
				'description'         => 'Update a WooCommerce product category',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array(
							'type'        => 'integer',
							'description' => 'Category ID',
						),
						'name' => array(
							'type'        => 'string',
							'description' => 'Category name',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'PUT', '/wc/v3/products/categories/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Delete category
		blu_register_ability(
			'blu/wc-delete-product-category',
			array(
				'label'               => 'Delete WooCommerce Product Category',
				'description'         => 'Delete a WooCommerce product category',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Category ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'DELETE', '/wc/v3/products/categories/' . $input['id'] );
					$request->set_param( 'force', true );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'delete_product_terms' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);
	}

	/**
	 * Register product tag abilities.
	 */
	private function register_tag_abilities(): void {
		// List tags
		blu_register_ability(
			'blu/wc-list-product-tags',
			array(
				'label'               => 'List WooCommerce Product Tags',
				'description'         => 'List all WooCommerce product tags',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'patterns' => array(
							'type'        => 'array',
							'description' => 'List of relevant tags based on product name, product description and product category',
							'items'       => array( 'type' => 'string' ),
							'maxItems'    => 5,
						),
					),
				),
				'execute_callback'    => function ( $input ) {
					$page    = 1;
					$tags    = array();
					$request = new \WP_REST_Request( 'GET', '/wc/v3/products/tags' );
					do {
						$request->set_query_params( array( 'page' => $page ) );
						$response = rest_do_request( $request );
						if ( is_wp_error( $response ) ) {
							return blu_standardize_rest_response( $response );
						}
						$data  = $response->get_data();
						$total = count( $data );
						foreach ( $data as $tag ) {
							$tags[] = array(
								'id'   => $tag['id'],
								'name' => $tag['name'],
							);
						}
						$page++;
					} while ( $total > 0 );

					$patterns = $input['patterns'] ?? array();

					$is_valid = blu_is_valid_input_array( $patterns, 'patterns', 0, 5 );
					if ( is_wp_error( $is_valid ) ) {
						return blu_standardize_rest_response( $is_valid );
					}

					blu_filter_terms_by_patterns( $patterns, $tags );

					return blu_prepare_ability_response( 200, $tags );
				},

				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Add tag
		blu_register_ability(
			'blu/wc-add-product-tag',
			array(
				'label'               => 'Add WooCommerce Product Tag',
				'description'         => 'Add one or more new WooCommerce product tag',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'tags' => array(
							'type'        => 'array',
							'description' => 'The list of product tag name',
							'items'       => array( 'type' => 'string' ),
							'minItems'    => 1,
						),
					),
					'required'   => array( 'tags' ),
				),
				'execute_callback'    => function ( $input ) {
					$all_tag  = $input['tags'] ?? array();
					$is_valid = blu_is_valid_input_array( $all_tag, 'tags', 1 );

					if ( is_wp_error( $is_valid ) ) {
						return blu_standardize_rest_response( $is_valid );
					}

					return $this->add_product_taxonomies( $all_tag, 'tags' );
				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		// Update tag
		blu_register_ability(
			'blu/wc-update-product-tag',
			array(
				'label'               => 'Update WooCommerce Product Tag',
				'description'         => 'Update a WooCommerce product tag',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array(
							'type'        => 'integer',
							'description' => 'Tag ID',
						),
						'name' => array(
							'type'        => 'string',
							'description' => 'Tag name',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'PUT', '/wc/v3/products/tags/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Delete tag
		blu_register_ability(
			'blu/wc-delete-product-tag',
			array(
				'label'               => 'Delete WooCommerce Product Tag',
				'description'         => 'Delete a WooCommerce product tag',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Tag ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'DELETE', '/wc/v3/products/tags/' . $input['id'] );
					$request->set_param( 'force', true );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'delete_product_terms' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);
	}

	/**
	 * Register product brand abilities.
	 */
	private function register_brand_abilities(): void {
		// List brands
		blu_register_ability(
			'blu/wc-list-product-brands',
			array(
				'label'               => 'List WooCommerce Product Brands',
				'description'         => 'List all WooCommerce product brands',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'patterns' => array(
							'type'        => 'array',
							'description' => 'List of relevant brands based on product name, product description and product category',
							'items'       => array( 'type' => 'string' ),
							'maxItems'    => 5,
						),
					),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/products/brands' );
					$brands  = array();
					$page    = 1;
					do {
						$request->set_query_params( array( 'page' => $page ) );
						$response = rest_do_request( $request );
						if ( is_wp_error( $response ) ) {
							return blu_standardize_rest_response( $response );
						}
						$data  = $response->get_data();
						$total = count( $data );
						foreach ( $data as $brand ) {
							$brands[] = array(
								'id'   => $brand['id'],
								'name' => $brand['name'],
							);
						}
						$page++;
					} while ( $total > 0 );

					$patterns = $input['patterns'] ?? array();

					$is_valid = blu_is_valid_input_array( $patterns, 'patterns', 0, 5 );
					if ( is_wp_error( $is_valid ) ) {
						return blu_standardize_rest_response( $is_valid );
					}

					blu_filter_terms_by_patterns( $patterns, $brands );

					return blu_prepare_ability_response( 200, $brands );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Add brand
		blu_register_ability(
			'blu/wc-add-product-brand',
			array(
				'label'               => 'Add WooCommerce Product Brand',
				'description'         => 'Add one or more new WooCommerce product brand',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'brands' => array(
							'type'        => 'array',
							'description' => 'The list of Brand name',
							'items'       => array( 'type' => 'string' ),
							'minItems'    => 1,
						),
					),
					'required'   => array( 'brands' ),
				),
				'execute_callback'    => function ( $input ) {
					$all_brand = $input['brands'] ?? array();

					$is_valid = blu_is_valid_input_array( $all_brand, 'brands', 1 );

					if ( is_wp_error( $is_valid ) ) {
						return blu_standardize_rest_response( $is_valid );
					}

					return $this->add_product_taxonomies( $all_brand, 'brands' );
				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		// Update brand
		blu_register_ability(
			'blu/wc-update-product-brand',
			array(
				'label'               => 'Update WooCommerce Product Brand',
				'description'         => 'Update a WooCommerce product brand',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array(
							'type'        => 'integer',
							'description' => 'Brand ID',
						),
						'name' => array(
							'type'        => 'string',
							'description' => 'Brand name',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'PUT', '/wc/v3/products/brands/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Delete brand
		blu_register_ability(
			'blu/wc-delete-product-brand',
			array(
				'label'               => 'Delete WooCommerce Product Brand',
				'description'         => 'Delete a WooCommerce product brand',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Brand ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'DELETE', '/wc/v3/products/brands/' . $input['id'] );
					$request->set_param( 'force', true );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'delete_product_terms' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);
	}



	// Utilities.

	/**
	 * Add the product taxonomy with REST API
	 *
	 * @param array   $taxonomies The taxonomy to add.
	 * @param string  $type The REST API type : categories|tags|brands.
	 * @param boolean $hierarchical If add the item with hierarchical structure.
	 *
	 * @return array
	 */
	private function add_product_taxonomies( $taxonomies, $type = 'categories', $hierarchical = false ) {
		$hierarchical = 'categories' === $type ? $hierarchical : false;
		$parent       = 0;
		$request      = new \WP_REST_Request( 'POST', '/wc/v3/products/' . $type );
		$created      = array();
		$existing     = array();
		foreach ( $taxonomies as $taxonomy ) {
			$args = array(
				'name' => trim( $taxonomy ),
			);
			if ( $hierarchical ) {
				$args['parent'] = $parent;
			}
			$request->set_body_params( $args );
			$response = rest_do_request( $request );
			$response = blu_standardize_rest_response( $response );
			if ( 400 == $response ['statusCode'] && 'term_exists' === $response['message']['code'] ) {
				$parent        = $response['message']['data']['resource_id'];
				$term_response = $this->get_taxonomy( $parent, $type );
				if ( 200 == $term_response['statusCode'] ) {
					$existing[] = $term_response['message'];
				} else {
					return $term_response;
				}
			} elseif ( 201 == $response ['statusCode'] ) {
				$parent    = $response['message']['id'];
				$created[] = $response['message'];
			} else {
				return $response;
			}
		}

		$total = count( $existing ) + count( $created );

		return array(
			'statusCode' => count( $created ) > 0 ? 201 : 200,
			'status'     => 'success',
			'message'    => array(
				'total'    => $total,
				'created'  => $created,
				'existing' => $existing,
			),
		);
	}

	/**
	 * Get the taxonomy set to product
	 *
	 * @param int    $product_id The product id.
	 * @param string $taxonomy The taxonomy to return.
	 *
	 * @return array|array[]
	 */
	private function get_product_taxonomy_ids( $product_id, $taxonomy = 'categories' ) {
		$request  = new \WP_REST_Request( 'GET', '/wc/v3/products/' . $product_id );
		$response = rest_do_request( $request );
		$ids      = array();
		if ( is_wp_error( $response ) ) {
			return $ids;
		} else {
			$data = $response->get_data();
			if ( isset( $data[ $taxonomy ] ) && count( $data[ $taxonomy ] ) > 0 ) {

				foreach ( $data[ $taxonomy ] as $tax ) {
					if ( isset( $tax['id'] ) ) {
						$ids[] = array( 'id' => $tax['id'] );
					}
				}
			}
		}

		return $ids;
	}

	/**
	 * Get the term by id
	 *
	 * @param int    $term_id The term id.
	 * @param string $taxonomy The taxonomy type.
	 *
	 * @return array
	 */
	private function get_taxonomy( $term_id, $taxonomy = 'categories' ) {
		$request = new \WP_REST_Request( 'GET', '/wc/v3/products/' . $taxonomy . '/' . $term_id );

		$response = rest_do_request( $request );

		return blu_standardize_rest_response( $response );
	}
}
