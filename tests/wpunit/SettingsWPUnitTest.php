<?php

namespace BLU;

use BLU\Abilities\Settings;

/**
 * Tests for the Settings abilities (blu/get-general-settings and
 * blu/update-general-settings).
 *
 * Covers registration, metadata/annotations, input schema, permission gating,
 * the get/update happy paths, and the blogname/blogdescription -> title/description
 * aliasing in update-general-settings.
 *
 * The aliasing exists because LLMs sometimes pass the raw WordPress option names
 * (`blogname`/`blogdescription`) instead of the REST endpoint's param names
 * (`title`/`description`). The call is otherwise valid, so /wp/v2/settings returns
 * 200 with nothing updated — a silent no-op the LLM reports as success. The ability
 * aliases the option names so the call lands either way.
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
	 * Set up: require the Abilities API, log in as admin, register the blu-mcp
	 * category, ensure the core /wp/v2/settings route exists, and register the
	 * Settings abilities.
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

		$this->ensure_category();

		// Bootstrap the REST server so the core settings route — and its
		// title->blogname / description->blogdescription mapping — is registered
		// for rest_do_request() inside the ability callbacks.
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
	 * Ensure the blu-mcp ability category exists (abilities can't register without it).
	 *
	 * Mirrors McpServer::register_ability_categories and the other ability tests:
	 * wp_register_ability_category only works during wp_abilities_api_categories_init,
	 * so for tests we register directly on the registry.
	 *
	 * @return void
	 */
	private function ensure_category(): void {
		$registry = \WP_Ability_Categories_Registry::get_instance();
		if ( ! $registry || $registry->is_registered( 'blu-mcp' ) ) {
			return;
		}
		$registry->register(
			'blu-mcp',
			array(
				'label'       => 'Bluehost MCP',
				'description' => 'Bluehost-specific abilities for use with MCP',
			)
		);
	}

	/**
	 * Register the Settings abilities through the wp_abilities_api_init hook.
	 *
	 * WordPress 6.9+ only allows registration during that action, so we hook in and
	 * fire it manually if the registry was already bootstrapped earlier in the request.
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

	/* Registration & metadata */

	/**
	 * Verifies blu/get-general-settings registers with the expected label/category.
	 *
	 * @return void
	 */
	public function test_registers_get_general_settings() {
		$ability = blu_get_ability( 'blu/get-general-settings' );
		$this->assertNotNull( $ability );
		$this->assertSame( 'Get General Settings', $ability->get_label() );
		$this->assertSame( 'blu-mcp', $ability->get_category() );
	}

	/**
	 * Verifies blu/update-general-settings registers with the expected label/category.
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
	 * Verifies get-general-settings is annotated read-only/idempotent.
	 *
	 * @return void
	 */
	public function test_get_general_settings_has_readonly_annotations() {
		$annotations = blu_get_ability( 'blu/get-general-settings' )->get_meta_item( 'annotations' );
		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
		$this->assertTrue( $annotations['idempotent'] );
	}

	/**
	 * Verifies update-general-settings is annotated writable/non-destructive/idempotent.
	 *
	 * @return void
	 */
	public function test_update_general_settings_has_expected_annotations() {
		$annotations = blu_get_ability( 'blu/update-general-settings' )->get_meta_item( 'annotations' );
		$this->assertFalse( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
		$this->assertTrue( $annotations['idempotent'] );
	}

	/**
	 * Verifies the update-general-settings input schema advertises the expected fields/types.
	 *
	 * @return void
	 */
	public function test_update_general_settings_schema_has_expected_properties() {
		$schema = blu_get_ability( 'blu/update-general-settings' )->get_input_schema();

		$this->assertSame( 'object', $schema['type'] );
		$this->assertArrayHasKey( 'title', $schema['properties'] );
		$this->assertSame( 'string', $schema['properties']['title']['type'] );
		$this->assertArrayHasKey( 'description', $schema['properties'] );
		$this->assertSame( 'string', $schema['properties']['description']['type'] );
		$this->assertArrayHasKey( 'posts_per_page', $schema['properties'] );
		$this->assertSame( 'integer', $schema['properties']['posts_per_page']['type'] );
		$this->assertArrayHasKey( 'start_of_week', $schema['properties'] );
		$this->assertSame( 'integer', $schema['properties']['start_of_week']['type'] );
		$this->assertArrayHasKey( 'use_smilies', $schema['properties'] );
		$this->assertSame( 'boolean', $schema['properties']['use_smilies']['type'] );

		// The raw WP option names are deliberately NOT advertised — they are accepted
		// at runtime via aliasing, not declared as schema properties.
		$this->assertArrayNotHasKey( 'blogname', $schema['properties'] );
		$this->assertArrayNotHasKey( 'blogdescription', $schema['properties'] );
	}

	/* get-general-settings behavior */

	/**
	 * Verifies get-general-settings returns the settings payload with a 200/success shape.
	 *
	 * @return void
	 */
	public function test_get_general_settings_returns_settings_payload() {
		$result = blu_get_ability( 'blu/get-general-settings' )->execute( array() );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'success', $result['status'] );
		$this->assertIsArray( $result['message'] );
		$this->assertArrayHasKey( 'title', $result['message'] );
		$this->assertArrayHasKey( 'description', $result['message'] );
	}

	/* update-general-settings: aliasing */

	/**
	 * Verifies `blogname` is aliased to `title` and actually updates the site title.
	 *
	 * @return void
	 */
	public function test_blogname_alias_updates_site_title() {
		$result = blu_get_ability( 'blu/update-general-settings' )->execute( array( 'blogname' => 'Blu Aliased Title' ) );

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
		$result = blu_get_ability( 'blu/update-general-settings' )->execute( array( 'blogdescription' => 'Aliased Tagline' ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'Aliased Tagline', get_option( 'blogdescription' ) );
	}

	/**
	 * Verifies both option-name aliases work together in a single call.
	 *
	 * @return void
	 */
	public function test_both_aliases_apply_in_single_call() {
		$result = blu_get_ability( 'blu/update-general-settings' )->execute(
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
	 * Verifies the canonical REST param names still work (aliasing is additive).
	 *
	 * @return void
	 */
	public function test_canonical_title_param_still_updates() {
		$result = blu_get_ability( 'blu/update-general-settings' )->execute(
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
		$result = blu_get_ability( 'blu/update-general-settings' )->execute(
			array(
				'title'    => 'Wins',
				'blogname' => 'Loses',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'Wins', get_option( 'blogname' ) );
	}

	/* update-general-settings: non-aliased fields, no-op, errors, permissions */

	/**
	 * Verifies non-aliased fields are forwarded to /wp/v2/settings and persisted.
	 *
	 * @return void
	 */
	public function test_updates_non_aliased_fields_persist() {
		$result = blu_get_ability( 'blu/update-general-settings' )->execute(
			array(
				'posts_per_page' => 7,
				'date_format'    => 'Y-m-d',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 7, (int) get_option( 'posts_per_page' ) );
		$this->assertSame( 'Y-m-d', get_option( 'date_format' ) );
	}

	/**
	 * Verifies an empty input object is a no-op: the `if ( $input )` guard skips the
	 * REST body entirely, so the call still succeeds and changes nothing.
	 *
	 * @return void
	 */
	public function test_empty_input_is_a_noop() {
		$before = get_option( 'blogname' );

		$result = blu_get_ability( 'blu/update-general-settings' )->execute( array() );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( $before, get_option( 'blogname' ) );
	}

	/**
	 * Verifies a value of the wrong type is rejected by the ability's input-schema
	 * validation (posts_per_page is declared `integer`) before the callback runs, so
	 * the option is never mutated.
	 *
	 * @return void
	 */
	public function test_invalid_field_value_is_rejected_by_input_validation() {
		$before = (int) get_option( 'posts_per_page' );

		$result = blu_get_ability( 'blu/update-general-settings' )->execute(
			array( 'posts_per_page' => 'not-a-number' )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_input', $result->get_error_code() );
		$this->assertSame( $before, (int) get_option( 'posts_per_page' ) );
	}

	/**
	 * Verifies update-general-settings is denied (WP_Error) for a user lacking
	 * manage_options, and the option is left unchanged.
	 *
	 * @return void
	 */
	public function test_update_denied_for_non_admin() {
		$before = get_option( 'blogname' );

		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$result = blu_get_ability( 'blu/update-general-settings' )->execute( array( 'blogname' => 'Should Not Apply' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
		$this->assertSame( $before, get_option( 'blogname' ) );
	}

	/**
	 * Verifies get-general-settings is denied (WP_Error) for a user lacking manage_options.
	 *
	 * @return void
	 */
	public function test_get_denied_for_non_admin() {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$result = blu_get_ability( 'blu/get-general-settings' )->execute( array() );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'ability_invalid_permissions', $result->get_error_code() );
	}
}
