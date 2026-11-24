<?php
/**
 * Singleton fixture that calls get_instance() from its own constructor.
 *
 * Models a plugin Main class whose constructor assigns `static::$instance` first and then runs a Loader, where a
 * loaded class calls Main::get_instance() before Main's constructor returns.
 *
 * @package rtCamp\WPPrimitives\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Fixtures;

use rtCamp\WPPrimitives\Contracts\Traits\Singleton;

/**
 * Class - ReentrantSingleton
 */
class ReentrantSingleton {

	use Singleton;

	/**
	 * Number of times the constructor ran.
	 */
	public static int $construct_count = 0;

	/**
	 * Instance returned by get_instance() inside the constructor.
	 */
	public static ?self $seen_during_construction = null;

	/**
	 * Set to 'yes' before the constructor calls get_instance().
	 */
	public string $ready = 'no';

	protected function __construct() {
		static::$instance = $this;

		++self::$construct_count;

		if ( self::$construct_count > 3 ) {
			// Stops infinite recursion if get_instance() constructs again.
			throw new \RuntimeException( 'Singleton re-entered its own construction.' );
		}

		$this->ready = 'yes';

		// What a Loader-built class does from inside Main::__construct().
		self::$seen_during_construction = self::get_instance();
	}
}
