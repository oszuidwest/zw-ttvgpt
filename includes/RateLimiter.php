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
	 * Records a request and reports whether the user is now over the limit.
	 *
	 * Note: `get_transient`/`set_transient` is not atomic, so concurrent requests
	 * can race and both observe a pre-increment value. For stricter guarantees,
	 * an object-cache-backed atomic counter would be required.
	 *
	 * Blocked requests deliberately do NOT refresh the transient: once a user
	 * is over the cap, further attempts must not extend their own lockout window.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id User ID to check and record a request for.
	 * @param Logger $logger  Logger for surfacing transient-write failures.
	 * @return bool True if the user is now rate limited, false otherwise.
	 */
	public static function check_and_increment( int $user_id, Logger $logger ): bool {
		$transient_key = Constants::get_rate_limit_key( $user_id );
		$stored        = get_transient( $transient_key );
		$current       = is_numeric( $stored ) ? (int) $stored : 0;

		if ( $current >= Constants::RATE_LIMIT_MAX_REQUESTS ) {
			return true;
		}

		$next  = $current + 1;
		$saved = set_transient( $transient_key, $next, Constants::RATE_LIMIT_WINDOW );

		if ( false === $saved ) {
			$logger->error(
				'Failed to persist rate-limit transient; rate limiting may be ineffective',
				array(
					'user_id' => $user_id,
					'count'   => $next,
				)
			);
		}

		return false;
	}
}
