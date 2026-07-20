<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * RestApiUtils - Static utility class for dynamic REST API discovery and schema extraction.
 *
 * This static utility class provides methods to dynamically discover REST API versions,
 * find routes, and extract schemas from WordPress REST API endpoints. It enables
 * creation of future-proof abilities that automatically adapt to API changes.
 *
 * Usage:
 * ```php
 * $wc_namespace = RestApiUtils::get_latest_namespace('wc');
 * $route = RestApiUtils::find_route_by_resource($wc_namespace, 'orders');
 * $schema = RestApiUtils::extract_input_schema($route, 'GET');
 * ```
 */
class RestApiUtils {

	/**
	 * Per-request cache of registered REST routes.
	 *
	 * @var array<string, mixed>|null
	 */
	private static $routes_cache = null;

	/**
	 * Per-request cache of registered REST namespaces.
	 *
	 * @var string[]|null
	 */
	private static $namespaces_cache = null;

	/**
	 * Whether eager_load_rest_routes() has already run this request.
	 *
	 * @var bool
	 */
	private static $eager_loaded = false;

	/**
	 * Per-request cache for get_latest_namespace() results.
	 *
	 * @var array<string, string|null>
	 */
	private static $latest_namespace_cache = array();

	/**
	 * Per-request cache for find_route_by_resource() results.
	 *
	 * @var array<string, string|null>
	 */
	private static $find_route_cache = array();

	/**
	 * Whether routes were cached before rest_api_init completed.
	 *
	 * @var bool
	 */
	private static $routes_cached_pre_init = false;

	/**
	 * Whether namespaces were cached before rest_api_init completed.
	 *
	 * @var bool
	 */
	private static $namespaces_cached_pre_init = false;

	/**
	 * Private constructor to prevent instantiation of utility class.
	 */
	private function __construct() {
		// Static utility class - prevent instantiation
	}

	/**
	 * Reset per-request caches (primarily for tests).
	 *
	 * @return void
	 */
	public static function reset_cache(): void {
		self::$routes_cache               = null;
		self::$namespaces_cache           = null;
		self::$eager_loaded               = false;
		self::$latest_namespace_cache     = array();
		self::$find_route_cache           = array();
		self::$routes_cached_pre_init     = false;
		self::$namespaces_cached_pre_init = false;
	}

	/**
	 * Clear route-discovery caches so the next lookup reads fresh server state.
	 *
	 * @return void
	 */
	private static function invalidate_route_discovery_cache(): void {
		self::$routes_cache               = null;
		self::$namespaces_cache           = null;
		self::$routes_cached_pre_init     = false;
		self::$namespaces_cached_pre_init = false;
		self::$latest_namespace_cache     = array();
		self::$find_route_cache           = array();
	}

	/**
	 * Ensure core REST routes are registered before discovery.
	 *
	 * @return void
	 */
	private static function ensure_rest_routes_registered(): void {
		if ( ! did_action( 'rest_api_init' ) ) {
			rest_api_init();
		}
	}

	/**
	 * Trigger lazy REST namespace registration (e.g. WooCommerce 10.3+).
	 *
	 * @return void
	 */
	private static function trigger_lazy_rest_registration(): void {
		if ( ! apply_filters( 'blu_mcp_list_api_eager_load', true ) ) {
			return;
		}

		$root_request = new \WP_REST_Request( 'GET', '/' );
		apply_filters( 'rest_pre_dispatch', null, rest_get_server(), $root_request );
	}

	/**
	 * Get registered REST routes, cached for the current request.
	 *
	 * @return array<string, mixed>
	 */
	private static function get_cached_routes(): array {
		if ( self::$routes_cached_pre_init && did_action( 'rest_api_init' ) ) {
			self::$routes_cache           = null;
			self::$routes_cached_pre_init = false;
		}

		if ( null === self::$routes_cache ) {
			self::$routes_cache           = rest_get_server()->get_routes();
			self::$routes_cached_pre_init = ! did_action( 'rest_api_init' );
		}

		return self::$routes_cache;
	}

