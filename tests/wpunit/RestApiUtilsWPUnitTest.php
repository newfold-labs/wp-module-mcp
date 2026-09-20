<?php

namespace BLU;

use BLU\Abilities\RestApiUtils;

/**
 * Tests for RestApiUtils, focused on the areas flagged in review:
 *  - Generated ability schemas must require named route captures (`id`,
 *    `product_id`, etc.) even when the native REST controller's own `args`
 *    definition does not mark them required (the URL regex already
 *    guarantees them there, but that guarantee doesn't carry over to a
 *    plain ability input property).
 *  - `get_latest_available_rest_route()` / `resolve_param_route()` must be
 *    resource-aware: search matching namespaces in descending version
 *    order for the requested resource, rather than picking the highest
 *    namespace first and failing if that namespace doesn't expose it.
 *  - `args_to_input_schema()` must preserve `pattern`, `minItems`, and
 *    `maxItems` from the native REST arg definition.
 *
 * Routes are registered directly on rest_get_server() under synthetic
 * namespaces so these tests never touch real `wp`/`wc` routes.
 *
 * @covers \BLU\Abilities\RestApiUtils
 */
class RestApiUtilsWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Register a stub REST route directly on the server.
	 *
	 * @param string $namespace Namespace, e.g. "blu-ru-test/v1".
	 * @param string $route     Full route, e.g. "/blu-ru-test/v1/widgets".
	 * @param string $method    HTTP method, e.g. "GET".
	 * @param array  $args      Endpoint `args` definition.
	 *
	 * @return void
	 */
	private function register_stub_route( string $namespace, string $route, string $method, array $args = array() ): void {
		rest_get_server()->register_route(
			$namespace,
			$route,
			array(
				array(
					'methods'             => $method,
					'callback'            => function ( \WP_REST_Request $request ) {
						return new \WP_REST_Response(
							array(
								'route'  => $request->get_route(),
								'params' => $request->get_params(),
							),
							200
						);
					},
					'permission_callback' => '__return_true',
					'args'                => $args,
				),
			),
			true
		);
	}

	// -----------------------------------------------------------------
	// get_versioned_namespaces() / get_latest_namespace()
	// -----------------------------------------------------------------

	/**
	 * Verifies versioned namespaces are returned newest-first.
	 *
	 * @return void
	 */
	public function test_get_versioned_namespaces_orders_newest_first(): void {
		$this->register_stub_route( 'blu-ns-test/v1', '/blu-ns-test/v1/items', 'GET' );
		$this->register_stub_route( 'blu-ns-test/v3', '/blu-ns-test/v3/items', 'GET' );
		$this->register_stub_route( 'blu-ns-test/v2', '/blu-ns-test/v2/items', 'GET' );

		$namespaces = RestApiUtils::get_versioned_namespaces( 'blu-ns-test' );

		$this->assertSame( array( 'blu-ns-test/v3', 'blu-ns-test/v2', 'blu-ns-test/v1' ), $namespaces );
	}

	/**
	 * Verifies get_latest_namespace() picks the highest version.
	 *
	 * @return void
	 */
	public function test_get_latest_namespace_picks_highest_version(): void {
		$this->register_stub_route( 'blu-ns-test/v1', '/blu-ns-test/v1/items', 'GET' );
		$this->register_stub_route( 'blu-ns-test/v2', '/blu-ns-test/v2/items', 'GET' );

		$this->assertSame( 'blu-ns-test/v2', RestApiUtils::get_latest_namespace( 'blu-ns-test' ) );
	}

	/**
	 * Verifies an exact unversioned match (e.g. "wc-analytics") is returned
	 * as-is, as the sole candidate.
	 *
	 * @return void
	 */
	public function test_get_versioned_namespaces_returns_exact_unversioned_match(): void {
		$this->register_stub_route( 'blu-flat-ns-test', '/blu-flat-ns-test/items', 'GET' );

		$this->assertSame( array( 'blu-flat-ns-test' ), RestApiUtils::get_versioned_namespaces( 'blu-flat-ns-test' ) );
		$this->assertSame( 'blu-flat-ns-test', RestApiUtils::get_latest_namespace( 'blu-flat-ns-test' ) );
	}

	/**
	 * Verifies an unmatched base namespace returns null/empty rather than throwing.
	 *
	 * @return void
	 */
	public function test_get_latest_namespace_returns_null_when_unmatched(): void {
		$this->assertNull( RestApiUtils::get_latest_namespace( 'blu-nonexistent-namespace' ) );
		$this->assertSame( array(), RestApiUtils::get_versioned_namespaces( 'blu-nonexistent-namespace' ) );
	}

	// -----------------------------------------------------------------
	// get_latest_available_rest_route() / find_route_by_resource_across_versions() —
	// resource-aware namespace fallback
	// -----------------------------------------------------------------

	/**
	 * Verifies discovery falls back to an older namespace when the newest
	 * namespace doesn't expose the requested resource, instead of returning
	 * null. This is the core fix for the "not resource-aware" report.
	 *
	 * @return void
	 */
	public function test_falls_back_to_older_namespace_when_newest_lacks_resource(): void {
		// Only v1 exposes "legacy-report".
		$this->register_stub_route( 'blu-fallback-test/v1', '/blu-fallback-test/v1/legacy-report', 'GET' );
		// v2 is newer, but only exposes "widgets" — not "legacy-report".
		$this->register_stub_route( 'blu-fallback-test/v2', '/blu-fallback-test/v2/widgets', 'GET' );

		// The highest namespace is still v2...
		$this->assertSame( 'blu-fallback-test/v2', RestApiUtils::get_latest_namespace( 'blu-fallback-test' ) );

		// ...but a resource only v1 exposes must still resolve, by falling back to v1.
		$route = RestApiUtils::get_latest_available_rest_route( 'blu-fallback-test', 'legacy-report' );
		$this->assertSame( '/blu-fallback-test/v1/legacy-report', $route );
	}

	/**
	 * Verifies a resource available in the newest namespace still resolves there
	 * (i.e. the fallback doesn't skip the newest namespace unnecessarily).
	 *
	 * @return void
	 */
	public function test_resolves_in_newest_namespace_when_resource_available_there(): void {
		$this->register_stub_route( 'blu-fallback-test/v1', '/blu-fallback-test/v1/legacy-report', 'GET' );
		$this->register_stub_route( 'blu-fallback-test/v2', '/blu-fallback-test/v2/widgets', 'GET' );

		$route = RestApiUtils::get_latest_available_rest_route( 'blu-fallback-test', 'widgets' );
		$this->assertSame( '/blu-fallback-test/v2/widgets', $route );
	}

	/**
	 * Verifies a resource missing from every matching namespace returns null.
	 *
	 * @return void
	 */
	public function test_returns_null_when_no_namespace_exposes_resource(): void {
		$this->register_stub_route( 'blu-fallback-test/v1', '/blu-fallback-test/v1/legacy-report', 'GET' );

		$route = RestApiUtils::get_latest_available_rest_route( 'blu-fallback-test', 'nonexistent-resource' );
		$this->assertNull( $route );
	}

	/**
	 * Verifies resolve_param_route() is likewise resource-aware: a parameterized
	 * resource only present in an older namespace must still resolve and have its
	 * capture substituted, even though a newer namespace exists.
	 *
	 * @return void
	 */
	public function test_resolve_param_route_falls_back_to_older_namespace(): void {
		$this->register_stub_route( 'blu-fallback-test/v1', '/blu-fallback-test/v1/legacy-items/(?P<id>[\d]+)', 'GET' );
		$this->register_stub_route( 'blu-fallback-test/v2', '/blu-fallback-test/v2/widgets', 'GET' );

		$route = RestApiUtils::resolve_param_route( 'blu-fallback-test', 'legacy-items/(?P<id>[\d]+)', array( 'id' => 42 ) );

		$this->assertSame( '/blu-fallback-test/v1/legacy-items/42', $route );
	}

	// -----------------------------------------------------------------
	// find_route_by_resource() — basic + parameterized
	// -----------------------------------------------------------------

	/**
	 * Verifies find_route_by_resource() matches a plain collection route.
	 *
	 * @return void
	 */
	public function test_find_route_by_resource_matches_collection(): void {
		$this->register_stub_route( 'blu-find-test/v1', '/blu-find-test/v1/widgets', 'GET' );

		$this->assertSame(
			'/blu-find-test/v1/widgets',
			RestApiUtils::find_route_by_resource( 'blu-find-test/v1', 'widgets' )
		);
	}

	/**
	 * Verifies find_route_by_resource() matches the parameterized form of a route.
	 *
	 * @return void
	 */
	public function test_find_route_by_resource_matches_parameterized_route(): void {
		$this->register_stub_route( 'blu-find-test/v1', '/blu-find-test/v1/widgets/(?P<id>[\d]+)', 'GET' );

		$this->assertSame(
			'/blu-find-test/v1/widgets/(?P<id>[\d]+)',
			RestApiUtils::find_route_by_resource( 'blu-find-test/v1', 'widgets/(?P<id>[\d]+)' )
		);
	}

	// -----------------------------------------------------------------
	// substitute_route_params() / build_item_route()
	// -----------------------------------------------------------------

	/**
	 * Verifies substitute_route_params() replaces named captures with concrete values.
	 *
	 * @return void
	 */
	public function test_substitute_route_params_replaces_named_captures(): void {
		$route = RestApiUtils::substitute_route_params(
			'/wc/v3/products/(?P<product_id>[\d]+)/variations/(?P<id>[\d]+)',
			array(
				'product_id' => 7,
				'id'         => 42,
			)
		);

		$this->assertSame( '/wc/v3/products/7/variations/42', $route );
	}

	/**
	 * Verifies build_item_route() strips a trailing capture and appends the ID.
	 *
	 * @return void
	 */
	public function test_build_item_route_appends_id(): void {
		$this->assertSame(
			'/wc/v3/products/9',
			RestApiUtils::build_item_route( '/wc/v3/products/(?P<id>[\d]+)', 9 )
		);
	}

	// -----------------------------------------------------------------
	// extract_input_schema() — required path parameters
	// -----------------------------------------------------------------

	/**
	 * Verifies extract_input_schema() marks a named route capture required even
	 * when the endpoint's own `args` definition doesn't declare it at all —
	 * matching native REST controllers, which rely on the URL regex instead.
	 *
	 * @return void
	 */
	public function test_extract_input_schema_requires_missing_path_param(): void {
		$this->register_stub_route(
			'blu-schema-test/v1',
			'/blu-schema-test/v1/widgets/(?P<id>[\d]+)',
			'GET',
			array(
				'context' => array( 'type' => 'string' ),
			)
		);

		$schema = RestApiUtils::extract_input_schema( '/blu-schema-test/v1/widgets/(?P<id>[\d]+)', 'GET' );

		$this->assertArrayHasKey( 'id', $schema['properties'], 'A property must be synthesized for the missing path param' );
		$this->assertSame( 'integer', $schema['properties']['id']['type'] );
		$this->assertArrayHasKey( 'required', $schema );
		$this->assertContains( 'id', $schema['required'] );
	}

	/**
	 * Verifies extract_input_schema() still marks a route capture required when
	 * the endpoint's own `args` definition DOES declare the property (but not as
	 * required) — the common WC/WP shape — while preserving its declared
	 * description instead of overwriting it with a generic one.
	 *
	 * @return void
	 */
	public function test_extract_input_schema_requires_declared_but_unrequired_path_param(): void {
		$this->register_stub_route(
			'blu-schema-test/v1',
			'/blu-schema-test/v1/gizmos/(?P<id>[\d]+)',
			'PUT',
			array(
				'id'   => array(
					'type'        => 'integer',
					'description' => 'Gizmo ID',
				),
				'name' => array( 'type' => 'string' ),
			)
		);

		$schema = RestApiUtils::extract_input_schema( '/blu-schema-test/v1/gizmos/(?P<id>[\d]+)', 'PUT' );

		$this->assertSame( 'Gizmo ID', $schema['properties']['id']['description'] );
		$this->assertArrayHasKey( 'required', $schema );
		$this->assertContains( 'id', $schema['required'] );
	}

	/**
	 * Verifies multiple named captures (e.g. a nested product/variation route)
	 * are all marked required.
	 *
	 * @return void
	 */
	public function test_extract_input_schema_requires_all_multiple_path_params(): void {
		$this->register_stub_route(
			'blu-schema-test/v1',
			'/blu-schema-test/v1/products/(?P<product_id>[\d]+)/variations/(?P<id>[\d]+)',
			'DELETE',
			array()
		);

		$schema = RestApiUtils::extract_input_schema(
			'/blu-schema-test/v1/products/(?P<product_id>[\d]+)/variations/(?P<id>[\d]+)',
			'DELETE'
		);

		$this->assertArrayHasKey( 'required', $schema );
		$this->assertContains( 'product_id', $schema['required'] );
		$this->assertContains( 'id', $schema['required'] );
	}

	/**
	 * Verifies a route with no captures at all (a plain collection) is left alone —
	 * no spurious required properties are introduced.
	 *
	 * @return void
	 */
	public function test_extract_input_schema_leaves_collection_route_unrequired(): void {
		$this->register_stub_route(
			'blu-schema-test/v1',
			'/blu-schema-test/v1/widgets',
			'GET',
			array( 'search' => array( 'type' => 'string' ) )
		);

		$schema = RestApiUtils::extract_input_schema( '/blu-schema-test/v1/widgets', 'GET' );

		$this->assertArrayNotHasKey( 'required', $schema );
	}

	// -----------------------------------------------------------------
	// extract_path_param_names() / require_path_params() — direct unit tests
	// -----------------------------------------------------------------

	/**
	 * Verifies extract_path_param_names() finds every named capture, in order.
	 *
	 * @return void
	 */
	public function test_extract_path_param_names_finds_all_captures(): void {
		$names = RestApiUtils::extract_path_param_names( '/wc/v3/products/(?P<product_id>[\d]+)/variations/(?P<id>[\d]+)' );

		$this->assertSame( array( 'product_id', 'id' ), $names );
	}

	/**
	 * Verifies extract_path_param_names() returns an empty array for a route with no captures.
	 *
	 * @return void
	 */
	public function test_extract_path_param_names_empty_for_plain_route(): void {
		$this->assertSame( array(), RestApiUtils::extract_path_param_names( '/wc/v3/products' ) );
	}

	/**
	 * Verifies require_path_params() is a no-op when given no param names.
	 *
	 * @return void
	 */
	public function test_require_path_params_noop_when_empty(): void {
		$schema = array(
			'type'       => 'object',
			'properties' => array(),
		);

		$this->assertSame( $schema, RestApiUtils::require_path_params( $schema, array() ) );
	}

	/**
	 * Verifies require_path_params() de-duplicates when a name is already required.
	 *
	 * @return void
	 */
	public function test_require_path_params_deduplicates_required(): void {
		$schema = array(
			'type'       => 'object',
			'properties' => array(
				'id' => array( 'type' => 'integer' ),
			),
			'required'   => array( 'id' ),
		);

		$updated = RestApiUtils::require_path_params( $schema, array( 'id' ) );

		$this->assertSame( array( 'id' ), $updated['required'] );
	}

	// -----------------------------------------------------------------
	// args_to_input_schema() — schema constraint preservation
	// -----------------------------------------------------------------

	/**
	 * Verifies pattern, minItems, and maxItems from a native REST arg definition
	 * are preserved on the generated ability schema.
	 *
	 * @return void
	 */
	public function test_args_to_input_schema_preserves_pattern_and_item_bounds(): void {
		$schema = RestApiUtils::args_to_input_schema(
			array(
				'slug'  => array(
					'type'    => 'string',
					'pattern' => '^[a-z0-9-]+$',
				),
				'terms' => array(
					'type'     => 'array',
					'items'    => array( 'type' => 'string' ),
					'minItems' => 1,
					'maxItems' => 5,
				),
			)
		);

		$this->assertSame( '^[a-z0-9-]+$', $schema['properties']['slug']['pattern'] );
		$this->assertSame( 1, $schema['properties']['terms']['minItems'] );
		$this->assertSame( 5, $schema['properties']['terms']['maxItems'] );
	}
}
