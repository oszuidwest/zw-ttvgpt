<?php
/**
 * Admin class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ZW_TTVGPT_Core\Logger;

/**
 * Admin class.
 *
 * Main admin interface coordinator.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class Admin {
	/**
	 * Initializes the admin interface.
	 *
	 * @since 1.0.0
	 *
	 * @param Logger $logger Logger instance for debugging.
	 */
	public function __construct( Logger $logger ) {
		// Initialize admin components.
		new AdminMenu( $logger );
	}
}
