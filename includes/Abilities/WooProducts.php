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
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
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
					$request = new \WP_REST_Request( 'GET', '/wc/v3/products/' . $input['id'] );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		// Add product
		blu_register_ability(
			'blu/wc-add-product',
			array(
				'label'               => 'Add WooCommerce Product',
				'description'         => 'Add a new WooCommerce product',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'        => array(
							'type'        => 'string',
							'description' => 'Product name',
						),
						'type'        => array(
							'type'        => 'string',
							'description' => 'Product type',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Product description',
						),
						'regular_price'       => array(
							'type'        => 'string',
							'description' => 'Product price',
						),
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'POST', '/wc/v3/products' );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => false,
					),
				),
			)
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
						'id'            => array(
							'type'        => 'integer',
							'description' => 'Product ID',
						),
						'name'          => array(
							'type'        => 'string',
							'description' => 'Product name',
						),
						'description'   => array(
							'type'        => 'string',
							'description' => 'Product description',
						),
						'regular_price' => array(
							'type'        => 'string',
							'description' => 'Product price',
						),
						'sale_price'    => array(
							'type'        => 'string',
							'description' => 'Product sale price',
						),
						'category'      => array(
							'type'        => 'string',
							'description' => 'Product category to set',
						),
						'tag'           => array(
							'type'        => 'string',
							'description' => 'Product tag to set',
						),
						'brand'           => array(
							'type'        => 'string',
							'description' => 'Product brand to set',
						)
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					if ( isset( $input['category'] ) ) {
						$category = $input['category'];

						$category = $this->get_taxonomy_id_by_name( $category );
						if( is_wp_error( $category ) | is_array( $category ) ) {
							return $category;
						}

						$stored_category     = $this->get_product_taxonomy_ids( $id );
						$input['categories'] = array_merge( [ [ 'id' => $category ] ], $stored_category );
						unset( $input['category'] );
					}

					if ( isset( $input['tag'] ) ) {
						$tag = $input['tag'];
						$tag = $this->get_taxonomy_id_by_name( $tag, 'tags' );
						if( is_wp_error( $tag ) | is_array( $tag ) ) {
							return $tag;
						}
						$stored_tag    = $this->get_product_taxonomy_ids( $id, 'tags' );
						$input['tags'] = array_merge( [ [ 'id' => $tag ]], $stored_tag  );
						unset( $input['tag'] );
					}

					if ( isset( $input['brand'] ) ) {
						$brand = $input['brand'];
						$brand = $this->get_taxonomy_id_by_name( $brand, 'brands' );
						if( is_wp_error( $brand ) | is_array( $brand ) ) {
							return $tag;
						}
						$stored_brand    = $this->get_product_taxonomy_ids( $id, 'brands' );
						$input['brands'] = array_merge( [ [ 'id' => $brand ] ], $stored_brand  );
						unset( $input['brand'] );
					}
					$request = new \WP_REST_Request( 'PUT', '/wc/v3/products/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => true,
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
						'readonly'     => false,
						'destructive'  => true,
						'idempotent'   => true,
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
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/products/categories' );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
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
							'description' => 'Product Categories List',
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
						)
					),
					'required'   => array( 'categories' ),
				),
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
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => false,
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
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => true,
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
						'readonly'     => false,
						'destructive'  => true,
						'idempotent'   => true,
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
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/products/tags' );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
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
							'description' => 'The Tag name list',
						),
					),
					'required'   => array( 'tags' ),
				),
				'execute_callback'    => function ( $input ) {

					return $this->add_product_taxonomies( $input['tags'], 'tags' );
				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => false,
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
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => true,
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
						'readonly'     => false,
						'destructive'  => true,
						'idempotent'   => true,
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
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/products/brands' );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_products' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
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
						),
					),
					'required'   => array( 'brands' ),
				),
				'execute_callback'    => function ( $input ) {
					return $this->add_product_taxonomies( $input['brands'], 'brands' );
				},
				'permission_callback' => fn() => current_user_can( 'manage_product_terms' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => false,
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
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => true,
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
						'readonly'     => false,
						'destructive'  => true,
						'idempotent'   => true,
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
			} else if ( 201 == $response ['statusCode'] ) {
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
	 * @param string $taxonomy The taxonomy to return.
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
			$data = $response->get_data();
			$uncategorized_id = get_option( 'default_product_cat');
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



	/**
	 * Get the id for a taxonomy by term name
	 *
	 * @param string|int $name The name or the id.
	 * @param string $taxonomy The taxonomy.
	 *
	 * @return \WP_REST_Response|int|array
	 */
	private function get_taxonomy_id_by_name( $name, $taxonomy = 'categories' ) {

		if( is_string( $name ) ) {
			$request = new \WP_REST_Request( 'GET', '/wc/v3/products/'.$taxonomy );
			$request->set_query_params( [ 'slug' => sanitize_title( $name ), 'hide_empty' => false ] );
			$response = rest_do_request( $request );
			if ( is_wp_error( $response ) ) {
				return $response;
			} else {
				$data = $response->get_data();
				if ( 1 !== count( $data )  ) {
					return [
						'statusCode' => 400,
						'status'     => 'error',
						'message'    => 'An not unique ' . $taxonomy . ' found for ' . $name,
					];
				}
				return $data[0]['id'];
			}
		}
		return $name;
	}
}
