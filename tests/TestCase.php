<?php
/**
 * Base test case for wp-module-mcp tests.
 *
 * @package BLU\Tests
 */

declare( strict_types=1 );

namespace BLU\Tests;

use Yoast\PHPUnitPolyfills\TestCases\TestCase as PolyfillsTestCase;
use WP_Error;
use WP_User;

/**
 * Base test case class.
 */
abstract class TestCase extends PolyfillsTestCase {

	/**
	 * Admin user for testing.
	 *
	 * @var WP_User|null
	 */
	protected ?WP_User $admin_user = null;

	/**
	 * Subscriber user for testing.
	 *
	 * @var WP_User|null
	 */
	protected ?WP_User $subscriber_user = null;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Reset any stored auth state.
		wp_set_current_user( 0 );

		// Clear any cached auth data.
		delete_transient( 'nfd_blu_mcp_jwt_user' );
		delete_transient( 'blu_jwt_public_key' );
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Create an admin user for testing.
	 *
	 * @return WP_User
	 */
	protected function create_admin_user(): WP_User {
		if ( $this->admin_user ) {
			return $this->admin_user;
		}

		$user_id = wp_create_user(
			'mcp_test_admin_' . uniqid(),
			'password123',
			'mcp_admin_' . uniqid() . '@example.com'
		);

		$user = get_user_by( 'id', $user_id );
		$user->set_role( 'administrator' );

		$this->admin_user = $user;
		return $user;
	}

	/**
	 * Create a subscriber user for testing.
	 *
	 * @return WP_User
	 */
	protected function create_subscriber_user(): WP_User {
		if ( $this->subscriber_user ) {
			return $this->subscriber_user;
		}

		$user_id = wp_create_user(
			'mcp_test_subscriber_' . uniqid(),
			'password123',
			'mcp_subscriber_' . uniqid() . '@example.com'
		);

		$user = get_user_by( 'id', $user_id );
		$user->set_role( 'subscriber' );

		$this->subscriber_user = $user;
		return $user;
	}

	/**
	 * Create an application password for a user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $name    Application password name.
	 * @return array{password: string, uuid: string}
	 */
	protected function create_application_password( int $user_id, string $name = 'Test App' ): array {
		$result = WP_Application_Passwords::create_new_application_password(
			$user_id,
			array( 'name' => $name )
		);

		if ( is_wp_error( $result ) ) {
			$this->fail( 'Failed to create application password: ' . $result->get_error_message() );
		}

		return array(
			'password' => $result[0],
			'uuid'     => $result[1]['uuid'],
		);
	}

	/**
	 * Generate a mock JWT token for testing.
	 *
	 * @param array $payload Token payload overrides.
	 * @param bool  $valid   Whether to create a valid signature.
	 * @return string
	 */
	protected function generate_mock_jwt( array $payload = array(), bool $valid = false ): string {
		$default_payload = array(
			'iss' => 'jarvis-jwt',
			'aud' => 'production',
			'sub' => 'user:12345',
			'iat' => time(),
			'exp' => time() + 3600,
		);

		$payload = array_merge( $default_payload, $payload );

		$header = base64_encode( json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$body   = base64_encode( json_encode( $payload ) );

		// For testing, create a mock signature (won't validate against real public key).
		$signature = $valid ? 'valid_signature' : 'mock_signature_for_testing';

		return sprintf( '%s.%s.%s', $header, $body, base64_encode( $signature ) );
	}

	/**
	 * Set the Authorization header for testing.
	 *
	 * @param string $value Header value.
	 */
	protected function set_authorization_header( string $value ): void {
		$_SERVER['HTTP_AUTHORIZATION'] = $value;
	}

	/**
	 * Clear the Authorization header.
	 */
	protected function clear_authorization_header(): void {
		unset( $_SERVER['HTTP_AUTHORIZATION'] );
		unset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		unset( $_SERVER['PHP_AUTH_USER'] );
		unset( $_SERVER['PHP_AUTH_PW'] );
	}

	/**
	 * Set Basic Auth credentials in server vars.
	 *
	 * @param string $username Username.
	 * @param string $password Password.
	 */
	protected function set_basic_auth( string $username, string $password ): void {
		$_SERVER['PHP_AUTH_USER'] = $username;
		$_SERVER['PHP_AUTH_PW']   = $password;

		$encoded = base64_encode( $username . ':' . $password );
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . $encoded;
	}

	/**
	 * Set REQUEST_URI for MCP endpoint testing.
	 *
	 * @param string $path Path to set (default: MCP endpoint).
	 */
	protected function set_mcp_request_uri( string $path = '/wp-json/blu/mcp' ): void {
		$_SERVER['REQUEST_URI'] = $path;
	}

	/**
	 * Asserts that the given value is a WP_Error.
	 *
	 * @param mixed  $actual  Value to check.
	 * @param string $message Optional message.
	 */
	protected function assertWPError( $actual, string $message = '' ): void {
		$this->assertInstanceOf( WP_Error::class, $actual, $message );
	}

	/**
	 * Asserts that the given value is not a WP_Error.
	 *
	 * @param mixed  $actual  Value to check.
	 * @param string $message Optional message.
	 */
	protected function assertNotWPError( $actual, string $message = '' ): void {
		$this->assertNotInstanceOf( WP_Error::class, $actual, $message );
	}

	/**
	 * Assert WP_Error has specific code.
	 *
	 * @param string   $expected_code Expected error code.
	 * @param WP_Error $error         The error object.
	 * @param string   $message       Optional message.
	 */
	protected function assertWPErrorCode( string $expected_code, WP_Error $error, string $message = '' ): void {
		$this->assertEquals(
			$expected_code,
			$error->get_error_code(),
			$message ?: sprintf( 'Expected error code "%s", got "%s"', $expected_code, $error->get_error_code() )
		);
	}
}

