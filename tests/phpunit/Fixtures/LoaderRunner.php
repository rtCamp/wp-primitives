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
 * Exposes the Loader trait's protected load() as run().
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
