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
	 * Canonical names of the gateway abilities (slash form).
	 *
	 * Used for registration, recursion guard, and list-abilities exclusion.
	 * Add new gateway abilities here — everything else derives from this list.
	 */
	private const GATEWAY_ABILITIES = array(
		'blu/list-abilities',
		'blu/get-ability-schema',
		'blu/call-ability',
	);

	/**
	 * Constructor — registers all gateway abilities.
	 */
	public function __construct() {
		$this->register_list_abilities();
		$this->register_get_ability_schema();
		$this->register_call_ability();
	}

	/* Whitelist helpers */

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
	 * Accepts both slash form (blu/posts-search) and hyphen form (blu-posts-search).
	 * Uses forward conversion (slash→hyphen) for matching, which is unambiguous
	 * unlike the reverse (hyphen→slash) which breaks for hyphenated namespaces.
	 *
	 * @param string $ability_name The ability name to check (either format).
	 *
	 * @return \WP_Ability|null The ability if whitelisted, null otherwise.
	 */
	private function get_whitelisted_ability( string $ability_name ) {
		$ability_name = trim( $ability_name );
		$whitelisted  = $this->get_whitelisted_abilities();

		foreach ( $whitelisted as $ability ) {
			$name = $ability->get_name();
			if ( $name === $ability_name || $this->to_mcp_name( $name ) === $ability_name ) {
				return $ability;
			}
		}

		return null;
	}

	/* Name conversion helpers */

	/**
	 * Convert an internal ability name to the MCP tool name.
	 *
	 * Mirrors {@see RegisterAbilityAsMcpTool::get_data()}:
	 *   str_replace( '/', '-', trim( $ability->get_name() ) )
	 *
	 * @param string $name Ability name (slash form).
	 *
	 * @return string MCP tool name (hyphen form).
	 */
	private function to_mcp_name( string $name ): string {
		return str_replace( '/', '-', trim( $name ) );
	}

	/**
	 * Extract the namespace from an ability name (part before the first slash).
	 *
	 * @param string $name Ability name (slash form).
	 *
	 * @return string Namespace, or empty string if no slash.
	 */
	private function get_namespace( string $name ): string {
		$slash_pos = strpos( $name, '/' );
		return false !== $slash_pos ? substr( $name, 0, $slash_pos ) : '';
	}

	/**
	 * Check whether an ability name (slash or hyphen form) is a gateway ability.
	 *
	 * @param string $name Ability name to check.
	 *
	 * @return bool True if it's a gateway ability.
	 */
	private function is_gateway_ability( string $name ): bool {
		$name = trim( $name );
		foreach ( self::GATEWAY_ABILITIES as $gw ) {
			if ( $gw === $name || $this->to_mcp_name( $gw ) === $name ) {
				return true;
			}
		}
		return false;
	}

	/* Parameter helpers */

	/**
	 * Normalize delegated parameters for WP_Ability::execute().
	 *
	 * Abilities declare JSON Schema `type: object` for inputs. Passing null
	 * fails validation with "input is not of type object".
	 *
	 * @param mixed $parameters Raw parameters from the gateway call.
	 *
	 * @return array Associative array suitable as a JSON object payload.
	 */
	private function normalize_parameters( $parameters ): array {
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

	/* Gateway ability registration */

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
							'description' => 'Optional namespace to filter abilities by their provider, e.g. "blu" for Bluehost abilities. Matches the namespace prefix before the slash in ability names.',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$abilities = $this->get_whitelisted_abilities();

					// Exclude gateway abilities — they are already exposed as
					// direct MCP tools via tools/list and cannot be called through
					// blu-call-ability, so listing them is redundant and confusing.
					$abilities = array_filter(
						$abilities,
						function ( $ability ) {
							return ! $this->is_gateway_ability( $ability->get_name() );
						}
					);

					// Apply optional namespace filter. Accept "blu", "blu/", or "blu-".
					if ( ! empty( $input['namespace'] ) ) {
						$ns = rtrim( $input['namespace'], '/-' );
						$abilities = array_filter(
							$abilities,
							function ( $ability ) use ( $ns ) {
								return $this->get_namespace( $ability->get_name() ) === $ns;
							}
						);
					}

					$result = array();
					foreach ( $abilities as $ability ) {
						$meta        = $ability->get_meta();
						$annotations = isset( $meta['annotations'] ) ? $meta['annotations'] : array();
						$name        = $ability->get_name();

						$result[] = array(
							'name'        => $this->to_mcp_name( $name ),
							'namespace'   => $this->get_namespace( $name ),
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
							'description' => 'Exact ability name from list-abilities (hyphen form, same as tools/list), e.g. "blu-posts-search".',
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
							'name'         => $this->to_mcp_name( $ability->get_name() ),
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
							'description' => 'Exact ability name from list-abilities (hyphen form), e.g. "blu-posts-search". Gateway tools (list-abilities, get-ability-schema, call-ability) cannot be called here.',
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

					if ( $this->is_gateway_ability( $input['ability_name'] ) ) {
						return blu_prepare_ability_response(
							400,
							'Gateway tools cannot be called through blu-call-ability. Call them directly as MCP tools.'
						);
					}

					$ability = $this->get_whitelisted_ability( $input['ability_name'] );

					if ( ! $ability ) {
						return blu_prepare_ability_response( 404, 'Ability not found or not available.' );
					}

					$parameters = $this->normalize_parameters(
						isset( $input['parameters'] ) ? $input['parameters'] : null
					);

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
