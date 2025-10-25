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
					return $response->get_data();
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
					return $response->get_data();
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
						'price'       => array(
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
					return $response->get_data();
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
						'id'          => array(
							'type'        => 'integer',
							'description' => 'Product ID',
						),
						'name'        => array(
							'type'        => 'string',
							'description' => 'Product name',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Product description',
						),
						'price'       => array(
							'type'        => 'string',
							'description' => 'Product price',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'PUT', '/wc/v3/products/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return $response->get_data();
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
					return $response->get_data();
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
					return $response->get_data();
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
				'description'         => 'Add a new WooCommerce product category',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Category name',
						),
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'POST', '/wc/v3/products/categories' );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return $response->get_data();
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
					return $response->get_data();
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
					return $response->get_data();
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
					return $response->get_data();
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
				'description'         => 'Add a new WooCommerce product tag',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Tag name',
						),
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'POST', '/wc/v3/products/tags' );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return $response->get_data();
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
					return $response->get_data();
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
					return $response->get_data();
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
					return $response->get_data();
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
				'description'         => 'Add a new WooCommerce product brand',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Brand name',
						),
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'POST', '/wc/v3/products/brands' );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return $response->get_data();
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
					return $response->get_data();
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
					return $response->get_data();
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
}
