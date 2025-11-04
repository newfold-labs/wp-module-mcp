<?php

declare( strict_types=1 );

namespace BLU\Validation;

use WP_Error;
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
	 * Public key for JWT validation.
	 *
	 * @var string
	 */
	private $public_key = <<<'EOD'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAuzWHNM5f+amCjQztc5QT
fJfzCC5J4nuW+L/aOxZ4f8J3FrewM2c/dufrnmedsApb0By7WhaHlcqCh/ScAPyJ
hzkPYLae7bTVro3hok0zDITR8F6SJGL42JAEUk+ILkPI+DONM0+3vzk6Kvfe548t
u4czCuqU8BGVOlnp6IqBHhAswNMM78pos/2z0CjPM4tbeXqSTTbNkXRboxjU29vS
opcT51koWOgiTf3C7nJUoMWZHZI5HqnIhPAG9yv8HAgNk6CMk2CadVHDo4IxjxTz
TTqo1SCSH2pooJl9O8at6kkRYsrZWwsKlOFE2LUce7ObnXsYihStBUDoeBQlGG/B
wQIDAQAB
-----END PUBLIC KEY-----
EOD;

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
		return preg_match( '#^/wp-json/blu/mcp(/|$)#', $request_uri ) === 1;
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
			$decoded = JWT::decode( $token, new Key( $this->public_key, 'RS256' ) );
			// The decoded JWT payload is currently unused. If claim validation is needed in the future,
			// use $decoded to inspect claims such as exp, nbf, iss, aud. For now, we only check if decoding succeeds.
			// TODO: Add extra validation as needed.

			// if( !isset( $decoded->aud ) || $decoded->aud !== 'QA' ) {
			// return new WP_Error(
			// 'invalid_token',
			// 'Token validation failed.',
			// array( 'status' => 403 )
			// );
			// }

			return true;
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
}
