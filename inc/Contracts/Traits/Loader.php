<?php
/**
 * Loader trait.
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
	 * @var ?Container
	 */
	private ?Container $container = null;

	/**
	 * Instantiate each class.
	 *
	 * Creates a new instance of each class, registers hooks for each Registrable, and caches each Shareable instance in a per-host Container.
	 *
	 * @param class-string[] $classes Classes to load.
	 */
	protected function load( array $classes ): void {
		$this->container = new Container();
		$seen            = [];

		foreach ( $classes as $class_name ) {
			// Skip duplicates so their hooks aren't triggered multiple times.
			if ( isset( $seen[ $class_name ] ) ) {
				continue;
			}
			$seen[ $class_name ] = true;

			$instance = new $class_name();

			// Register hooks if the instance is Registrable.
			if ( $instance instanceof Registrable ) {
				if ( ! $instance instanceof ConditionallyRegistrable || $instance->can_register() ) {
					$instance->register_hooks();
				}
			}

			// Cache the instance if it is Shareable.
			if ( $instance instanceof Shareable ) {
				$this->container->set( $class_name, $instance );
			}
		}
	}

	/**
	 * Fetch a shared instance populated during load.
	 *
	 * @template T of object
	 *
	 * @param string $id Class name.
	 * @phpstan-param class-string<T> $id
	 *
	 * @return object The shared instance.
	 * @phpstan-return T
	 *
	 * @throws \RuntimeException If load() has not been called or the class was not registered as Shareable.
	 */
	public function get_shared( string $id ): object {
		if ( null === $this->container ) {
			throw new \RuntimeException( 'Cannot call get_shared() before load() has been called.' );
		}

		/**
		 * The type is enforced via ::load()
		 *
		 * @phpstan-var T $instance
		 */
		$instance = $this->container->get( $id );

		return $instance;
	}
}
