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
				'yoast-seo/',
			)
		);

		// Allow curated categories. WooCommerce abilities also require public
		// metadata below; integrators can broaden this list via the filter.
		$allowed_categories = apply_filters(
			'blu_mcp_allowed_categories',
			array(
				'blu-mcp',
				'woocommerce',
				'woocommerce-rest',
				'yoast-seo',
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
				if ( isset( $meta['mcp']['public'] ) && ! $meta['mcp']['public'] ) {
					return false;
				}
				if ( str_starts_with( $name, 'woocommerce/' ) && ( $meta['mcp']['public'] ?? false ) !== true ) {
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

	/**
	 * Return an adapter-compatible array whose JSON shape is an MCP object.
	 *
	 * @param mixed $result Result returned by a delegated ability.
	 * @return array Object-shaped result or a result envelope.
	 */
	private function normalize_result_for_mcp( $result ): array {
		if ( is_array( $result ) ) {
			// PHP serializes only consecutive zero-based keys as a JSON list.
			// Sparse numeric keys serialize as an object and must retain their keys.
			if ( ! empty( $result ) && array_keys( $result ) !== range( 0, count( $result ) - 1 ) ) {
				return $result;
			}
			return array( 'result' => $result );
		}

		if ( is_object( $result ) ) {
			// The adapter's structuredContent DTO accepts arrays, not PHP objects.
			// Inspect the serialized shape because JsonSerializable may emit any type.
			$json = wp_json_encode( $result );
			if ( false === $json ) {
				return blu_prepare_ability_response( 500, 'Ability execution failed.' );
			}
			$decoded = json_decode( $json, true );
			if ( '{' === $json[0] && is_array( $decoded ) && ! empty( $decoded ) && array_keys( $decoded ) !== range( 0, count( $decoded ) - 1 ) ) {
				return $decoded;
			}
			return array( 'result' => $decoded );
		}

		return array( 'result' => $result );
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
				'description'         => 'Find the ability for the user\'s task. Call blu-list-abilities once, narrowing with search or name_prefix if needed; do not repeat the same search. Choose the ability matching the object and action, then call blu-get-ability-schema and blu-call-ability with its required parameters. For classic navigation menus choose blu-add-page-to-menu or blu-remove-page-from-menu; /wp/v2/navigation is for block navigation. If no specific ability fits, discover registered WordPress REST API routes with blu-list-api-functions, inspect one with blu-get-function-details, and dispatch with blu-run-api-function.',
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
								// Match each whitespace-separated search term independently
								// against the combined name/label/description (AND, any
								// order) rather than requiring the whole phrase to appear
								// verbatim. Callers (LLMs) phrase queries loosely — e.g.
								// "image edit" — that won't literally appear in text
								// written as "Edit Image" / "edit an existing image".
								$haystack = $mcp_name . ' ' . $ability->get_label() . ' ' . $ability->get_description();
								$terms    = preg_split( '/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY );
								$matched  = true;
								foreach ( $terms as $term ) {
									if ( false === mb_stripos( $haystack, $term ) ) {
										$matched = false;
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
							'description' => isset( $meta['mcp']['summary'] ) && is_string( $meta['mcp']['summary'] ) && '' !== trim( $meta['mcp']['summary'] ) ? $meta['mcp']['summary'] : $ability->get_description(),
							'annotations' => $annotations,
						);
					}

					return blu_prepare_ability_response( 200, $result );
				},
				'permission_callback' => fn() => is_user_logged_in(),
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
				'description'         => 'Get the full description and input schema for an ability returned by blu-list-abilities. Read the required parameters and detailed instructions before calling blu-call-ability.',
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
				'permission_callback' => fn() => is_user_logged_in(),
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
				'description'         => 'Execute an ability found with blu-list-abilities, using the name and parameters from blu-get-ability-schema. Prefer a specific ability for the requested object and action. If none fits, use blu-list-api-functions, blu-get-function-details, then blu-run-api-function for a registered REST API route. Gateway tools cannot be invoked through blu-call-ability.',
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

						// The Abilities API rejects a bad call with a slug and no status:
						// `ability_invalid_input` for a schema violation,
						// `ability_invalid_permissions` for a denial. Both are the caller's
						// fault and both describe the fix, but a slug is not an int, so
						// without this map they fall to 500 and the redaction below throws
						// the reason away. The caller is then told only that something
						// failed, which is the one thing it cannot act on: it retries the
						// same malformed call, or abandons that part of the request.
						if ( ! is_int( $status_code ) ) {
							$slug_statuses = array(
								'ability_invalid_input' => 400,
								'ability_invalid_permissions' => 403,
							);
							if ( isset( $slug_statuses[ $status_code ] ) ) {
								$status_code = $slug_statuses[ $status_code ];
							}
						}

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

					return $this->normalize_result_for_mcp( $result );
				},
				'permission_callback' => fn() => is_user_logged_in(),
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
