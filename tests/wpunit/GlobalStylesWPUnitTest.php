<?php

namespace BLU;

use BLU\Abilities\GlobalStyles;

/**
 * Tests for GlobalStyles abilities.
 *
 * @covers \BLU\Abilities\GlobalStyles
 */
class GlobalStylesWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Ability names registered during the test for cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array(
		'blu/get-global-styles',
		'blu/update-global-styles',
		'blu/get-active-global-styles',
		'blu/get-active-global-styles-id',
	);

	/**
	 * Whether abilities have been registered in this test instance.
	 *
	 * @var bool
	 */
	private $abilities_initialized = false;

	/**
	 * Skip if Abilities API is unavailable, set up admin user, ensure blu-mcp category.
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

		$cat_registry = \WP_Ability_Categories_Registry::get_instance();
		if ( $cat_registry && ! $cat_registry->is_registered( 'blu-mcp' ) ) {
			$cat_registry->register(
				'blu-mcp',
				array(
					'label'       => 'Bluehost MCP',
					'description' => 'Bluehost-specific abilities for use with MCP',
				)
			);
		}
	}

	/**
	 * Remove abilities registered by these tests.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$registry = \WP_Abilities_Registry::get_instance();
		if ( $registry ) {
			foreach ( $this->registered_abilities as $name ) {
				if ( $registry->is_registered( $name ) ) {
					blu_unregister_ability( $name );
				}
			}
		}
		$this->abilities_initialized = false;
		parent::tear_down();
	}

	/**
	 * Register GlobalStyles abilities via the wp_abilities_api_init action.
	 *
	 * @return void
	 */
	private function ensure_abilities_registered(): void {
		if ( $this->abilities_initialized ) {
			return;
		}

		$cb = function () {
			new GlobalStyles();
		};
		add_action( 'wp_abilities_api_init', $cb, 10 );

		$init_count_before = did_action( 'wp_abilities_api_init' );
		$registry          = \WP_Abilities_Registry::get_instance();
		if ( $registry && did_action( 'wp_abilities_api_init' ) === $init_count_before ) {
			do_action( 'wp_abilities_api_init', $registry );
		}

		remove_action( 'wp_abilities_api_init', $cb, 10 );
		$this->abilities_initialized = true;
	}

	/**
	 * Helper: execute a registered ability by name.
	 *
	 * @param string $ability_name The registered ability name.
	 * @param array  $input        Input to pass to the ability.
	 * @return mixed The result of the ability execution.
	 */
	private function execute_ability( string $ability_name, array $input ) {
		$this->ensure_abilities_registered();

		$ability = blu_get_ability( $ability_name );
		$this->assertNotNull( $ability, "Ability {$ability_name} should be registered." );

		return $ability->execute( $input );
	}

	// ── blu/update-global-styles ──────────────────────────────────────

	/**
	 * Verifies the anyOf schema rejects update-global-styles calls that provide neither settings nor styles.
	 */
	public function test_update_requires_settings_or_styles() {
		$result = $this->execute_ability( 'blu/update-global-styles', array() );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Verifies update-global-styles with settings only does not fatal and returns a response.
	 *
	 * The REST endpoint and global-styles post resolution are exercised through real
	 * WordPress APIs; we assert the callback returns a normalized response array.
	 */
	public function test_update_with_settings_returns_response() {
		$result = $this->execute_ability(
			'blu/update-global-styles',
			array(
				'settings' => array(
					'color' => array(
						'palette' => array(
							'theme' => array(
								array(
									'slug'  => 'accent-1',
									'color' => '#0B3D5B',
									'name'  => 'Primary',
								),
							),
						),
					),
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'statusCode', $result );
	}

	/**
	 * Verifies update-global-styles with styles only does not fatal and returns a response.
	 */
	public function test_update_with_styles_returns_response() {
		$result = $this->execute_ability(
			'blu/update-global-styles',
			array(
				'styles' => array(
					'color' => array(
						'background' => '#ffffff',
					),
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'statusCode', $result );
	}

	/**
	 * A pure styles write (e.g. set the active background colour) should land in
	 * `applied` and leave `not_applied` empty — proving the diff fires on the
	 * common happy path that the agent uses for "change the colour of X".
	 */
	public function test_update_with_styles_reports_applied_path() {
		$result = $this->execute_ability(
			'blu/update-global-styles',
			array(
				'styles' => array(
					'color' => array(
						'background' => '#fafafa',
					),
				),
			)
		);

		$this->assertIsArray( $result );
		$this->assertGreaterThanOrEqual( 200, $result['statusCode'] );
		$this->assertLessThan( 300, $result['statusCode'] );
		$this->assertArrayHasKey( 'applied', $result );
		$this->assertArrayHasKey( 'not_applied', $result );
		$this->assertContains( 'styles.color.background', $result['applied'] );
		$this->assertSame( array(), $result['not_applied'] );
	}

	/**
	 * Applying a scalar typography value under `settings.blocks.<block>.*` (the
	 * misplaced-application case from the production log) must surface as a
	 * `not_applied` entry pointing the agent at the correct
	 * `styles.blocks.<block>` path. Legitimate per-block REGISTRATIONS under
	 * settings.blocks.<block>.typography.fontFamilies (plural) are exercised
	 * by `test_per_block_registration_is_not_flagged` and must NOT be flagged.
	 */
	public function test_misplaced_settings_blocks_application_produces_hint() {
		$result = $this->execute_ability(
			'blu/update-global-styles',
			array(
				'settings' => array(
					'blocks' => array(
						'core/paragraph' => array(
							'typography' => array(
								'fontFamily' => 'var:preset|font-family|fira-code',
							),
						),
					),
				),
			)
		);

		$this->assertIsArray( $result );
		if ( $result['statusCode'] < 200 || $result['statusCode'] >= 300 ) {
			$this->markTestSkipped( 'REST update did not succeed in this harness; cannot assert on diff.' );
		}
		$this->assertNotEmpty( $result['not_applied'] );

		$paths  = array_column( $result['not_applied'], 'path' );
		$target = 'settings.blocks.core/paragraph.typography.fontFamily';
		$index  = array_search( $target, $paths, true );
		$this->assertNotFalse( $index, "expected {$target} in not_applied paths" );

		$reason = $result['not_applied'][ $index ]['reason'];
		$this->assertStringContainsString( 'styles.blocks.core/paragraph.typography.fontFamily', $reason );
	}

	/**
	 * Per-block REGISTRATION under `settings.blocks.<block>.typography.fontFamilies`
	 * (plural, an array) is a real WordPress feature — the diff must NOT flag
	 * it as misplaced. Without this guard the hint heuristic regresses to the
	 * over-aggressive blanket pre-strip that misadvised the LLM on legitimate
	 * registrations.
	 */
	public function test_per_block_registration_is_not_flagged() {
		$result = $this->execute_ability(
			'blu/update-global-styles',
			array(
				'settings' => array(
					'blocks' => array(
						'core/heading' => array(
							'typography' => array(
								'fontFamilies' => array(
									array(
										'slug'       => 'fira-code',
										'name'       => 'Fira Code',
										'fontFamily' => '"Fira Code", monospace',
									),
								),
							),
						),
					),
				),
			)
		);

		$this->assertIsArray( $result );
		if ( $result['statusCode'] < 200 || $result['statusCode'] >= 300 ) {
			$this->markTestSkipped( 'REST update did not succeed in this harness; cannot assert on diff.' );
		}

		$paths  = array_column( $result['not_applied'], 'path' );
		$prefix = 'settings.blocks.core/heading.typography.fontFamilies';
		foreach ( $paths as $p ) {
			$this->assertNotSame( 0, strpos( $p, $prefix ), "path '{$p}' must not be flagged as misplaced" );
		}
	}

	/**
	 * Posting a flat `fontFamilies` array registers under the `custom` origin
	 * bucket — the stored shape is `{custom: [...]}`. The diff must traverse
	 * through that wrapper or it will false-negative the registration (this
	 * was the bug that misled the agent into a destructive retry loop on
	 * 2026-05-25, see the production log analysis).
	 */
	public function test_flat_font_family_registration_is_reported_applied() {
		$result = $this->execute_ability(
			'blu/update-global-styles',
			array(
				'settings' => array(
					'typography' => array(
						'fontFamilies' => array(
							array(
								'slug'       => 'fira-code',
								'name'       => 'Fira Code',
								'fontFamily' => "'Fira Code', monospace",
							),
						),
					),
				),
			)
		);

		$this->assertIsArray( $result );
		if ( $result['statusCode'] < 200 || $result['statusCode'] >= 300 ) {
			$this->markTestSkipped( 'REST update did not succeed in this harness; cannot assert on diff.' );
		}

		// The three leaves of the registration item must all appear in
		// `applied`, even though WP wrapped them under `custom` in storage.
		$this->assertContains( 'settings.typography.fontFamilies[slug=fira-code].slug', $result['applied'] );
		$this->assertContains( 'settings.typography.fontFamilies[slug=fira-code].name', $result['applied'] );
		$this->assertContains( 'settings.typography.fontFamilies[slug=fira-code].fontFamily', $result['applied'] );

		// And none of those three leaves should appear as not_applied with the
		// generic "Path was not written..." reason.
		$bad_paths = array(
			'settings.typography.fontFamilies[slug=fira-code].slug',
			'settings.typography.fontFamilies[slug=fira-code].name',
			'settings.typography.fontFamilies[slug=fira-code].fontFamily',
		);
		foreach ( $result['not_applied'] as $entry ) {
			$this->assertNotContains( $entry['path'], $bad_paths, "leaf '{$entry['path']}' was wrongly reported as not_applied" );
		}
	}

	/**
	 * Top-level `settings.typography.fontFamily` (singular, scalar) is the
	 * misplaced-application analog of the per-block case. It must surface as
	 * a `not_applied` entry whose reason points the agent at
	 * `styles.typography.fontFamily`. This is the exact failure mode the
	 * production agent hit on 2026-05-25 — without this hint the agent got an
	 * unactionable "Path was not written..." message and gave up.
	 */
	public function test_misplaced_top_level_settings_application_produces_hint() {
		$result = $this->execute_ability(
			'blu/update-global-styles',
			array(
				'settings' => array(
					'typography' => array(
						'fontFamily' => 'var:preset|font|fira-code',
					),
				),
			)
		);

		$this->assertIsArray( $result );
		if ( $result['statusCode'] < 200 || $result['statusCode'] >= 300 ) {
			$this->markTestSkipped( 'REST update did not succeed in this harness; cannot assert on diff.' );
		}

		$paths = array_column( $result['not_applied'], 'path' );
		$this->assertContains( 'settings.typography.fontFamily', $paths );

		$entry  = $result['not_applied'][ array_search( 'settings.typography.fontFamily', $paths, true ) ];
		$reason = $entry['reason'];
		$this->assertStringContainsString( 'styles.typography.fontFamily', $reason );
		// The value also has the wrong preset token; the combined hint should
		// surface both fixes in one round-trip.
		$this->assertStringContainsString( 'font-family', $reason );
	}

	/**
	 * Applying a `var:preset|font|<slug>` reference (the wrong token — should be
	 * `font-family`) must trigger the targeted hint, not a generic "value
	 * differs" message. This protects against the exact mistake the agent made
	 * on attempt 6 in the production log.
	 */
	public function test_wrong_preset_var_token_produces_targeted_hint() {
		$result = $this->execute_ability(
			'blu/update-global-styles',
			array(
				'styles' => array(
					'typography' => array(
						'fontFamily' => 'var:preset|font|fira-code',
					),
				),
			)
		);

		$this->assertIsArray( $result );
		if ( $result['statusCode'] < 200 || $result['statusCode'] >= 300 ) {
			$this->markTestSkipped( 'REST update did not succeed in this harness; cannot assert on diff.' );
		}

		$paths = array_column( $result['not_applied'], 'path' );
		$this->assertContains( 'styles.typography.fontFamily', $paths );

		$entry = $result['not_applied'][ array_search( 'styles.typography.fontFamily', $paths, true ) ];
		$this->assertStringContainsString( 'font-family', $entry['reason'] );
	}

	/**
	 * Applying a preset reference to a slug that is not registered anywhere
	 * must surface a hint containing a copy-paste-ready JSON snippet for the
	 * registration entry the caller needs to add. Without this, the agent
	 * reads "register the slug" as a vague instruction and (per the 2026-05-25
	 * production log) stops to ask the user instead of just doing it.
	 */
	public function test_unknown_slug_hint_includes_registration_snippet() {
		$result = $this->execute_ability(
			'blu/update-global-styles',
			array(
				'styles' => array(
					'typography' => array(
						'fontFamily' => 'var:preset|font-family|fira-code',
					),
				),
			)
		);

		$this->assertIsArray( $result );
		if ( $result['statusCode'] < 200 || $result['statusCode'] >= 300 ) {
			$this->markTestSkipped( 'REST update did not succeed in this harness; cannot assert on diff.' );
		}

		$paths = array_column( $result['not_applied'], 'path' );
		$this->assertContains( 'styles.typography.fontFamily', $paths );

		$entry  = $result['not_applied'][ array_search( 'styles.typography.fontFamily', $paths, true ) ];
		$reason = $entry['reason'];

		// The hint must contain a valid JSON snippet the caller can paste into
		// the next call's `settings.*` payload — extract everything from the
		// first `{` onward and assert it parses + has the expected shape.
		$brace_pos = strpos( $reason, '{' );
		$this->assertNotFalse( $brace_pos, "hint must embed a JSON snippet; got: {$reason}" );
		$snippet = substr( $reason, $brace_pos );
		$decoded = json_decode( $snippet, true );
		$this->assertIsArray( $decoded, "embedded snippet must be valid JSON; got: {$snippet}" );

		$this->assertSame(
			'fira-code',
			$decoded['settings']['typography']['fontFamilies'][0]['slug'] ?? null,
			'snippet must register the exact slug from the failed reference'
		);
		$this->assertSame(
			'Fira Code',
			$decoded['settings']['typography']['fontFamilies'][0]['name'] ?? null,
			'snippet should title-case the slug as the display name default'
		);
		$this->assertArrayHasKey(
			'fontFamily',
			$decoded['settings']['typography']['fontFamilies'][0],
			'snippet must include a fontFamily placeholder for the caller to fill in'
		);
	}

	// ── blu/get-active-global-styles ──────────────────────────────────

	/**
	 * Verifies get-active-global-styles returns a response (either 200 with data or 404).
	 */
	public function test_get_active_global_styles_returns_response() {
		$result = $this->execute_ability( 'blu/get-active-global-styles', array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'statusCode', $result );
		$this->assertContains( $result['statusCode'], array( 200, 404 ) );
	}

	// ── blu/get-active-global-styles-id ───────────────────────────────

	/**
	 * Verifies get-active-global-styles-id returns a response with an id field on success.
	 */
	public function test_get_active_global_styles_id_returns_response() {
		$result = $this->execute_ability( 'blu/get-active-global-styles-id', array() );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'statusCode', $result );
		$this->assertContains( $result['statusCode'], array( 200, 404 ) );
	}
}
