<?php
/**
 * LoaderTest fixture: ConditionalDenied.
 *
 * @package rtCamp\WPPrimitives\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Fixtures;

use rtCamp\WPPrimitives\Contracts\Interfaces\ConditionallyRegistrable;

/**
 * ConditionallyRegistrable whose can_register() returns false.
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
