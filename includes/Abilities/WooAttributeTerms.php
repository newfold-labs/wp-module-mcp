<?php

declare( strict_types=1 );

namespace BLU\Abilities;

/**
 * One workflow for creating a WooCommerce global attribute and its terms.
 */
class WooAttributeTerms {
	private const ATTRIBUTE_ROUTE = '/wc/v3/products/attributes';
	private const TERMS_ROUTE     = '/wc/v3/products/attributes/(?P<attribute_id>[\d]+)/terms';

	/** Register the workflow only when both native WooCommerce write routes exist. */
	public function __construct() {
		$routes = rest_get_server()->get_routes();
		if ( ! $this->supports_post( $routes, self::ATTRIBUTE_ROUTE ) || ! $this->supports_post( $routes, self::TERMS_ROUTE ) ) {
			return;
		}

		blu_register_ability(
			'blu/wc-add-product-attribute',
			array(
				'label'               => 'Add WooCommerce Product Attribute with Terms',
				'description'         => 'Create one WooCommerce global product attribute and optionally its terms. Reports each term failure and the created attribute ID for recovery.',
				'category'            => 'blu-mcp',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'name'         => array(
							'type'      => 'string',
							'minLength' => 1,
						),
						'slug'         => array( 'type' => 'string' ),
						'type'         => array( 'type' => 'string' ),
						'order_by'     => array( 'type' => 'string' ),
						'has_archives' => array( 'type' => 'boolean' ),
						'terms'        => array(
							'type'        => 'array',
							'description' => 'Term names to create with the new attribute.',
							'items'       => array(
								'type'      => 'string',
								'minLength' => 1,
							),
						),
					),
					'required'   => array( 'name' ),
				),
				'execute_callback'    => array( $this, 'execute' ),
				// The native WooCommerce REST callbacks decide whether this user may write.
				'permission_callback' => '__return_true',
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);
	}

	/**
	 * Check a registered route for a POST endpoint.
	 *
	 * @param array  $routes Registered REST routes.
	 * @param string $route  Route pattern.
	 * @return bool Whether a POST endpoint exists.
	 */
	private function supports_post( array $routes, string $route ): bool {
		if ( ! isset( $routes[ $route ] ) ) {
			return false;
		}
		foreach ( $routes[ $route ] as $endpoint ) {
			$methods = isset( $endpoint['methods'] ) ? $endpoint['methods'] : array();
			if ( ( is_array( $methods ) && ! empty( $methods['POST'] ) ) || ( is_string( $methods ) && false !== strpos( $methods, 'POST' ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Execute both operations through the registered WooCommerce REST controllers.
	 *
	 * @param array $input Validated ability input.
	 * @return array Standard ability result, with explicit partial status when needed.
	 */
	public function execute( array $input ): array {
		$terms = $input['terms'] ?? array();
		unset( $input['terms'] );

		$request = new \WP_REST_Request( 'POST', self::ATTRIBUTE_ROUTE );
		$request->set_body_params( $input );
		$response = rest_do_request( $request );
		$result   = blu_standardize_rest_response( $response );
		if ( 'error' === $result['status'] || empty( $terms ) ) {
			return $result;
		}

		$attribute = $result['message'];
		$created   = array();
		$failed    = array();
		$id        = isset( $attribute['id'] ) ? absint( $attribute['id'] ) : 0;
		$slug      = isset( $attribute['slug'] ) ? $attribute['slug'] : '';
		if ( ! $id || ! is_string( $slug ) || 0 !== strpos( $slug, 'pa_' ) ) {
			return array(
				'statusCode' => 207,
				'status'     => 'partial',
				'message'    => array(
					'attribute' => $attribute,
					'terms'     => array(
						'created' => $created,
						'failed'  => $terms,
					),
					'error'     => 'WooCommerce did not return an attribute ID and taxonomy slug.',
				),
			);
		}

		// WooCommerce registers attribute taxonomies at init. A newly created one
		// needs a temporary registration for term operations in this same request.
		if ( ! taxonomy_exists( $slug ) ) {
			register_taxonomy( $slug, array( 'product' ), array( 'public' => false ) );
		}

		foreach ( $terms as $name ) {
			$term_request = new \WP_REST_Request( 'POST', self::ATTRIBUTE_ROUTE . '/' . $id . '/terms' );
			$term_request->set_body_params( array( 'name' => $name ) );
			$term_result = blu_standardize_rest_response( rest_do_request( $term_request ) );
			if ( 'error' === $term_result['status'] ) {
				$failed[] = array(
					'name'       => $name,
					'statusCode' => $term_result['statusCode'],
					'error'      => $term_result['message'],
				);
			} else {
				$created[] = $term_result['message'];
			}
		}

		return array(
			'statusCode' => $failed ? 207 : 201,
			'status'     => $failed ? 'partial' : 'success',
			'message'    => array(
				'attribute' => $attribute,
				'terms'     => array(
					'created' => $created,
					'failed'  => $failed,
				),
			),
		);
	}
}
