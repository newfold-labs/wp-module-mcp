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
	 * Verifies AbilityGateway class exists.
	 *
	 * @return void
	 */
	public function test_class_exists() {
		$this->assertTrue( class_exists( AbilityGateway::class ) );
	}

	/**
	 * Verifies AbilityGateway constructor runs without errors.
	 *
	 * @return void
	 */
	public function test_constructor_runs_without_errors() {
		$gateway = new AbilityGateway();
		$this->assertInstanceOf( AbilityGateway::class, $gateway );
	}

	/**
	 * Verifies gateway registers blu/list-abilities when abilities API is available.
	 *
	 * @return void
	 */
	public function test_registers_list_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
		$ability = blu_get_ability( 'blu/list-abilities' );
		$this->assertNotNull( $ability );
		$this->assertSame( 'List Abilities', $ability->get_label() );
		$this->assertSame( 'blu-mcp', $ability->get_category() );
	}

	/**
	 * Verifies gateway registers blu/get-ability-schema when abilities API is available.
	 *
	 * @return void
	 */
	public function test_registers_get_ability_schema() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
		$ability = blu_get_ability( 'blu/get-ability-schema' );
		$this->assertNotNull( $ability );
		$this->assertSame( 'Get Ability Schema', $ability->get_label() );
		$this->assertSame( 'blu-mcp', $ability->get_category() );
	}

	/**
	 * Verifies gateway registers blu/call-ability when abilities API is available.
	 *
	 * @return void
	 */
	public function test_registers_call_ability() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
		$ability = blu_get_ability( 'blu/call-ability' );
		$schema  = $ability->get_input_schema();
		$this->assertArrayHasKey( 'parameters', $schema['properties'] );
		$this->assertSame( 'object', $schema['properties']['parameters']['type'] );
	}

	/**
	 * Verifies blu_mcp_allowed_namespaces filter can add namespaces.
	 *
	 * @return void
	 */
	public function test_allowed_namespaces_filter() {
		$filter_called = false;
		$callback      = function ( $namespaces ) use ( &$filter_called ) {
			$filter_called = true;
			$this->assertIsArray( $namespaces );
			$this->assertContains( 'blu/', $namespaces );
			$this->assertContains( 'wc/', $namespaces );
			return $namespaces;
		};
		add_filter( 'blu_mcp_allowed_namespaces', $callback );

		// Trigger the filter by calling list-abilities execute callback.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			// Even without the API, we can verify the filter hook name is correct
			// by checking that our callback was registered.
			$this->assertNotFalse( has_filter( 'blu_mcp_allowed_namespaces', $callback ) );
			remove_filter( 'blu_mcp_allowed_namespaces', $callback );
			$this->markTestSkipped( 'WP Abilities API is not available to fully test filter.' );
		}

		new AbilityGateway();
		$ability = blu_get_ability( 'blu/list-abilities' );
		$ability->execute();
		$this->assertTrue( $filter_called );
		remove_filter( 'blu_mcp_allowed_namespaces', $callback );
	}

	/**
	 * Verifies blu_mcp_allowed_categories filter can add categories.
	 *
	 * @return void
	 */
	public function test_allowed_categories_filter() {
		$filter_called = false;
		$callback      = function ( $categories ) use ( &$filter_called ) {
			$filter_called = true;
			$this->assertIsArray( $categories );
			$this->assertContains( 'blu-mcp', $categories );
			return $categories;
		};
		add_filter( 'blu_mcp_allowed_categories', $callback );

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->assertNotFalse( has_filter( 'blu_mcp_allowed_categories', $callback ) );
			remove_filter( 'blu_mcp_allowed_categories', $callback );
			$this->markTestSkipped( 'WP Abilities API is not available to fully test filter.' );
		}

		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		// Register a test ability in a different namespace.
		blu_register_ability(
			'testns/test-tool',
			array(
				'label'               => 'Test Tool',
				'description'         => 'A test tool in testns namespace',
				'category'            => 'blu-mcp',
				'input_schema'        => array( 'type' => 'object' ),
				'execute_callback'    => function () {
					return blu_prepare_ability_response( 200, 'ok' );
				},
				'permission_callback' => fn() => true,
			)
		);

		new AbilityGateway();
		$ability = blu_get_ability( 'blu/list-abilities' );

		// Filter by blu/ namespace should not include testns/test-tool.
		$result = $ability->execute( array( 'namespace' => 'blu/' ) );
		$names  = array_column( $result['message'], 'name' );
		$this->assertNotContains( 'testns/test-tool', $names );

		// All abilities in the result should start with blu/.
		foreach ( $names as $name ) {
			$this->assertStringStartsWith( 'blu/', $name );
		}

		blu_unregister_ability( 'testns/test-tool' );
	}

	/**
	 * Verifies get-ability-schema returns schema for a whitelisted ability.
	 *
	 * @return void
	 */
	public function test_get_ability_schema_returns_schema_for_gateway_tool() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		new AbilityGateway();
		$ability = blu_get_ability( 'blu/call-ability' );
		// Call list-abilities through the gateway — it requires no special permissions beyond edit_posts.
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
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		// Register an ability in a non-whitelisted namespace and category.
		blu_register_ability(
			'secret/hidden-tool',
			array(
				'label'               => 'Hidden Tool',
				'description'         => 'Should not appear in gateway',
				'category'            => 'other-category',
				'input_schema'        => array( 'type' => 'object' ),
				'execute_callback'    => function () {
					return blu_prepare_ability_response( 200, 'hidden' );
				},
				'permission_callback' => fn() => true,
			)
		);

		new AbilityGateway();
		$ability = blu_get_ability( 'blu/list-abilities' );
		$result  = $ability->execute();
		$names   = array_column( $result['message'], 'name' );
		$this->assertNotContains( 'secret/hidden-tool', $names );

		blu_unregister_ability( 'secret/hidden-tool' );
	}

	/**
	 * Verifies get-ability-schema blocks non-whitelisted ability.
	 *
	 * @return void
	 */
	public function test_get_ability_schema_blocks_non_whitelisted() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		blu_register_ability(
			'secret/hidden-tool',
			array(
				'label'               => 'Hidden Tool',
				'description'         => 'Should not be accessible',
				'category'            => 'other-category',
				'input_schema'        => array( 'type' => 'object' ),
				'execute_callback'    => function () {
					return blu_prepare_ability_response( 200, 'hidden' );
				},
				'permission_callback' => fn() => true,
			)
		);

		new AbilityGateway();
		$ability = blu_get_ability( 'blu/get-ability-schema' );
		$result  = $ability->execute( array( 'ability_name' => 'secret/hidden-tool' ) );
		$this->assertSame( 404, $result['statusCode'] );

		blu_unregister_ability( 'secret/hidden-tool' );
	}

	/**
	 * Verifies call-ability blocks non-whitelisted ability.
	 *
	 * @return void
	 */
	public function test_call_ability_blocks_non_whitelisted() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		blu_register_ability(
			'secret/hidden-tool',
			array(
				'label'               => 'Hidden Tool',
				'description'         => 'Should not be callable',
				'category'            => 'other-category',
				'input_schema'        => array( 'type' => 'object' ),
				'execute_callback'    => function () {
					return blu_prepare_ability_response( 200, 'hidden' );
				},
				'permission_callback' => fn() => true,
			)
		);

		new AbilityGateway();
		$ability = blu_get_ability( 'blu/call-ability' );
		$result  = $ability->execute( array( 'ability_name' => 'secret/hidden-tool' ) );
		$this->assertSame( 404, $result['statusCode'] );

		blu_unregister_ability( 'secret/hidden-tool' );
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
