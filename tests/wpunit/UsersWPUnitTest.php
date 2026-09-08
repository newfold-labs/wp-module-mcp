<?php

namespace BLU;

use BLU\Abilities\Users;

/**
 * Tests for Users abilities, focused on behaviors that changed in the
 * RestControllerSchemaBuilder / dynamic-route-discovery refactor:
 *  - `context` now defaults to `edit` only when the caller omits it, instead
 *    of always being forced to `edit`.
 *  - `blu/delete-user` now requires `reassign` in its input schema.
 *  - Routes are resolved dynamically via RestApiUtils instead of being
 *    hardcoded to `/wp/v2/...`.
 *
 * @covers \BLU\Abilities\Users
 */
class UsersWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Names of abilities registered during tests that need cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array();

	/**
	 * The Users instance constructed during set_up, kept so tests can reflect
	 * into its private `base_namespace` to exercise route discovery without
	 * touching the real `wp` namespace.
	 *
	 * @var Users|null
	 */
	private $users_instance;

	/**
	 * Set up: ensure abilities API exists, log in as admin, register category, register Users abilities.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->ensure_category();
		$this->register_users_abilities();
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
		$this->users_instance       = null;
		parent::tear_down();
	}

	/**
	 * Ensure the blu-mcp ability category exists.
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
	 * Register the Users abilities via the wp_abilities_api_init hook, keeping a
	 * reference to the constructed instance for reflection-based tests.
	 *
	 * @return void
	 */
	private function register_users_abilities(): void {
		$instance = null;
		$cb       = function () use ( &$instance ) {
			$instance = new Users();
		};
		add_action( 'wp_abilities_api_init', $cb, 10 );

		$count_before = did_action( 'wp_abilities_api_init' );
		$registry     = \WP_Abilities_Registry::get_instance();
		if ( $registry && did_action( 'wp_abilities_api_init' ) === $count_before ) {
			do_action( 'wp_abilities_api_init', $registry );
		}
		remove_action( 'wp_abilities_api_init', $cb, 10 );

		$this->users_instance       = $instance;
		$this->registered_abilities = array(
			'blu/users-search',
			'blu/get-user',
			'blu/add-user',
			'blu/update-user',
			'blu/delete-user',
			'blu/get-current-user',
			'blu/update-current-user',
		);
	}

	/**
	 * Points the registered Users instance's route discovery at a synthetic base
	 * namespace via reflection, so route-resolution tests never touch the real
	 * `wp` namespace (which every other ability class also relies on).
	 *
	 * @param string $namespace Base namespace to use for the rest of the test.
	 *
	 * @return void
	 */
	private function set_base_namespace( string $namespace ): void {
		$prop = new \ReflectionProperty( Users::class, 'base_namespace' );
		$prop->setAccessible( true );
		$prop->setValue( $this->users_instance, $namespace );
	}

	/**
	 * Registers two versioned stub routes under a synthetic namespace so tests
	 * can assert route discovery picks the highest version rather than a
	 * hardcoded one.
	 *
	 * @return void
	 */
	private function register_versioned_stub_routes(): void {
		$server = rest_get_server();

		$server->register_route(
			'blu-users-test/v1',
			'/blu-users-test/v1/users',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => function () {
						return new \WP_REST_Response( array( 'version' => 'v1' ), 200 );
					},
					'permission_callback' => '__return_true',
				),
			),
			true
		);

		$server->register_route(
			'blu-users-test/v2',
			'/blu-users-test/v2/users',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => function () {
						return new \WP_REST_Response( array( 'version' => 'v2' ), 200 );
					},
					'permission_callback' => '__return_true',
				),
			),
			true
		);
	}

	// -----------------------------------------------------------------
	// context defaults to `edit` only when omitted (not force-overwritten)
	// -----------------------------------------------------------------

	/**
	 * Verifies get-user defaults to context=edit (e.g. exposes `email`/`roles`)
	 * when the caller doesn't specify a context.
	 *
	 * @return void
	 */
	public function test_get_user_defaults_to_edit_context_when_omitted(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$result = blu_get_ability( 'blu/get-user' )->execute( array( 'id' => $user_id ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertArrayHasKey( 'email', $result['message'], 'Edit context should expose email' );
		$this->assertArrayHasKey( 'roles', $result['message'], 'Edit context should expose roles' );
	}

	/**
	 * Verifies get-user honors an explicit context=view instead of silently
	 * forcing edit (the previous, pre-refactor behavior).
	 *
	 * @return void
	 */
	public function test_get_user_honors_explicit_view_context(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$result = blu_get_ability( 'blu/get-user' )->execute(
			array(
				'id'      => $user_id,
				'context' => 'view',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertArrayNotHasKey( 'email', $result['message'], 'view context must not be upgraded to edit' );
		$this->assertArrayNotHasKey( 'roles', $result['message'] );
	}

	/**
	 * Verifies users-search defaults to context=edit when the caller omits it.
	 *
	 * @return void
	 */
	public function test_users_search_defaults_to_edit_context_when_omitted(): void {
		self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$result = blu_get_ability( 'blu/users-search' )->execute( array() );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertNotEmpty( $result['message'] );
		foreach ( $result['message'] as $user ) {
			$this->assertArrayHasKey( 'email', $user );
		}
	}

	/**
	 * Verifies users-search honors an explicit context=view instead of forcing edit.
	 *
	 * @return void
	 */
	public function test_users_search_honors_explicit_context(): void {
		self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$result = blu_get_ability( 'blu/users-search' )->execute( array( 'context' => 'view' ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertNotEmpty( $result['message'] );
		foreach ( $result['message'] as $user ) {
			$this->assertArrayNotHasKey( 'email', $user );
		}
	}

	/**
	 * Verifies get-current-user defaults to context=edit when omitted.
	 *
	 * @return void
	 */
	public function test_get_current_user_defaults_to_edit_context_when_omitted(): void {
		$result = blu_get_ability( 'blu/get-current-user' )->execute( array() );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertArrayHasKey( 'email', $result['message'] );
	}

	/**
	 * Verifies get-current-user honors an explicit context=view.
	 *
	 * @return void
	 */
	public function test_get_current_user_honors_explicit_view_context(): void {
		$result = blu_get_ability( 'blu/get-current-user' )->execute( array( 'context' => 'view' ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertArrayNotHasKey( 'email', $result['message'] );
	}

	// -----------------------------------------------------------------
	// delete-user requiring reassign
	// -----------------------------------------------------------------

	/**
	 * Verifies the delete-user input schema marks both `id` and `reassign` as required.
	 *
	 * @return void
	 */
	public function test_delete_user_schema_requires_reassign(): void {
		$schema = blu_get_ability( 'blu/delete-user' )->get_input_schema();

		$this->assertArrayHasKey( 'required', $schema );
		$this->assertContains( 'id', $schema['required'] );
		$this->assertContains( 'reassign', $schema['required'] );
	}

	/**
	 * Verifies calling delete-user without `reassign` fails schema validation
	 * before the execute_callback ever runs, and the user is left untouched.
	 *
	 * @return void
	 */
	public function test_delete_user_without_reassign_fails_validation(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$result = blu_get_ability( 'blu/delete-user' )->execute( array( 'id' => $user_id ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertNotFalse( get_userdata( $user_id ), 'User must not be deleted when validation fails' );
	}

	/**
	 * Verifies delete-user succeeds end-to-end once `reassign` is supplied.
	 *
	 * @return void
	 */
	public function test_delete_user_with_reassign_deletes_user(): void {
		$reassign_to = self::factory()->user->create( array( 'role' => 'editor' ) );
		$user_id     = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$result = blu_get_ability( 'blu/delete-user' )->execute(
			array(
				'id'       => $user_id,
				'reassign' => $reassign_to,
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertFalse( get_userdata( $user_id ) );
	}

	/**
	 * Verifies delete-user refuses to delete the currently authenticated user,
	 * even with a valid `reassign` target and sufficient permissions, and that
	 * the user is left untouched.
	 *
	 * @return void
	 */
	public function test_delete_user_refuses_self_deletion(): void {
		$reassign_to = self::factory()->user->create( array( 'role' => 'editor' ) );
		$current_id  = get_current_user_id();

		$result = blu_get_ability( 'blu/delete-user' )->execute(
			array(
				'id'       => $current_id,
				'reassign' => $reassign_to,
			)
		);

		$this->assertSame( 400, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );
		$this->assertNotFalse( get_userdata( $current_id ), 'Currently logged-in user must not be deleted' );
	}

	// -----------------------------------------------------------------
	// dynamic route resolution
	// -----------------------------------------------------------------

	/**
	 * Verifies the ability discovers the highest versioned route at execution
	 * time instead of dispatching to a hardcoded `/wp/v2/...` path.
	 *
	 * @return void
	 */
	public function test_route_resolves_to_latest_registered_version(): void {
		$this->register_versioned_stub_routes();
		$this->set_base_namespace( 'blu-users-test' );

		$result = blu_get_ability( 'blu/users-search' )->execute( array() );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( array( 'version' => 'v2' ), $result['message'] );
	}

	/**
	 * Verifies the ability degrades gracefully with a standardized 400 error
	 * when no route can be discovered for the configured base namespace.
	 *
	 * @return void
	 */
	public function test_missing_route_returns_standardized_error(): void {
		$this->set_base_namespace( 'blu-users-missing-namespace' );

		$result = blu_get_ability( 'blu/get-user' )->execute( array( 'id' => 1 ) );

		$this->assertSame( 400, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );
		$this->assertStringContainsString( 'valid route for users not found', $result['message'] );
	}
}
