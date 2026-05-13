<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZW_TTVGPT_Core\Helper;

require_once __DIR__ . '/wp-load-helper.php';

#[CoversClass(Helper::class)]
final class HelperHtmlToTextTest extends TestCase {

	#[DataProvider('htmlProvider')]
	public function test_html_to_text( string $input, string $expected ): void {
		self::assertSame( $expected, Helper::html_to_text( $input ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function htmlProvider(): array {
		return array(
			'plain text is trimmed'               => array( '  artikel tekst  ', 'artikel tekst' ),
			'block elements become newlines'      => array( '<p>Eerste</p><div>Tweede<br>regel</div>', "Eerste\nTweede\nregel" ),
			'script-like content is removed'      => array( '<p>Start</p><script>alert("x")</script><style>.x{}</style><noscript>fallback</noscript><p>Eind</p>', "Start\nEind" ),
			'entities are decoded'               => array( '<p>Auto&apos;s &amp; fietsen</p>', "Auto's & fietsen" ),
			'excess whitespace is normalized'     => array( "<p>Veel   spaties</p>\n\n\n\n<p>Nieuwe\tregel</p>", "Veel spaties\n\nNieuwe regel" ),
			'unsafe inline markup becomes text'   => array( '<p>Tekst <strong>met nadruk</strong></p>', 'Tekst met nadruk' ),
		);
	}
}
