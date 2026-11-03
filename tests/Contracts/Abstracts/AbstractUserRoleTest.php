<?php
/**
 * AbstractUserRole tests.
 *
 * Integration tests against real WordPress (wp-env): asserts the versioned
 * role registration — capabilities applied, version stored, gating on the
 * stored version, and full removal.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractUserRole;
use rtCamp\WPPrimitives\Tests\TestCase;

final class AbstractUserRoleTest extends TestCase {

	private const SLUG        = 'wpf_test_role';
	private const VERSION_KEY = 'wpf_test_role_role_version';

	private function role(): AbstractUserRole {
		return new class() extends AbstractUserRole {
			public static function get_slug(): string {
				return 'wpf_test_role';
			}

			protected function get_display_name(): string {
				return 'WPF Test Role';
			}

			protected function get_capabilities(): array {
				return [
					'edit_posts'     => true,
					'manage_options' => false,
				];
			}

			protected function get_version(): int {
				return 1;
			}
		};
	}

	public function tear_down(): void {
		// Roles live in an in-memory singleton that isn't rolled back per test.
		remove_role( self::SLUG );
		delete_option( self::VERSION_KEY );

		parent::tear_down();
	}

	public function test_register_hooks_wires_admin_init(): void {
		$role = $this->role();
		$role->register_hooks();

		$this->assertNotFalse( has_action( 'admin_init', [ $role, 'maybe_update_role' ] ) );
	}

	public function test_maybe_update_role_registers_role_and_stores_version(): void {
		$this->role()->maybe_update_role();

		$role = get_role( self::SLUG );
		$this->assertNotNull( $role );
		$this->assertTrue( $role->has_cap( 'edit_posts' ) );
		$this->assertFalse( $role->has_cap( 'manage_options' ) );
		$this->assertSame( 1, (int) get_option( self::VERSION_KEY ) );
	}

	public function test_role_is_not_registered_when_stored_version_is_current(): void {
		update_option( self::VERSION_KEY, 5 );

		$this->role()->maybe_update_role();

		$this->assertNull( get_role( self::SLUG ) );
	}

	public function test_remove_role_deletes_role_and_version_option(): void {
		$role = $this->role();
		$role->maybe_update_role();
		$this->assertNotNull( get_role( self::SLUG ) );

		$role->remove_role();

		$this->assertNull( get_role( self::SLUG ) );
		$this->assertFalse( get_option( self::VERSION_KEY ) );
	}
}
