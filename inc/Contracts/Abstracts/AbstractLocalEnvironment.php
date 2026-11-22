<?php
/**
 * Abstract Local Environment.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

/**
 * Class AbstractLocalEnvironment
 *
 * The seam behind a local WordPress development environment — `wp-env`, a
 * hosting provider's own dev-env CLI, or any other container-based stack.
 * Each concrete adapter brings its own detection and answers the handful of
 * questions tooling actually asks: where the debug log is, which services are
 * up, whether the filesystem takes writes.
 *
 * Unlike most abstracts here this one is deliberately not `Registrable`: it
 * registers no WordPress hook. It is constructed and queried on demand by
 * whatever consumes it.
 *
 * @since 1.0.0
 */
abstract class AbstractLocalEnvironment {

	/**
	 * Return the environment identifier, e.g. "wp-env".
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Whether this environment is the one currently running.
	 *
	 * The detection seam — typically a constant or environment variable
	 * unique to that stack.
	 *
	 * @return bool
	 */
	abstract public function is_active(): bool;

	/**
	 * Return the absolute path to the environment's debug log.
	 *
	 * Defaults to WordPress's own `wp-content/debug.log`. Override when the
	 * environment writes it elsewhere.
	 *
	 * @return string
	 */
	public function get_debug_log_path(): string {
		return WP_CONTENT_DIR . '/debug.log';
	}

	/**
	 * Return the auxiliary services the environment provides.
	 *
	 * Defaults to none. Override with what the stack runs alongside PHP and
	 * the database, e.g. `[ 'memcached', 'elasticsearch' ]`.
	 *
	 * @return string[]
	 */
	public function get_available_services(): array {
		return [];
	}

	/**
	 * Whether the environment's filesystem accepts writes at runtime.
	 *
	 * Defaults to true. Override with false for a stack that mirrors a
	 * read-only production filesystem.
	 *
	 * @return bool
	 */
	public function is_filesystem_writable(): bool {
		return true;
	}
}
