<?php
/**
 * Abstract Platform Profile.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

/**
 * Class AbstractPlatformProfile
 *
 * The ruleset a readiness lens applies: the named buckets of constraints a
 * hosting platform imposes. A subclass declares only the buckets that platform
 * actually constrains.
 *
 * Every bucket defaults to permissive, so a profile that declares nothing
 * imposes nothing, and a new bucket can be added here later without breaking
 * any existing subclass. Most buckets spell that as an empty array;
 * `get_writable_paths()` uses null, reserving `[]` for "nothing is writable".
 *
 * Unlike most abstracts here this one is deliberately not `Registrable`: it
 * registers no WordPress hook. It is constructed and queried on demand by
 * whatever consumes it.
 *
 * @since 1.0.0
 */
abstract class AbstractPlatformProfile {

	/**
	 * Return the profile slug, e.g. "vip".
	 *
	 * @return string
	 */
	abstract public function get_slug(): string;

	/**
	 * Return the human-readable platform name, e.g. "WordPress VIP".
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Return the PHP functions the platform forbids.
	 *
	 * Defaults to none. Override to list them, e.g. `[ 'exec', 'shell_exec' ]`.
	 *
	 * @return string[]
	 */
	public function get_restricted_functions(): array {
		return [];
	}

	/**
	 * Return the paths the platform allows writes to.
	 *
	 * Null — the default — means the platform declares no constraint. An empty
	 * array is the opposite: no path is writable.
	 *
	 * @return string[]|null
	 */
	public function get_writable_paths(): ?array {
		return null;
	}

	/**
	 * Return the PHP versions the platform supports.
	 *
	 * Defaults to none declared. Override with the supported series, e.g.
	 * `[ '8.2', '8.3' ]`.
	 *
	 * @return string[]
	 */
	public function get_supported_php_versions(): array {
		return [];
	}

	/**
	 * Return the platform's object-cache constraints.
	 *
	 * Defaults to no limit and no declared backend. Override to state the
	 * per-object size cap and which backend is in play.
	 *
	 * @return array{max_object_bytes: ?int, backend: ?string}
	 */
	public function get_object_cache_constraints(): array {
		return [
			'max_object_bytes' => null,
			'backend'          => null,
		];
	}

	/**
	 * Return the plugin slugs the platform is incompatible with.
	 *
	 * Defaults to none. Override to list them.
	 *
	 * @return string[]
	 */
	public function get_incompatible_plugins(): array {
		return [];
	}
}
