<?php
/**
 * Test fixtures for LoaderTest.
 *
 * Multiple classes per file — explicitly required from bootstrap.php
 * since PSR-4 cannot autoload them.
 *
 * @package rtCamp\WPPrimitives\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Fixtures;

use rtCamp\WPPrimitives\Contracts\Interfaces\ConditionallyRegistrable;
use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;
use rtCamp\WPPrimitives\Contracts\Interfaces\Shareable;
use rtCamp\WPPrimitives\Contracts\Traits\Loader;

/**
 * Public wrapper around the Loader trait so tests can call load() and
 * get_shared() from outside the class hierarchy.
 */
final class LoaderRunner {
	use Loader;

	/**
	 * Load classes through the trait.
	 *
	 * @param class-string[] $classes Classes to load.
	 */
	public function run( array $classes ): void {
		$this->load( $classes );
	}
}

/**
 * A plain Registrable — should always have register_hooks() called.
 */
final class PlainRegistrable implements Registrable {
	public static bool $registered = false;

	public function register_hooks(): void {
		self::$registered = true;
	}
}

/**
 * A ConditionallyRegistrable that opts in — should have register_hooks() called.
 */
final class ConditionalAllowed implements ConditionallyRegistrable {
	public static bool $registered = false;

	public function can_register(): bool {
		return true;
	}

	public function register_hooks(): void {
		self::$registered = true;
	}
}

/**
 * A ConditionallyRegistrable that opts out — register_hooks() must NOT fire.
 */
final class ConditionalDenied implements ConditionallyRegistrable {
	public static bool $registered = false;

	public function can_register(): bool {
		return false;
	}

	public function register_hooks(): void {
		self::$registered = true;
	}
}

/**
 * Implements both Registrable and Shareable — hooks fire AND the instance
 * is cached in the container.
 */
final class ShareableRegistrable implements Registrable, Shareable {
	public static bool $registered = false;

	public function register_hooks(): void {
		self::$registered = true;
	}
}

/**
 * Counts constructions and registrations so a duplicate entry in the class list
 * is observable — a boolean flag cannot tell "registered" from "registered twice".
 */
final class CountingRegistrable implements Registrable, Shareable {
	public static int $construct_count = 0;

	public static int $register_count = 0;

	public function __construct() {
		++self::$construct_count;
	}

	public function register_hooks(): void {
		++self::$register_count;
	}
}
