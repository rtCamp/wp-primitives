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
 * Registrable that records whether register_hooks() ran.
 */
final class PlainRegistrable implements Registrable {
	public static bool $registered = false;

	public function register_hooks(): void {
		self::$registered = true;
	}
}
