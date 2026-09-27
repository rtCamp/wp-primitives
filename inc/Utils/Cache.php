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
 * Typed wrapper over WordPress's object-cache functions with per-consumer
 * group namespacing and optional stale-while-revalidate (SWR) stampede
 * prevention.
 *
 * Instance-based, configured with a context slug at construction — so each
 * consumer's cache groups are namespaced and cannot collide with another
 * plugin or theme using the same group name through a shared object cache:
 *
 *     $cache = new Cache( 'my-plugin' );
 *     $nav   = $cache->remember( 'nav_items', fn() => build_nav(), 'theme', 300 );
 *
 * Designed to be a service: construct it with the package's slug, register an
 * instance as Shareable in a consumer's container, or extend it to change the
 * namespacing by overriding the {@see Cache::resolve_group()} seam (the same
 * pattern as {@see \rtCamp\WPPrimitives\ComponentLoader::get_context()}).
 *
 * Centralises calls to `wp_cache_get`, `wp_cache_set`, `wp_cache_delete`, and
 * `wp_cache_flush_group` so consumers have a single typed API and one place to
 * layer cross-cutting behaviour (logging, telemetry, fallbacks). It adds no
 * cache layer of its own: WordPress's object cache already keeps an
 * in-process, per-request store (and persistent drop-ins keep a local cache in
 * front of the network), so every read and write goes straight through.
 *
 * Miss detection uses `wp_cache_get()`'s `$found` out-parameter, so falsy
 * values such as `false` or `0` are cached and served like any other value —
 * a callback returning `false` is not regenerated on every call.
 *
 * Two get-or-set flavours:
 * - {@see Cache::remember()} — plain get-or-set; one key, no extra writes.
 * - {@see Cache::remember_swr()} — adds stale-while-revalidate stampede
 *   prevention for expensive regenerations: on expiry, one caller regenerates
 *   in the foreground while the rest are served the stale copy.
 *
 * `wp_cache_flush_group()` was added in WordPress 6.1; `flush_group()` checks
 * `wp_cache_supports( 'flush_group' )` so it gracefully returns `false` when
 * the persistent cache backend does not support group flushing.
 *
 * @since 1.0.0
 */
class Cache {

	/**
	 * TTL (seconds) for the SWR regeneration lock key.
	 *
	 * Dead-man switch: if the regenerating process crashes before releasing the
	 * lock, it auto-expires so subsequent requests are not permanently blocked.
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
	 * Multiplier applied to `$expiration` when storing the stale entry.
	 * Stale data must outlive the fresh entry long enough for at least one
	 * regeneration cycle to complete.
	 */
	protected const STALE_MULTIPLIER = 2;

