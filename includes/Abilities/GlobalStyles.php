<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Global Styles class
 *
 * Registers abilities for getting and updating WordPress global styles.
 * Global styles are part of the Full Site Editing (FSE) system and contain
 * theme.json configuration and user customizations.
 */
class GlobalStyles {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register all global styles abilities
	 *
	 * @return void
	 */
	private function register_abilities(): void {
		$this->register_get_global_styles();
		$this->register_update_global_styles();
		$this->register_get_active_global_styles();
		$this->register_get_active_global_styles_id();
	}

	/**
	 * Register ability to get a specific global styles configuration
	 *
	 * @return void
	 */
	private function register_get_global_styles(): void {
		blu_register_ability(
			'blu/get-global-styles',
			array(
				'label'               => 'Get Global Styles',
				'description'         => 'Get a specific global styles configuration by ID. Returns theme.json settings and user customizations including colors, typography, and spacing. Only use this when you need to inspect the current styles — do NOT call this before blu/update-global-styles, which resolves the ID automatically.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Global styles ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id       = intval( $input['id'] );
					$request  = new \WP_REST_Request( 'GET', '/wp/v2/global-styles/' . $id );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'edit_theme_options' ),
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
	 * Register ability to update global styles
	 *
	 * @return void
	 */
	private function register_update_global_styles(): void {
		blu_register_ability(
			'blu/update-global-styles',
			array(
				'label'               => 'Update Global Styles',
				'description'         => 'Update WordPress global styles (colors, typography, spacing) using theme.json format. Resolves the global styles ID automatically — do NOT call get-global-styles first. Use for site-wide palette, font, or spacing changes — NOT for individual block colors. SETTINGS vs STYLES — these are NOT interchangeable: "settings" REGISTERS what is available (adds a font to the picker, declares a palette slot); writing only to "settings" does NOT change what visitors see. "styles" APPLIES values to the rendered page (sets the active font, sets the active background). To change the font users actually see you almost always need BOTH: register the family under settings.typography.fontFamilies AND apply it under styles.typography.fontFamily (site-wide) and/or styles.blocks.<block>.typography.fontFamily (per block). Per-block APPLICATION (the active font/color for a specific block) lives under styles.blocks.*; per-block REGISTRATION (settings.blocks.<block>.typography.fontFamilies, settings.blocks.<block>.appearanceTools, etc.) is a real WP feature and stays under settings.blocks.*. The common mistake is putting an application value (typography.fontFamily singular, color.background, color.text, etc.) under settings.blocks.<block> — that has no visual effect; move it to styles.blocks.<block>.<same-sub-path>. PRESET REFERENCES use the literal token "var:preset|font-family|<slug>" (note "font-family", not "font"); for colors use "var:preset|color|<slug>". The CSS-variable form var(--wp--preset--font-family--<slug>) also works and is equivalent. FONT CHANGE EXAMPLE — apply Fira Code site-wide and to common blocks: {"settings":{"typography":{"fontFamilies":[{"slug":"fira-code","name":"Fira Code","fontFamily":"\"Fira Code\", monospace"}]}},"styles":{"typography":{"fontFamily":"var:preset|font-family|fira-code"},"blocks":{"core/heading":{"typography":{"fontFamily":"var:preset|font-family|fira-code"}},"core/paragraph":{"typography":{"fontFamily":"var:preset|font-family|fira-code"}},"core/button":{"typography":{"fontFamily":"var:preset|font-family|fira-code"}}}}}. The response includes an "applied" list (paths that landed) and a "not_applied" list (paths sent but not in effect, each with a reason). If "not_applied" is non-empty the change did NOT fully succeed — fix the payload and retry; do NOT report success to the user. COLOR SLUGS: base=Background, base-midtone=Background midtone, contrast=Text, contrast-midtone=Text midtone, accent-2=Primary, accent-5=Secondary. Only include slugs you are changing — others are preserved. MIDTONE COLORS: When changing base, also update base-midtone (a subtle step toward contrast). When changing contrast, also update contrast-midtone (a subtle step toward base). Light theme example: base=#ffffff, base-midtone=#f4f4f4, contrast=#000000, contrast-midtone=#323232. Dark theme example: base=#181818, base-midtone=#1C1C1C, contrast=#FFFFFF, contrast-midtone=#DADADA. ACCENT COLORS: Generate ALL 6 shades via HSL lightness from the base color: accent-1(-24%), accent-2(base), accent-3(+18%), accent-4(+28%), accent-5(+56%), accent-6(+63%). Example for deep blue #0B3D5B: accent-1=#062533, accent-2=#0B3D5B, accent-3=#1A5A7A, accent-4=#2A7399, accent-5=#6BAAC9, accent-6=#8DC1D9. DARK/LIGHT MODE: Only change base + base-midtone + contrast + contrast-midtone, NEVER modify accents. "base" must be white/near-white for light or dark grey for dark themes. "contrast" must be the opposite. VAGUE PALETTE REQUESTS: When user says "change colors" without specifying which, ask what colors or mood they want first — do not apply immediately. PALETTE FORMAT: {"settings":{"color":{"palette":{"theme":[{"slug":"...","color":"#hex","name":"..."}]}}}}',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'settings' => array(
							'type'        => 'object',
							'description' => 'Settings object in theme.json format.',
							'properties'  => array(
								'color'      => array(
									'type'        => 'object',
									'description' => 'Color settings.',
									'properties'  => array(
										'palette' => array(
											'type'        => 'object',
											'description' => 'Palette settings.',
											'properties'  => array(
												'theme' => array(
													'type' => 'array',
													'description' => 'Array of theme palette color entries. Only include slugs you are changing.',
													'items' => array(
														'type'       => 'object',
														'properties' => array(
															'slug'  => array(
																'type'        => 'string',
																'description' => 'Color slug: base, base-midtone, contrast, contrast-midtone, accent-1 through accent-6.',
															),
															'color' => array(
																'type'        => 'string',
																'description' => 'Hex color value (e.g. #0B3D5B).',
															),
															'name'  => array(
																'type'        => 'string',
																'description' => 'Display name for the color.',
															),
														),
														'required' => array( 'slug', 'color', 'name' ),
													),
												),
											),
										),
									),
								),
								'typography' => array(
									'type'        => 'object',
									'description' => 'Typography settings (fontFamilies, fontSizes).',
								),
								'spacing'    => array(
									'type'        => 'object',
									'description' => 'Spacing settings.',
								),
							),
						),
						'styles'   => array(
							'type'        => 'object',
							'description' => 'Styles object containing CSS-like declarations for root, elements, and blocks.',
						),
					),
					'anyOf'      => array(
						array( 'required' => array( 'settings' ) ),
						array( 'required' => array( 'styles' ) ),
					),
				),
				'execute_callback'    => array( $this, 'execute_update_global_styles' ),
				'permission_callback' => fn() => current_user_can( 'edit_theme_options' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
					'mcp'         => array(
						'public' => true,
						'type'   => 'tool',
					),
				),
			)
		);
	}

