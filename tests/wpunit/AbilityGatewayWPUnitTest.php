<?php

namespace BLU;

use BLU\Abilities\AbilityGateway;

/**
 * Tests for AbilityGateway.
 *
 * @covers \BLU\Abilities\AbilityGateway
 */
class AbilityGatewayWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Names of abilities registered during tests that need cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array();

	/**
	 * Temporary callbacks on wp_abilities_api_init (must be removed after do_action).
	 *
	 * @var callable[]
	 */
	private $test_ability_hooks = array();

	/**
	 * Set up test fixtures.
	 *
	 * WordPress 6.9+ requires abilities to be registered during the
	 * {@see 'wp_abilities_api_init'} action. Instantiating {@see AbilityGateway}
	 * after that action has finished causes registration to fail. Tests register
	 * via {@see self::register_gateway()} which hooks into that action and runs
	 * {@see do_action( 'wp_abilities_api_init' )} — do not fire that action here.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		// Need an administrator for permission checks (edit_posts capability).
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$this->ensure_test_ability_categories();
	}

	/**
	 * Registers ability categories used by the gateway and test abilities.
	 *
	 * WordPress only runs {@see 'wp_abilities_api_categories_init'} when the
	 * categories registry is first bootstrapped. Manual {@see do_action()} does not
	 * use the same bootstrap path, and {@see wp_register_ability_category()} only
	 * works during that hook. For tests we register directly on the registry after
	 * {@see 'init'} (mirrors McpServer::register_ability_categories).
	 *
	 * @return void
	 */
	private function ensure_test_ability_categories(): void {
		$registry = \WP_Ability_Categories_Registry::get_instance();
		if ( ! $registry ) {
			return;
		}

		$categories = array(
			'blu-mcp'        => array(
				'label'       => 'Bluehost MCP',
				'description' => 'Bluehost-specific abilities for use with MCP',
			),
			'other-category' => array(
				'label'       => 'Other',
				'description' => 'Test category',
			),
		);

		foreach ( $categories as $slug => $args ) {
			if ( $registry->is_registered( $slug ) ) {
				continue;
			}
			$registry->register( $slug, $args );
		}
	}

	/**
	 * Clean up abilities registered during tests.
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
	 * Register the gateway abilities and track them for cleanup.
	 *
	 * @return void
	 */
	private function register_gateway(): void {
		$gateway_cb = function () {
			new AbilityGateway();
		};
		add_action( 'wp_abilities_api_init', $gateway_cb, 10 );

		// First WP_Abilities_Registry::get_instance() fires wp_abilities_api_init internally.
		// If the registry was already bootstrapped earlier in the request, get_instance() does not
		// fire the action again — run it manually so our hooks execute.
		$abilities_init_count_before = did_action( 'wp_abilities_api_init' );
		$abilities_registry          = \WP_Abilities_Registry::get_instance();
		if (
			$abilities_registry
			&& did_action( 'wp_abilities_api_init' ) === $abilities_init_count_before
		) {
			do_action( 'wp_abilities_api_init', $abilities_registry );
		}

		remove_action( 'wp_abilities_api_init', $gateway_cb, 10 );
		foreach ( $this->test_ability_hooks as $tb ) {
			remove_action( 'wp_abilities_api_init', $tb, 5 );
		}
		$this->test_ability_hooks = array();

		$this->registered_abilities = array_merge(
			$this->registered_abilities,
			array( 'blu/list-abilities', 'blu/get-ability-schema', 'blu/call-ability' )
		);
	}

	/**
	 * Register a test ability and track it for cleanup.
	 *
	 * @param string   $name     Ability name.
	 * @param string   $category Ability category.
	 * @param callable $execute  Execute callback.
	 *
	 * @return void
	 */
	private function register_test_ability( string $name, string $category, callable $execute ): void {
		$cb = function () use ( $name, $category, $execute ) {
			blu_register_ability(
				$name,
				array(
					'label'               => 'Test Ability',
					'description'         => 'A test ability',
					'category'            => $category,
					'input_schema'        => array( 'type' => 'object' ),
					'execute_callback'    => $execute,
					'permission_callback' => fn() => true,
				)
			);
		};
		add_action( 'wp_abilities_api_init', $cb, 5 );
		$this->test_ability_hooks[] = $cb;
		$this->registered_abilities[] = $name;
	}

	/**
	 * Verifies AbilityGateway class exists.
	 *
	 * @return void
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( AbilityGateway::class ) );
	}

	/**
	 * Verifies gateway registers blu/list-abilities.
	 *
	 * @return void
	 */
	public function test_registers_list_abilities() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/list-abilities' );
		$this->assertNotNull( $ability );
		$this->assertSame( 'List Abilities', $ability->get_label() );
		$this->assertSame( 'blu-mcp', $ability->get_category() );
	}

	/**
	 * Verifies gateway registers blu/get-ability-schema.
	 *
	 * @return void
	 */
	public function test_registers_get_ability_schema() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/get-ability-schema' );
		$this->assertNotNull( $ability );
		$this->assertSame( 'Get Ability Schema', $ability->get_label() );
		$this->assertSame( 'blu-mcp', $ability->get_category() );
	}

	/**
	 * Verifies gateway registers blu/call-ability.
	 *
	 * @return void
	 */
	public function test_registers_call_ability() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/call-ability' );
		$this->assertNotNull( $ability );
		$this->assertSame( 'Call Ability', $ability->get_label() );
		$this->assertSame( 'blu-mcp', $ability->get_category() );
	}

	/**
	 * Verifies blu/list-abilities has readonly annotations.
	 *
	 * @return void
	 */
	public function test_list_abilities_has_readonly_annotations() {
		$this->register_gateway();
		$ability     = blu_get_ability( 'blu/list-abilities' );
		$annotations = $ability->get_meta_item( 'annotations' );
		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
		$this->assertTrue( $annotations['idempotent'] );
	}

	/**
	 * Verifies blu/get-ability-schema has readonly annotations.
	 *
	 * @return void
	 */
	public function test_get_ability_schema_has_readonly_annotations() {
		$this->register_gateway();
		$ability     = blu_get_ability( 'blu/get-ability-schema' );
		$annotations = $ability->get_meta_item( 'annotations' );
		$this->assertTrue( $annotations['readonly'] );
		$this->assertFalse( $annotations['destructive'] );
		$this->assertTrue( $annotations['idempotent'] );
	}

	/**
	 * Verifies blu/call-ability has conservative annotations.
	 *
	 * @return void
	 */
	public function test_call_ability_has_conservative_annotations() {
		$this->register_gateway();
		$ability     = blu_get_ability( 'blu/call-ability' );
		$annotations = $ability->get_meta_item( 'annotations' );
		$this->assertFalse( $annotations['readonly'] );
		$this->assertTrue( $annotations['destructive'] );
		$this->assertFalse( $annotations['idempotent'] );
	}

	/**
	 * Verifies blu/get-ability-schema input_schema requires ability_name.
	 *
	 * @return void
	 */
	public function test_get_ability_schema_requires_ability_name() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/get-ability-schema' );
		$schema  = $ability->get_input_schema();
		$this->assertContains( 'ability_name', $schema['required'] );
	}

	/**
	 * Verifies blu/call-ability input_schema requires ability_name.
	 *
	 * @return void
	 */
	public function test_call_ability_requires_ability_name() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/call-ability' );
		$schema  = $ability->get_input_schema();
		$this->assertContains( 'ability_name', $schema['required'] );
	}

	/**
	 * Verifies blu/list-abilities input_schema has optional namespace property.
	 *
	 * @return void
	 */
	public function test_list_abilities_has_namespace_property() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/list-abilities' );
		$schema  = $ability->get_input_schema();
		$this->assertArrayHasKey( 'namespace', $schema['properties'] );
		$this->assertSame( 'string', $schema['properties']['namespace']['type'] );
	}

	/**
	 * Verifies blu/call-ability input_schema has parameters property.
	 *
	 * @return void
	 */
	public function test_call_ability_has_parameters_property() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/call-ability' );
		$schema  = $ability->get_input_schema();
		$this->assertArrayHasKey( 'parameters', $schema['properties'] );
		$this->assertSame( 'object', $schema['properties']['parameters']['type'] );
	}

	/**
	 * Verifies blu_mcp_allowed_namespaces filter is called with defaults.
	 *
	 * @return void
	 */
	public function test_allowed_namespaces_filter() {
		$this->register_gateway();
		$filter_called = false;
		$callback      = function ( $namespaces ) use ( &$filter_called ) {
			$filter_called = true;
			$this->assertIsArray( $namespaces );
			$this->assertContains( 'blu/', $namespaces );
			$this->assertContains( 'wc/', $namespaces );
			return $namespaces;
		};
		add_filter( 'blu_mcp_allowed_namespaces', $callback );

		$ability = blu_get_ability( 'blu/list-abilities' );
		$ability->execute( array() );
		$this->assertTrue( $filter_called );
		remove_filter( 'blu_mcp_allowed_namespaces', $callback );
	}

	/**
	 * Verifies blu_mcp_allowed_categories filter is called with defaults.
	 *
	 * @return void
	 */
	public function test_allowed_categories_filter() {
		$this->register_gateway();
		$filter_called = false;
		$callback      = function ( $categories ) use ( &$filter_called ) {
			$filter_called = true;
			$this->assertIsArray( $categories );
			$this->assertContains( 'blu-mcp', $categories );
			return $categories;
		};
		add_filter( 'blu_mcp_allowed_categories', $callback );

		$ability = blu_get_ability( 'blu/list-abilities' );
		$ability->execute( array() );
		$this->assertTrue( $filter_called );
		remove_filter( 'blu_mcp_allowed_categories', $callback );
	}

	/**
	 * Verifies get-ability-schema returns 404 for non-existent ability.
	 *
	 * @return void
	 */
	public function test_get_ability_schema_returns_404_for_unknown_ability() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/get-ability-schema' );
		$result  = $ability->execute( array( 'ability_name' => 'nonexistent/tool' ) );
		$this->assertSame( 404, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );
	}

	/**
	 * Verifies call-ability returns 404 for non-whitelisted ability.
	 *
	 * @return void
	 */
	public function test_call_ability_returns_404_for_non_whitelisted_ability() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/call-ability' );
		$result  = $ability->execute( array( 'ability_name' => 'nonexistent/tool' ) );
		$this->assertSame( 404, $result['statusCode'] );
		$this->assertSame( 'error', $result['status'] );
	}

	/**
	 * Verifies list-abilities returns success response.
	 *
	 * @return void
	 */
	public function test_list_abilities_returns_success() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/list-abilities' );
		$result  = $ability->execute( array() );
		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'success', $result['status'] );
		$this->assertIsArray( $result['message'] );
	}

	/**
	 * Verifies list-abilities includes the gateway tools themselves.
	 *
	 * @return void
	 */
	public function test_list_abilities_includes_gateway_tools() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/list-abilities' );
		$result  = $ability->execute( array() );
		$names   = array_column( $result['message'], 'name' );
		$this->assertContains( 'blu-list-abilities', $names );
		$this->assertContains( 'blu-get-ability-schema', $names );
		$this->assertContains( 'blu-call-ability', $names );
	}

	/**
	 * Verifies list-abilities entries have expected keys and no input_schema.
	 *
	 * @return void
	 */
	public function test_list_abilities_entries_have_expected_keys() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/list-abilities' );
		$result  = $ability->execute( array() );
		$this->assertNotEmpty( $result['message'] );
		$entry = $result['message'][0];
		$this->assertArrayHasKey( 'name', $entry );
		$this->assertArrayHasKey( 'label', $entry );
		$this->assertArrayHasKey( 'description', $entry );
		$this->assertArrayHasKey( 'annotations', $entry );
		$this->assertArrayNotHasKey( 'input_schema', $entry );
	}

	/**
	 * Verifies list-abilities namespace filter narrows results.
	 *
	 * @return void
	 */
	public function test_list_abilities_namespace_filter() {
		$this->register_test_ability(
			'testns/test-tool',
			'blu-mcp',
			function () {
				return blu_prepare_ability_response( 200, 'ok' );
			}
		);
		$this->register_gateway();

		$ability = blu_get_ability( 'blu/list-abilities' );

		// Filter by blu/ namespace should not include testns/test-tool.
		$result = $ability->execute( array( 'namespace' => 'blu/' ) );
		$names  = array_column( $result['message'], 'name' );
		$this->assertNotContains( 'testns-test-tool', $names );

		// All abilities in the result should use blu- prefix (hyphen form).
		foreach ( $names as $name ) {
			$this->assertStringStartsWith( 'blu-', $name );
		}
	}

	/**
	 * Verifies get-ability-schema returns schema for a whitelisted ability.
	 *
	 * @return void
	 */
	public function test_get_ability_schema_returns_schema_for_gateway_tool() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/get-ability-schema' );
		$result  = $ability->execute( array( 'ability_name' => 'blu/list-abilities' ) );
		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'blu-list-abilities', $result['message']['name'] );
		$this->assertArrayHasKey( 'input_schema', $result['message'] );
		$this->assertArrayHasKey( 'annotations', $result['message'] );
	}

	/**
	 * Verifies get-ability-schema accepts MCP hyphen ability_name.
	 *
	 * @return void
	 */
	public function test_get_ability_schema_accepts_mcp_hyphen_name() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/get-ability-schema' );
		$result  = $ability->execute( array( 'ability_name' => 'blu-list-abilities' ) );
		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'blu-list-abilities', $result['message']['name'] );
	}

	/**
	 * Verifies hyphen in the segment after namespace (e.g. blu/add-cpt → blu-add-cpt) resolves correctly.
	 *
	 * @return void
	 */
	public function test_get_ability_schema_resolves_hyphen_after_namespace() {
		$this->register_test_ability(
			'blu/add-mock',
			'blu-mcp',
			function () {
				return blu_prepare_ability_response( 200, 'ok' );
			}
		);
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/get-ability-schema' );
		$result  = $ability->execute( array( 'ability_name' => 'blu-add-mock' ) );
		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'blu-add-mock', $result['message']['name'] );
	}

	/**
	 * Verifies list-abilities name field uses hyphen form only.
	 *
	 * @return void
	 */
	public function test_list_abilities_name_uses_hyphen_form() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/list-abilities' );
		$result  = $ability->execute( array() );
		$this->assertNotEmpty( $result['message'] );
		foreach ( $result['message'] as $row ) {
			$this->assertArrayHasKey( 'name', $row );
			$this->assertStringNotContainsString( '/', $row['name'] );
		}
	}

	/**
	 * Verifies call-ability can execute a whitelisted ability.
	 *
	 * @return void
	 */
	public function test_call_ability_executes_whitelisted_ability() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/call-ability' );
		// Call list-abilities through the gateway.
		$result = $ability->execute(
			array(
				'ability_name' => 'blu/list-abilities',
				'parameters'   => array(),
			)
		);
		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'success', $result['status'] );
	}

	/**
	 * Verifies call-ability accepts MCP hyphen ability_name.
	 *
	 * @return void
	 */
	public function test_call_ability_accepts_mcp_hyphen_name() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/call-ability' );
		$result  = $ability->execute(
			array(
				'ability_name' => 'blu-list-abilities',
				'parameters'   => array(),
			)
		);
		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'success', $result['status'] );
	}

	/**
	 * Verifies call-ability omits parameters key still delegates with an empty object (not null).
	 *
	 * @return void
	 */
	public function test_call_ability_omitted_parameters_normalizes_to_empty_object() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/call-ability' );
		$result  = $ability->execute(
			array(
				'ability_name' => 'blu/list-abilities',
			)
		);
		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'success', $result['status'] );
	}

	/**
	 * Verifies non-whitelisted namespace abilities are excluded from list.
	 *
	 * @return void
	 */
	public function test_non_whitelisted_abilities_excluded_from_list() {
		$this->register_test_ability(
			'secret/hidden-tool',
			'other-category',
			function () {
				return blu_prepare_ability_response( 200, 'hidden' );
			}
		);
		$this->register_gateway();

		$ability = blu_get_ability( 'blu/list-abilities' );
		$result  = $ability->execute( array() );
		$names   = array_column( $result['message'], 'name' );
		$this->assertNotContains( 'secret-hidden-tool', $names );
	}

	/**
	 * Verifies get-ability-schema blocks non-whitelisted ability.
	 *
	 * @return void
	 */
	public function test_get_ability_schema_blocks_non_whitelisted() {
		$this->register_test_ability(
			'secret/hidden-tool',
			'other-category',
			function () {
				return blu_prepare_ability_response( 200, 'hidden' );
			}
		);
		$this->register_gateway();

		$ability = blu_get_ability( 'blu/get-ability-schema' );
		$result  = $ability->execute( array( 'ability_name' => 'secret/hidden-tool' ) );
		$this->assertSame( 404, $result['statusCode'] );
	}

	/**
	 * Verifies call-ability blocks non-whitelisted ability.
	 *
	 * @return void
	 */
	public function test_call_ability_blocks_non_whitelisted() {
		$this->register_test_ability(
			'secret/hidden-tool',
			'other-category',
			function () {
				return blu_prepare_ability_response( 200, 'hidden' );
			}
		);
		$this->register_gateway();

		$ability = blu_get_ability( 'blu/call-ability' );
		$result  = $ability->execute( array( 'ability_name' => 'secret/hidden-tool' ) );
		$this->assertSame( 404, $result['statusCode'] );
	}

	/**
	 * Verifies blu_mcp_use_gateway filter defaults to true.
	 *
	 * @return void
	 */
	public function test_gateway_filter_defaults_to_true() {
		$this->assertTrue( apply_filters( 'blu_mcp_use_gateway', true ) );
	}

	/**
	 * Verifies blu_mcp_use_gateway filter can be set to false.
	 *
	 * @return void
	 */
	public function test_gateway_filter_can_be_disabled() {
		add_filter( 'blu_mcp_use_gateway', '__return_false' );
		$this->assertFalse( apply_filters( 'blu_mcp_use_gateway', true ) );
		remove_filter( 'blu_mcp_use_gateway', '__return_false' );
	}
}
