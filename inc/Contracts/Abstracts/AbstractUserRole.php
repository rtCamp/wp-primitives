<?php
/**
 * Abstract User Role class.
 *
 * Provides a versioned approach to registering custom user roles.
 * Uses an option in the database to track the version, so roles are only
 * re-registered when the definition changes.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;

/**
 * Class - AbstractUserRole
 */
abstract class AbstractUserRole implements Registrable {
	/**
	 * Get the role slug (identifier).
	 *
	 * @return non-empty-string
	 */
	abstract public static function get_slug(): string;

	/**
	 * Get the display name for the role.
	 *
	 * @return non-empty-string
	 */
	abstract protected function get_display_name(): string;

	/**
	 * Get the capabilities for this role.
	 *
	 * @return array<string, bool>
	 */
	abstract protected function get_capabilities(): array;

	/**
	 * Get the role version.
	 *
	 * Increment this number each time the role definition changes.
	 * The role will only be re-registered when this version exceeds
	 * the stored version in the database.
	 *
	 * @return positive-int
	 */
	abstract protected function get_version(): int;

	/**
	 * Get the option key used to store the role version.
	 *
	 * Override to customize the option name per-plugin.
	 *
	 * @return non-empty-string
	 */
	protected function get_version_option_key(): string {
		return static::get_slug() . '_role_version';
	}

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'maybe_update_role' ] );
	}

	/**
	 * Conditionally register/update the role if the version has changed.
	 */
	public function maybe_update_role(): void {
		$stored_version = (int) get_option( $this->get_version_option_key(), 0 );

		if ( $this->get_version() <= $stored_version ) {
			return;
		}

		$this->register_role();

		update_option( $this->get_version_option_key(), $this->get_version(), true );
	}

	/**
	 * Register or update the role.
	 *
	 * Removes the existing role first (if any) to ensure capabilities are refreshed.
	 */
	protected function register_role(): void {
		// Remove existing role to reset capabilities.
		remove_role( static::get_slug() );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.custom_role_add_role -- Framework abstraction.
		add_role(
			static::get_slug(),
			$this->get_display_name(),
			$this->get_capabilities()
		);
	}

	/**
	 * Remove the role entirely.
	 *
	 * Call this on plugin deactivation or uninstall.
	 */
	public function remove_role(): void {
		remove_role( static::get_slug() );
		delete_option( $this->get_version_option_key() );
	}
}
