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
	 * Transient key for notifying administrators about automatic model migration.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const string MODEL_MIGRATION_NOTICE_TRANSIENT = 'zw_ttvgpt_model_migration_notice';

	/**
	 * Clears the cached settings.
	 *
	 * @since 1.0.0
	 */
	public static function clear_cache(): void {
		wp_cache_delete( self::CACHE_KEY, self::CACHE_GROUP );
	}

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
		self::clear_cache();
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
	 * Invalid stored models are migrated to the default model to avoid repeated silent fallback.
	 *
	 * @since 1.0.0
	 *
	 * @return string Model identifier.
	 */
	public static function get_model(): string {
		$model = self::get_setting( 'model', Constants::DEFAULT_MODEL );
		if ( ! is_string( $model ) || ! Constants::is_supported_model( $model ) ) {
			self::migrate_invalid_model_setting( $model );
			return Constants::DEFAULT_MODEL;
		}

		return $model;
	}

	/**
	 * Retrieves and clears the model migration notice data.
	 *
	 * @since 1.0.0
	 *
	 * @return array|null Migration notice data, or null when unavailable.
	 *
	 * @phpstan-return array{old_model: string, new_model: string}|null
	 */
	public static function get_model_migration_notice(): ?array {
		$notice = get_transient( self::MODEL_MIGRATION_NOTICE_TRANSIENT );
		delete_transient( self::MODEL_MIGRATION_NOTICE_TRANSIENT );

		if (
			! is_array( $notice )
			|| ! isset( $notice['old_model'], $notice['new_model'] )
			|| ! is_string( $notice['old_model'] )
			|| ! is_string( $notice['new_model'] )
		) {
			return null;
		}

		return array(
			'old_model' => $notice['old_model'],
			'new_model' => $notice['new_model'],
		);
	}

	/**
	 * Migrates an invalid stored model setting to the default model.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $model Invalid stored model value.
	 */
	private static function migrate_invalid_model_setting( mixed $model ): void {
		$old_model = is_scalar( $model ) ? (string) $model : gettype( $model );
		$settings  = self::get_settings();

		$settings['model'] = Constants::DEFAULT_MODEL;
		update_option( Constants::SETTINGS_OPTION_NAME, $settings );
		self::clear_cache();

		set_transient(
			self::MODEL_MIGRATION_NOTICE_TRANSIENT,
			array(
				'old_model' => $old_model,
				'new_model' => Constants::DEFAULT_MODEL,
			),
			DAY_IN_SECONDS
		);

		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Migration should be visible outside debug mode.
			sprintf( 'ZW TTVGPT: unsupported stored OpenAI model "%s" migrated to "%s".', $old_model, Constants::DEFAULT_MODEL )
		);
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
