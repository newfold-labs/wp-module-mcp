<?php

namespace BLU;

use BLU\Abilities\RestApiCrud;

/**
 * Tests for RestApiCrud abilities, focused on the filter and output-shape behavior of blu/list-api-functions.
 *
 * @covers \BLU\Abilities\RestApiCrud
 */
class RestApiCrudWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Names of abilities registered during tests that need cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array();

	/**
	 * Test REST routes registered during set_up — registered against rest_get_server() directly.
	 * Format: array of [namespace, route].
	 *
	 * @var array<int, array{0:string,1:string}>
	 */
	private $registered_test_routes = array();

	/**
	 * Set up: ensure abilities API exists, log in as admin, register category, register REST test routes, register abilities.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->ensure_category();
		$this->register_test_routes();
		$this->register_rest_api_crud();
	}

	/**
	 * Tear down: unregister abilities registered by these tests.
	 *
	 * Test REST routes registered against rest_get_server() persist for the remainder of the request,
	 * which is harmless — other tests don't assert that the catalog is empty.
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
	 * Register synthetic REST routes the tests can rely on.
	 *
	 * Registers directly on the WP_REST_Server instance to avoid register_rest_route()'s
	 * `_doing_it_wrong` notice when called after the rest_api_init action has already fired
	 * (which is the case once the test bootstrap has run).
	 *
	 * @return void
	 */
	private function register_test_routes(): void {
		$this->registered_test_routes = array(
			array( 'blu-test/v1', '/blu-test/v1/widgets' ),
			array( 'blu-test/v1', '/blu-test/v1/sprockets' ),
			array( 'blu-test/v2', '/blu-test/v2/gadgets' ),
			array( 'blu-flat', '/blu-flat' ),
			array( 'blu-flat', '/blu-flat/items' ),
			array( 'blu-multi/v1', '/blu-multi/v1/combo' ),
		);

		$server = rest_get_server();

		$server->register_route(
			'blu-test/v1',
			'/blu-test/v1/widgets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => '__return_true',
					'permission_callback' => '__return_true',
				),
				array(
					'methods'             => 'POST',
					'callback'            => '__return_true',
					'permission_callback' => '__return_true',
				),
			),
			true
		);

		$server->register_route(
			'blu-test/v1',
			'/blu-test/v1/sprockets',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => '__return_true',
					'permission_callback' => '__return_true',
				),
			),
			true
		);

		$server->register_route(
			'blu-test/v2',
			'/blu-test/v2/gadgets',
			array(
				array(
					'methods'             => 'DELETE',
					'callback'            => '__return_true',
					'permission_callback' => '__return_true',
				),
			),
			true
		);

		// Single-segment (unversioned) namespace — same shape as `wc-analytics`.
		$server->register_route(
			'blu-flat',
			'/blu-flat',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => '__return_true',
					'permission_callback' => '__return_true',
				),
			),
			true
		);

		$server->register_route(
			'blu-flat',
			'/blu-flat/items',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => '__return_true',
					'permission_callback' => '__return_true',
				),
			),
			true
		);

		// Endpoint that declares multiple HTTP methods in a single definition
		// (e.g. WP_REST_Server::EDITABLE = "POST, PUT, PATCH"). The catalog must
		// emit one row per method, not just the first key().
		$server->register_route(
			'blu-multi/v1',
			'/blu-multi/v1/combo',
			array(
				array(
					'methods'             => 'POST, PATCH, DELETE',
					'callback'            => '__return_true',
					'permission_callback' => '__return_true',
				),
			),
			true
		);
	}

	/**
	 * Register the RestApiCrud abilities via the wp_abilities_api_init hook.
	 *
	 * @return void
	 */
	private function register_rest_api_crud(): void {
		$cb = function () {
			new RestApiCrud();
		};
		add_action( 'wp_abilities_api_init', $cb, 10 );

		$count_before = did_action( 'wp_abilities_api_init' );
		$registry     = \WP_Abilities_Registry::get_instance();
		if ( $registry && did_action( 'wp_abilities_api_init' ) === $count_before ) {
			do_action( 'wp_abilities_api_init', $registry );
		}
		remove_action( 'wp_abilities_api_init', $cb, 10 );

		$this->registered_abilities = array(
			'blu/list-api-functions',
			'blu/get-function-details',
			'blu/run-api-function',
		);
	}

	/**
	 * Convenience: run list-api-functions and return the response message array.
	 *
	 * @param array|null $input Input arguments.
	 *
	 * @return array
	 */
	private function execute_list( $input = null ): array {
		$result = blu_get_ability( 'blu/list-api-functions' )->execute( $input );
		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'success', $result['status'] );
		$this->assertIsArray( $result['message'] );
		return $result['message'];
	}

	/**
	 * Verifies blu/list-api-functions is registered.
	 *
	 * @return void
	 */
	public function test_list_api_functions_is_registered(): void {
		$this->assertNotNull( blu_get_ability( 'blu/list-api-functions' ) );
	}

	/**
	 * Verifies the input schema declares namespace, methods, search and rejects additional properties.
	 *
	 * @return void
	 */
	public function test_input_schema_has_filter_properties(): void {
		$schema = blu_get_ability( 'blu/list-api-functions' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'namespace', $schema['properties'] );
		$this->assertSame( 'string', $schema['properties']['namespace']['type'] );

		$this->assertArrayHasKey( 'methods', $schema['properties'] );
		$this->assertSame( 'array', $schema['properties']['methods']['type'] );
		$this->assertSame( array( 'GET', 'POST', 'PATCH', 'DELETE' ), $schema['properties']['methods']['items']['enum'] );
		$this->assertTrue( $schema['properties']['methods']['uniqueItems'] );

		$this->assertArrayHasKey( 'search', $schema['properties'] );
		$this->assertSame( 'string', $schema['properties']['search']['type'] );

		$this->assertArrayHasKey( 'additionalProperties', $schema );
		$this->assertFalse( $schema['additionalProperties'] );
	}

	/**
	 * Verifies an empty input returns the full catalog and every item carries a namespace field.
	 *
	 * @return void
	 */
	public function test_empty_input_returns_catalog_with_namespace_field(): void {
		$items = $this->execute_list( array() );

		$this->assertNotEmpty( $items );
		foreach ( $items as $item ) {
			$this->assertArrayHasKey( 'route', $item );
			$this->assertArrayHasKey( 'method', $item );
			$this->assertArrayHasKey( 'namespace', $item );
			$this->assertIsString( $item['namespace'] );
		}

		$routes = array_column( $items, 'route' );
		$this->assertContains( '/blu-test/v1/widgets', $routes );
		$this->assertContains( '/blu-test/v1/sprockets', $routes );
		$this->assertContains( '/blu-test/v2/gadgets', $routes );
	}

	/**
	 * Verifies single-segment (unversioned) namespaces — like `wc-analytics` — are derived
	 * correctly. Both the namespace root route (`/blu-flat`) and a sub-route (`/blu-flat/items`)
	 * must resolve to the registered namespace `blu-flat`, not the empty string and not
	 * `blu-flat/items`.
	 *
	 * @return void
	 */
	public function test_single_segment_namespace_is_derived_correctly(): void {
		$items    = $this->execute_list( array( 'namespace' => 'blu-flat' ) );
		$by_route = array_column( $items, 'namespace', 'route' );

		$this->assertArrayHasKey( '/blu-flat', $by_route, 'Namespace root route should be returned' );
		$this->assertArrayHasKey( '/blu-flat/items', $by_route, 'Sub-route should be returned' );
		$this->assertSame( 'blu-flat', $by_route['/blu-flat'] );
		$this->assertSame( 'blu-flat', $by_route['/blu-flat/items'] );
	}

	/**
	 * Verifies namespace filter isolates routes by their first two path segments.
	 *
	 * @return void
	 */
	public function test_namespace_filter_isolates_segment(): void {
		$items = $this->execute_list( array( 'namespace' => 'blu-test/v1' ) );

		$this->assertNotEmpty( $items );
		foreach ( $items as $item ) {
			$this->assertSame( 'blu-test/v1', $item['namespace'] );
		}
		$routes = array_column( $items, 'route' );
		$this->assertContains( '/blu-test/v1/widgets', $routes );
		$this->assertContains( '/blu-test/v1/sprockets', $routes );
		$this->assertNotContains( '/blu-test/v2/gadgets', $routes );
	}

	/**
	 * Verifies the namespace filter is tolerant of leading and trailing slashes.
	 *
	 * @return void
	 */
	public function test_namespace_filter_tolerates_slashes(): void {
		$bare  = $this->execute_list( array( 'namespace' => 'blu-test/v1' ) );
		$lead  = $this->execute_list( array( 'namespace' => '/blu-test/v1' ) );
		$trail = $this->execute_list( array( 'namespace' => 'blu-test/v1/' ) );
		$both  = $this->execute_list( array( 'namespace' => '/blu-test/v1/' ) );

		$this->assertSame( $bare, $lead );
		$this->assertSame( $bare, $trail );
		$this->assertSame( $bare, $both );
	}

	/**
	 * Verifies methods filter narrows to listed HTTP verbs.
	 *
	 * @return void
	 */
	public function test_methods_filter_narrows_results(): void {
		$items = $this->execute_list(
			array(
				'namespace' => 'blu-test/v1',
				'methods'   => array( 'GET' ),
			)
		);

		$this->assertNotEmpty( $items );
		foreach ( $items as $item ) {
			$this->assertSame( 'GET', $item['method'] );
		}

		$routes = array_column( $items, 'route' );
		$this->assertContains( '/blu-test/v1/widgets', $routes );
		$this->assertContains( '/blu-test/v1/sprockets', $routes );
	}

	/**
	 * Verifies an empty methods array is treated the same as no filter (all methods allowed).
	 *
	 * @return void
	 */
	public function test_empty_methods_array_includes_all(): void {
		$with_empty = $this->execute_list(
			array(
				'namespace' => 'blu-test/v1',
				'methods'   => array(),
			)
		);
		$without    = $this->execute_list( array( 'namespace' => 'blu-test/v1' ) );

		$this->assertSame( $without, $with_empty );
	}

	/**
	 * Verifies search filter narrows by substring on the route.
	 *
	 * @return void
	 */
	public function test_search_filter_substring_on_route(): void {
		$items  = $this->execute_list( array( 'search' => 'widgets' ) );
		$routes = array_column( $items, 'route' );

		$this->assertContains( '/blu-test/v1/widgets', $routes );
		$this->assertNotContains( '/blu-test/v1/sprockets', $routes );
		$this->assertNotContains( '/blu-test/v2/gadgets', $routes );
	}

	/**
	 * Verifies search filter is case-insensitive.
	 *
	 * @return void
	 */
	public function test_search_filter_case_insensitive(): void {
		$lower = $this->execute_list( array( 'search' => 'widgets' ) );
		$upper = $this->execute_list( array( 'search' => 'WIDGETS' ) );
		$mixed = $this->execute_list( array( 'search' => 'WidGets' ) );

		$this->assertSame( $lower, $upper );
		$this->assertSame( $lower, $mixed );
	}

	/**
	 * Verifies that combining namespace + methods + search composes with AND semantics.
	 *
	 * @return void
	 */
	public function test_filters_compose_with_and(): void {
		$items = $this->execute_list(
			array(
				'namespace' => 'blu-test/v1',
				'methods'   => array( 'POST' ),
				'search'    => 'widget',
			)
		);

		$this->assertCount( 1, $items );
		$this->assertSame( '/blu-test/v1/widgets', $items[0]['route'] );
		$this->assertSame( 'POST', $items[0]['method'] );
		$this->assertSame( 'blu-test/v1', $items[0]['namespace'] );
	}

	/**
	 * Verifies namespace filter returning no matches yields an empty array (not an error).
	 *
	 * @return void
	 */
	public function test_namespace_filter_no_matches_returns_empty(): void {
		$items = $this->execute_list( array( 'namespace' => 'nonexistent/v9' ) );
		$this->assertSame( array(), $items );
	}

	/**
	 * Verifies that an endpoint registered with multiple HTTP methods in one definition
	 * (e.g. WP_REST_Server::EDITABLE = "POST, PUT, PATCH") emits one row per method.
	 *
	 * Filters by `search` on the route path because the synthetic namespace also has an
	 * auto-registered index route at `/blu-multi/v1` (GET) that we don't want to mix in.
	 *
	 * @return void
	 */
	public function test_combined_methods_endpoint_emits_one_row_per_method(): void {
		$items = $this->execute_list( array( 'search' => '/blu-multi/v1/combo' ) );

		$combo_methods = array();
		foreach ( $items as $item ) {
			if ( '/blu-multi/v1/combo' === $item['route'] ) {
				$combo_methods[] = $item['method'];
				$this->assertSame( 'blu-multi/v1', $item['namespace'] );
			}
		}
		sort( $combo_methods );

		$this->assertSame( array( 'DELETE', 'PATCH', 'POST' ), $combo_methods );
	}

	/**
	 * Verifies the methods filter narrows correctly even when the underlying endpoint
	 * declared multiple methods — i.e. asking for PATCH on a combined POST/PATCH/DELETE
	 * endpoint must still return the PATCH row.
	 *
	 * @return void
	 */
	public function test_methods_filter_matches_within_combined_methods_endpoint(): void {
		$items = $this->execute_list(
			array(
				'namespace' => 'blu-multi/v1',
				'methods'   => array( 'PATCH' ),
			)
		);

		$this->assertCount( 1, $items );
		$this->assertSame( '/blu-multi/v1/combo', $items[0]['route'] );
		$this->assertSame( 'PATCH', $items[0]['method'] );
	}

	/**
	 * Verifies the MCP transport route (/blu/mcp) is excluded from the catalog so the
	 * LLM cannot discover-and-call its way back into the transport.
	 *
	 * @return void
	 */
	public function test_blu_mcp_route_is_excluded_from_catalog(): void {
		$items  = $this->execute_list( array() );
		$routes = array_column( $items, 'route' );

		$this->assertNotContains( '/blu/mcp', $routes );
	}

	/**
	 * Verifies run-api-function refuses to dispatch to the MCP transport route.
	 *
	 * @return void
	 */
	public function test_run_api_function_blocks_blu_mcp_recursion(): void {
		$result = blu_get_ability( 'blu/run-api-function' )->execute(
			array(
				'route'  => '/blu/mcp',
				'method' => 'POST',
				'data'   => array(),
			)
		);
		$this->assertSame( 400, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );

		$result_nested = blu_get_ability( 'blu/run-api-function' )->execute(
			array(
				'route'  => '/blu/mcp/anything',
				'method' => 'POST',
				'data'   => array(),
			)
		);
		$this->assertSame( 400, $result_nested['statusCode'] );
	}

	/**
	 * Verifies get-function-details returns the endpoint metadata but strips callback
	 * and permission_callback (which leak internal class names and JSON-encode poorly).
	 *
	 * @return void
	 */
	public function test_get_function_details_strips_callables(): void {
		$result = blu_get_ability( 'blu/get-function-details' )->execute(
			array(
				'route'  => '/blu-test/v1/widgets',
				'method' => 'GET',
			)
		);
		$this->assertSame( 200, $result['statusCode'] );
		$endpoint = $result['message'];
		$this->assertIsArray( $endpoint );
		$this->assertArrayHasKey( 'methods', $endpoint );
		$this->assertArrayNotHasKey( 'callback', $endpoint );
		$this->assertArrayNotHasKey( 'permission_callback', $endpoint );
	}

	/**
	 * Verifies get-function-details returns 404 for an unknown route.
	 *
	 * @return void
	 */
	public function test_get_function_details_returns_404_for_unknown_route(): void {
		$result = blu_get_ability( 'blu/get-function-details' )->execute(
			array(
				'route'  => '/nonexistent/v9/nope',
				'method' => 'GET',
			)
		);
		$this->assertSame( 404, $result['statusCode'] );
	}

	/**
	 * Verifies get-function-details returns 404 when the route exists but not the method.
	 *
	 * @return void
	 */
	public function test_get_function_details_returns_404_for_missing_method(): void {
		$result = blu_get_ability( 'blu/get-function-details' )->execute(
			array(
				'route'  => '/blu-test/v1/sprockets',
				'method' => 'DELETE',
			)
		);
		$this->assertSame( 404, $result['statusCode'] );
	}

	/**
	 * Verifies the hardcoded ignore lists still apply even with permissive filters.
	 *
	 * @return void
	 */
	public function test_ignored_routes_still_excluded(): void {
		$items  = $this->execute_list( array() );
		$routes = array_column( $items, 'route' );

		$this->assertNotContains( '/', $routes );
		$this->assertNotContains( '/batch/v1', $routes );
		foreach ( $routes as $route ) {
			$this->assertStringNotContainsString( 'oembed', $route );
			$this->assertStringNotContainsString( 'autosaves', $route );
			$this->assertStringNotContainsString( 'revisions', $route );
			$this->assertStringNotContainsString( 'jwt-auth', $route );
		}
	}
}
