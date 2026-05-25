<?php

namespace BLU;

use BLU\Validation\McpValidation;

/**
 * Tests for McpValidation.
 *
 * @covers \BLU\Validation\McpValidation
 */
class McpValidationWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Verifies is_authenticated returns true when user is logged in as admin.
	 *
	 * @return void
	 */
	public function test_is_authenticated_returns_true_when_admin_logged_in() {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$request   = new \WP_REST_Request();
		$validator = new McpValidation( $request );

		$this->assertTrue( $validator->is_authenticated() );
	}

	/**
	 * Verifies is_authenticated returns false when no auth header and not logged in.
	 *
	 * @return void
	 */
	public function test_is_authenticated_returns_false_when_no_auth_header() {
		wp_set_current_user( 0 );

		$request   = new \WP_REST_Request();
		$validator = new McpValidation( $request );

		$this->assertFalse( $validator->is_authenticated() );
	}

	/**
	 * Verifies is_authenticated returns false when Authorization header has no Bearer token.
	 *
	 * @return void
	 */
	public function test_is_authenticated_returns_false_when_bearer_token_missing() {
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request();
		$request->set_header( 'Authorization', 'Bearer ' );
		$validator = new McpValidation( $request );

		$this->assertFalse( $validator->is_authenticated() );
	}

	/**
	 * Verifies is_authenticated returns false when Authorization header is not Bearer format.
	 *
	 * @return void
	 */
	public function test_is_authenticated_returns_false_when_auth_header_not_bearer() {
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request();
		$request->set_header( 'Authorization', 'Basic dXNlcjpwYXNz' );
		$validator = new McpValidation( $request );

		$this->assertFalse( $validator->is_authenticated() );
	}

	/**
	 * If there is no admin user on the site at all, set_admin_authentication throws
	 * and is_authenticated swallows it as a false result. Drives execution through
	 * is_valid_token -> set_admin_authentication, which the four passing tests above
	 * never reach.
	 *
	 * @return void
	 */
	public function test_is_authenticated_returns_false_when_no_admin_user_exists() {
		// Strip any existing administrators so get_users returns empty.
		$admins = get_users( array( 'role' => 'administrator' ) );
		foreach ( $admins as $admin ) {
			wp_delete_user( $admin->ID );
		}
		delete_transient( 'nfd_blu_mcp_user' );
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request();
		$request->set_header( 'Authorization', 'Bearer header.payload.signature' );
		$validator = new McpValidation( $request );

		$this->assertFalse( $validator->is_authenticated() );
	}
}
