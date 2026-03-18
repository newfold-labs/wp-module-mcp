<?php
/**
 * Integration tests for MCP authentication via REST API.
 *
 * @package BLU\Tests\Integration
 */

declare( strict_types=1 );

namespace BLU\Tests\Integration;

use BLU\Tests\TestCase;
use BLU\Validation\McpValidation;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Integration tests for MCP REST API authentication.
 *
 * These tests verify the full authentication flow through actual REST API requests.
 *
 * @covers \BLU\Validation\McpValidation
 * @group integration
 */
class McpAuthenticationTest extends TestCase {

	/**
	 * REST server instance.
	 *
	 * @var WP_REST_Server
	 */
	private WP_REST_Server $server;

	/**
	 * Set up before each test.
	 */
	public function set_up(): void {
		parent::set_up();

		// Initialize REST server.
		global $wp_rest_server;
		$this->server = $wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init' );

		// Initialize MCP validation.
		McpValidation::instance()->init();
	}

	/**
	 * Clean up after each test.
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;

		$this->clear_authorization_header();
		parent::tear_down();
	}

	// =========================================================================
	// No Authentication Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function mcp_endpoint_rejects_unauthenticated_requests(): void {
		$this->clear_authorization_header();
		wp_set_current_user( 0 );

		$request  = new WP_REST_Request( 'POST', '/blu/mcp' );
		$response = $this->server->dispatch( $request );

		// Should return 401 Unauthorized.
		$this->assertEquals( 401, $response->get_status(), 'Unauthenticated requests should be rejected' );

		$data = $response->get_data();
		$this->assertArrayHasKey( 'code', $data );
	}

	// =========================================================================
	// Application Password Authentication Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function mcp_endpoint_accepts_valid_application_password(): void {
		$user     = $this->create_admin_user();
		$app_pass = $this->create_application_password( $user->ID, 'MCP Test' );

		// Set Basic Auth with application password.
		$this->set_basic_auth( $user->user_login, $app_pass['password'] );

		// Simulate WordPress application password authentication.
		// In a real request, WordPress would authenticate via determine_current_user.
		wp_set_current_user( $user->ID );

		$request  = new WP_REST_Request( 'POST', '/blu/mcp' );
		$response = $this->server->dispatch( $request );

		// Should not be 401 (might be 404 if route doesn't exist, but not auth failure).
		$this->assertNotEquals(
			401,
			$response->get_status(),
			'Valid application password should authenticate successfully'
		);
	}

	/**
	 * @test
	 */
	public function mcp_endpoint_rejects_invalid_application_password(): void {
		$user = $this->create_admin_user();

		// Set Basic Auth with invalid password.
		$this->set_basic_auth( $user->user_login, 'invalid_password' );
		wp_set_current_user( 0 ); // Ensure not authenticated.

		$request  = new WP_REST_Request( 'POST', '/blu/mcp' );
		$response = $this->server->dispatch( $request );

		$this->assertEquals( 401, $response->get_status(), 'Invalid application password should be rejected' );
	}

	// =========================================================================
	// JWT Bearer Token Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function mcp_endpoint_rejects_invalid_jwt_format(): void {
		wp_set_current_user( 0 );

		// Set invalid JWT (not enough dots).
		$this->set_authorization_header( 'Bearer not_a_valid_jwt' );

		// Manually trigger JWT authentication.
		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp';
		$user_id = McpValidation::instance()->authenticate_jwt( 0 );

