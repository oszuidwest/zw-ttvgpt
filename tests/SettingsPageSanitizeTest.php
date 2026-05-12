<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ZW_TTVGPT_Core\Admin\SettingsPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[CoversClass(SettingsPage::class)]
final class SettingsPageSanitizeTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once __DIR__ . '/wp-load-helper.php';
	}

	protected function setUp(): void {
		$GLOBALS['zw_test_settings_errors'] = array();
	}

	public function test_sanitize_settings_does_not_store_invalid_api_key(): void {
		$logger = new RecordingLogger();
		$page   = self::newSettingsPage( $logger );

		$sanitized = $page->sanitize_settings( array( 'api_key' => 'not-an-openai-key' ) );

		self::assertSame( '', $sanitized['api_key'] );
		self::assertCount( 1, $logger->errors );
		self::assertSame( 'Invalid OpenAI API key submitted in settings', $logger->errors[0]['message'] );
		self::assertSame( 123, $logger->errors[0]['context']['user_id'] );
		self::assertSame( 'invalid_api_key', $GLOBALS['zw_test_settings_errors'][0]['code'] );
	}

	private static function newSettingsPage( RecordingLogger $logger ): SettingsPage {
		$reflection = new ReflectionClass( SettingsPage::class );
		$page       = $reflection->newInstanceWithoutConstructor();

		$logger_property = $reflection->getProperty( 'logger' );
		$logger_property->setValue( $page, $logger );

		return $page;
	}
}
