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

	$GLOBALS['zw_test_current_screen'] = false;
	$GLOBALS['zw_test_inline_scripts'] = array();
	$GLOBALS['zw_test_styles']         = array();

	if ( ! function_exists( 'add_action' ) ) {
		require_once ABSPATH . 'wp-includes/plugin.php';
	}

	if ( ! function_exists( 'get_current_screen' ) ) {
		function get_current_screen(): object|false {
			return $GLOBALS['zw_test_current_screen'];
		}
	}

	if ( ! function_exists( 'wp_create_nonce' ) ) {
		function wp_create_nonce( string $action = '-1' ): string {
			return 'test-nonce-' . $action;
		}
	}

	if ( ! function_exists( 'wp_enqueue_style' ) ) {
		function wp_enqueue_style(
			string $handle,
			string $src = '',
			array $deps = array(),
			mixed $ver = false,
			string $media = 'all'
		): void {
			$GLOBALS['zw_test_styles'][] = array(
				'handle' => $handle,
				'src'    => $src,
				'deps'   => $deps,
				'ver'    => $ver,
				'media'  => $media,
			);
		}
	}

	if ( ! function_exists( 'wp_print_inline_script_tag' ) ) {
		function wp_print_inline_script_tag( string $script, array $attributes = array() ): void {
			$GLOBALS['zw_test_inline_scripts'][] = array(
				'script'     => $script,
				'attributes' => $attributes,
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
	use ZW_TTVGPT_Core\Helper;

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
			$GLOBALS['zw_test_inline_scripts'] = array();
			$GLOBALS['zw_test_styles']         = array();
			$GLOBALS['zw_test_current_screen'] = false;
			remove_all_actions( 'admin_print_footer_scripts' );
			remove_all_actions( 'zw_ttvgpt_diff_sanitizer_failed' );
		}

		protected function tearDown(): void {
			remove_all_actions( 'admin_print_footer_scripts' );
			remove_all_actions( 'zw_ttvgpt_diff_sanitizer_failed' );
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

		public function test_editor_config_exposes_word_token_pattern(): void {
			$logger = new RecordingLogger();
			$menu   = self::newAdminMenu( $logger, new GoodInlineConfigSettingsPage() );

			$GLOBALS['zw_test_current_screen'] = (object) array( 'post_type' => Constants::SUPPORTED_POST_TYPE );

			$menu->enqueue_admin_assets( 'post.php' );
			do_action( 'admin_print_footer_scripts' );

			self::assertSame( 'zw-ttvgpt-admin', $GLOBALS['zw_test_script_modules'][0]['id'] );
			self::assertCount( 1, $GLOBALS['zw_test_inline_scripts'] );

			$config = self::decodeInlineConfig( $GLOBALS['zw_test_inline_scripts'][0]['script'] );
			self::assertSame( Helper::WORD_TOKEN_PATTERN, $config['wordTokenPattern'] );
		}

		public function test_diff_sanitizer_failure_hook_is_logged(): void {
			$logger = new RecordingLogger();

			new AdminMenu( $logger );
			do_action( 'zw_ttvgpt_diff_sanitizer_failed', 'wp_kses_non_string', 'pre_kses returned array' );

			self::assertCount( 1, $logger->errors );
			self::assertSame( 'Diff sanitizer failed', $logger->errors[0]['message'] );
			self::assertSame(
				array(
					'reason'        => 'wp_kses_non_string',
					'error_message' => 'pre_kses returned array',
				),
				$logger->errors[0]['context']
			);
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

		/**
		 * @return array<string, mixed>
		 */
		private static function decodeInlineConfig( string $script ): array {
			self::assertSame( 1, preg_match( '/^window\.zwTTVGPT = (.*);$/', $script, $matches ) );

			$decoded = json_decode( $matches[1], true );
			self::assertIsArray( $decoded );

			return $decoded;
		}
	}
}
