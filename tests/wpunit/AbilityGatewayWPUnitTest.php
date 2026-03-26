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
	 * Set up test fixtures.
	 *
	 * Ensures the Abilities API registry is initialized (which fires
	 * wp_abilities_api_init) so that wp_register_ability() calls succeed.
	 * In test environments the bootstrap skips automatic initialization.
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

		// The abilities API bootstrap skips initialization in test environments.
		// Manually trigger the registry singleton so wp_abilities_api_init fires
		// and did_action('wp_abilities_api_init') >= 1 for wp_register_ability().
		\WP_Abilities_Registry::get_instance();
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
		new AbilityGateway();
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
		$ability->execute();
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
		$ability->execute();
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
		$result  = $ability->execute();
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
		$result  = $ability->execute();
		$names   = array_column( $result['message'], 'name' );
		$this->assertContains( 'blu/list-abilities', $names );
		$this->assertContains( 'blu/get-ability-schema', $names );
		$this->assertContains( 'blu/call-ability', $names );
	}

	/**
	 * Verifies list-abilities entries have expected keys and no input_schema.
	 *
	 * @return void
	 */
	public function test_list_abilities_entries_have_expected_keys() {
		$this->register_gateway();
		$ability = blu_get_ability( 'blu/list-abilities' );
		$result  = $ability->execute();
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
		$this->assertNotContains( 'testns/test-tool', $names );

		// All abilities in the result should start with blu/.
		foreach ( $names as $name ) {
			$this->assertStringStartsWith( 'blu/', $name );
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
		$this->assertSame( 'blu/list-abilities', $result['message']['name'] );
		$this->assertArrayHasKey( 'input_schema', $result['message'] );
		$this->assertArrayHasKey( 'annotations', $result['message'] );
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
		$result  = $ability->execute();
		$names   = array_column( $result['message'], 'name' );
		$this->assertNotContains( 'secret/hidden-tool', $names );
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
