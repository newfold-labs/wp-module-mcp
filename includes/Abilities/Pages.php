<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Pages abilities for WordPress pages.
 */
class Pages {

	/**
	 * Constructor - registers all page-related abilities.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register page abilities.
	 */
	private function register_abilities(): void {
		// Search/list pages
		blu_register_ability(
			'blu/pages-search',
			array(
				'label'               => 'Search Pages',
				'description'         => 'Search and filter WordPress pages with pagination',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array(
							'type'        => 'string',
							'description' => 'Search term',
						),
						'status'   => array(
							'type'        => 'string',
							'description' => 'Page status(es): publish, draft, pending, future, private, trash. Comma-separated for multiple. Omit to search every status except trash. A search term matching nothing outside the trash then retries inside it, so a just-deleted page is still found and comes back with status "trash".',
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page number',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Pages per page',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$all_statuses = 'publish,future,draft,pending,private';
					$input        = is_array( $input ) ? $input : array();

					// Default to all statuses when not specified or empty (WP defaults to publish only).
					$params = $input;
					if ( ! isset( $params['status'] ) || '' === $params['status'] ) {
						$params['status'] = $all_statuses;
					}

					$request = new \WP_REST_Request( 'GET', '/wp/v2/pages' );
					$request->set_query_params( $params );
					$response = blu_standardize_rest_response( rest_do_request( $request ) );

					// Trashing keeps the title but drops the page out of $all_statuses,
					// so a lookup by name right after a delete finds nothing. Retry in
					// the trash and return the match with its "trash" status.
					if ( blu_should_retry_in_trash( $input, $response['message'] ?? null ) ) {
						$retry = new \WP_REST_Request( 'GET', '/wp/v2/pages' );
						$retry->set_query_params( array_merge( $params, array( 'status' => 'trash' ) ) );
						$trashed = blu_standardize_rest_response( rest_do_request( $retry ) );
						// Only swap in a retry that succeeded. A failed retry is still an
						// array, so without this an unrelated error would replace a
						// perfectly good "nothing matched" with a 4xx the caller never caused.
						if ( 200 === $trashed['statusCode'] && is_array( $trashed['message'] ) && count( $trashed['message'] ) > 0 ) {
							return $trashed;
						}
					}

					return $response;
				},
				'permission_callback' => fn() => current_user_can( 'edit_pages' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Get single page
		blu_register_ability(
			'blu/get-page',
			array(
				'label'               => 'Get Page',
				'description'         => 'Get a WordPress page by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Page ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/pages/' . $input['id'] );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_pages' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Add page
		blu_register_ability(
			'blu/add-page',
			array(
				'label'               => 'Add Page',
				'description'         => 'Add a new WordPress page',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'   => array(
							'type'        => 'string',
							'description' => 'Page title',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'Page content in Gutenberg block format',
						),
						'excerpt' => array(
							'type'        => 'string',
							'description' => 'Page excerpt',
						),
						'parent'  => array(
							'type'        => 'integer',
							'description' => 'Parent page ID',
						),
						'order'   => array(
							'type'        => 'integer',
							'description' => 'Page order',
						),
						'status'  => array(
							'type'        => 'string',
							'description' => 'Page status (publish, draft, etc.)',
						),
					),
					'required'   => array( 'title', 'content' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'POST', '/wp/v2/pages' );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_pages' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		// Update page
		blu_register_ability(
			'blu/update-page',
			array(
				'label'               => 'Update Page',
				'description'         => 'Update a WordPress page by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array(
							'type'        => 'integer',
							'description' => 'Page ID',
						),
						'title'   => array(
							'type'        => 'string',
							'description' => 'Page title',
						),
						'content' => array(
							'type'        => 'string',
							'description' => 'Page content',
						),
						'excerpt' => array(
							'type'        => 'string',
							'description' => 'Page excerpt',
						),
						'parent'  => array(
							'type'        => 'integer',
							'description' => 'Parent page ID',
						),
						'order'   => array(
							'type'        => 'integer',
							'description' => 'Page order',
						),
						'status'  => array(
							'type'        => 'string',
							'description' => 'Page status',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'PUT', '/wp/v2/pages/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_pages' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Delete page
		blu_register_ability(
			'blu/delete-page',
			array(
				'label'               => 'Delete Page',
				'description'         => 'Delete a WordPress page by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Page ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'DELETE', '/wp/v2/pages/' . $input['id'] );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'delete_pages' ),
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
