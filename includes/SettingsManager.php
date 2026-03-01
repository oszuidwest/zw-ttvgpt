<?php
/**
 * Settings Manager class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Manager class.
 *
 * Manages plugin settings retrieval and caching.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class SettingsManager {
	/**
	 * Cache key for settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const string CACHE_KEY = 'zw_ttvgpt_settings';

	/**
	 * Cache group for settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const string CACHE_GROUP = 'zw_ttvgpt';

	/**
	 * Retrieves all plugin settings with caching.
	 *
	 * @since 1.0.0
	 *
	 * @return array Complete settings array with defaults applied.
	 *
	 * @phpstan-return PluginSettings
	 */
	public static function get_settings(): array {
		$settings = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP );

		if ( false === $settings ) {
			$settings = get_option(
				Constants::SETTINGS_OPTION_NAME,
				Constants::get_default_settings()
			);
			$settings = is_array( $settings ) ? $settings : Constants::get_default_settings();
			wp_cache_set( self::CACHE_KEY, $settings, self::CACHE_GROUP );
		}

		return is_array( $settings ) ? $settings : Constants::get_default_settings();
	}

	/**
	 * Retrieves a specific setting value with fallback.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key           Setting key to retrieve.
	 * @param mixed  $default_value Default value if setting not found.
	 * @return mixed Setting value or default.
	 */
	private static function get_setting( string $key, $default_value = null ) {
		$settings = self::get_settings();
		return $settings[ $key ] ?? $default_value;
	}

	/**
	 * Removes all plugin settings from the database.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if settings were successfully deleted.
	 */
	public static function delete_settings(): bool {
		wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
		return delete_option( Constants::SETTINGS_OPTION_NAME );
	}

	/**
	 * Gets the API key from settings.
	 *
	 * @since 1.0.0
	 *
	 * @return string API key value.
	 */
	public static function get_api_key(): string {
		$api_key = self::get_setting( 'api_key', '' );
		return is_string( $api_key ) ? $api_key : '';
	}

	/**
	 * Gets the configured OpenAI model name.
	 *
	 * @since 1.0.0
	 *
	 * @return string Model identifier.
	 */
	public static function get_model(): string {
		$model = self::get_setting( 'model', Constants::DEFAULT_MODEL );
		return is_string( $model ) ? $model : Constants::DEFAULT_MODEL;
	}

	/**
	 * Gets the configured word limit for summaries.
	 *
	 * @since 1.0.0
	 *
	 * @return int Word limit value.
	 */
	public static function get_word_limit(): int {
		$word_limit = self::get_setting( 'word_limit', Constants::DEFAULT_WORD_LIMIT );
		return is_numeric( $word_limit ) ? (int) $word_limit : Constants::DEFAULT_WORD_LIMIT;
	}

	/**
	 * Gets the configured system prompt template.
	 *
	 * @since 1.0.0
	 *
	 * @return string System prompt template with %d placeholder for word limit.
	 */
	public static function get_system_prompt(): string {
		$prompt = self::get_setting( 'system_prompt', Constants::DEFAULT_SYSTEM_PROMPT );
		return is_string( $prompt ) && ! empty( $prompt ) ? $prompt : Constants::DEFAULT_SYSTEM_PROMPT;
	}

	/**
	 * Checks if debug mode is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if debug mode is enabled, false otherwise.
	 */
	public static function is_debug_mode(): bool {
		return (bool) self::get_setting( 'debug_mode', false );
	}
}
