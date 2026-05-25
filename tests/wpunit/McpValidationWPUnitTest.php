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
	 * A non-empty Bearer token drives is_authenticated through the is_valid_token
	 * code path, which currently short-circuits by setting an admin user and
	 * returning true. We seed an administrator so set_admin_authentication has
	 * one to promote, and assert the overall result is true.
	 *
	 * @return void
	 */
	public function test_is_authenticated_with_bearer_token_runs_admin_promotion_path() {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( 0 );
		delete_transient( 'nfd_blu_mcp_user' );

		$request = new \WP_REST_Request();
		$request->set_header( 'Authorization', 'Bearer header.payload.signature' );
		$validator = new McpValidation( $request );

		$this->assertTrue( $validator->is_authenticated() );
		$this->assertSame( $admin_id, get_current_user_id() );
	}

	/**
	 * Once set_admin_authentication has cached an admin user in the
	 * nfd_blu_mcp_user transient, subsequent is_authenticated calls
	 * reuse the cached id without re-running get_users().
	 *
	 * @return void
	 */
	public function test_is_authenticated_reuses_cached_admin_user_from_transient() {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		set_transient( 'nfd_blu_mcp_user', $admin_id, HOUR_IN_SECONDS );
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request();
		$request->set_header( 'Authorization', 'Bearer header.payload.signature' );
		$validator = new McpValidation( $request );

		$this->assertTrue( $validator->is_authenticated() );
		$this->assertSame( $admin_id, get_current_user_id() );

		delete_transient( 'nfd_blu_mcp_user' );
	}

	/**
	 * If the transient holds a user id that no longer has manage_options,
	 * set_admin_authentication falls back to looking up a fresh administrator.
	 *
	 * @return void
	 */
	public function test_is_authenticated_falls_back_when_cached_user_loses_capability() {
		$admin_id      = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		$subscriber_id = $this->factory()->user->create( array( 'role' => 'subscriber' ) );
		set_transient( 'nfd_blu_mcp_user', $subscriber_id, HOUR_IN_SECONDS );
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request();
		$request->set_header( 'Authorization', 'Bearer header.payload.signature' );
		$validator = new McpValidation( $request );

		$this->assertTrue( $validator->is_authenticated() );
		$this->assertSame( $admin_id, get_current_user_id() );

		delete_transient( 'nfd_blu_mcp_user' );
	}

	/**
	 * If there is no admin user on the site at all, set_admin_authentication throws
	 * and is_authenticated swallows it as a false result.
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

	/**
	 * Authorization header value is case-insensitive on the Bearer keyword.
	 *
	 * @return void
	 */
	public function test_is_authenticated_accepts_lowercase_bearer_keyword() {
		$this->factory()->user->create( array( 'role' => 'administrator' ) );
		delete_transient( 'nfd_blu_mcp_user' );
		wp_set_current_user( 0 );

		$request = new \WP_REST_Request();
		$request->set_header( 'Authorization', 'bearer header.payload.signature' );
		$validator = new McpValidation( $request );

		$this->assertTrue( $validator->is_authenticated() );
	}
}
