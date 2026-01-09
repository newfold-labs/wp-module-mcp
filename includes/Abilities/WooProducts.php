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
			[
				'label'               => 'Search WooCommerce Products',
				'description'         => 'Search and filter WooCommerce products with pagination',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'search'   => [
							'type'        => 'string',
							'description' => 'Search term',
						],
						'page'     => [
							'type'        => 'integer',
							'description' => 'Page number',
						],
						'per_page' => [
							'type'        => 'integer',
							'description' => 'Products per page',
						],
					],
				],
				'execute_callback'    => function ( $input = null ) {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/products' );
					if ( $input ) {
						$request->set_query_params( $input );
					}
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);

		// Get product
		blu_register_ability(
			'blu/wc-get-product',
			[
				'label'               => 'Get WooCommerce Product',
				'description'         => 'Get a WooCommerce product by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id' => [
							'type'        => 'integer',
							'description' => 'Product ID',
						],
					],
					'required'   => [ 'id' ],
				],
				'execute_callback'    => function ( $input ) {
					$request  = new \WP_REST_Request( 'GET', '/wc/v3/products/' . $input['id'] );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);

		// Add product
		blu_register_ability(
			'blu/wc-add-product',
			[
				'label'               => 'Add WooCommerce Product',
				'description'         => 'Add new WooCommerce product.',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'name'              => [
							'type'        => 'string',
							'description' => 'Product name',
						],
						'type'              => [
							'type'        => 'string',
							'description' => 'Product type',
						],
						'description'       => [
							'type'        => 'string',
							'description' => 'Product description',
						],
						'short_description' => [
							'type'        => 'string',
							'description' => 'Product short description',
						],
						'regular_price'     => [
							'type'        => 'string',
							'description' => 'Product price',
						],
						'sale_price'        => [
							'type'        => 'string',
							'description' => 'Product sale price',
						],
						'category'          => [
							'type'        => 'array',
							'description' => 'List of product category ids to set',
						],
						'tag'               => [
							'type'        => 'array',
							'description' => 'List of product tag ids to set',

						],
						'brand'             => [
							'type'        => 'array',
							'description' => 'List of product brand ids to set',

						],
						'ready'             => [
							'type'        => 'boolean',
							'description' => 'Check if the product is ready after customer interactions',
							'default'     => false,
						],
					],
					'required'   => [ 'name' ],
				],
				'execute_callback'    => function ( $input ) {
					$ready = $input['ready'];
					if ( $ready ) {
						unset( $input['ready'] );
						if ( isset( $input['category'] ) && count( $input['category'] ) > 0 ) {
							$categories = $input['category'];
							unset( $input['category'] );
							$input['categories'] = [];

							foreach ( $categories as $category ) {
								$input['categories'][] = [ 'id' => $category ];
							}

						}

						if ( isset( $input['tag'] ) && count( $input['tag'] ) > 0 ) {
							$tags = $input['tag'];
							unset( $input['tag'] );
							$input['tags'] = [];

							foreach ( $tags as $tag ) {
								$input['tags'][] = [ 'id' => $tag ];
							}

						}

						if ( isset( $input['brand'] ) && count( $input['brand'] ) > 0 ) {
							$brands = $input['brand'];
							unset( $input['brand'] );
							$input['brands'] = [];

							foreach ( $brands as $brand ) {
								$input['brands'][] = [ 'id' => $brand ];
							}

						}
						$request = new \WP_REST_Request( 'POST', '/wc/v3/products' );
						$request->set_body_params( $input );
						$response = rest_do_request( $request );

						return blu_standardize_rest_response( $response );
					} else {
						$name        = $input['name'] ?? '';
						$instruction = include_once __DIR__ . '/../instructions/product-full-flow.php';

						return [
							'messages' => [
								[
									'role'    => 'user',
									'content' => [
										'type'        => 'text',
										'text'        => $instruction,
										'annotations' => [
											'audience' => [ 'assistant' ],
											'priority' => 0.9,
										],
									],
								],
							],
						];
					}
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					],
				],
			]
		);

		// Update product
		blu_register_ability(
			'blu/wc-update-product',
			[
				'label'               => 'Update WooCommerce Product',
				'description'         => 'Update a WooCommerce product by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id'            => [
							'type'        => 'integer',
							'description' => 'Product ID',
						],
						'name'          => [
							'type'        => 'string',
							'description' => 'Product name',
						],
						'description'   => [
							'type'        => 'string',
							'description' => 'Product description',
						],
						'regular_price' => [
							'type'        => 'string',
							'description' => 'Product price',
						],
						'sale_price'    => [
							'type'        => 'string',
							'description' => 'Product sale price',
						],
						'category'      => [
							'type'        => 'array',
							'description' => 'List of product category ids to set',
						],
						'tag'           => [
							'type'        => 'array',
							'description' => 'List of product tag ids to set',

						],
						'brand'         => [
							'type'        => 'array',
							'description' => 'List of product brand ids to set',

						],
					],
					'required'   => [ 'id' ],
				],
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );

					if ( isset( $input['category'] ) && count( $input['category'] ) > 0 ) {
						$stored_category = $this->get_product_taxonomy_ids( $id );
						$categories      = $input['category'];
						unset( $input['category'] );
						$input['categories'] = [];

						foreach ( $categories as $category ) {
							$input['categories'][] = [ 'id' => $category ];
						}

						$input['categories'] = array_merge( $input['categories'], $stored_category );
					}

					if ( isset( $input['tag'] ) && count( $input['tag'] ) > 0 ) {
						$tags       = $input['tag'];
						$stored_tag = $this->get_product_taxonomy_ids( $id, 'tags' );
						unset( $input['tag'] );
						$input['tags'] = [];

						foreach ( $tags as $tag ) {
							$input['tags'][] = [ 'id' => $tag ];
						}
						$input['tags'] = array_merge( $input['tags'], $stored_tag );
					}

					if ( isset( $input['brand'] ) && count( $input['brand'] ) > 0 ) {
						$brands       = $input['brand'];
						$stored_brand = $this->get_product_taxonomy_ids( $id, 'brands' );
						unset( $input['brand'] );
						$input['brands'] = [];

						foreach ( $brands as $brand ) {
							$input['brands'][] = [ 'id' => $brand ];
						}
						$input['brands'] = array_merge( $input['brands'], $stored_brand );
					}


					$request = new \WP_REST_Request( 'PUT', '/wc/v3/products/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);

		// Delete product
		blu_register_ability(
			'blu/wc-delete-product',
			[
				'label'               => 'Delete WooCommerce Product',
				'description'         => 'Delete a WooCommerce product by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id' => [
							'type'        => 'integer',
							'description' => 'Product ID',
						],
					],
					'required'   => [ 'id' ],
				],
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'DELETE', '/wc/v3/products/' . $input['id'] );
					$request->set_param( 'force', true );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'delete_products' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					],
				],
			]
		);
	}

	/**
	 * Register product category abilities.
	 */
	private function register_category_abilities(): void {
		// List categories
		blu_register_ability(
			'blu/wc-list-product-categories',
			[
				'label'               => 'List WooCommerce Product Categories',
				'description'         => 'List all WooCommerce product categories',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'patterns' => [
							'type'        => 'array',
							'description' => 'List of relevant categories based on product name',
							'maxItems'    => 5,
						],
					],
				],
				'execute_callback'    => function ( $input ) {
					$page       = 1;
					$categories = [];
					$request    = new \WP_REST_Request( 'GET', '/wc/v3/products/categories' );
					do {
						$request->set_query_params( [ 'page' => $page ] );
						$response = rest_do_request( $request );
						if ( is_wp_error( $response ) ) {
							return blu_standardize_rest_response( $response );
						}
						$data  = $response->get_data();
						$total = count( $data );
						foreach ( $data as $category ) {
							$categories[] = [ 'id' => $category['id'], 'name' => $category['name'], 'parent' => $category['parent'] ];
						}
						$page ++;
					} while ( $total > 0 );

					if ( isset( $input['patterns'] ) && is_array( $input['patterns'] ) ) {
						$patterns     = $input['patterns'];
						$filtered_ids = [];
						foreach ( $categories as $category ) {
							$cat_name = trim( $category['name'] );

							foreach ( $patterns as $pattern ) {

								if ( @preg_match( $pattern, '' ) !== false ) {
									$regex = $pattern;
									if ( substr( $regex, - 1 ) !== 'i' ) {
										// Ensure case-insensitive
										$regex = rtrim( $regex, '/' ) . '/i';
									}
									if ( preg_match( $regex, $cat_name ) ) {
										$filtered_ids[] = $category['id'];
										break;
									}
								} else {
									// Case-insensitive substring match
									if ( false !== stripos( $cat_name, $pattern ) ) {
										$filtered_ids[] = $category['id'];
										break;
									}
								}
							}
						}

						if ( count( $filtered_ids ) > 0 ) {
							$categories = array_filter( $categories, function ( $category ) use ( $filtered_ids ) {
								return in_array( $category['id'], $filtered_ids );
							} );
						}
					}

					return blu_prepare_ability_response( '200', $categories );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);

		// Add category
		blu_register_ability(
			'blu/wc-add-product-category',
			[
				'label'               => 'Add WooCommerce Product Category',
				'description'         => 'Add one or more new WooCommerce product categories',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'categories'    => [
							'type'        => 'array',
							'description' => 'Product Categories List',
						],
						'hierarchical'  => [
							'type'        => 'boolean',
							'description' => 'Add the category hierarchically or not.',
							'default'     => false,
						],
						'is_google_tax' => [
							'type'        => 'boolean',
							'description' => 'Define is a google taxonomy or not',
							'default'     => false,
						],
					],
					'required'   => [ 'categories' ],
				],
				'execute_callback'    => function ( $input ) {

					$all_categories = $input['categories'] ?? [];

					$results = [];

					if ( $input['is_google_tax'] ) {

						foreach ( $all_categories as $category_path ) {
							$categories = explode( '>', $category_path );

							$resp = $this->add_product_taxonomies( $categories, 'categories', $input['hierarchical'] );
							if ( 201 !== $resp['statusCode'] ) {
								return $resp;
							}

							$results[] = $resp;
						}

						return [
							'statusCode' => 201,
							'status'     => 'success',
							'message'    => $results,
						];
					} else {
						return $this->add_product_taxonomies( $all_categories, 'categories', $input['hierarchical'] );
					}

				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					],
				],
			]
		);

		// Update category
		blu_register_ability(
			'blu/wc-update-product-category',
			[
				'label'               => 'Update WooCommerce Product Category',
				'description'         => 'Update a WooCommerce product category',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id'   => [
							'type'        => 'integer',
							'description' => 'Category ID',
						],
						'name' => [
							'type'        => 'string',
							'description' => 'Category name',
						],
					],
					'required'   => [ 'id' ],
				],
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'PUT', '/wc/v3/products/categories/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);

		// Delete category
		blu_register_ability(
			'blu/wc-delete-product-category',
			[
				'label'               => 'Delete WooCommerce Product Category',
				'description'         => 'Delete a WooCommerce product category',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id' => [
							'type'        => 'integer',
							'description' => 'Category ID',
						],
					],
					'required'   => [ 'id' ],
				],
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'DELETE', '/wc/v3/products/categories/' . $input['id'] );
					$request->set_param( 'force', true );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'delete_product_terms' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					],
				],
			]
		);
	}

	/**
	 * Register product tag abilities.
	 */
	private function register_tag_abilities(): void {
		// List tags
		blu_register_ability(
			'blu/wc-list-product-tags',
			[
				'label'               => 'List WooCommerce Product Tags',
				'description'         => 'List all WooCommerce product tags',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type' => 'object',
				],
				'execute_callback'    => function () {
					$request  = new \WP_REST_Request( 'GET', '/wc/v3/products/tags' );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);

		// Add tag
		blu_register_ability(
			'blu/wc-add-product-tag',
			[
				'label'               => 'Add WooCommerce Product Tag',
				'description'         => 'Add one or more new WooCommerce product tag',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'tags' => [
							'type'        => 'array',
							'description' => 'The Tag name list',
						],
					],
					'required'   => [ 'tags' ],
				],
				'execute_callback'    => function ( $input ) {

					$all_tags = $input['tags'] ?? [];

					$results = [];

					if ( $input['is_google_tax'] ) {

						foreach ( $all_tags as $tag_path ) {
							$tags = explode( '>', $tag_path );

							$resp = $this->add_product_taxonomies( $tags, 'tags', $input['hierarchical'] );
							if ( 201 !== $resp['statusCode'] ) {
								return $resp;
							}

							$results[] = $resp;
						}

						return [
							'statusCode' => 201,
							'status'     => 'success',
							'message'    => $results,
						];
					} else {
						return $this->add_product_taxonomies( $all_tags, 'tags', $input['hierarchical'] );
					}

				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					],
				],
			]
		);

		// Update tag
		blu_register_ability(
			'blu/wc-update-product-tag',
			[
				'label'               => 'Update WooCommerce Product Tag',
				'description'         => 'Update a WooCommerce product tag',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id'   => [
							'type'        => 'integer',
							'description' => 'Tag ID',
						],
						'name' => [
							'type'        => 'string',
							'description' => 'Tag name',
						],
					],
					'required'   => [ 'id' ],
				],
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'PUT', '/wc/v3/products/tags/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);

		// Delete tag
		blu_register_ability(
			'blu/wc-delete-product-tag',
			[
				'label'               => 'Delete WooCommerce Product Tag',
				'description'         => 'Delete a WooCommerce product tag',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id' => [
							'type'        => 'integer',
							'description' => 'Tag ID',
						],
					],
					'required'   => [ 'id' ],
				],
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'DELETE', '/wc/v3/products/tags/' . $input['id'] );
					$request->set_param( 'force', true );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'delete_product_terms' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					],
				],
			]
		);
	}

	/**
	 * Register product brand abilities.
	 */
	private function register_brand_abilities(): void {
		// List brands
		blu_register_ability(
			'blu/wc-list-product-brands',
			[
				'label'               => 'List WooCommerce Product Brands',
				'description'         => 'List all WooCommerce product brands',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type' => 'object',
				],
				'execute_callback'    => function () {
					$request  = new \WP_REST_Request( 'GET', '/wc/v3/products/brands' );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);

		// Add brand
		blu_register_ability(
			'blu/wc-add-product-brand',
			[
				'label'               => 'Add WooCommerce Product Brand',
				'description'         => 'Add one or more new WooCommerce product brand',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'brands' => [
							'type'        => 'array',
							'description' => 'The list of Brand name',
						],
					],
					'required'   => [ 'brands' ],
				],
				'execute_callback'    => function ( $input ) {
					return $this->add_product_taxonomies( $input['brands'], 'brands' );
				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					],
				],
			]
		);

		// Update brand
		blu_register_ability(
			'blu/wc-update-product-brand',
			[
				'label'               => 'Update WooCommerce Product Brand',
				'description'         => 'Update a WooCommerce product brand',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id'   => [
							'type'        => 'integer',
							'description' => 'Brand ID',
						],
						'name' => [
							'type'        => 'string',
							'description' => 'Brand name',
						],
					],
					'required'   => [ 'id' ],
				],
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'PUT', '/wc/v3/products/brands/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);

		// Delete brand
		blu_register_ability(
			'blu/wc-delete-product-brand',
			[
				'label'               => 'Delete WooCommerce Product Brand',
				'description'         => 'Delete a WooCommerce product brand',
				'category'            => 'blu-mcp',
				'input_schema'        => [
					'type'       => 'object',
					'properties' => [
						'id' => [
							'type'        => 'integer',
							'description' => 'Brand ID',
						],
					],
					'required'   => [ 'id' ],
				],
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'DELETE', '/wc/v3/products/brands/' . $input['id'] );
					$request->set_param( 'force', true );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'delete_product_terms' ),
				'meta'                => [
					'annotations' => [
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					],
				],
			]
		);
	}



	// Utilities.

	/**
	 * Add the product taxonomy with REST API
	 *
	 * @param array   $taxonomies   The taxonomy to add.
	 * @param string  $type         The REST API type : categories|tags|brands.
	 * @param boolean $hierarchical If add the item with hierarchical structure.
	 *
	 * @return array
	 */
	private function add_product_taxonomies( $taxonomies, $type = 'categories', $hierarchical = false ) {
		$hierarchical = 'categories' === $type ? $hierarchical : false;
		$parent       = 0;
		$request      = new \WP_REST_Request( 'POST', '/wc/v3/products/' . $type );
		$results      = [];
		foreach ( $taxonomies as $taxonomy ) {
			$args = [
				'name' => trim( $taxonomy ),
			];
			if ( $hierarchical ) {
				$args['parent'] = $parent;
			}
			$request->set_body_params( $args );
			$response = rest_do_request( $request );
			$response = blu_standardize_rest_response( $response );
			if ( 400 == $response ['statusCode'] && 'term_exists' === $response['message']['code'] ) {
				$parent = $response['message']['data']['resource_id'];
			} elseif ( 201 == $response ['statusCode'] ) {
				$parent    = $response['message']['id'];
				$results[] = $response['message'];
			} else {
				return $response;
			}

		}

		return [
			'statusCode' => 201,
			'status'     => 'success',
			'message'    => $results,
		];
	}

	/**
	 * Get the taxonomy set to product
	 *
	 * @param int    $product_id The product id.
	 * @param string $taxonomy   The taxonomy to return.
	 *
	 * @return array|array[]
	 */
	private function get_product_taxonomy_ids( $product_id, $taxonomy = 'categories' ) {
		$request  = new \WP_REST_Request( 'GET', '/wc/v3/products/' . $product_id );
		$response = rest_do_request( $request );
		$ids      = [];
		if ( is_wp_error( $response ) ) {
			return $ids;
		} else {
			$data             = $response->get_data();
			$uncategorized_id = get_option( 'default_product_cat' );
			if ( isset( $data[ $taxonomy ] ) && count( $data[ $taxonomy ] ) > 0 ) {

				foreach ( $data[ $taxonomy ] as $tax ) {
					if ( isset( $tax['id'] ) && $uncategorized_id != $tax['id'] ) {
						$ids[] = [ 'id' => $tax['id'] ];
					}
				}
			}

		}

		return $ids;
	}
}
