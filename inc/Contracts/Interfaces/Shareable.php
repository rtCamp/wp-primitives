<?php
/**
 * Interface for Shareable classes.
 *
 * Classes with this interface are cached by {@see rtCamp\WPPrimitives\Contracts\Traits\Loader} and can be retrieved
 * for reuse with ::get_shared().
 *
 * Shareable is an ANTI-PATTERN that adds hidden shared state, like Singleton. It should only be used when code needs to retrieve the same instance after loading.
 *
 * @package rtCamp\WPPrimitives\Contracts\Interfaces
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Interfaces;

/**
 * Interface - Shareable
 */
interface Shareable {
	// noop.
}
