<?php
/**
 * Loader trait.
 *
 * Loads a list of classes: instantiates each, registers hooks for any
 * Registrable, and caches any Shareable instance in a per-host Container.
 * Plain classes (neither Registrable nor Shareable) are just instantiated.
 *
 * @package rtCamp\WPPrimitives\Contracts\Traits
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Traits;

use rtCamp\WPPrimitives\Container;
use rtCamp\WPPrimitives\Contracts\Interfaces\ConditionallyRegistrable;
use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;
use rtCamp\WPPrimitives\Contracts\Interfaces\Shareable;

/**
 * Loader trait.
 */
trait Loader {
	/**
	 * Shared instances populated during load.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Instantiate each class; register hooks if Registrable; cache if Shareable.
	 *
	 * The Registrable and Shareable checks are independent — a class may be
	 * both, in which case its hooks are registered and its instance is cached.
	 *
	 * @param class-string[] $classes Classes to load.
	 */
	protected function load( array $classes ): void {
		$this->container = new Container();
		$seen            = [];

		foreach ( $classes as $class_name ) {
			// Skip duplicates: a class listed twice would otherwise be instantiated
			// twice and register its hooks on two separate instances (so the hook
			// body runs twice), while the Shareable cache would keep only the last.
			if ( isset( $seen[ $class_name ] ) ) {
				continue;
			}
			$seen[ $class_name ] = true;

			$instance = new $class_name();

			if ( $instance instanceof Registrable ) {
				if ( ! $instance instanceof ConditionallyRegistrable || $instance->can_register() ) {
					$instance->register_hooks();
				}
			}

			if ( $instance instanceof Shareable ) {
				$this->container->set( $class_name, $instance );
			}
		}
	}

	/**
	 * Fetch a shared instance populated during load.
	 *
	 * @template T of object
	 * @param string $id Class name.
	 * @phpstan-param class-string<T> $id
	 * @return object
	 * @phpstan-return T
	 * @throws \RuntimeException If load() has not been called or the class was not registered as Shareable.
	 */
	public function get_shared( string $id ): object {
		if ( ! isset( $this->container ) ) {
			throw new \RuntimeException( 'Cannot call get_shared() before load() has been called.' );
		}

		return $this->container->get( $id );
	}
}
