<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ZW_TTVGPT_Core\Admin\AuditPage;
use ZW_TTVGPT_Core\AuditHelper;

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

		// Call the same sanitizer the modal render path calls (AuditPage::sanitize_diff_panel)
		// so removing wp_kses from that helper fails these tests, not just the constant lock-in.
		$sanitized_before = AuditPage::sanitize_diff_panel( $diff['before'] );
		$sanitized_after  = AuditPage::sanitize_diff_panel( $diff['after'] );

		self::assertOnlyDiffSpansAsLiveHtml( $sanitized_before, 'BEFORE pane' );
		self::assertOnlyDiffSpansAsLiveHtml( $sanitized_after, 'AFTER pane' );
	}

	/**
	 * Strips diff-span open and close tags only (not their inner content) and
	 * asserts no tag-shaped markup remains. Catches any unencoded `<...>` that
	 * wp_kses failed to neutralize — including a hypothetical nested tag inside
	 * a diff span — while tolerating dangerous *words* (onerror, javascript:)
	 * appearing as inert text inside encoded fragments like `&lt;img ...&gt;`.
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
	 * Direct helper test that bypasses generate_word_diff. WP's Text_Diff
	 * renderer htmlspecialchars-encodes input before wrapping, so the integration
	 * tests above would also pass if someone removed wp_kses from
	 * sanitize_diff_panel. This test feeds un-encoded markup straight into the
	 * helper, so removing the wp_kses call breaks here even though the diff
	 * renderer's defence is gone.
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
			// wp_kses allows `class` as an attribute but does not validate its
			// value, so a `class="evil"` span passes wp_kses(['span' => ['class' => true]]).
			// The helper must enforce the class allowlist itself.
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
	 * Pins exact sanitizer output for cases the looser tag-shape assertion misses:
	 * nested disallowed spans (the regex's non-greedy `(.*?)` would otherwise leave
	 * the inner span surviving as a top-level node), multi-class values that are
	 * not exactly an allowlist entry, and entity-encoded class values that would
	 * decode to an allowlist entry in the browser. The expected outputs are the
	 * security contract: disallowed spans must collapse to inert text.
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
}
