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
	private $base_namespace = 'wc';

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
		$wc_namespace = $this->wc_namespace_label();
		$orders_route = $this->resolve_wc_route( 'orders' );

		$input_schema = $orders_route
			? RestApiUtils::extract_input_schema( $orders_route, 'GET' )
			: null;

		if ( ! $input_schema ) {
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

		blu_register_ability(
			'blu/wc-orders-search',
			array(
				'label'               => 'Search WooCommerce Orders',
				'description'         => sprintf( 'Get a list of WooCommerce orders using %s API', $wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $input_schema,
				'execute_callback'    => function ( $input = null ) {
					$orders_route = $this->resolve_wc_route( 'orders' );
					if ( ! $orders_route ) {
						return $this->wc_route_error( 'orders' );
					}

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

		$order_pattern = '(?P<id>[\d]+)';
		$order_route   = $this->resolve_wc_param_route( 'orders/' . $order_pattern );

		$input_schema = $order_route
			? RestApiUtils::extract_input_schema( $order_route, 'PUT' )
			: null;

		if ( ! $input_schema ) {
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

		blu_register_ability(
			'blu/wc-update-order',
			array(
				'label'               => 'Update WooCommerce Order',
				'description'         => sprintf( 'Update a WooCommerce order using %s API', $wc_namespace ),
				'category'            => 'blu-mcp',
				'input_schema'        => $input_schema,
				'execute_callback'    => function ( $input = null ) {
					if ( ! $input || ! isset( $input['id'] ) ) {
						return array(
							'status'  => 'error',
							'message' => 'Order ID is required',
						);
					}

					$orders_route = $this->resolve_wc_route( 'orders' );
					if ( ! $orders_route ) {
						return $this->wc_route_error( 'orders' );
					}

					$order_id = (int) $input['id'];
					$route    = RestApiUtils::build_item_route( $orders_route, $order_id );
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
	 * Register legacy wc/v3 report abilities not covered by WooAnalytics.
	 */
	private function register_report_abilities(): void {
		$wc_namespace = $this->wc_namespace_label();

		$reports = array(
			'reviews-totals' => array(
				'ability_id'  => 'blu/wc-reports-reviews-totals',
				'label'       => 'Get WooCommerce Reviews Report',
				'description' => 'Get WooCommerce reviews totals report',
				'path'        => 'reports/reviews/totals',
				'permission'  => 'view_woocommerce_reports',
			),
		);

		foreach ( $reports as $report_config ) {
			$route = $this->resolve_wc_route( $report_config['path'] );

			$input_schema = $route
				? RestApiUtils::extract_input_schema( $route, 'GET' )
				: null;

			if ( ! $input_schema ) {
				$input_schema = array( 'type' => 'object' );
			}

			$report_path = $report_config['path'];

			blu_register_ability(
				$report_config['ability_id'],
				array(
					'label'               => $report_config['label'],
					'description'         => sprintf( '%s using %s API', $report_config['description'], $wc_namespace ),
					'category'            => 'blu-mcp',
					'input_schema'        => $input_schema,
					'execute_callback'    => function ( $input = null ) use ( $report_path ) {
						$route = $this->resolve_wc_route( $report_path );
						if ( ! $route ) {
							return $this->wc_route_error( $report_path );
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

		$sales_route = $this->resolve_wc_route( 'reports/sales' );

		$sales_schema = $sales_route
			? RestApiUtils::extract_input_schema( $sales_route, 'GET' )
			: null;

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
				'execute_callback'    => function ( $input = null ) {
					$sales_route = $this->resolve_wc_route( 'reports/sales' );
					if ( ! $sales_route ) {
						return $this->wc_route_error( 'reports/sales' );
					}

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

	/**
	 * Resolved WooCommerce namespace label for ability descriptions.
	 *
	 * @return string
	 */
	private function wc_namespace_label(): string {
		RestApiUtils::eager_load_rest_routes();

		return RestApiUtils::get_latest_namespace( $this->base_namespace ) ?? 'wc';
	}

	/**
	 * Resolve a WooCommerce collection route at execution time.
	 *
	 * @param string $resource_path Resource path without version prefix.
	 *
	 * @return string|null
	 */
	private function resolve_wc_route( string $resource_path ): ?string {
		RestApiUtils::eager_load_rest_routes();

		return RestApiUtils::get_latest_available_rest_route( $this->base_namespace, $resource_path );
	}

	/**
	 * Resolve a parameterized WooCommerce route pattern at registration time.
	 *
	 * @param string $resource_path Resource path including (?P<name>...) segments.
	 *
	 * @return string|null
	 */
	private function resolve_wc_param_route( string $resource_path ): ?string {
		RestApiUtils::eager_load_rest_routes();
		$namespace = RestApiUtils::get_latest_namespace( $this->base_namespace );

		if ( ! $namespace ) {
			return null;
		}

		return RestApiUtils::find_route_by_resource( $namespace, $resource_path );
	}

	/**
	 * Standard error when a WooCommerce route is unavailable.
	 *
	 * @param string $resource_path Resource path that could not be resolved.
	 *
	 * @return array
	 */
	private function wc_route_error( string $resource_path ): array {
		return blu_standardize_rest_response(
			new \WP_Error(
				400,
				sprintf(
					'A valid route for %s not found. Please ensure WooCommerce is active and its REST API is enabled.',
					$resource_path
				)
			)
		);
	}
}
