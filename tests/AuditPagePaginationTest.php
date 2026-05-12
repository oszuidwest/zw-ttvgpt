<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZW_TTVGPT_Core\Admin\AuditPage;

#[CoversClass(AuditPage::class)]
final class AuditPagePaginationTest extends TestCase {

	/**
	 * @param array<int, int>                                                                $items
	 * @param array{slice: array<int, int>, paged: int, total_pages: int, total: int} $expected
	 */
	#[DataProvider('paginationProvider')]
	public function test_paginate_clamps_and_slices( array $items, int $requested_page, int $per_page, array $expected ): void {
		self::assertSame( $expected, AuditPage::paginate( $items, $requested_page, $per_page ) );
	}

	/**
	 * @return array<string, array{array<int, int>, int, int, array{slice: array<int, int>, paged: int, total_pages: int, total: int}}>
	 */
	public static function paginationProvider(): array {
		$five = array( 1, 2, 3, 4, 5 );
		$ten  = array( 1, 2, 3, 4, 5, 6, 7, 8, 9, 10 );

		return array(
			'empty list, page 1, per 50'        => array(
				array(),
				1,
				50,
				array( 'slice' => array(), 'paged' => 1, 'total_pages' => 0, 'total' => 0 ),
			),
			'empty list, requested page 5'      => array(
				array(),
				5,
				50,
				array( 'slice' => array(), 'paged' => 1, 'total_pages' => 0, 'total' => 0 ),
			),
			'single page fits exactly'          => array(
				$five,
				1,
				5,
				array( 'slice' => $five, 'paged' => 1, 'total_pages' => 1, 'total' => 5 ),
			),
			'multi-page, first page slice'      => array(
				$ten,
				1,
				4,
				array( 'slice' => array( 1, 2, 3, 4 ), 'paged' => 1, 'total_pages' => 3, 'total' => 10 ),
			),
			'multi-page, middle page slice'     => array(
				$ten,
				2,
				4,
				array( 'slice' => array( 5, 6, 7, 8 ), 'paged' => 2, 'total_pages' => 3, 'total' => 10 ),
			),
			'multi-page, last page short slice' => array(
				$ten,
				3,
				4,
				array( 'slice' => array( 9, 10 ), 'paged' => 3, 'total_pages' => 3, 'total' => 10 ),
			),
			'requested page over total clamps to last' => array(
				$ten,
				999,
				4,
				array( 'slice' => array( 9, 10 ), 'paged' => 3, 'total_pages' => 3, 'total' => 10 ),
			),
			'requested page zero clamps up to 1' => array(
				$ten,
				0,
				4,
				array( 'slice' => array( 1, 2, 3, 4 ), 'paged' => 1, 'total_pages' => 3, 'total' => 10 ),
			),
			'negative requested page clamps up to 1' => array(
				$ten,
				-5,
				4,
				array( 'slice' => array( 1, 2, 3, 4 ), 'paged' => 1, 'total_pages' => 3, 'total' => 10 ),
			),
			'per_page zero produces empty slice but reports total' => array(
				$five,
				1,
				0,
				array( 'slice' => array(), 'paged' => 1, 'total_pages' => 0, 'total' => 5 ),
			),
			'per_page negative produces empty slice' => array(
				$five,
				1,
				-3,
				array( 'slice' => array(), 'paged' => 1, 'total_pages' => 0, 'total' => 5 ),
			),
			'exactly POSTS_PER_PAGE boundary'   => array(
				range( 1, 50 ),
				1,
				50,
				array( 'slice' => range( 1, 50 ), 'paged' => 1, 'total_pages' => 1, 'total' => 50 ),
			),
			'one over POSTS_PER_PAGE boundary'  => array(
				range( 1, 51 ),
				2,
				50,
				array( 'slice' => array( 51 ), 'paged' => 2, 'total_pages' => 2, 'total' => 51 ),
			),
		);
	}
}
