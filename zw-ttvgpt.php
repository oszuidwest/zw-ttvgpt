<?php
/**
 * Plugin Name: ZuidWest TV Tekst TV GPT
 * Plugin URI: https://github.com/oszuidwest/zw-ttvgpt
 * Description: Genereert automatisch samenvattingen voor Tekst TV met behulp van OpenAI GPT-modellen
 * Version: 0.2.1-beta.2
 * Author: Streekomroep ZuidWest
 * Author URI: https://www.zuidwesttv.nl
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: zw-ttvgpt
 * Requires at least: 6.8
 * Requires PHP: 8.3
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

// Read version from plugin header (single source of truth).
$zw_ttvgpt_plugin_data = get_file_data( __FILE__, array( 'Version' => 'Version' ) );
define( 'ZW_TTVGPT_VERSION', $zw_ttvgpt_plugin_data['Version'] );
unset( $zw_ttvgpt_plugin_data );

define( 'ZW_TTVGPT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZW_TTVGPT_URL', plugin_dir_url( __FILE__ ) );

// Load Composer autoloader.
require_once ZW_TTVGPT_DIR . 'vendor/autoload.php';

use ZW_TTVGPT_Core\ApiHandler;
use ZW_TTVGPT_Core\Constants;
use ZW_TTVGPT_Core\FineTuningExport;
use ZW_TTVGPT_Core\Helper;
use ZW_TTVGPT_Core\Logger;
use ZW_TTVGPT_Core\SettingsManager;
use ZW_TTVGPT_Core\SummaryGenerator;
use ZW_TTVGPT_Core\Admin\Admin;
use ZW_TTVGPT_Core\Admin\FineTuningPage;

/**
 * Initializes plugin components with dependency injection.
 *
 * @since 1.0.0
 */
function zw_ttvgpt_init(): void {
	$logger = new Logger( SettingsManager::is_debug_mode() );

	$api_handler = new ApiHandler(
		SettingsManager::get_api_key(),
		SettingsManager::get_model(),
		$logger
	);

	$generator = new SummaryGenerator(
		$api_handler,
		SettingsManager::get_word_limit(),
		$logger
	);

	if ( is_admin() ) {
		// Initialize fine tuning export.
		$fine_tuning_export = new FineTuningExport(
			$logger,
			$api_handler,
			SettingsManager::get_word_limit()
		);
		$fine_tuning_page   = new FineTuningPage(
			$fine_tuning_export,
			$logger
		);

		new Admin( $logger, $fine_tuning_page );
	}
}
add_action( 'init', 'zw_ttvgpt_init' );

/**
 * Validates environment and initializes plugin settings on activation.
 *
 * @since 1.0.0
 */
function zw_ttvgpt_activate(): void {
	if ( version_compare( PHP_VERSION, '8.3', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 'Deze plugin vereist minimaal PHP versie 8.3.' );
	}

	if ( ! get_option( Constants::SETTINGS_OPTION_NAME ) ) {
		add_option(
			Constants::SETTINGS_OPTION_NAME,
			Constants::get_default_settings()
		);
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'zw_ttvgpt_activate' );

/**
 * Cleans up temporary data and rewrite rules on deactivation.
 *
 * @since 1.0.0
 */
function zw_ttvgpt_deactivate(): void {
	Helper::cleanup_transients();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'zw_ttvgpt_deactivate' );
