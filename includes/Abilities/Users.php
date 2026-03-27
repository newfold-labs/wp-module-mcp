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
						'roles'    => array(
							'type'        => 'array',
							'description' => 'Limit to users with at least one of these role slugs (WordPress REST collection param `roles`).',
							'items'       => array(
								'type' => 'string',
							),
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/users' );
					$query   = is_array( $input ) ? $input : array();
					unset( $query['context'] );
					$query['context'] = 'edit';
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
					$request = new \WP_REST_Request( 'GET', '/wp/v2/users/' . $user_id );
					$request->set_query_params( array( 'context' => 'edit' ) );
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
						'roles'      => array(
							'type'        => 'array',
							'description' => 'WordPress REST `roles`: one or more role slugs (e.g. ["editor"], ["subscriber"]).',
							'items'       => array(
								'type' => 'string',
							),
							'minItems'    => 1,
						),
					),
					'required'   => array( 'username', 'email', 'password', 'roles' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'POST', '/wp/v2/users' );
					unset( $input['context'] );
					$request->set_body_params( $input );
					$request->set_query_params( array( 'context' => 'edit' ) );
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
						'roles'      => array(
							'type'        => 'array',
							'description' => 'WordPress REST `roles` when updating roles; omit if not changing roles.',
							'items'       => array(
								'type' => 'string',
							),
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$user_id = (int) $input['id'];
					unset( $input['id'] );
					unset( $input['context'] );
					$request = new \WP_REST_Request( 'PUT', '/wp/v2/users/' . $user_id );
					$request->set_body_params( $input );
					$request->set_query_params( array( 'context' => 'edit' ) );
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
						'id'       => array(
							'type'        => 'integer',
							'description' => 'User ID',
						),
						'reassign' => array(
							'type'        => 'integer',
							'description' => 'User ID to reassign posts to; omit or use 0 / false for no reassignment (the REST API always receives a `reassign` value).',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$user_id = (int) $input['id'];
					$request = new \WP_REST_Request( 'DELETE', '/wp/v2/users/' . $user_id );
					$reassign = array_key_exists( 'reassign', $input ) ? $input['reassign'] : false;
					$request->set_param( 'reassign', $reassign );
					$request->set_param( 'force', true );
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
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/users/me' );
					$request->set_query_params( array( 'context' => 'edit' ) );
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
					if ( is_array( $input ) ) {
						unset( $input['context'] );
						$request->set_body_params( $input );
					}
					$request->set_query_params( array( 'context' => 'edit' ) );
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
