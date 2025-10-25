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
					$request = new \WP_REST_Request( 'GET', '/wp/v2/pages' );
					if ( $input ) {
						$request->set_query_params( $input );
					}
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'edit_pages' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
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
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'edit_pages' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
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
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'edit_pages' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => false,
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
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'edit_pages' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => true,
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
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'delete_pages' ),
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
