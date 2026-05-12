<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use ZW_TTVGPT_Core\Logger;

/**
 * Test logger that records error/debug calls for assertions.
 */
final class RecordingLogger extends Logger {
	/** @var list<array{message: string, context: array<string, mixed>}> */
	public array $errors = array();

	/** @var list<array{message: string, context: array<string, mixed>}> */
	public array $debugs = array();

	public function __construct() {
		parent::__construct( false );
	}

	public function error( string $message, array $context = array() ): void {
		$this->errors[] = array(
			'message' => $message,
			'context' => $context,
		);
	}

	public function debug( string $message, array $context = array() ): void {
		$this->debugs[] = array(
			'message' => $message,
			'context' => $context,
		);
	}
}
