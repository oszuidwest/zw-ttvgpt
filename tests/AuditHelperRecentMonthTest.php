<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ZW_TTVGPT_Core\AuditHelper;

#[CoversClass(AuditHelper::class)]
final class AuditHelperRecentMonthTest extends TestCase {

	private mixed $previous_wpdb;

	protected function setUp(): void {
		$this->previous_wpdb = $GLOBALS['wpdb'] ?? null;
	}

	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->previous_wpdb;
	}

	public function test_get_most_recent_month_includes_historical_audit_data(): void {
		$wpdb            = new AuditHelperRecentMonthWpdbStub('2021-04-12 10:30:00');
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
}

final class AuditHelperRecentMonthWpdbStub {
	public string $posts = 'wp_posts';
	public string $postmeta = 'wp_postmeta';
	public string $prepared_query = '';

	public function __construct( private readonly ?string $result ) {}

	public function prepare( string $query, mixed ...$args ): string {
		$this->prepared_query = $query;
		return $query;
	}

	public function get_var( string $query ): ?string {
		return $this->result;
	}
}
