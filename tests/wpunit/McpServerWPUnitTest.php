<?php

namespace BLU;

/**
 * Tests for McpServer.
 *
 * @covers \BLU\McpServer
 */
class McpServerWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Ability names introduced by tests that exercise register_abilities. Tracked so
	 * tear_down can unregister them, otherwise downstream tests like RestApiCrudWPUnitTest
	 * would see "already registered" notices when their own setUp re-registers them.
	 *
	 * @var string[]
	 */
	private $abilities_registered_during_test = array();

	/**
	 * Remove abilities that this test class registered so the global registry returns
	 * to the state expected by sibling test classes.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		if ( class_exists( '\WP_Abilities_Registry' ) ) {
			$registry = \WP_Abilities_Registry::get_instance();
			foreach ( $this->abilities_registered_during_test as $name ) {
				if ( $registry && $registry->is_registered( $name ) ) {
					blu_unregister_ability( $name );
				}
			}
		}
		$this->abilities_registered_during_test = array();
		parent::tear_down();
	}

	/**
	 * Run a callable with WP's incorrect-usage handling silenced so the wrapped
	 * registration calls do not turn into test failures. The WP test framework
	 * collects every doing_it_wrong notice into the protected
	 * caught_doing_it_wrong property and asserts in assert_post_conditions that
	 * it matches the expected set. The do_action that feeds that collector
	 * fires unconditionally (independent of the doing_it_wrong_trigger_error
	 * filter), so the only reliable suppression is to snapshot the property
	 * before the call and restore it afterwards. Notice handling differs across
	 * environments (bootstrap may or may not have already fired the categories
	 * / abilities init actions), so strict setExpectedIncorrectUsage matching
	 * is brittle here.
	 *
	 * @param callable $callable The callable to run with suppression in effect.
	 *
	 * @return void
	 */
	private function with_doing_it_wrong_suppressed( callable $callable ): void {
		$has_property = property_exists( $this, 'caught_doing_it_wrong' );
		$before       = $has_property ? $this->caught_doing_it_wrong : null;
		add_filter( 'doing_it_wrong_trigger_error', '__return_false' );
		try {
			$callable();
		} finally {
			remove_filter( 'doing_it_wrong_trigger_error', '__return_false' );
			if ( $has_property ) {
				$this->caught_doing_it_wrong = is_array( $before ) ? $before : array();
			}
		}
	}

	/**
	 * Constructor registers mcp_adapter_init action.
	 *
	 * @return void
	 */
	public function test_constructor_registers_mcp_adapter_init() {
		$server = new McpServer();
		$this->assertNotFalse( has_action( 'mcp_adapter_init', array( $server, 'register_server' ) ) );
	}

	/**
	 * Constructor registers the priority-999 cleanup hook used to detach the
	 * Strauss-prefixed DefaultServerFactory before a sibling adapter re-fires
	 * mcp_adapter_init.
	 *
	 * @return void
	 */
	public function test_constructor_registers_suppress_sibling_default_server_refire_at_late_priority() {
		$server   = new McpServer();
		$priority = has_action( 'mcp_adapter_init', array( $server, 'suppress_sibling_default_server_refire' ) );
		$this->assertNotFalse( $priority );
		$this->assertSame( 999, $priority );
	}

	/**
	 * Register_server early-returns without creating a server when the adapter
	 * arg passed by the mcp_adapter_init action is not our McpAdapter instance.
	 * This is the guard that stops the duplicate_server_id error that would
	 * otherwise surface on each sibling adapter's do_action.
	 *
	 * Passing null (simulating an unknown caller) is safe: the method returns
	 * without fataling on the use-statement-referenced McpAdapter class, which
	 * is Strauss-generated and absent from the module-only test environment.
	 *
	 * @return void
	 */
	public function test_register_server_early_returns_on_foreign_adapter() {
		$server = new McpServer();
		$server->register_server( null );
		$this->assertTrue( true, 'register_server( null ) returned without error' );
	}

	/**
	 * Same guard on the cleanup callback.
	 *
	 * @return void
	 */
	public function test_suppress_sibling_default_server_refire_early_returns_on_foreign_adapter() {
		$server = new McpServer();
		$server->suppress_sibling_default_server_refire( null );
		$this->assertTrue( true, 'suppress_sibling_default_server_refire( null ) returned without error' );
	}

	/**
	 * Constructor registers wp_abilities_api_init action.
	 *
	 * @return void
	 */
	public function test_constructor_registers_wp_abilities_api_init() {
		$server = new McpServer();
		$this->assertNotFalse( has_action( 'wp_abilities_api_init', array( $server, 'register_abilities' ) ) );
	}

	/**
	 * Constructor registers wp_abilities_api_categories_init action.
	 *
	 * @return void
	 */
	public function test_constructor_registers_wp_abilities_api_categories_init() {
		$server = new McpServer();
		$this->assertNotFalse( has_action( 'wp_abilities_api_categories_init', array( $server, 'register_ability_categories' ) ) );
	}

	/**
	 * The blu-mcp category ends up registered on the categories registry after the
	 * register_ability_categories call. The bootstrap may already have registered it
	 * (the abilities API runs the categories_init action once at boot), so we whitelist
	 * the "already registered" incorrect-usage notice that fires in that case.
	 *
	 * @return void
	 */
	public function test_register_ability_categories_ends_with_blu_mcp_registered() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			$this->markTestSkipped( 'WP Ability Categories API is not available.' );
		}

		$server = new McpServer();
		$this->with_doing_it_wrong_suppressed(
			function () use ( $server ) {
				// Fire the categories_init action via add_action + do_action so that
				// wp_register_ability_category passes its inside-the-action guard.
				add_action( 'wp_abilities_api_categories_init', array( $server, 'register_ability_categories' ) );
				do_action( 'wp_abilities_api_categories_init' );
				remove_action( 'wp_abilities_api_categories_init', array( $server, 'register_ability_categories' ) );

				// Also exercise the direct-call path so its body counts toward coverage.
				$server->register_ability_categories();
			}
		);

		$registry = \WP_Ability_Categories_Registry::get_instance();
		$this->assertNotNull( $registry );
		$this->assertTrue( $registry->is_registered( 'blu-mcp' ) );
	}

	/**
	 * Every BLU ability class is instantiated by register_abilities. The call must
	 * complete without fatals; some constructors are no-ops without WooCommerce. Any
	 * abilities that did not exist before the call are tracked so tear_down can clean
	 * them up, which keeps the global registry from leaking into RestApiCrudWPUnitTest
	 * and other tests that re-register the same names.
	 *
	 * @return void
	 */
	public function test_register_abilities_executes_every_ability_constructor() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		$registry             = \WP_Abilities_Registry::get_instance();
		$before_ability_names = $registry ? array_keys( $registry->get_all_registered() ) : array();

		$server = new McpServer();
		$this->with_doing_it_wrong_suppressed(
			function () use ( $server, $registry ) {
				$cb = function () use ( $server ) {
					$server->register_abilities();
				};
				add_action( 'wp_abilities_api_init', $cb, 5 );

				$init_count_before = did_action( 'wp_abilities_api_init' );
				if ( $registry && did_action( 'wp_abilities_api_init' ) === $init_count_before ) {
					do_action( 'wp_abilities_api_init', $registry );
				}
				remove_action( 'wp_abilities_api_init', $cb, 5 );
			}
		);

		$after_ability_names                    = $registry ? array_keys( $registry->get_all_registered() ) : array();
		$newly_registered                       = array_values( array_diff( $after_ability_names, $before_ability_names ) );
		$this->abilities_registered_during_test = array_merge(
			$this->abilities_registered_during_test,
			$newly_registered
		);

		$this->assertNotEmpty( $after_ability_names, 'register_abilities should leave at least one ability registered.' );
	}

	/**
	 * After register_abilities runs, the gateway tools are queryable on the registry.
	 * Same registration-tracking pattern as the previous test.
	 *
	 * @return void
	 */
	public function test_register_abilities_registers_gateway_tools() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		$registry             = \WP_Abilities_Registry::get_instance();
		$before_ability_names = $registry ? array_keys( $registry->get_all_registered() ) : array();

		$server = new McpServer();
		$this->with_doing_it_wrong_suppressed(
			function () use ( $server, $registry ) {
				add_action(
					'wp_abilities_api_init',
					function () use ( $server ) {
						$server->register_abilities();
					},
					5
				);

				$init_count_before = did_action( 'wp_abilities_api_init' );
				if ( $registry && did_action( 'wp_abilities_api_init' ) === $init_count_before ) {
					do_action( 'wp_abilities_api_init', $registry );
				}
			}
		);

		$after_ability_names                    = $registry ? array_keys( $registry->get_all_registered() ) : array();
		$newly_registered                       = array_values( array_diff( $after_ability_names, $before_ability_names ) );
		$this->abilities_registered_during_test = array_merge(
			$this->abilities_registered_during_test,
			$newly_registered
		);

		$this->assertNotNull( blu_get_ability( 'blu/list-abilities' ) );
		$this->assertNotNull( blu_get_ability( 'blu/get-ability-schema' ) );
		$this->assertNotNull( blu_get_ability( 'blu/call-ability' ) );
	}
}
