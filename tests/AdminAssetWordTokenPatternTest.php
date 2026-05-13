<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\TestCase;

final class AdminAssetWordTokenPatternTest extends TestCase {

	public function test_admin_script_builds_word_counter_regex_from_inline_config(): void {
		$script = file_get_contents( dirname( __DIR__ ) . '/assets/admin.js' );
		self::assertIsString( $script );

		self::assertStringContainsString( "new RegExp(config.wordTokenPattern, 'gu')", $script );
		self::assertStringNotContainsString( '/[\p{L}]+', $script );
	}
}
