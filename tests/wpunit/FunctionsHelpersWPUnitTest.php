<?php

namespace BLU;

/**
 * Tests for input/list helpers and ability-type filtering helpers added in functions.php.
 *
 * @covers ::blu_is_valid_list
 * @covers ::blu_is_valid_input_array
 * @covers ::blu_filter_terms_by_patterns
 * @covers ::blu_get_ability_by_type
 */
class FunctionsHelpersWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Names of abilities registered during tests that need cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array();

	/**
	 * Hooks added on wp_abilities_api_init that must be removed after the action runs.
	 *
	 * @var callable[]
	 */
	private $test_ability_hooks = array();

	/**
	 * Clean up abilities registered during tests so they do not leak across tests.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		if ( class_exists( '\WP_Abilities_Registry' ) ) {
			$registry = \WP_Abilities_Registry::get_instance();
			foreach ( $this->registered_abilities as $name ) {
				if ( $registry && $registry->is_registered( $name ) ) {
					blu_unregister_ability( $name );
				}
			}
		}
		$this->registered_abilities = array();
		$this->test_ability_hooks   = array();
		parent::tear_down();
	}

	/**
	 * Empty array is treated as a valid list (zero iterations, no index mismatch).
	 *
	 * @return void
	 */
	public function test_is_valid_list_accepts_empty_array() {
		$this->assertTrue( blu_is_valid_list( array() ) );
	}

	/**
	 * Indexed array with sequential keys starting at zero is a valid list.
	 *
	 * @return void
	 */
	public function test_is_valid_list_accepts_sequential_indexed_array() {
		$this->assertTrue( blu_is_valid_list( array( 'a', 'b', 'c' ) ) );
	}

	/**
	 * String-keyed (associative) arrays are not valid lists.
	 *
	 * @return void
	 */
	public function test_is_valid_list_rejects_associative_array() {
		$this->assertFalse( blu_is_valid_list( array( 'foo' => 'bar' ) ) );
	}

	/**
	 * Numeric-keyed arrays with gaps are not valid lists.
	 *
	 * @return void
	 */
	public function test_is_valid_list_rejects_non_sequential_keys() {
		$this->assertFalse(
			blu_is_valid_list(
				array(
					0 => 'a',
					2 => 'b',
				)
			)
		);
	}

	/**
	 * Array whose first key is not zero is not a valid list.
	 *
	 * @return void
	 */
	public function test_is_valid_list_rejects_offset_first_key() {
		$this->assertFalse(
			blu_is_valid_list(
				array(
					1 => 'a',
					2 => 'b',
				)
			)
		);
	}

	/**
	 * A simple list passes blu_is_valid_input_array with no bounds.
	 *
	 * @return void
	 */
	public function test_is_valid_input_array_accepts_simple_list() {
		$this->assertTrue( blu_is_valid_input_array( array( 'x', 'y' ), 'items' ) );
	}

	/**
	 * Empty list passes when no min_items is enforced.
	 *
	 * @return void
	 */
	public function test_is_valid_input_array_accepts_empty_when_no_min() {
		$this->assertTrue( blu_is_valid_input_array( array(), 'items' ) );
	}

	/**
	 * A list below min_items is rejected with a WP_Error mentioning the field name.
	 *
	 * @return void
	 */
	public function test_is_valid_input_array_rejects_below_min_items() {
		$result = blu_is_valid_input_array( array( 'a' ), 'items', 2 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'items', $result->get_error_message() );
		$this->assertStringContainsString( 'at least 2', $result->get_error_message() );
	}

	/**
	 * A list above max_items is rejected with a WP_Error mentioning the field name.
	 *
	 * @return void
	 */
	public function test_is_valid_input_array_rejects_above_max_items() {
		$result = blu_is_valid_input_array( array( 'a', 'b', 'c' ), 'items', false, 2 );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'items', $result->get_error_message() );
		$this->assertStringContainsString( 'more than 2', $result->get_error_message() );
	}

	/**
	 * An associative array is rejected because it is not a list.
	 *
	 * @return void
	 */
	public function test_is_valid_input_array_rejects_object_shape() {
		$result = blu_is_valid_input_array( array( 'foo' => 'bar' ), 'items' );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertStringContainsString( 'object-shaped', $result->get_error_message() );
	}

	/**
	 * The helper is a no-op when no patterns are supplied.
	 *
	 * @return void
	 */
	public function test_filter_terms_by_patterns_noop_when_no_patterns() {
		$terms    = array(
			array(
				'id'   => 1,
				'name' => 'Shoes',
			),
			array(
				'id'   => 2,
				'name' => 'Hats',
			),
		);
		$snapshot = $terms;
		blu_filter_terms_by_patterns( array(), $terms );
		$this->assertSame( $snapshot, $terms );
	}

	/**
	 * Plain string pattern filters terms via case-insensitive substring match (stripos).
	 *
	 * @return void
	 */
	public function test_filter_terms_by_patterns_matches_string_via_stripos() {
		$terms = array(
			array(
				'id'   => 1,
				'name' => 'Running Shoes',
			),
			array(
				'id'   => 2,
				'name' => 'Hats',
			),
			array(
				'id'   => 3,
				'name' => 'Walking shoes',
			),
		);
		blu_filter_terms_by_patterns( array( 'shoes' ), $terms );
		$names = array_values( array_map( static fn( $t ) => $t['name'], $terms ) );
		$this->assertCount( 2, $terms );
		$this->assertContains( 'Running Shoes', $names );
		$this->assertContains( 'Walking shoes', $names );
		$this->assertNotContains( 'Hats', $names );
	}

	/**
	 * Regex pattern with trailing /i applies case-insensitive matching.
	 *
	 * @return void
	 */
	public function test_filter_terms_by_patterns_matches_regex_case_insensitive() {
		$terms = array(
			array(
				'id'   => 1,
				'name' => 'Red Shoes',
			),
			array(
				'id'   => 2,
				'name' => 'Blue Hats',
			),
		);
		blu_filter_terms_by_patterns( array( '/red/i' ), $terms );
		$names = array_values( array_map( static fn( $t ) => $t['name'], $terms ) );
		$this->assertSame( array( 'Red Shoes' ), $names );
	}

	/**
	 * Regex without trailing /i still matches case-insensitively because the function appends /i.
	 *
	 * @return void
	 */
	public function test_filter_terms_by_patterns_appends_case_insensitive_flag() {
		$terms = array(
			array(
				'id'   => 1,
				'name' => 'red shoes',
			),
			array(
				'id'   => 2,
				'name' => 'BLUE HATS',
			),
		);
		blu_filter_terms_by_patterns( array( '/BLUE/' ), $terms );
		$names = array_values( array_map( static fn( $t ) => $t['name'], $terms ) );
		$this->assertSame( array( 'BLUE HATS' ), $names );
	}

	/**
	 * Terms missing id or name are skipped without causing errors.
	 *
	 * @return void
	 */
	public function test_filter_terms_by_patterns_skips_malformed_terms() {
		$terms = array(
			array( 'name' => 'No id field' ),
			array( 'id' => 5 ),
			array(
				'id'   => 6,
				'name' => 'Valid Shoes',
			),
		);
		blu_filter_terms_by_patterns( array( 'shoes' ), $terms );
		// One match was found, so filtering kicks in and only the valid term remains.
		$this->assertCount( 1, $terms );
		$remaining = array_values( $terms );
		$this->assertSame( 6, $remaining[0]['id'] );
	}

	/**
	 * When no pattern matches any term, the original list is preserved (no entries dropped).
	 *
	 * @return void
	 */
	public function test_filter_terms_by_patterns_preserves_list_when_nothing_matches() {
		$terms    = array(
			array(
				'id'   => 1,
				'name' => 'Shoes',
			),
			array(
				'id'   => 2,
				'name' => 'Hats',
			),
		);
		$snapshot = $terms;
		blu_filter_terms_by_patterns( array( 'nonexistent' ), $terms );
		$this->assertSame( $snapshot, $terms );
	}

	/**
	 * With no abilities registered under blu-mcp, the helper returns an empty list.
	 *
	 * @return void
	 */
	public function test_get_ability_by_type_returns_array() {
		$result = blu_get_ability_by_type( 'tool' );
		$this->assertIsArray( $result );
	}

	/**
	 * An unrecognised type falls back to 'tool'; the call still returns an array.
	 *
	 * @return void
	 */
	public function test_get_ability_by_type_defaults_invalid_type_to_tool() {
		$result = blu_get_ability_by_type( 'not-a-valid-type' );
		$this->assertIsArray( $result );
	}

	/**
	 * Default argument is 'tool' and the call returns an array.
	 *
	 * @return void
	 */
	public function test_get_ability_by_type_default_argument() {
		$result = blu_get_ability_by_type();
		$this->assertIsArray( $result );
	}

	/**
	 * When abilities are registered with explicit mcp.type meta and mcp.public=true,
	 * the helper returns only those matching the requested type.
	 *
	 * @return void
	 */
	public function test_get_ability_by_type_returns_matching_public_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		$this->ensure_blu_mcp_category();

		$this->register_ability_with_meta(
			'blu/test-tool-public',
			'blu-mcp',
			array(
				'mcp' => array(
					'type'   => 'tool',
					'public' => true,
				),
			)
		);
		$this->register_ability_with_meta(
			'blu/test-prompt-public',
			'blu-mcp',
			array(
				'mcp' => array(
					'type'   => 'prompt',
					'public' => true,
				),
			)
		);
		$this->register_ability_with_meta(
			'blu/test-tool-private',
			'blu-mcp',
			array(
				'mcp' => array(
					'type'   => 'tool',
					'public' => false,
				),
			)
		);
		$this->flush_ability_registrations();

		$tools = blu_get_ability_by_type( 'tool' );
		$this->assertContains( 'blu/test-tool-public', $tools );
		$this->assertNotContains( 'blu/test-prompt-public', $tools );
		$this->assertNotContains( 'blu/test-tool-private', $tools );

		$prompts = blu_get_ability_by_type( 'prompt' );
		$this->assertContains( 'blu/test-prompt-public', $prompts );
		$this->assertNotContains( 'blu/test-tool-public', $prompts );
	}

	/**
	 * Ensure the blu-mcp category is registered (mirrors McpServer bootstrap).
	 *
	 * @return void
	 */
	private function ensure_blu_mcp_category(): void {
		if ( ! class_exists( '\WP_Ability_Categories_Registry' ) ) {
			return;
		}
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
	 * Queue an ability registration to run on wp_abilities_api_init.
	 *
	 * @param string $name     Ability name.
	 * @param string $category Ability category.
	 * @param array  $meta     Ability meta.
	 *
	 * @return void
	 */
	private function register_ability_with_meta( string $name, string $category, array $meta ): void {
		$cb = function () use ( $name, $category, $meta ) {
			blu_register_ability(
				$name,
				array(
					'label'               => 'Test',
					'description'         => 'Test ability',
					'category'            => $category,
					'input_schema'        => array( 'type' => 'object' ),
					'execute_callback'    => fn() => array(),
					'permission_callback' => fn() => true,
					'meta'                => $meta,
				)
			);
		};
		add_action( 'wp_abilities_api_init', $cb, 5 );
		$this->test_ability_hooks[]   = $cb;
		$this->registered_abilities[] = $name;
	}

	/**
	 * Flush queued registrations through wp_abilities_api_init and detach the hooks.
	 *
	 * @return void
	 */
	private function flush_ability_registrations(): void {
		$abilities_init_count_before = did_action( 'wp_abilities_api_init' );
		$abilities_registry          = \WP_Abilities_Registry::get_instance();
		if (
			$abilities_registry
			&& did_action( 'wp_abilities_api_init' ) === $abilities_init_count_before
		) {
			do_action( 'wp_abilities_api_init', $abilities_registry );
		}
		foreach ( $this->test_ability_hooks as $cb ) {
			remove_action( 'wp_abilities_api_init', $cb, 5 );
		}
		$this->test_ability_hooks = array();
	}
}
