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
 *
 * @package ZW_TTVGPT
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
	 * @return array Field selector mappings for client-side access.
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
	 * @param string $api_key API key to validate.
	 * @return bool True if key format is valid (starts with 'sk-'), false otherwise.
	 */
	public static function is_valid_api_key( string $api_key ): bool {
		return ! empty( $api_key ) && str_starts_with( $api_key, 'sk-' );
	}

	/**
	 * Build SQL date filter clause for post queries
	 *
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date   End date in Y-m-d format.
	 * @return string SQL WHERE clause or empty string if dates invalid.
	 */
	public static function build_date_filter_clause( string $start_date = '', string $end_date = '' ): string {
		if ( empty( $start_date ) || empty( $end_date ) ) {
			return '';
		}

		global $wpdb;
		$start_date = sanitize_text_field( $start_date );
		$end_date   = sanitize_text_field( $end_date );

		return $wpdb->prepare(
			'AND p.post_date >= %s AND p.post_date <= %s',
			$start_date . ' 00:00:00',
			$end_date . ' 23:59:59'
		);
	}

	/**
	 * Get asset version string with cache busting in debug mode
	 *
	 * @return string Version string for asset enqueuing.
	 */
	public static function get_asset_version(): string {
		return ZW_TTVGPT_VERSION . ( TTVGPTSettingsManager::is_debug_mode() ? '.' . time() : '' );
	}

	/**
	 * Check if a model is a GPT-5 model that requires the Responses API
	 *
	 * @param string $model Model identifier to check.
	 * @return bool True if model uses Responses API (GPT-5), false otherwise.
	 */
	public static function is_gpt5_model( string $model ): bool {
		$model_lower = strtolower( $model );

		// Check for GPT-5 family models
		return str_starts_with( $model_lower, 'gpt-5' );
	}
}
