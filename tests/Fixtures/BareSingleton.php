<?php
/**
 * Singleton fixture that does NOT override the trait constructor.
 *
 * Exercises the trait's default protected __construct (SingletonExample
 * overrides it, so the default body would otherwise never run).
 *
 * @package rtCamp\WPPrimitives\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Fixtures;

use rtCamp\WPPrimitives\Contracts\Traits\Singleton;

/**
 * Class - BareSingleton
 */
class BareSingleton {
	use Singleton;
}
