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
	 * OpenAI Chat Completions API endpoint (GPT-4 and earlier)
	 *
	 * @var string
	 */
	private const CHAT_COMPLETIONS_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * OpenAI Responses API endpoint (GPT-5 and later)
	 *
	 * @var string
	 */
	private const RESPONSES_ENDPOINT = 'https://api.openai.com/v1/responses';

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
	 * Prepare content for API request by stripping HTML tags
	 *
	 * @param string $content Raw content to clean
	 * @return string Cleaned content ready for API
	 */
	public function prepare_content( string $content ): string {
		return wp_strip_all_tags( $content );
	}

	/**
	 * Build messages array for OpenAI API chat completion
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
	 * Determine which API endpoint to use based on the model
	 *
	 * @return string API endpoint URL
	 */
	private function get_api_endpoint(): string {
		if ( TTVGPTHelper::is_gpt5_model( $this->model ) ) {
			return self::RESPONSES_ENDPOINT;
		}

		return self::CHAT_COMPLETIONS_ENDPOINT;
	}

	/**
	 * Build request body for Chat Completions API (GPT-4 and earlier)
	 *
	 * @param string $content    Cleaned content to summarize
	 * @param int    $word_limit Maximum words for summary
	 * @return array Request body array
	 */
	private function build_chat_completions_request( string $content, int $word_limit ): array {
		return array(
			'model'       => $this->model,
			'messages'    => $this->build_messages( $content, $word_limit ),
			'max_tokens'  => self::MAX_TOKENS,
			'temperature' => self::TEMPERATURE,
		);
	}

	/**
	 * Build request body for Responses API (GPT-5 and later)
	 *
	 * @param string $content    Cleaned content to summarize
	 * @param int    $word_limit Maximum words for summary
	 * @return array Request body array
	 */
	private function build_responses_request( string $content, int $word_limit ): array {
		// The Responses API accepts messages array in the input parameter
		// Note: GPT-5 does not support temperature parameter
		return array(
			'model'             => $this->model,
			'input'             => $this->build_messages( $content, $word_limit ),
			'max_output_tokens' => self::MAX_TOKENS,
			'reasoning'         => array(
				'effort' => 'medium',
			),
			'store'             => false,
		);
	}

	/**
	 * Extract summary text from Chat Completions API response
	 *
	 * @param array $data Response data from API
	 * @return string|\WP_Error Summary text or WP_Error if invalid
	 */
	private function extract_chat_completions_summary( array $data ) {
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			$this->logger->error( 'Invalid Chat Completions API response structure' );
			return new \WP_Error(
				'invalid_response',
				__( 'Ongeldig antwoord van de API', 'zw-ttvgpt' )
			);
		}

		return trim( (string) $data['choices'][0]['message']['content'] );
	}

	/**
	 * Extract summary text from Responses API response
	 *
	 * @param array $data Response data from API
	 * @return string|\WP_Error Summary text or WP_Error if invalid
	 */
	private function extract_responses_summary( array $data ) {
		$this->logger->debug( 'Responses API data structure', array( 'keys' => array_keys( $data ) ) );

		// Check if output_text helper is available
		if ( isset( $data['output_text'] ) && is_string( $data['output_text'] ) ) {
			return trim( $data['output_text'] );
		}

		// Parse output array to find message items
		if ( isset( $data['output'] ) && is_array( $data['output'] ) ) {
			$this->logger->debug( 'Processing output array', array( 'count' => count( $data['output'] ) ) );

			foreach ( $data['output'] as $index => $item ) {
				if ( ! isset( $item['type'] ) ) {
					$this->logger->debug( "Output item $index has no type" );
					continue;
				}

				$this->logger->debug( "Output item $index type: {$item['type']}" );

				// Look for message items
				if ( 'message' === $item['type'] && isset( $item['content'] ) && is_array( $item['content'] ) ) {
					$this->logger->debug( 'Found message item', array( 'content_count' => count( $item['content'] ) ) );

					foreach ( $item['content'] as $content_index => $content_item ) {
						if ( isset( $content_item['type'] ) ) {
							$this->logger->debug( "Content item $content_index type: {$content_item['type']}" );
						}

						if ( isset( $content_item['type'] ) && 'output_text' === $content_item['type'] && isset( $content_item['text'] ) ) {
							$this->logger->debug( 'Found output_text in content' );
							return trim( (string) $content_item['text'] );
						}
					}
				}
			}
		} else {
			$this->logger->debug( 'No output array found or not an array' );
		}

		$this->logger->error( 'Invalid Responses API response structure', array( 'data' => wp_json_encode( $data ) ) );
		return new \WP_Error(
			'invalid_response',
			__( 'Ongeldig antwoord van de API', 'zw-ttvgpt' )
		);
	}

	/**
	 * Generate text summary using OpenAI API (Chat Completions or Responses)
	 *
	 * @param string $content    Content to summarize
	 * @param int    $word_limit Maximum words for summary
	 * @return string|\WP_Error Summary string on success, WP_Error on failure
	 */
	public function generate_summary( string $content, int $word_limit ) {
		$is_gpt5  = TTVGPTHelper::is_gpt5_model( $this->model );
		$api_type = $is_gpt5 ? 'Responses' : 'Chat Completions';
		$endpoint = $this->get_api_endpoint();
		$timeout  = $is_gpt5 ? TTVGPTConstants::API_TIMEOUT_GPT5 : TTVGPTConstants::API_TIMEOUT;

		$this->logger->debug(
			'Starting API request',
			array(
				'model'      => $this->model,
				'word_limit' => $word_limit,
				'api_type'   => $api_type,
				'endpoint'   => $endpoint,
			)
		);

		if ( empty( $this->api_key ) ) {
			$this->logger->error( 'API key is missing' );
			return new \WP_Error(
				'missing_api_key',
				__( 'API-sleutel niet geconfigureerd', 'zw-ttvgpt' )
			);
		}

		// Build request based on API type
		if ( $is_gpt5 ) {
			$request_data = $this->build_responses_request( $content, $word_limit );
		} else {
			$request_data = $this->build_chat_completions_request( $content, $word_limit );
		}

		$request_body = wp_json_encode( $request_data );

		if ( false === $request_body ) {
			$this->logger->error( 'Failed to encode JSON request body' );
			return new \WP_Error(
				'json_encode_failed',
				__( 'Fout bij het voorbereiden van API-verzoek', 'zw-ttvgpt' )
			);
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => $request_body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->logger->error( 'API request failed: ' . $response->get_error_message() );
			return new \WP_Error(
				'network_error',
				sprintf(
					/* translators: %s: Error message */
					__( 'Netwerkfout: %s', 'zw-ttvgpt' ),
					$response->get_error_message()
				),
				array( 'original_error' => $response )
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== (int) $status_code ) {
			$this->logger->error( 'API error: HTTP ' . $status_code );
			return new \WP_Error(
				'api_error',
				TTVGPTApiErrorHandler::get_error_message( (int) $status_code ),
				array( 'status_code' => $status_code )
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $data ) ) {
			$this->logger->error( 'Invalid JSON response from API' );
			return new \WP_Error(
				'invalid_response',
				__( 'Ongeldig antwoord van de API', 'zw-ttvgpt' )
			);
		}

		// Extract summary based on API type
		if ( $is_gpt5 ) {
			$summary = $this->extract_responses_summary( $data );
		} else {
			$summary = $this->extract_chat_completions_summary( $data );
		}

		if ( is_wp_error( $summary ) ) {
			return $summary;
		}

		$this->logger->debug( 'Summary generated', array( 'word_count' => str_word_count( $summary ) ) );

		return $summary;
	}
}
