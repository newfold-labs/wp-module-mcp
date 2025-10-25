<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Users abilities for WordPress user management.
 */
class Users {

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
							'description' => 'Users per page',
						),
						'role'     => array(
							'type'        => 'string',
							'description' => 'Filter by role',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/users' );
					if ( $input ) {
						$input['context'] = 'edit';
						$request->set_query_params( $input );
					}
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'list_users' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
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
					$request = new \WP_REST_Request( 'GET', '/wp/v2/users/' . $input['id'] );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'list_users' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
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
						'username'   => array(
							'type'        => 'string',
							'description' => 'Username',
						),
						'email'      => array(
							'type'        => 'string',
							'description' => 'Email address',
						),
						'password'   => array(
							'type'        => 'string',
							'description' => 'Password',
						),
						'first_name' => array(
							'type'        => 'string',
							'description' => 'First name',
						),
						'last_name'  => array(
							'type'        => 'string',
							'description' => 'Last name',
						),
						'role'       => array(
							'type'        => 'string',
							'description' => 'User role',
						),
					),
					'required'   => array( 'username', 'email', 'password' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'POST', '/wp/v2/users' );
					$input['context'] = 'edit';
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'create_users' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => false,
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
						'id'         => array(
							'type'        => 'integer',
							'description' => 'User ID',
						),
						'email'      => array(
							'type'        => 'string',
							'description' => 'Email address',
						),
						'first_name' => array(
							'type'        => 'string',
							'description' => 'First name',
						),
						'last_name'  => array(
							'type'        => 'string',
							'description' => 'Last name',
						),
						'role'       => array(
							'type'        => 'string',
							'description' => 'User role',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'PUT', '/wp/v2/users/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'edit_users' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => true,
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
						'id'       => array(
							'type'        => 'integer',
							'description' => 'User ID',
						),
						'reassign' => array(
							'type'        => 'integer',
							'description' => 'Reassign posts to this user ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'DELETE', '/wp/v2/users/' . $input['id'] );
					if ( isset( $input['reassign'] ) ) {
						$request->set_param( 'reassign', $input['reassign'] );
					}
					$request->set_param( 'force', true );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'delete_users' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => true,
						'idempotent'   => true,
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
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/users/me' );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => is_user_logged_in(),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
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
						'email'      => array(
							'type'        => 'string',
							'description' => 'Email address',
						),
						'first_name' => array(
							'type'        => 'string',
							'description' => 'First name',
						),
						'last_name'  => array(
							'type'        => 'string',
							'description' => 'Last name',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$request = new \WP_REST_Request( 'PUT', '/wp/v2/users/me' );
					if ( $input ) {
						$request->set_body_params( $input );
					}
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => is_user_logged_in(),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);
	}
}
