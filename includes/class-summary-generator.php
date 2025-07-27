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
	 * Initialize summary generator with dependencies and register AJAX handler
	 *
	 * @param TTVGPTApiHandler $api_handler API handler for OpenAI communication
	 * @param int              $word_limit  Maximum words allowed in summaries
	 * @param TTVGPTLogger     $logger      Logger instance for debugging
	 */
	public function __construct( TTVGPTApiHandler $api_handler, int $word_limit, TTVGPTLogger $logger ) {
		$this->api_handler = $api_handler;
		$this->word_limit  = $word_limit;
		$this->logger      = $logger;

		add_action( 'wp_ajax_zw_ttvgpt_generate', array( $this, 'handle_ajax_request' ) );
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

		$this->save_to_acf( $post_id, $summary );
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
	 * Save generated summary to ACF fields and mark as AI-generated
	 *
	 * @param int    $post_id Post ID to update
	 * @param string $summary Generated summary text
	 * @return void
	 */
	private function save_to_acf( int $post_id, string $summary ): void {
		if ( ! function_exists( 'update_field' ) ) {
			$this->logger->error( 'ACF not available' );
			return;
		}

		update_field( TTVGPTConstants::ACF_SUMMARY_FIELD, $summary, $post_id );
		update_field( TTVGPTConstants::ACF_GPT_MARKER_FIELD, $summary, $post_id );

		$this->logger->debug(
			'Saved summary to ACF fields',
			array(
				'post_id' => $post_id,
			)
		);
	}
}
