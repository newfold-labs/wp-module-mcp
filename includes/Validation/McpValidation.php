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
	 * MCP endpoint path pattern for authentication.
	 *
	 * @var string
	 */
	private const BLU_ENDPOINT_PATTERN = 'blu/mcp';

	/**
	 * Bearer token pattern.
	 *
	 * @var string
	 */
	private const BEARER_TOKEN_PATTERN = '/Bearer\s(\S+)/';

	/**
	 * URL to fetch the public key for JWT validation.
	 *
	 * @var string
	 */
	private const CF_UJWT_PUBLIC_KEY_URL = 'https://cdn.hiive.space/jwt-public-key.pem';

	/**
	 * Initializes the class
	 *
	 * @return void
	 */
	public function __construct() {

		add_filter( 'rest_authentication_errors', array( $this, 'authenticate_request' ) );
	}

	/**
	 * Permission callback for transport endpoints.
	 *
	 * Inspects the incoming HTTP Authorization header for a Bearer token and
	 * determines whether the current request is authorized to use the transport.
	 *
	 * @return bool|WP_Error True when authorized; WP_Error('mcp_transport_unauthorized', 'Unauthorized: Invalid API token.', array('status' => 401)) otherwise.
	 */
	public static function get_transport_permission_callback(): bool|WP_Error {

		$instance = new self();

		$is_valid_token = $instance->handle_token_validation();

		if ( $is_valid_token instanceof WP_Error ) {
			return new WP_Error( 'mcp_transport_unauthorized', 'Unauthorized: Invalid token authorization.', array( 'status' => 401 ) );
		}

		return true;
	}

	/**
	 * Authenticate incoming requests to MCP endpoints.
	 *
	 * @param mixed $result Previous authentication result.
	 * @return bool|WP_Error|null True if authenticated, WP_Error otherwise.
	 */
	public function authenticate_request( $result ): bool|WP_Error|null {

		// If a previous authentication check has already returned a result, pass it through.
		if ( ! empty( $result ) ) {
			return $result;
		}

		// Only apply JWT authentication to MCP endpoints.
		if ( ! $this->is_mcp_endpoint() ) {
			return $result;
		}

		$is_valid_token = $this->handle_token_validation();

		if ( $is_valid_token instanceof WP_Error ) {
			return $is_valid_token;
		}

		// Set current user to an admin user upon successful token validation.
		$admin_user    = get_transient( 'ndf_blu_mcp_user' );
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
				return new WP_Error(
					'unauthorized',
					'No user found for authentication.',
					array( 'status' => 401 )
				);
			}

			$admin_user = $admin_user[0];
			set_transient( 'ndf_blu_mcp_user', $admin_user, 2 * HOUR_IN_SECONDS );
		}
		wp_set_current_user( $admin_user );
		return $is_valid_token;
	}

	/**
	 * Handle token validation process.
	 *
	 * @return bool|WP_Error True if valid, WP_Error otherwise.
	 */
	private function handle_token_validation() {

		$auth_header = $this->get_authorization_header();

		if ( empty( $auth_header ) ) {
			return $this->handle_missing_authorization();
		}

		$token = $this->extract_bearer_token( $auth_header );

		if ( null === $token ) {
			return new WP_Error(
				'unauthorized',
				'Invalid Authorization header format. Expected "Bearer <token>".',
				array( 'status' => 401 )
			);
		}

		return $this->is_valid_token( $token );
	}

	/**
	 * Check if the current request is for an MCP endpoint.
	 *
	 * @return bool
	 */
	private function is_mcp_endpoint(): bool {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return preg_match( '#blu/mcp(/|$)#', $request_uri ) === 1;
	}

	/**
	 * Extract Bearer token from authorization header.
	 *
	 * @param string $auth Authorization header value.
	 * @return string|null Token if found, null otherwise.
	 */
	private function extract_bearer_token( string $auth ): ?string {
		if ( preg_match( self::BEARER_TOKEN_PATTERN, $auth, $matches ) ) {
			return $matches[1];
		}
		return null;
	}
	/**
	 * Validate the JWT token.
	 *
	 * @param string $token The JWT token to validate.
	 * @return bool|WP_Error True if valid, false or WP_Error otherwise.
	 */
	private function is_valid_token( string $token ): bool|WP_Error {

		if ( ! str_contains( $token, '.' ) ) {
			// Not a JWT format, return error for invalid token.
			return new WP_Error(
				'invalid_token',
				'Token format is invalid.',
				array( 'status' => 403 )
			);
		}

		try {

			$public_key = $this->get_public_key();

			$decoded = JWT::decode( $token, new Key( $public_key, 'RS256' ) );

			$userID = null;
			
			if( !isset( $decoded->aud ) || $decoded->aud !== 'production' ) {
				return new WP_Error(
					'invalid_token',
					'Token validation failed. The audience is invalid.',
					array( 'status' => 403 )
				);
			}

			if( !isset( $decoded->iss ) || $decoded->iss !== 'jarvis-jwt' ) {
				return new WP_Error(
					'invalid_token',
					'Token validation failed. The iss is invalid.',
					array( 'status' => 403 )
				);
			}
			
			$sub = $decoded->sub ?? null;
			if ( null === $sub ) {
				return new WP_Error(
					'invalid_token',
					'Token validation failed: sub claim missing.',
					array( 'status' => 403 )
				);
			} else {
				$sub_parts = explode( ':', $sub );
				if( !empty( $sub_parts ) ) {
					$userID = end( $sub_parts );
				}
			}	
			if ( null === $userID ) {
				return new WP_Error(
					'invalid_token',
					'Token validation failed: UserId missing.',
					array( 'status' => 403 )
				);
			}

			// Call the Hiive product verifier.
			$response = HiiveProductVerifier::verify_product_access( $token, $userID );

			return $response;
		} catch ( \Exception $e ) {
			return new WP_Error(
				'invalid_token',
				'Token validation failed: ' . $e->getMessage(),
				array( 'status' => 403 )
			);
		}
	}

	/**
	 * Get Authorization header from request.
	 *
	 * @return string
	 */
	private function get_authorization_header(): string {
		return isset( $_SERVER['HTTP_AUTHORIZATION'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ) : '';
	}

	/**
	 * Handle authentication when no Authorization header is present.
	 *
	 * @return mixed Authentication result.
	 */
	private function handle_missing_authorization() {
		return new WP_Error(
			'unauthorized',
			'Authentication required. Please provide a Bearer token.',
			array( 'status' => 401 )
		);
	}

	/**
	 * Get the public key for JWT validation.
	 *
	 * @return string
	 */
	private function get_public_key(): string|WP_Error {

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

				throw new \Exception( 'Failed to fetch public key: ' . $e->getMessage() );
			}
		}
		return apply_filters('blu_jwt_public_key', $public_key );
	}
}
