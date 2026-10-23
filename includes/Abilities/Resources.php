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
				'description'         => 'Search the current Google Product Taxonomy for matching Shopping categories. Returns each match as { id, path }, where id is the numeric Google category ID. This ability fetches the taxonomy for the site; the assistant does not need separate web access.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'patterns' => array(
							'type'        => 'array',
							'description' => 'One to five category search terms, e.g. ["running shoes"]. Each item is a string; regular expressions are optional.',
							'items'       => array( 'type' => 'string' ),
							'minItems'    => 1,
							'maxItems'    => 5,
						),
					),
					'required'   => array( 'patterns' ),
				),
				'execute_callback'    => function ( $input ) {
					$patterns = $input['patterns'] ?? array();

					$is_valid = blu_is_valid_input_array( $patterns, 'patterns', 1, 5 );
					if ( is_wp_error( $is_valid ) ) {
						return blu_standardize_rest_response( $is_valid );
					}
					$locale = str_replace( '_', '-', get_locale() );

					// v2 cache keeps each category's numeric ID; v1 stored paths only.
					$cache_key = 'blu/google-product-taxonomy-v2-' . $locale;
					$taxonomy  = get_transient( $cache_key );
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

							$taxonomy .= $line . "\n";
						}
						set_transient( $cache_key, $taxonomy, MONTH_IN_SECONDS );
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
	 * Patterns match the category path only, never its numeric ID.
	 *
	 * @param string $taxonomy The taxonomy, one "ID - Path" line per category.
	 * @param array  $patterns The patterns
	 *
	 * @return array List of arrays with integer `id` and string `path`.
	 */
	private function filter_google_taxonomies( $taxonomy, $patterns ) {
		$lines = explode( "\n", $taxonomy );

		$filtered = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( ! preg_match( '/^(\d+)\s*-\s*(.+)$/', $line, $parts ) ) {
				continue;
			}
			$category = array(
				'id'   => (int) $parts[1],
				'path' => $parts[2],
			);

			foreach ( $patterns as $pattern ) {

				if ( @preg_match( $pattern, '' ) !== false ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
					$regex = $pattern;
					if ( substr( $regex, - 1 ) !== 'i' ) {
						// Ensure case-insensitive
						$regex = rtrim( $regex, '/' ) . '/i';
					}
					if ( preg_match( $regex, $category['path'] ) ) {
						$filtered[] = $category;
						break;
					}
				} elseif ( false !== stripos( $category['path'], $pattern ) ) {
					$filtered[] = $category;
					break;
				}
			}
		}

		return $filtered;
	}
}
