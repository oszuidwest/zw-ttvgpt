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
	 * Transient key that throttles corrupted-settings logging.
	 *
	 * Without this, a non-array stored option would be re-detected and re-logged
	 * on every request that misses the per-request object cache.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const string CORRUPT_SETTINGS_LOG_THROTTLE_TRANSIENT = 'zw_ttvgpt_corrupt_settings_logged';

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

		if ( is_array( $settings ) ) {
			return $settings;
		}

		$settings = get_option(
			Constants::SETTINGS_OPTION_NAME,
			Constants::get_default_settings()
		);
		if ( ! is_array( $settings ) ) {
			self::log_corrupt_settings( $settings );
			$settings = Constants::get_default_settings();
		}

		wp_cache_set( self::CACHE_KEY, $settings, self::CACHE_GROUP );
		return $settings;
	}

	/**
	 * Logs a corrupted (non-array) stored settings option, throttled to one entry per hour.
	 *
	 * Only safe metadata is recorded: option name, detected type, and string length for
	 * string values. The raw value is never logged because a corrupted option may still
	 * contain fragments of sensitive settings such as the API key.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $settings The non-array value returned from get_option().
	 */
	private static function log_corrupt_settings( mixed $settings ): void {
		if ( false !== get_transient( self::CORRUPT_SETTINGS_LOG_THROTTLE_TRANSIENT ) ) {
			return;
		}

		$context = array(
			'option_name'   => Constants::SETTINGS_OPTION_NAME,
			'detected_type' => gettype( $settings ),
		);
		if ( is_string( $settings ) ) {
			$context['string_length'] = strlen( $settings );
		}

		// Logger::error() writes unconditionally, so the debug-mode flag is irrelevant here.
		( new Logger() )->error( 'Stored settings option is not an array; falling back to defaults', $context );

		set_transient( self::CORRUPT_SETTINGS_LOG_THROTTLE_TRANSIENT, true, HOUR_IN_SECONDS );
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
	 * Gets the configured OpenAI model name, falling back to the default for invalid stored values.
	 *
	 * @since 1.0.0
	 *
	 * @return string Model identifier.
	 */
	public static function get_model(): string {
		$model = self::get_setting( 'model', Constants::DEFAULT_MODEL );
		if ( ! is_string( $model ) || ! Constants::is_supported_model( $model ) ) {
			return Constants::DEFAULT_MODEL;
		}

		return $model;
	}

	/**
	 * Migrates an unsupported stored model setting to the default model.
	 *
	 * @since 1.0.0
	 *
	 * @param Logger $logger Logger instance for migration logging.
	 * @return bool True when a migration was performed, false otherwise.
	 */
	public static function migrate_invalid_model_if_needed( Logger $logger ): bool {
		$model = self::get_setting( 'model', Constants::DEFAULT_MODEL );
		if ( is_string( $model ) && Constants::is_supported_model( $model ) ) {
			return false;
		}

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

		$logger->error(
			'Unsupported stored OpenAI model migrated to default',
			array(
				'old_model' => $old_model,
				'new_model' => Constants::DEFAULT_MODEL,
			)
		);

		return true;
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

		if ( ! is_array( $notice ) ) {
			return null;
		}

		return $notice;
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
