<?php
/**
 * Cache utility tests.
 *
 * @package rtCamp\WPPrimitives\Tests\Utils
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Utils;

use rtCamp\WPPrimitives\Tests\TestCase;
use rtCamp\WPPrimitives\Utils\Cache;

/**
 * Tests for Cache.
 *
 * Runs against real WordPress (wp-env). With no persistent object cache
 * configured, wp_cache_* is backed by the in-process non-persistent cache; it is
 * flushed in set_up so no entry leaks between tests under random execution order.
 * Each test also uses a distinct context/group so namespaced keys never collide.
 */
final class CacheTest extends TestCase {

	/**
	 * Start every test from an empty object cache.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_cache_flush();
	}

	public function test_resolve_group_namespaces_values_by_context(): void {
		$cache = new Cache( 'ctx-ns' );

		$this->assertTrue( $cache->set( 'k', 'v', 'posts' ) );
		// The value must live under the namespaced group, not the raw group.
		$this->assertSame( 'v', wp_cache_get( 'k', 'ctx-ns:posts' ) );
		$this->assertFalse( wp_cache_get( 'k', 'posts' ) );
		// The default (empty) group resolves to the bare context.
		$this->assertTrue( $cache->set( 'k2', 'v2' ) );
		$this->assertSame( 'v2', wp_cache_get( 'k2', 'ctx-ns' ) );
	}

	public function test_get_reports_found_true_for_a_stored_false(): void {
		$cache = new Cache( 'ctx-found' );
		$cache->set( 'flag', false, 'g' );

		$found = null;
		$value = $cache->get( 'flag', 'g', false, $found );

		$this->assertFalse( $value );
		$this->assertTrue( $found, 'A stored false must be reported as found, not a miss.' );
	}

	public function test_remember_caches_a_false_return_and_does_not_reinvoke(): void {
		$cache = new Cache( 'ctx-remember' );
		$calls = 0;
		$make  = function () use ( &$calls ): bool {
			++$calls;
			return false; // A falsy value the naive "=== false means miss" check would recompute forever.
		};

		$this->assertFalse( $cache->remember( 'k', $make, 'g' ) );
		$this->assertFalse( $cache->remember( 'k', $make, 'g' ) );
		$this->assertSame( 1, $calls, 'remember() must cache a stored false and not re-invoke the callback.' );
	}

	public function test_remember_swr_cold_start_writes_fresh_and_stale_and_releases_lock(): void {
		$cache = new Cache( 'ctx-swr-cold' );

		$value = $cache->remember_swr( 'k', static fn (): string => 'fresh', 'g', 100 );

		$this->assertSame( 'fresh', $value );
		$this->assertSame( 'fresh', wp_cache_get( 'k', 'ctx-swr-cold:g' ), 'fresh value must be cached' );
		$this->assertSame( 'fresh', wp_cache_get( 'k_stale', 'ctx-swr-cold:g' ), 'stale companion must be written for an expiring entry' );
		$this->assertFalse( wp_cache_get( 'k_lock', 'ctx-swr-cold:g' ), 'lock must be released after regeneration' );
	}

	public function test_remember_swr_does_not_write_stale_for_a_non_expiring_entry(): void {
		$cache = new Cache( 'ctx-swr-noexpire' );

		$cache->remember_swr( 'k', static fn (): string => 'fresh', 'g', 0 );

		$this->assertSame( 'fresh', wp_cache_get( 'k', 'ctx-swr-noexpire:g' ) );
		$this->assertFalse( wp_cache_get( 'k_stale', 'ctx-swr-noexpire:g' ), 'a never-expiring entry has nothing to revalidate' );
	}

	public function test_remember_swr_releases_lock_when_callback_throws(): void {
		$cache = new Cache( 'ctx-swr-throw' );

		try {
			$cache->remember_swr(
				'k',
				static function (): string {
					throw new \RuntimeException( 'boom' );
				},
				'g',
				100
			);
			$this->fail( 'The callback exception should propagate.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'boom', $e->getMessage() );
		}

		$this->assertFalse(
			wp_cache_get( 'k_lock', 'ctx-swr-throw:g' ),
			'A throwing callback must still release the lock (finally), not strand it for LOCK_TTL.'
		);
	}

	public function test_remember_swr_serves_stale_and_regenerates_fresh_on_expiry(): void {
		$cache = new Cache( 'ctx-swr-stale' );
		// Simulate an expired fresh entry with a surviving stale companion.
		wp_cache_set( 'k_stale', 'old', 'ctx-swr-stale:g' );

		$returned = $cache->remember_swr( 'k', static fn (): string => 'new', 'g', 100 );

		$this->assertSame( 'old', $returned, 'the winner serves the stale value for the current request' );
		$this->assertSame( 'new', wp_cache_get( 'k', 'ctx-swr-stale:g' ), 'the fresh value is regenerated for the next request' );
	}

	public function test_remember_swr_returns_stale_without_regenerating_when_lock_is_held(): void {
		$cache = new Cache( 'ctx-swr-locked' );
		// Another process holds the lock and a stale copy exists.
		wp_cache_set( 'k_stale', 'old', 'ctx-swr-locked:g' );
		$this->assertTrue( wp_cache_add( 'k_lock', 1, 'ctx-swr-locked:g', 30 ), 'precondition: hold the lock' );

		$returned = $cache->remember_swr( 'k', static fn (): string => 'new', 'g', 100 );

		$this->assertSame( 'old', $returned, 'a losing caller is served stale immediately' );
		$this->assertFalse( wp_cache_get( 'k', 'ctx-swr-locked:g' ), 'the loser must not regenerate — that is the stampede prevention' );
	}

	public function test_empty_context_passes_groups_through_verbatim(): void {
		$cache = new Cache();

		$this->assertTrue( $cache->set( 'k', 'v', 'raw-group' ) );
		$this->assertSame( 'v', wp_cache_get( 'k', 'raw-group' ) );
		$this->assertSame( 'v', $cache->get( 'k', 'raw-group' ) );

		$this->assertTrue( $cache->delete( 'k', 'raw-group' ) );
		$this->assertFalse( wp_cache_get( 'k', 'raw-group' ) );
	}

	public function test_flush_group_clears_only_the_namespaced_group(): void {
		$cache = new Cache( 'ctx-flush' );
		$cache->set( 'a', 1, 'g' );
		$cache->set( 'b', 2, 'g' );
		$cache->set( 'c', 3, 'other' );

		// Core's non-persistent cache supports group flushing (WordPress 6.1+).
		$this->assertTrue( wp_cache_supports( 'flush_group' ), 'precondition: core cache supports flush_group' );
		$this->assertTrue( $cache->flush_group( 'g' ) );

		$found = null;
		$cache->get( 'a', 'g', false, $found );
		$this->assertFalse( $found );
		$cache->get( 'b', 'g', false, $found );
		$this->assertFalse( $found );
		$this->assertSame( 3, $cache->get( 'c', 'other' ), 'flushing one group must not touch another' );
	}

	public function test_remember_swr_returns_a_fresh_hit_without_invoking_the_callback(): void {
		$cache = new Cache( 'ctx-swr-hit' );
		$cache->set( 'k', 'fresh', 'g', 100 );

		$calls    = 0;
		$returned = $cache->remember_swr(
			'k',
			static function () use ( &$calls ): string {
				++$calls;
				return 'regenerated';
			},
			'g',
			100
		);

		$this->assertSame( 'fresh', $returned );
		$this->assertSame( 0, $calls );
	}

	public function test_remember_swr_cold_start_falls_back_to_the_callback_when_the_lock_never_frees(): void {
		$cache = new Cache( 'ctx-swr-wait' );
		// Another process holds the lock on a cold start (no fresh, no stale) and
		// never finishes: after the bounded wait the caller regenerates itself.
		$this->assertTrue( wp_cache_add( 'k_lock', 1, 'ctx-swr-wait:g', 30 ), 'precondition: hold the lock' );

		$calls    = 0;
		$returned = $cache->remember_swr(
			'k',
			static function () use ( &$calls ): string {
				++$calls;
				return 'computed';
			},
			'g',
			100
		);

		$this->assertSame( 'computed', $returned );
		$this->assertSame( 1, $calls );
		$this->assertSame( 'computed', wp_cache_get( 'k', 'ctx-swr-wait:g' ), 'the last-resort value is still cached' );
		$this->assertSame( 1, wp_cache_get( 'k_lock', 'ctx-swr-wait:g' ), 'the other process\'s lock must not be released by the fallback' );
	}

	public function test_remember_swr_cold_start_waits_for_the_lock_holder_to_publish(): void {
		// Simulate the lock holder finishing during our wait: the second read of
		// the fresh key (the first retry) finds the value it published.
		$cache = new class( 'ctx-swr-publish' ) extends Cache {
			/**
			 * Reads of the fresh key so far.
			 *
			 * @var int
			 */
			public int $fresh_reads = 0;

			public function get( string $key, string $group = '', bool $force = false, ?bool &$found = null ): mixed {
				if ( 'k' === $key && 2 === ++$this->fresh_reads ) {
					parent::set( 'k', 'published', $group, 100 );
				}

				return parent::get( $key, $group, $force, $found );
			}
		};
		$this->assertTrue( wp_cache_add( 'k_lock', 1, 'ctx-swr-publish:g', 30 ), 'precondition: hold the lock' );

		$calls    = 0;
		$returned = $cache->remember_swr(
			'k',
			static function () use ( &$calls ): string {
				++$calls;
				return 'computed';
			},
			'g',
			100
		);

		$this->assertSame( 'published', $returned );
		$this->assertSame( 0, $calls, 'a caller that sees the published value must not regenerate' );
	}
}
