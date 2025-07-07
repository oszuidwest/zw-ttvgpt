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
			return TTVGPTHelper::error_response( __( 'API key niet geconfigureerd', 'zw-ttvgpt' ) );
		}

		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => TTVGPTConstants::API_TIMEOUT,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $this->model,
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => sprintf( 'Summarize in Dutch, max %d words, short sentences, ignore non-Dutch content.', $word_limit ),
							),
							array(
								'role'    => 'user',
								'content' => $content,
							),
						),
						'max_tokens'  => self::MAX_TOKENS,
						'temperature' => self::TEMPERATURE,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'API request failed: ' . $response->get_error_message() );
			return TTVGPTHelper::error_response(
				sprintf(
					/* translators: %s: Error message */
					__( 'Netwerkfout: %s', 'zw-ttvgpt' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $status_code ) {
			$this->logger->error( 'API error: HTTP ' . $status_code );
			return TTVGPTHelper::error_response( TTVGPTApiErrorHandler::get_error_message( (int) $status_code ) );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! isset( $data['choices'][0]['message']['content'] ) ) {
			$this->logger->error( 'Invalid API response' );
			return TTVGPTHelper::error_response( __( 'Ongeldig antwoord van API', 'zw-ttvgpt' ) );
		}

		$summary = trim( $data['choices'][0]['message']['content'] );
		$this->logger->debug( 'Summary generated', array( 'word_count' => str_word_count( $summary ) ) );

		return array(
			'success' => true,
			'data'    => $summary,
		);
	}
}
