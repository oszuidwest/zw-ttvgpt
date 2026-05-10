<?php
/**
 * PHPStan bootstrap file - never loaded by WordPress.
 *
 * The OR-define pattern below satisfies WordPress plugin-check's direct-access
 * guard while still letting PHPStan execute the rest of this file (PHPStan
 * runs outside WordPress, so ABSPATH is not defined there).
 *
 * @package ZW_TTVGPT
 */

defined( 'ABSPATH' ) || define( 'ABSPATH', __DIR__ );

define( 'ZW_TTVGPT_VERSION', '1.0.0' );
define( 'ZW_TTVGPT_DIR', __DIR__ . '/' );
define( 'ZW_TTVGPT_URL', 'https://example.com/wp-content/plugins/zw-ttvgpt/' );
define( 'ZW_TTVGPT_SETTINGS', 'zw_ttvgpt_settings' );
define( 'ZW_TTVGPT_CAPABILITY', 'edit_posts' );

define( 'WPINC', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Core WordPress constant
