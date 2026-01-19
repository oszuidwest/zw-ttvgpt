<?php
/**
 * Rate Limiter class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace ZW_TTVGPT_Core;

/**
 * Rate Limiter class
 *
 * Handles API rate limiting functionality
 *
 * @package ZW_TTVGPT
 */
class RateLimiter {
	/**
	 * Check if current user has exceeded rate limit for API requests
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
	 * Increment rate limit counter for specific user
	 *
	 * @param int $user_id User ID to increment rate limit for.
	 * @return void
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
