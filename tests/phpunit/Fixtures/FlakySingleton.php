<?php
/**
 * Singleton fixture whose constructor throws while FlakySingleton::$fail is true.
 *
 * @package rtCamp\WPPrimitives\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Fixtures;

use rtCamp\WPPrimitives\Contracts\Traits\Singleton;

/**
 * Class - FlakySingleton
 */
class FlakySingleton {

	use Singleton;

	/**
	 * Throw on construction while true.
	 */
	public static bool $fail = true;

	protected function __construct() {
		if ( self::$fail ) {
			throw new \RuntimeException( 'construction failed' );
		}
	}
}
