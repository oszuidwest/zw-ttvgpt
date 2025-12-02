<?php
/**
 * Settings Manager class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Settings Manager class
 *
 * Handles all settings operations for the plugin
 *
 * @package ZW_TTVGPT
 */
class TTVGPTSettingsManager {
	/**
	 * Cache key for settings
	 */
	private const string CACHE_KEY = 'zw_ttvgpt_settings';

	/**
	 * Cache group for settings
	 */
	private const string CACHE_GROUP = 'zw_ttvgpt';

	/**
	 * Retrieve all plugin settings with caching
	 *
	 * @return array Complete settings array with defaults applied.
	 */
	public static function get_settings(): array {
		$settings = wp_cache_get( self::CACHE_KEY, self::CACHE_GROUP );

		if ( false === $settings ) {
			$settings = get_option(
				TTVGPTConstants::SETTINGS_OPTION_NAME,
				TTVGPTConstants::get_default_settings()
			);
			$settings = is_array( $settings ) ? $settings : TTVGPTConstants::get_default_settings();
			wp_cache_set( self::CACHE_KEY, $settings, self::CACHE_GROUP );
		}

		return is_array( $settings ) ? $settings : TTVGPTConstants::get_default_settings();
	}

	/**
	 * Retrieve specific setting value with fallback
	 *
	 * @param string $key           Setting key to retrieve.
	 * @param mixed  $default_value Default value if setting not found.
	 * @return mixed Setting value or default.
	 */
	public static function get_setting( string $key, $default_value = null ) {
		$settings = self::get_settings();
		return $settings[ $key ] ?? $default_value;
	}

	/**
	 * Update plugin settings and refresh cache
	 *
	 * @param array $new_settings Settings to merge with existing values.
	 * @return bool True if settings were successfully updated.
	 */
	public static function update_settings( array $new_settings ): bool {
		$settings = self::get_settings();
		$settings = array_merge( $settings, $new_settings );

		$result = update_option( TTVGPTConstants::SETTINGS_OPTION_NAME, $settings );

		if ( $result ) {
			wp_cache_set( self::CACHE_KEY, $settings, self::CACHE_GROUP );
		}

		return $result;
	}

	/**
	 * Reset all settings to default values and clear cache
	 *
	 * @return bool True if settings were successfully reset.
	 */
	public static function reset_settings(): bool {
		$result = update_option(
			TTVGPTConstants::SETTINGS_OPTION_NAME,
			TTVGPTConstants::get_default_settings()
		);

		if ( $result ) {
			wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
		}

		return $result;
	}

	/**
	 * Completely remove plugin settings and clear cache
	 *
	 * @return bool True if settings were successfully deleted.
	 */
	public static function delete_settings(): bool {
		wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
		return delete_option( TTVGPTConstants::SETTINGS_OPTION_NAME );
	}

	/**
	 * Get API key from settings
	 *
	 * @return string API key value.
	 */
	public static function get_api_key(): string {
		$api_key = self::get_setting( 'api_key', '' );
		return is_string( $api_key ) ? $api_key : '';
	}

	/**
	 * Get configured OpenAI model name
	 *
	 * @return string Model identifier.
	 */
	public static function get_model(): string {
		$model = self::get_setting( 'model', TTVGPTConstants::DEFAULT_MODEL );
		return is_string( $model ) ? $model : TTVGPTConstants::DEFAULT_MODEL;
	}

	/**
	 * Get configured word limit for summaries
	 *
	 * @return int Word limit value.
	 */
	public static function get_word_limit(): int {
		$word_limit = self::get_setting( 'word_limit', TTVGPTConstants::DEFAULT_WORD_LIMIT );
		return is_numeric( $word_limit ) ? (int) $word_limit : TTVGPTConstants::DEFAULT_WORD_LIMIT;
	}

	/**
	 * Get configured system prompt template
	 *
	 * @return string System prompt template with %d placeholder for word limit.
	 */
	public static function get_system_prompt(): string {
		$prompt = self::get_setting( 'system_prompt', TTVGPTConstants::DEFAULT_SYSTEM_PROMPT );
		return is_string( $prompt ) && ! empty( $prompt ) ? $prompt : TTVGPTConstants::DEFAULT_SYSTEM_PROMPT;
	}

	/**
	 * Check if debug mode is enabled
	 *
	 * @return bool True if debug mode is enabled, false otherwise.
	 */
	public static function is_debug_mode(): bool {
		return (bool) self::get_setting( 'debug_mode', false );
	}
}
