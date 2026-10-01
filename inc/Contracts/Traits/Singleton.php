<?php
/**
 * Singleton trait.
 *
 * Singletons are an ANTI-PATTERN. Use with caution and only when necessary.
 * In most cases, it's better to use dependency injection.
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
	 * Protected on purpose: it is part of the trait's contract. A singleton whose
	 * constructor does real work (a plugin/theme `Main` running a Loader) should
	 * assign `static::$instance = $this;` as its first statement, so anything
	 * built during that work can call `get_instance()` re-entrantly and receive
	 * this same object instead of triggering a second construction.
	 *
	 * Limitation: a trait `static` property is one storage slot shared by a class
	 * and its subclasses. Do not extend a class that uses this trait and call
	 * `get_instance()` on the child — whichever side is resolved first occupies
	 * the shared slot for both.
	 *
	 * @var ?static
	 */
	protected static $instance;

	/**
	 * The single constructor.
	 *
	 * It's protected to prevent direct instantiation.
	 */
	protected function __construct() {
		// To be implemented by the class using the trait.
	}

	/**
	 * Get the instance of the class.
	 *
	 * The instance is stored once the constructor returns. If the constructor
	 * does work that can call back into `get_instance()`, assign
	 * `static::$instance = $this;` as its first statement (see the property
	 * docblock) — otherwise the re-entrant call finds nothing stored yet and
	 * constructs a second instance, recursing until the stack blows.
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
