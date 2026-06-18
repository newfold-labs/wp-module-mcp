<?php

declare(strict_types=1);

namespace BLU\Abilities;

/**
 * Image edit ability using the AI platform service.
 */
class ImageEdit {


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
			'blu/edit-image',
			array(
				'label'               => 'Edit Image',
				'description'         => 'Generatively edit an existing image: add or remove objects, change colours, adjust lighting, apply styles, or transform any aspect of the photo. Requires a source_url pointing to the current image. Use this instead of blu/generate-image whenever an existing image is available and the user wants to modify, change, or enhance it.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'prompt'      => array(
							'type'        => 'string',
							'description' => 'What to change in the image — describe the desired result or the modification (e.g. "add a dog under the table", "make the background white", "change the shirt to red"). Max 1000 characters.',
							'maxLength'   => 1000,
						),
						'source_url'  => array(
							'type'        => 'string',
							'description' => 'URL of the existing image to edit. Must be an accessible HTTP/HTTPS URL.',
						),
						'orientation' => array(
							'type'        => 'string',
							'description' => 'The orientation of the image. Defaults to landscape.',
							'enum'        => array( 'landscape', 'portrait', 'square' ),
						),
						'width'       => array(
							'type'        => 'integer',
							'description' => 'The width of the image. Defaults to 1024.',
							'maximum'     => 1920,
							'minimum'     => 1,
						),
						'height'      => array(
							'type'        => 'integer',
							'description' => 'The height of the image. Defaults to 1024.',
							'maximum'     => 1080,
							'minimum'     => 1,
						),
						'quality'     => array(
							'type'        => 'integer',
							'description' => 'The quality of the image. Defaults to 85.',
							'maximum'     => 100,
							'minimum'     => 1,
						),
						'background'  => array(
							'type'        => 'string',
							'description' => 'The background of the image. Defaults to auto.',
							'enum'        => array( 'transparent', 'opaque', 'auto' ),
						),
						'trim'        => array(
							'type'        => 'boolean',
							'description' => 'Whether to trim the image. Defaults to false.',
						),
						'fit'         => array(
							'type'        => 'string',
							'description' => 'The fit of the image. Defaults to cover.',
							'enum'        => array( 'cover', 'contain', 'fill', 'none', 'scale-down' ),
						),
					),
					'required'   => array( 'prompt', 'source_url' ),
				),
				'execute_callback'    => array( $this, 'edit' ),
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
	 * Edit an image via the AI platform.
	 *
	 * @param array $input Tool input parameters.
	 * @return array Standardized ability response.
	 */
	public function edit( array $input ): array {
		// Extend PHP execution time for this long-running request.
		set_time_limit( 120 );

		$raw_source_url = (string) ( $input['source_url'] ?? '' );
		$source_url     = esc_url_raw( $raw_source_url );
		if ( empty( $source_url ) || ! filter_var( $raw_source_url, FILTER_VALIDATE_URL ) ) {
			return blu_prepare_ability_response( 400, 'A valid source_url is required.' );
		}

		if ( ! $this->is_allowed_source_url( $source_url ) ) {
			return blu_prepare_ability_response( 400, 'source_url is not allowed.' );
		}

		$image_payload = $this->fetch_source_image( $source_url );
		if ( is_array( $image_payload ) && isset( $image_payload['error'] ) ) {
			return blu_prepare_ability_response( $image_payload['status'], $image_payload['error'] );
		}

		$api_url = defined( 'NFD_AI_PLATFORM_URL' ) ? NFD_AI_PLATFORM_URL : 'https://ai-platform.hiive.cloud';

		// Get Hiive auth token — required by the ai-platform middleware.
		$hiive_token = '';
		if ( class_exists( '\NewfoldLabs\WP\Module\Data\HiiveConnection' ) ) {
			$hiive_token = \NewfoldLabs\WP\Module\Data\HiiveConnection::get_auth_token();
		}

		if ( empty( $hiive_token ) ) {
			return blu_prepare_ability_response( 401, 'Unable to retrieve Hiive authentication token for image generation.' );
		}

		$fields = array(
			'prompt' => substr( (string) $input['prompt'], 0, 1000 ),
		);
		if ( ! empty( $input['orientation'] ) ) {
			$fields['orientation'] = $input['orientation'];
		}
		if ( ! empty( $input['background'] ) ) {
			$fields['background'] = $input['background'];
		}
		if ( isset( $input['trim'] ) ) {
			$fields['trim'] = $input['trim'] ? '1' : '0';
		}
		if ( ! empty( $input['width'] ) ) {
			$fields['width'] = (string) min( (int) $input['width'], 1920 );
		}
		if ( ! empty( $input['height'] ) ) {
			$fields['height'] = (string) min( (int) $input['height'], 1080 );
		}
		if ( ! empty( $input['quality'] ) ) {
			$fields['quality'] = (string) min( max( (int) $input['quality'], 1 ), 100 );
		}
		if ( ! empty( $input['fit'] ) ) {
			$fields['fit'] = (string) $input['fit'];
		}
		$multipart_body = $this->build_multipart_body(
			$fields,
			array(
				array(
					'field'    => 'images[]',
					'filename' => $image_payload['filename'],
					'content'  => $image_payload['content'],
					'mime'     => $image_payload['mime'],
				),
			)
		);
		$response       = wp_remote_post(
			trailingslashit( $api_url ) . 'api/v1/imagegen/edit',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $hiive_token,
					'Accept'        => 'application/json',
					'Content-Type'  => 'multipart/form-data; boundary=' . $multipart_body['boundary'],
				),
				'body'    => $multipart_body['body'],
				'timeout' => 120,
			)
		);
		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			if ( false !== strpos( $message, 'timed out' ) || false !== strpos( $message, 'cURL error 28' ) ) {
				return blu_prepare_ability_response( 504, 'Image edit timed out' );
			}
			return blu_prepare_ability_response( 502, 'Image edit service unavailable: ' . $message );
		}
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_body = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_msg  = is_array( $error_body ) && ! empty( $error_body['message'] )
				? (string) $error_body['message']
				: 'Image edit failed with status ' . $status_code;
			return blu_prepare_ability_response( $status_code, $error_msg );
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['url'] ) ) {
			return blu_prepare_ability_response( 500, 'No image URL in response' );
		}
		// CDN URL only — no media library sideload here.
		return blu_prepare_ability_response( 200, array( 'url' => $data['url'] ) );
	}

	/**
	 * Allow local site URLs, hiive.cloud CDN / platform images or unsplash.com images only (SSRF guard).
	 *
	 * @param string $url The URL to check.
	 * @return bool True if the URL is allowed, false otherwise.
	 */
	private function is_allowed_source_url( $url ): bool {
		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['host'] ) ) {
			return false;
		}
		$host      = strtolower( $parsed['host'] );
		$site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		// local site URLs, hiive.cloud CDN / platform images (same domains onboarding cron sideloads) or unsplash.com images.
		if ( $host === $site_host || preg_match( '/(^|\.)hiive\.cloud$/', $host ) || preg_match( '/(^|\.)unsplash\.com$/', $host ) ) {
			return true;
		}
		return (bool) apply_filters( 'blu_mcp_allowed_image_edit_source_hosts', false, $host, $url );
	}


	/**
	 * Fetch source image bytes for forwarding to Laravel (not saved to media library).
	 *
	 * @param string $url The URL of the image to fetch.
	 *
	 * @return array
	 */
	private function fetch_source_image( string $url ): array {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 30,
				'redirection' => 3,
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'status' => 502,
				'error'  => 'Unable to fetch source image: ' . $response->get_error_message(),
			);
		}
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return array(
				'status' => 400,
				'error'  => 'Unable to fetch source image (HTTP ' . $status_code . ').',
			);
		}
		$content = wp_remote_retrieve_body( $response );
		if ( '' === $content ) {
			return array(
				'status' => 400,
				'error'  => 'Source image is empty.',
			);
		}
		$max_bytes = 10 * 1024 * 1024; // matches Laravel images.* max:10240 (KB)
		if ( strlen( $content ) > $max_bytes ) {
			return array(
				'status' => 400,
				'error'  => 'Source image exceeds 10MB limit.',
			);
		}
		$content_type  = wp_remote_retrieve_header( $response, 'content-type' );
		$mime          = is_string( $content_type ) ? strtolower( trim( explode( ';', $content_type )[0] ) ) : '';
		$allowed_mimes = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);
		if ( ! isset( $allowed_mimes[ $mime ] ) ) {
			return array(
				'status' => 400,
				'error'  => 'Unsupported source image type.',
			);
		}
		$path     = wp_parse_url( $url, PHP_URL_PATH );
		$basename = is_string( $path ) ? basename( $path ) : '';
		$filename = '' !== $basename ? $basename : 'source.' . $allowed_mimes[ $mime ];
		return array(
			'filename' => $filename,
			'content'  => $content,
			'mime'     => $mime,
		);
	}

	/**
	 * Build a multipart/form-data body for wp_remote_post.
	 *
	 * @param array $fields Fields to include in the multipart body.
	 * @param array $files Files to include in the multipart body.
	 *
	 * @return array
	 */
	private function build_multipart_body( array $fields, array $files ): array {
		$boundary = '----BluMcpFormBoundary' . wp_generate_password( 16, false );
		$body     = '';
		foreach ( $fields as $name => $value ) {
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $name . '"' . "\r\n\r\n";
			$body .= $value . "\r\n";
		}
		foreach ( $files as $file ) {
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="' . $file['field'] . '"; filename="' . $file['filename'] . '"' . "\r\n";
			$body .= 'Content-Type: ' . $file['mime'] . "\r\n\r\n";
			$body .= $file['content'] . "\r\n";
		}
		$body .= '--' . $boundary . '--' . "\r\n";
		return array(
			'boundary' => $boundary,
			'body'     => $body,
		);
	}
}
