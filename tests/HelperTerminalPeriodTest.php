<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZW_TTVGPT_Core\Helper;

#[CoversClass(Helper::class)]
final class HelperTerminalPeriodTest extends TestCase {

	#[DataProvider('terminalPeriodProvider')]
	public function test_ensure_terminal_period( string $input, string $expected ): void {
		self::assertSame( $expected, Helper::ensure_terminal_period( $input ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function terminalPeriodProvider(): array {
		return array(
			'empty string stays empty'                 => array( " \t\n", '' ),
			'missing punctuation gets period'         => array( 'Het bericht stopt hier', 'Het bericht stopt hier.' ),
			'text is trimmed before period is added'  => array( '  Het bericht stopt hier  ', 'Het bericht stopt hier.' ),
			'existing period is preserved'            => array( 'Het bericht is klaar.', 'Het bericht is klaar.' ),
			'existing question mark is preserved'     => array( 'Komt er extra toezicht?', 'Komt er extra toezicht?' ),
			'existing exclamation mark is preserved'  => array( 'De weg is weer open!', 'De weg is weer open!' ),
			'existing ellipsis is preserved'          => array( 'Het onderzoek loopt…', 'Het onderzoek loopt…' ),
			'closing quote after punctuation is kept' => array( '"Het besluit is genomen."', '"Het besluit is genomen."' ),
			'period is added after closing bracket'   => array( 'De weg gaat dicht (A58)', 'De weg gaat dicht (A58).' ),
			'trailing comma becomes period'           => array( 'De vergadering start morgen,', 'De vergadering start morgen.' ),
			'comma before quote becomes period'       => array( 'Hij zei "ja,"', 'Hij zei "ja."' ),
			'comma after quote becomes period'        => array( 'Hij zei "ja",', 'Hij zei "ja."' ),
			'colon before quote becomes period'       => array( 'Hij zei "ja:"', 'Hij zei "ja."' ),
			'comma before bracket becomes period'     => array( 'De weg gaat dicht (A58,)', 'De weg gaat dicht (A58).' ),
			'comma after bracket becomes period'      => array( 'De weg gaat dicht (A58),', 'De weg gaat dicht (A58).' ),
		);
	}
}
