<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Users abilities for WordPress user management.
 */
class Users {

	/**
	 * Base REST API namespace used to discover the latest versioned namespace.
	 *
	 * @var string
	 */
	private string $base_namespace = 'wp';

	/**
	 * Constructor - registers all user-related abilities.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register user abilities.
	 */
	private function register_abilities(): void {
		// Search/list users
		blu_register_ability(
			'blu/users-search',
			array(
				'label'               => 'Search Users',
				'description'         => 'Search and filter WordPress users with pagination',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. Use blu-get-function-details to retrieve it, if needed.',
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for users not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$request = new \WP_REST_Request( 'GET', $root );
					$query   = is_array( $input ) ? $input : array();
					$request->set_query_params( $query );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'list_users' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Get single user
		blu_register_ability(
			'blu/get-user',
			array(
				'label'               => 'Get User',
				'description'         => 'Get a WordPress user by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'User ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$user_id = (int) $input['id'];
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for users not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}
					$request = new \WP_REST_Request( 'GET', $root . '/' . $user_id );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'list_users' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Add user
		blu_register_ability(
			'blu/add-user',
			array(
				'label'               => 'Add User',
				'description'         => 'Add a new WordPress user',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. Use blu-get-function-details to retrieve it, if needed.',
					),
					'required'   => array( 'username', 'email', 'password', 'roles' ),
				),
				'execute_callback'    => function ( $input ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for users not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$request = new \WP_REST_Request( 'POST', $root );
					$request->set_body_params( $input );

					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'create_users' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		// Update user
		blu_register_ability(
			'blu/update-user',
			array(
				'label'               => 'Update User',
				'description'         => 'Update a WordPress user by ID',
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
					$user_id = (int) $input['id'];

					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for users not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$request = new \WP_REST_Request( 'PUT', $root . '/' . $user_id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_users' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Delete user
		blu_register_ability(
			'blu/delete-user',
			array(
				'label'               => 'Delete User',
				'description'         => 'Delete a WordPress user by ID',
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
					$user_id = (int) $input['id'];
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for users not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

					$request = new \WP_REST_Request( 'DELETE', $root . '/' . $user_id );
					unset( $input['id'] );
					$request->set_query_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'delete_users' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);

		// Get current user
		blu_register_ability(
			'blu/get-current-user',
			array(
				'label'               => 'Get Current User',
				'description'         => 'Get the current logged-in user',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
				),
				'execute_callback'    => function () {

					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for users not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}
					$request = new \WP_REST_Request( 'GET', $root . '/me' );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => is_user_logged_in(),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Update current user
		blu_register_ability(
			'blu/update-current-user',
			array(
				'label'               => 'Update Current User',
				'description'         => 'Update the current logged-in user',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => 'object',
						'description' => 'An object containing the native query or body parameters required by the target endpoint. You can use blu-get-function-details to retreive it, if needed.',
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for users not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);

					}

						$request = new \WP_REST_Request( 'PUT', $root . '/me' );
					if ( is_array( $input ) ) {

						$request->set_body_params( $input );
					}
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => is_user_logged_in(),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}
}
