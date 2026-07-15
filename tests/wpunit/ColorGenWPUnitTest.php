<?php

namespace BLU;

use BLU\Abilities\ColorGen;
use NewfoldLabs\WP\Module\Data\HiiveConnection;

require_once __DIR__ . '/_stubs/HiiveConnectionStub.php';

/**
 * Tests for the blu/generate-color-palette ability.
 *
 * Covers: auth guard, prompt validation, key normalization, and error handling.
 *
 * @covers \BLU\Abilities\ColorGen
 */
class ColorGenWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Ability names registered during the test for cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array( 'blu/generate-color-palette' );

	/**
	 * Whether the ability has been registered in this test instance.
	 *
	 * @var bool
	 */
	private $abilities_initialized = false;

	/**
	 * Captures the last HTTP request args observed by the pre_http_request mock.
	 *
	 * @var array|null
	 */
	private $last_request_args = null;

	/**
	 * The pre_http_request filter callback, retained for tear_down removal.
	 *
	 * @var callable|null
	 */
	private $pre_http_request_filter = null;

	/**
	 * Set up: create admin user, ensure blu-mcp category, reset Hiive stub.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $user_id );

		$cat_registry = \WP_Ability_Categories_Registry::get_instance();
		if ( $cat_registry && ! $cat_registry->is_registered( 'blu-mcp' ) ) {
			$cat_registry->register(
				'blu-mcp',
				array(
					'label'       => 'Bluehost MCP',
					'description' => 'Bluehost-specific abilities for use with MCP',
				)
			);
		}

		HiiveConnection::$token  = 'test-hiive-token';
		$this->last_request_args = null;
	}

	/**
	 * Remove registered abilities and HTTP mocks.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		if ( null !== $this->pre_http_request_filter ) {
			remove_filter( 'pre_http_request', $this->pre_http_request_filter, 10 );
			$this->pre_http_request_filter = null;
		}

		$registry = \WP_Abilities_Registry::get_instance();
		if ( $registry ) {
			foreach ( $this->registered_abilities as $name ) {
				if ( $registry->is_registered( $name ) ) {
					blu_unregister_ability( $name );
				}
			}
		}
		$this->abilities_initialized = false;
		parent::tear_down();
	}

	/**
	 * Register ColorGen ability via the wp_abilities_api_init action.
	 *
	 * @return void
	 */
	private function ensure_abilities_registered(): void {
		if ( $this->abilities_initialized ) {
			return;
		}

		$cb = function () {
			new ColorGen();
		};
		add_action( 'wp_abilities_api_init', $cb, 10 );

		$init_count_before = did_action( 'wp_abilities_api_init' );
		$registry          = \WP_Abilities_Registry::get_instance();
		if ( $registry && did_action( 'wp_abilities_api_init' ) === $init_count_before ) {
			do_action( 'wp_abilities_api_init', $registry );
		}

		remove_action( 'wp_abilities_api_init', $cb, 10 );
		$this->abilities_initialized = true;
	}

	/**
	 * Install a pre_http_request mock that records the request and returns the given response.
	 *
	 * @param mixed $response Response to return (array or WP_Error).
	 * @return void
	 */
	private function mock_http_response( $response ): void {
		if ( null !== $this->pre_http_request_filter ) {
			remove_filter( 'pre_http_request', $this->pre_http_request_filter, 10 );
		}

		$this->pre_http_request_filter = function ( $preempt, $args, $url ) use ( $response ) {
			$this->last_request_args = array(
				'url'  => $url,
				'args' => $args,
			);
			return $response;
		};

		add_filter( 'pre_http_request', $this->pre_http_request_filter, 10, 3 );
	}

	/**
	 * Build a success response containing the given palettes.
	 *
	 * @param array $palettes Array of palette objects.
	 * @return array
	 */
	private function success_response( array $palettes ): array {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => wp_json_encode( $palettes ),
			'headers'  => array(),
		);
	}

	/**
	 * Execute blu/generate-color-palette with the given input.
	 *
	 * @param array $input Input to pass to the ability.
	 * @return mixed
	 */
	private function execute_generate( array $input ) {
		$this->ensure_abilities_registered();
		$ability = blu_get_ability( 'blu/generate-color-palette' );
		$this->assertNotNull( $ability, 'Ability blu/generate-color-palette should be registered.' );
		return $ability->execute( $input );
	}

	// -------------------------------------------------------------------------
	// Auth and input validation
	// -------------------------------------------------------------------------

	/**
	 * Returns 401 when the Hiive token is empty.
	 */
	public function test_generate_returns_401_when_hiive_token_missing() {
		HiiveConnection::$token = '';

		$result = $this->execute_generate( array( 'prompt' => 'calm wellness brand' ) );

		$this->assertSame( 401, $result['statusCode'] );
	}

	/**
	 * Returns 400 when prompt is empty after trimming.
	 */
	public function test_generate_returns_400_when_prompt_empty() {
		$result = $this->execute_generate( array( 'prompt' => '' ) );

		$this->assertSame( 400, $result['statusCode'] );
	}

	// -------------------------------------------------------------------------
	// Successful generation
	// -------------------------------------------------------------------------

	/**
	 * Passes prompt and locale to the API and returns 200 with palettes.
	 */
	public function test_generate_success_passes_prompt_and_returns_palettes() {
		$raw_palettes = array(
			array(
				'base'             => '#FFFFFF',
				'base_midtone'     => '#F4F4F4',
				'contrast'         => '#111111',
				'contrast_midtone' => '#323232',
				'accent_1'         => '#1A3A5C',
				'accent_2'         => '#2563EB',
				'accent_3'         => '#93C5FD',
				'accent_4'         => '#DBEAFE',
				'accent_5'         => '#EFF6FF',
				'accent_6'         => '#F8FAFF',
			),
		);
		$this->mock_http_response( $this->success_response( $raw_palettes ) );

		$result = $this->execute_generate( array( 'prompt' => 'calm wellness brand' ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertIsArray( $result['message']['palettes'] );
		$this->assertCount( 1, $result['message']['palettes'] );

		// Verify the prompt was forwarded to the API.
		$body = json_decode( $this->last_request_args['args']['body'], true );
		$this->assertSame( 'calm wellness brand', $body['prompt'] );
		$this->assertSame( 'Bearer test-hiive-token', $this->last_request_args['args']['headers']['Authorization'] );
		$this->assertStringContainsString( '/api/v1/colorgen/palettes', $this->last_request_args['url'] );
	}

	/**
	 * Locale is included in the request body.
	 */
	public function test_generate_includes_locale_in_request() {
		$this->mock_http_response(
			$this->success_response(
				array(
					array(
						'base'     => '#FFFFFF',
						'accent_1' => '#000000',
					),
				)
			)
		);

		$this->execute_generate( array( 'prompt' => 'tech startup' ) );

		$body = json_decode( $this->last_request_args['args']['body'], true );
		$this->assertArrayHasKey( 'locale', $body );
		$this->assertNotEmpty( $body['locale'] );
	}

	// -------------------------------------------------------------------------
	// Key normalization
	// -------------------------------------------------------------------------

	/**
	 * Underscore keys from the API are converted to hyphenated slug names.
	 *
	 * The API returns base_midtone, contrast_midtone, accent_1 … accent_6.
	 * The ability must convert these to base-midtone, contrast-midtone,
	 * accent-1 … accent-6 so they map directly to blu/update-global-styles slugs.
	 */
	public function test_generate_normalizes_underscore_keys_to_hyphens() {
		$raw_palette = array(
			'base'             => '#FFFFFF',
			'base_midtone'     => '#F4F4F4',
			'contrast'         => '#111111',
			'contrast_midtone' => '#323232',
			'accent_1'         => '#1A3A5C',
			'accent_2'         => '#2563EB',
			'accent_3'         => '#93C5FD',
			'accent_4'         => '#DBEAFE',
			'accent_5'         => '#EFF6FF',
			'accent_6'         => '#F8FAFF',
		);
		$this->mock_http_response( $this->success_response( array( $raw_palette ) ) );

		$result  = $this->execute_generate( array( 'prompt' => 'elegant bakery' ) );
		$palette = $result['message']['palettes'][0];

		// Underscore keys must not appear in the normalized output.
		$this->assertArrayNotHasKey( 'base_midtone', $palette );
		$this->assertArrayNotHasKey( 'contrast_midtone', $palette );
		$this->assertArrayNotHasKey( 'accent_1', $palette );
		$this->assertArrayNotHasKey( 'accent_6', $palette );

		// Hyphenated keys must be present with the original values.
		$this->assertArrayHasKey( 'base-midtone', $palette );
		$this->assertArrayHasKey( 'contrast-midtone', $palette );
		$this->assertArrayHasKey( 'accent-1', $palette );
		$this->assertArrayHasKey( 'accent-6', $palette );

		$this->assertSame( '#F4F4F4', $palette['base-midtone'] );
		$this->assertSame( '#323232', $palette['contrast-midtone'] );
		$this->assertSame( '#1A3A5C', $palette['accent-1'] );
		$this->assertSame( '#F8FAFF', $palette['accent-6'] );

		// Keys without underscores are preserved as-is.
		$this->assertSame( '#FFFFFF', $palette['base'] );
		$this->assertSame( '#111111', $palette['contrast'] );
	}

	/**
	 * Multiple palettes are all normalized.
	 */
	public function test_generate_normalizes_all_palettes_in_response() {
		$raw_palettes = array(
			array(
				'base'     => '#FFF',
				'accent_1' => '#111',
			),
			array(
				'base'     => '#000',
				'accent_1' => '#EEE',
			),
		);
		$this->mock_http_response( $this->success_response( $raw_palettes ) );

		$result = $this->execute_generate( array( 'prompt' => 'dual palette test' ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertCount( 2, $result['message']['palettes'] );

		foreach ( $result['message']['palettes'] as $palette ) {
			$this->assertArrayHasKey( 'accent-1', $palette );
			$this->assertArrayNotHasKey( 'accent_1', $palette );
		}
	}

	// -------------------------------------------------------------------------
	// Error handling
	// -------------------------------------------------------------------------

	/**
	 * Returns 504 when the HTTP request times out.
	 */
	public function test_generate_returns_504_on_timeout() {
		$this->mock_http_response(
			new \WP_Error( 'http_request_failed', 'Operation timed out after 45 seconds with 0 bytes received' )
		);

		$result = $this->execute_generate( array( 'prompt' => 'calm brand' ) );

		$this->assertSame( 504, $result['statusCode'] );
	}

	/**
	 * Returns 500 when the API response body contains no palettes.
	 */
	public function test_generate_returns_500_when_response_body_is_empty_array() {
		$this->mock_http_response(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array() ),
				'headers'  => array(),
			)
		);

		$result = $this->execute_generate( array( 'prompt' => 'calm brand' ) );

		$this->assertSame( 500, $result['statusCode'] );
	}

	/**
	 * Forwards non-2xx HTTP status codes from the API.
	 */
	public function test_generate_forwards_non_2xx_status_code() {
		$this->mock_http_response(
			array(
				'response' => array( 'code' => 503 ),
				'body'     => '',
				'headers'  => array(),
			)
		);

		$result = $this->execute_generate( array( 'prompt' => 'calm brand' ) );

		$this->assertSame( 503, $result['statusCode'] );
	}

	/**
	 * Prompt longer than 1000 characters is truncated before being sent to the API.
	 */
	public function test_generate_truncates_prompt_at_1000_chars() {
		$long_prompt = str_repeat( 'x', 1500 );
		$this->mock_http_response(
			$this->success_response(
				array(
					array(
						'base'     => '#FFF',
						'accent_1' => '#000',
					),
				)
			)
		);

		$result = $this->execute_generate( array( 'prompt' => $long_prompt ) );

		$this->assertSame( 200, $result['statusCode'] );

		$body = json_decode( $this->last_request_args['args']['body'], true );
		$this->assertSame( 1000, strlen( $body['prompt'] ) );
	}
}
