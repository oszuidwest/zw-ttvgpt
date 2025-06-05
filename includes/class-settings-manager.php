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
	 * Get all settings
	 *
	 * @return array
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
	 * Get a specific setting
	 *
	 * @param string $key           Setting key
	 * @param mixed  $default_value Default value if not found
	 * @return mixed
	 */
	public static function get_setting( string $key, $default_value = null ) {
		$settings = self::get_settings();
		return $settings[ $key ] ?? $default_value;
	}

	/**
	 * Update settings
	 *
	 * @param array $new_settings New settings to merge
	 * @return bool
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
	 * Reset settings to defaults
	 *
	 * @return bool
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
	 * Delete all settings
	 *
	 * @return bool
	 */
	public static function delete_settings(): bool {
		self::$settings_cache = null;
		return delete_option( TTVGPTConstants::SETTINGS_OPTION_NAME );
	}

	/**
	 * Get API key
	 *
	 * @return string
	 */
	public static function get_api_key(): string {
		$api_key = self::get_setting( 'api_key', '' );
		return is_string( $api_key ) ? $api_key : '';
	}

	/**
	 * Get model
	 *
	 * @return string
	 */
	public static function get_model(): string {
		$model = self::get_setting( 'model', TTVGPTConstants::DEFAULT_MODEL );
		return is_string( $model ) ? $model : TTVGPTConstants::DEFAULT_MODEL;
	}

	/**
	 * Get word limit
	 *
	 * @return int
	 */
	public static function get_word_limit(): int {
		$word_limit = self::get_setting( 'word_limit', TTVGPTConstants::DEFAULT_WORD_LIMIT );
		return is_numeric( $word_limit ) ? (int) $word_limit : TTVGPTConstants::DEFAULT_WORD_LIMIT;
	}

	/**
	 * Is debug mode enabled
	 *
	 * @return bool
	 */
	public static function is_debug_mode(): bool {
		return (bool) self::get_setting( 'debug_mode', false );
	}
}
