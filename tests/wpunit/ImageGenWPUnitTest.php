<?php

namespace BLU;

use BLU\Abilities\ImageGen;
use NewfoldLabs\WP\Module\Data\HiiveConnection;

require_once __DIR__ . '/_stubs/HiiveConnectionStub.php';

/**
 * Tests for ImageGen abilities.
 *
 * @covers \BLU\Abilities\ImageGen
 */
class ImageGenWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Ability names registered during the test for cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array( 'blu/generate-image' );

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
	 * Skip if Abilities API is unavailable, set up admin user, ensure blu-mcp category,
	 * and reset the HiiveConnection stub token between tests.
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
	 * Remove abilities registered by these tests and clear HTTP mocks.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );

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
	 * Register ImageGen ability via the wp_abilities_api_init action.
	 *
	 * @return void
	 */
	private function ensure_abilities_registered(): void {
		if ( $this->abilities_initialized ) {
			return;
		}

		$cb = function () {
			new ImageGen();
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
		add_filter(
			'pre_http_request',
			function ( $preempt, $args, $url ) use ( $response ) {
				$this->last_request_args = array(
					'url'  => $url,
					'args' => $args,
				);
				return $response;
			},
			10,
			3
		);
	}

	/**
	 * Helper: execute blu/generate-image with input.
	 *
	 * @param array $input Input to pass to blu/generate-image.
	 * @return mixed
	 */
	private function execute_generate( array $input ) {
		$this->ensure_abilities_registered();

		$ability = blu_get_ability( 'blu/generate-image' );
		$this->assertNotNull( $ability, 'Ability blu/generate-image should be registered.' );

		return $ability->execute( $input );
	}

	/**
	 * Verifies a 401 is returned when the Hiive auth token is empty.
	 */
	public function test_generate_returns_401_when_hiive_token_missing() {
		HiiveConnection::$token = '';

		$result = $this->execute_generate( array( 'prompt' => 'A test image' ) );

		$this->assertSame( 401, $result['statusCode'] );
	}

	/**
	 * Verifies a successful response forwards the prompt and returns the CDN URL.
	 */
	public function test_generate_success_returns_url() {
		$this->mock_http_response(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array( 'url' => 'https://cdn.example.com/img.jpg' ) ),
				'headers'  => array(),
			)
		);

		$result = $this->execute_generate(
			array(
				'prompt' => 'A test image',
				'width'  => 800,
				'height' => 600,
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'https://cdn.example.com/img.jpg', $result['message']['url'] );

		$body = json_decode( $this->last_request_args['args']['body'], true );
		$this->assertSame( 'A test image', $body['prompt'] );
		$this->assertSame( 800, $body['width'] );
		$this->assertSame( 600, $body['height'] );
		$this->assertSame( 'Bearer test-hiive-token', $this->last_request_args['args']['headers']['Authorization'] );
	}

	/**
	 * Verifies width/height are clamped to the documented maxima.
	 */
	public function test_generate_clamps_oversized_dimensions() {
		$this->mock_http_response(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array( 'url' => 'https://cdn.example.com/img.jpg' ) ),
				'headers'  => array(),
			)
		);

		$this->execute_generate(
			array(
				'prompt' => 'A test image',
				'width'  => 9999,
				'height' => 9999,
			)
		);

		$body = json_decode( $this->last_request_args['args']['body'], true );
		$this->assertSame( 1920, $body['width'] );
		$this->assertSame( 1080, $body['height'] );
	}

	/**
	 * Verifies the prompt is truncated to 1000 characters before transport.
	 */
	public function test_generate_truncates_long_prompt() {
		$this->mock_http_response(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array( 'url' => 'https://cdn.example.com/img.jpg' ) ),
				'headers'  => array(),
			)
		);

		$this->execute_generate( array( 'prompt' => str_repeat( 'x', 1500 ) ) );

		$body = json_decode( $this->last_request_args['args']['body'], true );
		$this->assertSame( 1000, strlen( $body['prompt'] ) );
	}

	/**
	 * Verifies a cURL timeout from wp_remote_post produces a 504 response.
	 */
	public function test_generate_handles_timeout() {
		$this->mock_http_response( new \WP_Error( 'http_request_failed', 'Operation timed out after 90 seconds' ) );

		$result = $this->execute_generate( array( 'prompt' => 'A test image' ) );

		$this->assertSame( 504, $result['statusCode'] );
	}

	/**
	 * Verifies a generic WP_Error response is surfaced as a 502.
	 */
	public function test_generate_handles_transport_error() {
		$this->mock_http_response( new \WP_Error( 'http_request_failed', 'connection refused' ) );

		$result = $this->execute_generate( array( 'prompt' => 'A test image' ) );

		$this->assertSame( 502, $result['statusCode'] );
	}

	/**
	 * Verifies a non-2xx HTTP response is surfaced with its status code.
	 */
	public function test_generate_handles_error_status() {
		$this->mock_http_response(
			array(
				'response' => array( 'code' => 503 ),
				'body'     => '',
				'headers'  => array(),
			)
		);

		$result = $this->execute_generate( array( 'prompt' => 'A test image' ) );

		$this->assertSame( 503, $result['statusCode'] );
	}

	/**
	 * Verifies a 200 response missing a `url` field returns a 500 error.
	 */
	public function test_generate_handles_missing_url_in_response() {
		$this->mock_http_response(
			array(
				'response' => array( 'code' => 200 ),
				'body'     => wp_json_encode( array( 'foo' => 'bar' ) ),
				'headers'  => array(),
			)
		);

		$result = $this->execute_generate( array( 'prompt' => 'A test image' ) );

		$this->assertSame( 500, $result['statusCode'] );
	}
}
