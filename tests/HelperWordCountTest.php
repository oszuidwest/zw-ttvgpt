<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZW_TTVGPT_Core\Helper;

#[CoversClass(Helper::class)]
final class HelperWordCountTest extends TestCase {

	#[DataProvider('wordCountProvider')]
	public function test_count_words( string $input, int $expected ): void {
		self::assertSame( $expected, Helper::count_words( $input ) );
	}

	/**
	 * @return array<string, array{string, int}>
	 */
	public static function wordCountProvider(): array {
		return array(
			'empty string'                          => array( '', 0 ),
			'whitespace only'                       => array( "  \t\n", 0 ),
			'single ASCII word'                     => array( 'hello', 1 ),
			'two ASCII words'                       => array( 'hello world', 2 ),
			'hyphenated compound counts as one'     => array( 'zelf-rijdend', 1 ),
			'hyphenated mixed case counts as one'   => array( 'ABS-rem', 1 ),
			'apostrophe inside word'                => array( "auto's", 1 ),
			'leading apostrophe starts new word'    => array( "'s avonds", 2 ),
			'diacritic word counts as one'          => array( 'café', 1 ),
			'two diacritic words'                   => array( 'crème brûlée', 2 ),
			'cedilla word counts as one'            => array( 'Curaçao', 1 ),
			'sentence with diacritics'              => array( 'Het café serveert crème brûlée', 5 ),
			'em-dash separates words'               => array( 'Bergen op Zoom – Breda', 4 ),
			'pure numeric is not a word'            => array( '3,5', 0 ),
			'currency-prefixed number is not a word' => array( '€10', 0 ),
			'mixed number and word counts the word' => array( '3,5 miljoen', 1 ),
				// Regression: old str_word_count overcounted these cases and triggered retries.
				'regression: 15x diacritic sentence'    => array( str_repeat( 'Het café serveert crème brûlée. ', 15 ), 75 ),
			'regression: 18x em-dash sentence'      => array( str_repeat( 'Bergen op Zoom – Breda blijft bereikbaar. ', 18 ), 108 ),
		);
	}
}
