<?php

declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Document read ability — exposes uploaded temp documents to the AI.
 */
class DocumentRead {

	/**
	 * Constructor — registers the blu/read-document ability.
	 */
	public function __construct() {
		blu_register_ability(
			'blu/read-document',
			array(
				'label'               => 'Read Document',
				'description'         => 'Read the text content of an uploaded document (txt, md, csv, pdf) so it can be used to update page content. Call this when the user wants to apply the content of an uploaded file to text blocks on the page.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'source_url' => array(
							'type'        => 'string',
							'description' => 'URL of the uploaded document from the [User uploaded documents] list.',
						),
					),
					'required'   => array( 'source_url' ),
				),
				'execute_callback'    => array( $this, 'read' ),
				'permission_callback' => fn() => current_user_can( 'edit_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}

	/**
	 * Read document content from the temp upload directory.
	 *
	 * @param array $input Tool input parameters.
	 * @return array Standardized ability response.
	 */
	public function read( array $input ): array {
		$raw_url = (string) ( $input['source_url'] ?? '' );
		$url     = esc_url_raw( $raw_url );

		if ( empty( $url ) || ! filter_var( $raw_url, FILTER_VALIDATE_URL ) ) {
			return blu_prepare_ability_response( 400, __( 'A valid source_url is required.', 'wp-module-mcp' ) );
		}

		// Resolve to filesystem path — must be inside nfd-chat-temp (SSRF / path-traversal guard).
		$upload_dir = wp_upload_dir();
		$base_path  = realpath( $upload_dir['basedir'] . '/nfd-chat-temp' );
		if ( false === $base_path ) {
			return blu_prepare_ability_response( 404, __( 'Document not found or not accessible.', 'wp-module-mcp' ) );
		}

		$url_path = wp_parse_url( $url, PHP_URL_PATH );
		$abs_file = realpath( $base_path . '/' . basename( (string) $url_path ) );

		if ( false === $abs_file || strpos( $abs_file, $base_path ) !== 0 || ! is_file( $abs_file ) ) {
			return blu_prepare_ability_response( 404, __( 'Document not found or not accessible.', 'wp-module-mcp' ) );
		}

		$mime       = is_callable( 'mime_content_type' ) ? strtolower( (string) mime_content_type( $abs_file ) ) : '';
		$extension  = strtolower( (string) pathinfo( $abs_file, PATHINFO_EXTENSION ) );
		$text_types = array( 'text/plain', 'text/markdown', 'text/csv' );

		// Guard against very large files before reading into memory.
		$file_size     = filesize( $abs_file );
		$max_file_size = 50 * 1024 * 1024; // 50 MB
		if ( false !== $file_size && $file_size > $max_file_size ) {
			return blu_prepare_ability_response( 400, __( 'Document exceeds the 50 MB size limit.', 'wp-module-mcp' ) );
		}

		if ( in_array( $mime, $text_types, true ) ) {
			$content = file_get_contents( $abs_file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		} elseif ( 'application/pdf' === $mime || 'pdf' === $extension ) {
			$content = $this->extract_pdf_text( $abs_file );
			if ( 'application/pdf' !== $mime ) {
				$mime = 'application/pdf';
			}
		} else {
			return blu_prepare_ability_response( 400, __( 'Unsupported document type.', 'wp-module-mcp' ) );
		}

		if ( false === $content || '' === $content ) {
			return blu_prepare_ability_response( 500, __( 'Could not read document content.', 'wp-module-mcp' ) );
		}

		$max_chars = 30000;
		$truncated = mb_strlen( $content ) > $max_chars;
		if ( $truncated ) {
			$content = mb_substr( $content, 0, $max_chars );
		}

		return blu_prepare_ability_response(
			200,
			array(
				'content'   => $content,
				'type'      => $mime,
				'truncated' => $truncated,
				'message'   => $truncated
					? __( 'Document content (truncated to 30 000 characters).', 'wp-module-mcp' )
					: __( 'Document content read successfully.', 'wp-module-mcp' ),
			)
		);
	}

	/**
	 * Extract plain text from a PDF file.
	 *
	 * Tries smalot/pdfparser first (pure PHP, no system dependency), then falls
	 * back to pdftotext (poppler-utils) if the library is unavailable.
	 *
	 * @param string $path Absolute filesystem path to the PDF.
	 * @return string Extracted text or fallback message.
	 */
	private function extract_pdf_text( string $path ): string {
		$this->register_smalot_pdfparser_autoloader();

		// Primary: smalot/pdfparser — works on any PHP host, no binaries needed.
		if ( class_exists( '\Smalot\PdfParser\Parser' ) ) {
			try {
				$parser = new \Smalot\PdfParser\Parser();
				$pdf    = $parser->parseFile( $path );
				$text   = $pdf->getText();
				if ( is_string( $text ) && '' !== trim( $text ) ) {
					return $text;
				}
			} catch ( \Exception $e ) {
				// Fall through to pdftotext.
			}
		}

		// Fallback: pdftotext (poppler-utils), available on some servers.
		$disabled = array_map( 'trim', explode( ',', (string) ini_get( 'disable_functions' ) ) );
		if ( function_exists( 'shell_exec' ) && ! in_array( 'shell_exec', $disabled, true ) ) {
			$escaped = escapeshellarg( $path );
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
			$result = shell_exec( "pdftotext $escaped - 2>/dev/null" );
			if ( is_string( $result ) && '' !== trim( $result ) ) {
				return $result;
			}
		}

		return 'PDF content could not be extracted automatically on this server. Ask the user to upload a .txt version of the document instead.';
	}

	/**
	 * Register a targeted autoloader for smalot/pdfparser when Composer did not load it.
	 *
	 * Checks the module vendor tree and the parent brand-plugin vendor tree.
	 *
	 * @return void
	 */
	private function register_smalot_pdfparser_autoloader(): void {
		if ( class_exists( '\Smalot\PdfParser\Parser' ) ) {
			return;
		}

		$smalot_src_candidates = array(
			dirname( __DIR__, 2 ) . '/vendor/smalot/pdfparser/src',
			dirname( __DIR__, 4 ) . '/smalot/pdfparser/src',
		);

		foreach ( $smalot_src_candidates as $smalot_src ) {
			if ( ! is_dir( $smalot_src ) ) {
				continue;
			}

			spl_autoload_register(
				static function ( $class_name ) use ( $smalot_src ) {
					if ( 0 === strpos( $class_name, 'Smalot\\' ) ) {
						$file = $smalot_src . DIRECTORY_SEPARATOR . str_replace( '\\', DIRECTORY_SEPARATOR, $class_name ) . '.php';
						if ( file_exists( $file ) ) {
							require_once $file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
						}
					}
				}
			);

			return;
		}
	}
}
