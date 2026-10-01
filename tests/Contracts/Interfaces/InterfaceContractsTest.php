<?php
/**
 * Interface contract smoke tests.
 *
 * Reflection-based checks that the interface signatures haven't been
 * accidentally changed by a refactor. These are cheap and catch the
 * most common breakage (renames, missing methods, inheritance changes).
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Interfaces
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Interfaces;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use rtCamp\WPPrimitives\Contracts\Interfaces\CLICommand;
use rtCamp\WPPrimitives\Contracts\Interfaces\ConditionallyRegistrable;
use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;
use rtCamp\WPPrimitives\Contracts\Interfaces\Shareable;

final class InterfaceContractsTest extends TestCase {

	public function test_registrable_interface_declares_register_hooks(): void {
		$ref = new ReflectionClass( Registrable::class );

		$this->assertTrue( $ref->isInterface() );
		$this->assertTrue( $ref->hasMethod( 'register_hooks' ) );

		$method = $ref->getMethod( 'register_hooks' );
		$this->assertSame( 'void', (string) $method->getReturnType() );
		$this->assertSame( 0, $method->getNumberOfRequiredParameters() );
	}

	public function test_conditionally_registrable_extends_registrable(): void {
		$ref = new ReflectionClass( ConditionallyRegistrable::class );

		$this->assertTrue( $ref->isInterface() );
		$this->assertContains(
			Registrable::class,
			array_map( static fn ( ReflectionClass $i ): string => $i->getName(), $ref->getInterfaces() )
		);
	}

	public function test_conditionally_registrable_declares_can_register_returning_bool(): void {
		$ref    = new ReflectionClass( ConditionallyRegistrable::class );
		$method = $ref->getMethod( 'can_register' );

		$this->assertSame( 'bool', (string) $method->getReturnType() );
		$this->assertSame( 0, $method->getNumberOfRequiredParameters() );
	}

	public function test_shareable_is_a_marker_interface(): void {
		$ref = new ReflectionClass( Shareable::class );

		$this->assertTrue( $ref->isInterface() );
		// Marker interfaces have no methods — if someone adds one, this catches it.
		$this->assertSame( [], $ref->getMethods() );
	}

	public function test_cli_command_has_required_static_methods(): void {
		$ref = new ReflectionClass( CLICommand::class );

		$this->assertTrue( $ref->isInterface() );

		foreach ( [ 'get_name', 'get_description', 'run' ] as $method_name ) {
			$this->assertTrue( $ref->hasMethod( $method_name ), "Missing method: {$method_name}" );
			$this->assertTrue( $ref->getMethod( $method_name )->isStatic(), "{$method_name} must be static" );
		}
	}
}
