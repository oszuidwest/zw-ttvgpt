<?php
declare(strict_types=1);

namespace {
	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	if ( ! defined( 'ZW_TTVGPT_VERSION' ) ) {
		define( 'ZW_TTVGPT_VERSION', '1.0.0' );
	}
	if ( ! defined( 'ZW_TTVGPT_URL' ) ) {
		define( 'ZW_TTVGPT_URL', 'https://example.test/wp-content/plugins/zw-ttvgpt/' );
	}

	if ( ! function_exists( 'add_action' ) ) {
		require_once ABSPATH . 'wp-includes/plugin.php';
	}

	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( mixed $value, int $flags = 0, int $depth = 512 ): string|false {
			return json_encode( $value, $flags, $depth );
		}
	}

	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( string $path = '', string $scheme = 'admin' ): string {
			return 'https://example.test/wp-admin/' . $path;
		}
	}

	if ( ! function_exists( 'wp_cache_get' ) ) {
		function wp_cache_get( string $key, string $group = '', bool $force = false, ?bool &$found = null ): mixed {
			$found = false;
			return false;
		}
	}

	if ( ! function_exists( 'wp_cache_set' ) ) {
		function wp_cache_set( string $key, mixed $data, string $group = '', int $expire = 0 ): bool {
			return true;
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( string $option, mixed $default_value = false ): mixed {
			return $default_value;
		}
	}

	if ( ! function_exists( 'wp_enqueue_script_module' ) ) {
		function wp_enqueue_script_module( string $id, string $src = '', array $deps = array(), mixed $version = false, array $args = array() ): void {
			$GLOBALS['zw_test_script_modules'][] = array(
				'id'      => $id,
				'src'     => $src,
				'deps'    => $deps,
				'version' => $version,
				'args'    => $args,
			);
		}
	}
}

namespace ZW_TTVGPT_Core\Tests {

	use PHPUnit\Framework\Attributes\CoversClass;
	use PHPUnit\Framework\TestCase;
	use ReflectionClass;
	use ZW_TTVGPT_Core\Admin\AdminMenu;
	use ZW_TTVGPT_Core\Admin\SettingsPage;
	use ZW_TTVGPT_Core\Constants;

	final class BadInlineConfigSettingsPage extends SettingsPage {
		public function __construct() {}

		public function get_legacy_model_toggle_config(): array {
			return array( 'bad' => INF );
		}
	}

	final class GoodInlineConfigSettingsPage extends SettingsPage {
		public function __construct() {}

		public function get_legacy_model_toggle_config(): array {
			return array(
				'fieldName'         => Constants::SETTINGS_OPTION_NAME . '[model]',
				'legacyOptionValue' => 'legacy-fine-tuned',
			);
		}
	}

	#[CoversClass(AdminMenu::class)]
	final class AdminMenuInlineConfigTest extends TestCase {

		protected function setUp(): void {
			$GLOBALS['zw_test_script_modules'] = array();
		}

		public function test_settings_module_is_not_enqueued_when_inline_config_encoding_fails(): void {
			$logger = new RecordingLogger();
			$menu   = self::newAdminMenu( $logger, new BadInlineConfigSettingsPage() );

			$menu->enqueue_admin_assets( 'settings_page_' . Constants::SETTINGS_PAGE_SLUG );

			self::assertSame( array(), $GLOBALS['zw_test_script_modules'] );
			self::assertCount( 1, $logger->errors );
			self::assertStringContainsString( 'Failed to encode inline config for window.zwTTVGPTSettings', $logger->errors[0]['message'] );
			self::assertSame( 'Inf and NaN cannot be JSON encoded', $logger->errors[0]['context']['json_error'] );
		}

		public function test_settings_module_is_enqueued_when_inline_config_encoding_succeeds(): void {
			$logger = new RecordingLogger();
			$menu   = self::newAdminMenu( $logger, new GoodInlineConfigSettingsPage() );

			$menu->enqueue_admin_assets( 'settings_page_' . Constants::SETTINGS_PAGE_SLUG );

			self::assertSame( 'zw-ttvgpt-settings', $GLOBALS['zw_test_script_modules'][0]['id'] );
			self::assertSame( array(), $logger->errors );
		}

		private static function newAdminMenu( RecordingLogger $logger, SettingsPage $settings_page ): AdminMenu {
			$reflection = new ReflectionClass( AdminMenu::class );
			$menu       = $reflection->newInstanceWithoutConstructor();

			$logger_property = $reflection->getProperty( 'logger' );
			$logger_property->setValue( $menu, $logger );

			$settings_page_property = $reflection->getProperty( 'settings_page' );
			$settings_page_property->setValue( $menu, $settings_page );

			return $menu;
		}
	}
}
