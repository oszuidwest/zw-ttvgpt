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
 */
class TTVGPTSettingsManager {
	/**
	 * Settings cache
	 *
	 * @var array|null
	 */
	private static ?array $settings_cache = null;

	/**
	 * Retrieve all plugin settings with caching
	 *
	 * @return array Complete settings array with defaults applied
	 */
	public static function get_settings(): array {
		if ( null === self::$settings_cache ) {
			$settings             = get_option(
				TTVGPTConstants::SETTINGS_OPTION_NAME,
				TTVGPTConstants::get_default_settings()
			);
			self::$settings_cache = is_array( $settings ) ? $settings : TTVGPTConstants::get_default_settings();
		}

		return self::$settings_cache;
	}

	/**
	 * Retrieve specific setting value with fallback
	 *
	 * @param string $key           Setting key to retrieve
	 * @param mixed  $default_value Default value if setting not found
	 * @return mixed Setting value or default
	 */
	public static function get_setting( string $key, $default_value = null ) {
		$settings = self::get_settings();
		return $settings[ $key ] ?? $default_value;
	}

	/**
	 * Update plugin settings and refresh cache
	 *
	 * @param array $new_settings Settings to merge with existing values
	 * @return bool True if settings were successfully updated
	 */
	public static function update_settings( array $new_settings ): bool {
		$settings = self::get_settings();
		$settings = array_merge( $settings, $new_settings );

		$result = update_option( TTVGPTConstants::SETTINGS_OPTION_NAME, $settings );

		if ( $result ) {
			self::$settings_cache = $settings;
		}

		return $result;
	}

	/**
	 * Reset all settings to default values and clear cache
	 *
	 * @return bool True if settings were successfully reset
	 */
	public static function reset_settings(): bool {
		$result = update_option(
			TTVGPTConstants::SETTINGS_OPTION_NAME,
			TTVGPTConstants::get_default_settings()
		);

		if ( $result ) {
			self::$settings_cache = null;
		}

		return $result;
	}

	/**
	 * Completely remove plugin settings and clear cache
	 *
	 * @return bool True if settings were successfully deleted
	 */
	public static function delete_settings(): bool {
		self::$settings_cache = null;
		return delete_option( TTVGPTConstants::SETTINGS_OPTION_NAME );
	}

	/**
	 * Get typed setting values
	 *
	 * @return string API key
	 */
	public static function get_api_key(): string {
		$api_key = self::get_setting( 'api_key', '' );
		return is_string( $api_key ) ? $api_key : '';
	}

	/**
	 * Get model name
	 *
	 * @return string Model name
	 */
	public static function get_model(): string {
		$model = self::get_setting( 'model', TTVGPTConstants::DEFAULT_MODEL );
		return is_string( $model ) ? $model : TTVGPTConstants::DEFAULT_MODEL;
	}

	/**
	 * Get word limit
	 *
	 * @return int Word limit
	 */
	public static function get_word_limit(): int {
		$word_limit = self::get_setting( 'word_limit', TTVGPTConstants::DEFAULT_WORD_LIMIT );
		return is_numeric( $word_limit ) ? (int) $word_limit : TTVGPTConstants::DEFAULT_WORD_LIMIT;
	}

	/**
	 * Check if debug mode is enabled
	 *
	 * @return bool Debug mode status
	 */
	public static function is_debug_mode(): bool {
		return (bool) self::get_setting( 'debug_mode', false );
	}
}