	/**
	 * Get registered REST namespaces, cached for the current request.
	 *
	 * @return string[]
	 */
	private static function get_cached_namespaces(): array {
		if ( self::$namespaces_cached_pre_init && did_action( 'rest_api_init' ) ) {
			self::$namespaces_cache           = null;
			self::$namespaces_cached_pre_init = false;
		}

		if ( null === self::$namespaces_cache ) {
			self::$namespaces_cache           = rest_get_server()->get_namespaces();
			self::$namespaces_cached_pre_init = ! did_action( 'rest_api_init' );
		}

		return self::$namespaces_cache;
	}

	/**
	 * Normalize a route pattern for comparison by stripping named capture groups
	 * and collapsing repeated slashes.
	 *
	 * @param string $route REST route pattern.
	 *
	 * @return string Normalized route path.
	 */
	public static function normalize_route_for_match( string $route ): string {
		$normalized = preg_replace( '#/\(\?P<[^>]+>[^)]+\)#', '', $route );
		$normalized = preg_replace( '#/+#', '/', (string) $normalized );

		return $normalized;
	}

	/**
	 * Get the latest versioned namespace for a given base namespace.
	 *
	 * Discovers the highest available API version for a namespace by analyzing
	 * all registered namespaces. Supports both versioned (e.g., `wc/v1`, `wc/v2`, `wc/v3`)
	 * and unversioned (e.g., `wc-analytics`) namespaces.
	 *
	 * Examples:
	 *   Base: "wc"           → Returns: "wc/v3" (if v1, v2, v3 are registered)
	 *   Base: "wp"           → Returns: "wp/v2"
	 *   Base: "wc-analytics" → Returns: "wc-analytics" (unversioned)
	 *
	 * @param string $base_namespace Base namespace prefix (e.g., "wc", "wp", "wc-analytics").
	 *
	 * @return string|null The latest versioned namespace, or null if not found.
	 */
	public static function get_latest_namespace( string $base_namespace ): ?string {
		$base_namespace = trim( $base_namespace, '/' );

		if ( array_key_exists( $base_namespace, self::$latest_namespace_cache ) ) {
			return self::$latest_namespace_cache[ $base_namespace ];
		}

		$namespaces = self::get_cached_namespaces();
		$versions   = array();

		foreach ( $namespaces as $ns ) {
			// Exact match (unversioned namespace like "wc-analytics")
			if ( $ns === $base_namespace ) {
				self::$latest_namespace_cache[ $base_namespace ] = $ns;
				return $ns;
			}

			// Check for versioned namespace (e.g., "wc/v3")
			if ( preg_match( '#^' . preg_quote( $base_namespace, '#' ) . '/v(\d+)$#', $ns, $matches ) ) {
				$versions[ (int) $matches[1] ] = $ns;
			}
		}

		if ( empty( $versions ) ) {
			if ( did_action( 'rest_api_init' ) ) {
				self::$latest_namespace_cache[ $base_namespace ] = null;
			}
			return null;
		}

		// Return the highest version
		krsort( $versions, SORT_NUMERIC );
		$result = reset( $versions );

		self::$latest_namespace_cache[ $base_namespace ] = $result;

		return $result;
	}

