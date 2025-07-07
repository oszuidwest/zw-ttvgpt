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
class TTVGPTLogger {
	/**
	 * Debug mode flag
	 *
	 * @var bool
	 */
	private bool $debug_mode;

	/**
	 * Log prefix
	 *
	 * @var string
	 */
	private string $prefix = 'ZW_TTVGPT';

	/**
	 * Constructor
	 *
	 * @param bool $debug_mode Whether debug mode is enabled.
	 */
	public function __construct( bool $debug_mode = false ) {
		$this->debug_mode = $debug_mode;
	}

	/**
	 * Log debug message when debug mode is enabled
	 *
	 * @param string $message Debug message to log
	 * @param array  $context Additional context data
	 * @return void
	 */
	public function debug( string $message, array $context = array() ): void {
		if ( $this->debug_mode ) {
			$this->write_log( 'DEBUG', $message, $context );
		}
	}

	/**
	 * Log error message
	 *
	 * @param string $message Error message to log
	 * @param array  $context Additional context data
	 * @return void
	 */
	public function error( string $message, array $context = array() ): void {
		$this->write_log( 'ERROR', $message, $this->debug_mode ? $context : array() );
	}

	/**
	 * Write formatted log entry to PHP error log
	 *
	 * @param string $level   Log level (DEBUG or ERROR)
	 * @param string $message Message to log
	 * @param array  $context Additional context data
	 * @return void
	 */
	private function write_log( string $level, string $message, array $context = array() ): void {
		$timestamp   = current_time( 'Y-m-d H:i:s' );
		$log_message = sprintf( '[%s] %s.%s: %s', $timestamp, $this->prefix, $level, $message );

		if ( ! empty( $context ) ) {
			$log_message .= ' | Context: ' . wp_json_encode( $context );
		}

        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $log_message );
	}
}
