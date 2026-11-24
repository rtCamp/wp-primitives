<?php
/**
 * Singleton trait tests.
 *
 * @package rtCamp\WPPrimitives\Tests\Integration
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Integration;

use rtCamp\WPPrimitives\Tests\Fixtures\BareSingleton;
use rtCamp\WPPrimitives\Tests\Fixtures\FlakySingleton;
use rtCamp\WPPrimitives\Tests\Fixtures\ReentrantSingleton;
use rtCamp\WPPrimitives\Tests\Fixtures\SingletonExample;
use rtCamp\WPPrimitives\Tests\TestCase;

final class SingletonTest extends TestCase {
	public function test_get_instance_returns_the_same_instance_each_call(): void {
		$first  = SingletonExample::get_instance();
		$second = SingletonExample::get_instance();

		$this->assertSame( $first, $second );
	}

	public function test_get_instance_returns_concrete_class(): void {
		$instance = SingletonExample::get_instance();

		$this->assertInstanceOf( SingletonExample::class, $instance );
	}

	public function test_constructor_runs_only_once_regardless_of_call_count(): void {
		SingletonExample::get_instance();
		SingletonExample::get_instance();
		SingletonExample::get_instance();

		$this->assertSame( 1, SingletonExample::$construct_count );
	}

	public function test_cloning_is_disallowed(): void {
		$this->setExpectedIncorrectUsage( '__clone' );

		$instance = SingletonExample::get_instance();
		clone $instance;
	}

	public function test_deserializing_is_disallowed(): void {
		$this->setExpectedIncorrectUsage( '__wakeup' );

		$instance = SingletonExample::get_instance();
		unserialize( serialize( $instance ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize, WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Exercises the __wakeup guard.
	}

	public function test_default_constructor_is_used_when_not_overridden(): void {
		$this->assertInstanceOf( BareSingleton::class, BareSingleton::get_instance() );
	}

	public function test_early_assignment_guard_supports_reentrant_construction(): void {
		$instance = ReentrantSingleton::get_instance();

		$this->assertSame( 1, ReentrantSingleton::$construct_count );
		$this->assertSame( $instance, ReentrantSingleton::$seen_during_construction );

		$this->assertSame( 'yes', ReentrantSingleton::$seen_during_construction->ready );
	}

	public function test_a_throwing_constructor_is_not_left_published(): void {
		FlakySingleton::$fail = true;

		try {
			FlakySingleton::get_instance();
			$this->fail( 'Expected the constructor exception to propagate.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'construction failed', $e->getMessage() );
		}

		FlakySingleton::$fail = false;

		$this->assertInstanceOf( FlakySingleton::class, FlakySingleton::get_instance() );
	}
}
