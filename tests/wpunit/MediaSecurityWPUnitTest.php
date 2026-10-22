<?php

namespace BLU;

use BLU\Abilities\Media;

/** Security checks for direct media abilities. */
class MediaSecurityWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {
	/**
	 * Files to remove after each test.
	 *
	 * @var string[]
	 */
	private $uploaded_files   = array();
	/**
	 * Whether this test registered the media abilities.
	 *
	 * @var bool
	 */
	private $registered_media = false;

	/** Set up an administrator and the media abilities. */
	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is unavailable.' );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$categories = \WP_Ability_Categories_Registry::get_instance();
		if ( ! $categories->is_registered( 'blu-mcp' ) ) {
			$categories->register(
				'blu-mcp',
				array(
					'label'       => 'Bluehost MCP',
					'description' => 'Bluehost abilities',
				)
			);
		}
		if ( ! \WP_Abilities_Registry::get_instance()->is_registered( 'blu/get-media-file' ) ) {
			$register = static function () {
				new Media();
			};
			add_action( 'wp_abilities_api_init', $register );
			do_action( 'wp_abilities_api_init', \WP_Abilities_Registry::get_instance() );
			remove_action( 'wp_abilities_api_init', $register );
			$this->registered_media = true;
		}
	}

	/** Remove test ability registrations and upload files. */
	public function tear_down(): void {
		if ( $this->registered_media ) {
			foreach ( array( 'blu/list-media', 'blu/get-media', 'blu/get-media-file', 'blu/upload-media', 'blu/update-media', 'blu/delete-media', 'blu/search-media' ) as $name ) {
				if ( \WP_Abilities_Registry::get_instance()->is_registered( $name ) ) {
					blu_unregister_ability( $name );
				}
			}
		}
		foreach ( $this->uploaded_files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}
		parent::tear_down();
	}

	/** A broad upload capability must not override attachment read denial. */
	public function test_file_bytes_require_permission_on_the_attachment(): void {
		$upload = wp_upload_bits( 'mcp-media-security.txt', null, 'Private attachment bytes' );
		$this->assertEmpty( $upload['error'] );
		$this->uploaded_files[] = $upload['file'];
		$id                     = wp_insert_attachment(
			array(
				'post_mime_type' => 'text/plain',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		$deny                   = static function ( $caps, $cap, $user_id, $args ) use ( $id ) {
			return 'read_post' === $cap && isset( $args[0] ) && (int) $args[0] === $id ? array( 'do_not_allow' ) : $caps;
		};
		add_filter( 'map_meta_cap', $deny, 10, 4 );
		try {
			$result = blu_get_ability( 'blu/get-media-file' )->execute( array( 'id' => $id ) );
			$this->assertSame( 403, $result['statusCode'] );
		} finally {
			remove_filter( 'map_meta_cap', $deny, 10 );
		}
		$allowed = blu_get_ability( 'blu/get-media-file' )->execute( array( 'id' => $id ) );
		$this->assertSame( 200, $allowed['statusCode'] );
	}

	/** A PNG extension on PHP content must be rejected. */
	public function test_upload_rejects_content_that_does_not_match_extension(): void {
		$result = blu_get_ability( 'blu/upload-media' )->execute(
			array(
				'file'  => base64_encode( '<?php echo "unsafe";' ),
				'title' => 'unsafe.png',
			)
		);
		$this->assertSame( 400, $result['statusCode'], wp_json_encode( $result ) );
	}

	/** A valid image must still upload successfully. */
	public function test_upload_accepts_valid_png(): void {
		$png    = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lXcAAAAASUVORK5CYII=', true );
		$result = blu_get_ability( 'blu/upload-media' )->execute(
			array(
				'file'  => base64_encode( $png ),
				'title' => 'valid.png',
			)
		);
		$this->assertSame( 201, $result['statusCode'], wp_json_encode( $result ) );
		$this->assertSame( 'image/png', $result['message']->post_mime_type );
		$this->uploaded_files[] = get_attached_file( $result['message']->ID );
	}
}
