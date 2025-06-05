<?php
/**
 * Helper class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Helper class
 *
 * Common utility functions for the plugin
 */
class TTVGPTHelper {
	/**
	 * Create standardized error response array
	 *
	 * @param string $error_message Error message to include
	 * @return array{success: false, error: string} Error response with success flag and error message
	 */
	public static function error_response( string $error_message ): array {
		return array(
			'success' => false,
			'error'   => $error_message,
		);
	}

	/**
	 * Create standardized success response array
	 *
	 * @param mixed $data Response data to include
	 * @return array{success: true, data: mixed} Success response with success flag and data
	 */
	public static function success_response( $data ): array {
		return array(
			'success' => true,
			'data'    => $data,
		);
	}

	/**
	 * Remove all plugin-related transients from database
	 *
	 * @global \wpdb $wpdb WordPress database object
	 * @return void
	 */
	public static function cleanup_transients(): void {
		global $wpdb;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . TTVGPTConstants::RATE_LIMIT_PREFIX . '%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_timeout_' . TTVGPTConstants::RATE_LIMIT_PREFIX . '%'
			)
		);
	}

	/**
	 * Remove all plugin data including settings and transients
	 *
	 * @return void
	 */
	public static function cleanup_plugin_data(): void {
		TTVGPTSettingsManager::delete_settings();
		self::cleanup_transients();
	}

	/**
	 * Generate ACF field selectors for JavaScript usage
	 *
	 * @return array Field selector mappings for client-side access
	 */
	public static function get_acf_field_ids(): array {
		return array(
			'summary'    => 'acf-' . TTVGPTConstants::ACF_SUMMARY_FIELD,
			'gpt_marker' => 'acf-' . TTVGPTConstants::ACF_GPT_MARKER_FIELD,
		);
	}

	/**
	 * Validate OpenAI API key format
	 *
	 * @param string $api_key API key to validate
	 * @return bool True if key format is valid (starts with 'sk-')
	 */
	public static function is_valid_api_key( string $api_key ): bool {
		return ! empty( $api_key ) && strpos( $api_key, 'sk-' ) === 0;
	}

	/**
	 * Convert HTTP status codes to user-friendly Dutch error messages
	 *
	 * @param int $status_code HTTP status code from API response
	 * @return string Localized error message for end users
	 */
	public static function get_api_error_message( int $status_code ): string {
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
