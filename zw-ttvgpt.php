<?php
/**
 * Plugin Name: ZuidWest TV Tekst TV GPT
 * Plugin URI: https://github.com/oszuidwest/zw-ttvgpt
 * Description: Genereert automatisch samenvattingen voor Tekst TV met behulp van OpenAI's GPT modellen
 * Version: 0.10.1
 * Author: Streekomroep ZuidWest
 * Author URI: https://www.zuidwesttv.nl
 * License: GPL v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: zw-ttvgpt
 * Requires at least: 6.0
 * Requires PHP: 8.2
 *
 * @package ZW_TTVGPT
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'ZW_TTVGPT_VERSION', '0.10.1' );
define( 'ZW_TTVGPT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZW_TTVGPT_URL', plugin_dir_url( __FILE__ ) );
require_once ZW_TTVGPT_DIR . 'includes/class-constants.php';
require_once ZW_TTVGPT_DIR . 'includes/class-settings-manager.php';
require_once ZW_TTVGPT_DIR . 'includes/class-api-error-handler.php';
require_once ZW_TTVGPT_DIR . 'includes/class-rate-limiter.php';
require_once ZW_TTVGPT_DIR . 'includes/class-audit-helper.php';
require_once ZW_TTVGPT_DIR . 'includes/class-helper.php';
require_once ZW_TTVGPT_DIR . 'includes/class-logger.php';
require_once ZW_TTVGPT_DIR . 'includes/class-api-handler.php';
require_once ZW_TTVGPT_DIR . 'includes/class-summary-generator.php';
require_once ZW_TTVGPT_DIR . 'includes/class-admin-menu.php';
require_once ZW_TTVGPT_DIR . 'includes/class-settings-page.php';
require_once ZW_TTVGPT_DIR . 'includes/class-audit-page.php';
require_once ZW_TTVGPT_DIR . 'includes/class-admin.php';

/**
 * Initialize plugin components with dependency injection
 *
 * @return void
 */
function zw_ttvgpt_init() {
	load_plugin_textdomain( 'zw-ttvgpt', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	$logger = new ZW_TTVGPT_Core\TTVGPTLogger( ZW_TTVGPT_Core\TTVGPTSettingsManager::is_debug_mode() );

	$api_handler = new ZW_TTVGPT_Core\TTVGPTApiHandler(
		ZW_TTVGPT_Core\TTVGPTSettingsManager::get_api_key(),
		ZW_TTVGPT_Core\TTVGPTSettingsManager::get_model(),
		$logger
	);

	$generator = new ZW_TTVGPT_Core\TTVGPTSummaryGenerator(
		$api_handler,
		ZW_TTVGPT_Core\TTVGPTSettingsManager::get_word_limit(),
		$logger
	);

	if ( is_admin() ) {
		new ZW_TTVGPT_Core\TTVGPTAdmin( $logger );
	}
}
add_action( 'init', 'zw_ttvgpt_init' );

/**
 * Validates environment and initializes plugin settings on activation
 *
 * @return void
 */
function zw_ttvgpt_activate() {
	if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 'This plugin requires PHP 8.2 or higher.' );
	}

	if ( ! function_exists( 'get_field' ) && ! defined( 'WP_CLI' ) && ! getenv( 'CI' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 'This plugin requires Advanced Custom Fields to be installed and activated.' );
	}

	if ( ! get_option( ZW_TTVGPT_Core\TTVGPTConstants::SETTINGS_OPTION_NAME ) ) {
		add_option(
			ZW_TTVGPT_Core\TTVGPTConstants::SETTINGS_OPTION_NAME,
			ZW_TTVGPT_Core\TTVGPTConstants::get_default_settings()
		);
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'zw_ttvgpt_activate' );

/**
 * Cleans up temporary data and rewrite rules on deactivation
 *
 * @return void
 */
function zw_ttvgpt_deactivate() {
	ZW_TTVGPT_Core\TTVGPTHelper::cleanup_transients();
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'zw_ttvgpt_deactivate' );
