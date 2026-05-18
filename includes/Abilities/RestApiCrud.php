<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * RestApiCrud abilities for generic WordPress REST API operations.
 */
class RestApiCrud {

	/**
	 * Constructor - registers REST API CRUD abilities.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Derive the REST namespace for a route by matching against the set of namespaces
	 * actually registered with WordPress (longest prefix wins).
	 *
	 * WordPress REST namespaces may be single-segment (e.g. `wc-analytics`) or multi-segment
	 * (e.g. `wp/v2`, `wc/v3`, `wc-admin/marketing`). We cannot infer the boundary by counting
	 * segments — the truth lives in `WP_REST_Server::get_namespaces()`. The first call site
	 * fetches the list once and passes it in for every route to avoid repeated work.
	 *
	 * Examples (given `wp/v2`, `wc/v3`, `wc-analytics` are registered):
	 *   "/wp/v2/posts"                   → "wp/v2"
	 *   "/wc/v3/products/(?P<id>\d+)"    → "wc/v3"
	 *   "/wc-analytics"                  → "wc-analytics"
	 *   "/wc-analytics/reports/products" → "wc-analytics"
	 *   "/unknown-thing"                 → ""
	 *
	 * @param string   $route      Route path as registered with WordPress.
	 * @param string[] $namespaces Result of `WP_REST_Server::get_namespaces()`.
	 *
	 * @return string The longest matching registered namespace, or empty string if none match.
	 */
	private function derive_namespace( string $route, array $namespaces ): string {
		$route_trimmed = ltrim( $route, '/' );

		$matched = '';
		foreach ( $namespaces as $ns ) {
			if ( $route_trimmed !== $ns && strpos( $route_trimmed, $ns . '/' ) !== 0 ) {
				continue;
			}
			if ( strlen( $ns ) > strlen( $matched ) ) {
				$matched = $ns;
			}
		}

		return $matched;
	}

