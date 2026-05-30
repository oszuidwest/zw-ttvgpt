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
 * Provides utility functions for transients, ACF fields, validation, and text handling.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class Helper {

	/**
	 * Regex fragment used to tokenize words consistently across PHP and JS.
	 *
	 * @since 1.0.0
	 */
	public const string WORD_TOKEN_PATTERN = '[\p{L}]+(?:[-\'][\p{L}]+)*';

	/**
	 * Removes plugin-owned transient rows from wp_options.
	 *
	 * Only called on deactivation and uninstall, so any persistent-object-cache
	 * entries that survive this DB cleanup have no live reader and expire on
	 * their own TTL.
	 *
	 * @since 1.0.0
	 */
	public static function cleanup_transients(): void {
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
	 * Checks whether an API key has the expected OpenAI prefix.
	 *
	 * Intentionally avoids strict key-shape validation so future key formats with
	 * the same prefix are accepted.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key API key to inspect.
	 * @return bool True if the key looks like an OpenAI key, false otherwise.
	 */
	public static function is_valid_api_key( string $api_key ): bool {
		return ! empty( $api_key ) && str_starts_with( $api_key, 'sk-' );
	}

	/**
	 * Retrieves the version string for asset cache management.
	 *
	 * In debug mode the busting suffix is the latest filemtime across the
	 * top-level assets directory, so the browser revalidates when files
	 * actually change rather than on every page load. Falls back to a
	 * per-request time() if no asset mtimes are readable. Memoized per-request
	 * to avoid repeated file stats.
	 *
	 * @since 1.0.0
	 *
	 * @return string Version string for asset enqueuing.
	 */
	public static function get_asset_version(): string {
		if ( ! SettingsManager::is_debug_mode() ) {
			return ZW_TTVGPT_VERSION;
		}

		static $debug_version = null;
		if ( null !== $debug_version ) {
			return $debug_version;
		}

		$assets = glob( ZW_TTVGPT_DIR . 'assets/*' );
		$latest = 0;
		if ( is_array( $assets ) ) {
			foreach ( $assets as $asset ) {
				$mtime = filemtime( $asset );
				if ( false !== $mtime && $mtime > $latest ) {
					$latest = $mtime;
				}
			}
		}

		// time() fallback is intentionally unlogged: debug-only path, cosmetic cache-busting impact only.
		$debug_version = ZW_TTVGPT_VERSION . '.' . ( $latest > 0 ? $latest : time() );
		return $debug_version;
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
	 * Converts editor HTML content to plain text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Raw HTML content to clean.
	 * @return string Plain text content.
	 */
	public static function html_to_text( string $content ): string {
		// Remove script-like elements including their content; wp_strip_all_tags() only removes tags.
		$content = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $content ) ?? $content;
		$content = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $content ) ?? $content;
		$content = preg_replace( '/<noscript\b[^>]*>.*?<\/noscript>/is', '', $content ) ?? $content;

		// Convert block elements to newlines for proper paragraph spacing.
		$content = preg_replace( '/<\/(p|div|h[1-6]|li|tr|blockquote)>/i', "\n", $content ) ?? $content;
		$content = preg_replace( '/<br\s*\/?>/i', "\n", $content ) ?? $content;

		$text = wp_strip_all_tags( $content );

		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Normalize whitespace.
		$text = preg_replace( '/[ \t]+/', ' ', $text ) ?? $text;
		$text = preg_replace( '/\n{3,}/', "\n\n", $text ) ?? $text;

		return trim( $text );
	}

	/**
	 * Ensures generated summary text ends with sentence-ending punctuation.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Summary text to normalize.
	 * @return string Summary text with terminal punctuation.
	 */
	public static function ensure_terminal_period( string $text ): string {
		$text = trim( $text );
		if ( '' === $text ) {
			return $text;
		}

		if ( 1 === preg_match( '/[.!?…][\'"”’»)\]\}]*$/u', $text ) ) {
			return $text;
		}

		foreach (
			array(
				'/[,;:]+([\'"”’»]+)$/u' => '.$1',
				'/([\'"”’»]+)[,;:]+$/u' => '.$1',
				'/[,;:]+([)\]\}]+)$/u'  => '$1.',
				'/([)\]\}]+)[,;:]+$/u'  => '$1.',
				'/[,;:]+$/u'            => '.',
			) as $pattern => $replacement
		) {
			$normalized = preg_replace( $pattern, $replacement, $text );
			if ( null !== $normalized && $normalized !== $text ) {
				return $normalized;
			}
		}

		return $text . '.';
	}

	/**
	 * Tokenizes words in a text using a Unicode-aware pattern.
	 *
	 * A word is one or more Unicode letters, optionally followed by groups of a
	 * hyphen or apostrophe and more letters (e.g. "zelf-rijdend", "auto's"). This
	 * mirrors the client-side counter in admin.js so client and server agree on
	 * counts for content containing diacritics (café, Curaçao) — `str_word_count`
	 * would split such words and produce inflated counts.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to tokenize.
	 * @return array Word tokens.
	 *
	 * @phpstan-return list<string>
	 */
	public static function tokenize_words( string $text ): array {
		$matched = preg_match_all( '/' . self::WORD_TOKEN_PATTERN . '/u', $text, $matches );
		if ( false === $matched ) {
			return array();
		}

		return $matches[0];
	}

	/**
	 * Counts words in a text using a Unicode-aware tokenizer.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Text to count words in.
	 * @return int Number of words.
	 */
	public static function count_words( string $text ): int {
		return count( self::tokenize_words( $text ) );
	}
}
