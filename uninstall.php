<?php
/**
 * Uninstall ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . 'includes/class-constants.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-settings-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-helper.php';

ZW_TTVGPT_Core\TTVGPTHelper::cleanup_plugin_data();
if ( is_multisite() ) {
	$zw_ttvgpt_sites = get_sites();

	foreach ( $zw_ttvgpt_sites as $zw_ttvgpt_site ) {
		switch_to_blog( $zw_ttvgpt_site->blog_id );
		ZW_TTVGPT_Core\TTVGPTHelper::cleanup_plugin_data();
		restore_current_blog();
	}
}
