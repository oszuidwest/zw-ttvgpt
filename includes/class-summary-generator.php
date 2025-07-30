<?php
/**
 * Summary Generator class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Summary Generator class
 *
 * Core functionality for generating summaries
 */
class TTVGPTSummaryGenerator {
	use TTVGPTAjaxSecurity;

	/**
	 * API handler instance
	 *
	 * @var TTVGPTApiHandler
	 */
	private TTVGPTApiHandler $api_handler;

	/**
	 * Word limit for summaries
	 *
	 * @var int
	 */
	private int $word_limit;

	/**
	 * Logger instance
	 *
	 * @var TTVGPTLogger
	 */
	private TTVGPTLogger $logger;

	/**
	 * Version manager instance
	 *
	 * @var TTVGPTVersionManager
	 */
	private TTVGPTVersionManager $version_manager;

	/**
	 * Initialize summary generator with dependencies and register AJAX handler
	 *
	 * @param TTVGPTApiHandler     $api_handler     API handler for OpenAI communication
	 * @param int                  $word_limit      Maximum words allowed in summaries
	 * @param TTVGPTLogger         $logger          Logger instance for debugging
	 * @param TTVGPTVersionManager $version_manager Version manager instance
	 */
	public function __construct( TTVGPTApiHandler $api_handler, int $word_limit, TTVGPTLogger $logger, TTVGPTVersionManager $version_manager ) {
		$this->api_handler     = $api_handler;
		$this->word_limit      = $word_limit;
		$this->logger          = $logger;
		$this->version_manager = $version_manager;

		add_action( 'wp_ajax_zw_ttvgpt_generate', array( $this, 'handle_ajax_request' ) );
		add_action( 'wp_ajax_zw_ttvgpt_get_versions', array( $this, 'handle_get_versions_request' ) );
		add_action( 'wp_ajax_zw_ttvgpt_set_version', array( $this, 'handle_set_version_request' ) );
	}

	/**
	 * Process AJAX request for generating summary with security and validation
	 *
	 * @return void
	 */
	public function handle_ajax_request(): void {
		// Verify nonce first
		if ( ! check_ajax_referer( 'zw_ttvgpt_nonce', 'nonce', false ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Beveiligingscontrole mislukt', 'zw-ttvgpt' ) ),
				403
			);
		}

		// Check capability
		if ( ! current_user_can( TTVGPTConstants::EDIT_CAPABILITY ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Onvoldoende rechten', 'zw-ttvgpt' ) ),
				403
			);
		}

		if ( empty( TTVGPTSettingsManager::get_api_key() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'API key niet geconfigureerd. Ga naar Instellingen > ZW Tekst TV GPT om een API key in te stellen.', 'zw-ttvgpt' ) ),
				400
			);
		}

		// Get content and post ID from request
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( empty( $content ) || ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( __( 'Ongeldige gegevens', 'zw-ttvgpt' ), 400 );
		}

		$clean_content = $this->api_handler->prepare_content( $content );
		$word_count    = str_word_count( $clean_content );

		if ( $word_count < TTVGPTConstants::MIN_WORD_COUNT ) {
			wp_send_json_error(
				sprintf(
					/* translators: 1: Minimum required word count, 2: Found word count */
					__( 'Te weinig woorden. Minimaal %1$d vereist, %2$d gevonden.', 'zw-ttvgpt' ),
					TTVGPTConstants::MIN_WORD_COUNT,
					$word_count
				),
				400
			);
		}

		$user_id = get_current_user_id();
		if ( TTVGPTRateLimiter::is_limited( $user_id ) ) {
			wp_send_json_error( __( 'Te veel aanvragen. Wacht even voordat je opnieuw probeert.', 'zw-ttvgpt' ), 429 );
		}

		$result = $this->api_handler->generate_summary( $clean_content, $this->word_limit );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 500 );
		}

		$regions = isset( $_POST['regions'] ) ? array_map( 'sanitize_text_field', (array) $_POST['regions'] ) : array();
		$summary = $result;

		if ( ! empty( $regions ) ) {
			$summary = implode( ' / ', array_map( 'strtoupper', $regions ) ) . ' - ' . $summary;
		}

		$this->save_to_acf( $post_id, $summary, $regions );
		TTVGPTRateLimiter::increment( $user_id );
		$this->logger->debug( 'Summary generated for post ' . $post_id );

		wp_send_json_success(
			array(
				'summary'    => $summary,
				'word_count' => str_word_count( $summary ),
			)
		);
	}

	/**
	 * Save generated summary to ACF fields and version history
	 *
	 * @param int    $post_id Post ID to update
	 * @param string $summary Generated summary text
	 * @param array  $regions Selected regions
	 * @return void
	 */
	private function save_to_acf( int $post_id, string $summary, array $regions = array() ): void {
		if ( ! function_exists( 'update_field' ) ) {
			$this->logger->error( 'ACF not available' );
			return;
		}

		// Save to version history
		$model = TTVGPTSettingsManager::get_model();
		$this->version_manager->save_version( $post_id, $summary, $model, $regions );

		// Update ACF fields
		update_field( TTVGPTConstants::ACF_SUMMARY_FIELD, $summary, $post_id );
		update_field( TTVGPTConstants::ACF_GPT_MARKER_FIELD, $summary, $post_id );

		$this->logger->debug(
			'Saved summary to ACF fields and version history',
			array(
				'post_id' => $post_id,
			)
		);
	}

	/**
	 * Handle AJAX request to get version history
	 *
	 * @return void
	 */
	public function handle_get_versions_request(): void {
		// Verify nonce and capability
		if ( ! check_ajax_referer( 'zw_ttvgpt_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Beveiligingscontrole mislukt', 'zw-ttvgpt' ), 403 );
		}

		if ( ! current_user_can( TTVGPTConstants::EDIT_CAPABILITY ) ) {
			wp_send_json_error( __( 'Onvoldoende rechten', 'zw-ttvgpt' ), 403 );
		}

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( __( 'Ongeldige post ID', 'zw-ttvgpt' ), 400 );
		}

		$versions = $this->version_manager->get_version_history( $post_id );
		wp_send_json_success( array( 'versions' => $versions ) );
	}

	/**
	 * Handle AJAX request to set active version
	 *
	 * @return void
	 */
	public function handle_set_version_request(): void {
		// Verify nonce and capability
		if ( ! check_ajax_referer( 'zw_ttvgpt_nonce', 'nonce', false ) ) {
			wp_send_json_error( __( 'Beveiligingscontrole mislukt', 'zw-ttvgpt' ), 403 );
		}

		if ( ! current_user_can( TTVGPTConstants::EDIT_CAPABILITY ) ) {
			wp_send_json_error( __( 'Onvoldoende rechten', 'zw-ttvgpt' ), 403 );
		}

		$post_id    = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$version_id = isset( $_POST['version_id'] ) ? sanitize_text_field( $_POST['version_id'] ) : '';

		if ( ! $post_id || ! get_post( $post_id ) || empty( $version_id ) ) {
			wp_send_json_error( __( 'Ongeldige gegevens', 'zw-ttvgpt' ), 400 );
		}

		$success = $this->version_manager->set_active_version( $post_id, $version_id );
		if ( $success ) {
			wp_send_json_success( __( 'Versie geactiveerd', 'zw-ttvgpt' ) );
		} else {
			wp_send_json_error( __( 'Kon versie niet activeren', 'zw-ttvgpt' ), 500 );
		}
	}
}
