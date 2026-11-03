<?php
/**
 * Transients utility tests.
 *
 * @package rtCamp\WPPrimitives\Tests\Utils
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Utils;

use rtCamp\WPPrimitives\Tests\TestCase;
use rtCamp\WPPrimitives\Utils\Transients;

/**
 * Tests for Transients.
 *
 * Runs against real WordPress (wp-env): get/set/delete hit the real transient API,
 * which — with no persistent object cache configured — is backed by the options
 * table and rolled back per test by WP_UnitTestCase's DB transaction.
 *
 * Each test uses a distinct prefix so that, even if a non-persistent object cache
 * holds entries across tests under `executionOrder="random"`, no two tests can
 * resolve to the same namespaced key.
 */
final class TransientsTest extends TestCase {

	public function test_set_then_get_round_trips_a_value(): void {
		$store = new Transients( 'rt_round_trip' );

		$this->assertTrue( $store->set( 'thing', 42 ) );
		$this->assertSame( 42, $store->get( 'thing' ) );
	}

	public function test_missing_key_returns_false(): void {
		$this->assertFalse( ( new Transients( 'rt_missing' ) )->get( 'never-set' ) );
	}

	public function test_two_prefixes_do_not_collide_on_the_same_logical_key(): void {
		$a = new Transients( 'rt_iso_a' );
		$b = new Transients( 'rt_iso_b' );

		$a->set( 'shared_key', 'value-from-a' );
		$b->set( 'shared_key', 'value-from-b' );

		$this->assertSame( 'value-from-a', $a->get( 'shared_key' ) );
		$this->assertSame( 'value-from-b', $b->get( 'shared_key' ) );
	}

	public function test_delete_removes_only_this_instances_entry(): void {
		$a = new Transients( 'rt_del_a' );
		$b = new Transients( 'rt_del_b' );

		$a->set( 'k', 'a' );
		$b->set( 'k', 'b' );

		$this->assertTrue( $a->delete( 'k' ) );

		$this->assertFalse( $a->get( 'k' ) );
		$this->assertSame( 'b', $b->get( 'k' ) );
	}

	public function test_prefix_key_boundary_is_unambiguous(): void {
		// Without the length-prefixed key format, ( 'mod', 'a_b' ) and ( 'mod_a', 'b' )
		// would both collapse to mod_a_b and clobber each other.
		$one = new Transients( 'rtb' );      // 'rtb' + '_' + 'x_y'
		$two = new Transients( 'rtb_x' );    // 'rtb_x' + '_' + 'y'

		$one->set( 'x_y', 'one' );
		$two->set( 'y', 'two' );

		$this->assertSame( 'one', $one->get( 'x_y' ) );
		$this->assertSame( 'two', $two->get( 'y' ) );
	}

	public function test_over_long_key_still_round_trips_through_the_database(): void {
		// Regression: a namespaced name past 172 characters was truncated by the
		// options table, so once the in-request option cache was gone (a new request)
		// the value could never be read back. Flushing the object cache simulates that.
		$store = new Transients( 'rt_long' );
		$key   = str_repeat( 'k', 200 );

		$this->assertTrue( $store->set( $key, 'long-value', HOUR_IN_SECONDS ) );

		wp_cache_flush();

		$this->assertSame( 'long-value', $store->get( $key ) );
		$this->assertTrue( $store->delete( $key ) );
		$this->assertFalse( $store->get( $key ) );
	}

	public function test_over_long_keys_stay_distinct_and_within_the_limit(): void {
		$store = new Transients( 'rt_long_distinct' );
		$first = str_repeat( 'a', 180 ) . '-one';
		$other = str_repeat( 'a', 180 ) . '-two';

		$store->set( $first, 'one' );
		$store->set( $other, 'two' );
		wp_cache_flush();

		$this->assertSame( 'one', $store->get( $first ) );
		$this->assertSame( 'two', $store->get( $other ) );

		$resolve = new \ReflectionMethod( Transients::class, 'resolve_key' );
		$name    = $resolve->invoke( $store, $first );

		$this->assertLessThanOrEqual( 172, strlen( $name ) );
		$this->assertStringStartsWith( 'h:', $name );
	}

	public function test_key_at_the_limit_is_not_hashed(): void {
		// '5:rt_ok_' is 8 characters, so a 164-character key lands exactly on 172.
		$store   = new Transients( 'rt_ok' );
		$key     = str_repeat( 'k', 164 );
		$resolve = new \ReflectionMethod( Transients::class, 'resolve_key' );

		$this->assertSame( '5:rt_ok_' . $key, $resolve->invoke( $store, $key ) );

		$store->set( $key, 'at-limit', HOUR_IN_SECONDS );
		wp_cache_flush();

		$this->assertSame( 'at-limit', $store->get( $key ) );
	}

	public function test_value_is_stored_under_the_namespaced_key(): void {
		( new Transients( 'rt_ns' ) )->set( 'thing', 'v' );

		// The bare, unprefixed key must not resolve; the namespaced one must.
		$this->assertFalse( get_transient( 'thing' ) );
		$this->assertSame( 'v', get_transient( '5:rt_ns_thing' ) );
	}
}
