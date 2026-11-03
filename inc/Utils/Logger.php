<?php
/**
 * Logger utility.
 *
 * @package rtCamp\WPPrimitives\Utils
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Utils;

/**
 * PSR-3-style logger that writes to error_log() — but only when logging is enabled.
 *
 * Instance-based and context-scoped: construct one per consumer with that package's
 * prefix, so a theme and a plugin sharing the PHP process each own an independent
 * logger they configure (and can subclass) rather than routing every line through one
 * shared global instance:
 *
 *     $log = new Logger( 'my-plugin' );
 *     $log->info( 'Cache warmed', [ 'items' => 42 ] );
 *
 * Silent in production: {@see log()} writes nothing unless {@see is_enabled()} returns
 * true, which by default tracks `WP_DEBUG`. So it is safe to leave calls in shipped
 * code — they no-op on production where `WP_DEBUG` is off.
 *
 * PSR-3-*style*, not a full implementation: it ships the four levels rtCamp plugins
 * actually use ({@see debug()}, {@see info()}, {@see warning()}, {@see error()}) plus
 * the generic {@see log()}, and deliberately stops there — no `notice`/`critical`/etc.,
 * and no `psr/log` dependency (the framework carries zero runtime deps). The method
 * names match PSR-3 so a later swap to Monolog or another PSR-3 logger needs no caller
 * changes.
 *
 * Format: `[LEVEL] [prefix] message {"context":"json"}` (the JSON segment is omitted
 * when there is no context).
 *
 * Not `final`: downstream packages may extend it — e.g. override {@see is_enabled()} to
 * gate on an env var or feature flag instead of `WP_DEBUG`, or to force logging on for a
 * specific subsystem. Internal calls go through `$this` so such overrides take effect.
 *
 * @package rtCamp\WPPrimitives\Utils
 * @since   1.0.0
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
	 * @param string $prefix Label shown in every log line — typically the consumer's slug.
	 */
	public function __construct( string $prefix = 'rtcamp' ) {
		$this->prefix = $prefix;
	}

	/**
	 * Log a message at an arbitrary PSR-3 level.
	 *
	 * No-ops (writes nothing) when logging is disabled — see {@see is_enabled()} — so it
	 * is safe to leave calls in shipped code.
	 *
	 * @param string               $level   PSR-3 level: debug, info, warning, error.
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional structured context, appended as JSON.
	 *
	 * @return void
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

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Logger IS the centralised error_log() wrapper; callers route through it instead of calling error_log() directly. The sniff's intent (no debug output in production) is satisfied by the is_enabled()/WP_DEBUG gate above.
		error_log( sprintf( '[%s] [%s] %s%s', strtoupper( $level ), $this->prefix, $message, $context_part ) );
	}

	/**
	 * Log a debug-level message.
	 *
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional structured context.
	 *
	 * @return void
	 */
	public function debug( string $message, array $context = [] ): void {
		$this->log( 'debug', $message, $context );
	}

	/**
	 * Log an info-level message.
	 *
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional structured context.
	 *
	 * @return void
	 */
	public function info( string $message, array $context = [] ): void {
		$this->log( 'info', $message, $context );
	}

	/**
	 * Log a warning-level message.
	 *
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional structured context.
	 *
	 * @return void
	 */
	public function warning( string $message, array $context = [] ): void {
		$this->log( 'warning', $message, $context );
	}

	/**
	 * Log an error-level message.
	 *
	 * @param string               $message Message body.
	 * @param array<string, mixed> $context Optional structured context.
	 *
	 * @return void
	 */
	public function error( string $message, array $context = [] ): void {
		$this->log( 'error', $message, $context );
	}

	/**
	 * Whether {@see log()} should write.
	 *
	 * Defaults to `WP_DEBUG`, which is what keeps logs silent in production. Protected
	 * override seam (mirrors {@see Encryptor::key()}): a consumer can subclass to gate on
	 * an env var, a feature flag, or to force logging on for a specific subsystem —
	 * without touching the formatting in {@see log()}.
	 *
	 * @return bool True when logging is active.
	 */
	protected function is_enabled(): bool {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}
}
