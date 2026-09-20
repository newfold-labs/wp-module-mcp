<?php
/**
 * Test stub for the global WooCommerce class.
 *
 * WooProducts, WooOrders, and WooAnalytics all gate their constructors on
 * `class_exists( 'WooCommerce' )`. The real WooCommerce plugin is not loaded
 * in this module's standalone test environment, so this stub lets those
 * classes register their abilities against synthetic `wc/v*`-shaped routes
 * registered directly on rest_get_server().
 *
 * @package BLU
 */

if ( ! class_exists( 'WooCommerce' ) ) {
	/**
	 * Minimal stand-in; only its existence is checked by the classes under test.
	 */
	class WooCommerce {}
}
