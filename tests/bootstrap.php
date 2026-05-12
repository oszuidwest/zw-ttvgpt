<?php
declare(strict_types=1);

// Point ABSPATH at the vendored WordPress install for tests that load WP pieces.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/vendor/roots/wordpress-no-content/' );
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

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
		return json_encode( $value, $flags, $depth );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '', string $scheme = 'admin' ): string {
		return 'https://example.test/wp-admin/' . $path;
	}
}

if ( ! function_exists( 'wp_cache_get' ) ) {
	function wp_cache_get( string $key, string $group = '', bool $force = false, ?bool &$found = null ): mixed {
		$found = false;
		return false;
	}
}

if ( ! function_exists( 'wp_cache_set' ) ) {
	function wp_cache_set( string $key, mixed $data, string $group = '', int $expire = 0 ): bool {
		return true;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	function wp_cache_delete( string $key, string $group = '' ): bool {
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $option, mixed $default_value = false ): mixed {
		return $default_value;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		return 123;
	}
}

$GLOBALS['zw_test_settings_errors'] = array();
if ( ! function_exists( 'add_settings_error' ) ) {
	function add_settings_error( string $setting, string $code, string $message, string $type = 'error' ): void {
		$GLOBALS['zw_test_settings_errors'][] = array(
			'setting' => $setting,
			'code'    => $code,
			'message' => $message,
			'type'    => $type,
		);
	}
}

$GLOBALS['zw_test_script_modules'] = array();
if ( ! function_exists( 'wp_enqueue_script_module' ) ) {
	function wp_enqueue_script_module( string $id, string $src = '', array $deps = array(), mixed $version = false, array $args = array() ): void {
		$GLOBALS['zw_test_script_modules'][] = array(
			'id'      => $id,
			'src'     => $src,
			'deps'    => $deps,
			'version' => $version,
			'args'    => $args,
		);
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
