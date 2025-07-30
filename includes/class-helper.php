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
	 * Remove all plugin-related transients from database
	 *
	 * @return void
	 */
	public static function cleanup_transients(): void {
		// Get all users to clean their rate limit transients
		$users = get_users( array( 'fields' => 'ID' ) );

		foreach ( $users as $user_id ) {
			$transient_key = TTVGPTConstants::get_rate_limit_key( $user_id );
			delete_transient( $transient_key );
		}

		// Clean up orphaned transients from deleted users using direct query
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . TTVGPTConstants::RATE_LIMIT_PREFIX . '%'
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
