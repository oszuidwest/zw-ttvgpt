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
	 * Logger instance
	 *
	 * @var TTVGPTLogger
	 */
	private TTVGPTLogger $logger;

	/**
	 * Initialize admin interface
	 *
	 * @param TTVGPTLogger $logger Logger instance for debugging
	 */
	public function __construct( TTVGPTLogger $logger ) {
		$this->logger = $logger;

		// Initialize admin components
		new TTVGPTAdminMenu( $logger );
	}
}
