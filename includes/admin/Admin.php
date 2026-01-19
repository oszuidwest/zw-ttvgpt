<?php
/**
 * Admin class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

namespace ZW_TTVGPT_Core\Admin;

use ZW_TTVGPT_Core\Logger;

/**
 * Admin class
 *
 * Main admin interface coordinator
 *
 * @package ZW_TTVGPT
 */
class Admin {
	/**
	 * Initialize admin interface
	 *
	 * @param Logger         $logger           Logger instance for debugging.
	 * @param FineTuningPage $fine_tuning_page Fine tuning page instance.
	 */
	public function __construct( Logger $logger, FineTuningPage $fine_tuning_page ) {
		// Initialize admin components.
		new AdminMenu( $logger, $fine_tuning_page );
	}
}
