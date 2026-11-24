<?php
/**
 * Interface for Registrable classes whose registration is conditional.
 *
 * Implement this when a class should only register its hooks under certain runtime conditions, such as feature flags,
 * when WP-CLI is active, or an admin request.
 *
 * @package rtCamp\WPPrimitives\Contracts\Interfaces
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Interfaces;

/**
 * Interface - ConditionallyRegistrable
 */
interface ConditionallyRegistrable extends Registrable {
	/**
	 * Whether this class should register its hooks.
	 *
	 * Return false to skip register_hooks() entirely.
	 */
	public function can_register(): bool;
}
