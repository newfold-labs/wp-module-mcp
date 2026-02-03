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
	 * is_authenticated returns true when user is logged in as admin.
	 *
	 * @return void
	 */
	public function test_is_authenticated_returns_true_when_admin_logged_in() {
		$admin_id = $this->factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$request = new \WP_REST_Request();
		$validator = new McpValidation( $request );

		$this->assertTrue( $validator->is_authenticated() );
	}

	/**
	 * is_authenticated returns false when no auth header and not logged in.
	 *
	 * @return void
	 */
	public function test_is_authenticated_returns_false_when_no_auth_header() {
		wp_set_current_user( 0 );

		$request  = new \WP_REST_Request();
		$validator = new McpValidation( $request );

		$this->assertFalse( $validator->is_authenticated() );
	}
}
