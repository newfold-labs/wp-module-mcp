<?php

namespace BLU;

use BLU\Abilities\Media;

/**
 * Media upload regression tests.
 *
 * @covers \BLU\Abilities\Media
 */
class MediaWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Whether this test registered the media abilities.
	 *
	 * @var bool
	 */
	private $registered = false;

	/** Set up an administrator and the upload ability. */
	public function set_up(): void {
		parent::set_up();
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'WP Abilities API is not available.' );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$categories = \WP_Ability_Categories_Registry::get_instance();
		if ( ! $categories->is_registered( 'blu-mcp' ) ) {
			$categories->register( 'blu-mcp', array( 'label' => 'Bluehost MCP' ) );
		}
		$registry = \WP_Abilities_Registry::get_instance();
		if ( ! $registry->is_registered( 'blu/upload-media' ) ) {
			$register = static function () {
				new Media();
			};
			add_action( 'wp_abilities_api_init', $register );
			do_action( 'wp_abilities_api_init', $registry );
			remove_action( 'wp_abilities_api_init', $register );
			$this->registered = true;
		}
	}

	/** Clean up abilities registered by this test. */
	public function tear_down(): void {
		if ( $this->registered ) {
			foreach ( array( 'list-media', 'get-media', 'get-media-file', 'upload-media', 'update-media', 'delete-media', 'search-media' ) as $name ) {
				blu_unregister_ability( 'blu/' . $name );
			}
		}
		parent::tear_down();
	}

	/** A human title must not become an extensionless disk filename. */
	public function test_upload_uses_separate_filename_and_title(): void {
		$png      = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av4N70a4AAAAAElFTkSuQmCC', true );
		$ability  = blu_get_ability( 'blu/upload-media' );
		$response = $ability->execute(
			array(
				'file'     => base64_encode( $png ),
				'filename' => 'company-logo.png',
				'title'    => 'Company Logo',
			)
		);
		$this->assertSame( 201, $response['statusCode'] );
		$this->assertSame( 'Company Logo', $response['message']->post_title );
		$this->assertStringEndsWith( '.png', get_attached_file( $response['message']->ID ) );
	}

	/** Existing calls with only a human title get an extension inferred from MIME. */
	public function test_upload_infers_extension_when_filename_is_omitted(): void {
		$png      = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVR4nGP4//8/AAX+Av4N70a4AAAAAElFTkSuQmCC', true );
		$response = blu_get_ability( 'blu/upload-media' )->execute(
			array(
				'file'  => base64_encode( $png ),
				'title' => 'Company Logo',
			)
		);
		$this->assertSame( 201, $response['statusCode'] );
		$this->assertStringEndsWith( '.png', get_attached_file( $response['message']->ID ) );
	}
}
