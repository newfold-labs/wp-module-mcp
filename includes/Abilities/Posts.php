<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Posts abilities for WordPress posts, categories, and tags.
 */
class Posts {

	/**
	 * Base REST API namespace used to discover the latest versioned namespace.
	 *
	 * @var string
	 */
	private string $base_namespace = 'wp';

	/**
	 * Constructor - registers all post-related abilities.
	 */
	public function __construct() {
		$this->register_post_abilities();
		$this->register_category_abilities();
		$this->register_tag_abilities();
	}

	/**
	 * Register post abilities.
	 */
	private function register_post_abilities(): void {

		// Search/list posts
		blu_register_ability(
			'blu/posts-search',
			array(
				'label'               => 'Search Posts',
				'description'         => 'Search and filter WordPress posts with pagination',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'posts' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for posts not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$method     = 'GET';
					$request    = new \WP_REST_Request( $method, $root );

					$all_statuses = 'publish,future,draft,pending,private';
					if ( $input ) {
						// Default to all statuses when not specified or empty (WP defaults to publish only).
						if ( ! isset( $input['status'] ) || '' === $input['status'] ) {
							$input['status'] = $all_statuses;
						}
						$request->set_query_params( $input );
					} else {
						$request->set_param( 'status', $all_statuses );
					}
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Get single post
		blu_register_ability(
			'blu/get-post',
			array(
				'label'               => 'Get Post',
				'description'         => 'Get a WordPress post by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Post ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'posts' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for posts not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}
					$method     = 'GET';
					$request    = new \WP_REST_Request( $method, $root . '/' . $input['id'] );
					$request->set_query_params( $input );
					$response   = rest_do_request( $request );

					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Add post
		blu_register_ability(
			'blu/add-post',
			array(
				'label'               => 'Add Post',
				'description'         => 'Add a new WordPress post',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
					'required'   => array( 'title', 'content' ),
				),
				'execute_callback'    => function ( $input ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'posts' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for posts not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$method     = 'POST';
					$request    = new \WP_REST_Request( $method, $root );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		// Update post
		blu_register_ability(
			'blu/update-post',
			array(
				'label'               => 'Update Post',
				'description'         => 'Update a WordPress post by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {

					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'posts' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for posts not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$id = $input['id'];
					unset( $input['id'] );
					$method     = 'PUT';
					$request    = new \WP_REST_Request( $method, $root . '/' . $id );

					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Delete post
		blu_register_ability(
			'blu/delete-post',
			array(
				'label'               => 'Delete Post',
				'description'         => 'Delete a WordPress post by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'posts' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for posts not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$id = $input['id'];
					unset( $input['id'] );
					$method     = 'DELETE';
					$request    = new \WP_REST_Request( $method, $root . '/' . $id );

					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'delete_posts' ),
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
	 * Register category abilities.
	 */
	private function register_category_abilities(): void {
		// List categories
		blu_register_ability(
			'blu/list-categories',
			array(
				'label'               => 'List Categories',
				'description'         => 'List all WordPress post categories',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'categories' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for categories not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}
					$method     = 'GET';
					$request    = new \WP_REST_Request( $method, $root );

					if ( $input ) {
						$request->set_query_params( $input );
					}

					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
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
			'blu/add-category',
			array(
				'label'               => 'Add Category',
				'description'         => 'Add a new WordPress post category',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'categories' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for categories not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$method     = 'POST';
					$request    = new \WP_REST_Request( $method, $root );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_categories' ),
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
			'blu/update-category',
			array(
				'label'               => 'Update Category',
				'description'         => 'Update a WordPress post category',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {

					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'categories' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for categories not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$id = $input['id'];
					unset( $input['id'] );
					$method     = 'PUT';
					$request    = new \WP_REST_Request( $method, $root . '/' . $id );

					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_categories' ),
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
			'blu/delete-category',
			array(
				'label'               => 'Delete Category',
				'description'         => 'Delete a WordPress post category',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {

					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'categories' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for categories not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$id = $input['id'];
					unset( $input['id'] );
					$method     = 'DELETE';
					$request    = new \WP_REST_Request( $method, $root . '/' . $id );
					$request->set_query_params( array( 'force' => true ) );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_categories' ),
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
	 * Register tag abilities.
	 */
	private function register_tag_abilities(): void {
		// List tags
		blu_register_ability(
			'blu/list-tags',
			array(
				'label'               => 'List Tags',
				'description'         => 'List all WordPress post tags',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'tags' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for tags not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}
					$method     = 'GET';
					$request    = new \WP_REST_Request( $method, $root );

					if ( $input ) {
						$request->set_query_params( $input );
					}

					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
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
			'blu/add-tag',
			array(
				'label'               => 'Add Tag',
				'description'         => 'Add a new WordPress post tag',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'tags' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for tags not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$method     = 'POST';
					$request    = new \WP_REST_Request( $method, $root );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_categories' ),
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
			'blu/update-tag',
			array(
				'label'               => 'Update Tag',
				'description'         => 'Update a WordPress post tag',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {

					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'tags' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for tags not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$id = $input['id'];
					unset( $input['id'] );
					$method     = 'PUT';
					$request    = new \WP_REST_Request( $method, $root . '/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_categories' ),
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
			'blu/delete-tag',
			array(
				'label'               => 'Delete Tag',
				'description'         => 'Delete a WordPress post tag',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {

					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'tags' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for tags not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$id = $input['id'];
					unset( $input['id'] );
					$method     = 'DELETE';
					$request    = new \WP_REST_Request( $method, $root . '/' . $id );
					$request->set_query_params( array( 'force' => true ) );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_categories' ),
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
}
