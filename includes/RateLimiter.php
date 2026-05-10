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
 * Enforces request limits per user to prevent API abuse.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class RateLimiter {
	/**
	 * Records a request and reports whether the user has now exceeded the limit.
	 *
	 * Increments first, then compares — collapses the previous two-step
	 * check-then-increment into a single read/write cycle, shrinking (but not
	 * eliminating) the race window where parallel requests can both pass.
	 *
	 * @since 1.0.0
	 *
	 * @param int $user_id User ID to check and record a request for.
	 * @return bool True if the user is now rate limited, false otherwise.
	 */
	public static function check_and_increment( int $user_id ): bool {
		$transient_key = Constants::get_rate_limit_key( $user_id );
		$requests      = get_transient( $transient_key );

		$count = is_numeric( $requests ) ? (int) $requests + 1 : 1;
		set_transient( $transient_key, $count, Constants::RATE_LIMIT_WINDOW );

		return $count > Constants::RATE_LIMIT_MAX_REQUESTS;
	}
}
