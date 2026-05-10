<?php
/**
 * PHPStan bootstrap file - never loaded by WordPress.
 *
 * The canonical ABSPATH guard satisfies WordPress plugin-check's direct-access
 * rule. PHPStan can still execute the rest of this file because
 * szepeviktor/phpstan-wordpress runtime-defines ABSPATH in its extension
 * bootstrap, so the guard short-circuits during analysis.
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
