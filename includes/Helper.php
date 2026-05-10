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

		// Clean legacy export transients left by removed training-data export tooling.
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
		$model_lower = strtolower( $model );

		// Accept any gpt-5* model for Responses API (forward compatibility).
		return str_starts_with( $model_lower, 'gpt-5' );
	}

	/**
	 * Counts words in a text using a Unicode-aware tokenizer.
	 *
	 * A word is one or more Unicode letters, optionally followed by groups of a
	 * hyphen or apostrophe and more letters (e.g. "zelf-rijdend", "auto's"). This
	 * mirrors the client-side counter in admin.js so client and server agree on
	 * counts for content containing diacritics (café, Curaçao) — `str_word_count`
	 * would split such words and produce inflated counts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to count words in.
	 * @return int Number of words.
	 */
	public static function count_words( string $text ): int {
		return (int) preg_match_all( '/[\p{L}]+([-\'][\p{L}]+)*/u', $text );
	}
}
