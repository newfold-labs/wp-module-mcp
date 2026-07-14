<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * WooOrders abilities for WooCommerce orders and reports.
 */
class WooOrders {

	/**
	 * Base REST API namespace used to discover the latest versioned namespace.
	 *
	 * @var string
	 */
	private string $base_namespace = 'wc';

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
		// Discover latest WooCommerce REST API version
		$wc_namespace = RestApiUtils::get_latest_namespace( $this->base_namespace );

		if ( ! $wc_namespace ) {
			return;
		}

		// Find the orders route
		$orders_route = RestApiUtils::find_route_by_resource( $wc_namespace, 'orders' );

		if ( ! $orders_route ) {
			return;
		}

		// Extract dynamic schema from the REST API
		$input_schema = RestApiUtils::extract_input_schema( $orders_route, 'GET' );

		if ( ! $input_schema ) {
			// Fallback to basic schema if extraction fails
			$input_schema = array(
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
			);
		}

		// Search orders
		blu_register_ability(
			'blu/wc-orders-search',
			array(
				'label'               => 'Search WooCommerce Orders',
				'description'         => sprintf( 'Get a list of WooCommerce orders using %s API', $wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $input_schema,
				'execute_callback'    => function ( $input = null ) use ( $orders_route ) {
					$request = new \WP_REST_Request( 'GET', $orders_route );
					if ( $input ) {
						$request->set_query_params( $input );
					}
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_shop_orders' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Find the single order route pattern
		$order_pattern = '(?P<id>[\d]+)';
		$order_route   = RestApiUtils::find_route_by_resource( $wc_namespace, 'orders/' . $order_pattern );

		if ( ! $order_route ) {
			return;
		}

		// Extract dynamic schema from the REST API for PATCH method
		$input_schema = RestApiUtils::extract_input_schema( $order_route, 'PUT' );

		if ( ! $input_schema ) {
			// Fallback to basic schema if extraction fails
			$input_schema = array(
				'type'       => 'object',
				'properties' => array(
					'id'     => array(
						'type'        => 'integer',
						'description' => 'Order ID',
					),
					'status' => array(
						'type'        => 'string',
						'description' => 'Order status (pending, processing, on-hold, completed, cancelled, refunded, failed)',
					),
				),
				'required'   => array( 'id' ),
			);
		}

		// Update order
		blu_register_ability(
			'blu/wc-update-order',
			array(
				'label'               => 'Update WooCommerce Order',
				'description'         => sprintf( 'Update a WooCommerce order using %s API', $wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $input_schema,
				'execute_callback'    => function ( $input = null ) use ( $order_route, $order_pattern ) {
					if ( ! $input || ! isset( $input['id'] ) ) {
						return array(
							'status'  => 'error',
							'message' => 'Order ID is required',
						);
					}

					$order_id = (int) $input['id'];
					$route    = str_replace( $order_pattern, (string) $order_id, $order_route );
					$body     = $input;
					unset( $body['id'] );

					$request = new \WP_REST_Request( 'PUT', $route );
					$request->set_body_params( $body );

					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_shop_orders' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}

	/**
	 * Register report abilities using dynamic REST API discovery.
	 */
	private function register_report_abilities(): void {
		// Discover latest WooCommerce REST API version
		$wc_namespace = RestApiUtils::get_latest_namespace( $this->base_namespace );

		if ( ! $wc_namespace ) {
			return;
		}

		// Define report endpoints
		$reports = array(
			'coupons-totals'   => array(
				'ability_id'  => 'blu/wc-reports-coupons-totals',
				'label'       => 'Get WooCommerce Coupons Report',
				'description' => 'Get WooCommerce coupons totals report',
				'path'        => 'reports/coupons/totals',
				'permission'  => 'view_woocommerce_reports',
			),
			'customers-totals' => array(
				'ability_id'  => 'blu/wc-reports-customers-totals',
				'label'       => 'Get WooCommerce Customers Report',
				'description' => 'Get WooCommerce customers totals report',
				'path'        => 'reports/customers/totals',
				'permission'  => 'view_woocommerce_reports',
			),
			'orders-totals'    => array(
				'ability_id'  => 'blu/wc-reports-orders-totals',
				'label'       => 'Get WooCommerce Orders Report',
				'description' => 'Get WooCommerce orders totals report',
				'path'        => 'reports/orders/totals',
				'permission'  => 'view_woocommerce_reports',
			),
			'products-totals'  => array(
				'ability_id'  => 'blu/wc-reports-products-totals',
				'label'       => 'Get WooCommerce Products Report',
				'description' => 'Get WooCommerce products totals report',
				'path'        => 'reports/products/totals',
				'permission'  => 'view_woocommerce_reports',
			),
			'reviews-totals'   => array(
				'ability_id'  => 'blu/wc-reports-reviews-totals',
				'label'       => 'Get WooCommerce Reviews Report',
				'description' => 'Get WooCommerce reviews totals report',
				'path'        => 'reports/reviews/totals',
				'permission'  => 'view_woocommerce_reports',
			),
		);

		// Register each report ability dynamically
		foreach ( $reports as $report_config ) {
			$route = RestApiUtils::find_route_by_resource( $wc_namespace, $report_config['path'] );

			if ( ! $route ) {
				continue;
			}

			$input_schema = RestApiUtils::extract_input_schema( $route, 'GET' );

			if ( ! $input_schema ) {
				$input_schema = array( 'type' => 'object' );
			}

			blu_register_ability(
				$report_config['ability_id'],
				array(
					'label'               => $report_config['label'],
					'description'         => sprintf( '%s using %s API', $report_config['description'], $wc_namespace ),
					'category'            => 'blu-mcp',
					'input_schema'        => $input_schema,
					'execute_callback'    => function ( $input = null ) use ( $route ) {
						$request = new \WP_REST_Request( 'GET', $route );
						if ( $input ) {
							$request->set_query_params( $input );
						}
						$response = rest_do_request( $request );
						return blu_standardize_rest_response( $response );
					},
					'permission_callback' => fn() => current_user_can( $report_config['permission'] ),
					'meta'                => array(
						'annotations' => array(
							'readonly'    => true,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
				)
			);
		}

		// Sales report (with parameters)
		$sales_route = RestApiUtils::find_route_by_resource( $wc_namespace, 'reports/sales' );

		if ( $sales_route ) {
			$sales_schema = RestApiUtils::extract_input_schema( $sales_route, 'GET' );

			if ( ! $sales_schema ) {
				$sales_schema = array(
					'type'       => 'object',
					'properties' => array(
						'period' => array(
							'type'        => 'string',
							'description' => 'Report period (week, month, year)',
						),
					),
				);
			}

			blu_register_ability(
				'blu/wc-reports-sales',
				array(
					'label'               => 'Get WooCommerce Sales Report',
					'description'         => sprintf( 'Get WooCommerce sales report using %s API', $wc_namespace ),
					'category'            => 'blu-mcp',
					'input_schema'        => $sales_schema,
					'execute_callback'    => function ( $input = null ) use ( $sales_route ) {
						$request = new \WP_REST_Request( 'GET', $sales_route );
						if ( $input ) {
							$request->set_query_params( $input );
						}
						$response = rest_do_request( $request );
						return blu_standardize_rest_response( $response );
					},
					'permission_callback' => fn() => current_user_can( 'view_woocommerce_reports' ),
					'meta'                => array(
						'annotations' => array(
							'readonly'    => true,
							'destructive' => false,
							'idempotent'  => true,
						),
					),
				)
			);
		}
	}
}
