<?php
/**
 * Interface for Registrable classes whose registration is conditional.
 *
 * Implement this when a class should only register its hooks under certain
 * runtime conditions — e.g. behind a feature flag, when WP-CLI is active,
 * or only in admin requests. The Loader skips register_hooks() entirely
 * when can_register() returns false.
 *
 * Classes that always register can keep implementing the plain Registrable
 * interface instead; this one is strictly opt-in.
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
