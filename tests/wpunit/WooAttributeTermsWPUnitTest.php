<?php

namespace BLU;

use BLU\Abilities\WooAttributeTerms;

/**
 * Tests the single WooCommerce attribute and terms workflow against REST routes.
 *
 * @covers \BLU\Abilities\WooAttributeTerms
 */
class WooAttributeTermsWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {
	/**
	 * Previous REST server.
	 *
	 * @var \WP_REST_Server|null
	 */
	private $old_rest_server;
	/**
	 * Number of attribute create calls.
	 *
	 * @var int
	 */
	private $attribute_calls = 0;
	/**
	 * Term names submitted to the REST controller.
	 *
	 * @var string[]
	 */
	private $term_calls      = array();
	/**
	 * Whether the term route denies permission.
	 *
	 * @var bool
	 */
	private $deny_terms      = false;
	/**
	 * Term name that should fail.
	 *
	 * @var string
	 */
	private $fail_term       = '';

	/** Set up a fresh REST server and an administrator. */
	public function set_up(): void {
		parent::set_up();
		$this->old_rest_server     = $GLOBALS['wp_rest_server'] ?? null;
		$GLOBALS['wp_rest_server'] = new \WP_REST_Server();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$categories = \WP_Ability_Categories_Registry::get_instance();
		if ( ! $categories->is_registered( 'blu-mcp' ) ) {
			$categories->register(
				'blu-mcp',
				array(
					'label'       => 'Bluehost MCP',
					'description' => 'Bluehost MCP abilities',
				)
			);
		}
	}

	/** Register the ability on its required WordPress action. */
	private function register_workflow(): void {
		$callback = function () {
			new WooAttributeTerms();
		};
		add_action( 'wp_abilities_api_init', $callback );
		do_action( 'wp_abilities_api_init' );
		remove_action( 'wp_abilities_api_init', $callback );
	}

	/** Restore the previous REST server and remove the test ability. */
	public function tear_down(): void {
		if ( \WP_Abilities_Registry::get_instance()->is_registered( 'blu/wc-add-product-attribute' ) ) {
			blu_unregister_ability( 'blu/wc-add-product-attribute' );
		}
		$GLOBALS['wp_rest_server'] = $this->old_rest_server;
		parent::tear_down();
	}

	/**
	 * Register minimal WooCommerce-compatible test routes.
	 *
	 * @param bool $with_terms Whether to register the terms route.
	 */
	private function register_routes( bool $with_terms = true ): void {
		rest_get_server()->register_route(
			'wc/v3',
			'/wc/v3/products/attributes',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => function ( $request ) {
						++$this->attribute_calls;
						if ( 'Rejected' === $request['name'] ) {
							return new \WP_Error( 'woo_rejected', 'Attribute rejected', array( 'status' => 400 ) );
						}
						return new \WP_REST_Response(
							array(
								'id'   => 42,
								'name' => $request['name'],
								'slug' => 'pa_color',
							),
							201
						);
					},
					'permission_callback' => function () {
								return current_user_can( 'manage_options' ); },
				),
			)
		);
		if ( ! $with_terms ) {
			return;
		}
		rest_get_server()->register_route(
			'wc/v3',
			'/wc/v3/products/attributes/(?P<attribute_id>[\\d]+)/terms',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => function ( $request ) {
						$this->term_calls[] = $request['name'];
						if ( ! taxonomy_exists( 'pa_color' ) ) {
							return new \WP_Error( 'missing_taxonomy', 'Taxonomy not registered', array( 'status' => 404 ) );
						}
						if ( $this->fail_term === $request['name'] ) {
							return new \WP_Error( 'term_rejected', 'Term rejected', array( 'status' => 400 ) );
						}
						return new \WP_REST_Response(
							array(
								'id'   => count( $this->term_calls ),
								'name' => $request['name'],
							),
							201
						);
					},
					'permission_callback' => function () {
								return ! $this->deny_terms; },
				),
			)
		);
	}

	/** Ensure the workflow is unavailable when its terms route is absent. */
	public function test_requires_both_native_routes(): void {
		$this->register_routes( false );
		$this->register_workflow();
		$this->assertFalse( \WP_Abilities_Registry::get_instance()->is_registered( 'blu/wc-add-product-attribute' ) );
	}

	/** Ensure both writes use REST and report their created resources. */
	public function test_creates_attribute_and_terms_through_native_rest_permissions(): void {
		$this->register_routes();
		$this->register_workflow();
		$this->assertNotNull( blu_get_ability( 'blu/wc-add-product-attribute' ) );
		$result = blu_get_ability( 'blu/wc-add-product-attribute' )->execute(
			array(
				'name'  => 'Color',
				'terms' => array( 'Red', 'Blue' ),
			)
		);
		$this->assertSame( 'success', $result['status'] );
		$this->assertSame( 42, $result['message']['attribute']['id'] );
		$this->assertSame( array( 'Red', 'Blue' ), $this->term_calls );
		$this->assertCount( 2, $result['message']['terms']['created'] );
		$this->assertSame( array(), $result['message']['terms']['failed'] );
	}

	/** Ensure attribute creation failure stops the workflow. */
	public function test_attribute_failure_stops_without_creating_terms(): void {
		$this->register_routes();
		$this->register_workflow();
		$result = blu_get_ability( 'blu/wc-add-product-attribute' )->execute(
			array(
				'name'  => 'Rejected',
				'terms' => array( 'Red' ),
			)
		);
		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( 400, $result['statusCode'] );
		$this->assertSame( array(), $this->term_calls );
	}

	/** Ensure the native attribute route can deny a caller the ability allowed through. */
	public function test_attribute_rest_permission_denial_stops_without_creating_terms(): void {
		$this->register_routes();
		$this->register_workflow();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$result = blu_get_ability( 'blu/wc-add-product-attribute' )->execute(
			array(
				'name'  => 'Color',
				'terms' => array( 'Red' ),
			)
		);
		$this->assertSame( 'error', $result['status'] );
		$this->assertSame( 403, $result['statusCode'] );
		$this->assertSame( 0, $this->attribute_calls );
		$this->assertSame( array(), $this->term_calls );
	}

	/** Ensure one failed term cannot be mistaken for complete success. */
	public function test_partial_term_failure_reports_created_attribute_and_failed_term(): void {
		$this->register_routes();
		$this->fail_term = 'Blue';
		$this->register_workflow();
		$result = blu_get_ability( 'blu/wc-add-product-attribute' )->execute(
			array(
				'name'  => 'Color',
				'terms' => array( 'Red', 'Blue' ),
			)
		);
		$this->assertSame( 'partial', $result['status'] );
		$this->assertSame( 42, $result['message']['attribute']['id'] );
		$this->assertCount( 1, $result['message']['terms']['created'] );
		$this->assertSame( 'Blue', $result['message']['terms']['failed'][0]['name'] );
	}

	/** Ensure the native terms permission check remains in force. */
	public function test_term_permission_denial_is_reported_as_partial(): void {
		$this->register_routes();
		$this->deny_terms = true;
		$this->register_workflow();
		$result = blu_get_ability( 'blu/wc-add-product-attribute' )->execute(
			array(
				'name'  => 'Color',
				'terms' => array( 'Red' ),
			)
		);
		$this->assertSame( 'partial', $result['status'] );
		$this->assertSame( 403, $result['message']['terms']['failed'][0]['statusCode'] );
		$this->assertSame( array(), $this->term_calls );
	}
}
