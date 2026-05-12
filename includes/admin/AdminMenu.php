<?php
/**
 * Admin Menu class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ZW_TTVGPT_Core\Constants;
use ZW_TTVGPT_Core\Helper;
use ZW_TTVGPT_Core\Logger;
use ZW_TTVGPT_Core\SettingsManager;

/**
 * Admin Menu class.
 *
 * Handles WordPress admin menu registration and asset loading.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class AdminMenu {
	/**
	 * Cached SettingsPage instance so menu registration and asset enqueuing share state.
	 *
	 * @since 1.0.0
	 * @var SettingsPage
	 */
	private readonly SettingsPage $settings_page;

	/**
	 * Initializes the admin menu and registers WordPress hooks.
	 *
	 * @since 1.0.0
	 *
	 * @param Logger $logger Logger instance for debugging.
	 */
	public function __construct( private readonly Logger $logger ) {
		$this->settings_page = new SettingsPage( $this->logger );

		add_action( 'admin_menu', $this->add_admin_menu( ... ) );
		add_action( 'admin_enqueue_scripts', $this->enqueue_admin_assets( ... ) );
	}

	/**
	 * Adds plugin settings page to WordPress admin menu.
	 *
	 * @since 1.0.0
	 */
	public function add_admin_menu(): void {
		add_options_page(
			__( 'Tekst TV GPT Instellingen', 'zw-ttvgpt' ),
			__( 'Tekst TV GPT', 'zw-ttvgpt' ),
			Constants::REQUIRED_CAPABILITY,
			Constants::SETTINGS_PAGE_SLUG,
			array( $this->settings_page, 'render' )
		);

		add_management_page(
			__( 'Tekst TV Audit', 'zw-ttvgpt' ),
			__( 'Tekst TV Audit', 'zw-ttvgpt' ),
			Constants::REQUIRED_CAPABILITY,
			'zw-ttvgpt-audit',
			array( new AuditPage(), 'render' )
		);
	}

	/**
	 * Enqueues plugin stylesheets and JavaScript modules for the admin interface.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		$version = Helper::get_asset_version();

		// Inline config blobs below print at admin_print_footer_scripts priority 5
		// so they land in the document before wp_enqueue_script_module's deferred
		// module tag executes and reads window.zwTTVGPT*.
		// Enqueue audit assets on audit page.
		if ( 'tools_page_zw-ttvgpt-audit' === $hook ) {
			wp_enqueue_style( 'zw-ttvgpt-audit', ZW_TTVGPT_URL . 'assets/audit.css', array(), $version );

			$this->print_inline_config( 'zwTTVGPTAudit', array( 'baseUrl' => admin_url( 'tools.php' ) ) );
			wp_enqueue_script_module( 'zw-ttvgpt-audit', ZW_TTVGPT_URL . 'assets/audit.js', array(), $version );
			return;
		}

		// Enqueue settings JS on the settings page.
		if ( 'settings_page_' . Constants::SETTINGS_PAGE_SLUG === $hook ) {
			$this->print_inline_config( 'zwTTVGPTSettings', $this->settings_page->get_legacy_model_toggle_config() );
			wp_enqueue_script_module( 'zw-ttvgpt-settings', ZW_TTVGPT_URL . 'assets/settings.js', array(), $version );
			return;
		}

		// Enqueue admin assets on post edit screens.
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || Constants::SUPPORTED_POST_TYPE !== $screen->post_type ) {
			return;
		}

		// Enqueue CSS.
		wp_enqueue_style( 'zw-ttvgpt-admin', ZW_TTVGPT_URL . 'assets/admin.css', array(), $version );

		// Inline script data before module loads (wp_enqueue_script_module doesn't support wp_localize_script).
		$inline_data = array(
			'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
			'nonce'          => wp_create_nonce( 'zw_ttvgpt_nonce' ),
			'acfFields'      => Helper::get_acf_field_ids(),
			'debugMode'      => SettingsManager::is_debug_mode(),
			'wordLimit'      => SettingsManager::get_word_limit(),
			'animationDelay' => array(
				'min'   => 20,
				'max'   => 50,
				'space' => 30,
			),
			'timeouts'       => array( 'successMessage' => 3000 ),
			'strings'        => array(
				'generating'      => __( 'Genereren...', 'zw-ttvgpt' ),
				'error'           => __( 'Fout opgetreden', 'zw-ttvgpt' ),
				'success'         => __( 'Klaar!', 'zw-ttvgpt' ),
				'buttonText'      => __( 'Genereer', 'zw-ttvgpt' ),
				'loadingMessages' => array(
					__( '🤔 Even nadenken...', 'zw-ttvgpt' ),
					__( '📰 Artikel aan het lezen...', 'zw-ttvgpt' ),
					__( '✨ AI magie aan het werk...', 'zw-ttvgpt' ),
					__( '🔍 De essentie aan het vinden...', 'zw-ttvgpt' ),
					__( '📝 Aan het samenvatten...', 'zw-ttvgpt' ),
					__( '🎯 Belangrijkste punten selecteren...', 'zw-ttvgpt' ),
					__( '🧠 Neuronen aan het vuren...', 'zw-ttvgpt' ),
					__( '🚀 Tekst TV klaar maken...', 'zw-ttvgpt' ),
					__( '🎨 Tekst aan het polijsten...', 'zw-ttvgpt' ),
					__( '🌟 Briljante samenvatting maken...', 'zw-ttvgpt' ),
				),
			),
		);

		// Print inline config data before module loads.
		$this->print_inline_config( 'zwTTVGPT', $inline_data );

		// Enqueue ES module (WordPress 6.5+).
		wp_enqueue_script_module( 'zw-ttvgpt-admin', ZW_TTVGPT_URL . 'assets/admin.js', array(), $version );
	}

	/**
	 * Schedules an inline window.<global_name> = <json>; tag at footer priority 5.
	 *
	 * Encodes upfront so a wp_json_encode failure logs and aborts instead of
	 * emitting `window.X = ;` — a SyntaxError that silently breaks the matching
	 * ES module on the page.
	 *
	 * @since 1.0.0
	 *
	 * @param string $global_name Bare window property name (without `window.` prefix).
	 * @param array  $config      Configuration to expose to the module.
	 *
	 * @phpstan-param array<string, mixed> $config
	 */
	private function print_inline_config( string $global_name, array $config ): void {
		$json = wp_json_encode( $config );
		if ( false === $json ) {
			$this->logger->error( sprintf( 'Failed to encode inline config for window.%s', $global_name ) );
			return;
		}

		add_action(
			'admin_print_footer_scripts',
			static function () use ( $global_name, $json ): void {
				wp_print_inline_script_tag( sprintf( 'window.%s = %s;', $global_name, $json ) );
			},
			5
		);
	}
}
