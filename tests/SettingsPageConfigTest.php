<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ZW_TTVGPT_Core\Admin\SettingsPage;
use ZW_TTVGPT_Core\Constants;

#[CoversClass(SettingsPage::class)]
final class SettingsPageConfigTest extends TestCase {

	public function test_legacy_model_toggle_config_shape_is_stable(): void {
		$reflection = new ReflectionClass( SettingsPage::class );
		$page       = $reflection->newInstanceWithoutConstructor();

		self::assertSame(
			array(
				'fieldName'         => Constants::SETTINGS_OPTION_NAME . '[model]',
				'legacyOptionValue' => 'legacy-fine-tuned',
			),
			$page->get_legacy_model_toggle_config()
		);
	}
}
