<?php

namespace BLU\Validation;
use NewfoldLabs\WP\Module\Data\HiiveConnection;
use WP_Error;

/**
 * Class HiiveProductVerifier
 * Handles product verification via Hiive API.
 * @package BLU\Validation
 */
class HiiveProductVerifier {
	/**
	 * Hiive product verification endpoint.
	 * @var string
	 */
	private const NFD_BLU_JWT_HIIVE_VERIFY_ENDPOINT = '/api/sites/v1/customer/products/verify';
	/**
	 * Cache key for storing verified tokens.
	 * @var string
	 */
	private const NFD_BLU_JWT_VERIFIED_TOKEN_CACHE_KEY = 'blu_jwt_product_verify';
	/**
	 * Cache TTL for verified tokens in seconds.
	 * @var int
	 */
	private const NFD_BLU_JWT_VERIFIED_TOKEN_CACHE_TTL = 600; // 10 minutes
	/**
	 * Verifies product access for a user using a token.
	 * @param string $token The token to verify.
	 * @param string $userId The user ID associated with the token.
	 * @return bool|WP_Error Returns true if verification is successful, or a WP_Error object if verification fails.
	 */
	public static function verify_product_access( string $token, string $userId ): bool|WP_Error {

		$cached = get_transient( self::NFD_BLU_JWT_VERIFIED_TOKEN_CACHE_KEY . "_$userId" );
		if ( false !== $cached ) {
			if ( isset( $cached['token'] ) && $cached['token'] === $token ) {
				if ( isset( $cached['status'] ) && ( 'true' === $cached['status'] || true === $cached['status'] ) ) {
					return true;
				} else {
					return new WP_Error( 'hiive_product_verification_failed', 'Product verification failed', array( 'status' => $cached['status'] ) );
				}
			}
		}

		$connection = new HiiveConnection();
		$response   = $connection->hiive_request( self::NFD_BLU_JWT_HIIVE_VERIFY_ENDPOINT, array(
			'userId'   => $userId,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( ! in_array( $status_code, array( 200, 201 ), true ) ) {
			return new WP_Error( $status_code, wp_remote_retrieve_response_message( $response ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['response'] ) ) {
			$value = array(
				'token'  => $token,
				'status' => $data['response']
			);
			set_transient( self::NFD_BLU_JWT_VERIFIED_TOKEN_CACHE_KEY . "_$userId", $value, apply_filters( 'blu_jwt_product_verify_cache_ttl', self::NFD_BLU_JWT_VERIFIED_TOKEN_CACHE_TTL ) );

			if ( 'true' === $data['response'] || true === $data['response'] ) {
				return true;
			} else {
				return new WP_Error( 'hiive_product_verification_failed', 'Product verification failed', $data );
			}
		} else {
			return new WP_Error( 'hiive_product_verification_no_response', 'No verification response from Hiive', $data );
		}
	}
}
