<?php
/**
 * API Error Handler class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * API Error Handler class
 *
 * Centralized handling of API error messages
 *
 * @package ZW_TTVGPT
 */
class ApiErrorHandler {
	/**
	 * Convert HTTP status codes to localized error messages
	 *
	 * @param int $status_code HTTP status code from API response.
	 * @return string Localized error message for end users.
	 */
	public static function get_error_message( int $status_code ): string {
		$messages = array(
			400 => __( 'Ongeldige aanvraag - check je instellingen', 'zw-ttvgpt' ),
			401 => __( 'API-sleutel klopt niet', 'zw-ttvgpt' ),
			403 => __( 'Geen toegang - check API-rechten', 'zw-ttvgpt' ),
			404 => __( 'AI-model bestaat niet', 'zw-ttvgpt' ),
			429 => __( 'API-limiet bereikt - wacht even', 'zw-ttvgpt' ),
			500 => __( 'OpenAI heeft problemen - probeer later', 'zw-ttvgpt' ),
			503 => __( 'OpenAI offline - probeer later', 'zw-ttvgpt' ),
		);

		return $messages[ $status_code ] ?? sprintf(
			/* translators: %d: HTTP status code */
			__( 'API fout: HTTP %d', 'zw-ttvgpt' ),
			$status_code
		);
	}
}
