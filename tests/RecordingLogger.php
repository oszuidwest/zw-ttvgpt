<?php
declare(strict_types=1);

namespace ZW_TTVGPT_Core\Tests;

use ZW_TTVGPT_Core\Logger;

/**
 * Test double that records error/debug calls instead of writing to error_log.
 *
 * Lets tests assert on logger interactions without depending on WP's logging
 * stack or the global error_log destination.
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
