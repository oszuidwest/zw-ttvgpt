<?php
/**
 * Logger class for ZW TTVGPT
 *
 * @package ZW_TTVGPT
 */

namespace ZW_TTVGPT_Core;

/**
 * Logger class
 *
 * Simplified logging system with only debug and error levels.
 * Debug messages are only logged when debug mode is enabled.
 * Error messages are always logged but with limited context in production.
 */
class Logger {
	/**
	 * Log prefix
	 */
	private const string PREFIX = 'ZW_TTVGPT';

	/**
	 * Constructor
	 *
	 * @param bool $debug_mode Whether debug mode is enabled.
	 */
	public function __construct( private readonly bool $debug_mode = false ) {}

	/**
	 * Log debug message when debug mode is enabled
	 *
	 * @param string $message Debug message to log.
	 * @param array  $context Additional context data.
	 * @return void
	 *
	 * @phpstan-param LogContext $context
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( $this->debug_mode ) {
			$this->write_log( 'DEBUG', $message, $context );
		}
	}

	/**
	 * Log error message
	 *
	 * @param string $message Error message to log.
	 * @param array  $context Additional context data.
	 * @return void
	 *
	 * @phpstan-param LogContext $context
	 */
	public function error( string $message, array $context = array() ): void {
		$this->write_log( 'ERROR', $message, $this->debug_mode ? $context : array() );
	}


	/**
	 * Write formatted log entry to PHP error log
	 *
	 * @param string $level   Log level (DEBUG or ERROR).
	 * @param string $message Message to log.
	 * @param array  $context Additional context data.
	 * @return void
	 *
	 * @phpstan-param LogContext $context
	 */
	private function write_log( string $level, string $message, array $context = array() ): void {
		$timestamp   = current_time( 'Y-m-d H:i:s' );
		$log_message = sprintf( '[%s] %s.%s: %s', $timestamp, self::PREFIX, $level, $message );

		if ( ! empty( $context ) ) {
			$log_message .= ' | Context: ' . wp_json_encode( $context );
		}

		// Log to PHP error log for consistent error tracking.
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Design choice for error tracking
		error_log( $log_message );
	}
}
