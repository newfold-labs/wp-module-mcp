<?php
/**
 * Global Styles Abilities
 *
 * Provides abilities for managing WordPress global styles (theme.json customizations).
 *
 * @package BLU
 */

declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Global Styles class
 *
 * Registers abilities for getting and updating WordPress global styles.
 * Global styles are part of the Full Site Editing (FSE) system and contain
 * theme.json configuration and user customizations.
 */
class GlobalStyles {

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
		$this->register_get_global_styles();
		$this->register_update_global_styles();
		$this->register_get_active_global_styles();
		$this->register_get_active_global_styles_id();
	}

	/**
	 * Register ability to get a specific global styles configuration
	 *
	 * @return void
	 */
	private function register_get_global_styles(): void {
		blu_register_ability(
			'blu/get-global-styles',
			array(
				'label'               => 'Get Global Styles',
				'description'         => 'Get a specific global styles configuration by ID. Returns theme.json settings and user customizations.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Global styles ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id      = intval( $input['id'] );
					$request = new \WP_REST_Request( 'GET', '/wp/v2/global-styles/' . $id );
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

	/**
	 * Register ability to update global styles
	 *
	 * @return void
	 */
	private function register_update_global_styles(): void {
		blu_register_ability(
			'blu/update-global-styles',
			array(
				'label'               => 'Update Global Styles',
				'description'         => 'Update a global styles configuration. Allows customization of theme.json settings including colors, typography, spacing, and more.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'       => array(
							'type'        => 'integer',
							'description' => 'Global styles ID to update',
						),
						'settings' => array(
							'type'        => 'object',
							'description' => 'Settings object containing theme.json configuration (colors, typography, layout, etc.)',
						),
						'styles'   => array(
							'type'        => 'object',
							'description' => 'Styles object containing CSS-like declarations for blocks and elements',
						),
						'title'    => array(
							'type'        => 'string',
							'description' => 'Title for the global styles configuration',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = intval( $input['id'] );
					$request = new \WP_REST_Request( 'POST', '/wp/v2/global-styles/' . $id );

					// Prepare the update data
					$data = array();
					if ( isset( $input['settings'] ) ) {
						$data['settings'] = $input['settings'];
					}
					if ( isset( $input['styles'] ) ) {
						$data['styles'] = $input['styles'];
					}
					if ( isset( $input['title'] ) ) {
						$data['title'] = $input['title'];
					}

					$request->set_body_params( $data );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_theme_options' ),
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
	 * Register ability to get active global styles for the current theme
	 *
	 * @return void
	 */
	private function register_get_active_global_styles(): void {
		blu_register_ability(
			'blu/get-active-global-styles',
			array(
				'label'               => 'Get Active Global Styles',
				'description'         => 'Get the currently active global styles configuration for the current theme. This is a convenience method to get the theme\'s active style variations.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function ( $input = null ) {

					$global_styles = wp_get_global_styles();

					return is_array( $global_styles ) && ! empty( $global_styles ) ? blu_prepare_ability_response( 200, $global_styles ) : blu_prepare_ability_response( 404, 'No active global styles found.' );
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

	/**
	 * Register ability to get active global styles for the current theme
	 *
	 * @return void
	 */
	private function register_get_active_global_styles_id(): void {
		blu_register_ability(
			'blu/get-active-global-styles-id',
			array(
				'label'               => 'Get Active Global Styles ID',
				'description'         => 'Get the currently active global styles ID for the current theme. This is used for get or update the global styles.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function ( $input = null ) {

					$id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();

					return is_int( $id ) && $id > 0 ? blu_prepare_ability_response( 200, array( 'id' => $id ) ) : blu_prepare_ability_response( 404, 'No active global styles ID found.' );
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
