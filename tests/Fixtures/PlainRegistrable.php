<?php
/**
 * LoaderTest fixture: PlainRegistrable.
 *
 * @package rtCamp\WPPrimitives\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Fixtures;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;

/**
 * A plain Registrable — should always have register_hooks() called.
 */
final class PlainRegistrable implements Registrable {
	public static bool $registered = false;

	public function register_hooks(): void {
		self::$registered = true;
	}
}
