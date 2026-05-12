<?php
/**
 * Minimal WordPress loader for unit tests that need real wp_kses() and Text_Diff.
 *
 * Pulls in just enough of WordPress core (from roots/wordpress-no-content) to
 * call wp_kses() and run the WP_Text_Diff_Renderer_inline pipeline, without
 * standing up a database, plugins, or themes. Tests opt in via setUpBeforeClass.
 */

declare(strict_types=1);

if ( defined( 'ZW_TTVGPT_WP_LOADED' ) ) {
	return;
}

$zw_wp_path = dirname( __DIR__ ) . '/vendor/roots/wordpress-no-content/';
if ( ! is_file( $zw_wp_path . 'wp-includes/kses.php' ) ) {
	throw new RuntimeException( 'roots/wordpress-no-content is not installed; run composer install --dev.' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

// kses uses these globals for its allowed-html/entity tables.
$GLOBALS['wp_filter']            = $GLOBALS['wp_filter'] ?? array();
$GLOBALS['wp_current_filter']    = $GLOBALS['wp_current_filter'] ?? array();
$GLOBALS['wp_actions']           = $GLOBALS['wp_actions'] ?? array();
$GLOBALS['allowedposttags']      = $GLOBALS['allowedposttags'] ?? array();
$GLOBALS['allowedtags']          = $GLOBALS['allowedtags'] ?? array();
$GLOBALS['allowedentitynames']   = $GLOBALS['allowedentitynames'] ?? array();
$GLOBALS['allowedxmlentitynames'] = $GLOBALS['allowedxmlentitynames'] ?? array();

// Order matters: each file pulls helpers from the ones above it.
require_once $zw_wp_path . 'wp-includes/plugin.php';
require_once $zw_wp_path . 'wp-includes/formatting.php';
require_once $zw_wp_path . 'wp-includes/kses.php';

// wp_kses pulls wp_allowed_protocols() from wp-includes/functions.php, but
// loading functions.php drags in option.php which redeclares get_transient
// (already stubbed in bootstrap.php). Provide just the one helper we need.
if ( ! function_exists( 'wp_allowed_protocols' ) ) {
	function wp_allowed_protocols(): array {
		return array( 'http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 'irc6', 'ircs', 'gopher', 'nntp', 'feed', 'telnet', 'mms', 'rtsp', 'sms', 'svn', 'tel', 'fax', 'xmpp', 'webcal', 'urn' );
	}
}

// Text_Diff infrastructure used by AuditHelper::generate_word_diff.
require_once $zw_wp_path . 'wp-includes/Text/Diff.php';
require_once $zw_wp_path . 'wp-includes/Text/Diff/Engine/native.php';
require_once $zw_wp_path . 'wp-includes/Text/Diff/Renderer.php';
require_once $zw_wp_path . 'wp-includes/Text/Diff/Renderer/inline.php';
require_once $zw_wp_path . 'wp-includes/class-wp-text-diff-renderer-inline.php';

define( 'ZW_TTVGPT_WP_LOADED', true );
