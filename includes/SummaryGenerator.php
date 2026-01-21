<?php
/**
 * Summary Generator class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Summary Generator class.
 *
 * Core functionality for generating summaries.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class SummaryGenerator {
	use AjaxSecurity;

	/**
	 * Initializes the summary generator with dependencies and registers AJAX handler.
	 *
	 * @since 1.0.0
	 *
	 * @param ApiHandler $api_handler API handler for OpenAI communication.
	 * @param int        $word_limit  Maximum words allowed in summaries.
	 * @param Logger     $logger      Logger instance for debugging.
	 */
	public function __construct(
		private readonly ApiHandler $api_handler,
		private readonly int $word_limit,
		private readonly Logger $logger
	) {
		add_action( 'wp_ajax_zw_ttvgpt_generate', $this->handle_ajax_request( ... ) );
	}

	/**
	 * Processes AJAX request for generating summary with security and validation.
	 *
	 * @since 1.0.0
	 *
	 * @return never
	 */
	public function handle_ajax_request(): never {
		// Nonce is verified in validate_ajax_request() method.
		$this->validate_ajax_request( 'zw_ttvgpt_nonce', Constants::EDIT_CAPABILITY );

		if ( empty( SettingsManager::get_api_key() ) ) {
			AjaxResponse::error(
				'missing_config',
				__( 'Geen API-sleutel - ga naar Instellingen > Tekst TV GPT', 'zw-ttvgpt' ),
				503
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in validate_ajax_request()
		$content = isset( $_POST['content'] ) ? wp_unslash( $_POST['content'] ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in validate_ajax_request()
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( empty( $content ) || ! $post_id || ! get_post( $post_id ) ) {
			AjaxResponse::error(
				'invalid_input',
				__( 'Ongeldige gegevens - controleer artikel', 'zw-ttvgpt' ),
				400
			);
		}

		$clean_content = $this->api_handler->prepare_content( $content );
		$word_count    = str_word_count( $clean_content );

		if ( $word_count < Constants::MIN_WORD_COUNT ) {
			AjaxResponse::error(
				'invalid_input',
				sprintf(
					/* translators: 1: Minimum required word count, 2: Found word count */
					__( 'Te weinig woorden. Minimaal %1$d vereist, %2$d gevonden.', 'zw-ttvgpt' ),
					Constants::MIN_WORD_COUNT,
					$word_count
				),
				400
			);
		}

		$user_id = get_current_user_id();
		if ( RateLimiter::is_limited( $user_id ) ) {
			AjaxResponse::error(
				'rate_limited',
				__( 'Wacht even - max 10 per minuut', 'zw-ttvgpt' ),
				429
			);
		}

		$result = $this->generate_summary_with_retry( $clean_content, $this->word_limit );
		if ( is_wp_error( $result ) ) {
			AjaxResponse::error(
				$result->get_error_code(),
				$result->get_error_message(),
				502
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified in validate_ajax_request()
		$regions = isset( $_POST['regions'] ) ? array_map( 'sanitize_text_field', (array) $_POST['regions'] ) : array();
		$summary = $result;

		if ( ! empty( $regions ) ) {
			$summary = implode( ' / ', array_map( 'strtoupper', $regions ) ) . ' - ' . $summary;
		}

		$this->save_to_acf( $post_id, $summary );
		RateLimiter::increment( $user_id );
		$this->logger->debug( 'Summary generated for post ' . $post_id );

		AjaxResponse::success(
			array(
				'summary'    => $summary,
				'word_count' => str_word_count( $summary ),
			)
		);
	}

	/**
	 * Generates summary with automatic retry for invalid responses.
	 *
	 * Retries when response is too short (< 20% of limit) or too long (> limit).
	 * Returns the last attempt if all retries fail, allowing user to manually adjust.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content    Content to summarize.
	 * @param int    $word_limit Maximum words allowed.
	 * @return string|\WP_Error Summary text or error.
	 */
	private function generate_summary_with_retry( string $content, int $word_limit ): string|\WP_Error {
		$min_words   = (int) ( $word_limit * Constants::MIN_RESPONSE_RATIO );
		$last_result = '';

		for ( $attempt = 1; $attempt <= Constants::MAX_RETRY_ATTEMPTS; $attempt++ ) {
			$result = $this->api_handler->generate_summary( $content, $word_limit );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$last_result = $result;
			$word_count  = Helper::count_words( $result );

			// Check if response is valid (within min/max bounds).
			$is_valid = $word_count >= $min_words && $word_count <= $word_limit;

			if ( $is_valid ) {
				if ( $attempt > 1 ) {
					$this->logger->debug( sprintf( 'Summary accepted after %d retries', $attempt - 1 ) );
				}
				return $result;
			}
		}

		// All attempts exhausted - return last attempt for user to manually adjust.
		$this->logger->debug(
			sprintf( 'Summary retry limit reached (%d attempts), returning last attempt', Constants::MAX_RETRY_ATTEMPTS )
		);

		return $last_result;
	}

	/**
	 * Saves generated summary to ACF fields and marks as AI-generated.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $post_id Post ID to update.
	 * @param string $summary Generated summary text.
	 */
	private function save_to_acf( int $post_id, string $summary ): void {
		if ( ! function_exists( 'update_field' ) ) {
			$this->logger->error( 'ACF not available' );
			return;
		}

		update_field( Constants::ACF_SUMMARY_FIELD, $summary, $post_id );
		update_field( Constants::ACF_GPT_MARKER_FIELD, $summary, $post_id );

		$this->logger->debug(
			'Saved summary to ACF fields',
			array(
				'post_id' => $post_id,
			)
		);
	}
}
