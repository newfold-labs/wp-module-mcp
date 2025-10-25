<?php
declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * Media abilities for WordPress media library.
 */
class Media {

	/**
	 * Constructor - registers all media-related abilities.
	 */
	public function __construct() {
		$this->register_abilities();
	}

	/**
	 * Register media abilities.
	 */
	private function register_abilities(): void {
		// List media
		blu_register_ability(
			'blu/list-media',
			array(
				'label'               => 'List Media',
				'description'         => 'List WordPress media items with pagination and filtering',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'page'     => array(
							'type'        => 'integer',
							'description' => 'Page number',
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => 'Items per page',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/media' );
					if ( $input ) {
						$request->set_query_params( $input );
					}
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		// Get media
		blu_register_ability(
			'blu/get-media',
			array(
				'label'               => 'Get Media',
				'description'         => 'Get a WordPress media item details by ID',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Media ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/media/' . $input['id'] );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		// Get media file (binary content)
		blu_register_ability(
			'blu/get-media-file',
			array(
				'label'               => 'Get Media File',
				'description'         => 'Get the actual file content (blob) of a WordPress media item',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'   => array(
							'type'        => 'integer',
							'description' => 'Media ID',
						),
						'size' => array(
							'type'        => 'string',
							'description' => 'Image size (thumbnail, medium, large, full)',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id        = $input['id'];
					$size      = $input['size'] ?? 'full';
					$file_path = get_attached_file( $id );

					if ( ! file_exists( $file_path ) ) {
						return new \WP_Error( 'file_not_found', 'File not found' );
					}

					if ( 'full' !== $size && 'original' !== $size ) {
						$meta = wp_get_attachment_metadata( $id );
						if ( isset( $meta['sizes'][ $size ]['file'] ) ) {
							$base_dir  = pathinfo( $file_path, PATHINFO_DIRNAME );
							$file_path = $base_dir . '/' . $meta['sizes'][ $size ]['file'];
						}
					}

					if ( ! file_exists( $file_path ) ) {
						return new \WP_Error( 'size_not_found', 'Requested size not found' );
					}

					$mime_type = get_post_mime_type( $id );
					$file_data = file_get_contents( $file_path );

					return array(
						'results'  => $file_data,
						'type'     => 'image',
						'mimeType' => $mime_type,
					);
				},
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		// Upload media
		blu_register_ability(
			'blu/upload-media',
			array(
				'label'               => 'Upload Media',
				'description'         => 'Upload a new media file to WordPress',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'file'        => array(
							'type'        => 'string',
							'description' => 'Base64 encoded file data',
						),
						'title'       => array(
							'type'        => 'string',
							'description' => 'Media title',
						),
						'caption'     => array(
							'type'        => 'string',
							'description' => 'Media caption',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Media description',
						),
						'alt_text'    => array(
							'type'        => 'string',
							'description' => 'Alt text',
						),
					),
					'required'   => array( 'file' ),
				),
				'execute_callback'    => function ( $input ) {
					// Process base64 file
					$base64_data = $input['file'];
					if ( strpos( $base64_data, 'data:' ) === 0 ) {
						$base64_data = preg_replace( '/^data:.*?;base64,/', '', $base64_data );
					}

					$file_data = base64_decode( $base64_data, true );
					if ( false === $file_data ) {
						return new \WP_Error( 'invalid_file', 'Invalid base64 data' );
					}

					// Detect mime type
					$finfo     = finfo_open( FILEINFO_MIME_TYPE );
					$mime_type = finfo_buffer( $finfo, $file_data );
					finfo_close( $finfo );

					// Create temp file
					$filename = isset( $input['title'] ) ? sanitize_file_name( $input['title'] ) : 'upload';
					$upload   = wp_upload_bits( $filename, null, $file_data );

					if ( $upload['error'] ) {
						return new \WP_Error( 'upload_failed', $upload['error'] );
					}

					// Create attachment
					$attachment = array(
						'post_mime_type' => $mime_type,
						'post_title'     => $input['title'] ?? '',
						'post_content'   => $input['description'] ?? '',
						'post_excerpt'   => $input['caption'] ?? '',
						'post_status'    => 'inherit',
					);

					$attach_id = wp_insert_attachment( $attachment, $upload['file'] );
					require_once ABSPATH . 'wp-admin/includes/image.php';
					$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
					wp_update_attachment_metadata( $attach_id, $attach_data );

					if ( ! empty( $input['alt_text'] ) ) {
						update_post_meta( $attach_id, '_wp_attachment_image_alt', $input['alt_text'] );
					}

					return get_post( $attach_id );
				},
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => false,
					),
				),
			)
		);

		// Update media
		blu_register_ability(
			'blu/update-media',
			array(
				'label'               => 'Update Media',
				'description'         => 'Update a WordPress media item',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array(
							'type'        => 'integer',
							'description' => 'Media ID',
						),
						'title'       => array(
							'type'        => 'string',
							'description' => 'Media title',
						),
						'caption'     => array(
							'type'        => 'string',
							'description' => 'Media caption',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Media description',
						),
						'alt_text'    => array(
							'type'        => 'string',
							'description' => 'Alt text',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$id = $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'POST', '/wp/v2/media/' . $id );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);

		// Delete media
		blu_register_ability(
			'blu/delete-media',
			array(
				'label'               => 'Delete Media',
				'description'         => 'Delete a WordPress media item permanently',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Media ID',
						),
					),
					'required'   => array( 'id' ),
				),
				'execute_callback'    => function ( $input ) {
					$request = new \WP_REST_Request( 'DELETE', '/wp/v2/media/' . $input['id'] );
					$request->set_param( 'force', true );
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'delete_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => false,
						'destructive'  => true,
						'idempotent'   => true,
					),
				),
			)
		);

		// Search media
		blu_register_ability(
			'blu/search-media',
			array(
				'label'               => 'Search Media',
				'description'         => 'Search WordPress media items by title, caption, or description',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'     => array(
							'type'        => 'string',
							'description' => 'Search term',
						),
						'media_type' => array(
							'type'        => 'string',
							'description' => 'Media type filter (image, video, audio, application)',
						),
						'mime_type'  => array(
							'type'        => 'string',
							'description' => 'MIME type filter',
						),
					),
				),
				'execute_callback'    => function ( $input = null ) {
					$request = new \WP_REST_Request( 'GET', '/wp/v2/media' );
					if ( $input ) {
						$request->set_query_params( $input );
					}
					$response = rest_do_request( $request );
					return $response->get_data();
				},
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'     => true,
						'destructive'  => false,
						'idempotent'   => true,
					),
				),
			)
		);
	}
}
