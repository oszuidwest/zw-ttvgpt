<?php
/**
 * API Handler class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * API Handler class.
 *
 * Handles communication with the OpenAI API.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class ApiHandler {
	/**
	 * OpenAI Chat Completions API endpoint (for GPT-4.1 family).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const string CHAT_COMPLETIONS_ENDPOINT = 'https://api.openai.com/v1/chat/completions';

	/**
	 * OpenAI Responses API endpoint (GPT-5.1 only).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const string RESPONSES_ENDPOINT = 'https://api.openai.com/v1/responses';

	/**
	 * Maximum tokens for API response.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private const int MAX_TOKENS = 2048;

	/**
	 * Temperature for API responses (controls randomness).
	 *
	 * @since 1.0.0
	 * @var float
	 */
	private const float TEMPERATURE = 0.7;

	/**
	 * Initializes API handler with credentials and dependencies.
	 *
	 * @since 1.0.0
	 *
	 * @param string $api_key API key for OpenAI authentication (marked sensitive for stack trace protection).
	 * @param string $model   Model identifier to use for requests.
	 * @param Logger $logger  Logger instance for debugging and errors.
	 */
	public function __construct(
		#[\SensitiveParameter] private readonly string $api_key,
		private readonly string $model,
		private readonly Logger $logger
	) {}

	/**
	 * Generates system prompt for summarization.
	 *
	 * @since 1.0.0
	 *
	 * @param int $word_limit Maximum words for summary.
	 * @return string System prompt text.
	 */
	public function get_system_prompt( int $word_limit ): string {
		$prompt_template = SettingsManager::get_system_prompt();
		return sprintf( $prompt_template, $word_limit );
	}

	/**
	 * Prepares content for API request by extracting text from HTML.
	 *
	 * Removes script/style content (which wp_strip_all_tags doesn't handle),
	 * converts block elements to newlines, and normalizes whitespace.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content Raw content to clean.
	 * @return string Cleaned content ready for API.
	 */
	public function prepare_content( string $content ): string {
		// Remove script and style elements WITH their content.
		// wp_strip_all_tags() only removes tags, not the content inside.
		$content = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $content ) ?? $content;
		$content = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $content ) ?? $content;
		$content = preg_replace( '/<noscript\b[^>]*>.*?<\/noscript>/is', '', $content ) ?? $content;

		// Convert block elements to newlines for proper paragraph spacing.
		$content = preg_replace( '/<\/(p|div|h[1-6]|li|tr|blockquote)>/i', "\n", $content ) ?? $content;
		$content = preg_replace( '/<br\s*\/?>/i', "\n", $content ) ?? $content;

		// Strip remaining tags.
		$text = wp_strip_all_tags( $content );

		// Decode HTML entities.
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Normalize whitespace.
		$text = preg_replace( '/[ \t]+/', ' ', $text ) ?? $text;
		$text = preg_replace( '/\n{3,}/', "\n\n", $text ) ?? $text;

		return trim( $text );
	}

	/**
	 * Builds messages array for OpenAI API chat completion.
	 *
	 * @since 1.0.0
	 *
	 * @param string $content    Cleaned content to summarize.
	 * @param int    $word_limit Maximum words for summary.
	 * @return array Messages array for OpenAI API.
	 *
	 * @phpstan-return array<int, ChatMessage>
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
	 * Determines which API endpoint to use based on the model.
	 *
	 * @since 1.0.0
	 *
	 * @return string API endpoint URL.
	 */
	private function get_api_endpoint(): string {
		if ( Helper::is_gpt5_model( $this->model ) ) {
			return self::RESPONSES_ENDPOINT;
		}

		return self::CHAT_COMPLETIONS_ENDPOINT;
	}

	/**
	 * Builds request body for Chat Completions API (GPT-4.1 family).
	 *
	 * @since 1.0.0
	 *
	 * @param string $content    Cleaned content to summarize.
	 * @param int    $word_limit Maximum words for summary.
	 * @return array Request body array.
	 *
	 * @phpstan-return array<string, mixed>
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
	 * Builds request body for Responses API (GPT-5.1 only).
	 *
	 * @since 1.0.0
	 *
	 * @param string $content    Cleaned content to summarize.
	 * @param int    $word_limit Maximum words for summary.
	 * @return array Request body array.
	 *
	 * @phpstan-return array<string, mixed>
	 */
	private function build_responses_request( string $content, int $word_limit ): array {
		// The Responses API accepts messages array in the input parameter.
		// Note: GPT-5.1 does not support temperature parameter
		// Using 'low' reasoning effort for quality summaries while maintaining speed.
		// Using 'medium' verbosity for balanced response length.
		return array(
			'model'             => $this->model,
			'input'             => $this->build_messages( $content, $word_limit ),
			'max_output_tokens' => self::MAX_TOKENS,
			'reasoning'         => array(
				'effort' => 'low',
			),
			'text'              => array(
				'verbosity' => 'medium',
			),
			'store'             => false,
		);
	}

	/**
	 * Extracts summary text from Chat Completions API response.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Response data from API.
	 * @return string|\WP_Error Summary text or WP_Error if invalid.
	 *
	 * @phpstan-param array<string, mixed> $data
	 */
	private function extract_chat_completions_summary( array $data ): string|\WP_Error {
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
	 * Extracts summary text from Responses API response.
	 *
	 * @since 1.0.0
	 *
	 * @param array $data Response data from API.
	 * @return string|\WP_Error Summary text or WP_Error if invalid.
	 *
	 * @phpstan-param array<string, mixed> $data
	 */
	private function extract_responses_summary( array $data ): string|\WP_Error {
		// Check if output_text helper is available.
		if ( isset( $data['output_text'] ) && is_string( $data['output_text'] ) ) {
			return trim( $data['output_text'] );
		}

		// Parse output array to find message items.
		if ( isset( $data['output'] ) && is_array( $data['output'] ) ) {
			foreach ( $data['output'] as $item ) {
				if ( ! isset( $item['type'] ) ) {
					continue;
				}

				// Look for message items.
				if ( 'message' === $item['type'] && isset( $item['content'] ) && is_array( $item['content'] ) ) {
					foreach ( $item['content'] as $content_item ) {
						if ( isset( $content_item['type'] ) && 'output_text' === $content_item['type'] && isset( $content_item['text'] ) ) {
							return trim( (string) $content_item['text'] );
						}
					}
				}
			}
		}

		$this->logger->error( 'Invalid Responses API response structure' );
		return new \WP_Error(
			'invalid_response',
			__( 'Ongeldig antwoord van de API', 'zw-ttvgpt' )
		);
	}

	/**
	 * Generates text summary using OpenAI API (Chat Completions or Responses).
	 *
	 * @since 1.0.0
	 *
	 * @param string $content    Content to summarize.
	 * @param int    $word_limit Maximum words for summary.
	 * @return string|\WP_Error Summary string on success, WP_Error on failure.
	 */
	public function generate_summary( string $content, int $word_limit ): string|\WP_Error {
		$is_gpt5  = Helper::is_gpt5_model( $this->model );
		$api_type = $is_gpt5 ? 'Responses' : 'Chat Completions';
		$endpoint = $this->get_api_endpoint();
		$timeout  = Constants::API_TIMEOUT;

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

		// Build request based on API type.
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
				ApiErrorHandler::get_error_message( (int) $status_code ),
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

		// Extract summary based on API type.
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
