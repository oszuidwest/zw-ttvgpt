<?php
/**
 * PHPStan bootstrap; not loaded by WordPress.
 *
 * ABSPATH is runtime-defined by phpstan-wordpress, so the direct-access guard
 * remains plugin-check compatible.
 *
 * @package ZW_TTVGPT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZW_TTVGPT_VERSION', '1.0.0' );
define( 'ZW_TTVGPT_DIR', __DIR__ . '/' );
define( 'ZW_TTVGPT_URL', 'https://example.com/wp-content/plugins/zw-ttvgpt/' );
define( 'ZW_TTVGPT_SETTINGS', 'zw_ttvgpt_settings' );
define( 'ZW_TTVGPT_CAPABILITY', 'edit_posts' );

define( 'WPINC', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Core WordPress constant
