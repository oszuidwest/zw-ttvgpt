<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/RecordingLogger.php';

// In-memory transient store used by tests that exercise the rate limiter.
$GLOBALS['zw_test_transients']            = array();
$GLOBALS['zw_test_set_transient_return']  = true;
$GLOBALS['zw_test_set_transient_calls']   = array();

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * @return mixed
	 */
	function get_transient( string $key ) {
		return $GLOBALS['zw_test_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $key, mixed $value, int $expiration = 0 ): bool {
		$GLOBALS['zw_test_set_transient_calls'][] = array(
			'key'        => $key,
			'value'      => $value,
			'expiration' => $expiration,
		);
		if ( false === $GLOBALS['zw_test_set_transient_return'] ) {
			return false;
		}
		$GLOBALS['zw_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private string $code;
		private string $message;
		private mixed $data;

		public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		public function get_error_code(): string {
			return $this->code;
		}

		public function get_error_message(): string {
			return $this->message;
		}

		public function get_error_data(): mixed {
			return $this->data;
		}
	}
}
