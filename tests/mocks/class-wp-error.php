<?php
/**
 * Mock WP_Error class for unit testing.
 *
 * @package BLU\Tests\Mocks
 */

/**
 * Minimal WP_Error mock that mimics WordPress WP_Error behavior.
 */
class WP_Error {
	/**
	 * Error code.
	 *
	 * @var string
	 */
	private string $code;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	private string $message;

	/**
	 * Error data.
	 *
	 * @var mixed
	 */
	private $data;

	/**
	 * Stores errors.
	 *
	 * @var array
	 */
	public array $errors = array();

	/**
	 * Stores error data.
	 *
	 * @var array
	 */
	public array $error_data = array();

	/**
	 * Constructor.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 */
	public function __construct( $code = '', $message = '', $data = '' ) {
		if ( ! empty( $code ) ) {
			$this->add( $code, $message, $data );
		}
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	/**
	 * Add an error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Error message.
	 * @param mixed  $data    Error data.
	 */
	public function add( $code, $message, $data = '' ): void {
		$this->errors[ $code ][] = $message;
		if ( ! empty( $data ) ) {
			$this->error_data[ $code ] = $data;
		}
	}

	/**
	 * Get error code.
	 *
	 * @return string
	 */
	public function get_error_code(): string {
		return $this->code;
	}

	/**
	 * Get error message.
	 *
	 * @param string $code Optional error code.
	 * @return string
	 */
	public function get_error_message( $code = '' ): string {
		if ( empty( $code ) ) {
			return $this->message;
		}
		return $this->errors[ $code ][0] ?? '';
	}

	/**
	 * Get error data.
	 *
	 * @param string $code Optional error code.
	 * @return mixed
	 */
	public function get_error_data( $code = '' ) {
		if ( empty( $code ) ) {
			return $this->data;
		}
		return $this->error_data[ $code ] ?? '';
	}

	/**
	 * Check if there are errors.
	 *
	 * @return bool
	 */
	public function has_errors(): bool {
		return ! empty( $this->errors );
	}

	/**
	 * Get all error codes.
	 *
	 * @return array
	 */
	public function get_error_codes(): array {
		return array_keys( $this->errors );
	}
}

