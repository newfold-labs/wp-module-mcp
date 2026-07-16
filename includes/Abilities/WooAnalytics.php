<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * WooAnalytics abilities for WooCommerce analytics and reports.
 */
class WooAnalytics {

	/**
	 * Base REST API namespace used to discover the latest versioned namespace.
	 *
	 * @var string
	 */
	private string $base_namespace = 'wc-analytics';

	/**
	 * Constructor - registers WooCommerce analytics abilities if WooCommerce is active.
	 */
	public function __construct() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		$this->register_report_abilities();
	}

	/**
	 * Register report abilities using dynamic REST API discovery.
	 */
	private function register_report_abilities(): void {
		$analytics_namespace = RestApiUtils::get_latest_namespace( $this->base_namespace );

		if ( ! $analytics_namespace ) {
			return;
		}

		// Define report endpoints
		$reports = array(
			'coupons-totals'   => array(
				'ability_id'  => 'blu/wc-reports-coupons-totals',
				'label'       => 'Get WooCommerce Coupons Report',
				'description' => 'Get WooCommerce coupons totals report',
				'path'        => 'reports/coupons/stats',
				'permission'  => 'view_woocommerce_reports',
			),
			'customers-totals' => array(
				'ability_id'  => 'blu/wc-reports-customers-totals',
				'label'       => 'Get WooCommerce Customers Report',
				'description' => 'Get WooCommerce customers totals report',
				'path'        => 'reports/customers/stats',
				'permission'  => 'view_woocommerce_reports',
			),
			'orders-totals'    => array(
				'ability_id'  => 'blu/wc-reports-orders-totals',
				'label'       => 'Get WooCommerce Orders Report',
				'description' => 'Get WooCommerce orders totals report',
				'path'        => 'reports/orders/stats',
				'permission'  => 'view_woocommerce_reports',
			),
			'products-totals'  => array(
				'ability_id'  => 'blu/wc-reports-products-totals',
				'label'       => 'Get WooCommerce Products Report',
				'description' => 'Get WooCommerce products totals report',
				'path'        => 'reports/products/stats',
				'permission'  => 'view_woocommerce_reports',
			),
			'revenue-stats'    => array(
				'ability_id'  => 'blu/wc-reports-revenue-stats',
				'label'       => 'Get WooCommerce Revenue Report',
				'description' => sprintf( 'Get WooCommerce sales report using %s API', $analytics_namespace ),
				'path'        => 'reports/revenue/stats',
				'permission'  => 'view_woocommerce_reports',
			),
			/*'reviews-totals'   => array(
				'ability_id'  => 'blu/wc-reports-reviews-totals',
				'label'       => 'Get WooCommerce Reviews Report',
				'description' => 'Get WooCommerce reviews totals report',
				'path'        => 'reports/reviews/totals',
				'permission'  => 'view_woocommerce_reports',
			),*/
		);

		// Register each report ability dynamically
		foreach ( $reports as $report_config ) {
			$route = RestApiUtils::find_route_by_resource( $analytics_namespace, $report_config['path'] );

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
					'description'         => sprintf( '%s using %s API', $report_config['description'], $analytics_namespace ),
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
	}
}
