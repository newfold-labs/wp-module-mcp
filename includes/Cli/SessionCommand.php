<?php
/**
 * WP-CLI Command for managing MCP Sessions
 *
 * @package BLU
 */

declare( strict_types=1 );

namespace BLU\Cli;

use WP_CLI;
use WP_CLI_Command;

/**
 * Manage MCP sessions for the Bluehost MCP Server.
 *
 * Provides commands to create, list, and revoke MCP sessions for users.
 * Sessions can be pre-generated and passed to external services for
 * server-to-server MCP communication.
 *
 * ## EXAMPLES
 *
 *     # Create a session for admin user
 *     wp blu-mcp session create --for-user=admin
 *
 *     # List all sessions for a user
 *     wp blu-mcp session list --for-user=admin
 *
 *     # Revoke a specific session
 *     wp blu-mcp session revoke <session-id> --for-user=admin
 */
class SessionCommand extends WP_CLI_Command {

	/**
	 * The session manager class name (Strauss-prefixed).
	 *
	 * @var string
	 */
	private const SESSION_MANAGER_CLASS = '\Bluehost\Plugin\WP\MCP\Transport\Infrastructure\SessionManager';

	/**
	 * Create a new MCP session for a user.
	 *
	 * Generates a session ID that can be used to authenticate MCP requests
	 * without going through the full initialization flow. Useful for
	 * pre-configuring external services.
	 *
	 * ## OPTIONS
	 *
	 * [--for-user=<user>]
	 * : The user ID, login, or email to create the session for.
	 * ---
	 * required: true
	 * ---
	 *
	 * [--client-name=<name>]
	 * : Optional name to identify the client using this session.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Create session for admin user
	 *     wp blu-mcp session create --for-user=admin
	 *
	 *     # Create session with client name
	 *     wp blu-mcp session create --for-user=admin --client-name="My Integration"
	 *
	 *     # Get JSON output for scripting
	 *     wp blu-mcp session create --for-user=admin --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function create( array $args, array $assoc_args ): void {
		$this->ensure_session_manager_available();

		$user = $this->get_user_from_args( $assoc_args );
		wp_set_current_user( $user->ID );

		$client_params = array();
		if ( ! empty( $assoc_args['client-name'] ) ) {
			$client_params['clientInfo'] = array(
				'name' => sanitize_text_field( $assoc_args['client-name'] ),
			);
		}

		$session_manager = self::SESSION_MANAGER_CLASS;
		$session_id      = $session_manager::create_session( $user->ID, $client_params );

		if ( ! $session_id ) {
			WP_CLI::error( 'Failed to create session.' );
		}

		$timeout  = (int) apply_filters( 'mcp_adapter_session_inactivity_timeout', DAY_IN_SECONDS );
		$endpoint = rest_url( 'blu/mcp' );

		$data = array(
			array(
				'session_id'  => $session_id,
				'user_id'     => $user->ID,
				'user_login'  => $user->user_login,
				'endpoint'    => $endpoint,
				'expires_in'  => $timeout,
				'expires_at'  => gmdate( 'Y-m-d H:i:s', time() + $timeout ) . ' UTC',
				'client_name' => $assoc_args['client-name'] ?? '',
			),
		);

		$format = $assoc_args['format'] ?? 'table';

		if ( 'json' === $format ) {
			WP_CLI::line( wp_json_encode( $data[0], JSON_PRETTY_PRINT ) );
		} elseif ( 'yaml' === $format ) {
			WP_CLI\Utils\format_items( 'yaml', $data, array_keys( $data[0] ) );
		} else {
			WP_CLI::success( 'Session created successfully!' );
			WP_CLI\Utils\format_items( 'table', $data, array( 'session_id', 'user_login', 'endpoint', 'expires_at' ) );
			WP_CLI::line( '' );
			WP_CLI::line( 'Usage: Include this header in your MCP requests:' );
			WP_CLI::line( WP_CLI::colorize( "%GMcp-Session-Id: {$session_id}%n" ) );
		}
	}

	/**
	 * List all MCP sessions for a user.
	 *
	 * Shows all active sessions including creation time and last activity.
	 *
	 * ## OPTIONS
	 *
	 * [--for-user=<user>]
	 * : The user ID, login, or email to list sessions for.
	 * ---
	 * required: true
	 * ---
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 *   - csv
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List sessions for admin
	 *     wp blu-mcp session list --for-user=admin
	 *
	 *     # Get count of active sessions
	 *     wp blu-mcp session list --for-user=admin --format=count
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function list( array $args, array $assoc_args ): void {
		$this->ensure_session_manager_available();

		$user            = $this->get_user_from_args( $assoc_args );
		$session_manager = self::SESSION_MANAGER_CLASS;
		$sessions        = $session_manager::get_all_user_sessions( $user->ID );

		if ( empty( $sessions ) ) {
			WP_CLI::line( "No active sessions for user: {$user->user_login}" );
			return;
		}

		$timeout = (int) apply_filters( 'mcp_adapter_session_inactivity_timeout', DAY_IN_SECONDS );
		$now     = time();
		$data    = array();

		foreach ( $sessions as $session_id => $session ) {
			$expires_at   = $session['last_activity'] + $timeout;
			$is_expired   = $expires_at < $now;
			$client_name  = $session['client_params']['clientInfo']['name'] ?? '';

			$data[] = array(
				'session_id'    => $session_id,
				'created_at'    => gmdate( 'Y-m-d H:i:s', $session['created_at'] ) . ' UTC',
				'last_activity' => gmdate( 'Y-m-d H:i:s', $session['last_activity'] ) . ' UTC',
				'expires_at'    => gmdate( 'Y-m-d H:i:s', $expires_at ) . ' UTC',
				'status'        => $is_expired ? 'expired' : 'active',
				'client_name'   => $client_name,
			);
		}

		$format = $assoc_args['format'] ?? 'table';
		WP_CLI\Utils\format_items( $format, $data, array( 'session_id', 'created_at', 'last_activity', 'status', 'client_name' ) );
	}

	/**
	 * Revoke an MCP session.
	 *
	 * Immediately invalidates a session, preventing further use.
	 *
	 * ## OPTIONS
	 *
	 * <session-id>
	 * : The session ID to revoke.
	 *
	 * [--for-user=<user>]
	 * : The user ID, login, or email who owns the session.
	 * ---
	 * required: true
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Revoke a specific session
	 *     wp blu-mcp session revoke 550e8400-e29b-41d4-a716-446655440000 --for-user=admin
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function revoke( array $args, array $assoc_args ): void {
		$this->ensure_session_manager_available();

		if ( empty( $args[0] ) ) {
			WP_CLI::error( 'Session ID is required.' );
		}

		$session_id      = $args[0];
		$user            = $this->get_user_from_args( $assoc_args );
		$session_manager = self::SESSION_MANAGER_CLASS;

		$deleted = $session_manager::delete_session( $user->ID, $session_id );

		if ( $deleted ) {
			WP_CLI::success( "Session revoked: {$session_id}" );
		} else {
			WP_CLI::error( "Session not found or already revoked: {$session_id}" );
		}
	}

	/**
	 * Revoke all MCP sessions for a user.
	 *
	 * Immediately invalidates all sessions for the specified user.
	 *
	 * ## OPTIONS
	 *
	 * [--for-user=<user>]
	 * : The user ID, login, or email to revoke all sessions for.
	 * ---
	 * required: true
	 * ---
	 *
	 * [--yes]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Revoke all sessions for admin
	 *     wp blu-mcp session revoke-all --for-user=admin
	 *
	 *     # Skip confirmation
	 *     wp blu-mcp session revoke-all --for-user=admin --yes
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function revoke_all( array $args, array $assoc_args ): void {
		$this->ensure_session_manager_available();

		$user            = $this->get_user_from_args( $assoc_args );
		$session_manager = self::SESSION_MANAGER_CLASS;
		$sessions        = $session_manager::get_all_user_sessions( $user->ID );
		$count           = count( $sessions );

		if ( 0 === $count ) {
			WP_CLI::line( "No sessions to revoke for user: {$user->user_login}" );
			return;
		}

		if ( empty( $assoc_args['yes'] ) ) {
			WP_CLI::confirm( "Are you sure you want to revoke all {$count} session(s) for {$user->user_login}?" );
		}

		// Delete user meta directly to remove all sessions at once.
		delete_user_meta( $user->ID, 'mcp_adapter_sessions' );

		WP_CLI::success( "Revoked {$count} session(s) for user: {$user->user_login}" );
	}

	/**
	 * Show session info and usage instructions.
	 *
	 * Displays information about how to use MCP sessions with external services.
	 *
	 * ## EXAMPLES
	 *
	 *     wp blu-mcp session info
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function info( array $args, array $assoc_args ): void {
		$endpoint = rest_url( 'blu/mcp' );
		$timeout  = (int) apply_filters( 'mcp_adapter_session_inactivity_timeout', DAY_IN_SECONDS );

		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%BMCP Session Information%n' ) );
		WP_CLI::line( str_repeat( '─', 50 ) );
		WP_CLI::line( '' );
		WP_CLI::line( "Endpoint:        {$endpoint}" );
		WP_CLI::line( "Session Timeout: " . human_time_diff( 0, $timeout ) );
		WP_CLI::line( '' );
		WP_CLI::line( WP_CLI::colorize( '%YUsage:%n' ) );
		WP_CLI::line( '' );
		WP_CLI::line( '1. Create a session:' );
		WP_CLI::line( WP_CLI::colorize( '   %Gwp blu-mcp session create --user=admin%n' ) );
		WP_CLI::line( '' );
		WP_CLI::line( '2. Use the session in HTTP requests:' );
		WP_CLI::line( WP_CLI::colorize( '   %Gcurl -X POST ' . $endpoint . ' \\%n' ) );
		WP_CLI::line( WP_CLI::colorize( '   %G  -H "Mcp-Session-Id: <session-id>" \\%n' ) );
		WP_CLI::line( WP_CLI::colorize( '   %G  -H "Content-Type: application/json" \\%n' ) );
		WP_CLI::line( WP_CLI::colorize( '   %G  -d \'{"jsonrpc":"2.0","method":"tools/list","id":1}\'%n' ) );
		WP_CLI::line( '' );
		WP_CLI::line( '3. Available MCP methods:' );
		WP_CLI::line( '   - initialize          (create session via HTTP)' );
		WP_CLI::line( '   - tools/list          (list available tools)' );
		WP_CLI::line( '   - tools/call          (execute a tool)' );
		WP_CLI::line( '   - resources/list      (list resources)' );
		WP_CLI::line( '   - prompts/list        (list prompts)' );
		WP_CLI::line( '' );
	}

	/**
	 * Ensure the SessionManager class is available.
	 *
	 * @throws \WP_CLI\ExitException If SessionManager is not available.
	 */
	private function ensure_session_manager_available(): void {
		if ( ! class_exists( self::SESSION_MANAGER_CLASS ) ) {
			WP_CLI::error(
				'MCP Adapter SessionManager not available. ' .
				'Make sure the Bluehost plugin is active and the MCP adapter is loaded.'
			);
		}
	}

	/**
	 * Get a user from associative arguments.
	 *
	 * @param array $assoc_args Associative arguments containing 'user'.
	 *
	 * @return \WP_User The user object.
	 * @throws \WP_CLI\ExitException If user is not found.
	 */
	private function get_user_from_args( array $assoc_args ): \WP_User {
		if ( empty( $assoc_args['for-user'] ) ) {
			WP_CLI::error( '--for-user is required. Provide a user ID, login, or email.' );
		}

		$user_input = $assoc_args['for-user'];

		// Try as ID first.
		if ( is_numeric( $user_input ) ) {
			$user = get_user_by( 'id', (int) $user_input );
			if ( $user ) {
				return $user;
			}
		}

		// Try as login.
		$user = get_user_by( 'login', $user_input );
		if ( $user ) {
			return $user;
		}

		// Try as email.
		$user = get_user_by( 'email', $user_input );
		if ( $user ) {
			return $user;
		}

		WP_CLI::error( "User not found: {$user_input}" );
	}
}

