<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZW_TTVGPT_Core\AuditHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[CoversClass(AuditHelper::class)]
final class AuditHelperRegionPrefixTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/wp-load-helper.php';
	}

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
			// trim() removes the trailing space, so the separator no longer matches.
			'region without trailing body'   => array( 'LEIDEN - ', 'LEIDEN -' ),
			'single-letter prefix preserved' => array( 'A - eerste optie', 'A - eerste optie' ),
			'two-letter prefix still strips' => array( 'EU - mededeling.', 'mededeling.' ),
			// Only ASCII hyphen is a separator.
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

	public function test_calculate_change_percentage_ai_whitespace_returns_max(): void {
		self::assertSame( 100.0, AuditHelper::calculate_change_percentage( "   \t ", 'human content' ) );
	}

	public function test_calculate_change_percentage_human_empty_returns_max(): void {
		self::assertSame( 100.0, AuditHelper::calculate_change_percentage( 'human content', '' ) );
	}

	public function test_calculate_change_percentage_no_word_tokens_returns_zero(): void {
		self::assertSame( 0.0, AuditHelper::calculate_change_percentage( '3,5', '---' ) );
	}

	public function test_calculate_change_percentage_identical_returns_zero(): void {
		self::assertSame( 0.0, AuditHelper::calculate_change_percentage( 'same words here', 'same words here' ) );
	}

	public function test_calculate_change_percentage_half_changed(): void {
		// Four words per side; two shared words means 50% change.
		self::assertSame(
			50.0,
			AuditHelper::calculate_change_percentage( 'the cat sat down', 'the cat ran away' )
		);
	}

	public function test_calculate_change_percentage_uses_unicode_word_tokens(): void {
		self::assertSame(
			20.0,
			AuditHelper::calculate_change_percentage(
				'CURAÇAO - Het café opent vandaag',
				'CURAÇAO - Het café opent morgen'
			)
		);
	}

	public function test_generate_word_diff_falls_back_to_plain_text_when_diff_stripping_regex_fails(): void {
		$log_file           = tempnam( sys_get_temp_dir(), 'zw-ttvgpt-pcre-' );
		$previous_error_log = false;
		self::assertIsString( $log_file );

		$previous_error_log = ini_set( 'error_log', $log_file );
		$previous_limit     = ini_set( 'pcre.backtrack_limit', '1' );

		try {
			$diff = AuditHelper::generate_word_diff( 'oude zin', 'nieuwe zin' );
		} finally {
			if ( false !== $previous_limit ) {
				ini_set( 'pcre.backtrack_limit', $previous_limit );
			}
			if ( false !== $previous_error_log ) {
				ini_set( 'error_log', $previous_error_log );
			}
		}

		self::assertSame( 'oude zin', $diff['before'] );
		self::assertSame( 'nieuwe zin', $diff['after'] );
		self::assertNotSame( $diff['before'], $diff['after'], 'PCRE failures must not show the combined diff in both panes.' );

		$log_contents = file_get_contents( $log_file );
		self::assertIsString( $log_contents );
		self::assertStringContainsString( 'Audit diff PCRE failure during remove_diff_tag', $log_contents );
		self::assertStringContainsString( 'Backtrack limit exhausted', $log_contents );

		if ( is_file( $log_file ) ) {
			unlink( $log_file );
		}
	}
}
