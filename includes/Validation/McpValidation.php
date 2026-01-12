<?php

declare( strict_types=1 );

namespace BLU\Validation;

use WP_Error;
use BLU\Validation\HiiveProductVerifier;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Validation class for Blu MCP.
 */
class McpValidation {

	/**
	 * Bearer token pattern.
	 *
	 * @var string
	 */
	private const BEARER_TOKEN_PATTERN = '/^Bearer\s+(\S+)$/i';

	/**
	 * URL to fetch the public key for JWT validation.
	 *
	 * @var string
	 */
	private const CF_UJWT_PUBLIC_KEY_URL = 'https://cdn.hiive.space/jwt-public-key.pem';

	/**
	 * The request object.
	 *
	 * @var \WP_REST_Request
	 */
	private $request;

	/**
	 * Initializes the class
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return void
	 */
	public function __construct( \WP_REST_Request $request ) {
		$this->request = $request;
	}

	/**
	 * Check if the request is authenticated.
	 *
	 * @throws \Exception If authentication fails.
	 * @return bool True if authenticated, false if not.
	 */
	public function is_authenticated(): bool {
		try {

			// Check if the user has already been authenticated.
			if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
				return true;
			}

			// Otherwise, check for JWT token in the Authorization header.
			$auth_header = $this->get_authorization_header();

			// Bail early if no auth header is present.
			if ( empty( $auth_header ) ) {
				throw new \Exception( 'Authorization header is missing.' );
			}

			// Extract the token from the auth header.
			$token = $this->extract_bearer_token( $auth_header );

			// Bail early if no token is present.
			if ( empty( $token ) ) {
				throw new \Exception( 'Bearer token is missing.' );
			}

			// Validate the token and return the result.
			return $this->is_valid_token( $token );

		} catch ( \Exception $e ) {
			return false;
		}
	}

	/**
	 * Get Authorization header from request.
	 *
	 * @return string|null
	 */
	private function get_authorization_header(): ?string {
		return $this->request->get_header( 'Authorization' );
	}

	/**
	 * Extract the Bearer token from the authorization header.
	 *
	 * @param string $auth_header Authorization header value.
	 *
	 * @return string|null Token if found, null otherwise.
	 */
	private function extract_bearer_token( string $auth_header ): ?string {
		if ( preg_match( self::BEARER_TOKEN_PATTERN, $auth_header, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Validate the JWT token.
	 *
	 * @param string $token The JWT token to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 *
	 * @throws \Exception If token validation fails.
	 */
	private function is_valid_token( string $token ): bool {

		// Bail early if the token is not in JWT format.
		if ( strpos( $token, '.' ) === false ) {
			throw new \Exception( 'Invalid JWT token.' );
		}

		$public_key = $this->get_public_key();

		$decoded = JWT::decode( $token, new Key( $public_key, 'RS256' ) );

		$user_id = null;

		if ( ! isset( $decoded->aud ) ) {
			throw new \Exception( 'Token validation failed. The audience is invalid.' );
		}

		if ( ! isset( $decoded->iss ) || 'jarvis-jwt' !== $decoded->iss ) {
			throw new \Exception( 'Token validation failed. The iss is invalid.' );
		}

		$sub = $decoded->sub ?? null;
		if ( null === $sub ) {
			throw new \Exception( 'Token validation failed. The sub claim is missing.' );
		} else {
			$sub_parts = explode( ':', $sub );
			if ( ! empty( $sub_parts ) ) {
				$user_id = end( $sub_parts );
			}
		}

		if ( null === $user_id ) {
			throw new \Exception( 'Token validation failed. The user ID is missing.' );
		}

		// Call the Hiive product verifier.
		$response = HiiveProductVerifier::verify_product_access( $token, $user_id, $decoded );

		if ( true !== $response ) {
			throw new \Exception( 'Token validation failed. The product access is invalid.' );
		}

		$this->set_admin_authentication();

		return true;
	}

	/**
	 * Get the public key for JWT validation.
	 *
	 * @return string
	 *
	 * @throws \Exception If fetching the public key fails.
	 */
	private function get_public_key(): string {

		$public_key = get_transient( 'blu_jwt_public_key' );

		if ( false === $public_key ) {
			try {
				$response = wp_remote_get( self::CF_UJWT_PUBLIC_KEY_URL );

				if ( is_wp_error( $response ) ) {
					throw new \Exception( 'Failed to fetch public key: ' . $response->get_error_message() );
				}

				$body = wp_remote_retrieve_body( $response );

				if ( empty( $body ) ) {
					throw new \Exception( 'Public key response body is empty.' );
				}

				$public_key = $body;

				set_transient( 'blu_jwt_public_key', $public_key, HOUR_IN_SECONDS );

			} catch ( \Exception $e ) {

				throw new \Exception( 'Failed to fetch public key: ' . esc_html( $e->getMessage() ) );
			}
		}

		return apply_filters( 'blu_jwt_public_key', $public_key );
	}

	/**
	 * Set the current user to an administrator for authentication.
	 *
	 * @return void
	 *
	 * @throws \Exception If no valid admin user is found.
	 */
	private function set_admin_authentication(): void {

		$admin_user    = get_transient( 'nfd_blu_mcp_user' );
		$valid_user_id = false;
		if ( $admin_user ) {
			if ( user_can( $admin_user, 'manage_settings' ) ) {
				$valid_user_id = true;
			}
		}

		if ( ! $valid_user_id ) {
			$args       = array(
				'role'   => 'administrator',
				'fields' => 'ID',
				'number' => 1,
			);
			$admin_user = get_users( $args );

			if ( empty( $admin_user ) ) {
				throw new \Exception( 'No user found for authentication.' );
			}

			$admin_user = $admin_user[0];
			set_transient( 'nfd_blu_mcp_user', $admin_user, 2 * HOUR_IN_SECONDS );
		}
		wp_set_current_user( $admin_user );
	}
}
