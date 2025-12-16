<?php

declare( strict_types=1 );

namespace BLU\Validation;

use WP_Error;
use WP_REST_Request;
use BLU\Validation\HiiveProductVerifier;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Authentication method constants.
 */
class AuthMethod {
	public const NONE                    = 'none';
	public const JWT_BEARER              = 'jwt_bearer';
	public const APPLICATION_PASSWORD    = 'application_password';
	public const COOKIE                  = 'cookie';
	public const UNKNOWN                 = 'unknown';
}

/**
 * MCP Authentication Handler.
 *
 * Handles authentication for MCP endpoints:
 * 1. JWT Bearer tokens are handled via `determine_current_user` filter (early auth)
 * 2. Application passwords are handled natively by WordPress (no custom code needed)
 * 3. Cookie auth is handled natively by WordPress with nonce validation
 * 4. Permission callback only checks capabilities, not authentication
 *
 * @package BLU\Validation
 */
class McpValidation {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

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
	 * Required capability for MCP access.
	 *
	 * @var string
	 */
	private const REQUIRED_CAPABILITY = 'read';

	/**
	 * The authentication method used for the current request.
	 *
	 * @var string
	 */
	private string $auth_method = AuthMethod::NONE;

	/**
	 * Whether JWT authentication was performed this request.
	 *
	 * @var bool
	 */
	private bool $jwt_auth_attempted = false;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize authentication hooks.
	 *
	 * Called once during bootstrap.
	 *
	 * @return void
	 */
	public function init(): void {
		// Priority 10: Handle JWT authentication early in the user determination process.
		// This runs BEFORE WordPress checks cookies or application passwords.
		add_filter( 'determine_current_user', array( $this, 'authenticate_jwt' ), 10 );

		// Priority 99: Validate authentication results and handle errors.
		// This runs AFTER all authentication methods have been tried.
		add_filter( 'rest_authentication_errors', array( $this, 'validate_authentication' ), 99 );

		// Track when application password auth succeeds.
		add_action( 'application_password_did_authenticate', array( $this, 'on_application_password_auth' ), 10, 2 );
	}

