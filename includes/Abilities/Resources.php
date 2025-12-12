<?php
/**
 * This class manage the Abilities managed like Resources
 *
 * @package BLU\Abilities
 */

namespace BLU\Abilities;

/**
 * This class create abilities like "resources"
 */
class Resources {
	/**
	 * Constructor - registers resources
	 */
	public function __construct() {

		$this->register_google_taxonomy_resource();
	}

	/**
	 * Read the official Google Product Taxonomy and return the results
	 *
	 * @return void
	 */
	private function register_google_taxonomy_resource() {

		blu_register_ability( 'blu/google-product-taxonomy', [
			'label'               => 'Google Product Taxonomy',
			'description'         => 'The official Google Product Taxonomy resource',
			'category'            => 'blu-mcp',
			'execute_callback'    => function () {

				$locale = str_replace( '_', '-', get_locale() );

				$taxonomy =  get_transient( 'blu/google-product-taxonomy-' . $locale );
				if ( false === $taxonomy ) {

					$content = $this->retrieve_file( $locale );

					if ( is_wp_error( $content ) ) {
						return $content;
					} elseif ( 'not_found' === $content ) {
						$content = $this->retrieve_file();
						if ( is_wp_error( $content ) ) {
							return $content;
						}
					}

					// Split into lines
					$lines = explode( "\n", $content );

					$taxonomy = '';

					foreach ( $lines as $line ) {
						$line = trim( $line );
						if( '' === $line || strpos( $line, '#' ) === 0 ) {
							continue;
						}

						$line = preg_replace('/^\d+\s*-\s*/', '', $line);
						$taxonomy.= $line.'\n';
					}
					set_transient( 'blu/google-product-taxonomy-' . $locale, $taxonomy, MONTH_IN_SECONDS );
				}


				return [
					'categories' => $taxonomy,
				];
			},
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
			'meta'                => [
				'uri'         => 'blu://google/product/taxonomy',  // Required for resources
				'annotations' => [
					'readOnlyHint'   => true,
					'idempotentHint' => true,
					'audience'       => [ 'user', 'assistant' ],
					'priority'       => 0.9
				],
				'mcp'         => [
					'public' => true,      // Expose this ability via MCP
					'type'   => 'tool' // Mark as resource for auto-discovery
				]
			]
		] );
	}

	/**
	 * Read the google product taxonomy file and get the content
	 *
	 * @param string $locale The locale.
	 *
	 * @return array|string|\WP_Error
	 */
	private function retrieve_file( $locale = 'en-US' ) {
		$response = wp_remote_get( 'https://www.google.com/basepages/producttype/taxonomy-with-ids.' . $locale . '.txt' );
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( 404 == wp_remote_retrieve_response_code( $response ) ) {
			return 'not_found';
		} else {
			return wp_remote_retrieve_body( $response );
		}
	}
}

