<?php
/**
 * Helper class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class.
 *
 * Provides utility functions for transients, ACF fields, and validation.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class Helper {

	/**
	 * Removes all plugin-related transients from database.
	 *
	 * @since 1.0.0
	 */
	public static function cleanup_transients(): void {
		// Get all users to clean their rate limit transients.
		$users = get_users( array( 'fields' => 'ID' ) );

		foreach ( $users as $user_id ) {
			$transient_key = Constants::get_rate_limit_key( $user_id );
			delete_transient( $transient_key );
		}

		// Clean up orphaned transients using direct query.
		// Delete both _transient_ and _transient_timeout_ entries.
		global $wpdb;

		// Clean rate limit transients (value + timeout).
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_' . Constants::RATE_LIMIT_PREFIX . '%',
				'_transient_timeout_' . Constants::RATE_LIMIT_PREFIX . '%'
			)
		);

		// Clean export transients (value + timeout).
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_zw_ttvgpt_export_%',
				'_transient_timeout_zw_ttvgpt_export_%'
			)
		);
	}

	/**
	 * Removes all plugin data including settings and transients.
	 *
	 * @since 1.0.0
	 */
	public static function cleanup_plugin_data(): void {
		SettingsManager::delete_settings();
		self::cleanup_transients();
	}

	/**
	 * Retrieves ACF field CSS selector IDs for client-side access.
	 *
	 * @since 1.0.0
	 *
	 * @return array Field selector mappings for client-side access.
	 *
	 * @phpstan-return array{summary: string, gpt_marker: string}
	 */
	public static function get_acf_field_ids(): array {
		return array(
			'summary'    => 'acf-' . Constants::ACF_SUMMARY_FIELD,
			'gpt_marker' => 'acf-' . Constants::ACF_GPT_MARKER_FIELD,
		);
	}

	/**
	 * Validates OpenAI API key format.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key API key to validate.
	 * @return bool True if key format is valid (starts with 'sk-'), false otherwise.
	 */
	public static function is_valid_api_key( string $api_key ): bool {
		return ! empty( $api_key ) && str_starts_with( $api_key, 'sk-' );
	}

	/**
	 * Creates a SQL WHERE clause for filtering posts by date range.
	 *
	 * @since 1.0.0
	 *
	 * @param string $start_date Optional. Start date in Y-m-d format. Default empty.
	 * @param string $end_date   Optional. End date in Y-m-d format. Default empty.
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
	 * Retrieves the version string for asset cache management.
	 *
	 * @since 1.0.0
	 *
	 * @return string Version string for asset enqueuing.
	 */
	public static function get_asset_version(): string {
		return ZW_TTVGPT_VERSION . ( SettingsManager::is_debug_mode() ? '.' . time() : '' );
	}

	/**
	 * Determines whether a model uses the Responses API.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model Model identifier to check.
	 * @return bool True if model uses Responses API (GPT-5.* family), false otherwise.
	 */
	public static function is_gpt5_model( string $model ): bool {
		// Use base model for fine-tuned models.
		$base_model  = Constants::get_base_model( $model );
		$model_lower = strtolower( $base_model );

		// Accept any gpt-5* model for Responses API (forward compatibility).
		// Note: gpt-5 (without .1) is deprecated - use gpt-5.1 instead.
		return str_starts_with( $model_lower, 'gpt-5' );
	}

	/**
	 * Retrieves the word count for a text string.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to count words in.
	 * @return int Number of words.
	 */
	public static function count_words( string $text ): int {
		return str_word_count( trim( $text ) );
	}
}
