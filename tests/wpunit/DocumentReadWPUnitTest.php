<?php

namespace BLU;

use BLU\Abilities\DocumentRead;

/**
 * Tests for DocumentRead ability — path guards, truncation, and PDF extraction.
 *
 * @covers \BLU\Abilities\DocumentRead
 */
class DocumentReadWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Ability names registered during the test for cleanup.
	 *
	 * @var string[]
	 */
	private $registered_abilities = array( 'blu/read-document' );

	/**
	 * Whether the ability has been registered in this test instance.
	 *
	 * @var bool
	 */
	private $abilities_initialized = false;

	/**
	 * Absolute path to the nfd-chat-temp directory created for tests.
	 *
	 * @var string|null
	 */
	private $temp_dir = null;

	/**
	 * Files created inside temp_dir during a test, cleaned up in tear_down.
	 *
	 * @var string[]
	 */
	private $created_files = array();

	/**
	 * Set up: create admin user, ensure blu-mcp category, create temp dir.
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

		$upload_dir     = wp_upload_dir();
		$this->temp_dir = $upload_dir['basedir'] . '/' . blu_mcp_chat_temp_subdir();
		if ( ! is_dir( $this->temp_dir ) ) {
			wp_mkdir_p( $this->temp_dir );
		}
		$this->created_files = array();
	}

	/**
	 * Tear down: remove test files, clean up ability registrations.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		foreach ( $this->created_files as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
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
	 * Register DocumentRead ability via the wp_abilities_api_init action.
	 *
	 * @return void
	 */
	private function ensure_abilities_registered(): void {
		if ( $this->abilities_initialized ) {
			return;
		}

		$cb = function () {
			new DocumentRead();
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
	 * Create a file in the temp directory and register it for cleanup.
	 *
	 * @param string $filename File name (basename only).
	 * @param string $content  File content.
	 * @return string Absolute path to the created file.
	 */
	private function create_temp_file( string $filename, string $content ): string {
		$path = $this->temp_dir . '/' . $filename;
		file_put_contents( $path, $content );
		$this->created_files[] = $path;
		return $path;
	}

	/**
	 * Build a source_url that uses the given filename as its basename.
	 *
	 * DocumentRead only uses basename(url_path) to locate the file, so the
	 * host does not need to be resolvable — any syntactically valid URL works.
	 *
	 * @param string $filename File name (basename only).
	 * @return string
	 */
	private function url_for( string $filename ): string {
		return 'https://example.com/wp-content/uploads/' . blu_mcp_chat_temp_subdir() . '/' . rawurlencode( $filename );
	}

	/**
	 * Helper: execute blu/read-document with the given input.
	 *
	 * @param array $input Input to pass to the ability.
	 * @return mixed
	 */
	private function execute_read( array $input ) {
		$this->ensure_abilities_registered();
		$ability = blu_get_ability( 'blu/read-document' );
		$this->assertNotNull( $ability, 'Ability blu/read-document should be registered.' );
		return $ability->execute( $input );
	}

	// -------------------------------------------------------------------------
	// Path guards
	// -------------------------------------------------------------------------

	/**
	 * Returns WP_Error when source_url is absent (required schema violation).
	 */
	public function test_read_returns_wp_error_when_source_url_absent() {
		$result = $this->execute_read( array() );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Returns 400 when source_url is an empty string.
	 */
	public function test_read_returns_400_when_source_url_empty() {
		$result = $this->execute_read( array( 'source_url' => '' ) );
		$this->assertSame( 400, $result['statusCode'] );
	}

	/**
	 * Returns 400 when source_url is not a valid URL.
	 */
	public function test_read_returns_400_when_source_url_invalid() {
		$result = $this->execute_read( array( 'source_url' => 'not-a-url' ) );
		$this->assertSame( 400, $result['statusCode'] );
	}

	/**
	 * Returns 404 when the file does not exist in the temp directory.
	 */
	public function test_read_returns_404_when_file_not_found() {
		$result = $this->execute_read( array( 'source_url' => $this->url_for( 'nonexistent-' . uniqid() . '.txt' ) ) );
		$this->assertSame( 404, $result['statusCode'] );
	}

	/**
	 * Returns 400 when the file exceeds the 50 MB size limit.
	 *
	 * Uses fseek to create a sparse file so the test is fast (no 50 MB write).
	 */
	public function test_read_returns_400_when_file_exceeds_50_mb() {
		$filename              = 'large-doc-' . uniqid() . '.txt';
		$path                  = $this->temp_dir . '/' . $filename;
		$this->created_files[] = $path;

		$fh = fopen( $path, 'wb' );
		fseek( $fh, 50 * 1024 * 1024 ); // seek to exactly 50 MB
		fwrite( $fh, 'X' );             // one byte over the limit
		fclose( $fh );

		$result = $this->execute_read( array( 'source_url' => $this->url_for( $filename ) ) );
		$this->assertSame( 400, $result['statusCode'] );
		$this->assertStringContainsString( '50', $result['message'] );
	}

	/**
	 * Returns 400 for files whose MIME type is not in the allowed list.
	 *
	 * A file starting with PNG magic bytes will be detected as image/png.
	 */
	public function test_read_returns_400_for_unsupported_mime_type() {
		// PNG magic bytes — mime_content_type returns image/png.
		$this->create_temp_file( 'logo.bin', "\x89PNG\r\n\x1a\n" . str_repeat( 'x', 20 ) );
		$result = $this->execute_read( array( 'source_url' => $this->url_for( 'logo.bin' ) ) );
		$this->assertSame( 400, $result['statusCode'] );
	}

	// -------------------------------------------------------------------------
	// Text content reads
	// -------------------------------------------------------------------------

	/**
	 * Returns the full content of a plain-text file.
	 */
	public function test_read_returns_content_for_txt_file() {
		$text = "Hello, world!\nThis is a test document.";
		$this->create_temp_file( 'hello.txt', $text );

		$result = $this->execute_read( array( 'source_url' => $this->url_for( 'hello.txt' ) ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( $text, $result['message']['content'] );
		$this->assertFalse( $result['message']['truncated'] );
	}

	/**
	 * Returns the full content of a CSV file.
	 */
	public function test_read_returns_content_for_csv_file() {
		$csv = "name,email\nAlice,alice@example.com\nBob,bob@example.com";
		$this->create_temp_file( 'data.csv', $csv );

		$result = $this->execute_read( array( 'source_url' => $this->url_for( 'data.csv' ) ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertSame( $csv, $result['message']['content'] );
		$this->assertFalse( $result['message']['truncated'] );
	}

	// -------------------------------------------------------------------------
	// Truncation
	// -------------------------------------------------------------------------

	/**
	 * Truncates content at 30 000 characters and sets truncated = true.
	 */
	public function test_read_truncates_content_at_30000_chars() {
		$long_content = str_repeat( 'A', 35000 );
		$this->create_temp_file( 'long.txt', $long_content );

		$result = $this->execute_read( array( 'source_url' => $this->url_for( 'long.txt' ) ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertTrue( $result['message']['truncated'] );
		$this->assertSame( 30000, mb_strlen( $result['message']['content'] ) );
	}

	/**
	 * Does not truncate content that is exactly 30 000 characters.
	 */
	public function test_read_does_not_truncate_content_at_exactly_30000_chars() {
		$content = str_repeat( 'B', 30000 );
		$this->create_temp_file( 'exact.txt', $content );

		$result = $this->execute_read( array( 'source_url' => $this->url_for( 'exact.txt' ) ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertFalse( $result['message']['truncated'] );
		$this->assertSame( 30000, mb_strlen( $result['message']['content'] ) );
	}

	// -------------------------------------------------------------------------
	// PDF extraction
	// -------------------------------------------------------------------------

	/**
	 * Returns 200 for a PDF file — either extracted text or the fallback message.
	 *
	 * Whether smalot/pdfparser or pdftotext is available varies by environment;
	 * both paths must ultimately return 200 with a non-empty string.
	 */
	public function test_read_pdf_returns_200_with_content_or_fallback() {
		// Minimal PDF: magic bytes that make mime_content_type return application/pdf.
		$pdf_content = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\n%%EOF";
		$this->create_temp_file( 'document.pdf', $pdf_content );

		$result = $this->execute_read( array( 'source_url' => $this->url_for( 'document.pdf' ) ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertIsString( $result['message']['content'] );
		$this->assertNotSame( '', $result['message']['content'] );
	}

	/**
	 * When a PDF cannot be parsed by any available method, a human-readable
	 * fallback message is returned (not a 500 error).
	 *
	 * The fallback string is non-empty so the ability returns 200.
	 */
	public function test_read_pdf_fallback_message_is_non_empty_string() {
		// Corrupt PDF — smalot will throw, pdftotext will produce no output.
		$this->create_temp_file( 'corrupt.pdf', "%PDF-1.4\n\x00\x00\x00garbage\x01\x02\x03" );

		$result = $this->execute_read( array( 'source_url' => $this->url_for( 'corrupt.pdf' ) ) );

		$this->assertSame( 200, $result['statusCode'] );
		$this->assertIsString( $result['message']['content'] );
		$this->assertNotSame( '', trim( $result['message']['content'] ) );
	}
}
