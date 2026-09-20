<?php
declare( strict_types=1 );

namespace BLU\Abilities;

use BLU\RestControllerSchema\RestControllerSchemaBuilder;

/**
 * Media abilities for WordPress media library.
 */
class Media {

	/**
	 * Base REST API namespace used to discover the latest versioned namespace.
	 *
	 * @var string
	 */
	private $base_namespace = 'wp';

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
		$schemas = RestControllerSchemaBuilder::for_media();

		// List media
		blu_register_ability(
			'blu/list-media',
			array(
				'label'               => 'List Media',
				'description'         => 'List WordPress media items with pagination and filtering',
				'category'            => 'blu-mcp',
				'input_schema'        => $schemas->collection(),
				'execute_callback'    => function ( $input = null ) {
					return $this->execute_list_media( $input );
				},
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
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
				'input_schema'        => $schemas->get_item( 'Unique identifier for the media item.' ),
				'execute_callback'    => function ( $input ) {
					$root = $this->get_media_route();
					if ( is_array( $root ) ) {
						return $root;
					}

					$id      = (int) $input['id'];
					$request = new \WP_REST_Request( 'GET', RestApiUtils::build_item_route( $root, $id ) );
					if ( isset( $input['context'] ) ) {
						$request->set_param( 'context', $input['context'] );
					}
					if ( isset( $input['password'] ) ) {
						$request->set_param( 'password', $input['password'] );
					}
					if ( isset( $input['excerpt_length'] ) ) {
						$request->set_param( 'excerpt_length', $input['excerpt_length'] );
					}
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
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
						return blu_prepare_ability_response( 404, 'File not found' );
					}

					if ( 'full' !== $size && 'original' !== $size ) {
						$meta = wp_get_attachment_metadata( $id );
						if ( isset( $meta['sizes'][ $size ]['file'] ) ) {
							$base_dir  = pathinfo( $file_path, PATHINFO_DIRNAME );
							$file_path = $base_dir . '/' . $meta['sizes'][ $size ]['file'];
						}
					}

					if ( ! file_exists( $file_path ) ) {
						return blu_prepare_ability_response( 404, 'Requested size not found' );
					}

					$mime_type = get_post_mime_type( $id );
					$file_data = file_get_contents( $file_path );

					return blu_prepare_ability_response(
						200,
						array(
							'results'  => $file_data,
							'type'     => 'image',
							'mimeType' => $mime_type,
						)
					);
				},
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
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
				'input_schema'        => $this->schema_for_upload( $schemas ),
				'execute_callback'    => function ( $input ) {
					// Process base64 file
					$base64_data = $input['file'];
					if ( strpos( $base64_data, 'data:' ) === 0 ) {
						$base64_data = preg_replace( '/^data:.*?;base64,/', '', $base64_data );
					}

					$file_data = base64_decode( $base64_data, true );
					if ( false === $file_data ) {
						return blu_prepare_ability_response( 400, 'Invalid base64 data' );
					}

					// Detect mime type
					$finfo     = finfo_open( FILEINFO_MIME_TYPE );
					$mime_type = finfo_buffer( $finfo, $file_data );
					finfo_close( $finfo );

					// Create temp file
					$filename = isset( $input['title'] ) ? sanitize_file_name( $input['title'] ) : 'upload';
					$upload   = wp_upload_bits( $filename, null, $file_data );

					if ( $upload['error'] ) {
						return blu_prepare_ability_response( 500, 'File upload error: ' . $upload['error'] );
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

					return blu_prepare_ability_response( 201, get_post( $attach_id ) );
				},
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
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
				'input_schema'        => $schemas->update_with_id( 'Unique identifier for the media item.' ),
				'execute_callback'    => function ( $input ) {
					$root = $this->get_media_route();
					if ( is_array( $root ) ) {
						return $root;
					}

					$id = (int) $input['id'];
					unset( $input['id'] );
					$request = new \WP_REST_Request( 'POST', RestApiUtils::build_item_route( $root, $id ) );
					$request->set_body_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
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
				'input_schema'        => $schemas->delete_with_id(
					RestControllerSchemaBuilder::post_delete_endpoint_args(),
					array( 'id' ),
					'Unique identifier for the media item.'
				),
				'execute_callback'    => function ( $input ) {
					$root = $this->get_media_route();
					if ( is_array( $root ) ) {
						return $root;
					}

					$id = (int) $input['id'];
					unset( $input['id'] );
					if ( ! array_key_exists( 'force', $input ) ) {
						$input['force'] = true;
					}

					$request = new \WP_REST_Request( 'DELETE', RestApiUtils::build_item_route( $root, $id ) );
					$request->set_query_params( $input );
					$response = rest_do_request( $request );
					return blu_standardize_rest_response( $response );
				},
				'permission_callback' => fn() => current_user_can( 'delete_posts' ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);

		// Search media (deprecated alias of list-media)
		blu_register_ability(
			'blu/search-media',
			array(
				'label'               => 'Search Media',
				'description'         => 'Deprecated alias of blu/list-media. Search WordPress media items by title, caption, or description. Prefer blu/list-media for new integrations.',
				'category'            => 'blu-mcp',
				'input_schema'        => $schemas->collection(),
				'execute_callback'    => function ( $input = null ) {
					return $this->execute_list_media( $input );
				},
				'permission_callback' => fn() => current_user_can( 'upload_files' ),
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
	 * Execute list/search media against the attachments collection route.
	 *
	 * @param array<string, mixed>|null $input Query parameters.
	 *
	 * @return array<string, mixed>
	 */
	private function execute_list_media( $input = null ) {
		$root = $this->get_media_route();
		if ( is_array( $root ) ) {
			return $root;
		}

		$request = new \WP_REST_Request( 'GET', $root );
		if ( $input ) {
			$request->set_query_params( $input );
		}
		$response = rest_do_request( $request );

		return blu_standardize_rest_response( $response );
	}

	/**
	 * Resolve the media REST route or return a standardized error response.
	 *
	 * @return string|array<string, mixed> Route string or error response array.
	 */
	private function get_media_route() {
		$root = RestApiUtils::get_latest_available_rest_route( $this->base_namespace, 'media' );

		if ( ! $root ) {
			return blu_standardize_rest_response(
				new \WP_Error(
					400,
					'A valid route for media not found. Please ensure that the REST API is enabled and that the latest version of the WordPress REST API is installed.',
				)
			);
		}

		return $root;
	}

	/**
	 * Hybrid upload schema: creatable attachment fields plus base64 file input.
	 *
	 * @param RestControllerSchemaBuilder $schemas Schema builder for media.
	 *
	 * @return array<string, mixed>
	 */
	private function schema_for_upload( RestControllerSchemaBuilder $schemas ): array {
		$schema = $schemas->creatable();

		$schema['properties']['file'] = array(
			'type'        => 'string',
			'description' => 'Base64 encoded file data',
		);

		$schema['required'] = array_values(
			array_unique(
				array_merge(
					$schema['required'] ?? array(),
					array( 'file' )
				)
			)
		);

		return $schema;
	}
}
