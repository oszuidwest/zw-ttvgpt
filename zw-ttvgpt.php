<?php
/**
 * Plugin Name: ZuidWest TV Tekst TV GPT
 * Plugin URI: https://github.com/oszuidwest/zw-ttvgpt
 * Description: Genereert automatisch samenvattingen voor Tekst TV met behulp van OpenAI's GPT modellen
 * Version: 0.9.0
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

// If this file is called directly, abort
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Define plugin constants
define( 'ZW_TTVGPT_VERSION', '1.0.0' );
define( 'ZW_TTVGPT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZW_TTVGPT_URL', plugin_dir_url( __FILE__ ) );

// Include required files
require_once ZW_TTVGPT_DIR . 'includes/class-constants.php';
require_once ZW_TTVGPT_DIR . 'includes/class-settings-manager.php';
require_once ZW_TTVGPT_DIR . 'includes/class-helper.php';
require_once ZW_TTVGPT_DIR . 'includes/class-logger.php';
require_once ZW_TTVGPT_DIR . 'includes/class-api-handler.php';
require_once ZW_TTVGPT_DIR . 'includes/class-summary-generator.php';
require_once ZW_TTVGPT_DIR . 'includes/class-admin.php';

/**
 * Initialize the plugin
 *
 * @return void
 */
function zw_ttvgpt_init() {
	// Load plugin textdomain
	load_plugin_textdomain( 'zw-ttvgpt', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// Initialize logger
	$logger = new ZW_TTVGPT_Core\TTVGPTLogger( ZW_TTVGPT_Core\TTVGPTSettingsManager::is_debug_mode() );

	// Initialize API handler
	$api_handler = new ZW_TTVGPT_Core\TTVGPTApiHandler(
		ZW_TTVGPT_Core\TTVGPTSettingsManager::get_api_key(),
		ZW_TTVGPT_Core\TTVGPTSettingsManager::get_model(),
		$logger
	);

	// Initialize summary generator
	$generator = new ZW_TTVGPT_Core\TTVGPTSummaryGenerator(
		$api_handler,
		ZW_TTVGPT_Core\TTVGPTSettingsManager::get_word_limit(),
		$logger
	);

	// Initialize admin interface
	if ( is_admin() ) {
		new ZW_TTVGPT_Core\TTVGPTAdmin( $logger );
	}
}
add_action( 'init', 'zw_ttvgpt_init' );

/**
 * Plugin activation hook
 *
 * @return void
 */
function zw_ttvgpt_activate() {
	// Check PHP version
	if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 'This plugin requires PHP 8.2 or higher.' );
	}

	// Check if ACF is active
	if ( ! function_exists( 'get_field' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( 'This plugin requires Advanced Custom Fields to be installed and activated.' );
	}

	// Set default options
	if ( ! get_option( ZW_TTVGPT_Core\TTVGPTConstants::SETTINGS_OPTION_NAME ) ) {
		add_option(
			ZW_TTVGPT_Core\TTVGPTConstants::SETTINGS_OPTION_NAME,
			ZW_TTVGPT_Core\TTVGPTConstants::get_default_settings()
		);
	}

	// Flush rewrite rules
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'zw_ttvgpt_activate' );

/**
 * Plugin deactivation hook
 *
 * @return void
 */
function zw_ttvgpt_deactivate() {
	// Clean up any temporary data
	ZW_TTVGPT_Core\TTVGPTHelper::cleanup_transients();

	// Flush rewrite rules
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'zw_ttvgpt_deactivate' );
