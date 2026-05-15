<?php

declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Image generation ability using the AI platform service.
 */
class ImageGen {

	/**
	 * Constructor - registers image generation ability.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register image generation abilities.
	 */
	private function register_abilities(): void {
		blu_register_ability(
			'blu/generate-image',
			array(
				'label'               => 'Generate Image',
				'description'         => 'Generate an AI image from a text prompt. Returns a CDN URL to the generated image. Use when the user requests custom imagery for their page.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'prompt'      => array(
							'type'        => 'string',
							'description' => 'A detailed description of the image to generate. Max 1000 characters.',
							'maxLength'   => 1000,
						),
						'orientation' => array(
							'type'        => 'string',
							'description' => 'Image orientation. Defaults to landscape.',
							'enum'        => array( 'landscape', 'portrait', 'square' ),
						),
						'width'       => array(
							'type'        => 'integer',
							'description' => 'Desired width in pixels. Max 1920.',
							'maximum'     => 1920,
							'minimum'     => 1,
						),
						'height'      => array(
							'type'        => 'integer',
							'description' => 'Desired height in pixels. Max 1080.',
							'maximum'     => 1080,
							'minimum'     => 1,
						),
					),
					'required'   => array( 'prompt' ),
				),
				'execute_callback'    => array( $this, 'generate' ),
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);
	}

	/**
	 * Generate an image via the AI platform.
	 *
	 * @param array $input Tool input parameters.
	 * @return array Standardized ability response.
	 */
	public function generate( array $input ): array {
		// Extend PHP execution time for this long-running request.
		set_time_limit( 120 );

		$api_url = defined( 'NFD_AI_PLATFORM_URL' ) ? NFD_AI_PLATFORM_URL : 'https://ai-platform.hiive.cloud';

		// Get Hiive auth token — required by the ai-platform middleware.
		$hiive_token = '';
		if ( class_exists( '\NewfoldLabs\WP\Module\Data\HiiveConnection' ) ) {
			$hiive_token = \NewfoldLabs\WP\Module\Data\HiiveConnection::get_auth_token();
		}

		if ( empty( $hiive_token ) ) {
			return blu_prepare_ability_response( 401, 'Unable to retrieve Hiive authentication token for image generation.' );
		}

		$body = array(
			'prompt' => substr( $input['prompt'], 0, 1000 ),
		);

		if ( ! empty( $input['orientation'] ) ) {
			$body['orientation'] = $input['orientation'];
		}
		if ( ! empty( $input['width'] ) ) {
			$body['width'] = min( (int) $input['width'], 1920 );
		}
		if ( ! empty( $input['height'] ) ) {
			$body['height'] = min( (int) $input['height'], 1080 );
		}

		$response = wp_remote_post(
			trailingslashit( $api_url ) . 'api/v1/imagegen/image',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $hiive_token,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 90,
			)
		);

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			if ( false !== strpos( $message, 'timed out' ) || false !== strpos( $message, 'cURL error 28' ) ) {
				return blu_prepare_ability_response( 504, 'Image generation timed out' );
			}
			return blu_prepare_ability_response( 502, 'Image generation service unavailable: ' . $message );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return blu_prepare_ability_response( $status_code, 'Image generation failed with status ' . $status_code );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['url'] ) ) {
			return blu_prepare_ability_response( 500, 'No image URL in response' );
		}

		return blu_prepare_ability_response( 200, array( 'url' => $data['url'] ) );
	}
}
