<?php

namespace BLU;

use BLU\Abilities\LogoGen;
use NewfoldLabs\WP\Module\Data\HiiveConnection;

require_once __DIR__ . '/_stubs/HiiveConnectionStub.php';

/**
 * Tests for LogoGen abilities.
 *
 * @covers \BLU\Abilities\LogoGen
 */
class LogoGenWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Ability names registered during the test for cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array( 'blu/regenerate-logo', 'blu/set-logo-from-image' );

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
	 * Last request targeting the logo API (POST), for assertions after sideload download.
	 *
	 * @var array|null
	 */
	private $last_logo_request_args = null;

	/**
	 * The pre_http_request filter callback installed by mock_http_response,
	 * retained so tear_down can remove only this specific filter.
	 *
	 * @var callable|null
	 */
	private $pre_http_request_filter = null;

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

		HiiveConnection::$token       = 'test-hiive-token';
		$this->last_request_args      = null;
		$this->last_logo_request_args = null;
	}

	/**
	 * Remove abilities registered by these tests and clear HTTP mocks.
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
	 * Register LogoGen ability via the wp_abilities_api_init action.
	 *
	 * @return void
	 */
	private function ensure_abilities_registered(): void {
		if ( $this->abilities_initialized ) {
			return;
		}

		$cb = function () {
			new LogoGen();
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
	 * Mock AI logo POST returning a CDN URL, then CDN GET returning a minimal PNG for sideload.
	 *
	 * @return void
	 */
	private function mock_logo_api_and_cdn_png(): void {
		if ( null !== $this->pre_http_request_filter ) {
			remove_filter( 'pre_http_request', $this->pre_http_request_filter, 10 );
		}

		$cdn_url = 'https://cdn.example.com/logo.png';
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- test fixture bytes only.
		$png_body = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );

		$this->pre_http_request_filter = function ( $preempt, $args, $url ) use ( $cdn_url, $png_body ) {
			$this->last_request_args = array(
				'url'  => $url,
				'args' => $args,
			);

			if ( false !== strpos( $url, '/api/v1/imagegen/logo' ) ) {
				$this->last_logo_request_args = $this->last_request_args;

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( array( 'url' => $cdn_url ) ),
					'headers'  => array(),
				);
			}

			if ( false !== strpos( $url, 'cdn.example.com' ) ) {
				// download_url() uses stream => true + filename; pre_http_request short-circuit
				// does not copy body to the temp file unless we write it here (see WP_Http::request).
				$response = array(
					'response' => array( 'code' => 200 ),
					'body'     => $png_body,
					'headers'  => array(
						'content-type' => 'image/png',
					),
					'cookies'  => array(),
				);

				if ( ! empty( $args['stream'] ) && ! empty( $args['filename'] ) && is_string( $args['filename'] ) ) {
					$dirname = dirname( $args['filename'] );
					if ( '' !== $dirname && wp_mkdir_p( $dirname ) && wp_is_writable( $dirname ) ) {
						file_put_contents( $args['filename'], $png_body );
						$response['body'] = '';
					}
				}

				return $response;
			}

			return false;
		};

		add_filter( 'pre_http_request', $this->pre_http_request_filter, 10, 3 );
	}

	/**
	 * Helper: execute blu/regenerate-logo with input.
	 *
	 * @param array $input Input to pass to blu/regenerate-logo.
	 * @return mixed
	 */
	private function execute_generate( array $input ) {
		$this->ensure_abilities_registered();

		$ability = blu_get_ability( 'blu/regenerate-logo' );
		$this->assertNotNull( $ability, 'Ability blu/regenerate-logo should be registered.' );

		return $ability->execute( $input );
	}

	/**
	 * Verifies a 401 is returned when the Hiive auth token is empty.
	 */
	public function test_generate_returns_401_when_hiive_token_missing() {
		HiiveConnection::$token = '';

		$result = $this->execute_generate( array( 'prompt' => 'A test logo' ) );

		$this->assertSame( 401, $result['statusCode'] );
	}

	/**
	 * Verifies a successful flow POSTs to the logo API, sideloads the CDN image, and sets site_logo.
	 */
	public function test_generate_success_sideloads_and_sets_site_logo() {
		delete_option( 'site_logo' );

		$this->mock_logo_api_and_cdn_png();

		$result = $this->execute_generate(
			array(
				'prompt'       => 'A test logo',
				'subject_name' => 'Acme Co',
				'style'        => 'wordmark',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'Site logo updated.', $result['message']['message'] );
		$this->assertIsInt( $result['message']['attachment_id'] );
		$this->assertGreaterThan( 0, $result['message']['attachment_id'] );
		$this->assertNotEmpty( $result['message']['url'] );

		$this->assertSame( (int) $result['message']['attachment_id'], (int) get_option( 'site_logo' ) );

		$this->assertNotNull( $this->last_logo_request_args );
		$body = json_decode( $this->last_logo_request_args['args']['body'], true );
		$this->assertSame( 'A test logo', $body['prompt'] );
		$this->assertSame( 'Acme Co', $body['subject_name'] );
		$this->assertSame( 'wordmark', $body['style'] );
		$this->assertSame( 'Bearer test-hiive-token', $this->last_logo_request_args['args']['headers']['Authorization'] );
	}

	/**
	 * Verifies the schema rejects prompts longer than 1000 characters.
	 */
	public function test_generate_rejects_overlong_prompt() {
		$result = $this->execute_generate( array( 'prompt' => str_repeat( 'x', 1500 ) ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
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
