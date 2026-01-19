<?php
/**
 * Uninstall ZW TTVGPT.
 *
 * Removes all plugin data when uninstalled.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/Constants.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/SettingsManager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/Helper.php';

ZW_TTVGPT_Core\Helper::cleanup_plugin_data();
if ( is_multisite() ) {
	$zw_ttvgpt_sites = get_sites();

	foreach ( $zw_ttvgpt_sites as $zw_ttvgpt_site ) {
		switch_to_blog( $zw_ttvgpt_site->blog_id );
		ZW_TTVGPT_Core\Helper::cleanup_plugin_data();
		restore_current_blog();
	}
}
