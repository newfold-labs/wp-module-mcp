<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * WooOrders abilities for WooCommerce orders and reports.
 */
class WooOrders {

	/**
	 * Constructor - registers WooCommerce order abilities if WooCommerce is active.
	 */
	public function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->register_order_abilities();
		$this->register_report_abilities();
	}

	/**
	 * Register order abilities.
	 */
	private function register_order_abilities(): void {
		// Search orders
		newfold_register_ability(
			'blu/wc-orders-search',
			array(
				'label'               => 'Search WooCommerce Orders',
				'description'         => 'Get a list of WooCommerce orders',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status'   => array(
							'type'        => 'string',
							'description' => 'Filter by order status',
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page number',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Orders per page',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/orders' );
					if ( $input ) {
						$request->set_query_params( $input );
					}
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'edit_shop_orders' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);
	}

	/**
	 * Register report abilities.
	 */
	private function register_report_abilities(): void {
		// Coupons totals report
		newfold_register_ability(
			'blu/wc-reports-coupons-totals',
			array(
				'label'               => 'Get WooCommerce Coupons Report',
				'description'         => 'Get WooCommerce coupons totals report',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/reports/coupons/totals' );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'view_woocommerce_reports' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		// Customers totals report
		newfold_register_ability(
			'blu/wc-reports-customers-totals',
			array(
				'label'               => 'Get WooCommerce Customers Report',
				'description'         => 'Get WooCommerce customers totals report',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/reports/customers/totals' );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'view_woocommerce_reports' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		// Orders totals report
		newfold_register_ability(
			'blu/wc-reports-orders-totals',
			array(
				'label'               => 'Get WooCommerce Orders Report',
				'description'         => 'Get WooCommerce orders totals report',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/reports/orders/totals' );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'view_woocommerce_reports' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		// Products totals report
		newfold_register_ability(
			'blu/wc-reports-products-totals',
			array(
				'label'               => 'Get WooCommerce Products Report',
				'description'         => 'Get WooCommerce products totals report',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/reports/products/totals' );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'view_woocommerce_reports' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		// Reviews totals report
		newfold_register_ability(
			'blu/wc-reports-reviews-totals',
			array(
				'label'               => 'Get WooCommerce Reviews Report',
				'description'         => 'Get WooCommerce reviews totals report',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/reports/reviews/totals' );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'view_woocommerce_reports' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		// Sales report
		newfold_register_ability(
			'blu/wc-reports-sales',
			array(
				'label'               => 'Get WooCommerce Sales Report',
				'description'         => 'Get WooCommerce sales report',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'period' => array(
							'type'        => 'string',
							'description' => 'Report period (week, month, year)',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$request = new \WP_REST_Request( 'GET', '/wc/v3/reports/sales' );
					if ( $input ) {
						$request->set_query_params( $input );
					}
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'view_woocommerce_reports' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);
	}
}
