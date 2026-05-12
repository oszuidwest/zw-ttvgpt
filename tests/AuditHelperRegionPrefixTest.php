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
			// One-letter list markers are not region prefixes.
			'single-letter prefix preserved' => array( 'A - eerste optie', 'A - eerste optie' ),
			// Two uppercase letters still count as a region prefix.
			'two-letter prefix still strips' => array( 'EU - mededeling.', 'mededeling.' ),
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

	public function test_calculate_change_percentage_ai_whitespace_returns_max(): void {
		self::assertSame( 100.0, AuditHelper::calculate_change_percentage( "   \t ", 'human content' ) );
	}

	public function test_calculate_change_percentage_human_empty_returns_max(): void {
		self::assertSame( 100.0, AuditHelper::calculate_change_percentage( 'human content', '' ) );
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

	public function test_generate_word_diff_falls_back_to_plain_text_when_diff_stripping_regex_fails(): void {
		$previous_limit = ini_set( 'pcre.backtrack_limit', '1' );
		$error_log      = tempnam( sys_get_temp_dir(), 'zw-ttvgpt-error-log-' );
		self::assertIsString( $error_log );
		$previous_log = ini_set( 'error_log', $error_log );
		$events       = array();

		add_action(
			'zw_ttvgpt_audit_regex_failed',
			static function ( string $operation, int $error_code, string $error_message ) use ( &$events ): void {
				$events[] = array(
					'operation' => $operation,
					'code'      => $error_code,
					'message'   => $error_message,
				);
			},
			10,
			3
		);

		try {
			$diff = AuditHelper::generate_word_diff( 'oude zin', 'nieuwe zin' );
		} finally {
			remove_all_actions( 'zw_ttvgpt_audit_regex_failed' );
			if ( false !== $previous_limit ) {
				ini_set( 'pcre.backtrack_limit', $previous_limit );
			}
			if ( false !== $previous_log ) {
				ini_set( 'error_log', $previous_log );
			}
			if ( is_file( $error_log ) ) {
				unlink( $error_log );
			}
		}

		self::assertSame( 'oude zin', $diff['before'] );
		self::assertSame( 'nieuwe zin', $diff['after'] );
		self::assertNotSame( $diff['before'], $diff['after'], 'PCRE failures must not show the combined diff in both panes.' );
		self::assertContains( 'remove added diff span for before pane', array_column( $events, 'operation' ) );
	}
}
