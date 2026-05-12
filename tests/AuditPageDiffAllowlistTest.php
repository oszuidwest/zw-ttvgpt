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
	 * Mirrors AuditPage::DIFF_ALLOWED_TAGS so the integration tests assert what
	 * the renderer at AuditPage.php:583/594 actually uses. The first test below
	 * fails the suite if the two ever drift.
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

		$sanitized_before = wp_kses( $diff['before'], self::EXPECTED_ALLOWLIST );
		$sanitized_after  = wp_kses( $diff['after'], self::EXPECTED_ALLOWLIST );

		self::assertOnlyDiffSpansAsLiveHtml( $sanitized_before, 'BEFORE pane' );
		self::assertOnlyDiffSpansAsLiveHtml( $sanitized_after, 'AFTER pane' );
	}

	/**
	 * Strips the only HTML the renderer is supposed to emit (zw-diff-added /
	 * zw-diff-removed spans) and asserts nothing tag-shaped remains. Catches
	 * any unencoded `<...>` that wp_kses failed to neutralize, while
	 * tolerating dangerous *words* (onerror, javascript:) appearing as inert
	 * text inside encoded fragments like `&lt;img onerror=...&gt;`.
	 */
	private static function assertOnlyDiffSpansAsLiveHtml( string $sanitized, string $pane_label ): void {
		$stripped = preg_replace( '#<span class="zw-diff-(?:added|removed)">.*?</span>#s', '', $sanitized );
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

	public function test_legitimate_diff_spans_survive_sanitization(): void {
		$diff = AuditHelper::generate_word_diff( 'het originele bericht hier', 'het bewerkte bericht hier' );

		$sanitized_before = wp_kses( $diff['before'], self::EXPECTED_ALLOWLIST );
		$sanitized_after  = wp_kses( $diff['after'], self::EXPECTED_ALLOWLIST );

		self::assertStringContainsString( '<span class="zw-diff-removed"', $sanitized_before, 'Removed-content spans must survive sanitization.' );
		self::assertStringContainsString( '<span class="zw-diff-added"', $sanitized_after, 'Added-content spans must survive sanitization.' );
		self::assertStringContainsString( 'bericht', $sanitized_before );
		self::assertStringContainsString( 'bericht', $sanitized_after );
	}
}