	/**
	 * Find a REST route by resource path within a namespace.
	 *
	 * Searches registered routes for a matching pattern. Useful for constructing
	 * dynamic REST API calls without hardcoding version numbers.
	 *
	 * Examples:
	 *   Namespace: "wc/v3", Resource: "orders"     → Returns: "/wc/v3/orders"
	 *   Namespace: "wc/v3", Resource: "products"   → Returns: "/wc/v3/products"
	 *   Namespace: "wp/v2", Resource: "posts"      → Returns: "/wp/v2/posts"
	 *   Namespace: "wc/v3", Resource: "orders/123" → Returns: "/wc/v3/orders/(?P<id>[\d]+)"
	 *
	 * @param string $namespace     Full namespace (e.g., "wc/v3").
	 * @param string $resource_path Resource path without version (e.g., "orders", "products").
	 * @param bool   $exact_match   Whether to require exact match (default: true).
	 *
	 * @return string|null The matching route, or null if not found.
	 */
	public static function find_route_by_resource( string $namespace, string $resource_path, bool $exact_match = true ): ?string {
		$namespace     = trim( $namespace, '/' );
		$resource_path = trim( $resource_path, '/' );
		$cache_key     = $namespace . '|' . $resource_path . '|' . ( $exact_match ? '1' : '0' );

		if ( array_key_exists( $cache_key, self::$find_route_cache ) ) {
			return self::$find_route_cache[ $cache_key ];
		}

		$routes       = self::get_cached_routes();
		$search_route = '/' . $namespace . '/' . $resource_path;

		foreach ( array_keys( $routes ) as $route ) {
			if ( $exact_match ) {
				$normalized_route = self::normalize_route_for_match( $route );
				if ( $normalized_route === $search_route || $route === $search_route ) {
					self::$find_route_cache[ $cache_key ] = $route;
					return $route;
				}
			} elseif ( strpos( $route, $search_route ) === 0 ) {
				self::$find_route_cache[ $cache_key ] = $route;
				return $route;
			}
		}

		if ( did_action( 'rest_api_init' ) ) {
			self::$find_route_cache[ $cache_key ] = null;
		}

		return null;
	}

	/**
	 * Extract input schema from a REST route endpoint definition.
	 *
	 * Dynamically builds a JSON schema from the registered REST API endpoint's
	 * `args` definition. This allows abilities to stay in sync with the actual
	 * REST API without manual schema maintenance.
	 *
	 * @param string $route  REST route (e.g., "/wc/v3/orders").
	 * @param string $method HTTP method (e.g., "GET", "POST").
	 *
	 * @return array|null JSON schema object, or null if route/method not found.
	 */
	public static function extract_input_schema( string $route, string $method ): ?array {
		$routes = self::get_cached_routes();

		if ( ! isset( $routes[ $route ] ) ) {
			return null;
		}

		$method = strtoupper( $method );

		foreach ( $routes[ $route ] as $endpoint ) {
			if ( ! isset( $endpoint['methods'][ $method ] ) ) {
				continue;
			}

			return self::args_to_input_schema( $endpoint['args'] ?? array() );
		}

		return null;
	}