	/**
	 * Register REST API CRUD abilities.
	 */
	private function register_abilities(): void {
		// List available API functions
		blu_register_ability(
			'blu/list-api-functions',
			array(
				'label'               => 'List API Functions',
				'description'         => 'List WordPress REST API endpoints registered on this site. Each item includes `route` (e.g. "/wp/v2/posts"), `method` (GET/POST/PATCH/DELETE), and `namespace` (the namespace WordPress registered the route under, e.g. "wp/v2" or "wc-analytics"). Use the optional `namespace`, `methods`, and `search` filters to narrow.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'namespace' => array(
							'type'        => 'string',
							'description' => 'REST namespace as registered with WordPress, e.g. "wp/v2", "wc/v3", "wc-analytics", "wc-admin/marketing". Single-segment namespaces (unversioned, e.g. "wc-analytics") and multi-segment namespaces are both supported. Leading and trailing slashes are tolerated.',
							'minLength'   => 1,
							'maxLength'   => 100,
							'pattern'     => '^/?[A-Za-z0-9_-]+(/[A-Za-z0-9_-]+)*/?$',
						),
						'methods'   => array(
							'type'        => 'array',
							'description' => 'HTTP methods to include (uppercase). Omit or pass `[]` for all methods.',
							'items'       => array(
								'type' => 'string',
								'enum' => array( 'GET', 'POST', 'PATCH', 'DELETE' ),
							),
							'uniqueItems' => true,
							'maxItems'    => 4,
						),
						'search'    => array(
							'type'        => 'string',
							'description' => 'Case-insensitive substring filter on the route path.',
							'minLength'   => 1,
							'maxLength'   => 200,
						),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => function ( $input = null ) {
					$ignore_routes  = array( '/', '/batch/v1', '/blu/mcp' );
					$ignore_strings = array( 'oembed', 'autosaves', 'revisions', 'jwt-auth' );

					$ns_filter = isset( $input['namespace'] ) && is_string( $input['namespace'] ) ? trim( $input['namespace'], " \t\n\r\0\x0B/" ) : '';

					$method_filter = array();
					if ( isset( $input['methods'] ) && is_array( $input['methods'] ) ) {
						foreach ( $input['methods'] as $m ) {
							if ( is_string( $m ) && '' !== $m ) {
								$method_filter[] = strtoupper( $m );
							}
						}
						$method_filter = array_values( array_unique( $method_filter ) );
					}

					$search_filter = isset( $input['search'] ) && is_string( $input['search'] ) ? trim( $input['search'] ) : '';

					// Force lazy-loaded REST namespaces to register before enumerating routes.
					// WC 10.3+ (and any plugin using the same pattern) attaches a `rest_pre_dispatch`
					// filter that only registers its namespace when the incoming request's route
					// starts with that namespace, or with `/` for discovery. Since our MCP request
					// is routed to `/blu/mcp`, those filters never fire and ~150+ routes (e.g.
					// `wc-analytics/*`) would be invisible to this catalog. Firing the filter
					// with a synthetic root request triggers the same discovery path WC uses for
					// `/wp-json/` calls. Skippable via the `blu_mcp_list_api_eager_load` filter
					// for sites that prefer the perf saving.
					if ( apply_filters( 'blu_mcp_list_api_eager_load', true ) ) {
						$root_request = new \WP_REST_Request( 'GET', '/' );
						apply_filters( 'rest_pre_dispatch', null, rest_get_server(), $root_request );
					}

					$server     = rest_get_server();
					$routes     = $server->get_routes();
					$namespaces = $server->get_namespaces();
					$result     = array();

					foreach ( $routes as $route => $endpoints ) {
						if ( in_array( $route, $ignore_routes, true ) ) {
							continue;
						}

						$skip = false;
						foreach ( $ignore_strings as $ignore_string ) {
							if ( strpos( $route, $ignore_string ) !== false ) {
								$skip = true;
								break;
							}
						}
						if ( $skip ) {
							continue;
						}

						$namespace = $this->derive_namespace( $route, $namespaces );

						if ( '' !== $ns_filter && $namespace !== $ns_filter ) {
							continue;
						}

						if ( '' !== $search_filter && false === mb_stripos( $route, $search_filter ) ) {
							continue;
						}

						// One endpoint definition can declare multiple HTTP methods
						// (e.g. WP_REST_Server::EDITABLE = "POST, PUT, PATCH").
						// Also, the same route can appear in multiple endpoint defs
						// — dedupe by (route, method) so the catalog is one row per pair.
						$emitted = array();
						foreach ( $endpoints as $endpoint ) {
							if ( empty( $endpoint['methods'] ) || ! is_array( $endpoint['methods'] ) ) {
								continue;
							}
							foreach ( array_keys( $endpoint['methods'] ) as $method ) {
								if ( isset( $emitted[ $method ] ) ) {
									continue;
								}
								if ( ! empty( $method_filter ) && ! in_array( $method, $method_filter, true ) ) {
									continue;
								}
								$emitted[ $method ] = true;

								$result[] = array(
									'route'     => $route,
									'method'    => $method,
									'namespace' => $namespace,
								);
							}
						}
					}

					return blu_prepare_ability_response( 200, $result );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Get function details
		blu_register_ability(
			'blu/get-function-details',
			array(
				'label'               => 'Get Function Details',
				'description'         => 'Return the endpoint metadata (args schema, methods, accept_json, etc.) for one route + method pair, so the caller knows what parameters the endpoint accepts.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'route'  => array(
							'type'        => 'string',
							'description' => 'REST API route, including the leading slash (e.g. "/wp/v2/posts", "/wp/v2/posts/(?P<id>[\\d]+)"). Matches the `route` field from blu-list-api-functions.',
						),
						'method' => array(
							'type'        => 'string',
							'enum'        => array( 'GET', 'POST', 'PATCH', 'DELETE' ),
							'description' => 'HTTP method (uppercase).',
						),
					),
					'required'   => array( 'route', 'method' ),
				),
				'execute_callback'    => function ( $input ) {
					$route  = $input['route'];
					$method = $input['method'];

					$routes = rest_get_server()->get_routes();

					if ( ! isset( $routes[ $route ] ) ) {
						return blu_prepare_ability_response( 404, 'Route not found' );
					}

					foreach ( $routes[ $route ] as $endpoint ) {
						if ( isset( $endpoint['methods'][ $method ] ) ) {
							// Strip callable references — they JSON-encode as null (closures)
							// or as a bare class-name pair, neither of which is useful to the
							// caller and the latter leaks internal class names. The LLM only
							// needs the `args` schema and the `methods` map.
							unset( $endpoint['callback'], $endpoint['permission_callback'] );
							return blu_prepare_ability_response( 200, $endpoint );
						}
					}

					return blu_prepare_ability_response( 404, 'Method not found for this route' );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// Run API function
		blu_register_ability(
			'blu/run-api-function',
			array(
				'label'               => 'Run API Function',
				'description'         => 'Execute a WordPress REST API endpoint by route, method, and data. For routes with path parameters (e.g. "/wp/v2/posts/(?P<id>[\\d]+)"), substitute concrete values into the route (e.g. "/wp/v2/posts/42").',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'route'  => array(
							'type'        => 'string',
							'description' => 'REST API route, including the leading slash (e.g. "/wp/v2/posts").',
						),
						'method' => array(
							'type'        => 'string',
							'enum'        => array( 'GET', 'POST', 'PATCH', 'DELETE' ),
							'description' => 'HTTP method (uppercase).',
						),
						'data'   => array(
							'type'        => 'object',
							'description' => 'Request parameters matching the endpoint\'s args schema (see blu-get-function-details). Sent as query params for GET/DELETE, body for POST/PATCH.',
						),
					),
					'required'   => array( 'route', 'method' ),
				),
				'execute_callback'    => function ( $input ) {
					$route  = $input['route'];
					$method = $input['method'];
					$data   = $input['data'] ?? array();

					// Parse query parameters from route if present
					$query_params = array();
					if ( strpos( $route, '?' ) !== false ) {
						$parts      = explode( '?', $route, 2 );
						$route      = $parts[0];
						parse_str( $parts[1], $query_params );
					}

					// Refuse to re-enter the MCP transport from inside an ability.
					// Dispatching to the MCP route would let a crafted call invoke
					// this same ability with route=/blu/mcp again — a self-loop with
					// no legitimate use case.
					if ( '/blu/mcp' === $route || 0 === strpos( $route, '/blu/mcp/' ) ) {
						return blu_prepare_ability_response( 400, 'Refusing to dispatch to the MCP transport route from within an ability.' );
					}

					// Create REST request
					$request = new \WP_REST_Request( $method, $route );

					// Set parameters based on method
					if ( in_array( $method, array( 'GET', 'DELETE' ), true ) ) {
						if ( ! empty( $data ) ) {
							$query_params = array_merge( $query_params, $data );
						}
						if ( ! empty( $query_params ) ) {
							$request->set_query_params( $query_params );
						}
					} elseif ( ! empty( $data ) ) {
						$request->set_body_params( $data );
					}

					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);
	}
}
