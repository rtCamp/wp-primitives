<?php
/**
 * LoaderTest fixture: CountingRegistrable.
 *
 * @package rtCamp\WPPrimitives\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Fixtures;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;
use rtCamp\WPPrimitives\Contracts\Interfaces\Shareable;

/**
 * Shareable Registrable that counts constructions and register_hooks() calls.
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
