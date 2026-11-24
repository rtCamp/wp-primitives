<?php
/**
 * Cache utility.
 *
 * @package rtCamp\WPPrimitives\Utils
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Utils;

/**
 * Class - Cache
 *
 * Typed wrapper over the WordPress object-cache functions. Each cache group is
 * prefixed with the instance's context slug, and {@see Cache::remember_swr()}
 * adds stale-while-revalidate (SWR) regeneration.
 *
 *     $cache = new Cache( 'my-plugin' );
 *     $nav   = $cache->remember( 'nav_items', fn() => build_nav(), 'theme', 300 );
 *
 * Reads and writes go straight to the object cache. Misses are detected with
 * the `$found` argument of `wp_cache_get()`, so falsy values such as `false`
 * and `0` are cached like any other value. See docs/utilities.md.
 *
 * @since 1.0.0
 */
class Cache {
	/**
	 * TTL in seconds for the SWR regeneration lock key.
	 *
	 * If the regenerating process dies before releasing the lock, the lock
	 * expires after this many seconds.
	 */
	protected const LOCK_TTL = 30;

	/**
	 * Microseconds to sleep between cold-start lock-wait retries.
	 */
	protected const LOCK_WAIT_US = 50_000;

	/**
	 * Maximum retries while waiting for another process's cold-start regeneration.
	 */
	protected const LOCK_RETRIES = 2;

	/**
	 * Multiplier applied to `$expiration` when storing the stale entry, so the
	 * stale entry outlives the fresh entry by at least one regeneration.
	 */
	protected const STALE_MULTIPLIER = 2;

	/**
	 * Constructor.
	 *
	 * @param string $context Theme or plugin slug prefixed onto every cache group.
	 *                        An empty string leaves groups unchanged.
	 */
	public function __construct(
		protected string $context = '',
	) {}

	/**
	 * Resolve a caller-supplied group to the namespaced group used in the
	 * object cache.
	 *
	 * With a context of `my-plugin`, group `posts` resolves to
	 * `my-plugin:posts` and the default (empty) group resolves to `my-plugin`.
	 * Without a context, groups are returned unchanged.
	 *
	 * @param string $group Caller-supplied cache group.
	 *
	 * @return string Namespaced cache group.
	 */
	protected function resolve_group( string $group ): string {
		if ( '' === $this->context ) {
			return $group;
		}

		return '' === $group ? $this->context : $this->context . ':' . $group;
	}

	/**
	 * Get a value from cache.
	 *
	 * @param string    $key   Cache key.
	 * @param string    $group Cache group. Defaults to the global group.
	 * @param bool      $force Whether to bypass the drop-in's in-process cache and read from the persistent backend.
	 * @param bool|null $found Set to `true` if the key was found, `false` if not. Tells a stored falsy value apart from
	 *                         a miss.
	 *
	 * @return mixed Stored value, or `false` if missing or expired.
	 */
	public function get( string $key, string $group = '', bool $force = false, ?bool &$found = null ): mixed {
		return wp_cache_get( $key, $this->resolve_group( $group ), $force, $found );
	}

	/**
	 * Set a value in cache.
	 *
	 * @param string $key        Cache key.
	 * @param mixed  $value      Value to store. Must be serialisable.
	 * @param string $group      Cache group. Defaults to the global group.
	 * @param int    $expiration TTL in seconds. `0` means no expiry.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, mixed $value, string $group = '', int $expiration = 0 ): bool {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- The caller sets the expiry.
		return wp_cache_set( $key, $value, $this->resolve_group( $group ), $expiration );
	}

	/**
	 * Delete a single key from cache.
	 *
	 * Does not remove the `{key}_stale` and `{key}_lock` entries written by {@see Cache::remember_swr()}.
	 * To invalidate those as well, flush the group.
	 *
	 * @param string $key   Cache key.
	 * @param string $group Cache group. Defaults to the global group.
	 *
	 * @return bool True if the entry was deleted, false otherwise.
	 */
	public function delete( string $key, string $group = '' ): bool {
		return wp_cache_delete( $key, $this->resolve_group( $group ) );
	}

	/**
	 * Flush an entire cache group. Requires WordPress 6.1+.
	 *
	 * Returns false when the object-cache drop-in does not support group flushing.
	 *
	 * @param string $group Cache group to flush.
	 *
	 * @return bool True on success; false if the WordPress version or cache
	 *              backend does not support group flushing.
	 */
	public function flush_group( string $group ): bool {
		if (
			! function_exists( 'wp_cache_flush_group' )
			|| ! function_exists( 'wp_cache_supports' )
			|| ! wp_cache_supports( 'flush_group' )
		) {
			return false;
		}

		return wp_cache_flush_group( $this->resolve_group( $group ) );
	}

