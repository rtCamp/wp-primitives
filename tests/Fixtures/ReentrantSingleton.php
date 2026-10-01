<?php
/**
 * Singleton fixture using the documented heavy-constructor pattern.
 *
 * Models the supported consumer shape: a theme/plugin Main whose constructor
 * assigns `static::$instance = $this;` first, then runs a Loader — and a class
 * built by that Loader calls Main::get_instance() while Main is still
 * constructing. The early assignment is the trait's documented re-entrancy
 * guard; this fixture pins that the property stays protected and assignable.
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
	 * How many times the constructor ran — must stay at 1.
	 */
	public static int $construct_count = 0;

	/**
	 * What the re-entrant get_instance() call returned mid-construction.
	 */
	public static ?self $seen_during_construction = null;

	/**
	 * Set before the re-entrant call, so the test can prove the re-entrant
	 * caller observed the constructor's progress on the same object.
	 */
	public string $ready = 'no';

	protected function __construct() {
		// The documented pattern: publish first, then do the real work.
		static::$instance = $this;

		++self::$construct_count;

		if ( self::$construct_count > 3 ) {
			// A regression would recurse forever; fail loudly instead.
			throw new \RuntimeException( 'Singleton re-entered its own construction.' );
		}

		$this->ready = 'yes';

		// What a Loader-built class does from inside Main::__construct().
		self::$seen_during_construction = self::get_instance();
	}
}
