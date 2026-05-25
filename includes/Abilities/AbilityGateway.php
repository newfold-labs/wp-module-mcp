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
	public const GATEWAY_ABILITIES = array(
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
				'woocommerce/',
			)
		);

		// Category gate — restricts the whitelist to abilities registered under
		// these categories. Defaults to the BLU MCP category so the gateway
		// only exposes the curated set; integrators can broaden via the filter.
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
				$name         = $ability->get_name();
				$meta         = $ability->get_meta();
				$ability_type = 'tool';
				if ( isset( $meta['mcp']['type'] ) ) {
					$ability_type = $meta['mcp']['type'];
				}
				if ( 'tool' !== $ability_type ) {
					return false;
				}

				// Category gate: when the filter returns a non-empty list of
				// strings, the ability's category must match one of them.
				// Empty/non-array filter result disables the gate so callers
				// can opt out via `return array();`.
				if ( is_array( $allowed_categories ) && ! empty( $allowed_categories ) ) {
					$category    = $ability->get_category();
					$category_ok = false;
					foreach ( $allowed_categories as $cat ) {
						if ( is_string( $cat ) && '' !== $cat && $cat === $category ) {
							$category_ok = true;
							break;
						}
					}
					if ( ! $category_ok ) {
						return false;
					}
				}

				foreach ( $allowed_namespaces as $ns ) {
					// Skip empty/non-string entries — an empty string would match every
					// ability via str_starts_with() and silently bypass the whitelist.
					if ( ! is_string( $ns ) || '' === $ns ) {
						continue;
					}
					if ( str_starts_with( $name, $ns ) ) {
						return true;
					}
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
				'description'         => 'List the abilities available on this site. Each entry includes `name` (hyphen form), `label`, `description`, and `annotations`. Use the optional `search` and `name_prefix` filters to narrow the catalog.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search'      => array(
							'type'        => 'string',
							'description' => 'Case-insensitive substring filter across each ability\'s name, label, and description.',
							'minLength'   => 1,
							'maxLength'   => 100,
						),
						'name_prefix' => array(
							'type'        => 'string',
							'description' => 'Prefix match on the MCP tool name (hyphen form). Examples: `blu-wc-` for Bluehost WooCommerce wrappers, `woocommerce-` for WooCommerce-native abilities, `blu-posts` for post abilities. Slash form (e.g. `blu/wc`) is normalized to hyphen form.',
							'minLength'   => 1,
							'maxLength'   => 100,
							'pattern'     => '^[A-Za-z0-9/_-]+$',
						),
					),
					'additionalProperties' => false,
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

					$search      = isset( $input['search'] ) && is_string( $input['search'] ) ? trim( $input['search'] ) : '';
					$name_prefix = isset( $input['name_prefix'] ) && is_string( $input['name_prefix'] ) ? trim( $input['name_prefix'] ) : '';

					if ( '' !== $name_prefix ) {
						$name_prefix = rtrim( str_replace( '/', '-', $name_prefix ), '-' );
					}

					$abilities = array_filter(
						$abilities,
						function ( $ability ) use ( $search, $name_prefix ) {
							$mcp_name = $this->to_mcp_name( $ability->get_name() );

							if ( '' !== $name_prefix && 0 !== strpos( $mcp_name, $name_prefix ) ) {
								return false;
							}

							if ( '' !== $search ) {
								$haystacks = array(
									$mcp_name,
									(string) $ability->get_label(),
									(string) $ability->get_description(),
								);
								$matched   = false;
								foreach ( $haystacks as $haystack ) {
									if ( false !== mb_stripos( $haystack, $search ) ) {
										$matched = true;
										break;
									}
								}
								if ( ! $matched ) {
									return false;
								}
							}

							return true;
						}
					);

					$result = array();
					foreach ( $abilities as $ability ) {
						$meta        = $ability->get_meta();
						$annotations = isset( $meta['annotations'] ) ? $meta['annotations'] : array();

						$result[] = array(
							'name'        => $this->to_mcp_name( $ability->get_name() ),
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
				'description'         => 'Get the full input schema for a specific ability, so the caller knows what parameters to pass when invoking it via blu-call-ability.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'ability_name' => array(
							'type'        => 'string',
							'description' => 'Ability name in hyphen form (e.g. "blu-posts-search", "woocommerce-products-list"), matching the `name` field from blu-list-abilities.',
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
				'description'         => 'Execute an ability by name with its parameters. The gateway tools (blu-list-abilities, blu-get-ability-schema, blu-call-ability) cannot be invoked through this tool.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'ability_name' => array(
							'type'        => 'string',
							'description' => 'Ability name in hyphen form (e.g. "blu-posts-search"), matching the `name` field from blu-list-abilities.',
						),
						'parameters'   => array(
							'type'        => 'object',
							'description' => 'Parameters object matching the ability\'s input_schema (see blu-get-ability-schema). Pass `{}` if the ability takes none.',
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
						// WordPress convention: WP_Error::get_error_code() is a string slug
						// (e.g. "rest_invalid_param") and the HTTP status lives in error_data.
						// Falling back to get_error_code() handles the rare case where an
						// ability returns WP_Error( 400, ... ) with an integer code.
						$error_data  = $result->get_error_data();
						$status_code = is_array( $error_data ) && isset( $error_data['status'] ) && is_int( $error_data['status'] )
							? $error_data['status']
							: $result->get_error_code();

						if ( ! is_int( $status_code ) || $status_code < 400 || $status_code > 599 ) {
							$status_code = 500;
						}

						// Redact 5xx messages — abilities may surface stack frames,
						// SQL fragments, file paths, or upstream credentials in their
						// WP_Error message. 4xx messages are kept verbatim because
						// they carry validation/permission feedback the LLM needs to
						// self-correct.
						$message = $status_code >= 500
							? 'Ability execution failed.'
							: $result->get_error_message();

						return blu_prepare_ability_response( $status_code, $message );
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
