<?php

namespace Helper;

/**
 * Helper class for WPUnit tests.
 *
 * All public methods declared in this helper class will be available in $I.
 */
class Wpunit extends \Codeception\Module {

	/**
	 * Generate a mock JWT token for testing.
	 *
	 * @param array $payload Token payload overrides.
	 * @return string
	 */
	public function generateMockJwt( array $payload = array() ): string {
		$default_payload = array(
			'iss' => 'jarvis-jwt',
			'aud' => 'production',
			'sub' => 'user:12345',
			'iat' => time(),
			'exp' => time() + 3600,
		);

		$payload = array_merge( $default_payload, $payload );

		$header    = $this->base64UrlEncode( json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$body      = $this->base64UrlEncode( json_encode( $payload ) );
		$signature = $this->base64UrlEncode( 'mock_signature_for_testing' );

		return sprintf( '%s.%s.%s', $header, $body, $signature );
	}

	/**
	 * Set the Authorization header for testing.
	 *
	 * @param string $value Header value.
	 */
	public function setAuthorizationHeader( string $value ): void {
		$_SERVER['HTTP_AUTHORIZATION'] = $value;
	}

	/**
	 * Clear the Authorization header.
	 */
	public function clearAuthorizationHeader(): void {
		unset( $_SERVER['HTTP_AUTHORIZATION'] );
		unset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] );
		unset( $_SERVER['PHP_AUTH_USER'] );
		unset( $_SERVER['PHP_AUTH_PW'] );
	}

	/**
	 * Set Basic Auth credentials.
	 *
	 * @param string $username Username.
	 * @param string $password Password.
	 */
	public function setBasicAuth( string $username, string $password ): void {
		$_SERVER['PHP_AUTH_USER']      = $username;
		$_SERVER['PHP_AUTH_PW']        = $password;
		$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( $username . ':' . $password );
	}

	/**
	 * Set REQUEST_URI for MCP endpoint testing.
	 *
	 * @param string $path Path to set.
	 */
	public function setMcpRequestUri( string $path = '/wp-json/blu/mcp' ): void {
		$_SERVER['REQUEST_URI'] = $path;
	}

	/**
	 * Base64 URL encode.
	 *
	 * @param string $data Data to encode.
	 * @return string
	 */
	private function base64UrlEncode( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}

