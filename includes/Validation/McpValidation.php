<?php

declare( strict_types=1 );

namespace BLU\Validation;

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
	 * URL to fetch the staging public key for JWT validation (aud: qa).
	 *
	 * @var string
	 */
	private const CF_UJWT_PUBLIC_KEY_STAGING_URL = 'https://cdn.hiive.space/jwt-public-key-staging.pem';

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
			// If already logged in as admin, allow.
			if ( is_user_logged_in() && current_user_can( 'manage_options' ) ) {
				return true;
			}

			// Otherwise require JWT in the Authorization header.
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

			// Validate JWT (signature, claims, expiry) and verify product access via Hiive.
			return $this->is_valid_token( $token );

		} catch ( \Throwable $e ) {
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
	 * Peek at the JWT payload without verifying the signature.
	 * Used only for key choice (aud) and early expired check (exp); never to accept a token.
	 *
	 * @param string $token The JWT token.
	 *
	 * @return object|null Payload object with aud and exp, or null on failure.
	 */
	private function peek_payload( string $token ): ?object {
		// Decode the payload (middle segment) without verifying the signature.
		$segments = explode( '.', $token );
		if ( count( $segments ) !== 3 ) {
			return null;
		}

		$payload_b64url = $segments[1];
		$payload_b64    = strtr( $payload_b64url, '-_', '+/' );
		$payload_raw    = base64_decode( $payload_b64, true );
		if ( false === $payload_raw ) {
			return null;
		}

		$payload = json_decode( $payload_raw );
		if ( ! is_object( $payload ) ) {
			return null;
		}

		return $payload;
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

		$peeked = $this->peek_payload( $token );

		// Early exit for expired tokens (no key fetch, no Hiive).
		if ( null !== $peeked && isset( $peeked->exp ) && is_numeric( $peeked->exp ) && (int) $peeked->exp < time() ) {
			throw new \Exception( 'Token validation failed. The token has expired.' );
		}

		// Early exit for not-yet-valid tokens (nbf).
		if ( null !== $peeked && isset( $peeked->nbf ) && is_numeric( $peeked->nbf ) && (int) $peeked->nbf > time() ) {
			throw new \Exception( 'Token validation failed. The token is not yet valid.' );
		}

		// Choose key by audience: QA tokens (aud: qa) use staging key; production uses production key.
		$use_staging = ( null !== $peeked && isset( $peeked->aud ) && 'qa' === $peeked->aud );
		$public_key  = $this->get_public_key( $use_staging );

		// Verify signature and decode claims.
		$decoded = JWT::decode( $token, new Key( $public_key, 'RS256' ) );

		$user_id = null;

		if ( ! isset( $decoded->aud ) ) {
			throw new \Exception( 'Token validation failed. The audience is invalid.' );
		}

		if ( ! isset( $decoded->iss ) || 'jarvis-jwt' !== $decoded->iss ) {
			throw new \Exception( 'Token validation failed. The iss is invalid.' );
		}

		// Extract user ID: prefer act.sub (acting user, e.g. "urn:jarvis:bluehost:user:549716553")
		// over top-level sub (account, e.g. "urn:jarvis:bluehost:account:152665891").
		$sub_source = null;
		if ( isset( $decoded->act, $decoded->act->sub ) && is_string( $decoded->act->sub ) ) {
			$sub_source = $decoded->act->sub;
		} elseif ( isset( $decoded->sub ) && is_string( $decoded->sub ) ) {
			$sub_source = $decoded->sub;
		}

		if ( null === $sub_source ) {
			throw new \Exception( 'Token validation failed. The sub claim is missing.' );
		}

		$sub_parts = explode( ':', $sub_source );
		if ( ! empty( $sub_parts ) ) {
			$user_id = end( $sub_parts );
		}

		if ( empty( $user_id ) ) {
			throw new \Exception( 'Token validation failed. The user ID is missing.' );
		}

		// Verify product access with Hiive (staging for QA tokens, production otherwise).
		$response = HiiveProductVerifier::verify_product_access( $token, $user_id, $decoded );

		if ( true !== $response ) {
			throw new \Exception( 'Token validation failed. The product access is invalid.' );
		}

		// Set WordPress current user to an admin so the request has the required capabilities.
		$this->set_admin_authentication();

		return true;
	}

	/**
	 * Normalize a PEM key so OpenSSL accepts it (e.g. convert literal \n to newlines).
	 *
	 * @param string $key Raw key content, possibly with escaped newlines.
	 *
	 * @return string Normalized PEM key.
	 */
	private function normalize_pem_key( string $key ): string {
		return trim( str_replace( array( "\\n", "\\r" ), array( "\n", "\r" ), $key ) );
	}

	/**
	 * Get the public key for JWT validation.
	 *
	 * @param bool $use_staging True to use staging key (aud: qa), false for production.
	 *
	 * @return string
	 *
	 * @throws \Exception If fetching the public key fails.
	 */
	private function get_public_key( bool $use_staging = false ): string {
		$transient_key = $use_staging ? 'blu_jwt_public_key_staging' : 'blu_jwt_public_key';
		$url           = $use_staging ? self::CF_UJWT_PUBLIC_KEY_STAGING_URL : self::CF_UJWT_PUBLIC_KEY_URL;
		$filter_name   = $use_staging ? 'blu_jwt_public_key_staging' : 'blu_jwt_public_key';

		// Use cached key when available to avoid repeated remote fetches.
		$public_key = get_transient( $transient_key );

		if ( false === $public_key ) {
			try {
				$response = wp_remote_get( $url );

				if ( is_wp_error( $response ) ) {
					throw new \Exception( 'Failed to fetch public key: ' . $response->get_error_message() );
				}

				$body = wp_remote_retrieve_body( $response );

				if ( empty( $body ) ) {
					throw new \Exception( 'Public key response body is empty.' );
				}

				$public_key = $this->normalize_pem_key( $body );

				// Cache the key for 1 hour.
				set_transient( $transient_key, $public_key, HOUR_IN_SECONDS );

			} catch ( \Exception $e ) {
				throw new \Exception( 'Failed to fetch public key: ' . esc_html( $e->getMessage() ) );
			}
		}

		return apply_filters( $filter_name, $this->normalize_pem_key( $public_key ) );
	}

	/**
	 * Set the current user to an administrator for authentication.
	 *
	 * @return void
	 *
	 * @throws \Exception If no valid admin user is found.
	 */
	private function set_admin_authentication(): void {
		// Use cached admin user when valid; otherwise resolve the first administrator.
		$admin_user    = get_transient( 'nfd_blu_mcp_user' );
		$valid_user_id = false;
		if ( $admin_user ) {
			if ( user_can( $admin_user, 'manage_options' ) ) {
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
