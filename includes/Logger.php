<?php
/**
 * Logger class for ZW TTVGPT.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */

namespace ZW_TTVGPT_Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logger class.
 *
 * Simplified logging system with only debug and error levels.
 * Debug messages are only logged when debug mode is enabled.
 * Error messages are always logged but with limited context in production.
 *
 * @package ZW_TTVGPT
 * @since   1.0.0
 */
class Logger {
	/**
	 * Log prefix for identifying plugin messages.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const string PREFIX = 'ZW_TTVGPT';

	/**
	 * Initializes the logger with debug mode configuration.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $debug_mode Whether debug mode is enabled.
	 */
	public function __construct( private readonly bool $debug_mode = false ) {}

	/**
	 * Logs a debug message when debug mode is enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Debug message to log.
	 * @param array  $context Additional context data.
	 *
	 * @phpstan-param LogContext $context
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( $this->debug_mode ) {
			$this->write_log( 'DEBUG', $message, $context );
		}
	}

	/**
	 * Logs an error message.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Error message to log.
	 * @param array  $context Additional context data.
	 *
	 * @phpstan-param LogContext $context
	 */
	public function error( string $message, array $context = array() ): void {
		$this->write_log( 'ERROR', $message, $this->debug_mode ? $context : array() );
	}


	/**
	 * Writes a formatted log entry to PHP error log.
	 *
	 * @since 1.0.0
	 *
	 * @param string $level   Log level (DEBUG or ERROR).
	 * @param string $message Message to log.
	 * @param array  $context Additional context data.
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
