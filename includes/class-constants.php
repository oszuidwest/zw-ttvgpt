<?php
/**
 * Constants class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Constants class
 *
 * Centralizes all plugin constants and provides default configuration values
 *
 * @package ZW_TTVGPT
 */
class TTVGPTConstants {
	/**
	 * WordPress option name for plugin settings.
	 *
	 * @var string
	 */
	const SETTINGS_OPTION_NAME = 'zw_ttvgpt_settings';

	/**
	 * Settings group identifier for WordPress Settings API.
	 *
	 * @var string
	 */
	const SETTINGS_GROUP = 'zw_ttvgpt_settings_group';

	/**
	 * Page slug for plugin settings page.
	 *
	 * @var string
	 */
	const SETTINGS_PAGE_SLUG = 'zw-ttvgpt-settings';

	/**
	 * Capability required to access plugin settings.
	 *
	 * @var string
	 */
	const REQUIRED_CAPABILITY = 'manage_options';

	/**
	 * Capability required to generate summaries.
	 *
	 * @var string
	 */
	const EDIT_CAPABILITY = 'edit_posts';

	/**
	 * Supported post type for summary generation.
	 *
	 * @var string
	 */
	const SUPPORTED_POST_TYPE = 'post';

	/**
	 * ACF field key for summary field.
	 *
	 * @var string
	 */
	const ACF_SUMMARY_FIELD = 'field_5f21a06d22c58';

	/**
	 * ACF field key for GPT marker field.
	 *
	 * @var string
	 */
	const ACF_GPT_MARKER_FIELD = 'field_66ad2a3105371';

	/**
	 * Meta key for AI-generated content.
	 *
	 * @var string
	 */
	const ACF_FIELD_AI_CONTENT = 'post_kabelkrant_content_gpt';

	/**
	 * Meta key for human-edited content.
	 *
	 * @var string
	 */
	const ACF_FIELD_HUMAN_CONTENT = 'post_kabelkrant_content';

	/**
	 * Meta key for Kabelkrant inclusion flag.
	 *
	 * @var string
	 */
	const ACF_FIELD_IN_KABELKRANT = 'post_in_kabelkrant';

	/**
	 * Default OpenAI model for summary generation.
	 *
	 * @var string
	 */
	const DEFAULT_MODEL = 'gpt-5.1';

	/**
	 * Maximum requests allowed per user in rate limit window.
	 *
	 * @var int
	 */
	const RATE_LIMIT_MAX_REQUESTS = 10;

	/**
	 * Rate limit time window in seconds.
	 *
	 * @var int
	 */
	const RATE_LIMIT_WINDOW = 60;

	/**
	 * Transient key prefix for rate limiting.
	 *
	 * @var string
	 */
	const RATE_LIMIT_PREFIX = 'zw_ttvgpt_rate_';

	/**
	 * Minimum word count required in content to generate summary.
	 *
	 * @var int
	 */
	const MIN_WORD_COUNT = 100;

	/**
	 * Default word limit for generated summaries.
	 *
	 * @var int
	 */
	const DEFAULT_WORD_LIMIT = 100;

	/**
	 * Minimum allowed word limit for summaries.
	 *
	 * @var int
	 */
	const MIN_WORD_LIMIT = 50;

	/**
	 * Maximum allowed word limit for summaries.
	 *
	 * @var int
	 */
	const MAX_WORD_LIMIT = 500;

	/**
	 * Step value for word limit adjustment.
	 *
	 * @var int
	 */
	const WORD_LIMIT_STEP = 10;

	/**
	 * API request timeout in seconds for all models (GPT-5.1 and GPT-4.1 family).
	 * GPT-5.1 uses reasoning_effort='none' for fast responses, similar to GPT-4.1 speed.
	 *
	 * @var int
	 */
	const API_TIMEOUT = 30;

	/**
	 * Maximum number of retry attempts for word count validation.
	 *
	 * @var int
	 */
	const MAX_RETRY_ATTEMPTS = 5;

	/**
	 * Success message display duration in milliseconds.
	 *
	 * @var int
	 */
	const SUCCESS_MESSAGE_TIMEOUT = 3000;

	/**
	 * Minimum typing animation delay in milliseconds.
	 *
	 * @var int
	 */
	const ANIMATION_DELAY_MIN = 20;

	/**
	 * Maximum typing animation delay in milliseconds.
	 *
	 * @var int
	 */
	const ANIMATION_DELAY_MAX = 50;

	/**
	 * Space character typing delay in milliseconds.
	 *
	 * @var int
	 */
	const ANIMATION_DELAY_SPACE = 30;

	/**
	 * Default system prompt for AI summary generation.
	 * Optimized for GPT-5.1 with reasoning_effort='low'.
	 * Use %d as placeholder for word limit.
	 *
	 * @var string
	 */
	const DEFAULT_SYSTEM_PROMPT = 'Je bent een eindredacteur voor tekst-tv. Denk eerst: wat is de kernboodschap van dit artikel? Vat het artikel samen in natuurlijk, vloeiend Nederlands voor een breed publiek. Schrijf volledige zinnen met een logische opbouw. Focus op de kernboodschap en de belangrijkste feiten. Gebruik korte, heldere zinnen maar pas op voor telegramstijl. Gebruik maximaal %d woorden. Schrijf alleen in het Nederlands en gebruik geen gedachtestreepjes.';

	/**
	 * Get default plugin settings
	 *
	 * @return array Default settings configuration
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
	 * Generate rate limit transient key for specific user
	 *
	 * @param int $user_id User ID to generate key for
	 * @return string Transient key for rate limiting
	 */
	public static function get_rate_limit_key( int $user_id ): string {
		return self::RATE_LIMIT_PREFIX . $user_id;
	}
}
