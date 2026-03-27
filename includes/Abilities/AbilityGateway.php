<?php

declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Gateway abilities that reduce ~83 individual MCP tools to 3 generic gateway tools.
 *
 * Instead of sending every tool's full JSON Schema to the LLM on each request,
 * the gateway exposes only list/schema/call abilities. The LLM discovers tools
 * on demand, dramatically reducing token usage.
 */
class AbilityGateway {

	/**
	 * Constructor - registers the 3 gateway abilities.
	 */
	public function __construct() {
		$this->register_list_abilities();
		$this->register_get_ability_schema();
		$this->register_call_ability();
	}

	/**
	 * Returns the list of whitelisted abilities based on allowed namespaces and categories.
	 *
	 * @return \WP_Ability[] Filtered abilities.
	 */
	private function get_whitelisted_abilities(): array {
		$allowed_namespaces = apply_filters(
			'blu_mcp_allowed_namespaces',
			array(
				'blu/',
				'wc/',
			)
		);

		$allowed_categories = apply_filters(
			'blu_mcp_allowed_categories',
			array(
				'blu-mcp',
			)
		);

		$all_abilities = blu_get_abilities();

		return array_filter(
			$all_abilities,
			function ( $ability ) use ( $allowed_namespaces, $allowed_categories ) {
				$name     = $ability->get_name();
				$category = $ability->get_category();

				foreach ( $allowed_namespaces as $ns ) {
					if ( str_starts_with( $name, $ns ) ) {
						return true;
					}
				}

				if ( in_array( $category, $allowed_categories, true ) ) {
					return true;
				}

				return false;
			}
		);
	}

	/**
	 * Checks whether a given ability name is whitelisted.
	 *
	 * @param string $ability_name The ability name to check.
	 *
	 * @return \WP_Ability|null The ability if whitelisted, null otherwise.
	 */
	private function get_whitelisted_ability( string $ability_name ) {
		$canonical = $this->normalize_mcp_tool_name_to_ability_name( $ability_name );
		$whitelisted = $this->get_whitelisted_abilities();

		foreach ( $whitelisted as $ability ) {
			if ( $ability->get_name() === $canonical ) {
				return $ability;
			}
		}

		return null;
	}

	/**
	 * Convert an internal ability name to the MCP tool name.
	 *
	 * Mirrors {@see RegisterAbilityAsMcpTool::get_data()} line:
	 *   str_replace( '/', '-', trim( $ability->get_name() ) )
	 *
	 * Examples: blu/posts-search → blu-posts-search, wc/order-create → wc-order-create.
	 *
	 * @param string $name Ability name from WP_Ability::get_name().
	 *
	 * @return string MCP tool name (hyphen form).
	 */
	private function ability_name_to_mcp_tool_name( string $name ): string {
		return str_replace( '/', '-', trim( $name ) );
	}

	/**
	 * Normalize an ability reference from MCP hyphen form to WordPress slash form.
	 *
	 * If the name already contains `/`, it is returned unchanged. Otherwise split on the
	 * first hyphen only so names like blu-add-cpt map to blu/add-cpt (not blu-add/cpt).
	 *
	 * @param string $name Ability name as sent by the client (either format).
	 *
	 * @return string Canonical WordPress ability name for lookup.
	 */
	private function normalize_mcp_tool_name_to_ability_name( string $name ): string {
		$name = trim( $name );
		if ( str_contains( $name, '/' ) ) {
			return $name;
		}

		// Split only on the first hyphen. A regex like ^([a-z][a-z0-9_-]*)- is wrong here because
		// the character class includes '-', so blu-add-cpt is parsed as blu-add + / + cpt.
		$parts = explode( '-', $name, 2 );
		if ( count( $parts ) < 2 ) {
			return $name;
		}

		return $parts[0] . '/' . $parts[1];
	}

