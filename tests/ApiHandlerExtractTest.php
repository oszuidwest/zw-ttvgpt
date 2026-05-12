<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use WP_Error;
use ZW_TTVGPT_Core\ApiHandler;

#[CoversClass(ApiHandler::class)]
final class ApiHandlerExtractTest extends TestCase {

	private RecordingLogger $logger;
	private ApiHandler $handler;

	protected function setUp(): void {
		$this->logger  = new RecordingLogger();
		$this->handler = new ApiHandler( 'sk-test', 'gpt-5.5', $this->logger );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function invoke_chat( array $data ): string|WP_Error {
		$method = new ReflectionMethod( ApiHandler::class, 'extract_chat_completions_summary' );
		/** @var string|WP_Error $result */
		$result = $method->invoke( $this->handler, $data );
		return $result;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	private function invoke_responses( array $data ): string|WP_Error {
		$method = new ReflectionMethod( ApiHandler::class, 'extract_responses_summary' );
		/** @var string|WP_Error $result */
		$result = $method->invoke( $this->handler, $data );
		return $result;
	}

	public function test_chat_valid_response_returns_trimmed_summary(): void {
		$result = $this->invoke_chat(
			array(
				'choices' => array(
					array( 'message' => array( 'content' => "  hello world  \n" ) ),
				),
			)
		);

		self::assertSame( 'hello world', $result );
		self::assertSame( array(), $this->logger->errors );
	}

	public function test_chat_empty_content_returns_empty_response_error_with_safe_context(): void {
		$result = $this->invoke_chat(
			array(
				'id'      => 'chatcmpl-x',
				'model'   => 'gpt-4.1-mini',
				'usage'   => array( 'total_tokens' => 1 ),
				'choices' => array(
					array(
						'message'       => array( 'content' => '   ' ),
						'finish_reason' => 'length',
					),
				),
			)
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'empty_response', $result->get_error_code() );

		self::assertCount( 1, $this->logger->errors );
		$context = $this->logger->errors[0]['context'];
		self::assertSame( 'Chat Completions', $context['api_type'] );
		self::assertSame( 'gpt-4.1-mini', $context['model'] );
		self::assertSame( 'chatcmpl-x', $context['id'] );
		self::assertSame( 'length', $context['finish_reason'] );
		self::assertSame( array( 'total_tokens' => 1 ), $context['usage'] );
		self::assertArrayNotHasKey( 'content', $context );
		self::assertArrayNotHasKey( 'choices', $context );
	}

	public function test_chat_missing_structure_returns_invalid_response_error(): void {
		$result = $this->invoke_chat( array( 'id' => 'chatcmpl-y' ) );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'invalid_response', $result->get_error_code() );
		self::assertCount( 1, $this->logger->errors );

		$context = $this->logger->errors[0]['context'];
		self::assertSame( 'chatcmpl-y', $context['id'] );
		// Fall back to the configured model when the payload omits it.
		self::assertSame( 'gpt-5.5', $context['model'] );
	}

	public function test_responses_output_text_helper_returns_trimmed_summary(): void {
		$result = $this->invoke_responses( array( 'output_text' => '  summary  ' ) );

		self::assertSame( 'summary', $result );
		self::assertSame( array(), $this->logger->errors );
	}

	public function test_responses_output_array_walk_finds_text_and_stops(): void {
		$result = $this->invoke_responses(
			array(
				'output' => array(
					array( 'type' => 'reasoning' ),
					array(
						'type'    => 'message',
						'content' => array(
							array(
								'type' => 'output_text',
								'text' => 'walked text',
							),
							array(
								'type' => 'output_text',
								'text' => 'should be ignored',
							),
						),
					),
				),
			)
		);

		self::assertSame( 'walked text', $result );
	}

	public function test_responses_empty_text_returns_empty_response_error(): void {
		$result = $this->invoke_responses(
			array(
				'id'     => 'resp_x',
				'model'  => 'gpt-5.5',
				'status' => 'completed',
				'output' => array(
					array(
						'type'    => 'message',
						'content' => array(
							array(
								'type' => 'output_text',
								'text' => '   ',
							),
						),
					),
				),
			)
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'empty_response', $result->get_error_code() );

		self::assertCount( 1, $this->logger->errors );
		$context = $this->logger->errors[0]['context'];
		self::assertSame( 'Responses', $context['api_type'] );
		self::assertSame( 'gpt-5.5', $context['model'] );
		self::assertSame( 'completed', $context['status'] );
	}

	public function test_responses_missing_structure_returns_invalid_response_error(): void {
		$result = $this->invoke_responses( array() );

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'invalid_response', $result->get_error_code() );

		self::assertCount( 1, $this->logger->errors );
		$context = $this->logger->errors[0]['context'];
		self::assertSame( 'Responses', $context['api_type'] );
		// Fall back to the configured model when the payload omits it.
		self::assertSame( 'gpt-5.5', $context['model'] );
	}

	public function test_responses_output_without_message_items_returns_invalid_response_error(): void {
		$result = $this->invoke_responses(
			array(
				'output' => array(
					array( 'type' => 'reasoning' ),
				),
			)
		);

		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'invalid_response', $result->get_error_code() );
	}
}
