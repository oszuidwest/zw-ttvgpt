<?php
/**
 * AJAX Security Trait for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX Security Trait.
 *
 * Provides common AJAX security validation methods.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
trait AjaxSecurity {
	/**
	 * Validates AJAX nonce and capabilities.
	 *
	 * Exits with JSON error if validation fails.
	 *
	 * @since 1.0.0
	 *
	 * @param string $nonce_action        The nonce action to check.
	 * @param string $required_capability Required user capability.
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
	 * Validates page access capability.
	 *
	 * Exits with wp_die if validation fails.
	 *
	 * @since 1.0.0
	 *
	 * @param string $required_capability Required user capability.
	 * @param string $error_message       Optional. Custom error message. Default empty.
	 */
	protected function validate_page_access( string $required_capability, string $error_message = '' ): void {
		if ( ! current_user_can( $required_capability ) ) {
			$message = $error_message ? $error_message : __( 'Je hebt geen toestemming om deze pagina te bekijken.', 'zw-ttvgpt' );
			wp_die( esc_html( $message ) );
		}
	}
}
