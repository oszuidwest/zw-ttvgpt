<?php
/**
 * AJAX Security Trait for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * AJAX Security Trait
 *
 * Provides common AJAX security validation methods
 */
trait TTVGPTAjaxSecurity {
	/**
	 * Validate AJAX nonce and capabilities
	 *
	 * @param string $nonce_action The nonce action to check
	 * @param string $required_capability Required user capability
	 * @return void Exits with JSON error if validation fails
	 */
	protected function validate_ajax_request( string $nonce_action, string $required_capability ): void {
		if ( ! check_ajax_referer( $nonce_action, 'nonce', false ) ) {
			wp_send_json_error( 
				array( 'message' => __( 'Beveiligingscontrole mislukt', 'zw-ttvgpt' ) ), 
				403 
			);
		}

		if ( ! current_user_can( $required_capability ) ) {
			wp_send_json_error( 
				array( 'message' => __( 'Onvoldoende rechten', 'zw-ttvgpt' ) ), 
				403 
			);
		}
	}

	/**
	 * Validate page access capability
	 *
	 * @param string $required_capability Required user capability
	 * @param string $error_message Custom error message (optional)
	 * @return void Exits with wp_die if validation fails
	 */
	protected function validate_page_access( string $required_capability, string $error_message = '' ): void {
		if ( ! current_user_can( $required_capability ) ) {
			$message = $error_message ?: __( 'Je hebt geen toestemming om deze pagina te bekijken.', 'zw-ttvgpt' );
			wp_die( esc_html( $message ) );
		}
	}
}