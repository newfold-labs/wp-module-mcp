<?php

namespace BLU;

use BLU\Abilities\ImageEdit;

/**
 * Tests for the blu/extract-image-colors ability.
 *
 * Uses synthetic GD images to verify dominant-color extraction, transparency
 * filtering, and near-white/near-black filtering without relying on real files.
 *
 * @covers \BLU\Abilities\ImageEdit::extract_colors
 */
class ImageEditColorsWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Ability names registered during the test for cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array( 'blu/edit-image', 'blu/extract-image-colors' );

	/**
	 * Whether the ability has been registered in this test instance.
	 *
	 * @var bool
	 */
	private $abilities_initialized = false;

	/**
	 * The pre_http_request filter callback, retained for tear_down removal.
	 *
	 * @var callable|null
	 */
	private $pre_http_request_filter = null;

	/**
	 * Set up: create admin user, ensure blu-mcp category.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}

		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD extension is not available.' );
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
	 * Register ImageEdit abilities via the wp_abilities_api_init action.
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
	 * Generate PNG binary data for a solid-color truecolor image.
	 *
	 * @param int $r Red channel (0–255).
	 * @param int $g Green channel (0–255).
	 * @param int $b Blue channel (0–255).
	 * @param int $w Width in pixels.
	 * @param int $h Height in pixels.
	 * @return string Raw PNG bytes.
	 */
	private function make_solid_png( int $r, int $g, int $b, int $w = 20, int $h = 20 ): string {
		$img   = imagecreatetruecolor( $w, $h );
		$color = imagecolorallocate( $img, $r, $g, $b );
		imagefill( $img, 0, 0, $color );
		ob_start();
		imagepng( $img );
		$data = ob_get_clean();
		unset( $img );
		return $data;
	}

	/**
	 * Generate PNG binary data for a fully transparent image.
	 *
	 * @param int $w Width in pixels.
	 * @param int $h Height in pixels.
	 * @return string Raw PNG bytes.
	 */
	private function make_transparent_png( int $w = 20, int $h = 20 ): string {
		$img = imagecreatetruecolor( $w, $h );
		imagealphablending( $img, false );
		imagesavealpha( $img, true );
		$transparent = imagecolorallocatealpha( $img, 0, 0, 0, 127 );
		imagefill( $img, 0, 0, $transparent );
		ob_start();
		imagepng( $img );
		$data = ob_get_clean();
		unset( $img );
		return $data;
	}

	/**
	 * Mock the HTTP fetch so the ability receives the given PNG bytes.
	 *
	 * @param string $png_data Raw PNG binary.
	 * @return void
	 */
	private function mock_image_fetch( string $png_data ): void {
		if ( null !== $this->pre_http_request_filter ) {
			remove_filter( 'pre_http_request', $this->pre_http_request_filter, 10 );
		}

		$this->pre_http_request_filter = function ( $preempt, $args, $url ) use ( $png_data ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => $png_data,
				'headers'  => array( 'content-type' => 'image/png' ),
			);
		};

		add_filter( 'pre_http_request', $this->pre_http_request_filter, 10, 3 );
	}

	/**
	 * Execute blu/extract-image-colors with the given input.
	 *
	 * @param array $input Input to pass to the ability.
	 * @return mixed
	 */
	private function execute_extract( array $input ) {
		$this->ensure_abilities_registered();
		$ability = blu_get_ability( 'blu/extract-image-colors' );
		$this->assertNotNull( $ability, 'Ability blu/extract-image-colors should be registered.' );
		return $ability->execute( $input );
	}

	/**
	 * A URL that passes is_allowed_source_url() in any test environment.
	 *
	 * Uses images.unsplash.com which is explicitly allow-listed in ImageEdit.
	 * The actual HTTP request is intercepted by the pre_http_request mock so
	 * no real network call is made.
	 *
	 * @return string
	 */
	private function allowed_image_url(): string {
		return 'https://images.unsplash.com/test-color-image.png';
	}

	// -------------------------------------------------------------------------
	// Input validation
	// -------------------------------------------------------------------------

	/**
	 * Returns WP_Error when image_url is absent (required schema violation).
	 */
	public function test_extract_returns_wp_error_when_image_url_absent() {
		$result = $this->execute_extract( array() );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Returns 400 when image_url is an empty string.
	 */
	public function test_extract_returns_400_when_image_url_empty() {
		$result = $this->execute_extract( array( 'image_url' => '' ) );
		$this->assertSame( 400, $result['statusCode'] );
	}

	/**
	 * Returns 400 when image_url is not a valid URL.
	 */
	public function test_extract_returns_400_when_image_url_invalid() {
		$result = $this->execute_extract( array( 'image_url' => 'not-a-url' ) );
		$this->assertSame( 400, $result['statusCode'] );
	}

	/**
	 * Returns 400 when image_url points to a disallowed external host.
	 */
	public function test_extract_returns_400_when_image_url_not_allowed() {
		$result = $this->execute_extract( array( 'image_url' => 'https://evil.example.com/logo.png' ) );
		$this->assertSame( 400, $result['statusCode'] );
	}

	// -------------------------------------------------------------------------
	// Dominant color detection
	// -------------------------------------------------------------------------

	/**
	 * A solid-red image yields a dominant hex of #ff0000.
	 *
	 * Quantization: round(255/32)*32 = 256 → clamped to 255.
	 * round(0/32)*32 = 0. Key = 'ff0000'.
	 */
	public function test_extract_returns_dominant_red_for_solid_red_image() {
		$this->mock_image_fetch( $this->make_solid_png( 255, 0, 0 ) );

		$result = $this->execute_extract( array( 'image_url' => $this->allowed_image_url() ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( '#ff0000', $result['message']['dominant'] );
		$this->assertNotEmpty( $result['message']['colors'] );
	}

	/**
	 * A solid mid-blue image (0, 0, 200) yields the expected quantized hex.
	 *
	 * Quantization: round(200/32)*32 = 6*32 = 192 → '#0000c0'.
	 */
	public function test_extract_returns_quantized_hex_for_solid_blue_image() {
		$this->mock_image_fetch( $this->make_solid_png( 0, 0, 200 ) );

		$result = $this->execute_extract( array( 'image_url' => $this->allowed_image_url() ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( '#0000c0', $result['message']['dominant'] );
	}

	/**
	 * Max_colors limits the number of entries returned.
	 */
	public function test_extract_respects_max_colors_parameter() {
		// Two-color image: left half red, right half blue.
		$img  = imagecreatetruecolor( 40, 20 );
		$red  = imagecolorallocate( $img, 255, 0, 0 );
		$blue = imagecolorallocate( $img, 0, 0, 200 );
		imagefilledrectangle( $img, 0, 0, 19, 19, $red );
		imagefilledrectangle( $img, 20, 0, 39, 19, $blue );
		ob_start();
		imagepng( $img );
		$png = ob_get_clean();
		unset( $img );

		$this->mock_image_fetch( $png );

		$result = $this->execute_extract(
			array(
				'image_url'  => $this->allowed_image_url(),
				'max_colors' => 1,
			)
		);

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertCount( 1, $result['message']['colors'] );
	}

	// -------------------------------------------------------------------------
	// Pixel filtering
	// -------------------------------------------------------------------------

	/**
	 * A fully white image (255,255,255) returns no distinct colors.
	 *
	 * All pixels satisfy r>230 && g>230 && b>230 → filtered.
	 */
	public function test_extract_returns_empty_colors_for_white_image() {
		$this->mock_image_fetch( $this->make_solid_png( 255, 255, 255 ) );

		$result = $this->execute_extract( array( 'image_url' => $this->allowed_image_url() ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertEmpty( $result['message']['colors'] );
	}

	/**
	 * A fully black image (0,0,0) returns no distinct colors.
	 *
	 * All pixels satisfy r<20 && g<20 && b<20 → filtered.
	 */
	public function test_extract_returns_empty_colors_for_black_image() {
		$this->mock_image_fetch( $this->make_solid_png( 0, 0, 0 ) );

		$result = $this->execute_extract( array( 'image_url' => $this->allowed_image_url() ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertEmpty( $result['message']['colors'] );
	}

	/**
	 * A fully transparent image returns no distinct colors.
	 *
	 * All pixels have alpha > 90 → filtered.
	 */
	public function test_extract_returns_empty_colors_for_transparent_image() {
		$this->mock_image_fetch( $this->make_transparent_png() );

		$result = $this->execute_extract( array( 'image_url' => $this->allowed_image_url() ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertEmpty( $result['message']['colors'] );
	}

	/**
	 * Near-white pixels (240,240,240) are filtered — no colors returned.
	 *
	 * r=240 > 230, g=240 > 230, b=240 > 230 → near-white guard activates.
	 */
	public function test_extract_filters_near_white_pixels() {
		$this->mock_image_fetch( $this->make_solid_png( 240, 240, 240 ) );

		$result = $this->execute_extract( array( 'image_url' => $this->allowed_image_url() ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertEmpty( $result['message']['colors'] );
	}

	/**
	 * Near-black pixels (10,10,10) are filtered — no colors returned.
	 *
	 * r=10 < 20, g=10 < 20, b=10 < 20 → near-black guard activates.
	 */
	public function test_extract_filters_near_black_pixels() {
		$this->mock_image_fetch( $this->make_solid_png( 10, 10, 10 ) );

		$result = $this->execute_extract( array( 'image_url' => $this->allowed_image_url() ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertEmpty( $result['message']['colors'] );
	}
}