	/**
	 * Return a cached value, generating and storing it if absent.
	 *
	 * Stores one key and takes no lock. For expensive regenerations, use {@see Cache::remember_swr()}.
	 *
	 * `$callback` takes no arguments. Capture any parameters in a closure and include them in `$key`, so each variant
	 *  its own entry:
	 *
	 *     $id    = 42;
	 *     $posts = $cache->remember(
	 *         "user_posts_{$id}",
	 *         fn() => fetch_user_posts( $id ),
	 *         'posts',
	 *         300
	 *     );
	 *
	 * A falsy return value from `$callback` is cached like any other value. If
	 * `$callback` throws, the exception propagates and nothing is cached.
	 *
	 * @param string   $key        Cache key.
	 * @param callable $callback   Called with no arguments on a miss. Its return
	 *                             value is stored and returned.
	 * @param string   $group      Cache group. Defaults to the global group.
	 * @param int      $expiration TTL in seconds. `0` means no expiry.
	 *
	 * @return mixed The cached or generated value.
	 */
	public function remember( string $key, callable $callback, string $group = '', int $expiration = 0 ): mixed {
		$found  = false;
		$cached = $this->get( $key, $group, false, $found );
		if ( $found ) {
			return $cached;
		}

		$value = $callback();
		$this->set( $key, $value, $group, $expiration );

		return $value;
	}

	/**
	 * Return a cached value, regenerating it under a lock and serving a stale copy while one caller regenerates.
	 *
	 * The stale copy is stored at `{key}_stale` and the lock at `{key}_lock`, so  `$key` must not end in `_stale`
	 * or `_lock`.
	 *
	 * Use this instead of {@see Cache::remember()} when regeneration is expensive enough that a thundering herd on expiry matters.
	 *
	 * Flow:
	 * - Fresh hit: return it.
	 * - Fresh miss with a stale copy: the caller that acquires the lock
	 *   regenerates and writes both keys. Every caller, including that one,
	 *   returns the stale copy.
	 * - Both absent: the caller that acquires the lock regenerates and returns
	 *   the new value. Other callers retry the fresh key up to
	 *   {@see Cache::LOCK_RETRIES} times, {@see Cache::LOCK_WAIT_US}
	 *   microseconds apart, then call `$callback` themselves.
	 *
	 * If `$callback` throws, the exception propagates, nothing is cached and the lock is released.
	 *
	 * Locks and stale copies are shared across PHP workers only with a persistent object cache such as Redis
	 * or Memcached.
	 *
	 * @param string   $key        Cache key. Must not end in `_stale` or `_lock`.
	 * @param callable $callback   Called with no arguments on a fresh miss. Its
	 *                             return value is stored and returned.
	 * @param string   $group      Cache group. Defaults to the global group.
	 * @param int      $expiration TTL in seconds for the fresh entry. `0` means
	 *                             no expiry, and no stale copy is written.
	 *
	 * @return mixed The cached, stale or generated value.
	 */
	public function remember_swr( string $key, callable $callback, string $group = '', int $expiration = 0 ): mixed {
		$found  = false;
		$cached = $this->get( $key, $group, false, $found );
		if ( $found ) {
			return $cached;
		}

		$stale_key   = $key . '_stale';
		$lock_key    = $key . '_lock';
		$stale_found = false;
		$stale       = $this->get( $stale_key, $group, false, $stale_found );

		if ( $stale_found ) {
			if ( $this->acquire_lock( $lock_key, $group ) ) {
				$this->store_with_stale( $key, $stale_key, $callback, $group, $expiration, $lock_key );
			}
			return $stale;
		}

		if ( $this->acquire_lock( $lock_key, $group ) ) {
			return $this->store_with_stale( $key, $stale_key, $callback, $group, $expiration, $lock_key );
		}

		for ( $i = 0; $i < static::LOCK_RETRIES; $i++ ) {
			usleep( static::LOCK_WAIT_US );
			$cached = $this->get( $key, $group, false, $found );
			if ( $found ) {
				return $cached;
			}
		}

		return $this->store_with_stale( $key, $stale_key, $callback, $group, $expiration );
	}

	/**
	 * Atomically acquire the SWR regeneration lock.
	 *
	 * `wp_cache_add()` fails when the key already exists, so only one process acquires the lock.
	 * The lock expires after {@see Cache::LOCK_TTL} seconds.
	 *
	 * @param string $lock_key Lock cache key.
	 * @param string $group    Caller-supplied cache group.
	 *
	 * @return bool True if this process acquired the lock.
	 */
	protected function acquire_lock( string $lock_key, string $group ): bool {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- LOCK_TTL is the lock expiry, not a data TTL.
		return wp_cache_add( $lock_key, 1, $this->resolve_group( $group ), static::LOCK_TTL );
	}

	/**
	 * Call `$callback`, write the cache entries, release the lock if given, and return the value.
	 *
	 * The stale copy is written only when `$expiration > 0`. The lock is released even when `$callback` throws.
	 *
	 * @param string   $key        Fresh cache key.
	 * @param string   $stale_key  Stale cache key.
	 * @param callable $callback   Invoked to produce the fresh value.
	 * @param string   $group      Cache group.
	 * @param int      $expiration TTL for the fresh entry. The stale TTL is this times {@see Cache::STALE_MULTIPLIER}.
	 * @param string   $lock_key   Optional lock key to delete after regeneration.
	 *
	 * @return mixed The generated value.
	 */
	protected function store_with_stale( string $key, string $stale_key, callable $callback, string $group, int $expiration, string $lock_key = '' ): mixed {
		try {
			$value = $callback();
			$this->set( $key, $value, $group, $expiration );
			if ( $expiration > 0 ) {
				$this->set( $stale_key, $value, $group, $expiration * static::STALE_MULTIPLIER );
			}
			return $value;
		} finally {
			if ( '' !== $lock_key ) {
				$this->delete( $lock_key, $group );
			}
		}
	}
}
