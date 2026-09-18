<?php
declare( strict_types=1 );

namespace BLU\Abilities;

use BLU\RestControllerSchema\RestControllerSchemaBuilder;

/**
 * Settings abilities for WordPress site settings.
 */
class Settings {

	/**
	 * Base REST API namespace used to discover the latest versioned namespace.
	 *
	 * @var string
	 */
	private $base_namespace = 'wp';

	/**
	 * Constructor - registers settings abilities.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register settings abilities.
	 */
	private function register_abilities(): void {
		$schemas = RestControllerSchemaBuilder::for_settings();

		// Get settings
		blu_register_ability(
			'blu/get-general-settings',
			array(
				'label'               => 'Get General Settings',
				'description'         => 'Get WordPress general site settings',
				'category'            => 'blu-mcp',
				'input_schema'        => $schemas->empty_object(),
				'execute_callback'    => function ( $input = null ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'settings' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for settings not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);
					}

					$request = new \WP_REST_Request( 'GET', $root );
					if ( is_array( $input ) ) {
						$request->set_query_params( $input );
					}

					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Update settings
		blu_register_ability(
			'blu/update-general-settings',
			array(
				'label'               => 'Update General Settings',
				'description'         => 'Update WordPress general site settings',
				'category'            => 'blu-mcp',
				'input_schema'        => $schemas->editable(),
				'execute_callback'    => function ( $input = null ) {
					$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'settings' );

					if ( ! $root ) {
						return blu_standardize_rest_response(
							new \WP_Error(
								400,
								'A valid route for settings not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
							)
						);
					}

					$request = new \WP_REST_Request( 'POST', $root );

					if ( $input ) {
						// LLMs occasionally pass the WP option names
						// (`blogname`/`blogdescription`) instead of the
						// REST endpoint's param names (`title`/`description`).
						// The call is otherwise valid so the REST API returns
						// 200 with no fields updated — a silent no-op the LLM
						// then reports as success. Alias them here so the
						// call lands either way.
						if ( isset( $input['blogname'] ) && ! isset( $input['title'] ) ) {
							$input['title'] = $input['blogname'];
							unset( $input['blogname'] );
						}
						if ( isset( $input['blogdescription'] ) && ! isset( $input['description'] ) ) {
							$input['description'] = $input['blogdescription'];
							unset( $input['blogdescription'] );
						}
						$request->set_body_params( $input );
					}
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
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
