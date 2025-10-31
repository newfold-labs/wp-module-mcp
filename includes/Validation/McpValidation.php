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
	 * The user ID associated with the token.
	 *
	 * @var int|false
	 */
	private $user_id = false;

	public $public_key = <<<EOD
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

		add_filter( 'determine_current_user', [ $this, 'filter_current_user' ] );
	}

	/**
	 * Permission callback for transport endpoints.
	 *
	 * Inspects the incoming HTTP Authorization header for a Bearer token and
	 * determines whether the current request is authorized to use the transport.
	 *
	 * @access private
	 * @return bool|WP_Error True when authorized; WP_Error('mcp_transport_unauthorized', 'Unauthorized: Invalid API token.', array('status' => 401)) otherwise.
	 */
	public static function get_transport_permission_callback(): bool|WP_Error {

		if( isset($_SERVER['HTTP_AUTHORIZATION']) && str_starts_with( $_SERVER['HTTP_AUTHORIZATION'], 'Bearer ' )) {
			$instance = new self();
			$token = $instance->extract_bearer_token( $_SERVER['HTTP_AUTHORIZATION'] );
			if ( $token && $instance->is_valid_token( $token ) ) {
				return true;
			}
		}

		return new WP_Error( 'mcp_transport_unauthorized', 'Unauthorized: Invalid token authorization.', array( 'status' => 401 ) );
	}

	/**
	 * Filter to set the current user for authentication.
	 *
	 * Checks the Authorization header for a valid Bearer token and sets an admin user.
	 * This will be checked only on mcp endpoint requests.
	 * 
	 * @param int|false $user_id The current user ID or false if not authenticated.
	 * @return int The user ID to set as the current user.
	 */

	public function filter_current_user($user_id) {
		
		if ( $this->is_mcp_endpoint() && isset($_SERVER['HTTP_AUTHORIZATION']) && str_starts_with( $_SERVER['HTTP_AUTHORIZATION'], 'Bearer ' )) {
			$token = $this->extract_bearer_token( $_SERVER['HTTP_AUTHORIZATION'] );
			if( $token && $this->is_valid_token( $token ) ) {
				if( $this->user_id ) {
					return $this->user_id;
				}	
				// Return the first admin user ID.
				$args = array(
					'role'   => 'administrator',
					'fields' => 'ID',            
					'number' => 1,               
				);
				$admin_user = get_users( $args );
				if ( ! empty( $admin_user ) ) {
					$this->user_id = $admin_user[0];
					return $this->user_id;
				}
			}
		}
		return $user_id;
	}

	/**
	 * Check if the current request is for an MCP endpoint.
	 *
	 * @return bool
	 */
	private function is_mcp_endpoint(): bool {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return str_contains( $request_uri, self::BLU_ENDPOINT_PATTERN );
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
	 * @return bool True if valid, false otherwise.
	 */
	private function is_valid_token( string $token ): bool {
		try {
			$decoded = JWT::decode( $token, new Key( $this->public_key, 'RS256' ) );
			return true;
		} catch ( \Exception $e ) {
			return false;
		}	
	}

}
