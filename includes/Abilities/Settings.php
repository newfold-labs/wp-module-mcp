<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Settings abilities for WordPress site settings.
 */
class Settings {

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
		// Get settings
		blu_register_ability(
			'blu/get-general-settings',
			array(
				'label'               => 'Get General Settings',
				'description'         => 'Get WordPress general site settings',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/settings' );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
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
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'                  => array(
							'type'        => 'string',
							'description' => 'Site title',
						),
						'description'            => array(
							'type'        => 'string',
							'description' => 'Site tagline/description',
						),
						'timezone_string'        => array(
							'type'        => 'string',
							'description' => 'Site timezone',
						),
						'date_format'            => array(
							'type'        => 'string',
							'description' => 'Date format',
						),
						'time_format'            => array(
							'type'        => 'string',
							'description' => 'Time format',
						),
						'start_of_week'          => array(
							'type'        => 'integer',
							'description' => 'Start of week (0 = Sunday, 1 = Monday, etc.)',
						),
						'language'               => array(
							'type'        => 'string',
							'description' => 'Site language',
						),
						'use_smilies'            => array(
							'type'        => 'boolean',
							'description' => 'Convert emoticons to graphics',
						),
						'default_category'       => array(
							'type'        => 'integer',
							'description' => 'Default post category',
						),
						'default_post_format'    => array(
							'type'        => 'string',
							'description' => 'Default post format',
						),
						'posts_per_page'         => array(
							'type'        => 'integer',
							'description' => 'Number of posts to show per page',
						),
						'default_comment_status' => array(
							'type'        => 'string',
							'description' => 'Default comment status (open/closed)',
						),
						'default_ping_status'    => array(
							'type'        => 'string',
							'description' => 'Default ping status (open/closed)',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$request = new \WP_REST_Request( 'POST', '/wp/v2/settings' );
					if ( $input ) {
						$request->set_body_params( $input );
					}
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
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
