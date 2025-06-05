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

	// API timeout is now in TTVGPTConstants

	/**
	 * Maximum tokens for API response
	 *
	 * @var int
	 */
	private const MAX_TOKENS = 2048;

	/**
	 * Temperature for API responses
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
	 * Constructor
	 *
	 * @param string       $api_key API key for OpenAI
	 * @param string       $model   Model to use
	 * @param TTVGPTLogger $logger  Logger instance
	 */
	public function __construct( string $api_key, string $model, TTVGPTLogger $logger ) {
		$this->api_key = $api_key;
		$this->model   = $model;
		$this->logger  = $logger;
	}

	/**
	 * Generate a summary using OpenAI API
	 *
	 * @param string $content    Content to summarize
	 * @param int    $word_limit Maximum words for summary
	 * @return array{success: bool, data?: string, error?: string}
	 */
	public function generate_summary( string $content, int $word_limit ): array {
		// Log alleen in debug mode
		$this->logger->debug(
			'Starting API request',
			array(
				'model'          => $this->model,
				'word_limit'     => $word_limit,
				'content_length' => strlen( $content ),
			)
		);

		// Validate API key
		if ( empty( $this->api_key ) ) {
			$this->logger->error( 'API key is missing' );
			return TTVGPTHelper::error_response( __( 'API key niet geconfigureerd', 'zw-ttvgpt' ) );
		}

		// Prepare system prompt
		$system_prompt = sprintf(
			'Please summarize the following news article in a clear and concise manner that is easy to understand for a general audience. ' .
			'Use short sentences. Do it in Dutch. ' .
			'Ignore everything in the article that\'s not a Dutch word. ' .
			'Parse HTML. Never output English words. ' .
			'Use maximal %d words.',
			$word_limit
		);

		// Prepare API request
		$request_body = array(
			'model'       => $this->model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system_prompt,
				),
				array(
					'role'    => 'user',
					'content' => $content,
				),
			),
			'max_tokens'  => self::MAX_TOKENS,
			'temperature' => self::TEMPERATURE,
		);

		// Make API request
		$response = wp_remote_post(
			self::API_ENDPOINT,
			array(
				'timeout' => TTVGPTConstants::API_TIMEOUT,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $request_body ),
			)
		);

		// Handle WordPress HTTP errors
		if ( is_wp_error( $response ) ) {
			// Minimale logging zonder debug mode
			$this->logger->error( 'OpenAI API request failed: ' . $response->get_error_message() );
			return TTVGPTHelper::error_response(
				sprintf(
					/* translators: %s: Error message */
					__( 'Netwerkfout: %s', 'zw-ttvgpt' ),
					$response->get_error_message()
				)
			);
		}

		// Check HTTP status code
		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			// Log alleen status code zonder response body in non-debug mode
			$this->logger->error(
				'OpenAI API error: HTTP ' . $status_code,
				array(
					'status_code' => $status_code,
					'body'        => wp_remote_retrieve_body( $response ),
				)
			);

			$error_message = $this->get_api_error_message( $status_code );
			return TTVGPTHelper::error_response( $error_message );
		}

		// Parse response
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$this->logger->error(
				'Failed to parse API response',
				array(
					'json_error' => json_last_error_msg(),
				)
			);
			return TTVGPTHelper::error_response( __( 'Ongeldig antwoord van API', 'zw-ttvgpt' ) );
		}

		// Extract summary from response
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			$this->logger->error(
				'Unexpected API response structure',
				array(
					'response' => $data,
				)
			);
			return TTVGPTHelper::error_response( __( 'Onverwacht API antwoord formaat', 'zw-ttvgpt' ) );
		}

		$summary = trim( $data['choices'][0]['message']['content'] );

		// Minimale logging bij succes
		$this->logger->debug(
			'Summary generated successfully',
			array(
				'word_count' => str_word_count( $summary ),
			)
		);

		return TTVGPTHelper::success_response( $summary );
	}

	/**
	 * Get user-friendly error message for API status codes
	 *
	 * @param int $status_code HTTP status code
	 * @return string Error message
	 */
	private function get_api_error_message( int $status_code ): string {
		$messages = array(
			400 => __( 'Ongeldige aanvraag', 'zw-ttvgpt' ),
			401 => __( 'Ongeldige API key', 'zw-ttvgpt' ),
			403 => __( 'Toegang geweigerd', 'zw-ttvgpt' ),
			404 => __( 'Model niet gevonden', 'zw-ttvgpt' ),
			429 => __( 'Te veel aanvragen, probeer later opnieuw', 'zw-ttvgpt' ),
			500 => __( 'OpenAI server fout', 'zw-ttvgpt' ),
			503 => __( 'OpenAI service tijdelijk niet beschikbaar', 'zw-ttvgpt' ),
		);

		return $messages[ $status_code ] ?? sprintf(
			/* translators: %d: HTTP status code */
			__( 'API fout: HTTP %d', 'zw-ttvgpt' ),
			$status_code
		);
	}
}
