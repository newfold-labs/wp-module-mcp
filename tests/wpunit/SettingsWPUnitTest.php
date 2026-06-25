<?php

namespace BLU;

use BLU\Abilities\Settings;

/**
 * Tests for the Settings abilities, focused on the blogname/blogdescription
 * input aliasing in blu/update-general-settings.
 *
 * LLMs sometimes pass the raw WordPress option names (`blogname`/`blogdescription`)
 * instead of the REST endpoint's param names (`title`/`description`). The call is
 * otherwise valid, so the /wp/v2/settings endpoint returns 200 with nothing updated
 * — a silent no-op the LLM reports as success. The ability aliases the option names
 * to the REST param names so the call lands either way.
 *
 * @covers \BLU\Abilities\Settings
 */
class SettingsWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Abilities registered during tests that need cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array();

	/**
	 * Set up: require the Abilities API, log in as admin, ensure the core
	 * /wp/v2/settings route exists, and register the Settings abilities.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		// update-general-settings delegates to /wp/v2/settings, which requires manage_options.
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		// Bootstrap the REST server so the core settings route — and its
		// title->blogname / description->blogdescription mapping — is registered
		// for rest_do_request() inside the ability callback.
		rest_get_server();

		$this->register_settings_abilities();
	}

	/**
	 * Tear down: unregister abilities registered by these tests.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		$registry = \WP_Abilities_Registry::get_instance();
		foreach ( $this->registered_abilities as $name ) {
			if ( $registry && $registry->is_registered( $name ) ) {
				blu_unregister_ability( $name );
			}
		}
		$this->registered_abilities = array();
		parent::tear_down();
	}

	/**
	 * Register the Settings abilities through the wp_abilities_api_init hook.
	 *
	 * Mirrors the pattern in the other ability tests: WordPress 6.9+ only allows
	 * registration during that action, so we hook in and fire it manually if the
	 * registry was already bootstrapped earlier in the request.
	 *
	 * @return void
	 */
	private function register_settings_abilities(): void {
		$cb = function () {
			new Settings();
		};
		add_action( 'wp_abilities_api_init', $cb, 10 );

		$count_before = did_action( 'wp_abilities_api_init' );
		$registry     = \WP_Abilities_Registry::get_instance();
		if ( $registry && did_action( 'wp_abilities_api_init' ) === $count_before ) {
			do_action( 'wp_abilities_api_init', $registry );
		}
		remove_action( 'wp_abilities_api_init', $cb, 10 );

		$this->registered_abilities = array_merge(
			$this->registered_abilities,
			array( 'blu/get-general-settings', 'blu/update-general-settings' )
		);
	}

	/**
	 * Verifies the Settings abilities register.
	 *
	 * @return void
	 */
	public function test_registers_update_general_settings() {
		$ability = blu_get_ability( 'blu/update-general-settings' );
		$this->assertNotNull( $ability );
		$this->assertSame( 'Update General Settings', $ability->get_label() );
		$this->assertSame( 'blu-mcp', $ability->get_category() );
	}

	/**
	 * Verifies `blogname` is aliased to `title` and actually updates the site title.
	 *
	 * @return void
	 */
	public function test_blogname_alias_updates_site_title() {
		$ability = blu_get_ability( 'blu/update-general-settings' );
		$result  = $ability->execute( array( 'blogname' => 'Blu Aliased Title' ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 'Blu Aliased Title', get_option( 'blogname' ) );
	}

	/**
	 * Verifies `blogdescription` is aliased to `description` and updates the tagline.
	 *
	 * @return void
	 */
	public function test_blogdescription_alias_updates_tagline() {
		$ability = blu_get_ability( 'blu/update-general-settings' );
		$result  = $ability->execute( array( 'blogdescription' => 'Aliased Tagline' ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'Aliased Tagline', get_option( 'blogdescription' ) );
	}

	/**
	 * Verifies both option-name aliases work together in a single call.
	 *
	 * @return void
	 */
	public function test_both_aliases_apply_in_single_call() {
		$ability = blu_get_ability( 'blu/update-general-settings' );
		$result  = $ability->execute(
			array(
				'blogname'        => 'Name X',
				'blogdescription' => 'Desc Y',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'Name X', get_option( 'blogname' ) );
		$this->assertSame( 'Desc Y', get_option( 'blogdescription' ) );
	}

	/**
	 * Verifies the canonical REST param names still work (aliasing is additive,
	 * not a replacement).
	 *
	 * @return void
	 */
	public function test_canonical_title_param_still_updates() {
		$ability = blu_get_ability( 'blu/update-general-settings' );
		$result  = $ability->execute(
			array(
				'title'       => 'Canonical Title',
				'description' => 'Canonical Tagline',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'Canonical Title', get_option( 'blogname' ) );
		$this->assertSame( 'Canonical Tagline', get_option( 'blogdescription' ) );
	}

	/**
	 * Verifies the canonical param wins when both `title` and `blogname` are sent
	 * — the alias only fills in when `title` is absent (the `! isset` guard).
	 *
	 * @return void
	 */
	public function test_canonical_title_takes_precedence_over_blogname_alias() {
		$ability = blu_get_ability( 'blu/update-general-settings' );
		$result  = $ability->execute(
			array(
				'title'    => 'Wins',
				'blogname' => 'Loses',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'Wins', get_option( 'blogname' ) );
	}
}
