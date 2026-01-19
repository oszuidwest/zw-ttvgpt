<?php
/**
 * PHPStan bootstrap file
 *
 * @package ZW_TTVGPT
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

define( 'ZW_TTVGPT_VERSION', '1.0.0' );
define( 'ZW_TTVGPT_DIR', __DIR__ . '/' );
define( 'ZW_TTVGPT_URL', 'https://example.com/wp-content/plugins/zw-ttvgpt/' );
define( 'ZW_TTVGPT_SETTINGS', 'zw_ttvgpt_settings' );
define( 'ZW_TTVGPT_CAPABILITY', 'edit_posts' );

define( 'WPINC', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Core WordPress constant
