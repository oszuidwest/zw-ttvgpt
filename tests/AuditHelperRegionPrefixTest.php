<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZW_TTVGPT_Core\AuditHelper;

#[CoversClass(AuditHelper::class)]
final class AuditHelperRegionPrefixTest extends TestCase {

	#[DataProvider('regionPrefixProvider')]
	public function test_strip_region_prefix( string $input, string $expected ): void {
		self::assertSame( $expected, AuditHelper::strip_region_prefix( $input ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function regionPrefixProvider(): array {
		return array(
			'simple uppercase region'        => array( 'LEIDEN - Een nieuwsbericht.', 'Een nieuwsbericht.' ),
			'multi-word region'              => array( 'DEN HAAG - Een nieuwsbericht.', 'Een nieuwsbericht.' ),
			'slash-separated regions'        => array( 'ROOSENDAAL/OUDENBOSCH - Bericht.', 'Bericht.' ),
			'hyphenated region'              => array( 'ETTEN-LEUR - Bericht.', 'Bericht.' ),
			// Regression: pre-fix ASCII-only character class skipped diacritics.
			'diacritic region (cedilla)'     => array( 'CURAÇAO - Bericht.', 'Bericht.' ),
			'diacritic region (umlaut)'      => array( 'ZÜRICH - Bericht.', 'Bericht.' ),
			'no prefix is left untouched'    => array( 'Geen prefix hier.', 'Geen prefix hier.' ),
			'lowercase is not a prefix'      => array( 'leiden - geen prefix.', 'leiden - geen prefix.' ),
			'leading whitespace stripped'    => array( '   LEIDEN - Bericht.', 'Bericht.' ),
			'empty string passes through'    => array( '', '' ),
			'whitespace-only collapses'      => array( "   \t  ", '' ),
			// trim() runs first so the trailing space is gone, breaking the `\s-\s` anchor.
			'region without trailing body'   => array( 'LEIDEN - ', 'LEIDEN -' ),
			// Single-letter prefix currently matches; documenting behaviour, not endorsing it.
			'single-letter prefix matches'   => array( 'A - bericht', 'bericht' ),
			// Regex requires ASCII hyphen; typographic dashes are not treated as separators.
			'en-dash is not a separator'     => array( 'LEIDEN – Bericht.', 'LEIDEN – Bericht.' ),
			'em-dash is not a separator'     => array( 'LEIDEN — Bericht.', 'LEIDEN — Bericht.' ),
		);
	}

	public function test_calculate_change_percentage_both_empty(): void {
		self::assertSame( 0.0, AuditHelper::calculate_change_percentage( '', '' ) );
	}

	public function test_calculate_change_percentage_ai_empty_returns_max(): void {
		self::assertSame( 100.0, AuditHelper::calculate_change_percentage( '', 'human content' ) );
	}

	public function test_calculate_change_percentage_identical_returns_zero(): void {
		self::assertSame( 0.0, AuditHelper::calculate_change_percentage( 'same words here', 'same words here' ) );
	}

	public function test_calculate_change_percentage_half_changed(): void {
		// AI=4 words, human=4 words, shared=2 ('the', 'cat'). Similarity = 2/4 = 0.5 → 50% change.
		self::assertSame(
			50.0,
			AuditHelper::calculate_change_percentage( 'the cat sat down', 'the cat ran away' )
		);
	}
}
