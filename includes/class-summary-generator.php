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
	 * Initialize summary generator with dependencies and register AJAX handler
	 *
	 * @param TTVGPTApiHandler $api_handler API handler for OpenAI communication.
	 * @param int              $word_limit  Maximum words allowed in summaries.
	 * @param TTVGPTLogger     $logger      Logger instance for debugging.
	 */
	public function __construct(
		private readonly TTVGPTApiHandler $api_handler,
		private readonly int $word_limit,
		private readonly TTVGPTLogger $logger
	) {
		add_action( 'wp_ajax_zw_ttvgpt_generate', $this->handle_ajax_request( ... ) );
	}

	/**
	 * Process AJAX request for generating summary with security and validation
	 *
	 * @return never
	 */
	public function handle_ajax_request(): never {
		// Nonce is verified in validate_ajax_request() method
		$this->validate_ajax_request( 'zw_ttvgpt_nonce', TTVGPTConstants::EDIT_CAPABILITY );

		if ( empty( TTVGPTSettingsManager::get_api_key() ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Geen API-sleutel - ga naar Instellingen > Tekst TV GPT', 'zw-ttvgpt' ) ),
				400
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in validate_ajax_request()
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in validate_ajax_request()
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( empty( $content ) || ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( __( 'Ongeldige gegevens - controleer artikel', 'zw-ttvgpt' ), 400 );
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
			wp_send_json_error( __( 'Wacht even - max 10 per minuut', 'zw-ttvgpt' ), 429 );
		}

		$result = $this->generate_summary_with_retry( $clean_content, $this->word_limit );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), 500 );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in validate_ajax_request()
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
	 * Generate summary with automatic retry for invalid responses.
	 *
	 * Retries when response is too short (< 20% of limit) or too long (> limit).
	 * Returns the last attempt if all retries fail, allowing user to manually adjust.
	 *
	 * @param string $content    Content to summarize.
	 * @param int    $word_limit Maximum words allowed.
	 * @return string|\WP_Error Summary text or error.
	 */
	private function generate_summary_with_retry( string $content, int $word_limit ): string|\WP_Error {
		$min_words   = (int) ( $word_limit * TTVGPTConstants::MIN_RESPONSE_RATIO );
		$last_result = '';

		for ( $attempt = 1; $attempt <= TTVGPTConstants::MAX_RETRY_ATTEMPTS; $attempt++ ) {
			$result = $this->api_handler->generate_summary( $content, $word_limit );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$last_result = $result;
			$word_count  = TTVGPTHelper::count_words( $result );

			// Check if response is valid (within min/max bounds)
			$is_valid = $word_count >= $min_words && $word_count <= $word_limit;

			if ( $is_valid ) {
				if ( $attempt > 1 ) {
					$this->logger->debug( sprintf( 'Summary accepted after %d retries', $attempt - 1 ) );
				}
				return $result;
			}
		}

		// All attempts exhausted - return last attempt for user to manually adjust
		$this->logger->debug(
			sprintf( 'Summary retry limit reached (%d attempts), returning last attempt', TTVGPTConstants::MAX_RETRY_ATTEMPTS )
		);

		return $last_result;
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