	/**
	 * Constructor.
	 *
	 * @param string $context Context slug identifying the package that owns this
	 *                        instance (e.g. a theme/plugin slug). Prefixed onto
	 *                        every cache group so consumers sharing an object
	 *                        cache cannot collide. Empty string means no
	 *                        namespacing — groups are passed through verbatim.
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
	 * Without a context, groups pass through verbatim. Override this seam to
	 * change the namespacing scheme.
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
	 * Returns `mixed` because object-cache entries can hold any serialisable
	 * value (mirrors WordPress's own `wp_cache_get()` signature).
	 *
	 * @param string    $key   Cache key.
	 * @param string    $group Cache group. Defaults to the global group.
	 * @param bool      $force Re-fetch from the persistent backend, bypassing the
	 *                         drop-in's local in-process cache.
	 * @param bool|null $found Set to `true` if the key was found, `false` if not.
	 *                         Disambiguates a stored falsy value from a miss.
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
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- expiry originates with the caller; enforced at call sites, not inside the wrapper.
		return wp_cache_set( $key, $value, $this->resolve_group( $group ), $expiration );
	}

	/**
	 * Delete a single key from cache.
	 *
	 * Deletes only `$key`. It does not remove the companion `{key}_stale` /
	 * `{key}_lock` entries written by {@see Cache::remember_swr()} — to fully
	 * invalidate an SWR-remembered value, flush its group instead.
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
	 * Gates on `wp_cache_supports( 'flush_group' )` rather than a bare
	 * `function_exists()` check: `wp_cache_flush_group()` exists on every WP 6.1+
	 * core, but the active object-cache drop-in may not implement group flushing
	 * (e.g. some Memcached or Redis configurations). Calling it there would
	 * report success while flushing nothing.
	 *
	 * Both functions are guarded with `function_exists()`: `wp_cache_supports`
	 * keeps this safe on pre-6.1 cores (where neither exists), and the explicit
	 * `wp_cache_flush_group` guard protects against an inconsistent drop-in that
	 * advertises support without defining the function.
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
	 * Plain get-or-set: one key, no companion entries, no locking. For
	 * expensive regenerations that need stampede protection, use
	 * {@see Cache::remember_swr()} instead.
	 *
	 * `$callback` takes no arguments: to regenerate with specific parameters,
	 * capture them in a closure and encode the same parameters in `$key` so each
	 * variant caches under its own entry:
	 *
	 *     $id    = 42;
	 *     $posts = $cache->remember(
	 *         "user_posts_{$id}",
	 *         fn() => fetch_user_posts( $id ),
	 *         'posts',
	 *         300
	 *     );
	 *
	 * Hits are detected via `wp_cache_get()`'s `$found` out-parameter, so a
	 * callback that returns a falsy value (`false`, `0`, `''`) is cached
	 * normally and not re-invoked on every call.
	 *
	 * If `$callback` throws, the exception propagates to the caller and nothing
	 * is cached.
	 *
	 * Returns `mixed` because the stored value can be any serialisable type
	 * (mirrors WordPress's own `wp_cache_get()` return type).
	 *
	 * @param string   $key        Cache key.
	 * @param callable $callback   Invoked (with no arguments) when the entry is
	 *                             absent; its return value is stored and
	 *                             returned. Capture any inputs via a closure.
	 * @param string   $group      Cache group. Defaults to the global group.
	 * @param int      $expiration TTL in seconds. `0` means no expiry.
	 *
	 * @return mixed The cached or freshly-generated value.
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
	 * Return a cached value with stale-while-revalidate stampede prevention,
	 * generating and storing it if absent.
	 *
	 * Use this instead of {@see Cache::remember()} when regeneration is
	 * expensive enough that a thundering herd on expiry matters. On expiry,
	 * exactly one caller wins the regeneration lock and runs `$callback`
	 * synchronously, paying the regeneration cost in the foreground; every
	 * other caller returns the stale copy (stored at `{key}_stale` with a 2×
	 * TTL) without waiting. All callers — including the lock winner — are
	 * served the stale value for the current request; the fresh value lands in
	 * cache for subsequent requests.
	 *
	 * Reserved suffixes: companion entries are stored at `{key}_stale` and
	 * `{key}_lock`. Avoid passing a `$key` that already ends in `_stale` or
	 * `_lock`, as it would collide with another remembered value's companions.
	 *
	 * Flow:
	 * - Fresh hit          → return immediately.
	 * - Fresh miss + stale → if lock acquired, regenerate synchronously, write
	 *                        both keys, release lock; return stale either way.
	 * - Both absent        → cold start (first-ever load or full flush); acquire
	 *                        lock, regenerate, write both keys; if lock not
	 *                        acquired, spin-wait up to {@see Cache::LOCK_RETRIES} ×
	 *                        {@see Cache::LOCK_WAIT_US} µs, then call `$callback`
	 *                        directly as a last resort without releasing the
	 *                        other process's lock.
	 *
	 * As with {@see Cache::remember()}, hits are detected via the `$found`
	 * out-parameter (falsy values cache normally) and `$callback` takes no
	 * arguments — capture inputs via a closure and encode them in `$key`.
	 *
	 * If `$callback` throws, the exception propagates to the caller and the
	 * regeneration lock is released immediately (no value is cached), so the
	 * next request can retry without waiting out {@see Cache::LOCK_TTL}.
	 *
	 * **Requires a persistent object cache (Redis, Memcached) for effective
	 * cross-process stampede prevention.** On the default WordPress in-memory
	 * cache each PHP-FPM worker has its own isolated cache, so locks and stale
	 * entries are not shared across processes. The method remains useful as an
	 * ergonomic get-or-set helper regardless.
	 *
	 * Returns `mixed` because the stored value can be any serialisable type
	 * (mirrors WordPress's own `wp_cache_get()` return type).
	 *
	 * @param string   $key        Cache key (avoid endings `_stale` / `_lock`).
	 * @param callable $callback   Invoked (with no arguments) when the fresh
	 *                             entry is absent; its return value is stored and
	 *                             returned. Capture any inputs via a closure.
	 * @param string   $group      Cache group. Defaults to the global group.
	 * @param int      $expiration TTL in seconds for the fresh entry. `0` means
	 *                             no expiry: no stale companion is written (a
	 *                             never-expiring entry has nothing to
	 *                             revalidate), though cold-start lock
	 *                             protection still applies.
	 *
	 * @return mixed The cached or freshly-generated value.
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
	 * Relies on `wp_cache_add()` failing when the key already exists, so only
	 * one process wins the lock. The {@see Cache::LOCK_TTL} expiry is a
	 * dead-man switch in case the winner crashes before releasing it.
	 *
	 * @param string $lock_key Lock cache key.
	 * @param string $group    Caller-supplied cache group.
	 *
	 * @return bool True if this process acquired the lock.
	 */
	protected function acquire_lock( string $lock_key, string $group ): bool {
		// phpcs:ignore WordPressVIPMinimum.Performance.LowExpiryCacheTime.CacheTimeUndetermined -- LOCK_TTL is a dead-man-switch for the lock entry, not a data TTL.
		return wp_cache_add( $lock_key, 1, $this->resolve_group( $group ), static::LOCK_TTL );
	}

	/**
	 * Invoke `$callback`, write the cache entries, optionally release the lock, and return the value.
	 *
	 * The stale companion is only written for expiring entries
	 * (`$expiration > 0`): a never-expiring fresh entry has nothing to
	 * revalidate, so duplicating it at `{key}_stale` would only waste backend
	 * memory.
	 *
	 * When this process acquired the lock, it is released in a `finally` block so
	 * a throwing callback cannot leave it held for the remainder of
	 * {@see Cache::LOCK_TTL}.
	 *
	 * Returns `mixed` because the callback can return any serialisable type.
	 *
	 * @param string   $key        Fresh cache key.
	 * @param string   $stale_key  Stale cache key (grace-period copy).
	 * @param callable $callback   Invoked to produce the fresh value.
	 * @param string   $group      Cache group.
	 * @param int      $expiration TTL for the fresh entry; stale TTL = expiration × {@see Cache::STALE_MULTIPLIER}.
	 * @param string   $lock_key   Optional lock key to delete after regeneration.
	 *
	 * @return mixed The freshly-generated value.
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
