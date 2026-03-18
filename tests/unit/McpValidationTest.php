<?php
/**
 * Unit Tests for McpValidation
 *
 * Uses Brain Monkey to mock WordPress functions.
 *
 * @package BLU\Tests\Unit
 */

namespace BLU\Tests\Unit;

use BLU\Validation\McpValidation;
use BLU\Validation\AuthMethod;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

// Mock WP_Error class before any tests run.
if ( ! class_exists( 'WP_Error' ) ) {
	require_once __DIR__ . '/../mocks/class-wp-error.php';
}

/**
 * Tests for the McpValidation authentication handler.
 */
class McpValidationTest extends TestCase {

	/**
	 * McpValidation instance for testing.
	 *
	 * @var McpValidation
	 */
	private McpValidation $validation;

	/**
	 * Set up test environment.
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		// Initialize GLOBALS needed by McpValidation.
		$GLOBALS['wp'] = new \stdClass();
		$GLOBALS['wp']->query_vars = array();

		// Mock WordPress functions used by McpValidation.
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'delete_transient' )->justReturn( true );
		Functions\when( 'wp_remote_get' )->justReturn( array( 'body' => '' ) );
		Functions\when( 'is_wp_error' )->alias( function( $thing ) {
			return $thing instanceof \WP_Error;
		});
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '' );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'absint' )->alias( 'intval' );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'apply_filters' )->returnArg( 2 );
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'wp_validate_auth_cookie' )->justReturn( false );
		Functions\when( 'user_can' )->justReturn( false );
		Functions\when( 'get_users' )->justReturn( array() );

		// Reset singleton and get fresh instance.
		$this->resetSingleton();
		$this->validation = McpValidation::instance();

		// Clear any auth state.
		$this->clearAuthHeaders();
	}

	/**
	 * Tear down test environment.
	 */
	protected function tearDown(): void {
		$this->clearAuthHeaders();
		unset( $GLOBALS['wp'] );
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Reset the singleton instance for testing.
	 */
	private function resetSingleton(): void {
		$reflection = new ReflectionClass( McpValidation::class );

		// Reset static instance.
		$instance = $reflection->getProperty( 'instance' );
		$instance->setAccessible( true );
		$instance->setValue( null, null );
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
		$this->assertFalse( $method->invoke( $this->validation, 'Bearerabc123' ) ); // No space.
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

		$_SERVER['REQUEST_URI'] = '';
		$this->assertFalse( $method->invoke( $this->validation ) );
	}

	/**
	 * Test is_mcp_request uses REST route from globals when available.
	 */
	public function test_is_mcp_request_uses_rest_route_from_globals(): void {
		$method = $this->getMethod( 'is_mcp_request' );

		// Set REST route in globals.
		$GLOBALS['wp']->query_vars['rest_route'] = '/blu/mcp';
		$_SERVER['REQUEST_URI'] = '/something-else';

		$this->assertTrue( $method->invoke( $this->validation ) );
	}

	// =========================================================================
	// JWT Format Validation Tests
	// =========================================================================

	/**
	 * Test validate_jwt_token rejects invalid format.
	 */
	public function test_validate_jwt_token_rejects_invalid_format(): void {
		$method = $this->getMethod( 'validate_jwt_token' );

		// No dots.
		$result = $method->invoke( $this->validation, 'not_a_jwt_token' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'mcp_invalid_jwt_format', $result->get_error_code() );

		// Only one dot.
		$result = $method->invoke( $this->validation, 'header.payload' );
		$this->assertInstanceOf( \WP_Error::class, $result );
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

	/**
	 * Test authenticate_jwt processes MCP request with Bearer token.
	 */
	public function test_authenticate_jwt_processes_bearer_token(): void {
		$_SERVER['REQUEST_URI']        = '/wp-json/blu/mcp';
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer header.payload.signature';

		// Should attempt to validate (will fail due to invalid JWT, but should try).
		$result = $this->validation->authenticate_jwt( 0 );

		// Returns 0 because JWT validation fails (expected - we're not providing a valid token).
		$this->assertEquals( 0, $result );
	}

	// =========================================================================
	// Auth Method Constants Tests
	// =========================================================================

	/**
	 * Test AuthMethod constants exist and have expected values.
	 */
	public function test_auth_method_constants(): void {
		$this->assertEquals( 'none', AuthMethod::NONE );
		$this->assertEquals( 'jwt_bearer', AuthMethod::JWT_BEARER );
		$this->assertEquals( 'application_password', AuthMethod::APPLICATION_PASSWORD );
		$this->assertEquals( 'cookie', AuthMethod::COOKIE );
		$this->assertEquals( 'unknown', AuthMethod::UNKNOWN );
	}

	// =========================================================================
	// Permission Callback Tests
	// =========================================================================

	/**
	 * Test permission callback returns error when not logged in.
	 */
	public function test_permission_callback_returns_error_when_not_logged_in(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'mcp_not_authenticated', $result->get_error_code() );
	}

	/**
	 * Test permission callback returns error when lacking capability.
	 */
	public function test_permission_callback_returns_error_without_capability(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( false );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertEquals( 'mcp_forbidden', $result->get_error_code() );
	}

	/**
	 * Test permission callback returns true for authorized user.
	 */
	public function test_permission_callback_returns_true_for_authorized_user(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( true );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertTrue( $result );
	}

	// =========================================================================
	// Validate Authentication Tests
	// =========================================================================

	/**
	 * Test validate_authentication passes through existing errors.
	 */
	public function test_validate_authentication_passes_through_errors(): void {
		$existing_error = new \WP_Error( 'existing_error', 'An error occurred' );

		$result = $this->validation->validate_authentication( $existing_error );

		$this->assertInstanceOf( \WP_Error::class, $result );
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
	// Get Auth Method Tests
	// =========================================================================

	/**
	 * Test get_auth_method returns none when not logged in.
	 */
	public function test_get_auth_method_returns_none_when_not_logged_in(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( false );

		$result = $this->validation->get_auth_method();

		$this->assertEquals( AuthMethod::NONE, $result );
	}

	/**
	 * Test get_auth_method returns cookie when logged in via cookie.
	 */
	public function test_get_auth_method_returns_cookie_when_logged_in_via_cookie(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'wp_validate_auth_cookie' )->justReturn( 1 );

		$result = $this->validation->get_auth_method();

		$this->assertEquals( AuthMethod::COOKIE, $result );
	}

	/**
	 * Test get_auth_method returns unknown when logged in but not via cookie.
	 */
	public function test_get_auth_method_returns_unknown_when_logged_in_without_cookie(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		Functions\when( 'wp_validate_auth_cookie' )->justReturn( false );

		$result = $this->validation->get_auth_method();

		$this->assertEquals( AuthMethod::UNKNOWN, $result );
	}
}
