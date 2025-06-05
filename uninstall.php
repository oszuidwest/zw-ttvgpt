<?php
/**
 * Uninstall ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Include required files.
require_once plugin_dir_path( __FILE__ ) . 'includes/class-constants.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-settings-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-helper.php';

// Clean up for single site.
ZW_TTVGPT_Core\TTVGPTHelper::cleanup_plugin_data();

// For multisite.
if ( is_multisite() ) {
	$zw_ttvgpt_sites = get_sites();

	foreach ( $zw_ttvgpt_sites as $zw_ttvgpt_site ) {
		switch_to_blog( $zw_ttvgpt_site->blog_id );

		// Clean up site data.
		ZW_TTVGPT_Core\TTVGPTHelper::cleanup_plugin_data();

		restore_current_blog();
	}
}
