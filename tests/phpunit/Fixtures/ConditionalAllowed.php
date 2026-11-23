<?php
/**
 * LoaderTest fixture: ConditionalAllowed.
 *
 * @package rtCamp\WPPrimitives\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Fixtures;

use rtCamp\WPPrimitives\Contracts\Interfaces\ConditionallyRegistrable;

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
