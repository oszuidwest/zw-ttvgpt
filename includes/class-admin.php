<?php
/**
 * Admin class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Admin class
 *
 * Main admin interface coordinator
 */
class TTVGPTAdmin {
	/**
	 * Initialize admin interface
	 *
	 * @param TTVGPTLogger         $logger            Logger instance for debugging
	 * @param TTVGPTFineTuningPage $fine_tuning_page  Fine tuning page instance
	 */
	public function __construct( TTVGPTLogger $logger, TTVGPTFineTuningPage $fine_tuning_page ) {
		// Initialize admin components
		new TTVGPTAdminMenu( $logger, $fine_tuning_page );
	}
}
