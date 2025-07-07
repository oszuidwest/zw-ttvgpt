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
 */
class TTVGPTApiErrorHandler {
	/**
	 * Convert HTTP status codes to localized error messages
	 *
	 * @param int $status_code HTTP status code from API response
	 * @return string Localized error message for end users
	 */
	public static function get_error_message( int $status_code ): string {
		$messages = array(
			400 => __( 'Ongeldige aanvraag', 'zw-ttvgpt' ),
			401 => __( 'Ongeldige API key', 'zw-ttvgpt' ),
			403 => __( 'Toegang geweigerd', 'zw-ttvgpt' ),
			404 => __( 'Model niet gevonden', 'zw-ttvgpt' ),
			429 => __( 'Te veel aanvragen, probeer later opnieuw', 'zw-ttvgpt' ),
			500 => __( 'OpenAI server fout', 'zw-ttvgpt' ),
			503 => __( 'OpenAI service tijdelijk niet beschikbaar', 'zw-ttvgpt' ),
		);

		return $messages[ $status_code ] ?? sprintf(
			/* translators: %d: HTTP status code */
			__( 'API fout: HTTP %d', 'zw-ttvgpt' ),
			$status_code
		);
	}
}
