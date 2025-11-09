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
	const DEFAULT_MODEL = 'gpt-4.1-mini';

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
	 * API request timeout in seconds (for GPT-4 and earlier models).
	 *
	 * @var int
	 */
	const API_TIMEOUT = 30;

	/**
	 * API request timeout in seconds for GPT-5 models.
	 * GPT-5 models with reasoning can take longer, so we use a higher timeout.
	 *
	 * @var int
	 */
	const API_TIMEOUT_GPT5 = 60;

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
	 * Use %d as placeholder for word limit.
	 *
	 * @var string
	 */
	const DEFAULT_SYSTEM_PROMPT = 'Je bent een ervaren eindredacteur die perfect Nederlands beheerst. Vat het volgende nieuwsartikel samen op een heldere en beknopte manier die makkelijk te begrijpen is voor een breed publiek. Focus op de hoofdzaak en laat bijzaken weg. Schrijf een vloeiend verhaal met natuurlijke overgangen. Gebruik korte zinslengtes om de leesbaarheid te behouden. Negeer alles in het artikel dat geen Nederlands is. Parse HTML. Gebruik nooit Engelse woorden. Gebruik geen gedachtestreepjes (—), alleen komma\'s en punten. Gebruik maximaal %d woorden.';

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
