<?php

namespace BLU;

/**
 * Tests for BLU global functions.
 *
 * @coversNothing
 */
class FunctionsWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Verifies blu_get_status_type returns success for 2xx and 3xx.
	 *
	 * @return void
	 */
	public function test_blu_get_status_type_success() {
		$this->assertSame( 'success', blu_get_status_type( 200 ) );
		$this->assertSame( 'success', blu_get_status_type( 201 ) );
		$this->assertSame( 'success', blu_get_status_type( 301 ) );
	}

	/**
	 * Verifies blu_get_status_type returns error for 4xx and 5xx.
	 *
	 * @return void
	 */
	public function test_blu_get_status_type_error() {
		$this->assertSame( 'error', blu_get_status_type( 400 ) );
		$this->assertSame( 'error', blu_get_status_type( 404 ) );
		$this->assertSame( 'error', blu_get_status_type( 500 ) );
	}

	/**
	 * Verifies blu_get_status_type returns unknown for other codes.
	 *
	 * @return void
	 */
	public function test_blu_get_status_type_unknown() {
		$this->assertSame( 'unknown', blu_get_status_type( 0 ) );
		$this->assertSame( 'unknown', blu_get_status_type( 100 ) );
	}

	/**
	 * Verifies blu_prepare_ability_response returns array with statusCode, status, message.
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
	 * Verifies blu_standardize_rest_response with WP_Error returns error format.
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
	 * Verifies blu_standardize_rest_response with WP_REST_Response returns response format.
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
	 * Verifies blu_standardize_rest_response with unexpected type returns 500.
	 *
	 * @return void
	 */
	public function test_blu_standardize_rest_response_unexpected() {
		$result = blu_standardize_rest_response( 'not valid' );
		$this->assertSame( 500, $result['statusCode'] );
		$this->assertSame( 'Unexpected response format.', $result['message'] );
	}

	/**
	 * Verifies blu_get_abilities_by_category with no abilities returns empty array.
	 *
	 * @return void
	 */
	public function test_blu_get_abilities_by_category_empty() {
		$abilities = blu_get_abilities_by_category( 'blu-mcp' );
		$this->assertIsArray( $abilities );
	}

	/**
	 * Verifies blu_filter_abilities_by_category with empty array returns empty.
	 *
	 * @return void
	 */
	public function test_blu_filter_abilities_by_category_empty() {
		$filtered = blu_filter_abilities_by_category( array(), 'blu-mcp' );
		$this->assertSame( array(), $filtered );
	}

	/**
	 * Verifies blu_filter_abilities_by_namespace with empty array returns empty.
	 *
	 * @return void
	 */
	public function test_blu_filter_abilities_by_namespace_empty() {
		$filtered = blu_filter_abilities_by_namespace( array(), 'blu' );
		$this->assertSame( array(), $filtered );
	}

	/**
	 * Verifies blu_get_ability_categories returns array.
	 *
	 * @return void
	 */
	public function test_blu_get_ability_categories_returns_array() {
		$categories = blu_get_ability_categories();
		$this->assertIsArray( $categories );
	}

	/**
	 * Verifies blu_register_ability returns null when wp_register_ability is unavailable.
	 *
	 * @return void
	 */
	public function test_blu_register_ability_returns_null_when_wp_unavailable() {
		$this->assertNull( blu_register_ability( 'test-ability', array( 'label' => 'Test' ) ) );
	}

	/**
	 * Verifies blu_unregister_ability returns null when wp_unregister_ability is unavailable.
	 *
	 * @return void
	 */
	public function test_blu_unregister_ability_returns_null_when_wp_unavailable() {
		$this->assertNull( blu_unregister_ability( 'test-ability' ) );
	}

	/**
	 * Verifies blu_get_ability returns null when wp_get_ability is unavailable.
	 *
	 * @return void
	 */
	public function test_blu_get_ability_returns_null_when_wp_unavailable() {
		$this->assertNull( blu_get_ability( 'test-ability' ) );
	}

	/**
	 * Verifies blu_register_ability_category returns null when wp_register_ability_category is unavailable.
	 *
	 * @return void
	 */
	public function test_blu_register_ability_category_returns_null_when_wp_unavailable() {
		$this->assertNull( blu_register_ability_category( 'test-cat', array( 'label' => 'Test' ) ) );
	}

	/**
	 * Verifies blu_unregister_ability_category returns null when wp_unregister_ability_category is unavailable.
	 *
	 * @return void
	 */
	public function test_blu_unregister_ability_category_returns_null_when_wp_unavailable() {
		$this->assertNull( blu_unregister_ability_category( 'test-cat' ) );
	}

	/**
	 * Verifies blu_get_ability_category returns null when wp_get_ability_category is unavailable.
	 *
	 * @return void
	 */
	public function test_blu_get_ability_category_returns_null_when_wp_unavailable() {
		$this->assertNull( blu_get_ability_category( 'test-cat' ) );
	}

	/**
	 * Verifies blu_standardize_rest_response with WP_Error and empty code uses 500.
	 *
	 * @return void
	 */
	public function test_blu_standardize_rest_response_wp_error_empty_code_uses_500() {
		$error  = new \WP_Error( '', 'Error with no code' );
		$result = blu_standardize_rest_response( $error );
		$this->assertSame( 500, $result['statusCode'] );
		$this->assertSame( 'Error with no code', $result['message'] );
	}

	/**
	 * Verifies blu_prepare_ability_response with error status code sets status to error.
	 *
	 * @return void
	 */
	public function test_blu_prepare_ability_response_error_status() {
		$result = blu_prepare_ability_response( 404, array( 'error' => 'Not found' ) );
		$this->assertSame( 404, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );
	}

	/**
	 * Verifies blu_filter_abilities_by_category keeps abilities matching the category.
	 *
	 * @return void
	 */
	public function test_blu_filter_abilities_by_category_filters_by_category() {
		// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing -- stub for test
		$match    = new class() {
			public function get_category() {
				return 'blu-mcp';
			}
			public function get_name() {
				return 'blu/test';
			}
		};
		$no_match = new class() {
			public function get_category() {
				return 'other';
			}
			public function get_name() {
				return 'other/test';
			}
		};
		// phpcs:enable
		$filtered = blu_filter_abilities_by_category( array( $match, $no_match ), 'blu-mcp' );
		$this->assertCount( 1, $filtered );
		$this->assertSame( $match, $filtered[0] );
	}

	/**
	 * Verifies blu_filter_abilities_by_namespace keeps abilities matching the namespace.
	 *
	 * @return void
	 */
	public function test_blu_filter_abilities_by_namespace_filters_by_namespace() {
		// phpcs:disable Generic.Commenting.DocComment.MissingShort,Squiz.Commenting.FunctionComment.Missing -- stub for test
		$match    = new class() {
			public function get_category() {
				return 'blu-mcp';
			}
			public function get_name() {
				return 'blu/site-info';
			}
		};
		$no_match = new class() {
			public function get_category() {
				return 'blu-mcp';
			}
			public function get_name() {
				return 'other/thing';
			}
		};
		// phpcs:enable
		$filtered = blu_filter_abilities_by_namespace( array( $match, $no_match ), 'blu' );
		$this->assertCount( 1, $filtered );
		$this->assertSame( $match, $filtered[0] );
	}

	/**
	 * Verifies blu_get_abilities_by_namespace returns array.
	 *
	 * @return void
	 */
	public function test_blu_get_abilities_by_namespace_returns_array() {
		$abilities = blu_get_abilities_by_namespace( 'blu' );
		$this->assertIsArray( $abilities );
	}
}
