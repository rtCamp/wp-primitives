<?php
/**
 * Singleton trait.
 *
 * Singletons are an ANTI-PATTERN. Use with caution and only when necessary.
 * In most cases, it's better to use dependency injection.
 *
 * Avoid using this trait in a class meant to be extended by multiple subclasses, as the static instance will be shared across them.
 *
 * @package rtCamp\WPPrimitives\Contracts\Traits
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Traits;

/**
 * Singleton trait.
 */
trait Singleton {
	/**
	 * The single instance of the class using this trait.
	 *
	 * @var ?static
	 */
	protected static $instance;

	/**
	 * Protected to prevent direct instantiation.
	 *
	 * When overloading, assign `static::$instance = $this;` as the first statement in the constructor to ensure `get_instance()` returns the correct object during construction.
	 */
	protected function __construct() {
		// To be implemented by the class using the trait.
	}

	/**
	 * Get the instance of the class.
	 *
	 * Before calling `get_instance()` within the constructor, ensure that `static::$instance` has been assigned.
	 * Do not call in a child class, to prevent the static $instance from being shared across different subclasses.
	 */
	public static function get_instance(): static {
		if ( ! isset( static::$instance ) ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Prevent the class from being cloned.
	 */
	final public function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				// translators: %s: Class name.
				esc_html__( 'The %s class should not be cloned.', 'wp-primitives' ),
				esc_html( static::class ),
			),
			'1.0.0'
		);
	}

	/**
	 * Prevent the class from being deserialized.
	 */
	final public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf(
				// translators: %s: Class name.
				esc_html__( 'De-serializing instances of %s is not allowed.', 'wp-primitives' ),
				esc_html( static::class ),
			),
			'1.0.0'
		);
	}
}