	/**
	 * Log when registration-time schema discovery falls back to a minimal schema.
	 *
	 * Runtime validation still applies via the native REST endpoint. Enable logging
	 * with the `blu_mcp_log_schema_fallback` filter (defaults to WP_DEBUG).
	 *
	 * @param string $ability_id Ability identifier (e.g. "blu/wc-reports-orders-totals").
	 * @param string $reason     Short reason discovery failed.
	 *
	 * @return void
	 */
	public static function log_registration_schema_fallback( string $ability_id, string $reason ): void {
		if ( ! apply_filters( 'blu_mcp_log_schema_fallback', defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log(
			sprintf(
				'[wp-module-mcp] Registration-time schema fallback for %s: %s. Runtime REST validation still applies.',
				$ability_id,
				$reason
			)
		);
	}

	/**
	 * Convert REST endpoint args to a JSON Schema object.
	 *
	 * Used by extract_input_schema() and by controller-based schema builders
	 * that do not depend on routes being registered yet.
	 *
	 * @param array<string, mixed> $args            REST endpoint argument definitions.
	 * @param bool                 $skip_context    Whether to omit the context parameter.
	 *
	 * @return array<string, mixed> JSON schema object.
	 */
	public static function args_to_input_schema( array $args, bool $skip_context = true ): array {
		if ( empty( $args ) ) {
			return array(
				'type'                 => 'object',
				'properties'           => array(),
				'additionalProperties' => true,
			);
		}

		$schema = array(
			'type'       => 'object',
			'properties' => array(),
		);

		$required = array();

		foreach ( $args as $arg_name => $arg_def ) {
			if ( $skip_context && 'context' === $arg_name ) {
				continue;
			}

			if ( ! is_array( $arg_def ) ) {
				continue;
			}

			$property = array();

			if ( isset( $arg_def['type'] ) ) {
				$property['type'] = self::map_rest_type_to_schema_type( $arg_def['type'] );
			}

			if ( isset( $arg_def['description'] ) ) {
				$property['description'] = $arg_def['description'];
			}

			if ( isset( $arg_def['enum'] ) && is_array( $arg_def['enum'] ) ) {
				$property['enum'] = $arg_def['enum'];
			}

			if ( isset( $arg_def['minimum'] ) ) {
				$property['minimum'] = $arg_def['minimum'];
			}

			if ( isset( $arg_def['maximum'] ) ) {
				$property['maximum'] = $arg_def['maximum'];
			}

			if ( isset( $arg_def['format'] ) ) {
				$property['format'] = $arg_def['format'];
			}

			if ( isset( $arg_def['items'] ) ) {
				$property['items'] = $arg_def['items'];
			}

			if ( isset( $arg_def['default'] ) ) {
				$property['default'] = $arg_def['default'];
			}

			$schema['properties'][ $arg_name ] = $property;

			if ( isset( $arg_def['required'] ) && true === $arg_def['required'] ) {
				$required[] = $arg_name;
			}
		}

		if ( ! empty( $required ) ) {
			$schema['required'] = $required;
		}

		$schema['additionalProperties'] = true;

		return $schema;
	}

	/**
	 * Build an input schema from REST controller endpoint args.
	 *
	 * Useful for core WP resources whose routes register late on rest_api_init
	 * but whose controllers expose args via public methods at any time.
	 *
	 * @param array<string, mixed> $args              REST endpoint argument definitions.
	 * @param array<string, mixed> $extra_properties  Additional JSON schema properties to merge.
	 * @param string[]             $extra_required    Additional required property names.
	 * @param bool                 $skip_context      Whether to omit the context parameter.
	 *
	 * @return array<string, mixed> JSON schema object.
	 */
	public static function schema_from_controller_args(
		array $args,
		array $extra_properties = array(),
		array $extra_required = array(),
		bool $skip_context = true
	): array {
		$schema = self::args_to_input_schema( $args, $skip_context );

		if ( ! empty( $extra_properties ) ) {
			foreach ( $extra_properties as $name => $property ) {
				$schema['properties'][ $name ] = $property;
			}
		}

		if ( ! empty( $extra_required ) ) {
			$required           = $schema['required'] ?? array();
			$schema['required'] = array_values( array_unique( array_merge( $required, $extra_required ) ) );
		}

		return $schema;
	}

	/**
	 * Force lazy-loaded REST namespaces to register before route discovery.
	 *
	 * WC 10.3+ attaches a rest_pre_dispatch filter that only registers its
	 * namespace when the incoming request route starts with that namespace or
	 * with `/` for discovery. Firing the filter with a synthetic root request
	 * triggers the same discovery path WC uses for /wp-json/ calls.
	 *
	 * @return void
	 */
	public static function eager_load_rest_routes(): void {
		if ( self::$eager_loaded ) {
			return;
		}

		self::ensure_rest_routes_registered();
		self::trigger_lazy_rest_registration();

		self::$eager_loaded = true;

		// Routes/namespaces may have changed after lazy registration.
		self::invalidate_route_discovery_cache();
	}

	/**
	 * Build a concrete item route from a collection or parameterized route.
	 *
	 * Strips a trailing (?P<name>...) capture group and appends the numeric ID.
	 * Returns null when the ID is missing or not a positive integer.
	 *
	 * @param string   $route REST route (collection or parameterized).
	 * @param int|null $id    Item ID.
	 *
	 * @return string|null Concrete REST route path, or null when ID is invalid.
	 */
	public static function build_item_route( string $route, $id ): ?string {
		if ( null === $id || '' === $id ) {
			return null;
		}

		$numeric_id = is_numeric( $id ) ? (int) $id : 0;
		if ( $numeric_id <= 0 ) {
			return null;
		}

		$base = preg_replace( '#/\(\?P<[^>]+>[^)]+\)$#', '', $route );

		return $base . '/' . $numeric_id;
	}

	/**
	 * Replace named capture groups in a REST route with concrete values.
	 *
	 * Numeric IDs are inserted as-is. Other values are rawurlencoded for safe
	 * use in path segments (e.g. slugs). Callers should pass integer IDs when
	 * the capture pattern expects digits.
	 *
	 * @param string               $route  REST route containing (?P<name>...) patterns.
	 * @param array<string, mixed> $params Map of capture name => replacement value.
	 *
	 * @return string Route with substitutions applied.
	 */
	public static function substitute_route_params( string $route, array $params ): string {
		foreach ( $params as $name => $value ) {
			$encoded = is_numeric( $value ) ? (string) (int) $value : rawurlencode( (string) $value );

			$route = preg_replace(
				'#\(\?P<' . preg_quote( (string) $name, '#' ) . '>[^)]+\)#',
				$encoded,
				$route,
				1
			);
		}

		return $route;
	}

	/**
	 * Resolve a concrete item route from a base namespace, resource path, and ID.
	 *
	 * Combines get_latest_available_rest_route() and build_item_route() so callers
	 * do not repeat collection discovery and ID substitution logic.
	 *
	 * @param string   $base_namespace Base namespace prefix (e.g. "wc", "wp").
	 * @param string   $resource_path  Resource path without version (e.g. "products").
	 * @param int|null $id             Item ID.
	 *
	 * @return string|null Concrete route path, or null when discovery fails.
	 */
	public static function resolve_item_route( string $base_namespace, string $resource_path, $id ): ?string {
		self::eager_load_rest_routes();
		$route = self::get_latest_available_rest_route( $base_namespace, $resource_path );

		if ( ! $route ) {
			return null;
		}

		return self::build_item_route( $route, $id );
	}

	/**
	 * Discover a parameterized route and substitute named capture groups.
	 *
	 * @param string               $base_namespace          Base namespace prefix (e.g. "wc").
	 * @param string               $resource_pattern_path   Path including (?P<name>...) segments.
	 * @param array<string, mixed> $params                  Capture name => value map.
	 *
	 * @return string|null Concrete route path, or null when discovery fails.
	 */
	public static function resolve_param_route( string $base_namespace, string $resource_pattern_path, array $params ): ?string {
		self::eager_load_rest_routes();
		$namespace = self::get_latest_namespace( $base_namespace );

		if ( ! $namespace ) {
			return null;
		}

		$route = self::find_route_by_resource( $namespace, $resource_pattern_path );

		if ( ! $route ) {
			return null;
		}

		return self::substitute_route_params( $route, $params );
	}

	/**
	 * JSON Schema for pass-through REST parameter objects.
	 *
	 * Use when an ability accepts arbitrary native endpoint args not fully enumerated
	 * in the schema builder. Known fields can be merged via schema_from_controller_args().
	 *
	 * @param string $description Human-readable schema description.
	 *
	 * @return array<string, mixed>
	 */
	public static function passthrough_object_schema( string $description ): array {
		return array(
			'type'                 => 'object',
			'description'          => $description,
			'additionalProperties' => true,
		);
	}

	/**
	 * Map WordPress REST API argument types to JSON Schema types.
	 *
	 * @param string|array $rest_type REST API type definition.
	 *
	 * @return string|array JSON Schema type.
	 */
	private static function map_rest_type_to_schema_type( $rest_type ) {
		if ( is_array( $rest_type ) ) {
			// Multiple types allowed - convert to JSON schema format
			return $rest_type;
		}

		// Map common REST types to JSON schema equivalents
		$type_map = array(
			'integer' => 'integer',
			'number'  => 'number',
			'string'  => 'string',
			'boolean' => 'boolean',
			'array'   => 'array',
			'object'  => 'object',
			'null'    => 'null',
		);

		return $type_map[ $rest_type ] ?? 'string';
	}

	/**
	 * Get all available namespaces registered with WordPress.
	 *
	 * Convenience method to retrieve all registered REST API namespaces.
	 *
	 * @return array Array of namespace strings.
	 */
	public static function get_all_namespaces(): array {
		return self::get_cached_namespaces();
	}

	/**
	 * Check if a namespace is registered.
	 *
	 * @param string $namespace Namespace to check (e.g., "wc/v3", "wp/v2").
	 *
	 * @return bool True if namespace is registered, false otherwise.
	 */
	public static function namespace_exists( string $namespace ): bool {
		$namespaces = self::get_cached_namespaces();
		$namespace  = trim( $namespace, '/' );
		return in_array( $namespace, $namespaces, true );
	}

	/**
	 * Get all routes for a specific namespace.
	 *
	 * @param string $namespace Namespace to get routes for (e.g., "wc/v3").
	 *
	 * @return array Array of route strings.
	 */
	public static function get_routes_for_namespace( string $namespace ): array {
		$all_routes = self::get_cached_routes();
		$namespace  = trim( $namespace, '/' );
		$routes     = array();

		foreach ( array_keys( $all_routes ) as $route ) {
			if ( strpos( ltrim( $route, '/' ), $namespace . '/' ) === 0 ) {
				$routes[] = $route;
			}
		}

		return $routes;
	}

	/**
	 * Get endpoint information for a specific route and method.
	 *
	 * Returns detailed information about an endpoint including methods,
	 * args, callback, etc.
	 *
	 * @param string $route  REST route (e.g., "/wc/v3/orders").
	 * @param string $method HTTP method (e.g., "GET", "POST").
	 *
	 * @return array|null Endpoint information, or null if not found.
	 */
	public static function get_endpoint_info( string $route, string $method ): ?array {
		$routes = self::get_cached_routes();

		if ( ! isset( $routes[ $route ] ) ) {
			return null;
		}

		$method = strtoupper( $method );

		foreach ( $routes[ $route ] as $endpoint ) {
			if ( isset( $endpoint['methods'][ $method ] ) ) {
				// Strip callable references to avoid serialization issues
				unset( $endpoint['callback'], $endpoint['permission_callback'] );
				return $endpoint;
			}
		}

		return null;
	}

	/**
	 * Resolve the latest REST route for a resource under a base namespace.
	 *
	 * Convenience method that discovers the highest available API version for
	 * the given base namespace, then finds the registered route for the resource.
	 * Equivalent to calling get_latest_namespace() followed by find_route_by_resource().
	 *
	 * Examples:
	 *   Base: "wp", Resource: "types"   → Returns: "/wp/v2/types"
	 *   Base: "wc", Resource: "orders"  → Returns: "/wc/v3/orders"
	 *   Base: "wp", Resource: "posts"   → Returns: "/wp/v2/posts"
	 *
	 * @param string $base_namespace Base namespace prefix (e.g., "wc", "wp", "wc-analytics").
	 * @param string $resource_path  Resource path without version (e.g., "types", "orders", "posts").
	 *
	 * @return string|null The matching REST route, or null if the namespace or resource is not found.
	 */
	public static function get_latest_available_rest_route( string $base_namespace, string $resource_path ): ?string {
		self::eager_load_rest_routes();

		$route = self::resolve_latest_available_rest_route( $base_namespace, $resource_path );
		if ( null !== $route ) {
			return $route;
		}

		// Retry with a fresh snapshot in case the first lookup used a stale per-request cache
		// (e.g. routes enumerated before rest_api_init or lazy namespaces registered later).
		self::ensure_rest_routes_registered();
		self::invalidate_route_discovery_cache();
		self::trigger_lazy_rest_registration();

		return self::resolve_latest_available_rest_route( $base_namespace, $resource_path );
	}

	/**
	 * Resolve the latest REST route without retry logic.
	 *
	 * @param string $base_namespace Base namespace prefix (e.g., "wc", "wp", "wc-analytics").
	 * @param string $resource_path  Resource path without version (e.g., "types", "orders", "posts").
	 *
	 * @return string|null The matching REST route, or null if the namespace or resource is not found.
	 */
	private static function resolve_latest_available_rest_route( string $base_namespace, string $resource_path ): ?string {
		$namespace = self::get_latest_namespace( $base_namespace );

		if ( ! $namespace ) {
			return null;
		}

		return self::find_route_by_resource( $namespace, $resource_path );
	}
}
