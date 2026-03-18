<?php
/**
 * WordPress Unit Tests for McpValidation
 *
 * These tests run in a real WordPress environment with database access.
 * They test the actual integration with WordPress functions and database.
 *
 * @package BLU\Tests\WPUnit
 * @coversDefaultClass \BLU\Validation\McpValidation
 */

namespace BLU\Validation;

use WP_Error;
use ReflectionClass;

/**
 * Tests for the McpValidation authentication handler.
 */
class McpValidationWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * McpValidation instance for testing.
	 *
	 * @var McpValidation
	 */
	private McpValidation $validation;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset singleton and get fresh instance.
		$this->resetSingleton();
		$this->validation = McpValidation::instance();

		// Clear any auth state.
		wp_set_current_user( 0 );
		$this->clearAuthHeaders();

		// Clear cached data.
		delete_transient( 'nfd_blu_mcp_jwt_user' );
		delete_transient( 'blu_jwt_public_key' );
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		wp_set_current_user( 0 );
		$this->clearAuthHeaders();
		parent::tearDown();
	}

	/**
	 * Reset the singleton instance for testing.
	 */
	private function resetSingleton(): void {
		$reflection = new ReflectionClass( McpValidation::class );
		$property   = $reflection->getProperty( 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * Clear all auth-related headers.
	 */
	private function clearAuthHeaders(): void {
		unset( $_SERVER['HTTP_AUTHORIZATION'] );
		unset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		unset( $_SERVER['PHP_AUTH_USER'] );
		unset( $_SERVER['PHP_AUTH_PW'] );
		unset( $_SERVER['REQUEST_URI'] );
	}

	/**
	 * Get a private/protected method for testing.
	 *
	 * @param string $method_name Method name.
	 * @return \ReflectionMethod
	 */
	private function getMethod( string $method_name ): \ReflectionMethod {
		$reflection = new ReflectionClass( McpValidation::class );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method;
	}

	// =========================================================================
	// Singleton Tests
	// =========================================================================

	/**
	 * Test that instance() returns singleton.
	 */
	public function test_instance_returns_singleton(): void {
		$instance1 = McpValidation::instance();
		$instance2 = McpValidation::instance();

		$this->assertSame( $instance1, $instance2 );
	}

	// =========================================================================
	// Authorization Header Detection Tests
	// =========================================================================

	/**
	 * Test get_authorization_header returns HTTP_AUTHORIZATION.
	 */
	public function test_get_authorization_header_returns_http_authorization(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_token';

		$method = $this->getMethod( 'get_authorization_header' );
		$result = $method->invoke( $this->validation );

		$this->assertEquals( 'Bearer test_token', $result );
	}

	/**
	 * Test get_authorization_header returns REDIRECT_HTTP_AUTHORIZATION.
	 */
	public function test_get_authorization_header_returns_redirect_header(): void {
		$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer redirected_token';

		$method = $this->getMethod( 'get_authorization_header' );
		$result = $method->invoke( $this->validation );

		$this->assertEquals( 'Bearer redirected_token', $result );
	}

	/**
	 * Test get_authorization_header returns empty when no header.
	 */
	public function test_get_authorization_header_returns_empty_when_no_header(): void {
		$method = $this->getMethod( 'get_authorization_header' );
		$result = $method->invoke( $this->validation );

		$this->assertEquals( '', $result );
	}

	// =========================================================================
	// Bearer Token Detection Tests
	// =========================================================================

	/**
	 * Test has_bearer_token returns true for valid bearer.
	 */
	public function test_has_bearer_token_returns_true_for_valid_bearer(): void {
		$method = $this->getMethod( 'has_bearer_token' );

		$this->assertTrue( $method->invoke( $this->validation, 'Bearer abc123' ) );
		$this->assertTrue( $method->invoke( $this->validation, 'Bearer eyJhbGciOiJSUzI1NiJ9.test.sig' ) );
	}

	/**
	 * Test has_bearer_token returns false for invalid formats.
	 */
	public function test_has_bearer_token_returns_false_for_invalid(): void {
		$method = $this->getMethod( 'has_bearer_token' );

		$this->assertFalse( $method->invoke( $this->validation, 'Basic abc123' ) );
		$this->assertFalse( $method->invoke( $this->validation, '' ) );
	}

	/**
	 * Test extract_bearer_token returns token.
	 */
	public function test_extract_bearer_token_returns_token(): void {
		$method = $this->getMethod( 'extract_bearer_token' );

		$result = $method->invoke( $this->validation, 'Bearer my_token_123' );

		$this->assertEquals( 'my_token_123', $result );
	}

	/**
	 * Test extract_bearer_token returns null for invalid.
	 */
	public function test_extract_bearer_token_returns_null_for_invalid(): void {
		$method = $this->getMethod( 'extract_bearer_token' );

		$this->assertNull( $method->invoke( $this->validation, 'Basic abc123' ) );
		$this->assertNull( $method->invoke( $this->validation, '' ) );
	}

	// =========================================================================
	// MCP Request Detection Tests
	// =========================================================================

	/**
	 * Test is_mcp_request returns true for MCP endpoints.
	 */
	public function test_is_mcp_request_returns_true_for_mcp_endpoints(): void {
		$method = $this->getMethod( 'is_mcp_request' );

		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp';
		$this->assertTrue( $method->invoke( $this->validation ) );

		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp/';
		$this->assertTrue( $method->invoke( $this->validation ) );

		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp?param=value';
		$this->assertTrue( $method->invoke( $this->validation ) );
	}

	/**
	 * Test is_mcp_request returns false for non-MCP endpoints.
	 */
	public function test_is_mcp_request_returns_false_for_non_mcp(): void {
		$method = $this->getMethod( 'is_mcp_request' );

		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
		$this->assertFalse( $method->invoke( $this->validation ) );

		$_SERVER['REQUEST_URI'] = '/wp-admin/';
		$this->assertFalse( $method->invoke( $this->validation ) );
	}

	// =========================================================================
	// Permission Callback Tests
	// =========================================================================

	/**
	 * Test permission callback returns error when not logged in.
	 */
	public function test_permission_callback_returns_error_when_not_logged_in(): void {
		wp_set_current_user( 0 );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'mcp_not_authenticated', $result->get_error_code() );
	}

	/**
	 * Test permission callback returns true for admin.
	 */
	public function test_permission_callback_returns_true_for_admin(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertTrue( $result );
	}

	/**
	 * Test permission callback returns true for subscriber with read capability.
	 */
	public function test_permission_callback_returns_true_for_subscriber(): void {
		$user_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user_id );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertTrue( $result );
	}

	/**
	 * Test permission callback returns error for user without capability.
	 */
	public function test_permission_callback_returns_error_without_capability(): void {
		$user_id = $this->factory()->user->create( array( 'role' => '' ) );
		wp_set_current_user( $user_id );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'mcp_forbidden', $result->get_error_code() );
	}

	// =========================================================================
	// JWT Validation Tests
	// =========================================================================

	/**
	 * Test validate_jwt_token rejects invalid format.
	 */
	public function test_validate_jwt_token_rejects_invalid_format(): void {
		$method = $this->getMethod( 'validate_jwt_token' );

		// No dots.
		$result = $method->invoke( $this->validation, 'not_a_jwt_token' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'mcp_invalid_jwt_format', $result->get_error_code() );

		// Only one dot.
		$result = $method->invoke( $this->validation, 'header.payload' );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'mcp_invalid_jwt_format', $result->get_error_code() );
	}

	// =========================================================================
	// Authentication Flow Tests
	// =========================================================================

	/**
	 * Test authenticate_jwt ignores non-MCP requests.
	 */
	public function test_authenticate_jwt_ignores_non_mcp_requests(): void {
		$_SERVER['REQUEST_URI']        = '/wp-json/wp/v2/posts';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer some_token';

		$result = $this->validation->authenticate_jwt( 0 );

		$this->assertEquals( 0, $result );
	}

	/**
	 * Test authenticate_jwt does not override existing user.
	 */
	public function test_authenticate_jwt_does_not_override_existing_user(): void {
		$_SERVER['REQUEST_URI']        = '/wp-json/blu/mcp';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer some_token';

		$result = $this->validation->authenticate_jwt( 123 );

		$this->assertEquals( 123, $result );
	}

	/**
	 * Test authenticate_jwt ignores requests without bearer token.
	 */
	public function test_authenticate_jwt_ignores_basic_auth(): void {
		$_SERVER['REQUEST_URI']        = '/wp-json/blu/mcp';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( 'user:pass' );

		$result = $this->validation->authenticate_jwt( 0 );

		$this->assertEquals( 0, $result );
	}

	// =========================================================================
	// Validate Authentication Tests
	// =========================================================================

	/**
	 * Test validate_authentication passes through existing errors.
	 */
	public function test_validate_authentication_passes_through_errors(): void {
		$existing_error = new WP_Error( 'existing_error', 'An error occurred' );

		$result = $this->validation->validate_authentication( $existing_error );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 'existing_error', $result->get_error_code() );
	}

	/**
	 * Test validate_authentication returns null for non-MCP requests.
	 */
	public function test_validate_authentication_returns_null_for_non_mcp(): void {
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';

		$result = $this->validation->validate_authentication( null );

		$this->assertNull( $result );
	}

	// =========================================================================
	// Auth Method Tracking Tests
	// =========================================================================

	/**
	 * Test get_auth_method returns none when not logged in.
	 */
	public function test_get_auth_method_returns_none_when_not_logged_in(): void {
		wp_set_current_user( 0 );

		$result = $this->validation->get_auth_method();

		$this->assertEquals( AuthMethod::NONE, $result );
	}

	// =========================================================================
	// Hook Registration Tests
	// =========================================================================

	/**
	 * Test init registers required hooks.
	 */
	public function test_init_registers_required_hooks(): void {
		$this->resetSingleton();
		$validation = McpValidation::instance();
		$validation->init();

		$this->assertNotFalse(
			has_filter( 'determine_current_user', array( $validation, 'authenticate_jwt' ) )
		);

		$this->assertNotFalse(
			has_filter( 'rest_authentication_errors', array( $validation, 'validate_authentication' ) )
		);

		$this->assertNotFalse(
			has_action( 'application_password_did_authenticate', array( $validation, 'on_application_password_auth' ) )
		);
	}
}

