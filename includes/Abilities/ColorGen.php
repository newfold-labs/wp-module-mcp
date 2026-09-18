<?php

declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Registers the blu/generate-color-palette MCP ability.
 * Calls the Hiive ai-platform colorgen endpoint to produce ready-to-apply
 * theme palettes from a text prompt.
 */
class ColorGen {

	/**
	 * Register color generation abilities.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register color generation abilities.
	 */
	private function register_abilities(): void {
		blu_register_ability(
			'blu/generate-color-palette',
			array(
				'label'               => 'Generate Color Palette',
				'description'         => 'Generate one or more color palettes from a text prompt using AI. Returns ready-to-apply palette objects whose keys match blu/update-global-styles slugs exactly (base, base-midtone, contrast, contrast-midtone, accent-1 … accent-6). After calling this ability, present the palettes to the user and — once they choose — apply the selected palette with blu/update-global-styles. Use for requests like "suggest a color palette", "give me some color options", "generate a palette for my brand", or when the user wants to explore color options before committing.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'prompt' => array(
							'type'        => 'string',
							'description' => 'Describe the desired palette mood, brand, or style. Examples: "calm wellness brand with greens and earth tones", "bold tech startup, dark theme", "elegant bakery with warm pastels", "palette based on #E83E8C pink".',
						),
					),
					'required'   => array( 'prompt' ),
				),
				'execute_callback'    => array( $this, 'generate' ),
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);
	}

	/**
	 * Generate color palettes via the Hiive ai-platform colorgen endpoint.
	 *
	 * @param array $input Tool input parameters.
	 * @return array Standardized ability response.
	 */
	public function generate( array $input ): array {
		set_time_limit( 60 );

		$api_url = defined( 'NFD_AI_PLATFORM_URL' ) ? NFD_AI_PLATFORM_URL : 'https://ai-platform.hiive.cloud';

		$hiive_token = '';
		if ( class_exists( '\NewfoldLabs\WP\Module\Data\HiiveConnection' ) ) {
			$hiive_token = \NewfoldLabs\WP\Module\Data\HiiveConnection::get_auth_token();
		}

		if ( empty( $hiive_token ) ) {
			return blu_prepare_ability_response( 401, __( 'Unable to retrieve Hiive authentication token for palette generation.', 'wp-module-mcp' ) );
		}

		$prompt = substr( (string) ( $input['prompt'] ?? '' ), 0, 1000 );
		if ( '' === $prompt ) {
			return blu_prepare_ability_response( 400, __( 'A prompt is required.', 'wp-module-mcp' ) );
		}

		$body = array(
			'prompt' => $prompt,
			'locale' => get_locale(),
		);

		$response = wp_remote_post(
			trailingslashit( $api_url ) . 'api/v1/colorgen/palettes',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $hiive_token,
				),
				'body'      => wp_json_encode( $body ),
				'timeout'   => 45,
				'sslverify' => blu_ai_platform_sslverify(),
			)
		);

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			if ( false !== strpos( $message, 'timed out' ) || false !== strpos( $message, 'cURL error 28' ) ) {
				return blu_prepare_ability_response( 504, __( 'Palette generation timed out.', 'wp-module-mcp' ) );
			}
			return blu_prepare_ability_response(
				502,
				/* translators: %s: error message */
				sprintf( __( 'Palette generation service unavailable: %s', 'wp-module-mcp' ), $message )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return blu_prepare_ability_response(
				$status_code,
				/* translators: %d: HTTP status code */
				sprintf( __( 'Palette generation failed with status %d.', 'wp-module-mcp' ), $status_code )
			);
		}

		$palettes = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $palettes ) || empty( $palettes ) ) {
			return blu_prepare_ability_response( 500, __( 'No palettes returned by the generation service.', 'wp-module-mcp' ) );
		}

		// Convert underscore keys to hyphenated slugs so they match
		// blu/update-global-styles palette slug names directly.
		$normalized = array_map(
			function ( $palette ) {
				$out = array();
				foreach ( $palette as $key => $value ) {
					$out[ str_replace( '_', '-', $key ) ] = $value;
				}
				return $out;
			},
			$palettes
		);

		return blu_prepare_ability_response(
			200,
			array(
				'palettes' => $normalized,
				'message'  => sprintf(
					/* translators: %d: number of palettes */
					__( '%d color palette(s) generated. Present them to the user and apply the chosen one with blu/update-global-styles.', 'wp-module-mcp' ),
					count( $normalized )
				),
			)
		);
	}
}
