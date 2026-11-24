<?php
/**
 * Logger utility.
 *
 * PSR-3-style logger that writes to error_log() when {@see Logger::is_enabled()} returns true.
 *
 *     $log = new Logger( 'my-plugin' );
 *     $log->info( 'Cache warmed', [ 'items' => 42 ] );
 *
 * The method names match PSR-3, but only the debug, info, warning and error
 * levels exist, and the class does not implement `Psr\Log\LoggerInterface`.
 *
 * Line format: `[LEVEL] [prefix] message {"context":"json"}`.
 * The JSON is omitted when the context is empty.
 *
 * @package rtCamp\WPPrimitives\Utils
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Utils;

/**
 * Class Logger
 */
class Logger {
	/**
	 * Prefix shown in every line, identifying the consumer that emitted it.
	 *
	 * @var string
	 */
	private string $prefix;

	/**
	 * Construct a logger for a consumer.
	 *
	 * @param string $prefix Label shown in every log line, usually the theme or plugin slug.
	 */
	public function __construct( string $prefix = 'rtcamp' ) {
		$this->prefix = $prefix;
	}

	/**
	 * Log a message at an arbitrary PSR-3 level.
	 *
	 * Writes nothing when {@see Logger::is_enabled()} returns false.
	 *
	 * @param string               $level   PSR-3 level: debug, info, warning, error.
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional structured context, appended as JSON.
	 */
	public function log( string $level, string $message, array $context = [] ): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$context_part = '';
		if ( ! empty( $context ) ) {
			$encoded_context = wp_json_encode( $context );
			$context_part    = is_string( $encoded_context ) ? ' ' . $encoded_context : '';
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- It's a logger utility.
		error_log( sprintf( '[%s] [%s] %s%s', strtoupper( $level ), $this->prefix, $message, $context_part ) );
	}

	/**
	 * Log a debug-level message.
	 *
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional structured context.
	 */
	public function debug( string $message, array $context = [] ): void {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * Log an info-level message.
	 *
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional structured context.
	 */
	public function info( string $message, array $context = [] ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Log a warning-level message.
	 *
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional structured context.
	 */
	public function warning( string $message, array $context = [] ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Log an error-level message.
	 *
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional structured context.
	 */
	public function error( string $message, array $context = [] ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Whether {@see Logger::log()} should write.
	 *
	 * Returns the value of `WP_DEBUG`. Subclasses can override it, e.g. to check an env variable or a feature flag.
	 *
	 * @return bool True when logging is active.
	 */
	protected function is_enabled(): bool {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}
}
