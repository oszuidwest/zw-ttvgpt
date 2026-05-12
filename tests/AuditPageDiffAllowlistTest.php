<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ZW_TTVGPT_Core\Admin\AuditPage;

#[CoversClass(AuditPage::class)]
final class AuditPageDiffAllowlistTest extends TestCase {

	/**
	 * The diff markup rendered into the audit modal is passed through
	 * wp_kses(..., DIFF_ALLOWED_TAGS) as a defence-in-depth layer on top of
	 * WordPress' diff renderer (which htmlspecialchars-encodes content before
	 * wrapping). This test pins the allowlist so any future expansion
	 * (anchors, images, inline styles) has to be a deliberate decision rather
	 * than an accidental relaxation of the trust boundary.
	 */
	public function test_diff_allowlist_permits_only_span_with_class(): void {
		$reflection = new ReflectionClass( AuditPage::class );
		$constant   = $reflection->getReflectionConstant( 'DIFF_ALLOWED_TAGS' );

		self::assertNotFalse( $constant, 'DIFF_ALLOWED_TAGS constant must exist on AuditPage.' );
		self::assertSame(
			array( 'span' => array( 'class' => true ) ),
			$constant->getValue(),
			'DIFF_ALLOWED_TAGS must only permit <span class="...">; widening it expands XSS surface.'
		);
	}
}
