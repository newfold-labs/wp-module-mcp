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
			'blu/extract-image-colors',
			array(
				'label'               => 'Extract Image Colors',
				'description'         => 'Analyze an uploaded image and return its dominant colors as hex codes. Use this when the user asks to apply a logo or image color to the site\'s global styles (accent color, primary color, brand color, etc.).',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'image_url'  => array(
							'type'        => 'string',
							'description' => 'URL of the image to analyze. Must be a URL on this site (e.g. from the [User uploaded images] list).',
						),
						'max_colors' => array(
							'type'        => 'integer',
							'description' => 'Maximum number of dominant colors to return. Defaults to 5.',
							'minimum'     => 1,
							'maximum'     => 10,
						),
					),
					'required'   => array( 'image_url' ),
				),
				'execute_callback'    => array( $this, 'extract_colors' ),
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		blu_register_ability(
			'blu/edit-image',
			array(
				'label'               => 'Edit Image',
				'description'         => 'Generatively edit an existing image: add or remove objects, change colours, adjust lighting, apply styles, or transform any aspect of the photo. Requires a source_url pointing to the current image. Use this instead of blu/generate-image whenever an existing image is available and the user wants to modify, change, or enhance it.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'prompt'        => array(
							'type'        => 'string',
							'description' => 'What to change in the image — describe the desired result or the modification (e.g. "add a dog under the table", "make the background white", "change the shirt to red"). Max 1000 characters.',
							'maxLength'   => 1000,
						),
						'source_url'    => array(
							'type'        => 'string',
							'description' => 'URL of the existing image to edit. Must be an accessible HTTP/HTTPS URL.',
						),
						'reference_url' => array(
							'type'        => 'string',
							'description' => 'URL of a second reference image to blend or combine with the source. Use this when the user has uploaded an image and wants to merge, blend, or apply elements from it onto the source. Must be an accessible HTTP/HTTPS URL.',
						),
						'orientation'   => array(
							'type'        => 'string',
							'description' => 'The orientation of the image. Defaults to landscape.',
							'enum'        => array( 'landscape', 'portrait', 'square' ),
						),
						'width'         => array(
							'type'        => 'integer',
							'description' => 'The width of the image. Defaults to 1024.',
							'maximum'     => 1920,
							'minimum'     => 1,
						),
						'height'        => array(
							'type'        => 'integer',
							'description' => 'The height of the image. Defaults to 1024.',
							'maximum'     => 1080,
							'minimum'     => 1,
						),
						'quality'       => array(
							'type'        => 'integer',
							'description' => 'The quality of the image. Defaults to 85.',
							'maximum'     => 100,
							'minimum'     => 1,
						),
						'background'    => array(
							'type'        => 'string',
							'description' => 'The background of the image. Defaults to auto.',
							'enum'        => array( 'transparent', 'opaque', 'auto' ),
						),
						'trim'          => array(
							'type'        => 'boolean',
							'description' => 'Whether to trim the image. Defaults to false.',
						),
						'fit'           => array(
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
	 * Extract dominant colors from an image using GD.
	 *
	 * @param array $input Tool input parameters.
	 * @return array Standardized ability response.
	 */
	public function extract_colors( array $input ): array {
		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return blu_prepare_ability_response( 500, 'GD image library is not available on this server.' );
		}

		$raw_url = (string) ( $input['image_url'] ?? '' );
		$url     = esc_url_raw( $raw_url );
		if ( empty( $url ) || ! filter_var( $raw_url, FILTER_VALIDATE_URL ) ) {
			return blu_prepare_ability_response( 400, 'A valid image_url is required.' );
		}

		if ( ! $this->is_allowed_source_url( $url ) ) {
			return blu_prepare_ability_response( 400, 'image_url is not allowed.' );
		}

		$image_payload = $this->fetch_source_image( $url );
		if ( isset( $image_payload['error'] ) ) {
			return blu_prepare_ability_response( (int) $image_payload['status'], (string) $image_payload['error'] );
		}

		$max_colors = min( max( (int) ( $input['max_colors'] ?? 5 ), 1 ), 10 );
		$colors     = $this->analyze_image_colors( $image_payload['content'], $max_colors );

		if ( empty( $colors ) ) {
			return blu_prepare_ability_response(
				200,
				array(
					'colors'  => array(),
					'message' => 'No distinct colors found. The image may be mostly white, black, or transparent.',
				)
			);
		}

		return blu_prepare_ability_response(
			200,
			array(
				'dominant' => $colors[0]['hex'],
				'colors'   => $colors,
				'message'  => 'Dominant color: ' . $colors[0]['hex'] . '. Use this hex value with blu/update-global-styles to apply it as the accent or primary color.',
			)
		);
	}

	/**
	 * Sample pixels from image binary data and return dominant non-background colors.
	 *
	 * Resizes to an 80×80 thumbnail for efficient sampling, skips transparent,
	 * near-white, and near-black pixels, then quantizes to 32-step bins to group
	 * similar shades before ranking by frequency.
	 *
	 * @param string $content    Raw image binary content.
	 * @param int    $max_colors Maximum colors to return.
	 * @return array Array of {hex, percentage} sorted by frequency descending.
	 */
	private function analyze_image_colors( string $content, int $max_colors ): array {
		$img = @imagecreatefromstring( $content ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		if ( false === $img ) {
			return array();
		}

		// Convert palette-mode images (indexed PNG/GIF) to truecolor so that
		// GD maps the transparent colour index to a real alpha channel before
		// resampling — otherwise transparent pixels blend as opaque colours.
		if ( ! imageistruecolor( $img ) ) {
			imagepalettetotruecolor( $img );
			imagealphablending( $img, false );
			imagesavealpha( $img, true );
		}

		$sample_w = 80;
		$sample_h = 80;
		$thumb    = imagecreatetruecolor( $sample_w, $sample_h );

		// Preserve transparency during resampling.
		imagealphablending( $thumb, false );
		imagesavealpha( $thumb, true );
		$transparent = imagecolorallocatealpha( $thumb, 255, 255, 255, 127 );
		imagefill( $thumb, 0, 0, $transparent );
		imagealphablending( $thumb, true );

		imagecopyresampled( $thumb, $img, 0, 0, 0, 0, $sample_w, $sample_h, imagesx( $img ), imagesy( $img ) );
		imagedestroy( $img );

		$color_counts = array();
		$total        = 0;

		for ( $x = 0; $x < $sample_w; $x++ ) {
			for ( $y = 0; $y < $sample_h; $y++ ) {
				$rgba  = imagecolorat( $thumb, $x, $y );
				$alpha = ( $rgba >> 24 ) & 0x7F;

				// Skip mostly-transparent pixels (127 = fully transparent in GD).
				if ( $alpha > 90 ) {
					continue;
				}

				$r = ( $rgba >> 16 ) & 0xFF;
				$g = ( $rgba >> 8 ) & 0xFF;
				$b = $rgba & 0xFF;

				// Skip near-white (typical image backgrounds).
				if ( $r > 230 && $g > 230 && $b > 230 ) {
					continue;
				}

				// Skip near-black.
				if ( $r < 20 && $g < 20 && $b < 20 ) {
					continue;
				}

				// Quantize to 32-step bins to group perceptually similar shades.
				$qr  = (int) round( $r / 32 ) * 32;
				$qg  = (int) round( $g / 32 ) * 32;
				$qb  = (int) round( $b / 32 ) * 32;
				$key = sprintf( '%02x%02x%02x', min( 255, $qr ), min( 255, $qg ), min( 255, $qb ) );

				$color_counts[ $key ] = ( $color_counts[ $key ] ?? 0 ) + 1;
				++$total;
			}
		}

		imagedestroy( $thumb );

		if ( 0 === $total || empty( $color_counts ) ) {
			return array();
		}

		arsort( $color_counts );

		$colors = array();
		foreach ( array_slice( $color_counts, 0, $max_colors, true ) as $hex => $count ) {
			$colors[] = array(
				'hex'        => '#' . $hex,
				'percentage' => (int) round( $count / $total * 100 ),
			);
		}

		return $colors;
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
		$files = array(
			array(
				'field'    => 'images[]',
				'filename' => $image_payload['filename'],
				'content'  => $image_payload['content'],
				'mime'     => $image_payload['mime'],
			),
		);

		// If a reference image is provided, fetch and append it as a second image.
		$raw_reference_url = (string) ( $input['reference_url'] ?? '' );
		if ( ! empty( $raw_reference_url ) ) {
			$reference_url = esc_url_raw( $raw_reference_url );
			if ( filter_var( $raw_reference_url, FILTER_VALIDATE_URL ) && $this->is_allowed_source_url( $reference_url ) ) {
				$reference_payload = $this->fetch_source_image( $reference_url );
				if ( is_array( $reference_payload ) && isset( $reference_payload['content'] ) ) {
					$files[] = array(
						'field'    => 'images[]',
						'filename' => $reference_payload['filename'],
						'content'  => $reference_payload['content'],
						'mime'     => $reference_payload['mime'],
					);
				}
			}
		}

		$multipart_body = $this->build_multipart_body( $fields, $files );
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
		$allowed_mimes = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);

		// For local-site URLs, read directly from the filesystem to avoid loopback
		// HTTP issues (SSL certificate problems, blocked requests on local dev envs).
		$url_host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		if ( $url_host === $site_host ) {
			$url_path = wp_parse_url( $url, PHP_URL_PATH );
			if ( ! is_string( $url_path ) ) {
				return array(
					'status' => 400,
					'error'  => __( 'Invalid local URL path.', 'wp-module-mcp' ),
				);
			}
			$abs_root = realpath( ABSPATH );
			$abs_file = realpath( $abs_root . '/' . ltrim( $url_path, '/' ) );
			if ( false === $abs_file || strpos( $abs_file, $abs_root ) !== 0 ) {
				return array(
					'status' => 400,
					'error'  => __( 'Local file path is outside the WordPress root.', 'wp-module-mcp' ),
				);
			}
			if ( ! is_file( $abs_file ) ) {
				return array(
					'status' => 404,
					'error'  => __( 'Local source image not found.', 'wp-module-mcp' ),
				);
			}
			$content = file_get_contents( $abs_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $content || '' === $content ) {
				return array(
					'status' => 400,
					'error'  => __( 'Could not read local source image.', 'wp-module-mcp' ),
				);
			}
			$max_bytes = 10 * 1024 * 1024;
			if ( strlen( $content ) > $max_bytes ) {
				return array(
					'status' => 400,
					'error'  => __( 'Source image exceeds 10 MB limit.', 'wp-module-mcp' ),
				);
			}
			$mime = is_callable( 'mime_content_type' ) ? strtolower( mime_content_type( $abs_file ) ) : '';
			if ( ! isset( $allowed_mimes[ $mime ] ) ) {
				return array(
					'status' => 400,
					'error'  => __( 'Unsupported source image type.', 'wp-module-mcp' ),
				);
			}
			return array(
				'filename' => basename( $abs_file ),
				'content'  => $content,
				'mime'     => $mime,
			);
		}

		// Remote URL — fetch via HTTP.
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
				/* translators: %s: error message from wp_remote_get */
				'error'  => sprintf( __( 'Unable to fetch source image: %s', 'wp-module-mcp' ), $response->get_error_message() ),
			);
		}
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return array(
				'status' => 400,
				/* translators: %d: HTTP status code */
				'error'  => sprintf( __( 'Unable to fetch source image (HTTP %d).', 'wp-module-mcp' ), $status_code ),
			);
		}
		$content = wp_remote_retrieve_body( $response );
		if ( '' === $content ) {
			return array(
				'status' => 400,
				'error'  => __( 'Source image is empty.', 'wp-module-mcp' ),
			);
		}
		$max_bytes = 10 * 1024 * 1024;
		if ( strlen( $content ) > $max_bytes ) {
			return array(
				'status' => 400,
				'error'  => __( 'Source image exceeds 10 MB limit.', 'wp-module-mcp' ),
			);
		}
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		$mime         = is_string( $content_type ) ? strtolower( trim( explode( ';', $content_type )[0] ) ) : '';
		if ( ! isset( $allowed_mimes[ $mime ] ) ) {
			return array(
				'status' => 400,
				'error'  => __( 'Unsupported source image type.', 'wp-module-mcp' ),
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
