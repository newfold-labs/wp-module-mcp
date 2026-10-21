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
	 * Read the official Google Product Taxonomy and return the results.
	 *
	 * @return void
	 */
	private function register_google_taxonomy_resource() {

		blu_register_ability(
			'blu/google-product-taxonomy',
			array(
				'label'               => 'Google Product Taxonomy',
				'description'         => 'The official Google Product Taxonomy resource',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'patterns' => array(
							'type'        => 'array',
							'description' => 'List of relevant categories or relevant regex keywords based on product name',
							'items'       => array( 'type' => 'string' ),
							'minItems'    => 1,
							'maxItems'    => 5,
						),
						'required' => array( 'patterns' ),
					),
				),
				'execute_callback'    => function ( $input ) {
					$patterns = $input['patterns'] ?? array();

					$is_valid = blu_is_valid_input_array( $patterns, 'patterns', 1, 5 );
					if ( is_wp_error( $is_valid ) ) {
						return blu_standardize_rest_response( $is_valid );
					}
					$locale = str_replace( '_', '-', get_locale() );

					$taxonomy = get_transient( 'blu/google-product-taxonomy-' . $locale );
					if ( false === $taxonomy ) {

						$content = $this->retrieve_file( $locale );

						if ( is_wp_error( $content ) ) {
							return blu_standardize_rest_response( $content );
						} elseif ( 'not_found' === $content ) {
							$content = $this->retrieve_file();
							if ( is_wp_error( $content ) ) {
								return blu_standardize_rest_response( $content );
							}
						}

						// Split into lines
						$lines = explode( "\n", $content );

						$taxonomy = '';

						foreach ( $lines as $line ) {
							$line = trim( $line );
							if ( '' === $line || strpos( $line, '#' ) === 0 ) {
								continue;
							}

							$line = preg_replace( '/^\d+\s*-\s*/', '', $line );

							$taxonomy .= $line . "\n";
						}
						set_transient( 'blu/google-product-taxonomy-' . $locale, $taxonomy, MONTH_IN_SECONDS );
					}

					$filtered = $this->filter_google_taxonomies( $taxonomy, $patterns );

					return blu_prepare_ability_response( 200, $filtered );
				},
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'   => true,
						'idempotent' => true,
					),
				),
			)
		);
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
		} elseif ( 404 == wp_remote_retrieve_response_code( $response ) ) { //phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
			return 'not_found';
		} else {
			return wp_remote_retrieve_body( $response );
		}
	}

	/**
	 * Filter the Google product taxonomies
	 *
	 * @param string $taxonomy The taxonomy.
	 * @param array  $patterns The patterns
	 *
	 * @return array
	 */
	private function filter_google_taxonomies( $taxonomy, $patterns ) {
		$lines = explode( "\n", $taxonomy );

		$filtered = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			foreach ( $patterns as $pattern ) {

				if ( @preg_match( $pattern, '' ) !== false ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
					$regex = $pattern;
					if ( substr( $regex, - 1 ) !== 'i' ) {
						// Ensure case-insensitive
						$regex = rtrim( $regex, '/' ) . '/i';
					}
					if ( preg_match( $regex, $line ) ) {
						$filtered[] = $line;
						break;
					}
				} elseif ( false !== stripos( $line, $pattern ) ) {
					$filtered[] = $line;
					break;
				}
			}
		}

		return $filtered;
	}
}
