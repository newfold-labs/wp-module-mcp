<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Pages abilities for WordPress pages.
 */
class Pages {

	/**
	 * Base REST API namespace used to discover the latest versioned namespace.
	 *
	 * @var string
	 */
	private $base_namespace = 'wp';

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
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
				),
				'execute_callback'    => function ( $input = null ) {

					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'pages' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for pages not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$request = new \WP_REST_Request( 'GET', $root );
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
					$id = $input['id'];
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'pages' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for pages not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}
					$request = new \WP_REST_Request( 'GET', $root . '/' . $id );
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
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. Use blu-get-function-details to retrieve it, if needed.',
					),
					'required'   => array( 'title', 'content' ),
				),
				'execute_callback'    => function ( $input ) {

					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'pages' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for pages not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$request = new \WP_REST_Request( 'POST', $root );
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
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. Use blu-get-function-details to retrieve it, if needed.',
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'pages' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for pages not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$request = new \WP_REST_Request( 'PUT', $root . '/' . $id );
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
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'pages' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for pages not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$request = new \WP_REST_Request( 'DELETE', $root . '/' . $input['id'] );
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
