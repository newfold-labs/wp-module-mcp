<?php
/**
 * Session-based Authentication for MCP
 *
 * Allows MCP requests to authenticate using just the Mcp-Session-Id header,
 * without requiring Basic Auth or cookies.
 *
 * @package BLU
 */

declare( strict_types=1 );

namespace BLU;

/**
 * Handles session-based authentication for MCP endpoints.
 */
class SessionAuthenticator {

	/**
	 * The session meta key used by the MCP adapter.
	 */
	private const SESSION_META_KEY = 'mcp_adapter_sessions';

	/**
	 * Initialize the authenticator.
	 */
	public function __construct() {
		// Hook into REST authentication - run early before other auth
		add_filter( 'rest_authentication_errors', [ $this, 'authenticate_from_session' ], 5 );
	}

	/**
	 * Authenticate a request using the Mcp-Session-Id header.
	 *
	 * If a valid session ID is provided and no user is logged in,
	 * this will set the current user to the session owner.
	 *
	 * @param \WP_Error|null|true $result Current authentication result.
	 * @return \WP_Error|null|true Authentication result.
	 */
	public function authenticate_from_session( $result ) {
		// If already authenticated or already errored, don't interfere
		if ( null !== $result || is_user_logged_in() ) {
			return $result;
		}

		// Only apply to MCP endpoints
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';
		if ( strpos( $request_uri, '/blu/mcp' ) === false ) {
			return $result;
		}

		// Get session ID from header
		$session_id = $this->get_session_id_from_request();
		if ( ! $session_id ) {
			return $result;
		}

		// Find the user who owns this session
		$user_id = $this->find_session_owner( $session_id );
		if ( ! $user_id ) {
			return $result;
		}

		// Validate the session is still active
		if ( ! $this->is_session_valid( $user_id, $session_id ) ) {
			return $result;
		}

		// Set the current user
		wp_set_current_user( $user_id );

		// Return null to indicate successful authentication (let request proceed)
		return null;
	}

	/**
	 * Get the session ID from the request headers.
	 *
	 * @return string|null Session ID or null if not provided.
	 */
	private function get_session_id_from_request(): ?string {
		// Check for Mcp-Session-Id header
		$headers = $this->get_request_headers();

		if ( isset( $headers['Mcp-Session-Id'] ) ) {
			return sanitize_text_field( $headers['Mcp-Session-Id'] );
		}

		if ( isset( $headers['mcp-session-id'] ) ) {
			return sanitize_text_field( $headers['mcp-session-id'] );
		}

		return null;
	}

	/**
	 * Get all request headers.
	 *
	 * @return array Request headers.
	 */
	private function get_request_headers(): array {
		if ( function_exists( 'getallheaders' ) ) {
			return getallheaders() ?: [];
		}

		// Fallback for servers that don't have getallheaders
		$headers = [];
		foreach ( $_SERVER as $key => $value ) {
			if ( str_starts_with( $key, 'HTTP_' ) ) {
				$header_name = str_replace( '_', '-', substr( $key, 5 ) );
				$headers[ $header_name ] = $value;
			}
		}

		return $headers;
	}

	/**
	 * Find the user who owns a session.
	 *
	 * @param string $session_id The session ID to look up.
	 * @return int|null User ID or null if not found.
	 */
	private function find_session_owner( string $session_id ): ?int {
		global $wpdb;

		// Query user meta for the session
		// This searches all users' session data for the given session ID
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s",
				self::SESSION_META_KEY
			)
		);

		foreach ( $results as $row ) {
			$sessions = maybe_unserialize( $row->meta_value );
			if ( is_array( $sessions ) && isset( $sessions[ $session_id ] ) ) {
				return (int) $row->user_id;
			}
		}

		return null;
	}

	/**
	 * Check if a session is still valid (not expired).
	 *
	 * @param int    $user_id    User ID.
	 * @param string $session_id Session ID.
	 * @return bool True if valid, false otherwise.
	 */
	private function is_session_valid( int $user_id, string $session_id ): bool {
		$sessions = get_user_meta( $user_id, self::SESSION_META_KEY, true );

		if ( ! is_array( $sessions ) || ! isset( $sessions[ $session_id ] ) ) {
			return false;
		}

		$session = $sessions[ $session_id ];
		$timeout = (int) apply_filters( 'mcp_adapter_session_inactivity_timeout', DAY_IN_SECONDS );

		// Check if session has expired
		if ( $session['last_activity'] + $timeout < time() ) {
			return false;
		}

		// Update last activity
		$sessions[ $session_id ]['last_activity'] = time();
		update_user_meta( $user_id, self::SESSION_META_KEY, $sessions );

		return true;
	}
}

