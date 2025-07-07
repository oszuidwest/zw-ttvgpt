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
	 * Create standardized response arrays
	 *
	 * @param string $error_message Error message to include
	 * @return array Error response array
	 */
	public static function error_response( string $error_message ): array {
		return array(
			'success' => false,
			'error'   => $error_message,
		);
	}

	/**
	 * Create success response array
	 *
	 * @param mixed $data Response data
	 * @return array Success response array
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
}
