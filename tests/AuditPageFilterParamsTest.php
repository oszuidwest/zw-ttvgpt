<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use ZW_TTVGPT_Core\Admin\AuditPage;

#[CoversClass(AuditPage::class)]
final class AuditPageFilterParamsTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/wp-load-helper.php';
	}

	/**
	 * @param array<string, string> $query
	 */
	#[DataProvider('nativeMonthProvider')]
	public function test_get_filter_params_parses_native_month_fallback( array $query, ?int $expected_year, ?int $expected_month ): void {
		$previous_get = $_GET;
		$_GET         = $query;

		try {
			$params = self::invokeGetFilterParams();
		} finally {
			$_GET = $previous_get;
		}

		self::assertSame( $expected_year, $params['year'] );
		self::assertSame( $expected_month, $params['month'] );
	}

	/**
	 * @return array<string, array{array<string, string>, int|null, int|null}>
	 */
	public static function nativeMonthProvider(): array {
		return array(
			'native YYYYMM value parses into year and month' => array(
				array( 'm' => '202604' ),
				2026,
				4,
			),
			'native all-dates zero is a no-op' => array(
				array( 'm' => '0' ),
				null,
				null,
			),
			'five digits do not pass anchored YYYYMM regex' => array(
				array( 'm' => '20264' ),
				null,
				null,
			),
			'seven digits do not pass anchored YYYYMM regex' => array(
				array( 'm' => '2026041' ),
				null,
				null,
			),
			'month zero is outside valid month bounds' => array(
				array( 'm' => '202600' ),
				null,
				null,
			),
			'month thirteen is outside valid month bounds' => array(
				array( 'm' => '202613' ),
				null,
				null,
			),
			'explicit year and month take precedence over native m' => array(
				array(
					'year'  => '2025',
					'month' => '3',
					'm'     => '202604',
				),
				2025,
				3,
			),
		);
	}

	/**
	 * @return array{year: int|null, month: int|null, status_filter: string, change_filter: string, paged: int}
	 */
	private static function invokeGetFilterParams(): array {
		$method = new ReflectionMethod( AuditPage::class, 'get_filter_params' );

		$result = $method->invoke( new AuditPage() );
		self::assertIsArray( $result );

		return $result;
	}
}
