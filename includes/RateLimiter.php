<?php
/**
 * Rate Limiter class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rate Limiter class.
 *
 * Handles API rate limiting functionality.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class RateLimiter {
	/**
	 * Checks if current user has exceeded rate limit for API requests.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID to check rate limit for.
	 * @return bool True if user is rate limited, false otherwise.
	 */
	public static function is_limited( int $user_id ): bool {
		$transient_key = Constants::get_rate_limit_key( $user_id );
		$requests      = get_transient( $transient_key );

		return $requests >= Constants::RATE_LIMIT_MAX_REQUESTS;
	}

	/**
	 * Increments the rate limit counter for a specific user.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID to increment rate limit for.
	 */
	public static function increment( int $user_id ): void {
		$transient_key = Constants::get_rate_limit_key( $user_id );
		$requests      = get_transient( $transient_key );

		if ( false === $requests ) {
			set_transient( $transient_key, 1, Constants::RATE_LIMIT_WINDOW );
		} else {
			set_transient( $transient_key, $requests + 1, Constants::RATE_LIMIT_WINDOW );
		}
	}
}
