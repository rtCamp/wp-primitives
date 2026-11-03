<?php
/**
 * Abstract Module class.
 *
 * A module groups related Registrable classes together, acting as an
 * intermediary between the plugin's Main class and individual services.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;
use rtCamp\WPPrimitives\Contracts\Traits\Loader;

/**
 * Class - AbstractModule
 */
abstract class AbstractModule implements Registrable {

	use Loader;

	/**
	 * Get the Registrable class-strings this module manages.
	 *
	 * @return class-string<Registrable>[]
	 */
	abstract protected function get_classes(): array;

	/**
	 * {@inheritDoc}
	 *
	 * Instantiates each class from get_classes() and calls register_hooks().
	 */
	public function register_hooks(): void {
		$this->load( $this->get_classes() );
	}
}
