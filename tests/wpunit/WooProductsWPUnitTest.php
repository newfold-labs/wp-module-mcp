<?php

namespace BLU;

require_once __DIR__ . '/_stubs/WooCommerceStub.php';

use BLU\Abilities\WooProducts;

/**
 * Tests for WooProducts abilities, focused on:
 *  - generated schemas requiring named route captures (`id`, `attribute_id`,
 *    `product_id`) that native WC endpoints don't mark required in their own
 *    `args` — the headline case being `blu/wc-update-product`, whose
 *    callback reads `$input['id']` immediately.
 *  - parameterized route substitution for nested resources (attribute terms,
 *    variations, and the two-capture delete-variation route).
 *
 * Routes are registered directly on rest_get_server() under the real `wc`
 * base namespace (as `wc/v3`) since WooProducts hardcodes
 * `$base_namespace = 'wc'` and resolves schemas from registered routes at
 * construction time.
 *
 * @covers \BLU\Abilities\WooProducts
 */
class WooProductsWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Names of abilities registered during tests that need cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array();

	/**
	 * Set up: register synthetic wc/v3 routes, then construct WooProducts so
	 * its abilities are built against them.
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
		// administrators these capabilities; add them directly so permission_callback
		// checks in the abilities under test pass.
		$admin = get_user_by( 'id', $admin_id );
		$admin->add_cap( 'edit_products' );
		$admin->add_cap( 'manage_woocommerce' );
		wp_set_current_user( $admin_id );

		$this->ensure_category();
		$this->register_stub_wc_routes();
		$this->register_woo_products();
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
	 * A callback that echoes the dispatched route, method, and body params so
	 * tests can assert on route substitution and body handling.
	 *
	 * @return callable
	 */
	private function echo_callback(): callable {
		return function ( \WP_REST_Request $request ) {
			return new \WP_REST_Response(
				array(
					'route'  => $request->get_route(),
					'params' => $request->get_body_params(),
				),
				200
			);
		};
	}

	/**
	 * Register synthetic wc/v3 routes shaped like the native WooCommerce ones:
	 * item/nested routes deliberately omit their own path-captured names from
	 * `args` (e.g. no `id`, `attribute_id`, or `product_id`), mirroring how
	 * WooCommerce itself registers them.
	 *
	 * @return void
	 */
	private function register_stub_wc_routes(): void {
		$server = rest_get_server();

		$server->register_route(
			'wc/v3',
			'/wc/v3/products',
			array(
				array(
					'methods'             => 'GET, POST',
					'callback'            => $this->echo_callback(),
					'permission_callback' => '__return_true',
					'args'                => array(
						'name' => array( 'type' => 'string' ),
					),
				),
			),
			true
		);

		$server->register_route(
			'wc/v3',
			'/wc/v3/products/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'PUT',
					'callback'            => $this->echo_callback(),
					'permission_callback' => '__return_true',
					// Deliberately no `id` — WooCommerce relies on the URL regex.
					'args'                => array(
						'name' => array( 'type' => 'string' ),
					),
				),
			),
			true
		);

		$server->register_route(
			'wc/v3',
			'/wc/v3/products/attributes/(?P<attribute_id>[\d]+)/terms',
			array(
				array(
					'methods'             => 'GET, POST',
					'callback'            => $this->echo_callback(),
					'permission_callback' => '__return_true',
					// Deliberately no `attribute_id`.
					'args'                => array(
						'name' => array( 'type' => 'string' ),
					),
				),
			),
			true
		);

		$server->register_route(
			'wc/v3',
			'/wc/v3/products/(?P<product_id>[\d]+)/variations',
			array(
				array(
					'methods'             => 'GET, POST',
					'callback'            => $this->echo_callback(),
					'permission_callback' => '__return_true',
					// Deliberately no `product_id`.
					'args'                => array(
						'sku' => array( 'type' => 'string' ),
					),
				),
			),
			true
		);

		$server->register_route(
			'wc/v3',
			'/wc/v3/products/(?P<product_id>[\d]+)/variations/(?P<id>[\d]+)',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => $this->echo_callback(),
					'permission_callback' => '__return_true',
					'args'                => array(),
				),
			),
			true
		);
	}

	/**
	 * Register the WooProducts abilities via the wp_abilities_api_init hook.
	 *
	 * @return void
	 */
	private function register_woo_products(): void {
		add_action( 'wp_abilities_api_init', array( $this, 'construct_woo_products' ), 10 );

		$count_before = did_action( 'wp_abilities_api_init' );
		$registry     = \WP_Abilities_Registry::get_instance();
		if ( $registry && did_action( 'wp_abilities_api_init' ) === $count_before ) {
			do_action( 'wp_abilities_api_init', $registry );
		}
		remove_action( 'wp_abilities_api_init', array( $this, 'construct_woo_products' ), 10 );

		$this->registered_abilities = array(
			'blu/wc-products-search',
			'blu/wc-get-product',
			'blu/wc-add-product',
			'blu/wc-update-product',
			'blu/wc-delete-product',
			'blu/wc-list-product-categories',
			'blu/wc-add-product-category',
			'blu/wc-update-product-category',
			'blu/wc-delete-product-category',
			'blu/wc-list-product-tags',
			'blu/wc-add-product-tag',
			'blu/wc-update-product-tag',
			'blu/wc-delete-product-tag',
			'blu/wc-list-product-brands',
			'blu/wc-add-product-brand',
			'blu/wc-update-product-brand',
			'blu/wc-delete-product-brand',
			'blu/wc-list-product-attributes',
			'blu/wc-add-product-attribute',
			'blu/wc-delete-product-attribute',
			'blu/wc-list-attribute-terms',
			'blu/wc-add-attribute-term',
			'blu/wc-list-product-variations',
			'blu/wc-add-product-variation',
			'blu/wc-generate-product-variations',
			'blu/wc-delete-product-variation',
			'blu/wc-reports-reviews-totals',
		);
	}

	/**
	 * Constructs WooProducts (public so it can be used as an add_action callback).
	 *
	 * @return void
	 */
	public function construct_woo_products(): void {
		new WooProducts();
	}

	// -----------------------------------------------------------------
	// blu/wc-update-product — the headline case: `id` must be required
	// -----------------------------------------------------------------

	/**
	 * Verifies the generated schema for wc-update-product requires `id`, even
	 * though the synthetic (WC-shaped) PUT endpoint's own args don't declare it.
	 *
	 * @return void
	 */
	public function test_update_product_schema_requires_id(): void {
		$schema = blu_get_ability( 'blu/wc-update-product' )->get_input_schema();

		$this->assertArrayHasKey( 'id', $schema['properties'] );
		$this->assertArrayHasKey( 'required', $schema );
		$this->assertContains( 'id', $schema['required'] );
	}

	/**
	 * Verifies calling wc-update-product without `id` fails ability-schema
	 * validation before the execute_callback (which reads $input['id']
	 * unconditionally) ever runs.
	 *
	 * @return void
	 */
	public function test_update_product_without_id_fails_validation(): void {
		$result = blu_get_ability( 'blu/wc-update-product' )->execute( array( 'name' => 'Updated name' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies wc-update-product substitutes `id` into the route and strips it
	 * from the request body.
	 *
	 * @return void
	 */
	public function test_update_product_dispatches_to_substituted_route_and_strips_id(): void {
		$result = blu_get_ability( 'blu/wc-update-product' )->execute(
			array(
				'id'   => 8,
				'name' => 'Updated name',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( '/wc/v3/products/8', $result['message']['route'] );
		$this->assertArrayNotHasKey( 'id', $result['message']['params'] );
		$this->assertSame( 'Updated name', $result['message']['params']['name'] );
	}

	// -----------------------------------------------------------------
	// blu/wc-add-attribute-term — attribute_id must be required
	// -----------------------------------------------------------------

	/**
	 * Verifies the generated schema for wc-add-attribute-term requires
	 * `attribute_id`, matching the nested (?P<attribute_id>...) capture in its route.
	 *
	 * @return void
	 */
	public function test_add_attribute_term_schema_requires_attribute_id(): void {
		$schema = blu_get_ability( 'blu/wc-add-attribute-term' )->get_input_schema();

		$this->assertArrayHasKey( 'attribute_id', $schema['properties'] );
		$this->assertArrayHasKey( 'required', $schema );
		$this->assertContains( 'attribute_id', $schema['required'] );
	}

	/**
	 * Verifies wc-add-attribute-term substitutes `attribute_id` into the nested
	 * route and strips it from the request body.
	 *
	 * @return void
	 */
	public function test_add_attribute_term_dispatches_to_substituted_route(): void {
		$result = blu_get_ability( 'blu/wc-add-attribute-term' )->execute(
			array(
				'attribute_id' => 3,
				'name'         => 'Red',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( '/wc/v3/products/attributes/3/terms', $result['message']['route'] );
		$this->assertArrayNotHasKey( 'attribute_id', $result['message']['params'] );
		$this->assertSame( 'Red', $result['message']['params']['name'] );
	}

	// -----------------------------------------------------------------
	// blu/wc-list-product-variations, blu/wc-add-product-variation —
	// product_id must be required exactly once (no duplicate entries)
	// -----------------------------------------------------------------

	/**
	 * Verifies the generated schema for wc-list-product-variations requires
	 * `product_id` exactly once, even though both the generic path-capture fix
	 * and the ability's own manual patch add it.
	 *
	 * @return void
	 */
	public function test_list_product_variations_schema_requires_product_id_once(): void {
		$schema = blu_get_ability( 'blu/wc-list-product-variations' )->get_input_schema();

		$this->assertArrayHasKey( 'product_id', $schema['properties'] );
		$this->assertArrayHasKey( 'required', $schema );
		$this->assertSame( 1, count( array_keys( $schema['required'], 'product_id', true ) ), 'product_id must appear exactly once in required' );
	}

	/**
	 * Verifies the generated schema for wc-add-product-variation requires
	 * `product_id` exactly once.
	 *
	 * @return void
	 */
	public function test_add_product_variation_schema_requires_product_id_once(): void {
		$schema = blu_get_ability( 'blu/wc-add-product-variation' )->get_input_schema();

		$this->assertArrayHasKey( 'product_id', $schema['properties'] );
		$this->assertArrayHasKey( 'required', $schema );
		$this->assertSame( 1, count( array_keys( $schema['required'], 'product_id', true ) ), 'product_id must appear exactly once in required' );
	}

	// -----------------------------------------------------------------
	// blu/wc-delete-product-variation — two-capture route substitution
	// -----------------------------------------------------------------

	/**
	 * Verifies wc-delete-product-variation substitutes both `product_id` and
	 * `id` into the nested route.
	 *
	 * @return void
	 */
	public function test_delete_product_variation_dispatches_to_substituted_route(): void {
		$result = blu_get_ability( 'blu/wc-delete-product-variation' )->execute(
			array(
				'product_id' => 12,
				'id'         => 34,
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( '/wc/v3/products/12/variations/34', $result['message']['route'] );
	}
}
