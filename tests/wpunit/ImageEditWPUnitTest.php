<?php

namespace BLU;

use BLU\Abilities\ImageEdit;
use NewfoldLabs\WP\Module\Data\HiiveConnection;

require_once __DIR__ . '/_stubs/HiiveConnectionStub.php';

/**
 * Tests for ImageEdit abilities.
 *
 * @covers \BLU\Abilities\ImageEdit
 */
class ImageEditWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Ability names registered during the test for cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array( 'blu/edit-image' );

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
	 * Last request targeting the image edit API (POST).
	 *
	 * @var array|null
	 */
	private $last_edit_request_args = null;

	/**
	 * The pre_http_request filter callback installed by HTTP mocks.
	 *
	 * @var callable|null
	 */
	private $pre_http_request_filter = null;

	/**
	 * Minimal valid PNG bytes for mocked source image responses.
	 *
	 * @var string
	 */
	private $png_body;

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
		$this->last_edit_request_args = null;
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- test fixture bytes only.
		$this->png_body = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
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
	 * Register ImageEdit ability via the wp_abilities_api_init action.
	 *
	 * @return void
	 */
	private function ensure_abilities_registered(): void {
		if ( $this->abilities_initialized ) {
			return;
		}

		$cb = function () {
			new ImageEdit();
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
	 * Mock source-image GET and edit API POST responses.
	 *
	 * @param string $edited_url URL returned by the edit API.
	 * @return void
	 */
	private function mock_source_and_edit_api( string $edited_url = 'https://hiive.cloud/cdn-cgi/image/edited.png' ): void {
		if ( null !== $this->pre_http_request_filter ) {
			remove_filter( 'pre_http_request', $this->pre_http_request_filter, 10 );
		}

		$this->pre_http_request_filter = function ( $preempt, $args, $url ) use ( $edited_url ) {
			$this->last_request_args = array(
				'url'  => $url,
				'args' => $args,
			);

			if ( false !== strpos( $url, '/api/v1/imagegen/edit' ) ) {
				$this->last_edit_request_args = $this->last_request_args;

				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( array( 'url' => $edited_url ) ),
					'headers'  => array(),
				);
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => $this->png_body,
				'headers'  => array(
					'content-type' => 'image/png',
				),
			);
		};

		add_filter( 'pre_http_request', $this->pre_http_request_filter, 10, 3 );
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
	 * Helper: execute blu/edit-image with input.
	 *
	 * @param array $input Input to pass to blu/edit-image.
	 * @return mixed
	 */
	private function execute_edit( array $input ) {
		$this->ensure_abilities_registered();

		$ability = blu_get_ability( 'blu/edit-image' );
		$this->assertNotNull( $ability, 'Ability blu/edit-image should be registered.' );

		return $ability->execute( $input );
	}

	/**
	 * Verifies a 400 is returned when source_url is missing or invalid.
	 */
	public function test_edit_returns_400_when_source_url_invalid() {
		$result = $this->execute_edit(
			array(
				'prompt'     => 'Make the sky purple',
				'source_url' => 'not-a-url',
			)
		);

		$this->assertSame( 400, $result['statusCode'] );
		$this->assertSame( 'A valid source_url is required.', $result['message'] );
	}

	/**
	 * Verifies disallowed external hosts are rejected.
	 */
	public function test_edit_returns_400_when_source_url_not_allowed() {
		$result = $this->execute_edit(
			array(
				'prompt'     => 'Make the sky purple',
				'source_url' => 'https://evil.example.com/photo.png',
			)
		);

		$this->assertSame( 400, $result['statusCode'] );
		$this->assertSame( 'source_url is not allowed.', $result['message'] );
	}

	/**
	 * Verifies a 401 is returned when the Hiive auth token is empty.
	 */
	public function test_edit_returns_401_when_hiive_token_missing() {
		HiiveConnection::$token = '';
		$this->mock_source_and_edit_api();

		$result = $this->execute_edit(
			array(
				'prompt'     => 'Make the sky purple',
				'source_url' => 'https://images.unsplash.com/photo-test.png',
			)
		);

		$this->assertSame( 401, $result['statusCode'] );
	}

	/**
	 * Verifies a successful Unsplash edit returns the CDN URL and multipart payload.
	 */
	public function test_edit_success_with_unsplash_source_returns_url() {
		$this->mock_source_and_edit_api( 'https://hiive.cloud/cdn-cgi/image/edited.png' );

		$result = $this->execute_edit(
			array(
				'prompt'      => 'Make the sky purple',
				'source_url'  => 'https://images.unsplash.com/photo-test.png',
				'orientation' => 'square',
				'background'  => 'transparent',
				'trim'        => true,
				'width'       => 800,
				'height'      => 600,
				'quality'     => 90,
				'fit'         => 'cover',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( 'https://hiive.cloud/cdn-cgi/image/edited.png', $result['message']['url'] );

		$this->assertNotNull( $this->last_edit_request_args );
		$this->assertStringContainsString( '/api/v1/imagegen/edit', $this->last_edit_request_args['url'] );
		$this->assertSame( 'Bearer test-hiive-token', $this->last_edit_request_args['args']['headers']['Authorization'] );
		$this->assertStringContainsString( 'multipart/form-data', $this->last_edit_request_args['args']['headers']['Content-Type'] );

		$body = $this->last_edit_request_args['args']['body'];
		$this->assertStringContainsString( 'name="prompt"', $body );
		$this->assertStringContainsString( 'Make the sky purple', $body );
		$this->assertStringContainsString( 'name="image[]"', $body );
		$this->assertStringContainsString( 'name="orientation"', $body );
		$this->assertStringContainsString( 'square', $body );
		$this->assertStringContainsString( 'name="background"', $body );
		$this->assertStringContainsString( 'transparent', $body );
		$this->assertStringContainsString( 'name="trim"', $body );
		$this->assertStringContainsString( '1', $body );
	}

	/**
	 * Verifies hiive.cloud source URLs are allowed.
	 */
	public function test_edit_allows_hiive_cloud_source_url() {
		$this->mock_source_and_edit_api();

		$result = $this->execute_edit(
			array(
				'prompt'     => 'Brighten the image',
				'source_url' => 'https://hiive.cloud/cdn-cgi/image/source.png',
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
	}

	/**
	 * Verifies same-site source URLs are allowed.
	 */
	public function test_edit_allows_local_site_source_url() {
		$this->mock_source_and_edit_api();

		$result = $this->execute_edit(
			array(
				'prompt'     => 'Brighten the image',
				'source_url' => home_url( '/wp-content/uploads/2024/test.png' ),
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
	}

	/**
	 * Verifies source fetch HTTP errors are surfaced as 400.
	 */
	public function test_edit_handles_source_fetch_http_error() {
		$this->mock_http_response(
			array(
				'response' => array( 'code' => 404 ),
				'body'     => '',
				'headers'  => array(),
			)
		);

		$result = $this->execute_edit(
			array(
				'prompt'     => 'Make the sky purple',
				'source_url' => 'https://images.unsplash.com/missing.png',
			)
		);

		$this->assertSame( 400, $result['statusCode'] );
		$this->assertStringContainsString( 'Unable to fetch source image', $result['message'] );
	}

	/**
	 * Verifies unsupported source content types are rejected.
	 */
	public function test_edit_rejects_unsupported_source_mime_type() {
		if ( null !== $this->pre_http_request_filter ) {
			remove_filter( 'pre_http_request', $this->pre_http_request_filter, 10 );
		}

		$this->pre_http_request_filter = function ( $preempt, $args, $url ) {
			$this->last_request_args = array(
				'url'  => $url,
				'args' => $args,
			);

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => 'not-an-image',
				'headers'  => array(
					'content-type' => 'application/pdf',
				),
			);
		};

		add_filter( 'pre_http_request', $this->pre_http_request_filter, 10, 3 );

		$result = $this->execute_edit(
			array(
				'prompt'     => 'Make the sky purple',
				'source_url' => 'https://images.unsplash.com/document.pdf',
			)
		);

		$this->assertSame( 400, $result['statusCode'] );
		$this->assertSame( 'Unsupported source image type.', $result['message'] );
	}

	/**
	 * Verifies a cURL timeout on the edit POST produces a 504 response.
	 */
	public function test_edit_handles_edit_timeout() {
		if ( null !== $this->pre_http_request_filter ) {
			remove_filter( 'pre_http_request', $this->pre_http_request_filter, 10 );
		}

		$this->pre_http_request_filter = function ( $preempt, $args, $url ) {
			$this->last_request_args = array(
				'url'  => $url,
				'args' => $args,
			);

			if ( false !== strpos( $url, '/api/v1/imagegen/edit' ) ) {
				return new \WP_Error( 'http_request_failed', 'Operation timed out after 120 seconds with 0 bytes received cURL error 28' );
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => $this->png_body,
				'headers'  => array(
					'content-type' => 'image/png',
				),
			);
		};

		add_filter( 'pre_http_request', $this->pre_http_request_filter, 10, 3 );

		$result = $this->execute_edit(
			array(
				'prompt'     => 'Make the sky purple',
				'source_url' => 'https://images.unsplash.com/photo-test.png',
			)
		);

		$this->assertSame( 504, $result['statusCode'] );
	}

	/**
	 * Verifies a generic transport error on edit POST is surfaced as 502.
	 */
	public function test_edit_handles_edit_transport_error() {
		if ( null !== $this->pre_http_request_filter ) {
			remove_filter( 'pre_http_request', $this->pre_http_request_filter, 10 );
		}

		$this->pre_http_request_filter = function ( $preempt, $args, $url ) {
			$this->last_request_args = array(
				'url'  => $url,
				'args' => $args,
			);

			if ( false !== strpos( $url, '/api/v1/imagegen/edit' ) ) {
				return new \WP_Error( 'http_request_failed', 'connection refused' );
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => $this->png_body,
				'headers'  => array(
					'content-type' => 'image/png',
				),
			);
		};

		add_filter( 'pre_http_request', $this->pre_http_request_filter, 10, 3 );

		$result = $this->execute_edit(
			array(
				'prompt'     => 'Make the sky purple',
				'source_url' => 'https://images.unsplash.com/photo-test.png',
			)
		);

		$this->assertSame( 502, $result['statusCode'] );
	}

	/**
	 * Verifies a non-2xx edit API response is surfaced with its status code.
	 */
	public function test_edit_handles_edit_error_status() {
		if ( null !== $this->pre_http_request_filter ) {
			remove_filter( 'pre_http_request', $this->pre_http_request_filter, 10 );
		}

		$this->pre_http_request_filter = function ( $preempt, $args, $url ) {
			$this->last_request_args = array(
				'url'  => $url,
				'args' => $args,
			);

			if ( false !== strpos( $url, '/api/v1/imagegen/edit' ) ) {
				return array(
					'response' => array( 'code' => 503 ),
					'body'     => wp_json_encode( array( 'message' => 'Service unavailable' ) ),
					'headers'  => array(),
				);
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => $this->png_body,
				'headers'  => array(
					'content-type' => 'image/png',
				),
			);
		};

		add_filter( 'pre_http_request', $this->pre_http_request_filter, 10, 3 );

		$result = $this->execute_edit(
			array(
				'prompt'     => 'Make the sky purple',
				'source_url' => 'https://images.unsplash.com/photo-test.png',
			)
		);

		$this->assertSame( 503, $result['statusCode'] );
		$this->assertSame( 'Service unavailable', $result['message'] );
	}

	/**
	 * Verifies a 200 edit response missing a url field returns 500.
	 */
	public function test_edit_handles_missing_url_in_response() {
		if ( null !== $this->pre_http_request_filter ) {
			remove_filter( 'pre_http_request', $this->pre_http_request_filter, 10 );
		}

		$this->pre_http_request_filter = function ( $preempt, $args, $url ) {
			$this->last_request_args = array(
				'url'  => $url,
				'args' => $args,
			);

			if ( false !== strpos( $url, '/api/v1/imagegen/edit' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => wp_json_encode( array( 'foo' => 'bar' ) ),
					'headers'  => array(),
				);
			}

			return array(
				'response' => array( 'code' => 200 ),
				'body'     => $this->png_body,
				'headers'  => array(
					'content-type' => 'image/png',
				),
			);
		};

		add_filter( 'pre_http_request', $this->pre_http_request_filter, 10, 3 );

		$result = $this->execute_edit(
			array(
				'prompt'     => 'Make the sky purple',
				'source_url' => 'https://images.unsplash.com/photo-test.png',
			)
		);

		$this->assertSame( 500, $result['statusCode'] );
		$this->assertSame( 'No image URL in response', $result['message'] );
	}
}
