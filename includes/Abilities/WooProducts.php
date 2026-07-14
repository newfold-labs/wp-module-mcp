<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * WooProducts abilities for WooCommerce products.
 */
class WooProducts {

	/**
	 * Base REST API namespace used to discover the latest versioned namespace.
	 *
	 * @var string
	 */
	private string $base_namespace = 'wc';

	/**
	 * Resolved WooCommerce REST API namespace (e.g. "wc/v3").
	 *
	 * @var string
	 */
	private string $wc_namespace = '';

	/**
	 * Resolved base products route (e.g. "/wc/v3/products").
	 *
	 * @var string
	 */
	private string $products_route = '';

	/**
	 * Constructor - registers WooCommerce product abilities if WooCommerce is active.
	 */
	public function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$wc_namespace = RestApiUtils::get_latest_namespace( $this->base_namespace );

		if ( ! $wc_namespace ) {
			return;
		}

		$products_route = RestApiUtils::find_route_by_resource( $wc_namespace, 'products' );

		if ( ! $products_route ) {
			return;
		}

		$this->wc_namespace   = $wc_namespace;
		$this->products_route = $products_route;

		$this->register_product_abilities();
		$this->register_category_abilities();
		$this->register_tag_abilities();
		$this->register_brand_abilities();
		$this->register_attribute_abilities();
		$this->register_variation_abilities();
	}

	/**
	 * Register product abilities.
	 */
	private function register_product_abilities(): void {
		$products_route = $this->products_route;

		// Extract dynamic schema for product list (GET)
		$search_schema = RestApiUtils::extract_input_schema( $products_route, 'GET' );

		if ( ! $search_schema ) {
			$search_schema = array(
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
			);
		}

		// Search products
		blu_register_ability(
			'blu/wc-products-search',
			array(
				'label'               => 'Search WooCommerce Products',
				'description'         => sprintf( 'Search and filter WooCommerce products with pagination using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $search_schema,
				'execute_callback'    => function ( $input = null ) use ( $products_route ) {
					$request = new \WP_REST_Request( 'GET', $products_route );
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

		// Find the single product route
		$product_pattern = '(?P<id>[\d]+)';
		$product_route   = RestApiUtils::find_route_by_resource( $this->wc_namespace, 'products/' . $product_pattern );

		if ( ! $product_route ) {
			return;
		}

		// Extract dynamic schema for single product (GET)
		$get_schema = RestApiUtils::extract_input_schema( $product_route, 'GET' );

		if ( ! $get_schema ) {
			$get_schema = array(
				'type'       => 'object',
				'properties' => array(
					'id' => array(
						'type'        => 'integer',
						'description' => 'Product ID',
					),
				),
				'required'   => array( 'id' ),
			);
		}

		// Get product
		blu_register_ability(
			'blu/wc-get-product',
			array(
				'label'               => 'Get WooCommerce Product',
				'description'         => sprintf( 'Get a WooCommerce product by ID using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $get_schema,
				'execute_callback'    => function ( $input ) use ( $product_route, $product_pattern ) {
					if ( ! $input || ! isset( $input['id'] ) ) {
						return array(
							'status'  => 'error',
							'message' => 'Product ID is required',
						);
					}
					$route    = str_replace( $product_pattern, (string) (int) $input['id'], $product_route );
					$request  = new \WP_REST_Request( 'GET', $route );
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

		// Extract dynamic schema for product creation (POST)
		$add_schema = RestApiUtils::extract_input_schema( $products_route, 'POST' );

		if ( ! $add_schema ) {
			$add_schema = array(
				'type'       => 'object',
				'properties' => array(
					'name'              => array(
						'type'        => 'string',
						'description' => 'Product name',
					),
					'type'              => array(
						'type'        => 'string',
						'description' => 'Product type',
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
				'required'   => array( 'name' ),
			);
		}

		// Add product
		blu_register_ability(
			'blu/wc-add-product',
			array(
				'label'               => 'Add WooCommerce Product',
				'description'         => sprintf( 'Create a WooCommerce product using %s API, or start the guided add-product flow. If ready is false or omitted, no product is created—the response returns assistant-only steps (A/B options, suggestions).', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $add_schema,
				'execute_callback'    => function ( $input ) use ( $products_route, $product_route, $product_pattern ) {

					$request = new \WP_REST_Request( 'POST', $products_route );

					$request->set_body_params( $input );
					$response = rest_do_request( $request );

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

		// Extract dynamic schema for product update (PUT)
		$update_schema = RestApiUtils::extract_input_schema( $product_route, 'PUT' );

		if ( ! $update_schema ) {
			$update_schema = array(
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
			);
		}

		// Update product
		blu_register_ability(
			'blu/wc-update-product',
			array(
				'label'               => 'Update WooCommerce Product',
				'description'         => sprintf( 'Update a WooCommerce product by ID using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $update_schema,
				'execute_callback'    => function ( $input ) use ( $product_route, $product_pattern ) {
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

					$route = str_replace( $product_pattern, (string) $id, $product_route );

					$request = new \WP_REST_Request( 'PUT', $route );
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
				'description'         => sprintf( 'Delete a WooCommerce product by ID using %s API', $this->wc_namespace ),
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
				'execute_callback'    => function ( $input ) use ( $product_route, $product_pattern ) {
					$route   = str_replace( $product_pattern, (string) (int) $input['id'], $product_route );
					$request = new \WP_REST_Request( 'DELETE', $route );
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
		$categories_route = RestApiUtils::find_route_by_resource( $this->wc_namespace, 'products/categories' );

		if ( ! $categories_route ) {
			return;
		}

		$category_pattern = '(?P<id>[\d]+)';
		$category_route   = RestApiUtils::find_route_by_resource( $this->wc_namespace, 'products/categories/' . $category_pattern );

		$default_schema = array(
			'type'       => 'object',
			'properties' => array(
				'patterns' => array(
					'type'        => 'array',
					'description' => 'List of relevant categories and regex based on product name',
					'items'       => array( 'type' => 'string' ),
					'maxItems'    => 5,
				),
			),
		);
		// List categories
		$list_schema = RestApiUtils::extract_input_schema( $categories_route, 'GET' );

		if ( ! $list_schema ) {
			$list_schema = $default_schema;
		} else {
			$list_schema['properties']['patterns'] = $default_schema['properties']['patterns'];
		}

		blu_register_ability(
			'blu/wc-list-product-categories',
			array(
				'label'               => 'List WooCommerce Product Categories',
				'description'         => sprintf( 'List all WooCommerce product categories using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $list_schema,
				'execute_callback'    => function ( $input ) use ( $categories_route ) {
					$page       = 1;
					$categories = array();
					$request    = new \WP_REST_Request( 'GET', $categories_route );
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
				'description'         => sprintf( 'Add one or more new WooCommerce product categories using %s API', $this->wc_namespace ),
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

		if ( ! $category_route ) {
			return;
		}

		// Extract dynamic schema for category update (PUT)
		$update_category_schema = RestApiUtils::extract_input_schema( $category_route, 'PUT' );

		if ( ! $update_category_schema ) {
			$update_category_schema = array(
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
			);
		}

		// Update category
		blu_register_ability(
			'blu/wc-update-product-category',
			array(
				'label'               => 'Update WooCommerce Product Category',
				'description'         => sprintf( 'Update a WooCommerce product category using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $update_category_schema,
				'execute_callback'    => function ( $input ) use ( $category_route, $category_pattern ) {
					$id = $input['id'];
					unset( $input['id'] );
					$route   = str_replace( $category_pattern, (string) $id, $category_route );
					$request = new \WP_REST_Request( 'PUT', $route );
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
				'description'         => sprintf( 'Delete a WooCommerce product category using %s API', $this->wc_namespace ),
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
				'execute_callback'    => function ( $input ) use ( $category_route, $category_pattern ) {
					$route   = str_replace( $category_pattern, (string) (int) $input['id'], $category_route );
					$request = new \WP_REST_Request( 'DELETE', $route );
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
		$tags_route = RestApiUtils::find_route_by_resource( $this->wc_namespace, 'products/tags' );

		if ( ! $tags_route ) {
			return;
		}

		$tag_pattern = '(?P<id>[\d]+)';
		$tag_route   = RestApiUtils::find_route_by_resource( $this->wc_namespace, 'products/tags/' . $tag_pattern );

		$default_schema = array(
			'type'       => 'object',
			'properties' => array(
				'patterns' => array(
					'type'        => 'array',
					'description' => 'List of relevant tags and regex based on product name',
					'items'       => array( 'type' => 'string' ),
					'maxItems'    => 5,
				),
			),
		);
		// List tags
		$list_schema = RestApiUtils::extract_input_schema( $tags_route, 'GET' );

		if ( ! $list_schema ) {
			$list_schema = $default_schema;
		} else {
			$list_schema['properties']['patterns'] = $default_schema['properties']['patterns'];
		}
		// List tags
		blu_register_ability(
			'blu/wc-list-product-tags',
			array(
				'label'               => 'List WooCommerce Product Tags',
				'description'         => sprintf( 'List all WooCommerce product tags using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $list_schema,
				'execute_callback'    => function ( $input ) use ( $tags_route ) {
					$page    = 1;
					$tags    = array();
					$request = new \WP_REST_Request( 'GET', $tags_route );
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
				'description'         => sprintf( 'Add one or more new WooCommerce product tag using %s API', $this->wc_namespace ),
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

		if ( ! $tag_route ) {
			return;
		}

		// Extract dynamic schema for tag update (PUT)
		$update_tag_schema = RestApiUtils::extract_input_schema( $tag_route, 'PUT' );

		if ( ! $update_tag_schema ) {
			$update_tag_schema = array(
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
			);
		}

		// Update tag
		blu_register_ability(
			'blu/wc-update-product-tag',
			array(
				'label'               => 'Update WooCommerce Product Tag',
				'description'         => sprintf( 'Update a WooCommerce product tag using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $update_tag_schema,
				'execute_callback'    => function ( $input ) use ( $tag_route, $tag_pattern ) {
					$id = $input['id'];
					unset( $input['id'] );
					$route   = str_replace( $tag_pattern, (string) $id, $tag_route );
					$request = new \WP_REST_Request( 'PUT', $route );
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
				'description'         => sprintf( 'Delete a WooCommerce product tag using %s API', $this->wc_namespace ),
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
				'execute_callback'    => function ( $input ) use ( $tag_route, $tag_pattern ) {
					$route   = str_replace( $tag_pattern, (string) (int) $input['id'], $tag_route );
					$request = new \WP_REST_Request( 'DELETE', $route );
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
		$brands_route = RestApiUtils::find_route_by_resource( $this->wc_namespace, 'products/brands' );

		if ( ! $brands_route ) {
			return;
		}

		$brand_pattern = '(?P<id>[\d]+)';
		$brand_route   = RestApiUtils::find_route_by_resource( $this->wc_namespace, 'products/brands/' . $brand_pattern );

		$default_schema = array(
			'type'       => 'object',
			'properties' => array(
				'patterns' => array(
					'type'        => 'array',
					'description' => 'List of relevant brands and regex based on product name',
					'items'       => array( 'type' => 'string' ),
					'maxItems'    => 5,
				),
			),
		);
		// List brands
		$list_schema = RestApiUtils::extract_input_schema( $brands_route, 'GET' );

		if ( ! $list_schema ) {
			$list_schema = $default_schema;
		} else {
			$list_schema['properties']['patterns'] = $default_schema['properties']['patterns'];
		}
		// List brands
		blu_register_ability(
			'blu/wc-list-product-brands',
			array(
				'label'               => 'List WooCommerce Product Brands',
				'description'         => sprintf( 'List all WooCommerce product brands using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $list_schema,
				'execute_callback'    => function ( $input ) use ( $brands_route ) {
					$request = new \WP_REST_Request( 'GET', $brands_route );
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
				'description'         => sprintf( 'Add one or more new WooCommerce product brand using %s API', $this->wc_namespace ),
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

		if ( ! $brand_route ) {
			return;
		}

		// Extract dynamic schema for brand update (PUT)
		$update_brand_schema = RestApiUtils::extract_input_schema( $brand_route, 'PUT' );

		if ( ! $update_brand_schema ) {
			$update_brand_schema = array(
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
			);
		}

		// Update brand
		blu_register_ability(
			'blu/wc-update-product-brand',
			array(
				'label'               => 'Update WooCommerce Product Brand',
				'description'         => sprintf( 'Update a WooCommerce product brand using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $update_brand_schema,
				'execute_callback'    => function ( $input ) use ( $brand_route, $brand_pattern ) {
					$id = $input['id'];
					unset( $input['id'] );
					$route   = str_replace( $brand_pattern, (string) $id, $brand_route );
					$request = new \WP_REST_Request( 'PUT', $route );
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
				'description'         => sprintf( 'Delete a WooCommerce product brand using %s API', $this->wc_namespace ),
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
				'execute_callback'    => function ( $input ) use ( $brand_route, $brand_pattern ) {
					$route   = str_replace( $brand_pattern, (string) (int) $input['id'], $brand_route );
					$request = new \WP_REST_Request( 'DELETE', $route );
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
	 * Register product attribute abilities.
	 */
	private function register_attribute_abilities(): void {
		$attributes_route = RestApiUtils::find_route_by_resource( $this->wc_namespace, 'products/attributes' );

		if ( ! $attributes_route ) {
			return;
		}

		// Extract dynamic schema for attribute list (GET)
		$list_schema = RestApiUtils::extract_input_schema( $attributes_route, 'GET' );

		if ( ! $list_schema ) {
			$list_schema = array(
				'type'       => 'object',
				'properties' => array(
					'context' => array(
						'type'        => 'string',
						'description' => 'Scope under which the request is made (view or edit)',
					),
				),
			);
		}

		// List attributes
		blu_register_ability(
			'blu/wc-list-product-attributes',
			array(
				'label'               => 'List WooCommerce Product Attributes',
				'description'         => sprintf( 'List all WooCommerce product attributes using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $list_schema,
				'execute_callback'    => function ( $input = null ) use ( $attributes_route ) {
					$request = new \WP_REST_Request( 'GET', $attributes_route );
					if ( $input ) {
						$request->set_query_params( $input );
					}
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_woocommerce' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Extract dynamic schema for attribute creation (POST)
		$create_schema = RestApiUtils::extract_input_schema( $attributes_route, 'POST' );

		if ( ! $create_schema ) {
			$create_schema = array(
				'type'       => 'object',
				'properties' => array(
					'name'         => array(
						'type'        => 'string',
						'description' => 'Attribute name (e.g. Color, Size)',
					),
					'slug'         => array(
						'type'        => 'string',
						'description' => 'Unique slug (max 28 characters). Auto-generated if omitted.',
					),
					'type'         => array(
						'type'        => 'string',
						'description' => 'Attribute type (select, text, etc.)',
						'default'     => 'select',
					),
					'order_by'     => array(
						'type'        => 'string',
						'description' => 'Default sort order (menu_order, name, name_num, id)',
						'default'     => 'menu_order',
						'enum'        => array( 'menu_order', 'name', 'name_num', 'id' ),
					),
					'has_archives' => array(
						'type'        => 'boolean',
						'description' => 'Enable attribute archives',
						'default'     => false,
					),
					'terms'        => array(
						'type'        => 'array',
						'description' => 'Optional list of term names to create for this attribute (e.g. ["Red","Blue","Green"])',
						'items'       => array( 'type' => 'string' ),
					),
				),
				'required'   => array( 'name' ),
			);
		} else {
			// Inject the extra `terms` field not present in the WC schema
			$create_schema['properties']['terms'] = array(
				'type'        => 'array',
				'description' => 'Optional list of term names to create for this attribute (e.g. ["Red","Blue","Green"])',
				'items'       => array( 'type' => 'string' ),
			);
		}

		// Add attribute (+ optional terms)
		blu_register_ability(
			'blu/wc-add-product-attribute',
			array(
				'label'               => 'Add WooCommerce Product Attribute',
				'description'         => sprintf( 'Create a WooCommerce product attribute and optionally its terms using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $create_schema,
				'execute_callback'    => function ( $input ) use ( $attributes_route ) {
					$terms = $input['terms'] ?? array();
					unset( $input['terms'] );

					$request = new \WP_REST_Request( 'POST', $attributes_route );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					$result   = blu_standardize_rest_response( $response );

					if ( ! in_array( $result['statusCode'], array( 200, 201 ), true ) || empty( $terms ) ) {
						return $result;
					}

					$attribute_id   = $result['message']['id'];
					$attribute_slug = $result['message']['slug'] ?? '';
					$created_terms  = array();

					// A freshly created attribute's pa_* taxonomy is not registered in the
					// current request context, so the REST terms endpoint returns 404.
					// Register a minimal taxonomy entry so wp_insert_term() can resolve it.
					try {
						if ( $attribute_slug && ! taxonomy_exists( $attribute_slug ) ) {
							register_taxonomy( $attribute_slug, array( 'product' ) );
						}

						foreach ( $terms as $term_name ) {
							$inserted = wp_insert_term( trim( $term_name ), $attribute_slug );
							if ( ! is_wp_error( $inserted ) ) {
								$created_terms[] = array(
									'id'   => $inserted['term_id'],
									'name' => trim( $term_name ),
									'slug' => sanitize_title( $term_name ),
								);
							} else {
								$created_terms[] = array(
									'name'  => trim( $term_name ),
									'error' => $inserted->get_error_message(),
								);
							}
						}
					} catch ( \Throwable $e ) {
						$result['message']['terms_error'] = $e->getMessage();
						return $result;
					}

					$result['message']['terms'] = $created_terms;
					return $result;
				},
				'permission_callback' => fn() => current_user_can( 'manage_woocommerce' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		// Find single attribute route
		$attribute_pattern = '(?P<id>[\d]+)';
		$attribute_route   = RestApiUtils::find_route_by_resource( $this->wc_namespace, 'products/attributes/' . $attribute_pattern );

		if ( ! $attribute_route ) {
			return;
		}

		// Delete attribute
		blu_register_ability(
			'blu/wc-delete-product-attribute',
			array(
				'label'               => 'Delete WooCommerce Product Attribute',
				'description'         => sprintf( 'Delete a WooCommerce product attribute and all its terms using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Attribute ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) use ( $attribute_route, $attribute_pattern ) {
					$route   = str_replace( $attribute_pattern, (string) (int) $input['id'], $attribute_route );
					$request = new \WP_REST_Request( 'DELETE', $route );
					$request->set_param( 'force', true );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_woocommerce' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);

		$attribute_pattern = '(?P<attribute_id>[\d]+)';
		// Find attribute terms route
		$terms_route = RestApiUtils::find_route_by_resource( $this->wc_namespace, 'products/attributes/' . $attribute_pattern . '/terms' );

		if ( ! $terms_route ) {
			return;
		}

		$list_schema = RestApiUtils::extract_input_schema( $terms_route, 'GET' );
		// List attribute terms
		blu_register_ability(
			'blu/wc-list-attribute-terms',
			array(
				'label'               => 'List WooCommerce Attribute Terms',
				'description'         => sprintf( 'List all terms for a WooCommerce product attribute using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $list_schema,
				'execute_callback'    => function ( $input ) use ( $terms_route, $attribute_pattern ) {
					$attribute_id = (int) $input['attribute_id'];
					unset( $input['attribute_id'] );

					$route   = str_replace( $attribute_pattern, (string) $attribute_id, $terms_route );
					$request = new \WP_REST_Request( 'GET', $route );
					if ( $input ) {
						$request->set_query_params( $input );
					}
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_woocommerce' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		$create_schema = RestApiUtils::extract_input_schema( $terms_route, 'POST' );
		// Add attribute term
		blu_register_ability(
			'blu/wc-add-attribute-term',
			array(
				'label'               => 'Add WooCommerce Attribute Term',
				'description'         => sprintf( 'Add a term to a WooCommerce product attribute using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $create_schema,
				'execute_callback'    => function ( $input ) use ( $terms_route, $attribute_pattern ) {
					$attribute_id = (int) $input['attribute_id'];
					unset( $input['attribute_id'] );

					$route   = str_replace( $attribute_pattern, (string) $attribute_id, $terms_route );
					$request = new \WP_REST_Request( 'POST', $route );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_woocommerce' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);
	}

	/**
	 * Register product variation abilities.
	 */
	private function register_variation_abilities(): void {
		$product_pattern  = '(?P<product_id>[\d]+)';
		$variations_route = RestApiUtils::find_route_by_resource( $this->wc_namespace, 'products/' . $product_pattern . '/variations' );

		if ( ! $variations_route ) {
			return;
		}

		// Extract dynamic schema for variation list (GET)
		$list_schema = RestApiUtils::extract_input_schema( $variations_route, 'GET' );

		if ( ! $list_schema ) {
			$list_schema = array(
				'type'       => 'object',
				'properties' => array(
					'product_id'   => array(
						'type'        => 'integer',
						'description' => 'Parent product ID',
					),
					'page'         => array(
						'type'        => 'integer',
						'description' => 'Page number',
					),
					'per_page'     => array(
						'type'        => 'integer',
						'description' => 'Variations per page',
					),
					'status'       => array(
						'type'        => 'string',
						'description' => 'Filter by status',
					),
					'stock_status' => array(
						'type'        => 'string',
						'description' => 'Filter by stock status',
					),
				),
				'required'   => array( 'product_id' ),
			);
		} else {
			// product_id comes from input, not the URL pattern, so we add it manually
			if ( ! isset( $list_schema['properties'] ) ) {
				$list_schema['properties'] = array();
			}
			$list_schema['properties']['product_id'] = array(
				'type'        => 'integer',
				'description' => 'Parent product ID',
			);
			$list_schema['required']                 = array_merge( $list_schema['required'] ?? array(), array( 'product_id' ) );
		}

		// List variations
		blu_register_ability(
			'blu/wc-list-product-variations',
			array(
				'label'               => 'List WooCommerce Product Variations',
				'description'         => sprintf( 'List all variations for a WooCommerce variable product using %s API', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $list_schema,
				'execute_callback'    => function ( $input ) use ( $variations_route, $product_pattern ) {
					if ( ! $input || ! isset( $input['product_id'] ) ) {
						return array(
							'status'  => 'error',
							'message' => 'product_id is required',
						);
					}
					$product_id = (int) $input['product_id'];
					unset( $input['product_id'] );

					$route   = str_replace( $product_pattern, (string) $product_id, $variations_route );
					$request = new \WP_REST_Request( 'GET', $route );
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

		// Extract dynamic schema for variation creation (POST)
		$create_schema = RestApiUtils::extract_input_schema( $variations_route, 'POST' );

		if ( ! $create_schema ) {
			$create_schema = array(
				'type'       => 'object',
				'properties' => array(
					'product_id'     => array(
						'type'        => 'integer',
						'description' => 'Parent product ID',
					),
					'regular_price'  => array(
						'type'        => 'string',
						'description' => 'Regular price',
					),
					'sale_price'     => array(
						'type'        => 'string',
						'description' => 'Sale price',
					),
					'sku'            => array(
						'type'        => 'string',
						'description' => 'Stock Keeping Unit',
					),
					'status'         => array(
						'type'        => 'string',
						'description' => 'Variation status',
					),
					'manage_stock'   => array(
						'type'        => 'boolean',
						'description' => 'Enable stock management',
					),
					'stock_quantity' => array(
						'type'        => 'integer',
						'description' => 'Stock quantity',
					),
					'stock_status'   => array(
						'type'        => 'string',
						'description' => 'Stock status (instock, outofstock, onbackorder)',
						'enum'        => array( 'instock', 'outofstock', 'onbackorder' ),
					),
					'weight'         => array(
						'type'        => 'string',
						'description' => 'Variation weight',
					),
					'dimensions'     => array(
						'type'        => 'object',
						'description' => 'Variation dimensions',
						'properties'  => array(
							'length' => array(
								'type'        => 'string',
								'description' => 'Length',
							),
							'width'  => array(
								'type'        => 'string',
								'description' => 'Width',
							),
							'height' => array(
								'type'        => 'string',
								'description' => 'Height',
							),
						),
					),
					'attributes'     => array(
						'type'        => 'array',
						'description' => 'Variation attribute values. Each attribute must match a product attribute. Use empty string for "Any".',
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'id'     => array(
									'type'        => 'integer',
									'description' => 'Attribute ID (use 0 for custom attributes)',
								),
								'name'   => array(
									'type'        => 'string',
									'description' => 'Attribute name',
								),
								'option' => array(
									'type'        => 'string',
									'description' => 'Selected term value (e.g. "Red"). Empty string means "Any".',
								),
							),
						),
					),
					'image'          => array(
						'type'        => 'object',
						'description' => 'Variation image',
						'properties'  => array(
							'id'  => array(
								'type'        => 'integer',
								'description' => 'Image attachment ID',
							),
							'src' => array(
								'type'        => 'string',
								'description' => 'Image URL',
							),
							'alt' => array(
								'type'        => 'string',
								'description' => 'Alt text',
							),
						),
					),
					'description'    => array(
						'type'        => 'string',
						'description' => 'Variation description',
					),
					'virtual'        => array(
						'type'        => 'boolean',
						'description' => 'Whether the variation is virtual',
					),
					'downloadable'   => array(
						'type'        => 'boolean',
						'description' => 'Whether the variation is downloadable',
					),
					'tax_class'      => array(
						'type'        => 'string',
						'description' => 'Tax class',
					),
					'shipping_class' => array(
						'type'        => 'string',
						'description' => 'Shipping class slug',
					),
				),
				'required'   => array( 'product_id' ),
			);
		} else {
			if ( ! isset( $create_schema['properties'] ) ) {
				$create_schema['properties'] = array();
			}
			$create_schema['properties']['product_id'] = array(
				'type'        => 'integer',
				'description' => 'Parent product ID',
			);
			$create_schema['required']                 = array_merge( $create_schema['required'] ?? array(), array( 'product_id' ) );
		}

		// Add variation
		blu_register_ability(
			'blu/wc-add-product-variation',
			array(
				'label'               => 'Add WooCommerce Product Variation',
				'description'         => sprintf( 'Create a variation for a WooCommerce variable product using %s API. The parent product must already exist and have attributes configured.', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $create_schema,
				'execute_callback'    => function ( $input ) use ( $variations_route, $product_pattern ) {
					if ( ! $input || ! isset( $input['product_id'] ) ) {
						return array(
							'status'  => 'error',
							'message' => 'product_id is required',
						);
					}
					$product_id = (int) $input['product_id'];
					unset( $input['product_id'] );

					$route   = str_replace( $product_pattern, (string) $product_id, $variations_route );
					$request = new \WP_REST_Request( 'POST', $route );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
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
			)
		);

		// Find generate variations route
		$generate_route = RestApiUtils::find_route_by_resource( $this->wc_namespace, 'products/' . $product_pattern . '/variations/generate' );

		if ( ! $generate_route ) {
			return;
		}

		// Generate all variations
		blu_register_ability(
			'blu/wc-generate-product-variations',
			array(
				'label'               => 'Generate WooCommerce Product Variations',
				'description'         => sprintf( 'Automatically generate all attribute combinations as variations for a WooCommerce variable product using %s API. The product must have variation attributes already set.', $this->wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'product_id'     => array(
							'type'        => 'integer',
							'description' => 'Variable product ID',
						),
						'delete'         => array(
							'type'        => 'boolean',
							'description' => 'Delete existing unused variations before generating',
							'default'     => false,
						),
						'default_values' => array(
							'type'        => 'object',
							'description' => 'Default field values to apply to each generated variation (e.g. {"regular_price":"10.00","stock_status":"instock"})',
						),
					),
					'required'   => array( 'product_id' ),
				),
				'execute_callback'    => function ( $input ) use ( $generate_route, $product_pattern ) {
					if ( ! $input || ! isset( $input['product_id'] ) ) {
						return array(
							'status'  => 'error',
							'message' => 'product_id is required',
						);
					}
					$product_id = (int) $input['product_id'];
					unset( $input['product_id'] );

					$route   = str_replace( $product_pattern, (string) $product_id, $generate_route );
					$request = new \WP_REST_Request( 'POST', $route );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
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
		$request      = new \WP_REST_Request( 'POST', $this->products_route . '/' . $type );
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
		$request  = new \WP_REST_Request( 'GET', $this->products_route . '/' . $product_id );
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
		$request = new \WP_REST_Request( 'GET', $this->products_route . '/' . $taxonomy . '/' . $term_id );

		$response = rest_do_request( $request );

		return blu_standardize_rest_response( $response );
	}
}
