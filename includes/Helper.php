<?php
/**
 * Helper class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper class
 *
 * Common utility functions for the plugin
 *
 * @package ZW_TTVGPT
 */
class Helper {

	/**
	 * Remove all plugin-related transients from database
	 *
	 * @return void
	 */
	public static function cleanup_transients(): void {
		// Get all users to clean their rate limit transients.
		$users = get_users( array( 'fields' => 'ID' ) );

		foreach ( $users as $user_id ) {
			$transient_key = Constants::get_rate_limit_key( $user_id );
			delete_transient( $transient_key );
		}

		// Clean up orphaned transients from deleted users using direct query.
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				'_transient_' . Constants::RATE_LIMIT_PREFIX . '%'
			)
		);
	}

	/**
	 * Remove all plugin data including settings and transients
	 *
	 * @return void
	 */
	public static function cleanup_plugin_data(): void {
		SettingsManager::delete_settings();
		self::cleanup_transients();
	}

	/**
	 * Generate ACF field selectors for JavaScript usage
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
		return ZW_TTVGPT_VERSION . ( SettingsManager::is_debug_mode() ? '.' . time() : '' );
	}

	/**
	 * Check if a model requires the Responses API (GPT-5.1 only)
	 *
	 * Only gpt-5.1 is supported. The older gpt-5 model (without .1) is deprecated
	 * and replaced by gpt-5.1.
	 *
	 * Note: This function accepts any model starting with 'gpt-5' for forward
	 * compatibility with potential future GPT-5 variants, but currently only
	 * gpt-5.1 is tested and recommended.
	 *
	 * @param string $model Model identifier to check.
	 * @return bool True if model uses Responses API (GPT-5.* family), false otherwise.
	 */
	public static function is_gpt5_model( string $model ): bool {
		$model_lower = strtolower( $model );

		// Accept any gpt-5* model for Responses API (forward compatibility).
		// Note: gpt-5 (without .1) is deprecated - use gpt-5.1 instead.
		return str_starts_with( $model_lower, 'gpt-5' );
	}

	/**
	 * Count words in a string
	 *
	 * @param string $text Text to count words in.
	 * @return int Number of words.
	 */
	public static function count_words( string $text ): int {
		$text = trim( $text );
		if ( empty( $text ) ) {
			return 0;
		}
		return str_word_count( $text );
	}

	/**
	 * Check if plugin translations are available
	 *
	 * Uses has_translation() to check if translations exist without loading
	 * them first. This improves performance by avoiding unnecessary translation
	 * file loading.
	 *
	 * @return bool True if translations are available, false otherwise.
	 */
	public static function has_plugin_translation(): bool {
		return has_translation( 'zw-ttvgpt' );
	}

	/**
	 * Get localized strings with fallback if no translations available
	 *
	 * Uses has_translation() to optimize performance by skipping translation
	 * loading when not needed. Returns original strings if no translations exist.
	 *
	 * @param array<string, string> $strings Array of strings to translate (key => original text).
	 * @return array<string, string> Translated strings or originals if no translation available.
	 */
	public static function get_localized_strings( array $strings ): array {
		// Check if translations are available before processing.
		if ( ! self::has_plugin_translation() ) {
			return $strings;
		}

		$translated = array();
		foreach ( $strings as $key => $text ) {
			// Use __() for translation when available.
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
			$translated[ $key ] = __( $text, 'zw-ttvgpt' );
		}

		return $translated;
	}
}
