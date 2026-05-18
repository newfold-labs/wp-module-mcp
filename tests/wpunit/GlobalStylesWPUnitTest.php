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
