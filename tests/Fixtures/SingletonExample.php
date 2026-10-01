<?php
/**
 * Singleton fixture class for SingletonTest.
 *
 * @package rtCamp\WPPrimitives\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Fixtures;

use rtCamp\WPPrimitives\Contracts\Traits\Singleton;

/**
 * Class - SingletonExample
 */
class SingletonExample {
	use Singleton;

	/**
	 * Tracks how many times the constructor ran — a true singleton runs it once.
	 */
	public static int $construct_count = 0;

	protected function __construct() {
		++self::$construct_count;
	}
}
