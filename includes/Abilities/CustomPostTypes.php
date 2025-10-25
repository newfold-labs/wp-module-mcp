<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * CustomPostTypes abilities for WordPress custom post types.
 */
class CustomPostTypes {

	/**
	 * Constructor - registers custom post type abilities.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register custom post type abilities.
	 */
	private function register_abilities(): void {
		// List post types
		blu_register_ability(
			'blu/list-post-types',
			array(
				'label'               => 'List Post Types',
				'description'         => 'List all available WordPress custom post types',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/types' );
					$response = rest_do_request( $request );
					return $response->get_data();
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

		// Search custom post types
		blu_register_ability(
			'blu/cpt-search',
			array(
				'label'               => 'Search Custom Post Types',
				'description'         => 'Search and filter WordPress custom post types with pagination',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'The custom post type to search',
						),
						'search'    => array(
							'type'        => 'string',
							'description' => 'Search term',
						),
						'author'    => array(
							'type'        => 'integer',
							'description' => 'Filter by author ID',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => 'Filter by post status',
						),
						'page'      => array(
							'type'        => 'integer',
							'description' => 'Page number',
							'default'     => 1,
						),
						'per_page'  => array(
							'type'        => 'integer',
							'description' => 'Items per page',
							'default'     => 10,
						),
					),
					'required'   => array( 'post_type' ),
				),
				'execute_callback'    => function ( $input ) {
					$post_type = sanitize_text_field( $input['post_type'] );
					$page      = $input['page'] ?? 1;
					$per_page  = $input['per_page'] ?? 10;

					$args = array(
						'post_type'      => $post_type,
						'posts_per_page' => $per_page,
						'paged'          => $page,
						'post_status'    => 'publish',
					);

					if ( ! empty( $input['search'] ) ) {
						$args['s'] = sanitize_text_field( $input['search'] );
					}

					if ( ! empty( $input['author'] ) ) {
						$args['author'] = intval( $input['author'] );
					}

					if ( ! empty( $input['status'] ) ) {
						$args['post_status'] = sanitize_text_field( $input['status'] );
					}

					$query = new \WP_Query( $args );
					return array(
						'results'  => $query->posts,
						'total'    => $query->found_posts,
						'pages'    => $query->max_num_pages,
						'page'     => $page,
						'per_page' => $per_page,
					);
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

		// Get custom post type
		blu_register_ability(
			'blu/get-cpt',
			array(
				'label'               => 'Get Custom Post Type',
				'description'         => 'Get a WordPress custom post type by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'The custom post type',
						),
						'id'        => array(
							'type'        => 'integer',
							'description' => 'Post ID',
						),
					),
					'required'   => array( 'post_type', 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$post = get_post( intval( $input['id'] ) );
					if ( ! $post || $post->post_type !== $input['post_type'] ) {
						return new \WP_Error( 'post_not_found', 'Post not found' );
					}
					return $post;
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

		// Add custom post type
		blu_register_ability(
			'blu/add-cpt',
			array(
				'label'               => 'Add Custom Post Type',
				'description'         => 'Add a new WordPress custom post type',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'The custom post type',
						),
						'title'     => array(
							'type'        => 'string',
							'description' => 'Post title',
						),
						'content'   => array(
							'type'        => 'string',
							'description' => 'Post content in Gutenberg block format',
						),
						'excerpt'   => array(
							'type'        => 'string',
							'description' => 'Post excerpt',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => 'Post status',
						),
					),
					'required'   => array( 'post_type', 'title', 'content' ),
				),
				'execute_callback'    => function ( $input ) {
					$post_data = array(
						'post_type'    => sanitize_text_field( $input['post_type'] ),
						'post_title'   => sanitize_text_field( $input['title'] ),
						'post_content' => wp_kses_post( $input['content'] ),
						'post_status'  => 'draft',
					);

					if ( ! empty( $input['excerpt'] ) ) {
						$post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
					}

					if ( ! empty( $input['status'] ) ) {
						$post_data['post_status'] = sanitize_text_field( $input['status'] );
					}

					$post_id = wp_insert_post( $post_data );
					if ( is_wp_error( $post_id ) ) {
						return $post_id;
					}

					return get_post( $post_id );
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

		// Update custom post type
		blu_register_ability(
			'blu/update-cpt',
			array(
				'label'               => 'Update Custom Post Type',
				'description'         => 'Update a WordPress custom post type by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'The custom post type',
						),
						'id'        => array(
							'type'        => 'integer',
							'description' => 'Post ID',
						),
						'title'     => array(
							'type'        => 'string',
							'description' => 'Post title',
						),
						'content'   => array(
							'type'        => 'string',
							'description' => 'Post content',
						),
						'excerpt'   => array(
							'type'        => 'string',
							'description' => 'Post excerpt',
						),
						'status'    => array(
							'type'        => 'string',
							'description' => 'Post status',
						),
					),
					'required'   => array( 'post_type', 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$post = get_post( intval( $input['id'] ) );
					if ( ! $post || $post->post_type !== $input['post_type'] ) {
						return new \WP_Error( 'post_not_found', 'Post not found' );
					}

					$post_data = array(
						'ID' => $post->ID,
					);

					if ( ! empty( $input['title'] ) ) {
						$post_data['post_title'] = sanitize_text_field( $input['title'] );
					}

					if ( ! empty( $input['content'] ) ) {
						$post_data['post_content'] = wp_kses_post( $input['content'] );
					}

					if ( ! empty( $input['excerpt'] ) ) {
						$post_data['post_excerpt'] = sanitize_text_field( $input['excerpt'] );
					}

					if ( ! empty( $input['status'] ) ) {
						$post_data['post_status'] = sanitize_text_field( $input['status'] );
					}

					$post_id = wp_update_post( $post_data );
					if ( is_wp_error( $post_id ) ) {
						return $post_id;
					}

					return get_post( $post_id );
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

		// Delete custom post type
		blu_register_ability(
			'blu/delete-cpt',
			array(
				'label'               => 'Delete Custom Post Type',
				'description'         => 'Delete a WordPress custom post type by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => 'The custom post type',
						),
						'id'        => array(
							'type'        => 'integer',
							'description' => 'Post ID',
						),
					),
					'required'   => array( 'post_type', 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$post = get_post( intval( $input['id'] ) );
					if ( ! $post || $post->post_type !== $input['post_type'] ) {
						return new \WP_Error( 'post_not_found', 'Post not found' );
					}

					$result = wp_delete_post( $post->ID, true );
					if ( ! $result ) {
						return new \WP_Error( 'delete_failed', 'Failed to delete post' );
					}

					return array( 'success' => true );
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
}
