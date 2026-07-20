<?php

namespace BLU;

use BLU\Abilities\RestApiUtils;

/**
 * Tests for RestApiUtils route discovery and route-building helpers.
 *
 * @covers \BLU\Abilities\RestApiUtils
 */
class RestApiUtilsWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Registered test routes for the current test case.
	 *
	 * @var array<int, array{0:string,1:string}>
	 */
	private $registered_test_routes = array();

	/**
	 * Reset RestApiUtils caches before each test.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		RestApiUtils::reset_cache();
	}

	/**
	 * Tear down test routes and caches.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		RestApiUtils::reset_cache();
		parent::tear_down();
	}

	/**
	 * Register a synthetic REST route for discovery tests.
	 *
	 * @param string $namespace Namespace prefix.
	 * @param string $route     Route path relative to namespace.
	 *
	 * @return void
	 */
	private function register_test_route( string $namespace, string $route ): void {
		$full_route = '/' . trim( $namespace, '/' ) . '/' . ltrim( $route, '/' );

		register_rest_route(
			$namespace,
			'/' . ltrim( $route, '/' ),
			array(
				'methods'             => 'GET',
				'callback'            => static function () {
					return rest_ensure_response( array( 'ok' => true ) );
				},
				'permission_callback' => '__return_true',
			)
		);

		$this->registered_test_routes[] = array( $namespace, $route );
		RestApiUtils::reset_cache();
	}

	/**
	 * Verifies get_latest_namespace returns the highest versioned namespace.
	 *
	 * @return void
	 */
	public function test_get_latest_namespace_returns_highest_version(): void {
		$this->register_test_route( 'blu-test/v1', 'items' );
		$this->register_test_route( 'blu-test/v2', 'items' );
		$this->register_test_route( 'blu-test/v3', 'items' );

		$this->assertSame( 'blu-test/v3', RestApiUtils::get_latest_namespace( 'blu-test' ) );
	}

	/**
	 * Verifies find_route_by_resource matches collection routes.
	 *
	 * @return void
	 */
	public function test_find_route_by_resource_exact_match(): void {
		$this->register_test_route( 'wp/v2', 'posts' );

		$route = RestApiUtils::find_route_by_resource( 'wp/v2', 'posts' );

		$this->assertNotNull( $route );
		$this->assertStringContainsString( '/wp/v2/posts', $route );
	}

	/**
	 * Verifies find_route_by_resource matches parameterized routes in the middle of a path.
	 *
	 * @return void
	 */
	public function test_find_route_by_resource_parameter_in_middle(): void {
		$this->register_test_route( 'wc/v3', 'products/(?P<id>[\d]+)/variations' );

		$route = RestApiUtils::find_route_by_resource(
			'wc/v3',
			'products/(?P<id>[\d]+)/variations'
		);

		$this->assertNotNull( $route );
		$this->assertStringContainsString( 'products', $route );
		$this->assertStringContainsString( 'variations', $route );
	}

	/**
	 * Verifies normalize_route_for_match does not leave double slashes.
	 *
	 * @return void
	 */
	public function test_normalize_route_for_match_collapses_slashes(): void {
		$normalized = RestApiUtils::normalize_route_for_match(
			'/wc/v3/products/(?P<id>[\d]+)/variations'
		);

		$this->assertSame( '/wc/v3/products/variations', $normalized );
		$this->assertStringNotContainsString( '//', $normalized );
	}

	/**
	 * Verifies build_item_route appends a positive integer ID.
	 *
	 * @return void
	 */
	public function test_build_item_route_with_valid_id(): void {
		$route = RestApiUtils::build_item_route( '/wp/v2/posts/(?P<id>[\d]+)', 42 );

		$this->assertSame( '/wp/v2/posts/42', $route );
	}

	/**
	 * Verifies build_item_route returns null for invalid IDs.
	 *
	 * @return void
	 */
	public function test_build_item_route_returns_null_for_invalid_id(): void {
		$this->assertNull( RestApiUtils::build_item_route( '/wp/v2/posts', null ) );
		$this->assertNull( RestApiUtils::build_item_route( '/wp/v2/posts', 0 ) );
		$this->assertNull( RestApiUtils::build_item_route( '/wp/v2/posts', -1 ) );
	}

	/**
	 * Verifies substitute_route_params leaves numeric IDs unencoded.
	 *
	 * @return void
	 */
	public function test_substitute_route_params_numeric_values(): void {
		$route = RestApiUtils::substitute_route_params(
			'/wc/v3/products/(?P<product_id>[\d]+)/variations/(?P<id>[\d]+)',
			array(
				'product_id' => 10,
				'id'         => 55,
			)
		);

		$this->assertSame( '/wc/v3/products/10/variations/55', $route );
	}

	/**
	 * Verifies substitute_route_params encodes non-numeric path values.
	 *
	 * @return void
	 */
	public function test_substitute_route_params_encodes_non_numeric_values(): void {
		$route = RestApiUtils::substitute_route_params(
			'/wp/v2/posts/(?P<slug>[^/]+)',
			array( 'slug' => 'hello world' )
		);

		$this->assertSame( '/wp/v2/posts/hello%20world', $route );
	}

	/**
	 * Verifies get_latest_available_rest_route resolves a core WP collection route.
	 *
	 * @return void
	 */
	public function test_get_latest_available_rest_route_for_wp_posts(): void {
		$this->register_test_route( 'wp/v2', 'posts' );

		$route = RestApiUtils::get_latest_available_rest_route( 'wp', 'posts' );

		$this->assertNotNull( $route );
		$this->assertStringContainsString( '/wp/v2/posts', $route );
	}

	/**
	 * Verifies resolve_param_route works when eager loading is enabled.
	 *
	 * @return void
	 */
	public function test_resolve_param_route_with_eager_loading(): void {
		$this->register_test_route( 'blu-test/v1', 'parents/(?P<parent_id>[\d]+)/children' );

		add_filter( 'blu_mcp_list_api_eager_load', '__return_true' );

		$route = RestApiUtils::resolve_param_route(
			'blu-test',
			'parents/(?P<parent_id>[\d]+)/children',
			array( 'parent_id' => 7 )
		);

		remove_filter( 'blu_mcp_list_api_eager_load', '__return_true' );

		$this->assertNotNull( $route );
		$this->assertStringContainsString( '/parents/7/children', $route );
	}

	/**
	 * Verifies resolve_param_route still resolves when eager loading is disabled.
	 *
	 * @return void
	 */
	public function test_resolve_param_route_without_eager_loading(): void {
		$this->register_test_route( 'blu-test/v1', 'parents/(?P<parent_id>[\d]+)/children' );

		add_filter( 'blu_mcp_list_api_eager_load', '__return_false' );

		$route = RestApiUtils::resolve_param_route(
			'blu-test',
			'parents/(?P<parent_id>[\d]+)/children',
			array( 'parent_id' => 9 )
		);

		remove_filter( 'blu_mcp_list_api_eager_load', '__return_false' );

		$this->assertNotNull( $route );
		$this->assertStringContainsString( '/parents/9/children', $route );
	}
}
