<?php
/**
 * This class manage the Abilities managed like Resources
 *
 * @package BLU\Abilities
 */

namespace BLU\Abilities;

/**
 * This class add static methods that return common resources
 */
class Resources {


	/**
	 * Read the official Google Product Taxonomy and return the results
	 *
	 * @return array|string
	 */
	public static function get_google_taxonomy_resource() {


		$locale = str_replace( '_', '-', get_locale() );

		$taxonomy = get_transient( 'blu/google-product-taxonomy-' . $locale );
		if ( false === $taxonomy ) {

			$content = self::retrieve_file( $locale );

			if ( is_wp_error( $content ) ) {
				return $content;
			} elseif ( 'not_found' === $content ) {
				$content = self::retrieve_file();
				if ( is_wp_error( $content ) ) {
					return $content;
				}
			}

			// Split into lines
			$lines = explode( "\n", $content );

			$taxonomy = [];

			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' === $line || strpos( $line, '#' ) === 0 ) {
					continue;
				}

				$line = preg_replace( '/^\d+\s*-\s*/', '', $line );

				$parts = array_map( 'trim', explode( '>', $line ) );

				$ref = &$taxonomy;
				foreach ( $parts as $part ) {
					if ( ! isset( $ref[ $part ] ) ) {
						$ref[ $part ] = [];
					}
					$ref = &$ref[ $part ];
				}
				unset( $ref );
			}
			set_transient( 'blu/google-product-taxonomy-' . $locale, $taxonomy, MONTH_IN_SECONDS );
		}

		return wp_json_encode(
			[
				'google_taxonomy' => $taxonomy,
			]
		);

	}

	/**
	 * Read the google product taxonomy file and get the content
	 *
	 * @param string $locale The locale.
	 *
	 * @return array|string|\WP_Error
	 */
	private static function retrieve_file( $locale = 'en-US' ) {
		$response = wp_remote_get( 'https://www.google.com/basepages/producttype/taxonomy-with-ids.' . $locale . '.txt' );
		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( 404 == wp_remote_retrieve_response_code( $response ) ) {
			return 'not_found';
		} else {
			return wp_remote_retrieve_body( $response );
		}
	}


	/**
	 *
	 * Get the product categories
	 *
	 * @return array|false|string
	 */
	public static function get_product_categories(){
		$page = 1;
		$categories  = [];
		$request = new \WP_REST_Request( 'GET', '/wc/v3/products/categories' );
		do {
			$request->set_query_params( [ 'page' => $page ] );
			$response = rest_do_request( $request );
			if( is_wp_error( $response ) ) {
				return blu_standardize_rest_response( $response );
			}
			$data     = $response->get_data();
			$total    = count( $data );
			foreach ( $data as $category ) {
				$categories[] = [ 'id' => $category['id'], 'name' => $category['name'], 'parent' => $category['parent'] ];
			}
			$page ++;
		}while( $total > 0 );

		return wp_json_encode( ['categories'=>$categories ] );
	}
}

