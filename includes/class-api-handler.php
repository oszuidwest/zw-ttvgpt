<?php
/**
 * API Handler class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * API Handler class
 *
 * Handles communication with OpenAI API
 */
class TTVGPTApiHandler {
	/**
	 * OpenAI API endpoint
	 *
	 * @var string
	 */
	private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Maximum tokens for API response
	 *
	 * @var int
	 */
	private const MAX_TOKENS = 2048;

	/**
	 * Temperature for API responses (controls randomness)
	 *
	 * @var float
	 */
	private const TEMPERATURE = 0.7;

	/**
	 * API key
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Model to use
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * Logger instance
	 *
	 * @var TTVGPTLogger
	 */
	private TTVGPTLogger $logger;

	/**
	 * Initialize API handler with credentials and dependencies
	 *
	 * @param string       $api_key API key for OpenAI authentication
	 * @param string       $model   Model identifier to use for requests
	 * @param TTVGPTLogger $logger  Logger instance for debugging and errors
	 */
	public function __construct( string $api_key, string $model, TTVGPTLogger $logger ) {
		$this->api_key = $api_key;
		$this->model   = $model;
		$this->logger  = $logger;
	}

	/**
	 * Generate system prompt for summarization
	 *
	 * @param int $word_limit Maximum words for summary
	 * @return string System prompt text
	 */
	public function get_system_prompt( int $word_limit ): string {
		return sprintf( 'Please summarize the following news article in a clear and concise manner that is easy to understand for a general audience. Use short sentences. Do it in Dutch. Ignore everything in the article that\'s not a Dutch word. Parse HTML. Never output English words. Use maximal %d words.', $word_limit );
	}

	/**
	 * Prepare content for API request (same cleaning as used in production)
	 *
	 * @param string $content Raw content to clean
	 * @return string Cleaned content ready for API
	 */
	public function prepare_content( string $content ): string {
		return wp_strip_all_tags( $content );
	}

	/**
	 * Build messages array for OpenAI API (exact format used in production)
	 *
	 * @param string $content    Cleaned content to summarize
	 * @param int    $word_limit Maximum words for summary
	 * @return array Messages array for OpenAI API
	 */
	public function build_messages( string $content, int $word_limit ): array {
		return array(
			array(
				'role'    => 'system',
				'content' => $this->get_system_prompt( $word_limit ),
			),
			array(
				'role'    => 'user',
				'content' => $content,
			),
		);
	}

	/**
	 * Generate text summary using OpenAI Chat Completions API
	 *
	 * @param string $content    Content to summarize
	 * @param int    $word_limit Maximum words for summary
	 * @return array{success: bool, data?: string, error?: string} API response with success status
	 */
	public function generate_summary( string $content, int $word_limit ): array {
		$this->logger->debug(
			'Starting API request',
			array(
				'model'      => $this->model,
				'word_limit' => $word_limit,
			)
		);

		if ( empty( $this->api_key ) ) {
			$this->logger->error( 'API key is missing' );
			return array(
				'success' => false,
				'error'   => __( 'API key niet geconfigureerd', 'zw-ttvgpt' ),
			);
		}

		$request_body = wp_json_encode(
			array(
				'model'       => $this->model,
				'messages'    => $this->build_messages( $content, $word_limit ),
				'max_tokens'  => self::MAX_TOKENS,
				'temperature' => self::TEMPERATURE,
			)
		);

		if ( false === $request_body ) {
			$this->logger->error( 'Failed to encode JSON request body' );
			return array(
				'success' => false,
				'error'   => __( 'Fout bij het voorbereiden van API aanvraag', 'zw-ttvgpt' ),
			);
		}

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => TTVGPTConstants::API_TIMEOUT,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => $request_body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'API request failed: ' . $response->get_error_message() );
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: Error message */
					__( 'Netwerkfout: %s', 'zw-ttvgpt' ),
					$response->get_error_message()
				),
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $status_code ) {
			$this->logger->error( 'API error: HTTP ' . $status_code );
			return array(
				'success' => false,
				'error'   => TTVGPTApiErrorHandler::get_error_message( (int) $status_code ),
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) || ! isset( $data['choices'][0]['message']['content'] ) ) {
			$this->logger->error( 'Invalid API response' );
			return array(
				'success' => false,
				'error'   => __( 'Ongeldig antwoord van API', 'zw-ttvgpt' ),
			);
		}

		$summary = trim( (string) $data['choices'][0]['message']['content'] );
		$this->logger->debug( 'Summary generated', array( 'word_count' => str_word_count( $summary ) ) );

		return array(
			'success' => true,
			'data'    => $summary,
		);
	}
}
