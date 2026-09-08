<?php

namespace BLU;

require_once __DIR__ . '/_stubs/WooCommerceStub.php';

use BLU\Abilities\WooOrders;

/**
 * Tests for WooOrders abilities, focused on:
 *  - `blu/wc-update-order`'s generated schema requiring `id` even though the
 *    (synthetic, WC-shaped) endpoint's own `args` don't declare it — the
 *    fix for RestApiUtils::extract_input_schema() not carrying route
 *    captures through as required ability inputs.
 *  - the callback correctly substituting `id` into the route and stripping
 *    it from the request body.
 *  - graceful, standardized errors when no matching route can be resolved.
 *
 * Routes are registered directly on rest_get_server() under the real `wc`
 * base namespace (as `wc/v3`, unused by anything else in this standalone
 * test environment) since WooOrders hardcodes `$base_namespace = 'wc'` and
 * resolves schemas from registered routes at construction time.
 *
 * @covers \BLU\Abilities\WooOrders
 */
class WooOrdersWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Names of abilities registered during tests that need cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array();

	/**
	 * The WooOrders instance constructed during set_up, kept so tests can
	 * reflect into its private `base_namespace` to exercise route discovery
	 * failure without re-registering (and colliding with) the abilities.
	 *
	 * @var WooOrders|null
	 */
	private $woo_orders_instance;

	/**
	 * Set up: register synthetic wc/v3 routes, then construct WooOrders so its
	 * abilities are built against them.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		// WooCommerce (not bootstrapped in this test env) is normally what grants
		// administrators this capability; add it directly so permission_callback
		// checks in the abilities under test pass.
		get_user_by( 'id', $admin_id )->add_cap( 'edit_shop_orders' );
		wp_set_current_user( $admin_id );

		$this->ensure_category();
		$this->register_stub_wc_routes();
		$this->register_woo_orders();
	}

	/**
	 * Tear down: unregister abilities registered by these tests.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$registry = \WP_Abilities_Registry::get_instance();
		foreach ( $this->registered_abilities as $name ) {
			if ( $registry && $registry->is_registered( $name ) ) {
				blu_unregister_ability( $name );
			}
		}
		$this->registered_abilities = array();
		$this->woo_orders_instance  = null;
		parent::tear_down();
	}

	/**
	 * Ensure the blu-mcp ability category exists.
	 *
	 * @return void
	 */
	private function ensure_category(): void {
		$registry = \WP_Ability_Categories_Registry::get_instance();
		if ( ! $registry || $registry->is_registered( 'blu-mcp' ) ) {
			return;
		}
		$registry->register(
			'blu-mcp',
			array(
				'label'       => 'Bluehost MCP',
				'description' => 'Bluehost-specific abilities for use with MCP',
			)
		);
	}

	/**
	 * Register synthetic wc/v3 routes shaped like the native WooCommerce ones:
	 * the item route's PUT args deliberately omit `id` (it's guaranteed by the
	 * URL regex instead), mirroring how WooCommerce itself registers it.
	 *
	 * @return void
	 */
	private function register_stub_wc_routes(): void {
		$server = rest_get_server();

		$server->register_route(
			'wc/v3',
			'/wc/v3/orders',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => function ( \WP_REST_Request $request ) {
						return new \WP_REST_Response( array( 'route' => $request->get_route() ), 200 );
					},
					'permission_callback' => '__return_true',
					'args'                => array(
						'status' => array( 'type' => 'string' ),
					),
				),
			),
			true
		);

		$server->register_route(
			'wc/v3',
			'/wc/v3/orders/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => function ( \WP_REST_Request $request ) {
						return new \WP_REST_Response(
							array(
								'route'  => $request->get_route(),
								'params' => $request->get_body_params(),
							),
							200
						);
					},
					'permission_callback' => '__return_true',
					// Deliberately no `id` here — WooCommerce relies on the URL regex.
					'args'                => array(
						'status' => array( 'type' => 'string' ),
					),
				),
			),
			true
		);

		$server->register_route(
			'wc/v3',
			'/wc/v3/reports/sales',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => '__return_true',
					'permission_callback' => '__return_true',
					'args'                => array(),
				),
			),
			true
		);
	}

	/**
	 * Register the WooOrders abilities via the wp_abilities_api_init hook.
	 *
	 * @return void
	 */
	private function register_woo_orders(): void {
		$instance = null;
		$cb       = function () use ( &$instance ) {
			$instance = new WooOrders();
		};
		add_action( 'wp_abilities_api_init', $cb, 10 );

		$count_before = did_action( 'wp_abilities_api_init' );
		$registry     = \WP_Abilities_Registry::get_instance();
		if ( $registry && did_action( 'wp_abilities_api_init' ) === $count_before ) {
			do_action( 'wp_abilities_api_init', $registry );
		}
		remove_action( 'wp_abilities_api_init', $cb, 10 );

		$this->woo_orders_instance  = $instance;
		$this->registered_abilities = array(
			'blu/wc-orders-search',
			'blu/wc-update-order',
			'blu/wc-reports-sales',
		);
	}

	/**
	 * Verifies the generated schema for wc-update-order requires `id`, even
	 * though the synthetic (WC-shaped) PUT endpoint's own args don't declare it.
	 *
	 * @return void
	 */
	public function test_update_order_schema_requires_id(): void {
		$schema = blu_get_ability( 'blu/wc-update-order' )->get_input_schema();

		$this->assertArrayHasKey( 'id', $schema['properties'] );
		$this->assertArrayHasKey( 'required', $schema );
		$this->assertContains( 'id', $schema['required'] );
	}

	/**
	 * Verifies calling wc-update-order without `id` fails ability-schema
	 * validation before the execute_callback runs.
	 *
	 * @return void
	 */
	public function test_update_order_without_id_fails_validation(): void {
		$result = blu_get_ability( 'blu/wc-update-order' )->execute( array( 'status' => 'completed' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies wc-update-order substitutes `id` into the route and strips it
	 * from the request body, forwarding the remaining fields.
	 *
	 * @return void
	 */
	public function test_update_order_dispatches_to_substituted_route_and_strips_id(): void {
		$result = blu_get_ability( 'blu/wc-update-order' )->execute(
			array(
				'id'     => 5,
				'status' => 'completed',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( '/wc/v3/orders/5', $result['message']['route'] );
		$this->assertArrayNotHasKey( 'id', $result['message']['params'] );
		$this->assertSame( 'completed', $result['message']['params']['status'] );
	}

	/**
	 * Verifies a standardized 400 error when no `wc` route can be resolved,
	 * exercising the shared wc_route_error() fallback used across WooOrders.
	 *
	 * @return void
	 */
	public function test_update_order_missing_route_returns_standardized_error(): void {
		// Point the already-registered instance's route discovery at a namespace
		// that has no "orders" resource at all, without re-registering (and
		// colliding with) the ability itself.
		$prop = new \ReflectionProperty( WooOrders::class, 'base_namespace' );
		$prop->setAccessible( true );
		$prop->setValue( $this->woo_orders_instance, 'blu-orders-missing-namespace' );

		$result = blu_get_ability( 'blu/wc-update-order' )->execute(
			array(
				'id'     => 5,
				'status' => 'completed',
			)
		);

		$this->assertSame( 400, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );
		$this->assertStringContainsString( 'valid route for orders not found', $result['message'] );
	}
}