	/**
	 * Normalize delegated parameters for WP_Ability::execute().
	 *
	 * Abilities declare JSON Schema `type: object` for inputs. Passing null (e.g. when
	 * `parameters` is omitted when calling blu-call-ability) fails validation with "input is not of type object".
	 *
	 * @param mixed $parameters Raw parameters from the gateway call.
	 *
	 * @return array Associative array suitable as a JSON object payload.
	 */
	private function normalize_call_ability_parameters( $parameters ): array {
		if ( null === $parameters ) {
			return array();
		}
		if ( is_array( $parameters ) ) {
			return $parameters;
		}
		if ( is_string( $parameters ) ) {
			$decoded = json_decode( $parameters, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return array();
	}

	/**
	 * Register the blu/list-abilities gateway tool.
	 *
	 * Returns names, labels, descriptions, and annotations for all whitelisted abilities.
	 * Does NOT return input schemas to minimize token usage.
	 */
	private function register_list_abilities(): void {
		blu_register_ability(
			'blu/list-abilities',
			array(
				'label'               => 'List Abilities',
				'description'         => 'List all available abilities. Each entry includes name (hyphen form, same as WordPress MCP tool names, e.g. blu-posts-search), label, description, and annotations. Does not return input schemas — use blu-get-ability-schema with those name values.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'namespace' => array(
							'type'        => 'string',
							'description' => 'Optional namespace to filter abilities, e.g. "wc" for WooCommerce only. Matches the prefix of the hyphen-form names returned by this tool.',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$abilities = $this->get_whitelisted_abilities();

					// Apply optional namespace filter. Accept "wc", "wc/", or "wc-" — normalise
					// to the hyphen-form prefix and match against the MCP tool name.
					if ( ! empty( $input['namespace'] ) ) {
						$ns_prefix = rtrim( $input['namespace'], '/-' ) . '-';
						$abilities  = array_filter(
							$abilities,
							function ( $ability ) use ( $ns_prefix ) {
								return str_starts_with( $this->ability_name_to_mcp_tool_name( $ability->get_name() ), $ns_prefix );
							}
						);
					}

					$result = array();
					foreach ( $abilities as $ability ) {
						$meta        = $ability->get_meta();
						$annotations = isset( $meta['annotations'] ) ? $meta['annotations'] : array();

						$result[] = array(
							'name'        => $this->ability_name_to_mcp_tool_name( $ability->get_name() ),
							'label'       => $ability->get_label(),
							'description' => $ability->get_description(),
							'annotations' => $annotations,
						);
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
	}

	/**
	 * Register the blu/get-ability-schema gateway ability.
	 *
	 * The MCP adapter exposes WordPress ability names `blu/<segment>` as MCP tools `blu-<segment>`.
	 * Returns the full input schema for a specific whitelisted ability so the LLM
	 * knows what parameters to pass when calling it.
	 */
	private function register_get_ability_schema(): void {
		blu_register_ability(
			'blu/get-ability-schema',
			array(
				'label'               => 'Get Ability Schema',
				'description'         => 'Get the full input schema for a specific ability. Use this after blu-list-abilities to learn what parameters an ability accepts before calling it with blu-call-ability.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'ability_name' => array(
							'type'        => 'string',
							'description' => 'Exact ability name from list-abilities (hyphen form, same as tools/list), e.g. "blu-posts-search" or "blu-call-ability".',
						),
					),
					'required'   => array( 'ability_name' ),
				),
				'execute_callback'    => function ( $input ) {
					if ( empty( $input['ability_name'] ) ) {
						return blu_prepare_ability_response( 400, 'The ability_name parameter is required.' );
					}

					$ability = $this->get_whitelisted_ability( $input['ability_name'] );

					if ( ! $ability ) {
						return blu_prepare_ability_response( 404, 'Ability not found or not available.' );
					}

					$meta        = $ability->get_meta();
					$annotations = isset( $meta['annotations'] ) ? $meta['annotations'] : array();

					return blu_prepare_ability_response(
						200,
						array(
							'name'         => $this->ability_name_to_mcp_tool_name( $ability->get_name() ),
							'label'        => $ability->get_label(),
							'description'  => $ability->get_description(),
							'input_schema' => $ability->get_input_schema(),
							'annotations'  => $annotations,
						)
					);
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
	}

	/**
	 * Register the blu/call-ability gateway tool.
	 *
	 * Executes any whitelisted ability by name, delegating to the target ability's
	 * own permission_callback and execute_callback.
	 */
	private function register_call_ability(): void {
		blu_register_ability(
			'blu/call-ability',
			array(
				'label'               => 'Call Ability',
				'description'         => 'Execute any available ability by name. First use blu-list-abilities to discover abilities, then blu-get-ability-schema to learn the parameters, then use this tool to execute it.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'ability_name' => array(
							'type'        => 'string',
							'description' => 'Exact ability name from list-abilities (hyphen form, same as tools/list), e.g. "blu-posts-search" or "blu-call-ability".',
						),
						'parameters'   => array(
							'type'        => 'object',
							'description' => 'The parameters to pass to the ability (see blu-get-ability-schema for the expected format)',
						),
					),
					'required'   => array( 'ability_name' ),
				),
				'execute_callback'    => function ( $input ) {
					if ( empty( $input['ability_name'] ) ) {
						return blu_prepare_ability_response( 400, 'The ability_name parameter is required.' );
					}

					$ability = $this->get_whitelisted_ability( $input['ability_name'] );

					if ( ! $ability ) {
						return blu_prepare_ability_response( 404, 'Ability not found or not available.' );
					}

					$parameters = $this->normalize_call_ability_parameters(
						isset( $input['parameters'] ) ? $input['parameters'] : null
					);

					// Delegate to the ability's own execute method which handles
					// input validation, permission checking, and execution.
					$result = $ability->execute( $parameters );

					if ( is_wp_error( $result ) ) {
						$status_code = $result->get_error_code();
						if ( ! is_int( $status_code ) || $status_code < 400 || $status_code > 599 ) {
							$status_code = 500;
						}
						return blu_prepare_ability_response( $status_code, $result->get_error_message() );
					}

					return $result;
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
