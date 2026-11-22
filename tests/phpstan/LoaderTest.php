<?php
/**
 * PHPStan type tests for the Loader trait.
 *
 * Analysed by PHPStan only; never executed.
 *
 * @package rtCamp\WPPrimitives\Tests\PHPStan
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\PHPStan;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;
use rtCamp\WPPrimitives\Contracts\Interfaces\Shareable;
use rtCamp\WPPrimitives\Contracts\Traits\Loader;
use function PHPStan\Testing\assertType;

/**
 * A shareable, registrable service.
 */
final class LoaderServiceFixture implements Registrable, Shareable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {}
}

/**
 * A minimal class using the Loader trait.
 */
final class LoaderFixture {

	use Loader;

	/**
	 * Load the fixture services.
	 */
	public function __construct() {
		$this->load( [ LoaderServiceFixture::class ] );
	}
}

$loader = new LoaderFixture();

assertType( LoaderServiceFixture::class, $loader->get_shared( LoaderServiceFixture::class ) );