	/**
	 * Permission callback for MCP transport endpoints.
	 *
	 * This should ONLY check capabilities, not perform authentication.
	 * Authentication has already happened via the filters above.
	 *
	 * @param WP_REST_Request|null $request Optional request object.
	 * @return bool|WP_Error
	 */
	public static function get_transport_permission_callback( ?WP_REST_Request $request = null ) {
		// User should already be authenticated by this point.
		if ( ! is_user_logged_in() ) {
			return new WP_Error(
				'mcp_not_authenticated',
				'Authentication required. Use JWT Bearer token, Application Password, or Cookie with nonce.',
				array( 'status' => 401 )
			);
		}

		// Check required capability.
		if ( ! current_user_can( self::REQUIRED_CAPABILITY ) ) {
			return new WP_Error(
				'mcp_forbidden',
				sprintf( 'User lacks required capability: %s', self::REQUIRED_CAPABILITY ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Authenticate via JWT Bearer token.
	 *
	 * Hooks into `determine_current_user` to authenticate JWT tokens early.
	 * This is the correct hook for custom authentication methods in WordPress.
	 *
	 * @param int|false $user_id Current user ID or false if not determined.
	 * @return int|false User ID if JWT authenticated, original value otherwise.
	 */
	public function authenticate_jwt( $user_id ) {
		// If user already determined, don't override.
		if ( $user_id ) {
			return $user_id;
		}

		// Only process MCP endpoints.
		if ( ! $this->is_mcp_request() ) {
			return $user_id;
		}

		// Check for Bearer token.
		$auth_header = $this->get_authorization_header();
		if ( empty( $auth_header ) || ! $this->has_bearer_token( $auth_header ) ) {
			return $user_id;
		}

		$this->jwt_auth_attempted = true;
		$token = $this->extract_bearer_token( $auth_header );

		if ( null === $token ) {
			return $user_id;
		}

		// Validate the JWT.
		$result = $this->validate_jwt_token( $token );

		if ( $result instanceof WP_Error ) {
			// Store error for later retrieval in rest_authentication_errors.
			$this->store_auth_error( $result );
			return $user_id;
		}

		// JWT valid - get or create admin user for this session.
		$admin_user_id = $this->get_admin_user_for_jwt();

		if ( $admin_user_id ) {
			$this->auth_method = AuthMethod::JWT_BEARER;
			return $admin_user_id;
		}

		return $user_id;
	}

	/**
	 * Validate authentication results.
	 *
	 * Runs after all authentication methods. Returns any stored errors
	 * or validates the final authentication state.
	 *
	 * @param WP_Error|true|null $result Existing authentication result.
	 * @return WP_Error|true|null
	 */
	public function validate_authentication( $result ) {
		// Pass through existing errors.
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Only apply to MCP endpoints.
		if ( ! $this->is_mcp_request() ) {
			return $result;
		}

		// Check for stored JWT auth error.
		$stored_error = $this->get_stored_auth_error();
		if ( $stored_error instanceof WP_Error ) {
			return $stored_error;
		}

		// If JWT was attempted and succeeded, mark as authenticated.
		if ( $this->jwt_auth_attempted && is_user_logged_in() ) {
			return true;
		}

		// Let WordPress handle other auth methods (app passwords, cookies).
		return $result;
	}

	/**
	 * Track when application password authentication succeeds.
	 *
	 * @param WP_User $user The authenticated user.
	 * @param array   $item The application password record.
	 * @return void
	 */
	public function on_application_password_auth( $user, $item ): void {
		if ( $this->is_mcp_request() ) {
			$this->auth_method = AuthMethod::APPLICATION_PASSWORD;
		}
	}

	/**
	 * Get the authentication method used for the current request.
	 *
	 * @return string One of AuthMethod constants.
	 */
	public function get_auth_method(): string {
		if ( $this->auth_method !== AuthMethod::NONE ) {
			return $this->auth_method;
		}

		// Detect method if not already set.
		if ( is_user_logged_in() ) {
			// Check if it was cookie auth (has valid logged-in cookie).
			if ( wp_validate_auth_cookie( '', 'logged_in' ) ) {
				return AuthMethod::COOKIE;
			}
			return AuthMethod::UNKNOWN;
		}

		return AuthMethod::NONE;
	}

	/**
	 * Check if current request is for an MCP endpoint.
	 *
	 * @return bool
	 */
	private function is_mcp_request(): bool {
		// Check REST API route.
		$rest_route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
		if ( ! empty( $rest_route ) && strpos( $rest_route, '/blu/mcp' ) !== false ) {
			return true;
		}

		// Fallback to REQUEST_URI check.
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		return (bool) preg_match( '#/wp-json/blu/mcp(/|$|\?)#', $request_uri );
	}

	/**
	 * Get admin user ID for JWT authenticated requests.
	 *
	 * @return int|false Admin user ID or false if none found.
	 */
	private function get_admin_user_for_jwt() {
		// Try cached user first.
		$cached_user_id = get_transient( 'nfd_blu_mcp_jwt_user' );
		if ( $cached_user_id && user_can( $cached_user_id, 'manage_options' ) ) {
			return (int) $cached_user_id;
		}

		// Find an admin user.
		$admin_users = get_users(
			array(
				'role'    => 'administrator',
				'fields'  => 'ID',
				'number'  => 1,
				'orderby' => 'ID',
				'order'   => 'ASC',
			)
		);

		if ( empty( $admin_users ) ) {
			return false;
		}

		$admin_user_id = (int) $admin_users[0];
		set_transient( 'nfd_blu_mcp_jwt_user', $admin_user_id, HOUR_IN_SECONDS );

		return $admin_user_id;
	}

	/**
	 * Store an authentication error for later retrieval.
	 *
	 * @param WP_Error $error The error to store.
	 * @return void
	 */
	private function store_auth_error( WP_Error $error ): void {
		$this->stored_auth_error = $error;
	}

	/**
	 * Get any stored authentication error.
	 *
	 * @return WP_Error|null
	 */
	private function get_stored_auth_error(): ?WP_Error {
		return $this->stored_auth_error ?? null;
	}

	/**
	 * Stored authentication error.
	 *
	 * @var WP_Error|null
	 */
	private ?WP_Error $stored_auth_error = null;

	/**
	 * Check if the authorization header contains a Bearer token.
	 *
	 * @param string $auth Authorization header value.
	 * @return bool
	 */
	private function has_bearer_token( string $auth ): bool {
		return (bool) preg_match( self::BEARER_TOKEN_PATTERN, $auth );
	}

	/**
	 * Extract Bearer token from authorization header.
	 *
	 * @param string $auth Authorization header value.
	 * @return string|null Token or null if not found.
	 */
	private function extract_bearer_token( string $auth ): ?string {
		if ( preg_match( self::BEARER_TOKEN_PATTERN, $auth, $matches ) ) {
			return $matches[1];
		}
		return null;
	}
	/**
	 * Validate a JWT token.
	 *
	 * @param string $token The JWT token to validate.
	 * @return true|WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_jwt_token( string $token ) {
		// Check JWT format (must have dots for header.payload.signature).
		if ( substr_count( $token, '.' ) !== 2 ) {
			return new WP_Error(
				'mcp_invalid_jwt_format',
				'Invalid token format. Expected JWT with header.payload.signature structure.',
				array( 'status' => 401 )
			);
		}

		try {
			$public_key = $this->get_public_key();
			$decoded    = JWT::decode( $token, new Key( $public_key, 'RS256' ) );

			// Validate audience.
			$expected_audience = apply_filters( 'blu_mcp_jwt_expected_audience', 'production' );
			if ( ! isset( $decoded->aud ) || $decoded->aud !== $expected_audience ) {
				return new WP_Error(
					'mcp_invalid_jwt_audience',
					'Token validation failed: invalid audience.',
					array( 'status' => 401 )
				);
			}

			// Validate issuer.
			$expected_issuer = apply_filters( 'blu_mcp_jwt_expected_issuer', 'jarvis-jwt' );
			if ( ! isset( $decoded->iss ) || $decoded->iss !== $expected_issuer ) {
				return new WP_Error(
					'mcp_invalid_jwt_issuer',
					'Token validation failed: invalid issuer.',
					array( 'status' => 401 )
				);
			}

			// Extract user ID from subject claim.
			$sub = $decoded->sub ?? null;
			if ( empty( $sub ) ) {
				return new WP_Error(
					'mcp_missing_jwt_subject',
					'Token validation failed: sub claim missing.',
					array( 'status' => 401 )
				);
			}

			$sub_parts = explode( ':', $sub );
			$hiive_user_id = ! empty( $sub_parts ) ? end( $sub_parts ) : null;

			if ( empty( $hiive_user_id ) ) {
				return new WP_Error(
					'mcp_missing_user_id',
					'Token validation failed: user ID missing from subject.',
					array( 'status' => 401 )
				);
			}

			// Verify product access via Hiive (if verifier is available).
			if ( class_exists( HiiveProductVerifier::class ) ) {
				$verification = HiiveProductVerifier::verify_product_access( $token, $hiive_user_id );
				if ( $verification instanceof WP_Error ) {
					return $verification;
				}
			}

			return true;

		} catch ( \Firebase\JWT\ExpiredException $e ) {
			return new WP_Error(
				'mcp_jwt_expired',
				'Token has expired.',
				array( 'status' => 401 )
			);
		} catch ( \Firebase\JWT\SignatureInvalidException $e ) {
			return new WP_Error(
				'mcp_jwt_signature_invalid',
				'Token signature verification failed.',
				array( 'status' => 401 )
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'mcp_jwt_validation_error',
				'Token validation failed: ' . $e->getMessage(),
				array( 'status' => 401 )
			);
		}
	}

	/**
	 * Get Authorization header from request.
	 *
	 * Checks multiple sources for the Authorization header since some server
	 * configurations may not pass it through in $_SERVER['HTTP_AUTHORIZATION'].
	 *
	 * @return string
	 */
	private function get_authorization_header(): string {
		// Standard location.
		if ( ! empty( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] );
		}

		// Apache with mod_rewrite may use this.
		if ( ! empty( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		}

		// Try getallheaders() as fallback (works on Apache).
		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			// Headers can be case-insensitive per HTTP spec.
			foreach ( $headers as $name => $value ) {
				if ( strtolower( $name ) === 'authorization' ) {
					return $value;
				}
			}
		}

		return '';
	}

	/**
	 * Get the public key for JWT validation.
	 *
	 * @return string
	 * @throws \Exception If public key cannot be fetched.
	 */
	private function get_public_key(): string {
		$public_key = get_transient( 'blu_jwt_public_key' );

		if ( false !== $public_key && ! empty( $public_key ) ) {
			return apply_filters( 'blu_jwt_public_key', $public_key );
		}

		$response = wp_remote_get(
			self::CF_UJWT_PUBLIC_KEY_URL,
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept' => 'application/x-pem-file, text/plain',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new \Exception( 'Failed to fetch public key: ' . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( $status_code !== 200 ) {
			throw new \Exception( sprintf( 'Failed to fetch public key: HTTP %d', $status_code ) );
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			throw new \Exception( 'Public key response body is empty.' );
		}

		// Validate it looks like a PEM key.
		if ( strpos( $body, '-----BEGIN' ) === false ) {
			throw new \Exception( 'Invalid public key format.' );
		}

		set_transient( 'blu_jwt_public_key', $body, HOUR_IN_SECONDS );

		return apply_filters( 'blu_jwt_public_key', $body );
	}

	/**
	 * Private constructor for singleton pattern.
	 */
	private function __construct() {
		// Use init() to set up hooks.
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @throws \Exception Always.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}
}
