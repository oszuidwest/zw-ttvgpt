<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ZW_TTVGPT_Core\Admin\AuditPage;
use ZW_TTVGPT_Core\AuditHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[CoversClass(AuditPage::class)]
#[CoversClass(AuditHelper::class)]
final class AuditPageDiffAllowlistTest extends TestCase {

	/**
	 * The allowlist the constant lock-in test pins. Kept here, not derived from
	 * AuditPage::DIFF_ALLOWED_TAGS, so widening the constant on the production
	 * side fails this test instead of silently propagating into the assertions.
	 *
	 * @var array<string, array<string, bool>>
	 */
	private const array EXPECTED_ALLOWLIST = array( 'span' => array( 'class' => true ) );

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/wp-load-helper.php';
	}

	public function test_diff_allowlist_constant_only_permits_span_with_class(): void {
		$reflection = new ReflectionClass( AuditPage::class );
		$constant   = $reflection->getReflectionConstant( 'DIFF_ALLOWED_TAGS' );

		self::assertNotFalse( $constant, 'DIFF_ALLOWED_TAGS constant must exist on AuditPage.' );
		self::assertSame(
			self::EXPECTED_ALLOWLIST,
			$constant->getValue(),
			'Widening DIFF_ALLOWED_TAGS expands the modal trust boundary; require deliberate review.'
		);
	}

	#[DataProvider('maliciousContentProvider')]
	public function test_malicious_content_cannot_survive_diff_render_pipeline( string $ai_content, string $human_content ): void {
		$diff = AuditHelper::generate_word_diff( $ai_content, $human_content );

		$sanitized_before = AuditPage::sanitize_diff_panel( $diff['before'] );
		$sanitized_after  = AuditPage::sanitize_diff_panel( $diff['after'] );

		self::assertOnlyDiffSpansAsLiveHtml( $sanitized_before, 'BEFORE pane' );
		self::assertOnlyDiffSpansAsLiveHtml( $sanitized_after, 'AFTER pane' );
	}

	/**
	 * Asserts that only plugin diff spans remain as live HTML.
	 *
	 * @param string $sanitized Sanitized HTML.
	 * @param string $pane_label Assertion label.
	 */
	private static function assertOnlyDiffSpansAsLiveHtml( string $sanitized, string $pane_label ): void {
		$stripped = preg_replace( '#<span class="zw-diff-(?:added|removed)">|</span>#', '', $sanitized );
		self::assertIsString( $stripped, 'preg_replace must return a string.' );
		self::assertDoesNotMatchRegularExpression(
			'/<[a-zA-Z!?\/]/',
			$stripped,
			sprintf( 'Live HTML markup other than diff spans survived in %s: %s', $pane_label, $sanitized )
		);
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function maliciousContentProvider(): array {
		return array(
			'script tag in AI content'       => array( '<script>alert(1)</script> bericht', 'bewerkt bericht' ),
			'script tag in human content'    => array( 'bericht', '<script>alert(1)</script> bewerkt' ),
			'img onerror in AI content'      => array( '<img src=x onerror="alert(1)">tekst', 'tekst veranderd' ),
			'javascript: protocol in anchor' => array( '<a href="javascript:alert(1)">klik</a> hier', 'klik hier veranderd' ),
			'iframe in AI content'           => array( '<iframe src="https://evil.example/"></iframe> bericht', 'bewerkt bericht' ),
			'svg with onload handler'        => array( '<svg onload="alert(1)"></svg> bericht', 'bewerkt bericht' ),
			'style attribute on raw span'    => array( '<span style="background:url(javascript:alert(1))">x</span>', 'x veranderd' ),
		);
	}

	/**
	 * Feeds raw markup into the sanitizer without relying on Text_Diff escaping.
	 *
	 * @param string $raw_input Raw diff HTML.
	 */
	#[DataProvider('rawSanitizerProvider')]
	public function test_sanitize_diff_panel_strips_unencoded_markup( string $raw_input ): void {
		$sanitized = AuditPage::sanitize_diff_panel( $raw_input );
		self::assertOnlyDiffSpansAsLiveHtml( $sanitized, 'direct helper input' );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function rawSanitizerProvider(): array {
		return array(
			'raw script tag bypasses Text_Diff escaping'   => array( '<span class="zw-diff-added">ok</span><script>alert(1)</script>' ),
			'raw img onerror bypasses Text_Diff escaping'  => array( '<img src=x onerror="alert(1)"><span class="zw-diff-added">ok</span>' ),
			'raw iframe bypasses Text_Diff escaping'       => array( '<iframe src="https://evil.example/"></iframe><span class="zw-diff-removed">x</span>' ),
			'raw javascript: anchor'                       => array( '<a href="javascript:alert(1)">klik</a>' ),
			'nested script inside diff span'               => array( '<span class="zw-diff-added">ok<script>alert(1)</script></span>' ),
			// wp_kses allows class values; the helper must enforce the allowlist.
			'span with non-diff class'                     => array( '<span class="evil">x</span>' ),
			'span with non-diff class plus legit diff'     => array( '<span class="evil">x</span><span class="zw-diff-added">ok</span>' ),
			'span with extra class alongside diff class'   => array( '<span class="zw-diff-added evil">x</span>' ),
			'span with no class attribute at all'          => array( '<span>x</span><span class="zw-diff-added">ok</span>' ),
			'span with single-quoted non-diff class'       => array( "<span class='evil'>x</span>" ),
		);
	}

	public function test_legitimate_diff_spans_survive_sanitization(): void {
		$diff = AuditHelper::generate_word_diff( 'het originele bericht hier', 'het bewerkte bericht hier' );

		$sanitized_before = AuditPage::sanitize_diff_panel( $diff['before'] );
		$sanitized_after  = AuditPage::sanitize_diff_panel( $diff['after'] );

		self::assertStringContainsString( '<span class="zw-diff-removed"', $sanitized_before, 'Removed-content spans must survive sanitization.' );
		self::assertStringContainsString( '<span class="zw-diff-added"', $sanitized_after, 'Added-content spans must survive sanitization.' );
		self::assertStringContainsString( 'bericht', $sanitized_before );
		self::assertStringContainsString( 'bericht', $sanitized_after );
	}

	/**
	 * Pins exact sanitizer output for cases the looser tag-shape assertion misses.
	 *
	 * @param string $raw_input Raw diff HTML.
	 * @param string $expected Expected sanitized output.
	 */
	#[DataProvider('strictSanitizerProvider')]
	public function test_sanitize_diff_panel_strips_disallowed_to_inert_text( string $raw_input, string $expected ): void {
		self::assertSame( $expected, AuditPage::sanitize_diff_panel( $raw_input ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function strictSanitizerProvider(): array {
		return array(
			'nested disallowed spans (both stripped, no orphan markup)' => array(
				'<span class="evil"><span class="also-evil">x</span></span>',
				'x',
			),
			'multi-class value is not a partial allowlist match' => array(
				'<span class="zw-diff-added evil">x</span>',
				'x',
			),
			'entity-encoded class does not decode into allowlist' => array(
				'<span class="zw-diff-&#x61;dded">x</span>',
				'x',
			),
			'three-deep disallowed nesting collapses to text' => array(
				'<span class="a"><span class="b"><span class="c">deep</span></span></span>',
				'deep',
			),
			// Malformed nesting must fail closed instead of returning an orphan span.
			'unbalanced outer disallowed leaks inner open if not fail-closed' => array(
				'<span class="evil"><span class="also-evil">x</span>',
				'x',
			),
			'unbalanced disallowed open with no close at all'                  => array(
				'<span class="evil">trailing text',
				'trailing text',
			),
		);
	}

	/**
	 * Guards against fixed iteration caps that could return live disallowed spans.
	 *
	 * @param int $depth Nesting depth.
	 */
	#[DataProvider('deepNestingDepthProvider')]
	public function test_sanitize_diff_panel_handles_deep_nested_disallowed_spans( int $depth ): void {
		$input  = str_repeat( '<span class="evil">', $depth ) . 'x' . str_repeat( '</span>', $depth );
		$output = AuditPage::sanitize_diff_panel( $input );

		self::assertSame( 'x', $output, sprintf( 'Depth %d must collapse to inert text.', $depth ) );
		self::assertOnlyDiffSpansAsLiveHtml( $output, sprintf( 'depth %d output', $depth ) );
	}

	/**
	 * @return array<string, array{int}>
	 */
	public static function deepNestingDepthProvider(): array {
		return array(
			'21 deep (just past the old fixed cap)' => array( 21 ),
			'25 deep'                               => array( 25 ),
			'50 deep'                               => array( 50 ),
		);
	}

	public function test_legitimate_diff_with_angle_brackets_renders_as_entities(): void {
		$diff = AuditHelper::generate_word_diff( '5 < 10 of niet', '5 < 11 of niet' );

		$sanitized_before = AuditPage::sanitize_diff_panel( $diff['before'] );
		$sanitized_after  = AuditPage::sanitize_diff_panel( $diff['after'] );

		self::assertStringContainsString( '&lt;', $sanitized_before, 'Literal `<` from user text must survive as entity in BEFORE pane.' );
		self::assertStringContainsString( '&lt;', $sanitized_after, 'Literal `<` from user text must survive as entity in AFTER pane.' );
		self::assertOnlyDiffSpansAsLiveHtml( $sanitized_before, 'BEFORE pane' );
		self::assertOnlyDiffSpansAsLiveHtml( $sanitized_after, 'AFTER pane' );
	}

	public function test_sanitize_diff_panel_reports_orphan_disallowed_open_fail_closed(): void {
		$events = array();

		add_action(
			'zw_ttvgpt_diff_sanitizer_failed',
			static function ( string $reason, string $error_message ) use ( &$events ): void {
				$events[] = array(
					'reason'  => $reason,
					'message' => $error_message,
				);
			},
			10,
			2
		);

		try {
			self::assertSame( 'trailing text', AuditPage::sanitize_diff_panel( '<span class="evil">trailing text' ) );
		} finally {
			remove_all_actions( 'zw_ttvgpt_diff_sanitizer_failed' );
		}

		self::assertSame(
			array(
				array(
					'reason'  => 'orphan_disallowed_span',
					'message' => '',
				),
			),
			$events
		);
	}

	public function test_sanitize_diff_panel_fails_closed_when_kses_filter_returns_non_string(): void {
		$events = array();

		add_filter(
			'pre_kses',
			static fn (): array => array( 'unexpected' ),
			10,
			0
		);
		add_action(
			'zw_ttvgpt_diff_sanitizer_failed',
			static function ( string $reason, string $error_message ) use ( &$events ): void {
				$events[] = array(
					'reason'  => $reason,
					'message' => $error_message,
				);
			},
			10,
			2
		);

		try {
			self::assertSame( 'x', AuditPage::sanitize_diff_panel( '<span class="evil">x</span>' ) );
		} finally {
			remove_all_filters( 'pre_kses' );
			remove_all_actions( 'zw_ttvgpt_diff_sanitizer_failed' );
		}

		self::assertSame(
			array(
				array(
					'reason'  => 'wp_kses_non_string',
					'message' => '',
				),
			),
			$events
		);
	}
}
