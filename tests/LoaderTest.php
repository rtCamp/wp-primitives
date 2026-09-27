<?php
/**
 * Loader trait tests.
 *
 * Verifies the load() contract:
 *   - Plain Registrable → register_hooks() called
 *   - ConditionallyRegistrable + can_register()=true → register_hooks() called
 *   - ConditionallyRegistrable + can_register()=false → register_hooks() skipped
 *   - Shareable → instance cached, retrievable via get_shared()
 *   - A class that is both Shareable and Registrable gets both treatments
 *
 * @package rtCamp\WPPrimitives\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use rtCamp\WPPrimitives\Tests\Fixtures\ConditionalAllowed;
use rtCamp\WPPrimitives\Tests\Fixtures\CountingRegistrable;
use rtCamp\WPPrimitives\Tests\Fixtures\ConditionalDenied;
use rtCamp\WPPrimitives\Tests\Fixtures\LoaderRunner;
use rtCamp\WPPrimitives\Tests\Fixtures\PlainRegistrable;
use rtCamp\WPPrimitives\Tests\Fixtures\ShareableRegistrable;

final class LoaderTest extends TestCase {

	protected function setUp(): void {
		PlainRegistrable::$registered      = false;
		ConditionalAllowed::$registered    = false;
		ConditionalDenied::$registered     = false;
		ShareableRegistrable::$registered  = false;
		CountingRegistrable::$construct_count = 0;
		CountingRegistrable::$register_count  = 0;
	}

	public function test_plain_registrable_has_hooks_registered(): void {
		( new LoaderRunner() )->run( [ PlainRegistrable::class ] );

		$this->assertTrue( PlainRegistrable::$registered );
	}

	public function test_conditional_registrable_is_registered_when_can_register_is_true(): void {
		( new LoaderRunner() )->run( [ ConditionalAllowed::class ] );

		$this->assertTrue( ConditionalAllowed::$registered );
	}

	public function test_conditional_registrable_is_skipped_when_can_register_is_false(): void {
		( new LoaderRunner() )->run( [ ConditionalDenied::class ] );

		$this->assertFalse( ConditionalDenied::$registered );
	}

	public function test_a_class_listed_twice_is_loaded_once(): void {
		// Without de-duplication the class is constructed twice and registers its
		// hooks on two separate instances, so the hook body runs twice while the
		// container keeps only the last instance.
		$loader = new LoaderRunner();
		$loader->run( [ CountingRegistrable::class, CountingRegistrable::class ] );

		$this->assertSame( 1, CountingRegistrable::$construct_count );
		$this->assertSame( 1, CountingRegistrable::$register_count );
	}

	public function test_the_shared_instance_is_the_one_that_registered_hooks(): void {
		// The de-duplication must keep a single instance, not quietly replace the
		// registered one with a second construction.
		$loader = new LoaderRunner();
		$loader->run( [ CountingRegistrable::class, CountingRegistrable::class ] );

		$this->assertInstanceOf(
			CountingRegistrable::class,
			$loader->get_shared( CountingRegistrable::class )
		);
		$this->assertSame( 1, CountingRegistrable::$construct_count );
	}

	public function test_duplicates_do_not_suppress_other_classes_in_the_list(): void {
		$loader = new LoaderRunner();
		$loader->run( [ CountingRegistrable::class, PlainRegistrable::class, CountingRegistrable::class ] );

		$this->assertSame( 1, CountingRegistrable::$register_count );
		$this->assertTrue( PlainRegistrable::$registered );
	}

	public function test_shareable_instance_is_retrievable(): void {
		$loader = new LoaderRunner();
		$loader->run( [ ShareableRegistrable::class ] );

		$this->assertInstanceOf(
			ShareableRegistrable::class,
			$loader->get_shared( ShareableRegistrable::class )
		);
	}

	public function test_class_that_is_both_shareable_and_registrable_gets_both_treatments(): void {
		$loader = new LoaderRunner();
		$loader->run( [ ShareableRegistrable::class ] );

		$this->assertTrue( ShareableRegistrable::$registered );
		$this->assertTrue( $loader->get_shared( ShareableRegistrable::class ) instanceof ShareableRegistrable );
	}

	public function test_get_shared_throws_for_unregistered_class(): void {
		$loader = new LoaderRunner();
		$loader->run( [ PlainRegistrable::class ] );

		$this->expectException( RuntimeException::class );
		$loader->get_shared( PlainRegistrable::class );
	}

	public function test_get_shared_throws_before_load_is_called(): void {
		$loader = new LoaderRunner();

		$this->expectException( RuntimeException::class );
		$loader->get_shared( PlainRegistrable::class );
	}
}
