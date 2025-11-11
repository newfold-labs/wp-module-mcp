<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Posts abilities for WordPress posts, categories, and tags.
 */
class Posts {

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
							'description' => 'Posts per page',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
					if ( $input ) {
						$request->set_query_params( $input );
					}
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
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
					$request = new \WP_REST_Request( 'GET', '/wp/v2/posts/' . $input['id'] );
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
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
						'title'   => array(
							'type'        => 'string',
							'description' => 'Post title',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'Post content in Gutenberg block format',
						),
						'excerpt' => array(
							'type'        => 'string',
							'description' => 'Post excerpt',
						),
						'status'  => array(
							'type'        => 'string',
							'description' => 'Post status (publish, draft, etc.)',
						),
					),
					'required'   => array( 'title', 'content' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'POST', '/wp/v2/posts' );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => false,
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
						'id'      => array(
							'type'        => 'integer',
							'description' => 'Post ID',
						),
						'title'   => array(
							'type'        => 'string',
							'description' => 'Post title',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'Post content',
						),
						'excerpt' => array(
							'type'        => 'string',
							'description' => 'Post excerpt',
						),
						'status'  => array(
							'type'        => 'string',
							'description' => 'Post status',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'PUT', '/wp/v2/posts/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => true,
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
						'id' => array(
							'type'        => 'integer',
							'description' => 'Post ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'DELETE', '/wp/v2/posts/' . $input['id'] );
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'delete_posts' ),
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
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/categories' );
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
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
			'blu/add-category',
			array(
				'label'               => 'Add Category',
				'description'         => 'Add a new WordPress post category',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'        => array(
							'type'        => 'string',
							'description' => 'Category name',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Category description',
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => 'Category slug',
						),
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'POST', '/wp/v2/categories' );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'manage_categories' ),
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
			'blu/update-category',
			array(
				'label'               => 'Update Category',
				'description'         => 'Update a WordPress post category',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array(
							'type'        => 'integer',
							'description' => 'Category ID',
						),
						'name'        => array(
							'type'        => 'string',
							'description' => 'Category name',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Category description',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'PUT', '/wp/v2/categories/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'manage_categories' ),
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
			'blu/delete-category',
			array(
				'label'               => 'Delete Category',
				'description'         => 'Delete a WordPress post category',
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
					$request = new \WP_REST_Request( 'DELETE', '/wp/v2/categories/' . $input['id'] );
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'manage_categories' ),
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
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/tags' );
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
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
			'blu/add-tag',
			array(
				'label'               => 'Add Tag',
				'description'         => 'Add a new WordPress post tag',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'        => array(
							'type'        => 'string',
							'description' => 'Tag name',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Tag description',
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => 'Tag slug',
						),
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'POST', '/wp/v2/tags' );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'manage_categories' ),
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
			'blu/update-tag',
			array(
				'label'               => 'Update Tag',
				'description'         => 'Update a WordPress post tag',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array(
							'type'        => 'integer',
							'description' => 'Tag ID',
						),
						'name'        => array(
							'type'        => 'string',
							'description' => 'Tag name',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Tag description',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'PUT', '/wp/v2/tags/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'manage_categories' ),
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
			'blu/delete-tag',
			array(
				'label'               => 'Delete Tag',
				'description'         => 'Delete a WordPress post tag',
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
					$request = new \WP_REST_Request( 'DELETE', '/wp/v2/tags/' . $input['id'] );
					$response = rest_do_request( $request );
					return array( 'response' => $response->get_data() );
				},
				'permission_callback' => fn() => current_user_can( 'manage_categories' ),
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
