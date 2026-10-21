<?php
/**
 * Test stub for NewfoldLabs\WP\Module\Data\HiiveConnection.
 *
 * The real class lives in the sibling wp-module-data package and is not autoloaded
 * in this module's standalone test environment. This stub lets ImageGen tests
 * exercise both the happy path and the missing-token branch by mutating
 * HiiveConnection::$token from individual tests.
 *
 * @package BLU
 */

namespace NewfoldLabs\WP\Module\Data;

if ( ! class_exists( __NAMESPACE__ . '\HiiveConnection' ) ) {
	/**
	 * Test stub for HiiveConnection. Token is mutable per-test via the public static.
	 */
	class HiiveConnection {
		/**
		 * Token returned by get_auth_token(). Set to empty string to simulate missing auth.
		 *
		 * @var string
		 */
		public static $token = 'test-hiive-token';

		/**
		 * Return the current stub token.
		 *
		 * @return string
		 */
		public static function get_auth_token() {
			return self::$token;
		}
	}
}
