<?php
/**
 * Container.
 *
 * Simple container that stores object instances and retrieves them by class name.
 *
 * @package rtCamp\WPPrimitives
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives;

/**
 * Class - Container
 */
final class Container {
	/**
	 * Stored instances.
	 *
	 * @var array<class-string, object>
	 */
	private array $instances = [];

	/**
	 * Store an object in the container.
	 *
	 * @param class-string $id       The class name used as the key.
	 * @param object       $instance The object to store.
	 */
	public function set( string $id, object $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Retrieve an object from the container.
	 *
	 * @param class-string $id The class name.
	 *
	 * @return object The stored instance.
	 *
	 * @throws \RuntimeException If not found.
	 */
	public function get( string $id ): object {
		if ( ! isset( $this->instances[ $id ] ) ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Exception messages are not rendered to browser.
			throw new \RuntimeException(
				sprintf( 'Instance "%s" is not registered in the container.', $id )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		return $this->instances[ $id ];
	}

	/**
	 * Check if an instance exists in the container.
	 *
	 * @param class-string $id The class name.
	 */
	public function has( string $id ): bool {
		return isset( $this->instances[ $id ] );
	}
}
