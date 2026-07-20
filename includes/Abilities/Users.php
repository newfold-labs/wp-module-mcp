<?php
declare( strict_types=1 );

namespace BLU\Abilities;

use BLU\RestControllerSchema\RestControllerSchemaBuilder;

/**
 * Users abilities for WordPress user management.
 */
class Users {

	/**
	 * Base REST API namespace used to discover the latest versioned namespace.
	 *
	 * @var string
	 */
	private $base_namespace = 'wp';

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
		$schemas = RestControllerSchemaBuilder::for_users();

		// Search/list users
		blu_register_ability(
			'blu/users-search',
			array(
				'label'               => 'Search Users',
				'description'         => 'Search and filter WordPress users with pagination',
				'category'            => 'blu-mcp',
				'input_schema'        => $schemas->collection(),
				'execute_callback'    => function ( $input = null ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_route_unavailable_for_resource( 'users' );
					}

					$request = new \WP_REST_Request( 'GET', $root );
					$query   = is_array( $input ) ? $input : array();
					unset( $query['context'] );
					$query['context'] = $this->resolve_user_context( $input );
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
				'input_schema'        => $schemas->get_item( 'Unique identifier for the user.' ),
				'execute_callback'    => function ( $input ) {
					$user_id = (int) $input['id'];
					$root    = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_route_unavailable_for_resource( 'users' );
					}
					$request = new \WP_REST_Request( 'GET', RestApiUtils::build_item_route( $root, $user_id ) );
					$request->set_query_params( array( 'context' => $this->resolve_user_context( $input ) ) );
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
				'input_schema'        => $schemas->creatable(),
				'execute_callback'    => function ( $input ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_route_unavailable_for_resource( 'users' );
					}

					$request = new \WP_REST_Request( 'POST', $root );
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
				'input_schema'        => $schemas->update_with_id( 'Unique identifier for the user.' ),
				'execute_callback'    => function ( $input ) {
					$user_id = (int) $input['id'];
					unset( $input['id'], $input['context'] );

					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_route_unavailable_for_resource( 'users' );
					}

					$request = new \WP_REST_Request( 'PUT', RestApiUtils::build_item_route( $root, $user_id ) );
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
				'input_schema'        => $schemas->delete_with_id(
					RestControllerSchemaBuilder::user_delete_endpoint_args(),
					array( 'id', 'reassign' ),
					'Unique identifier for the user.'
				),
				'execute_callback'    => function ( $input ) {
					$user_id = (int) $input['id'];
					$root    = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_route_unavailable_for_resource( 'users' );
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
				'input_schema'        => $schemas->context_only(),
				'execute_callback'    => function ( $input = null ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_route_unavailable_for_resource( 'users' );
					}
					$request = new \WP_REST_Request( 'GET', $root . '/me' );
					$request->set_query_params( array( 'context' => $this->resolve_user_context( is_array( $input ) ? $input : array() ) ) );
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
				'input_schema'        => $schemas->editable(),
				'execute_callback'    => function ( $input = null ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'users' );

					if ( ! $root ) {
						return blu_standardize_route_unavailable_for_resource( 'users' );
					}

					$request = new \WP_REST_Request( 'PUT', $root . '/me' );
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

	/**
	 * Default REST context for user read/write calls.
	 *
	 * WordPress omits sensitive fields (e.g. email) unless context is `edit`.
	 * Callers may override by passing `context` in the input schema.
	 *
	 * @param array<string, mixed> $input Ability input.
	 *
	 * @return string REST context query value.
	 */
	private function resolve_user_context( array $input = array() ): string {
		if ( isset( $input['context'] ) && is_string( $input['context'] ) && '' !== $input['context'] ) {
			return $input['context'];
		}

		return 'edit';
	}
}
