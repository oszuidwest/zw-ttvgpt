<?php
/**
 * Constants class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Constants class.
 *
 * Centralizes all plugin constants and provides default configuration values.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class Constants {
	/**
	 * WordPress option name for plugin settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string SETTINGS_OPTION_NAME = 'zw_ttvgpt_settings';

	/**
	 * Settings group identifier for WordPress Settings API.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string SETTINGS_GROUP = 'zw_ttvgpt_settings_group';

	/**
	 * Page slug for plugin settings page.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string SETTINGS_PAGE_SLUG = 'zw-ttvgpt-settings';

	/**
	 * Capability required to access plugin settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string REQUIRED_CAPABILITY = 'manage_options';

	/**
	 * Capability required to generate summaries.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string EDIT_CAPABILITY = 'edit_posts';

	/**
	 * Supported post type for summary generation.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string SUPPORTED_POST_TYPE = 'post';

	/**
	 * ACF field key for summary field.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string ACF_SUMMARY_FIELD = 'field_5f21a06d22c58';

	/**
	 * ACF field key for GPT marker field.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string ACF_GPT_MARKER_FIELD = 'field_66ad2a3105371';

	/**
	 * Meta key for AI-generated content.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string ACF_FIELD_AI_CONTENT = 'post_kabelkrant_content_gpt';

	/**
	 * Meta key for human-edited content.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string ACF_FIELD_HUMAN_CONTENT = 'post_kabelkrant_content';

	/**
	 * Meta key for Kabelkrant inclusion flag.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string ACF_FIELD_IN_KABELKRANT = 'post_in_kabelkrant';

	/**
	 * Default OpenAI model for summary generation.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string DEFAULT_MODEL = 'gpt-5.2';

	/**
	 * Base models that are supported.
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	public const array SUPPORTED_BASE_MODELS = array(
		'gpt-5.2',
		'gpt-5.1',
		'gpt-4.1',
		'gpt-4.1-mini',
		'gpt-4.1-nano',
	);

	/**
	 * Models that can be fine-tuned (GPT-5 does not support fine-tuning).
	 *
	 * @since 1.0.0
	 * @var array<string>
	 */
	public const array FINE_TUNABLE_MODELS = array(
		'gpt-4.1',
		'gpt-4.1-mini',
		'gpt-4.1-nano',
	);

	/**
	 * Maximum requests allowed per user in rate limit window.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int RATE_LIMIT_MAX_REQUESTS = 10;

	/**
	 * Rate limit time window in seconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int RATE_LIMIT_WINDOW = 60;

	/**
	 * Transient key prefix for rate limiting.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const string RATE_LIMIT_PREFIX = 'zw_ttvgpt_rate_';

	/**
	 * Minimum word count required in content to generate summary.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int MIN_WORD_COUNT = 100;

	/**
	 * Default word limit for generated summaries.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int DEFAULT_WORD_LIMIT = 100;

	/**
	 * Minimum allowed word limit for summaries.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int MIN_WORD_LIMIT = 50;

	/**
	 * Maximum allowed word limit for summaries.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int MAX_WORD_LIMIT = 500;

	/**
	 * Step value for word limit adjustment.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int WORD_LIMIT_STEP = 10;

	/**
	 * API request timeout in seconds for all models.
	 *
	 * GPT-5.1 uses reasoning_effort='low' for quality responses with good speed.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int API_TIMEOUT = 30;

	/**
	 * Maximum number of retry attempts for word count validation.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int MAX_RETRY_ATTEMPTS = 3;

	/**
	 * Minimum response ratio (as fraction of word limit).
	 *
	 * Responses shorter than this ratio are considered invalid.
	 * E.g., 0.2 means response must be at least 20% of word limit.
	 *
	 * @since 1.0.0
	 * @var float
	 */
	public const float MIN_RESPONSE_RATIO = 0.2;

	/**
	 * Success message display duration in milliseconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int SUCCESS_MESSAGE_TIMEOUT = 3000;

	/**
	 * Minimum typing animation delay in milliseconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int ANIMATION_DELAY_MIN = 20;

	/**
	 * Maximum typing animation delay in milliseconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int ANIMATION_DELAY_MAX = 50;

	/**
	 * Space character typing delay in milliseconds.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const int ANIMATION_DELAY_SPACE = 30;

	/**
	 * Default system prompt for AI summary generation.
	 *
	 * Optimized for GPT-5.1 with reasoning_effort='low'.
	 * Use %d as placeholder for word limit.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	// phpcs:ignore Generic.Files.LineLength.TooLong -- System prompt must remain as single continuous string
	public const string DEFAULT_SYSTEM_PROMPT = 'Je bent een eindredacteur voor tekst-tv. Denk eerst: wat is de kernboodschap van dit artikel? Vat het artikel samen in natuurlijk, vloeiend Nederlands voor een breed publiek. Schrijf volledige zinnen met een logische opbouw. Focus op de kernboodschap en de belangrijkste feiten. Gebruik korte, heldere zinnen maar pas op voor telegramstijl. Gebruik maximaal %d woorden. Schrijf alleen in het Nederlands en gebruik geen gedachtestreepjes.';

	/**
	 * Gets default plugin settings.
	 *
	 * @since 1.0.0
	 *
	 * @return array Default settings configuration.
	 *
	 * @phpstan-return PluginSettings
	 */
	public static function get_default_settings(): array {
		return array(
			'api_key'       => '',
			'model'         => self::DEFAULT_MODEL,
			'word_limit'    => self::DEFAULT_WORD_LIMIT,
			'system_prompt' => self::DEFAULT_SYSTEM_PROMPT,
			'debug_mode'    => false,
		);
	}

	/**
	 * Generates rate limit transient key for specific user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID to generate key for.
	 * @return string Transient key for rate limiting.
	 */
	public static function get_rate_limit_key( int $user_id ): string {
		return self::RATE_LIMIT_PREFIX . $user_id;
	}

	/**
	 * Checks if a model is supported.
	 *
	 * Supports both base models and fine-tuned variants (ft:gpt-4.1:...).
	 * Note: GPT-5 models cannot be fine-tuned.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model Model identifier to check.
	 * @return bool True if model is supported, false otherwise.
	 */
	public static function is_supported_model( string $model ): bool {
		$model_lower = strtolower( $model );

		// Check base models.
		if ( in_array( $model_lower, self::SUPPORTED_BASE_MODELS, true ) ) {
			return true;
		}

		// Check fine-tuned models (format: ft:base-model:org:suffix:id).
		// Only GPT-4.1 family supports fine-tuning.
		if ( str_starts_with( $model_lower, 'ft:' ) ) {
			foreach ( self::FINE_TUNABLE_MODELS as $base ) {
				if ( str_starts_with( $model_lower, 'ft:' . $base . ':' ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Gets the base model from a model string.
	 *
	 * For fine-tuned models (ft:gpt-4.1:...) this returns gpt-4.1.
	 *
	 * @since 1.0.0
	 *
	 * @param string $model Model identifier.
	 * @return string Base model identifier.
	 */
	public static function get_base_model( string $model ): string {
		$model_lower = strtolower( $model );

		if ( str_starts_with( $model_lower, 'ft:' ) ) {
			// Extract base model from ft:base-model:org:suffix:id.
			$parts = explode( ':', $model, 3 );
			if ( count( $parts ) >= 2 ) {
				return $parts[1];
			}
		}

		return $model;
	}
}
