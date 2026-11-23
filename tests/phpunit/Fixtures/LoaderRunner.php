<?php
/**
 * LoaderTest fixture: LoaderRunner.
 *
 * @package rtCamp\WPPrimitives\Tests\Fixtures
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Fixtures;

use rtCamp\WPPrimitives\Contracts\Traits\Loader;

/**
 * Public wrapper around the Loader trait so tests can call load() and
 * get_shared() from outside the class hierarchy.
 */
final class LoaderRunner {
	use Loader;

	/**
	 * Load classes through the trait.
	 *
	 * @param class-string[] $classes Classes to load.
	 */
	public function run( array $classes ): void {
		$this->load( $classes );
	}
}
