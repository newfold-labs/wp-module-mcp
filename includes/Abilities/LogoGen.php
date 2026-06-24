<?php

declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Logo generation ability using the AI platform service.
 */
class LogoGen {

	/**
	 * Constructor - registers logo generation ability.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register logo generation abilities.
	 */
	private function register_abilities(): void {
		blu_register_ability(
			'blu/set-logo-from-image',
			array(
				'label'               => 'Set Logo From Image',
				'description'         => 'Set an existing image URL as the site logo. Use this after optionally calling blu/edit-image to clean the image (e.g. remove background with background:transparent). Sideloads the image into the media library and sets it as the active site logo.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'source_url' => array(
							'type'        => 'string',
							'description' => 'URL of the image to use as the site logo. Can be a temp upload URL or a CDN URL returned by blu/edit-image.',
						),
					),
					'required'   => array( 'source_url' ),
				),
				'execute_callback'    => array( $this, 'set_from_image' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		blu_register_ability(
			'blu/regenerate-logo',
			array(
				'label'               => 'Regenerate Logo',
				'description'         => 'Generate or replace the site logo using AI. Use this for ANY logo-related request: "regenerate my logo", "generate a new logo", "change the logo", "update my logo", "create a logo", or similar. IMPORTANT: there is no media library UI available — you cannot open a file picker or ask the user to upload an image. This ability is the ONLY way to change the site logo. Compose the prompt from the site/brand name and any style or color preferences the user mentions. Required parameter: prompt (describe the logo: brand name, style, colors). Optional: subject_name (brand or site name), style (auto|lettermark|wordmark|combination|emblem|pictorial). Generates a new logo image, saves it to the media library, and sets it as the active site logo.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'prompt'       => array(
							'type'        => 'string',
							'description' => 'A detailed description of the logo to generate. Max 1000 characters.',
							'maxLength'   => 1000,
						),
						'subject_name' => array(
							'type'        => 'string',
							'description' => 'The name of the brand, company, or entity the logo is for. May come as an explicit brand name or a website title.',
							'maxLength'   => 100,
						),
						'style'        => array(
							'type'        => 'string',
							'description' => 'Logo style',
							'enum'        => array( 'auto', 'lettermark', 'wordmark', 'combination', 'emblem', 'pictorial' ),
							'default'     => 'auto',
						),
					),
					'required'   => array( 'prompt' ),
				),
				'execute_callback'    => array( $this, 'regenerate' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
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
	 * Request a logo from the AI platform, sideload it, and set as site_logo.
	 *
	 * @param array $input Tool input parameters.
	 * @return array Standardized ability response.
	 */
	public function regenerate( array $input ): array {
		// Extend PHP execution time for this long-running request.
		set_time_limit( 120 );

		$api_url = defined( 'NFD_AI_PLATFORM_URL' ) ? NFD_AI_PLATFORM_URL : 'https://ai-platform.hiive.cloud';

		// Get Hiive auth token — required by the ai-platform middleware.
		$hiive_token = '';
		if ( class_exists( '\NewfoldLabs\WP\Module\Data\HiiveConnection' ) ) {
			$hiive_token = \NewfoldLabs\WP\Module\Data\HiiveConnection::get_auth_token();
		}

		if ( empty( $hiive_token ) ) {
			return blu_prepare_ability_response( 401, __( 'Unable to retrieve Hiive authentication token for logo generation.', 'wp-module-mcp' ) );
		}

		$body = array(
			'prompt' => substr( $input['prompt'], 0, 1000 ),
		);

		if ( ! empty( $input['subject_name'] ) ) {
			$body['subject_name'] = $input['subject_name'];
		}

		$body['style'] = ! empty( $input['style'] ) ? $input['style'] : 'auto';

		$response = wp_remote_post(
			trailingslashit( $api_url ) . 'api/v1/imagegen/logo',
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
				return blu_prepare_ability_response( 504, __( 'Logo generation timed out.', 'wp-module-mcp' ) );
			}
			return blu_prepare_ability_response(
				502,
				/* translators: %s: error message */
				sprintf( __( 'Logo generation service unavailable: %s', 'wp-module-mcp' ), $message )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code < 200 || $status_code >= 300 ) {
			return blu_prepare_ability_response(
				$status_code,
				/* translators: %d: HTTP status code */
				sprintf( __( 'Logo generation failed with status %d.', 'wp-module-mcp' ), $status_code )
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $data['url'] ) ) {
			return blu_prepare_ability_response( 500, __( 'No image URL in response.', 'wp-module-mcp' ) );
		}

		$cdn_url = $data['url'];

		$desc = __( 'Site logo (AI generated)', 'wp-module-mcp' );
		if ( ! empty( $input['subject_name'] ) ) {
			$desc = sprintf(
				/* translators: %s: brand or site name for the logo attachment title/description */
				__( 'Site logo (AI generated) — %s', 'wp-module-mcp' ),
				substr( (string) $input['subject_name'], 0, 100 )
			);
		}

		return $this->sideload_and_set_logo( $cdn_url, $desc );
	}

	/**
	 * Set an existing image URL as the site logo.
	 *
	 * @param array $input Tool input parameters.
	 * @return array Standardized ability response.
	 */
	public function set_from_image( array $input ): array {
		set_time_limit( 120 );

		$raw_url = (string) ( $input['source_url'] ?? '' );
		$url     = esc_url_raw( $raw_url );

		if ( empty( $url ) || ! filter_var( $raw_url, FILTER_VALIDATE_URL ) ) {
			return blu_prepare_ability_response( 400, __( 'A valid source_url is required.', 'wp-module-mcp' ) );
		}

		if ( ! $this->is_allowed_url( $url ) ) {
			return blu_prepare_ability_response( 400, __( 'source_url is not allowed.', 'wp-module-mcp' ) );
		}

		$desc = __( 'Site logo (uploaded)', 'wp-module-mcp' );

		return $this->sideload_and_set_logo( $url, $desc );
	}

	/**
	 * Sideload an image URL into the media library and set it as the site logo.
	 *
	 * @param string $url  Image URL to sideload.
	 * @param string $desc Attachment description/title.
	 * @return array Standardized ability response.
	 */
	private function sideload_and_set_logo( string $url, string $desc ): array {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $url, 0, $desc, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			return blu_prepare_ability_response( 500, $attachment_id->get_error_message() );
		}

		$attachment_id = (int) $attachment_id;
		update_option( 'site_logo', $attachment_id );

		$local_url = wp_get_attachment_url( $attachment_id );

		return blu_prepare_ability_response(
			200,
			array(
				'message'       => __( 'Site logo updated.', 'wp-module-mcp' ),
				'attachment_id' => $attachment_id,
				'url'           => $local_url ?: $url,
			)
		);
	}

	/**
	 * SSRF guard — allow local site, hiive.cloud CDN, and unsplash only.
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	private function is_allowed_url( string $url ): bool {
		$host      = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$site_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );

		return $host === $site_host
			|| preg_match( '/(^|\.)hiive\.cloud$/', $host )
			|| preg_match( '/(^|\.)unsplash\.com$/', $host );
	}
}
