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
						return blu_standardize_route_unavailable_for_resource( 'settings' );
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
						return blu_standardize_route_unavailable_for_resource( 'settings' );
					}

					$request = new \WP_REST_Request( 'POST', $root );

					if ( $input ) {
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
