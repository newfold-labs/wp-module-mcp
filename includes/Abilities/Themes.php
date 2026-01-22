<?php
/**
 * Themes Abilities
 *
 * Provides abilities for managing WordPress Themes
 *
 * @package BLU
 */

declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Themes class
 *
 * Registers abilities for getting the active WordPress theme.
 */
class Themes {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register all global styles abilities
	 *
	 * @return void
	 */
	private function register_abilities(): void {
		$this->register_get_active_theme();
	}

	/**
	 * Register ability to get the currently active theme information
	 *
	 * @return void
	 */
	private function register_get_active_theme(): void {
		blu_register_ability(
			'blu/get-active-theme',
			array(
				'label'               => 'Get Active Theme',
				'description'         => 'Get the active theme information',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'active' ),
							'description' => 'Theme status filter',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/themes' );

					if ( ! $input ) {
						$input = array( 'status' => 'active' );
					}
					$request->set_query_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}
}