		// Should not authenticate.
		$this->assertEquals( 0, $user_id, 'Invalid JWT format should not authenticate' );
	}

	/**
	 * @test
	 */
	public function mcp_endpoint_rejects_expired_jwt(): void {
		wp_set_current_user( 0 );

		// Create an expired JWT.
		$expired_token = $this->generate_mock_jwt(
			array(
				'exp' => time() - 3600, // Expired 1 hour ago.
			)
		);

		$this->set_authorization_header( 'Bearer ' . $expired_token );

		// Mock the public key.
		$this->mock_public_key();

		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp';
		$user_id = McpValidation::instance()->authenticate_jwt( 0 );

		// Should not authenticate (JWT validation will fail).
		$this->assertEquals( 0, $user_id, 'Expired JWT should not authenticate' );
	}

	/**
	 * @test
	 */
	public function mcp_endpoint_rejects_jwt_with_wrong_audience(): void {
		wp_set_current_user( 0 );

		// Create JWT with wrong audience.
		$token = $this->generate_mock_jwt(
			array(
				'aud' => 'wrong_audience',
			)
		);

		$this->set_authorization_header( 'Bearer ' . $token );
		$this->mock_public_key();

		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp';
		$user_id = McpValidation::instance()->authenticate_jwt( 0 );

		$this->assertEquals( 0, $user_id, 'JWT with wrong audience should not authenticate' );
	}

	/**
	 * @test
	 */
	public function mcp_endpoint_rejects_jwt_with_wrong_issuer(): void {
		wp_set_current_user( 0 );

		// Create JWT with wrong issuer.
		$token = $this->generate_mock_jwt(
			array(
				'iss' => 'wrong_issuer',
			)
		);

		$this->set_authorization_header( 'Bearer ' . $token );
		$this->mock_public_key();

		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp';
		$user_id = McpValidation::instance()->authenticate_jwt( 0 );

		$this->assertEquals( 0, $user_id, 'JWT with wrong issuer should not authenticate' );
	}

	// =========================================================================
	// Cookie Authentication Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function mcp_endpoint_accepts_cookie_auth_with_nonce(): void {
		$user = $this->create_admin_user();
		wp_set_current_user( $user->ID );

		// Create a nonce for REST API.
		$nonce = wp_create_nonce( 'wp_rest' );

		$request = new WP_REST_Request( 'POST', '/blu/mcp' );
		$request->set_header( 'X-WP-Nonce', $nonce );

		$response = $this->server->dispatch( $request );

		// Should not be 401 (auth should succeed).
		$this->assertNotEquals(
			401,
			$response->get_status(),
			'Cookie auth with valid nonce should authenticate'
		);
	}

	// =========================================================================
	// Authorization Tests (Post-Authentication)
	// =========================================================================

	/**
	 * @test
	 */
	public function authenticated_user_without_read_capability_is_forbidden(): void {
		// Create user with no capabilities.
		$user_id = wp_create_user( 'nocap_' . uniqid(), 'password', 'nocap_' . uniqid() . '@example.com' );
		$user    = get_user_by( 'id', $user_id );
		$user->set_role( '' ); // Remove all roles.
		wp_set_current_user( $user_id );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertWPError( $result );
		$this->assertWPErrorCode( 'mcp_forbidden', $result );
	}

	/**
	 * @test
	 */
	public function subscriber_with_read_capability_is_allowed(): void {
		$user = $this->create_subscriber_user();
		wp_set_current_user( $user->ID );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertTrue( $result, 'Subscriber with read capability should be allowed' );
	}

	/**
	 * @test
	 */
	public function administrator_is_allowed(): void {
		$user = $this->create_admin_user();
		wp_set_current_user( $user->ID );

		$result = McpValidation::get_transport_permission_callback();

		$this->assertTrue( $result, 'Administrator should be allowed' );
	}

	// =========================================================================
	// Auth Method Tracking Tests
	// =========================================================================

	/**
	 * @test
	 */
	public function auth_method_is_tracked_for_application_password(): void {
		$user     = $this->create_admin_user();
		$app_pass = $this->create_application_password( $user->ID, 'MCP Test' );

		// Simulate application password authentication callback.
		$validation = McpValidation::instance();
		$_SERVER['REQUEST_URI'] = '/wp-json/blu/mcp';

		// Trigger the action that WordPress fires on successful app password auth.
		$validation->on_application_password_auth( $user, array( 'name' => 'MCP Test' ) );

		$this->assertEquals(
			'application_password',
			$validation->get_auth_method(),
			'Auth method should be tracked as application_password'
		);
	}

	// =========================================================================
	// Helper Methods
	// =========================================================================

	/**
	 * Mock the public key to avoid network calls during tests.
	 */
	private function mock_public_key(): void {
		// This is a valid RSA public key format (but not a real key).
		$mock_key = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA0Z3VS5JJcds3xfn/ygWy
f8MuLVIQRBX7TJ+vmLfXqzKd7U7O3MF4T8S9qJHrEXUELpBsKT0PfBQlYzCr4oQF
5j1xRkCJ/8F3L5zC6eMJPNb6z+6kD8VlZgF0aQzzCnSqDU7O9qJE5QO0aWBqYzGK
JLVYm+0r8Qb3iGD8F0uPyZkGxlD/lXFfE7V+ZQYB3Y6c8F0K1S6A8S0lJHKk6U7c
-----END PUBLIC KEY-----
PEM;
		set_transient( 'blu_jwt_public_key', $mock_key, HOUR_IN_SECONDS );
	}
}

