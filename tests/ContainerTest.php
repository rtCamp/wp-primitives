<?php
/**
 * Container tests.
 *
 * @package rtCamp\WPPrimitives\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use rtCamp\WPPrimitives\Container;
use stdClass;

final class ContainerTest extends TestCase {

	public function test_set_then_get_returns_same_instance(): void {
		$container = new Container();
		$instance  = new stdClass();

		$container->set( stdClass::class, $instance );

		$this->assertSame( $instance, $container->get( stdClass::class ) );
	}

	public function test_has_reflects_whether_id_is_registered(): void {
		$container = new Container();

		$this->assertFalse( $container->has( stdClass::class ) );

		$container->set( stdClass::class, new stdClass() );

		$this->assertTrue( $container->has( stdClass::class ) );
	}

	public function test_get_throws_when_id_is_missing(): void {
		$container = new Container();

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( stdClass::class );

		$container->get( stdClass::class );
	}

	public function test_set_overwrites_previous_instance_under_same_id(): void {
		$container = new Container();
		$first     = new stdClass();
		$second    = new stdClass();

		$container->set( stdClass::class, $first );
		$container->set( stdClass::class, $second );

		$this->assertSame( $second, $container->get( stdClass::class ) );
	}
}
