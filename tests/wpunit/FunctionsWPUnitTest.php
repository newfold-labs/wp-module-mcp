<?php

namespace BLU;

/**
 * Tests for BLU global functions.
 *
 * @coversNothing
 */
class FunctionsWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * blu_get_status_type returns success for 2xx and 3xx.
	 *
	 * @return void
	 */
	public function test_blu_get_status_type_success() {
		$this->assertSame( 'success', blu_get_status_type( 200 ) );
		$this->assertSame( 'success', blu_get_status_type( 201 ) );
		$this->assertSame( 'success', blu_get_status_type( 301 ) );
	}

	/**
	 * blu_get_status_type returns error for 4xx and 5xx.
	 *
	 * @return void
	 */
	public function test_blu_get_status_type_error() {
		$this->assertSame( 'error', blu_get_status_type( 400 ) );
		$this->assertSame( 'error', blu_get_status_type( 404 ) );
		$this->assertSame( 'error', blu_get_status_type( 500 ) );
	}

	/**
	 * blu_get_status_type returns unknown for other codes.
	 *
	 * @return void
	 */
	public function test_blu_get_status_type_unknown() {
		$this->assertSame( 'unknown', blu_get_status_type( 0 ) );
		$this->assertSame( 'unknown', blu_get_status_type( 100 ) );
	}

	/**
	 * blu_prepare_ability_response returns array with statusCode, status, message.
	 *
	 * @return void
	 */
	public function test_blu_prepare_ability_response() {
		$result = blu_prepare_ability_response( 200, array( 'key' => 'value' ) );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'statusCode', $result );
		$this->assertArrayHasKey( 'status', $result );
		$this->assertArrayHasKey( 'message', $result );
		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( array( 'key' => 'value' ), $result['message'] );
	}

	/**
	 * blu_standardize_rest_response with WP_Error returns error format.
	 *
	 * @return void
	 */
	public function test_blu_standardize_rest_response_wp_error() {
		$error  = new \WP_Error( 'code', 'Error message' );
		$result = blu_standardize_rest_response( $error );
		$this->assertIsArray( $result );
		$this->assertSame( 'code', $result['statusCode'] );
		$this->assertSame( 'Error message', $result['message'] );
	}

	/**
	 * blu_standardize_rest_response with WP_REST_Response returns response format.
	 *
	 * @return void
	 */
	public function test_blu_standardize_rest_response_rest_response() {
		$response = new \WP_REST_Response( array( 'data' => 'value' ), 201 );
		$result   = blu_standardize_rest_response( $response );
		$this->assertIsArray( $result );
		$this->assertSame( 201, $result['statusCode'] );
		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( array( 'data' => 'value' ), $result['message'] );
	}

	/**
	 * blu_standardize_rest_response with unexpected type returns 500.
	 *
	 * @return void
	 */
	public function test_blu_standardize_rest_response_unexpected() {
		$result = blu_standardize_rest_response( 'not valid' );
		$this->assertSame( 500, $result['statusCode'] );
		$this->assertSame( 'Unexpected response format.', $result['message'] );
	}

	/**
	 * blu_get_abilities_by_category with no abilities returns empty array.
	 *
	 * @return void
	 */
	public function test_blu_get_abilities_by_category_empty() {
		$abilities = blu_get_abilities_by_category( 'blu-mcp' );
		$this->assertIsArray( $abilities );
	}

	/**
	 * blu_filter_abilities_by_category with empty array returns empty.
	 *
	 * @return void
	 */
	public function test_blu_filter_abilities_by_category_empty() {
		$filtered = blu_filter_abilities_by_category( array(), 'blu-mcp' );
		$this->assertSame( array(), $filtered );
	}

	/**
	 * blu_filter_abilities_by_namespace with empty array returns empty.
	 *
	 * @return void
	 */
	public function test_blu_filter_abilities_by_namespace_empty() {
		$filtered = blu_filter_abilities_by_namespace( array(), 'blu' );
		$this->assertSame( array(), $filtered );
	}

	/**
	 * blu_get_ability_categories returns array.
	 *
	 * @return void
	 */
	public function test_blu_get_ability_categories_returns_array() {
		$categories = blu_get_ability_categories();
		$this->assertIsArray( $categories );
	}
}
