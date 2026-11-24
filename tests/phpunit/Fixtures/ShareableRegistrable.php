<?php
/**
 * LoaderTest fixture: ShareableRegistrable.
 *
 * @package rtCamp\WPPrimitives\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Fixtures;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;
use rtCamp\WPPrimitives\Contracts\Interfaces\Shareable;

/**
 * Registrable and Shareable fixture that records whether register_hooks() ran.
 */
final class ShareableRegistrable implements Registrable, Shareable {
	public static bool $registered = false;

	public function register_hooks(): void {
		self::$registered = true;
	}
}
