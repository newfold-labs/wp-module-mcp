<?php

namespace BLU;

/** Tests recovery from an existing WooCommerce taxonomy term. */
class WooProductsErrorsWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {
	/** Existing terms are still resolved after REST failures become WP_Error. */
	public function test_existing_taxonomy_term_is_recovered(): void {
		$filter = function ( $result, $server, $request ) {
			if ( '/wc/v3/products/categories' === $request->get_route() ) {
				return new \WP_REST_Response(
					array(
						'code'    => 'term_exists',
						'message' => 'Exists',
						'data'    => array(
							'status'      => 400,
							'resource_id' => 42,
						),
					),
					400
				);
			}
			if ( '/wc/v3/products/categories/42' === $request->get_route() ) {
				return new \WP_REST_Response(
					array(
						'id'   => 42,
						'name' => 'Existing',
					),
					200
				);
			}
			return $result;
		};
		add_filter( 'rest_pre_dispatch', $filter, 10, 3 );
		try {
			$method = new \ReflectionMethod( \BLU\Abilities\WooProducts::class, 'add_product_taxonomies' );
			$method->setAccessible( true );
			$result = $method->invoke( new \BLU\Abilities\WooProducts(), array( 'Existing' ) );
			$this->assertSame( 200, $result['statusCode'] );
			$this->assertSame( 42, $result['message']['existing'][0]['id'] );
		} finally {
			remove_filter( 'rest_pre_dispatch', $filter, 10 );
		}
	}
}
