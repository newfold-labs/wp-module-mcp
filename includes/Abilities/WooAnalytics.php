<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * WooAnalytics abilities for WooCommerce analytics and reports.
 *
 * Uses the wc-analytics REST namespace (stats endpoints). Legacy wc/v3
 * report totals remain on WooOrders where still applicable.
 */
class WooAnalytics {

	/**
	 * Base REST API namespace used to discover the analytics namespace.
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
	 * Register analytics report abilities.
	 */
	private function register_report_abilities(): void {
		$analytics_namespace = $this->analytics_namespace_label();

		$reports = array(
			'coupons-stats'   => array(
				'ability_id'  => 'blu/wc-reports-coupons-totals',
				'label'       => 'Get WooCommerce Coupons Report',
				'description' => 'Get WooCommerce coupons totals report',
				'path'        => 'reports/coupons/stats',
				'permission'  => 'view_woocommerce_reports',
			),
			'customers-stats' => array(
				'ability_id'  => 'blu/wc-reports-customers-totals',
				'label'       => 'Get WooCommerce Customers Report',
				'description' => 'Get WooCommerce customers totals report',
				'path'        => 'reports/customers/stats',
				'permission'  => 'view_woocommerce_reports',
			),
			'orders-stats'    => array(
				'ability_id'  => 'blu/wc-reports-orders-totals',
				'label'       => 'Get WooCommerce Orders Report',
				'description' => 'Get WooCommerce orders totals report',
				'path'        => 'reports/orders/stats',
				'permission'  => 'view_woocommerce_reports',
			),
			'products-stats'  => array(
				'ability_id'  => 'blu/wc-reports-products-totals',
				'label'       => 'Get WooCommerce Products Report',
				'description' => 'Get WooCommerce products totals report',
				'path'        => 'reports/products/stats',
				'permission'  => 'view_woocommerce_reports',
			),
			'revenue-stats'   => array(
				'ability_id'  => 'blu/wc-reports-revenue-stats',
				'label'       => 'Get WooCommerce Revenue Report',
				'description' => 'Get WooCommerce revenue stats report',
				'path'        => 'reports/revenue/stats',
				'permission'  => 'view_woocommerce_reports',
			),
		);

		foreach ( $reports as $report_config ) {
			$report_path = $report_config['path'];
			$route       = $this->resolve_analytics_route( $report_path );

			$input_schema = $route
				? RestApiUtils::extract_input_schema( $route, 'GET' )
				: null;

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
					'execute_callback'    => function ( $input = null ) use ( $report_path ) {
						$route = $this->resolve_analytics_route( $report_path );
						if ( ! $route ) {
							return $this->analytics_route_error( $report_path );
						}

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

	/**
	 * Resolved wc-analytics namespace label for ability descriptions.
	 *
	 * @return string
	 */
	private function analytics_namespace_label(): string {
		RestApiUtils::eager_load_rest_routes();

		return RestApiUtils::get_latest_namespace( $this->base_namespace ) ?? 'wc-analytics';
	}

	/**
	 * Resolve a wc-analytics route at execution time.
	 *
	 * @param string $resource_path Resource path without namespace prefix.
	 *
	 * @return string|null
	 */
	private function resolve_analytics_route( string $resource_path ): ?string {
		RestApiUtils::eager_load_rest_routes();

		return RestApiUtils::get_latest_available_rest_route( $this->base_namespace, $resource_path );
	}

	/**
	 * Standard error when a wc-analytics route is unavailable.
	 *
	 * @param string $resource_path Resource path that could not be resolved.
	 *
	 * @return array<string, mixed>
	 */
	private function analytics_route_error( string $resource_path ): array {
		return blu_standardize_rest_response(
			new \WP_Error(
				400,
				sprintf(
					'A valid wc-analytics route for %s not found. Please ensure WooCommerce is active and its analytics REST API is available.',
					$resource_path
				)
			)
		);
	}
}
