<?php
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
				'description'         => 'Get a specific global styles configuration by ID. Returns theme.json settings and user customizations including colors, typography, and spacing. Only use this when you need to inspect the current styles — do NOT call this before blu/update-global-styles, which resolves the ID automatically.',
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
					$id       = intval( $input['id'] );
					$request  = new \WP_REST_Request( 'GET', '/wp/v2/global-styles/' . $id );
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
				'description'         => 'Update WordPress global styles (colors, typography, spacing) using theme.json format. Resolves the global styles ID automatically — do NOT call get-global-styles first. Use for site-wide palette, font, or spacing changes — NOT for individual block colors. COLOR SLUGS: base=Background, base-midtone=Background midtone, contrast=Text, contrast-midtone=Text midtone, accent-2=Primary, accent-5=Secondary. Only include slugs you are changing — others are preserved. MIDTONE COLORS: When changing base, also update base-midtone (a subtle step toward contrast). When changing contrast, also update contrast-midtone (a subtle step toward base). Light theme example: base=#ffffff, base-midtone=#f4f4f4, contrast=#000000, contrast-midtone=#323232. Dark theme example: base=#181818, base-midtone=#1C1C1C, contrast=#FFFFFF, contrast-midtone=#DADADA. ACCENT COLORS: Generate ALL 6 shades via HSL lightness from the base color: accent-1(-24%), accent-2(base), accent-3(+18%), accent-4(+28%), accent-5(+56%), accent-6(+63%). Example for deep blue #0B3D5B: accent-1=#062533, accent-2=#0B3D5B, accent-3=#1A5A7A, accent-4=#2A7399, accent-5=#6BAAC9, accent-6=#8DC1D9. DARK/LIGHT MODE: Only change base + base-midtone + contrast + contrast-midtone, NEVER modify accents. "base" must be white/near-white for light or dark grey for dark themes. "contrast" must be the opposite. VAGUE PALETTE REQUESTS: When user says "change colors" without specifying which, ask what colors or mood they want first — do not apply immediately. FORMAT: {"settings":{"color":{"palette":{"theme":[{"slug":"...","color":"#hex","name":"..."}]}}}}',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array(
							'type'        => 'object',
							'description' => 'Settings object in theme.json format.',
							'properties'  => array(
								'color'      => array(
									'type'        => 'object',
									'description' => 'Color settings.',
									'properties'  => array(
										'palette' => array(
											'type'        => 'object',
											'description' => 'Palette settings.',
											'properties'  => array(
												'theme' => array(
													'type' => 'array',
													'description' => 'Array of theme palette color entries. Only include slugs you are changing.',
													'items' => array(
														'type'       => 'object',
														'properties' => array(
															'slug'  => array(
																'type'        => 'string',
																'description' => 'Color slug: base, base-midtone, contrast, contrast-midtone, accent-1 through accent-6.',
															),
															'color' => array(
																'type'        => 'string',
																'description' => 'Hex color value (e.g. #0B3D5B).',
															),
															'name'  => array(
																'type'        => 'string',
																'description' => 'Display name for the color.',
															),
														),
														'required' => array( 'slug', 'color', 'name' ),
													),
												),
											),
										),
									),
								),
								'typography' => array(
									'type'        => 'object',
									'description' => 'Typography settings (fontFamilies, fontSizes).',
								),
								'spacing'    => array(
									'type'        => 'object',
									'description' => 'Spacing settings.',
								),
							),
						),
						'styles'   => array(
							'type'        => 'object',
							'description' => 'Styles object containing CSS-like declarations for root, elements, and blocks.',
						),
					),
					'required'   => array(),
				),
				'execute_callback'    => array( $this, 'execute_update_global_styles' ),
				'permission_callback' => fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
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
				'description'         => 'Get the currently active global styles for the current theme, including colors, typography, and spacing. Use for: "show colors", "what fonts are available", "current palette", "list styles". Returns the full styles object with all active customizations.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$global_styles = wp_get_global_styles();

					return is_array( $global_styles ) && ! empty( $global_styles )
						? blu_prepare_ability_response( 200, $global_styles )
						: blu_prepare_ability_response( 404, 'No active global styles found.' );
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
	 * Register ability to get active global styles ID for the current theme
	 *
	 * @return void
	 */
	private function register_get_active_global_styles_id(): void {
		blu_register_ability(
			'blu/get-active-global-styles-id',
			array(
				'label'               => 'Get Active Global Styles ID',
				'description'         => 'Get the active global styles post ID for the current theme. Only use this when you need the ID for reference — do NOT call this before blu/update-global-styles, which resolves the ID automatically. Returns an object with the numeric ID.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();

					return is_int( $id ) && $id > 0
						? blu_prepare_ability_response( 200, array( 'id' => $id ) )
						: blu_prepare_ability_response( 404, 'No active global styles ID found.' );
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
	 * Execute update global styles ability.
	 *
	 * Uses the WordPress REST API to update global styles.
	 *
	 * @param array $input The input parameters.
	 * @return array The result.
	 */
	public function execute_update_global_styles( array $input = array() ): array {
		if ( ! isset( $input['settings'] ) && ! isset( $input['styles'] ) ) {
			return blu_prepare_ability_response(
				400,
				array(
					'success' => false,
					'message' => 'Settings or styles object is required',
				)
			);
		}

		$global_styles_id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();

		if ( ! $global_styles_id ) {
			return blu_prepare_ability_response(
				500,
				array(
					'success' => false,
					'message' => 'Could not find global styles post',
				)
			);
		}

		$request = new \WP_REST_Request( 'POST', '/wp/v2/global-styles/' . $global_styles_id );

		// Prepare the update data.
		$data = array();
		if ( isset( $input['settings'] ) ) {
			$data['settings'] = $input['settings'];
		}
		if ( isset( $input['styles'] ) ) {
			$data['styles'] = $input['styles'];
		}

		$request->set_body_params( $data );
		$response = rest_do_request( $request );

		return blu_standardize_rest_response( $response );
	}
}
