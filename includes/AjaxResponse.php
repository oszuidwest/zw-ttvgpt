<?php
/**
 * AJAX Response helper class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Response helper class.
 *
 * Provides centralized helper methods for consistent AJAX responses.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class AjaxResponse {
	/**
	 * Sends a standardized error response and terminates execution.
	 *
	 * Status code mapping:
	 * - missing_config → 503 (service unavailable)
	 * - invalid_input → 400 (bad request)
	 * - rate_limited → 429 (too many requests)
	 * - api_error → 502 (bad gateway) for API errors
	 * - server_error → 500 (internal server error)
	 *
	 * @since 1.0.0
	 *
	 * @param string $code    Error code for debugging.
	 * @param string $message User-friendly error message.
	 * @param int    $status  HTTP status code. Default 400.
	 * @return never
	 */
	public static function error( string $code, string $message, int $status = 400 ): never {
		wp_send_json_error(
			array(
				'code'    => $code,
				'message' => $message,
			),
			$status
		);
	}

	/**
	 * Sends a standardized success response and terminates execution.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Response data.
	 * @return never
	 */
	public static function success( array $data ): never {
		wp_send_json_success( $data );
	}
}
