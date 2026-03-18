<?php
/**
 * Unit tests for McpValidation class.
 *
 * @package BLU\Tests\Unit\Validation
 */

declare( strict_types=1 );

namespace BLU\Tests\Unit\Validation;

use BLU\Tests\TestCase;
use BLU\Validation\McpValidation;
use BLU\Validation\AuthMethod;
use WP_Error;
use ReflectionClass;
use ReflectionMethod;

/**
 * Tests for the McpValidation authentication handler.
 *
 * @covers \BLU\Validation\McpValidation
 */
class McpValidationTest extends TestCase {

	/**
	 * McpValidation instance for testing.
	 *
	 * @var McpValidation
	 */
	private McpValidation $validation;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Get a fresh instance (bypass singleton for testing).
		$this->validation = $this->create_fresh_instance();
		$this->clear_authorization_header();
	}

	/**
	 * Create a fresh McpValidation instance for testing.
	 *
	 * Uses reflection to bypass the singleton pattern.
	 *
	 * @return McpValidation
	 */
	private function create_fresh_instance(): McpValidation {
		$reflection = new ReflectionClass( McpValidation::class );
		$instance   = $reflection->newInstanceWithoutConstructor();

		// Reset the singleton instance.
		$instance_property = $reflection->getProperty( 'instance' );
		$instance_property->setAccessible( true );
		$instance_property->setValue( null, null );

		return McpValidation::instance();
	}

	/**
	 * Get a private/protected method for testing.
	 *
	 * @param string $method_name Method name.
	 * @return ReflectionMethod
	 */
	private function get_method( string $method_name ): ReflectionMethod {
		$reflection = new ReflectionClass( McpValidation::class );
		$method     = $reflection->getMethod( $method_name );
		$method->setAccessible( true );
		return $method;
	}

	// =========================================================================
	// Singleton Pattern Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function instance_returns_singleton(): void {
		$instance1 = McpValidation::instance();
		$instance2 = McpValidation::instance();

		$this->assertSame( $instance1, $instance2, 'instance() should return the same instance' );
	}

	// =========================================================================
	// Authorization Header Detection Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function get_authorization_header_returns_http_authorization(): void {
		$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer test_token';

		$method = $this->get_method( 'get_authorization_header' );
		$result = $method->invoke( $this->validation );

		$this->assertEquals( 'Bearer test_token', $result );
	}

	/**
	 * @test
	 */
	public function get_authorization_header_returns_redirect_http_authorization(): void {
		unset( $_SERVER['HTTP_AUTHORIZATION'] );
		$_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer redirected_token';

		$method = $this->get_method( 'get_authorization_header' );
		$result = $method->invoke( $this->validation );

		$this->assertEquals( 'Bearer redirected_token', $result );
	}

	/**
	 * @test
	 */
	public function get_authorization_header_returns_empty_when_no_header(): void {
		$this->clear_authorization_header();

		$method = $this->get_method( 'get_authorization_header' );
		$result = $method->invoke( $this->validation );

		$this->assertEquals( '', $result );
	}

	// =========================================================================
	// Bearer Token Detection Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function has_bearer_token_returns_true_for_valid_bearer(): void {
		$method = $this->get_method( 'has_bearer_token' );

		$this->assertTrue( $method->invoke( $this->validation, 'Bearer abc123' ) );
		$this->assertTrue( $method->invoke( $this->validation, 'Bearer eyJhbGciOiJSUzI1NiJ9.test.sig' ) );
	}

	/**
	 * @test
	 */
	public function has_bearer_token_returns_false_for_invalid_formats(): void {
		$method = $this->get_method( 'has_bearer_token' );

		$this->assertFalse( $method->invoke( $this->validation, 'Basic abc123' ) );
		$this->assertFalse( $method->invoke( $this->validation, 'bearer abc123' ) ); // Case sensitive.
		$this->assertFalse( $method->invoke( $this->validation, 'Bearer' ) ); // No token.
		$this->assertFalse( $method->invoke( $this->validation, '' ) );
	}

	/**
	 * @test
	 */
	public function extract_bearer_token_returns_token(): void {
		$method = $this->get_method( 'extract_bearer_token' );

		$result = $method->invoke( $this->validation, 'Bearer my_token_123' );

		$this->assertEquals( 'my_token_123', $result );
	}

	/**
	 * @test
	 */
	public function extract_bearer_token_returns_null_for_invalid(): void {
		$method = $this->get_method( 'extract_bearer_token' );

		$this->assertNull( $method->invoke( $this->validation, 'Basic abc123' ) );
		$this->assertNull( $method->invoke( $this->validation, '' ) );
	}

	// =========================================================================
	// MCP Request Detection Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function is_mcp_request_returns_true_for_mcp_endpoints(): void {
		$method = $this->get_method( 'is_mcp_request' );

		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp';
		$this->assertTrue( $method->invoke( $this->validation ) );

		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp/';
		$this->assertTrue( $method->invoke( $this->validation ) );

		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp?param=value';
		$this->assertTrue( $method->invoke( $this->validation ) );
	}

	/**
	 * @test
	 */
	public function is_mcp_request_returns_false_for_non_mcp_endpoints(): void {
		$method = $this->get_method( 'is_mcp_request' );

		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
		$this->assertFalse( $method->invoke( $this->validation ) );

		$_SERVER['REQUEST_URI'] = '/wp-admin/';
		$this->assertFalse( $method->invoke( $this->validation ) );

		$_SERVER['REQUEST_URI'] = '/';
		$this->assertFalse( $method->invoke( $this->validation ) );
	}

	// =========================================================================
	// Permission Callback Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function permission_callback_returns_error_when_not_logged_in(): void {
		wp_set_current_user( 0 );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertWPError( $result );
		$this->assertWPErrorCode( 'mcp_not_authenticated', $result );
	}

	/**
	 * @test
	 */
	public function permission_callback_returns_true_for_authenticated_user_with_capability(): void {
		$user = $this->create_admin_user();
		wp_set_current_user( $user->ID );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertTrue( $result );
	}

	/**
	 * @test
	 */
	public function permission_callback_returns_error_for_user_without_capability(): void {
		// Create a user with no capabilities.
		$user_id = wp_create_user( 'nocap_user_' . uniqid(), 'password', 'nocap_' . uniqid() . '@example.com' );
		$user    = get_user_by( 'id', $user_id );
		$user->set_role( '' ); // Remove all roles.
		wp_set_current_user( $user_id );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertWPError( $result );
		$this->assertWPErrorCode( 'mcp_forbidden', $result );
	}

	// =========================================================================
	// JWT Validation Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function validate_jwt_token_rejects_invalid_format(): void {
		$method = $this->get_method( 'validate_jwt_token' );

		// No dots.
		$result = $method->invoke( $this->validation, 'not_a_jwt_token' );
		$this->assertWPError( $result );
		$this->assertWPErrorCode( 'mcp_invalid_jwt_format', $result );

		// Only one dot.
		$result = $method->invoke( $this->validation, 'header.payload' );
		$this->assertWPError( $result );
		$this->assertWPErrorCode( 'mcp_invalid_jwt_format', $result );
	}

	/**
	 * @test
	 */
	public function validate_jwt_token_rejects_token_with_invalid_signature(): void {
		// Mock a valid-looking but unsigned JWT.
		$token = $this->generate_mock_jwt();

		// Set a mock public key to avoid network call.
		$mock_key = "-----BEGIN PUBLIC KEY-----\nMIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0Z3VS5JJcds3xfn/ygWyf8Mu\n-----END PUBLIC KEY-----";
		set_transient( 'blu_jwt_public_key', $mock_key, HOUR_IN_SECONDS );

		$method = $this->get_method( 'validate_jwt_token' );
		$result = $method->invoke( $this->validation, $token );

		// Should fail signature validation.
		$this->assertWPError( $result );
	}

	// =========================================================================
	// Authentication Flow Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function authenticate_jwt_returns_original_user_id_for_non_mcp_request(): void {
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
		$this->set_authorization_header( 'Bearer some_token' );

		$result = $this->validation->authenticate_jwt( 0 );

		$this->assertEquals( 0, $result, 'Should not process JWT for non-MCP endpoints' );
	}

	/**
	 * @test
	 */
	public function authenticate_jwt_returns_original_user_id_when_already_set(): void {
		$this->set_mcp_request_uri();
		$this->set_authorization_header( 'Bearer some_token' );

		$result = $this->validation->authenticate_jwt( 123 );

		$this->assertEquals( 123, $result, 'Should not override existing user ID' );
	}

	/**
	 * @test
	 */
	public function authenticate_jwt_returns_original_user_id_when_no_bearer_token(): void {
		$this->set_mcp_request_uri();
		$this->clear_authorization_header();

		$result = $this->validation->authenticate_jwt( 0 );

		$this->assertEquals( 0, $result, 'Should not process when no Bearer token present' );
	}

	/**
	 * @test
	 */
	public function authenticate_jwt_returns_original_user_id_for_basic_auth(): void {
		$this->set_mcp_request_uri();
		$this->set_basic_auth( 'username', 'password' );

		$result = $this->validation->authenticate_jwt( 0 );

		$this->assertEquals( 0, $result, 'Should not process Basic Auth as JWT' );
	}

	// =========================================================================
	// Validate Authentication Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function validate_authentication_passes_through_existing_errors(): void {
		$existing_error = new WP_Error( 'existing_error', 'An error occurred' );

		$result = $this->validation->validate_authentication( $existing_error );

		$this->assertWPError( $result );
		$this->assertWPErrorCode( 'existing_error', $result );
	}

	/**
	 * @test
	 */
	public function validate_authentication_returns_null_for_non_mcp_requests(): void {
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';

		$result = $this->validation->validate_authentication( null );

		$this->assertNull( $result );
	}

	// =========================================================================
	// Auth Method Detection Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function get_auth_method_returns_none_when_not_logged_in(): void {
		wp_set_current_user( 0 );

		$result = $this->validation->get_auth_method();

		$this->assertEquals( AuthMethod::NONE, $result );
	}

	/**
	 * @test
	 */
	public function get_auth_method_returns_correct_method_after_jwt_auth(): void {
		// Simulate JWT authentication by setting the internal property.
		$reflection = new ReflectionClass( $this->validation );
		$property   = $reflection->getProperty( 'auth_method' );
		$property->setAccessible( true );
		$property->setValue( $this->validation, AuthMethod::JWT_BEARER );

		$result = $this->validation->get_auth_method();

		$this->assertEquals( AuthMethod::JWT_BEARER, $result );
	}

	// =========================================================================
	// Integration with WordPress Hooks Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function init_registers_required_hooks(): void {
		// Reset to ensure clean state.
		$fresh_instance = $this->create_fresh_instance();
		$fresh_instance->init();

		// Check determine_current_user filter is registered.
		$this->assertNotFalse(
			has_filter( 'determine_current_user', array( $fresh_instance, 'authenticate_jwt' ) ),
			'authenticate_jwt should be hooked to determine_current_user'
		);

		// Check rest_authentication_errors filter is registered.
		$this->assertNotFalse(
			has_filter( 'rest_authentication_errors', array( $fresh_instance, 'validate_authentication' ) ),
			'validate_authentication should be hooked to rest_authentication_errors'
		);

		// Check application password action is registered.
		$this->assertNotFalse(
			has_action( 'application_password_did_authenticate', array( $fresh_instance, 'on_application_password_auth' ) ),
			'on_application_password_auth should be hooked to application_password_did_authenticate'
		);
	}
}

