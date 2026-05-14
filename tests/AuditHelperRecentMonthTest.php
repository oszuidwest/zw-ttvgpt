<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZW_TTVGPT_Core\AuditHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[CoversClass(AuditHelper::class)]
final class AuditHelperRecentMonthTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
	}

	public function test_get_most_recent_month_includes_historical_audit_data(): void {
		$wpdb            = new AuditHelperRecentMonthWpdbStub( '2021-04-12 10:30:00' );
		$GLOBALS['wpdb'] = $wpdb;

		self::assertSame(
			array(
				'year'  => 2021,
				'month' => 4,
			),
			AuditHelper::get_most_recent_month()
		);
		self::assertStringNotContainsString( 'DATE_SUB', $wpdb->prepared_query );
		self::assertStringNotContainsString( 'INTERVAL 2 YEAR', $wpdb->prepared_query );
	}

	public function test_get_most_recent_month_returns_null_when_no_matching_posts(): void {
		$GLOBALS['wpdb'] = new AuditHelperRecentMonthWpdbStub( null );

		self::assertNull( AuditHelper::get_most_recent_month() );
	}
}

final class AuditHelperRecentMonthWpdbStub {
	public readonly string $posts;
	public readonly string $postmeta;
	public string $prepared_query = '';

	public function __construct( private readonly ?string $result ) {
		$this->posts    = 'wp_posts';
		$this->postmeta = 'wp_postmeta';
	}

	public function prepare( string $query, mixed ...$args ): string {
		$this->prepared_query = $query;
		return $query;
	}

	public function get_var( string $query ): ?string {
		return $this->result;
	}
}
