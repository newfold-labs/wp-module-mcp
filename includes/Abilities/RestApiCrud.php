<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * RestApiCrud abilities for generic WordPress REST API operations.
 */
class RestApiCrud {

	/**
	 * Constructor - registers REST API CRUD abilities.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register REST API CRUD abilities.
	 */
	private function register_abilities(): void {
		// List available API functions
		blu_register_ability(
			'blu/list-api-functions',
			array(
				'label'               => 'List API Functions',
				'description'         => 'List all available WordPress REST API endpoints that support CRUD operations',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$ignore_routes  = array( '/', '/batch/v1' );
					$ignore_strings = array( 'oembed', 'autosaves', 'revisions', 'jwt-auth' );

					$routes = rest_get_server()->get_routes();
					$result = array();

					foreach ( $routes as $route => $methods ) {
						// Skip ignored routes
						if ( in_array( $route, $ignore_routes, true ) ) {
							continue;
						}

						// Skip routes with ignored strings
						$skip = false;
						foreach ( $ignore_strings as $ignore_string ) {
							if ( strpos( $route, $ignore_string ) !== false ) {
								$skip = true;
								break;
							}
						}
						if ( $skip ) {
							continue;
						}

						foreach ( $methods as $method_data ) {
							$result[] = array(
								'route'  => $route,
								'method' => key( $method_data['methods'] ),
							);
						}
					}

					return blu_prepare_ability_response( 200, $result );
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

		// Get function details
		blu_register_ability(
			'blu/get-function-details',
			array(
				'label'               => 'Get Function Details',
				'description'         => 'Get detailed metadata for a specific WordPress REST API endpoint and HTTP method',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'route'  => array(
							'type'        => 'string',
							'description' => 'REST API route (e.g., "/wp/v2/posts")',
						),
						'method' => array(
							'type'        => 'string',
							'enum'        => array( 'GET', 'POST', 'PATCH', 'DELETE' ),
							'description' => 'HTTP method',
						),
					),
					'required'   => array( 'route', 'method' ),
				),
				'execute_callback'    => function ( $input ) {
					$route  = $input['route'];
					$method = $input['method'];

					$routes = rest_get_server()->get_routes();

					if ( ! isset( $routes[ $route ] ) ) {
						return blu_prepare_ability_response( 404, 'Route not found' );
					}

					foreach ( $routes[ $route ] as $endpoint ) {
						if ( isset( $endpoint['methods'][ $method ] ) ) {
							return blu_prepare_ability_response( 200, $endpoint );
						}
					}

					return blu_prepare_ability_response( 404, 'Method not found for this route' );
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

		// Run API function
		blu_register_ability(
			'blu/run-api-function',
			array(
				'label'               => 'Run API Function',
				'description'         => 'Execute a specific WordPress REST API function by providing the endpoint route, HTTP method, and parameters',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'route'  => array(
							'type'        => 'string',
							'description' => 'REST API route (e.g., "/wp/v2/posts")',
						),
						'method' => array(
							'type'        => 'string',
							'enum'        => array( 'GET', 'POST', 'PATCH', 'DELETE' ),
							'description' => 'HTTP method',
						),
						'data'   => array(
							'type'        => 'object',
							'description' => 'Request parameters (query params for GET/DELETE, body for POST/PATCH)',
						),
					),
					'required'   => array( 'route', 'method' ),
				),
				'execute_callback'    => function ( $input ) {
					$route  = $input['route'];
					$method = $input['method'];
					$data   = $input['data'] ?? array();

					// Parse query parameters from route if present
					$query_params = array();
					if ( strpos( $route, '?' ) !== false ) {
						$parts      = explode( '?', $route, 2 );
						$route      = $parts[0];
						parse_str( $parts[1], $query_params );
					}

					// Create REST request
					$request = new \WP_REST_Request( $method, $route );

					// Set parameters based on method
					if ( in_array( $method, array( 'GET', 'DELETE' ), true ) ) {
						if ( ! empty( $data ) ) {
							$query_params = array_merge( $query_params, $data );
						}
						if ( ! empty( $query_params ) ) {
							$request->set_query_params( $query_params );
						}
					} else {
						if ( ! empty( $data ) ) {
							$request->set_body_params( $data );
						}
					}

					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => true,
						'idempotent'   => false,
					),
				),
			)
		);
	}
}
