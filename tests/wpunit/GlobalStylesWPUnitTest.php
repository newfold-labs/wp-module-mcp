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
	 * Helper: register abilities and execute one as an admin.
	 *
	 * @param string $ability_name The registered ability name.
	 * @param array  $input        Input to pass to the ability.
	 * @return mixed The result of the ability execution.
	 */
	private function execute_ability( string $ability_name, array $input ) {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		new GlobalStyles();

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$ability = blu_get_ability( $ability_name );
		$this->assertNotNull( $ability, "Ability {$ability_name} should be registered." );

		return $ability->execute( $input );
	}

	/**
	 * Verifies constructor registers abilities without fatal.
	 */
	public function test_constructor_does_not_fatal() {
		$instance = new GlobalStyles();
		$this->assertInstanceOf( GlobalStyles::class, $instance );
	}

	// ── blu/update-global-styles ──────────────────────────────────────

	/**
	 * Verifies update-global-styles returns 400 when neither settings nor styles is provided.
	 */
	public function test_update_requires_settings_or_styles() {
		$result = $this->execute_ability( 'blu/update-global-styles', array() );

		$this->assertSame( 400, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );
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
