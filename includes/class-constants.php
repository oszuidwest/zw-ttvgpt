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
 */
class TTVGPTConstants {
	/**
	 * Plugin settings
	 */
	const SETTINGS_OPTION_NAME = 'zw_ttvgpt_settings';
	const SETTINGS_GROUP       = 'zw_ttvgpt_settings_group';
	const SETTINGS_PAGE_SLUG   = 'zw-ttvgpt-settings';

	/**
	 * Capabilities
	 */
	const REQUIRED_CAPABILITY = 'manage_options';
	const EDIT_CAPABILITY     = 'edit_posts';

	/**
	 * Post types
	 */
	const SUPPORTED_POST_TYPE = 'post';

	/**
	 * ACF field keys
	 */
	const ACF_SUMMARY_FIELD    = 'field_5f21a06d22c58';
	const ACF_GPT_MARKER_FIELD = 'field_66ad2a3105371';

	/**
	 * ACF field names (meta keys)
	 */
	const ACF_FIELD_AI_CONTENT = 'post_kabelkrant_content_gpt';
	const ACF_FIELD_HUMAN_CONTENT = 'post_kabelkrant_content';
	const ACF_FIELD_IN_KABELKRANT = 'post_in_kabelkrant';

	/**
	 * OpenAI models
	 */
	const DEFAULT_MODEL = 'gpt-4.1-mini';


	/**
	 * Rate limiting
	 */
	const RATE_LIMIT_MAX_REQUESTS = 10;
	const RATE_LIMIT_WINDOW       = 60; // seconds
	const RATE_LIMIT_PREFIX       = 'zw_ttvgpt_rate_';

	/**
	 * Word limits
	 */
	const MIN_WORD_COUNT     = 100;
	const DEFAULT_WORD_LIMIT = 100;
	const MIN_WORD_LIMIT     = 50;
	const MAX_WORD_LIMIT     = 500;
	const WORD_LIMIT_STEP    = 10;

	/**
	 * Timeouts
	 */
	const API_TIMEOUT             = 30; // seconds
	const SUCCESS_MESSAGE_TIMEOUT = 3000; // milliseconds

	/**
	 * Animation delays (milliseconds)
	 */
	const ANIMATION_DELAY_MIN   = 20;
	const ANIMATION_DELAY_MAX   = 50;
	const ANIMATION_DELAY_SPACE = 30;

	/**
	 * Get default plugin settings
	 *
	 * @return array Default settings configuration
	 */
	public static function get_default_settings(): array {
		return array(
			'api_key'    => '',
			'model'      => self::DEFAULT_MODEL,
			'word_limit' => self::DEFAULT_WORD_LIMIT,
			'debug_mode' => false,
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