	/**
	 * Register ability to get active global styles for the current theme
	 *
	 * @return void
	 */
	private function register_get_active_global_styles(): void {
		blu_register_ability(
			'blu/get-active-global-styles',
			array(
				'label'               => 'Get Active Global Styles',
				'description'         => 'Get the currently active global styles for the current theme, including colors, typography, and spacing. Use for: "show colors", "what fonts are available", "current palette", "list styles". Returns the full styles object with all active customizations.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$global_styles = wp_get_global_styles();

					return is_array( $global_styles ) && ! empty( $global_styles )
						? blu_prepare_ability_response( 200, $global_styles )
						: blu_prepare_ability_response( 404, 'No active global styles found.' );
				},
				'permission_callback' => fn() => current_user_can( 'edit_theme_options' ),
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
	 * Register ability to get active global styles ID for the current theme
	 *
	 * @return void
	 */
	private function register_get_active_global_styles_id(): void {
		blu_register_ability(
			'blu/get-active-global-styles-id',
			array(
				'label'               => 'Get Active Global Styles ID',
				'description'         => 'Get the active global styles post ID for the current theme. Only use this when you need the ID for reference — do NOT call this before blu/update-global-styles, which resolves the ID automatically. Returns an object with the numeric ID.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type' => 'object',
				),
				'execute_callback'    => function () {
					$id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();

					return is_int( $id ) && $id > 0
						? blu_prepare_ability_response( 200, array( 'id' => $id ) )
						: blu_prepare_ability_response( 404, 'No active global styles ID found.' );
				},
				'permission_callback' => fn() => current_user_can( 'edit_theme_options' ),
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
	 * Execute update global styles ability.
	 *
	 * Sends the requested settings/styles to the WP REST endpoint, then diffs the
	 * stored result against the request so callers can see what actually landed.
	 *
	 * The standardized response always includes (alongside the usual statusCode /
	 * status / message fields) an `applied` array of dot-paths that were written
	 * and a `not_applied` array of dot-paths that were sent but did not take
	 * effect. Each not_applied entry carries a `path` and a `reason` so the
	 * caller can self-correct without round-tripping through a follow-up read.
	 *
	 * @param array $input The input parameters.
	 * @return array The result.
	 */
	public function execute_update_global_styles( array $input = array() ): array {
		if ( ! isset( $input['settings'] ) && ! isset( $input['styles'] ) ) {
			return blu_prepare_ability_response( 400, 'Settings or styles object is required' );
		}

		$global_styles_id = \WP_Theme_JSON_Resolver::get_user_global_styles_post_id();

		if ( ! $global_styles_id ) {
			return blu_prepare_ability_response( 500, 'Could not find global styles post' );
		}

		$request = new \WP_REST_Request( 'POST', '/wp/v2/global-styles/' . $global_styles_id );

		// Prepare the update data.
		$data = array();
		if ( isset( $input['settings'] ) ) {
			$data['settings'] = $input['settings'];
		}
		if ( isset( $input['styles'] ) ) {
			$data['styles'] = $input['styles'];
		}

		$request->set_body_params( $data );
		$response = rest_do_request( $request );

		$result = blu_standardize_rest_response( $response );

		// Only attach the applied/not_applied diff on a successful 2xx response.
		// On error, the message already carries the REST error string; surfacing
		// a diff against an unwritten post would be misleading.
		if ( isset( $result['statusCode'] ) && $result['statusCode'] >= 200 && $result['statusCode'] < 300 ) {
			$stored = is_array( $result['message'] ?? null ) ? $result['message'] : array();
			$diff   = $this->diff_requested_vs_stored( $data, $stored );

			$result['applied']     = $diff['applied'];
			$result['not_applied'] = $diff['not_applied'];
		}

		return $result;
	}

	/**
	 * Compare what was requested against what the stored post actually contains.
	 *
	 * Walks every leaf in `$requested` and looks up the same path in the stored
	 * settings/styles payload returned by the REST endpoint. Leaves whose stored
	 * value matches the requested value land in `applied`; everything else lands
	 * in `not_applied` with a human-readable reason — wrong key path, dropped
	 * unknown key, or preset slug that was never registered.
	 *
	 * @param array $requested Top-level payload submitted to the REST endpoint (keys: settings, styles).
	 * @param array $stored    Stored post payload returned by the REST endpoint.
	 * @return array{applied: string[], not_applied: array<int, array{path: string, reason: string}>}
	 */
	private function diff_requested_vs_stored( array $requested, array $stored ): array {
		$applied     = array();
		$not_applied = array();

		// Collect preset slugs FIRST, across the full request (top-level AND
		// per-block settings). A reference to a slug registered under
		// settings.blocks.<block>.typography.fontFamilies must not be flagged
		// as unknown.
		$preset_slugs = $this->collect_known_preset_slugs( $requested, $stored );

		$this->walk_leaves(
			$requested,
			'',
			function ( string $path, $requested_value ) use ( $stored, $preset_slugs, &$applied, &$not_applied ) {
				// Application-shaped leaves placed anywhere under settings.*
				// (e.g. settings.typography.fontFamily, or
				// settings.blocks.core/paragraph.typography.fontFamily) have
				// no effect — they belong under the parallel styles.* path.
				// Flag this before consulting storage: even if WP kept the
				// value byte-for-byte, it is rendered as inert metadata.
				$misplaced = $this->detect_misplaced_application( $path, $requested_value );
				if ( null !== $misplaced ) {
					$not_applied[] = array(
						'path'   => $path,
						'reason' => $misplaced,
					);
					return;
				}

				$stored_value = $this->lookup_path( $stored, $path );

				// Even when the value lands in storage byte-for-byte, a preset
				// reference that points at nothing (wrong token, or unknown
				// slug) will not resolve at render time. Surface it as
				// not_applied so the caller does not claim success on a no-op.
				$preset_problem = $this->detect_unresolved_preset_reference( $path, $requested_value, $preset_slugs );
				if ( null !== $preset_problem ) {
					$not_applied[] = array(
						'path'   => $path,
						'reason' => $preset_problem,
					);
					return;
				}

				if ( $this->values_match( $requested_value, $stored_value ) ) {
					$applied[] = $path;
					return;
				}

				$not_applied[] = array(
					'path'   => $path,
					'reason' => $this->explain_missing_leaf( $stored_value ),
				);
			}
		);

		return array(
			'applied'     => $applied,
			'not_applied' => $not_applied,
		);
	}

	/**
	 * Detect a leaf placed under `settings.*` whose terminal sub-path is a
	 * scalar application key (the kind that belongs under `styles.*`). Covers
	 * both the top-level case (`settings.typography.fontFamily`) and the
	 * per-block case (`settings.blocks.<block>.typography.fontFamily`) —
	 * both produce inert metadata that has no visual effect.
	 *
	 * Deliberately narrow: only catches high-confidence "I tried to set the
	 * active font/color by writing to settings" mistakes. Real registrations
	 * (`settings.typography.fontFamilies` plural, `settings.color.palette`,
	 * `settings.appearanceTools`, per-block analogues) are NOT flagged.
	 *
	 * When the value itself uses the wrong preset token (`var:preset|font|...`
	 * instead of `var:preset|font-family|...`), the reason includes that
	 * correction too — the agent should not need a second round-trip to fix
	 * two adjacent mistakes in the same leaf.
	 *
	 * @param string $path  Dot-path of the leaf being visited.
	 * @param mixed  $value Requested leaf value.
	 * @return string|null Reason text, or null when the leaf is not a misplaced application.
	 */
	private function detect_misplaced_application( string $path, $value ): ?string {
		$app_keys = 'typography\.font(?:Family|Size|Style|Weight)'
			. '|typography\.(?:lineHeight|letterSpacing|textDecoration|textTransform)'
			. '|color\.(?:background|text|gradient)';

		// Two shapes, captured into the same `sub` group:
		// settings.<sub>
		// settings.blocks.<block>.<sub>
		$pattern = "#^settings\\.(?:blocks\\.(?P<block>[^.]+)\\.)?(?P<sub>{$app_keys})$#";

		if ( ! preg_match( $pattern, $path, $m ) ) {
			return null;
		}

		$block        = $m['block'] ?? '';
		$sub          = $m['sub'];
		$target_path  = '' === $block
			? "styles.{$sub}"
			: "styles.blocks.{$block}.{$sub}";
		$source_scope = '' === $block ? 'settings.*' : 'settings.blocks.*';

		$reason = "'{$path}' applies a value under {$source_scope} — that subtree is treated as registration metadata, so the value will not render. Move it to '{$target_path}' and resend.";

		// Adjacent mistake: wrong preset token. Mention the fix in the same
		// reason so the agent does not have to round-trip twice.
		if ( is_string( $value ) && preg_match( '/^var:preset\|font\|(.+)$/', $value, $vm ) ) {
			$reason .= " The value also uses the wrong preset token — use 'var:preset|font-family|{$vm[1]}' (not 'font') when you resend.";
		}

		return $reason;
	}

	/**
	 * Recursively walk every scalar/leaf inside `$value`, invoking `$visit` with
	 * a dot-delimited path. Numerically-indexed lists (e.g. fontFamilies) are
	 * keyed by `slug` when each item has one so paths stay stable across
	 * reorderings — otherwise we fall back to the numeric index.
	 *
	 * @param mixed    $value Node to walk.
	 * @param string   $path  Path accumulated so far.
	 * @param callable $visit Receives (path, leaf_value).
	 * @return void
	 */
	private function walk_leaves( $value, string $path, callable $visit ): void {
		if ( is_array( $value ) ) {
			// An empty array carries no leaves to compare; do not visit, do not
			// recurse — it would falsely register as a missing scalar.
			if ( empty( $value ) ) {
				return;
			}

			$is_list = array_keys( $value ) === range( 0, count( $value ) - 1 );

			foreach ( $value as $key => $child ) {
				if ( $is_list ) {
					// Slugs are used as a stable identifier in the path, but
					// only when they look like a slug. Anything containing
					// path-significant characters (`.`, `[`, `]`, `=`) falls
					// back to the numeric index so lookup_path does not
					// mis-parse.
					$slug      = is_array( $child ) && isset( $child['slug'] ) && is_string( $child['slug'] ) ? $child['slug'] : null;
					$child_key = ( null !== $slug && preg_match( '/^[A-Za-z0-9_-]+$/', $slug ) )
						? '[slug=' . $slug . ']'
						: '[' . $key . ']';
				} else {
					$child_key = (string) $key;
				}

				$next_path = '' === $path ? $child_key : $path . ( str_starts_with( $child_key, '[' ) ? $child_key : '.' . $child_key );
				$this->walk_leaves( $child, $next_path, $visit );
			}
			return;
		}

		$visit( $path, $value );
	}

	/**
	 * Look up the value at `$path` inside `$haystack`. Returns null when any
	 * segment is missing. Mirrors the path syntax produced by `walk_leaves`
	 * (dot-delimited keys, `[slug=...]` and `[N]` for list items).
	 *
	 * @param array  $haystack Stored payload.
	 * @param string $path     Dot-delimited path.
	 * @return mixed Value at the path, or null when absent.
	 */
	private function lookup_path( array $haystack, string $path ) {
		if ( '' === $path ) {
			return $haystack;
		}

		// Split on '.' and '[' boundaries while preserving the bracket segment.
		preg_match_all( '/\[[^\]]+\]|[^.\[]+/', $path, $matches );
		$segments = $matches[0];

		$cursor = $haystack;
		foreach ( $segments as $segment ) {
			// WP_REST_Global_Styles_Controller emits stdClass for empty
			// settings/styles subtrees. Coerce so the traversal does not
			// false-negative every leaf below.
			if ( is_object( $cursor ) ) {
				$cursor = (array) $cursor;
			}
			if ( ! is_array( $cursor ) ) {
				return null;
			}

			if ( str_starts_with( $segment, '[' ) ) {
				// WP normalises user-supplied preset lists (font families,
				// colours, etc.) into `{custom, theme, default}` "origin"
				// buckets. Callers post a flat list; the stored result is
				// wrapped. Flatten before any list-indexed lookup so the
				// requested path still resolves.
				$cursor = $this->flatten_origin_buckets( $cursor );
				if ( ! is_array( $cursor ) ) {
					return null;
				}

				if ( str_starts_with( $segment, '[slug=' ) ) {
					$slug  = substr( $segment, 6, -1 );
					$found = null;
					foreach ( $cursor as $item ) {
						$item = is_object( $item ) ? (array) $item : $item;
						if ( is_array( $item ) && isset( $item['slug'] ) && $item['slug'] === $slug ) {
							$found = $item;
							break;
						}
					}
					if ( null === $found ) {
						return null;
					}
					$cursor = $found;
					continue;
				}

				$index = (int) substr( $segment, 1, -1 );
				if ( ! array_key_exists( $index, $cursor ) ) {
					return null;
				}
				$cursor = $cursor[ $index ];
				continue;
			}

			if ( ! array_key_exists( $segment, $cursor ) ) {
				return null;
			}
			$cursor = $cursor[ $segment ];
		}

		return $cursor;
	}

	/**
	 * Loose equality for diff comparison. Scalars compare with `==` so that
	 * `"42"` and `42` agree (WP REST sometimes restringifies numerics). Arrays
	 * compare structurally after stringification of leaves; we only reach this
	 * helper for scalars in practice because `walk_leaves` recurses into arrays
	 * before invoking the visitor.
	 *
	 * @param mixed $requested Requested leaf value.
	 * @param mixed $stored    Stored leaf value.
	 * @return bool
	 */
	private function values_match( $requested, $stored ): bool {
		if ( null === $requested && null === $stored ) {
			return true;
		}
		if ( null === $requested || null === $stored ) {
			return false;
		}
		if ( is_scalar( $requested ) && is_scalar( $stored ) ) {
			// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- numeric/string drift after REST round-trip is expected
			return (string) $requested === (string) $stored;
		}
		return $requested === $stored;
	}

	/**
	 * Build the set of preset slugs that exist after this update, by union-ing
	 * the slugs already in the stored payload with the slugs the request is
	 * adding. Used to detect `var:preset|*|<slug>` references that point at
	 * nothing.
	 *
	 * The result is a map keyed by `<kind>|<slug>` (e.g. `font-family|fira-code`)
	 * because preset namespaces are independent — a color slug doesn't satisfy
	 * a font-family reference.
	 *
	 * @param array $requested Requested payload.
	 * @param array $stored    Stored payload returned by REST.
	 * @return array<string, bool>
	 */
	private function collect_known_preset_slugs( array $requested, array $stored ): array {
		$slugs = array();

		// Each collector points at the parent of the preset list. WP wraps the
		// list in `{custom, theme, default}` origin buckets when storing user
		// updates, so we flatten across all origins to collect every defined
		// slug regardless of where it was registered (top-level OR per-block,
		// custom OR theme-provided).
		$collectors = array(
			'font-family' => array( 'typography', 'fontFamilies' ),
			'font-size'   => array( 'typography', 'fontSizes' ),
			'color'       => array( 'color', 'palette' ),
		);

		foreach ( array( $requested, $stored ) as $source ) {
			$source = $this->coerce_to_array( $source );
			foreach ( $this->iter_per_block_and_global_settings( $source ) as $settings ) {
				foreach ( $collectors as $kind => $path ) {
					$node = $settings;
					foreach ( $path as $segment ) {
						$node = is_object( $node ) ? (array) $node : $node;
						if ( ! is_array( $node ) || ! isset( $node[ $segment ] ) ) {
							$node = null;
							break;
						}
						$node = $node[ $segment ];
					}
					$node = $this->flatten_origin_buckets( $node );
					if ( ! is_array( $node ) ) {
						continue;
					}
					foreach ( $node as $item ) {
						$item = is_object( $item ) ? (array) $item : $item;
						if ( is_array( $item ) && isset( $item['slug'] ) && is_string( $item['slug'] ) ) {
							$slugs[ $kind . '|' . $item['slug'] ] = true;
						}
					}
				}
			}
		}

		return $slugs;
	}

	/**
	 * Unwrap WordPress's `{custom, theme, default}` "origin" buckets into a
	 * single flat list. When the caller posts a flat preset list (e.g.
	 * `settings.typography.fontFamilies: [...]`) WP normalises the stored
	 * shape to `{custom: [...]}`. The diff walker uses flat list semantics
	 * (`[slug=...]`, `[N]`), so any indexed lookup against a stored payload
	 * must flatten the buckets first or it will false-negative every item.
	 *
	 * Behaviour:
	 *  - List in → list out (passes through).
	 *  - Dict with any of {custom, theme, default} keys → concatenated values.
	 *  - Anything else → returned unchanged.
	 *
	 * Coerces stdClass nodes before inspecting them.
	 *
	 * @param mixed $node Subtree to flatten.
	 * @return mixed Flat list when wrapping detected, otherwise $node unchanged.
	 */
	private function flatten_origin_buckets( $node ) {
		if ( is_object( $node ) ) {
			$node = (array) $node;
		}
		if ( ! is_array( $node ) || empty( $node ) ) {
			return $node;
		}
		// Already a list — nothing to unwrap.
		if ( array_keys( $node ) === range( 0, count( $node ) - 1 ) ) {
			return $node;
		}
		$origin_keys = array_intersect( array_keys( $node ), array( 'custom', 'theme', 'default' ) );
		if ( empty( $origin_keys ) ) {
			return $node;
		}
		$flat = array();
		foreach ( $origin_keys as $k ) {
			$bucket = is_object( $node[ $k ] ) ? (array) $node[ $k ] : $node[ $k ];
			if ( is_array( $bucket ) ) {
				foreach ( $bucket as $item ) {
					$flat[] = $item;
				}
			}
		}
		return $flat;
	}

	/**
	 * Detect a preset reference (`var:preset|<kind>|<slug>`) that will not
	 * resolve at render time — either because the token is wrong (`font`
	 * instead of `font-family`) or because the slug isn't registered anywhere.
	 *
	 * Only fires on `styles.*` leaves: under `settings.*` a similar-looking
	 * string would be data, not an applied reference.
	 *
	 * @param string              $path         Dot-path of the leaf.
	 * @param mixed               $value        Requested leaf value.
	 * @param array<string, bool> $preset_slugs Known preset slugs keyed by `<kind>|<slug>`.
	 * @return string|null Human-readable reason, or null when nothing is wrong.
	 */
	private function detect_unresolved_preset_reference( string $path, $value, array $preset_slugs ): ?string {
		if ( ! is_string( $value ) || ! str_starts_with( $path, 'styles.' ) ) {
			return null;
		}

		// WordPress accepts two interchangeable preset forms:
		// var:preset|<kind>|<slug>
		// var(--wp--preset--<kind>--<slug>)
		// LLMs reach for the second form when writing CSS-like values, so
		// check both.
		if ( preg_match( '/^var:preset\|([^|]+)\|(.+)$/', $value, $m ) ) {
			$kind = $m[1];
			$slug = $m[2];
		} elseif ( preg_match( '/^var\(--wp--preset--([a-z0-9-]+)--([a-z0-9-]+)\)$/', $value, $m ) ) {
			$kind = $m[1];
			$slug = $m[2];
		} else {
			return null;
		}

		if ( 'font' === $kind ) {
			return "Preset reference '{$value}' uses the wrong token — use 'var:preset|font-family|{$slug}' (not 'font'). WordPress will not resolve 'var:preset|font|*' and the stored value will have no visual effect.";
		}

		if ( ! isset( $preset_slugs[ $kind . '|' . $slug ] ) ) {
			$snippet = $this->build_registration_snippet( $kind, $slug );
			return "Preset reference '{$value}' points at an unknown slug — the value was stored but will not resolve at render time. To fix it, REGISTER the slug AND keep this styles application in the SAME call. Add this to your payload (fill in the placeholder fields), then resend together: {$snippet}";
		}

		return null;
	}

	/**
	 * Build a copy-paste-ready JSON snippet for the registration that an
	 * unresolved preset reference needs. The caller pastes this into the
	 * `settings.*` half of the payload, fills in the few placeholder fields
	 * (display name, CSS family stack, hex colour, size), and resends with
	 * the original `styles.*` application still attached.
	 *
	 * Slug → display name uses title-case of the dash-separated slug
	 * ("fira-code" → "Fira Code") as a reasonable default the caller can
	 * override.
	 *
	 * @param string $kind Preset kind: font-family | color | font-size | …
	 * @param string $slug Preset slug.
	 * @return string Snippet that can be embedded inline in a hint string.
	 */
	private function build_registration_snippet( string $kind, string $slug ): string {
		$display = ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );

		switch ( $kind ) {
			case 'font-family':
				return wp_json_encode(
					array(
						'settings' => array(
							'typography' => array(
								'fontFamilies' => array(
									array(
										'slug'       => $slug,
										'name'       => $display,
										'fontFamily' => "\"{$display}\", <fallback stack — e.g. monospace or sans-serif>",
									),
								),
							),
						),
					)
				);
			case 'color':
				return wp_json_encode(
					array(
						'settings' => array(
							'color' => array(
								'palette' => array(
									array(
										'slug'  => $slug,
										'name'  => $display,
										'color' => '<#hex value>',
									),
								),
							),
						),
					)
				);
			case 'font-size':
				return wp_json_encode(
					array(
						'settings' => array(
							'typography' => array(
								'fontSizes' => array(
									array(
										'slug' => $slug,
										'name' => $display,
										'size' => '<CSS size — e.g. 1rem>',
									),
								),
							),
						),
					)
				);
			default:
				return wp_json_encode(
					array(
						'settings' => array(
							$kind => array(
								array(
									'slug' => $slug,
									'name' => $display,
								),
							),
						),
					)
				);
		}
	}

	/**
	 * Reason text for a leaf whose requested value did not land in storage.
	 * Kept deliberately small — the caller has already ruled out the preset
	 * cases and the misplaced-subtree case before reaching this helper.
	 *
	 * @param mixed $stored Stored value at the path (or null when absent).
	 * @return string
	 */
	private function explain_missing_leaf( $stored ): string {
		if ( null === $stored ) {
			return 'Path was not written to the stored global styles. Most likely the key is not part of theme.json schema, or it was overridden by a sanitization step.';
		}

		if ( is_scalar( $stored ) ) {
			$stored_preview = (string) $stored;
		} else {
			$encoded        = (string) wp_json_encode( $stored );
			$stored_preview = strlen( $encoded ) > 200 ? substr( $encoded, 0, 200 ) . '…' : $encoded;
		}
		return "Stored value differs from requested. Stored: {$stored_preview}.";
	}

	/**
	 * Shallow object→array coercion for the top level of a REST payload, where
	 * `WP_REST_Global_Styles_Controller` may emit `stdClass` for empty
	 * subtrees. Deeper coercion happens inline at each traversal step.
	 *
	 * @param mixed $value Value to coerce.
	 * @return mixed Array when $value is array-like, otherwise $value unchanged.
	 */
	private function coerce_to_array( $value ) {
		if ( is_object( $value ) ) {
			return (array) $value;
		}
		return $value;
	}

	/**
	 * Yield every settings-shaped subtree in a global-styles payload — the
	 * top-level `settings` and each `settings.blocks.<block>` per-block
	 * override. Used to collect preset slugs from every location WordPress
	 * recognises, not just the root.
	 *
	 * @param array $payload Coerced top-level payload.
	 * @return iterable<array>
	 */
	private function iter_per_block_and_global_settings( array $payload ): iterable {
		$settings = $payload['settings'] ?? array();
		$settings = is_object( $settings ) ? (array) $settings : $settings;
		if ( ! is_array( $settings ) ) {
			return;
		}

		yield $settings;

		$blocks = $settings['blocks'] ?? array();
		$blocks = is_object( $blocks ) ? (array) $blocks : $blocks;
		if ( ! is_array( $blocks ) ) {
			return;
		}

		foreach ( $blocks as $block_settings ) {
			$block_settings = is_object( $block_settings ) ? (array) $block_settings : $block_settings;
			if ( is_array( $block_settings ) ) {
				yield $block_settings;
			}
		}
	}
}
