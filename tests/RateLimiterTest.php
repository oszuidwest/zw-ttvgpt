<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZW_TTVGPT_Core\Constants;
use ZW_TTVGPT_Core\RateLimiter;

#[CoversClass(RateLimiter::class)]
final class RateLimiterTest extends TestCase {

	private const int USER_ID = 7;

	private RecordingLogger $logger;

	protected function setUp(): void {
		$GLOBALS['zw_test_transients']           = array();
		$GLOBALS['zw_test_set_transient_return'] = true;
		$GLOBALS['zw_test_set_transient_calls']  = array();
		$this->logger                            = new RecordingLogger();
	}

	public function test_first_request_returns_false_and_persists_count_one_with_window_ttl(): void {
		self::assertFalse( RateLimiter::check_and_increment( self::USER_ID, $this->logger ) );

		$key = Constants::get_rate_limit_key( self::USER_ID );
		self::assertSame( 1, $GLOBALS['zw_test_transients'][ $key ] );
		self::assertCount( 1, $GLOBALS['zw_test_set_transient_calls'] );
		self::assertSame( Constants::RATE_LIMIT_WINDOW, $GLOBALS['zw_test_set_transient_calls'][0]['expiration'] );
	}

	public function test_last_request_under_cap_still_succeeds(): void {
		$key                                   = Constants::get_rate_limit_key( self::USER_ID );
		$GLOBALS['zw_test_transients'][ $key ] = Constants::RATE_LIMIT_MAX_REQUESTS - 1;

		self::assertFalse( RateLimiter::check_and_increment( self::USER_ID, $this->logger ) );
		self::assertSame( Constants::RATE_LIMIT_MAX_REQUESTS, $GLOBALS['zw_test_transients'][ $key ] );
	}

	/** Regression: blocked requests must not refresh the transient TTL. */
	public function test_request_at_cap_returns_true_without_refreshing_transient(): void {
		$key                                   = Constants::get_rate_limit_key( self::USER_ID );
		$GLOBALS['zw_test_transients'][ $key ] = Constants::RATE_LIMIT_MAX_REQUESTS;

		self::assertTrue( RateLimiter::check_and_increment( self::USER_ID, $this->logger ) );
		self::assertSame( array(), $GLOBALS['zw_test_set_transient_calls'] );
	}

	public function test_request_far_above_cap_remains_blocked_without_refresh(): void {
		$key                                   = Constants::get_rate_limit_key( self::USER_ID );
		$GLOBALS['zw_test_transients'][ $key ] = Constants::RATE_LIMIT_MAX_REQUESTS + 5;

		self::assertTrue( RateLimiter::check_and_increment( self::USER_ID, $this->logger ) );
		self::assertSame( array(), $GLOBALS['zw_test_set_transient_calls'] );
	}

	public function test_non_numeric_stored_value_resets_to_one(): void {
		$key                                   = Constants::get_rate_limit_key( self::USER_ID );
		$GLOBALS['zw_test_transients'][ $key ] = 'corrupted';

		self::assertFalse( RateLimiter::check_and_increment( self::USER_ID, $this->logger ) );
		self::assertSame( 1, $GLOBALS['zw_test_transients'][ $key ] );
	}

	public function test_set_transient_failure_is_logged(): void {
		$GLOBALS['zw_test_set_transient_return'] = false;

		self::assertFalse( RateLimiter::check_and_increment( self::USER_ID, $this->logger ) );

		self::assertCount( 1, $this->logger->errors );
		self::assertStringContainsString( 'Failed to persist rate-limit transient', $this->logger->errors[0]['message'] );
		self::assertSame( self::USER_ID, $this->logger->errors[0]['context']['user_id'] );
		self::assertSame( 1, $this->logger->errors[0]['context']['count'] );
	}

	public function test_successful_set_transient_does_not_log_error(): void {
		RateLimiter::check_and_increment( self::USER_ID, $this->logger );

		self::assertSame( array(), $this->logger->errors );
	}
}
