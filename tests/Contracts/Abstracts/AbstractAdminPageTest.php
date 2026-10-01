<?php
/**
 * AbstractAdminPage tests.
 *
 * Integration tests against real WordPress (wp-env): asserts top-level and
 * submenu pages are registered with WordPress' admin-menu globals. add_menu_page()
 * lives in wp-admin/includes/plugin.php and checks the current user's capability,
 * so both are set up here.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractAdminPage;
use rtCamp\WPPrimitives\Tests\TestCase;

final class AbstractAdminPageTest extends TestCase {

	private const SLUG = 'wpf-test-admin';

	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		// add_menu_page()/add_submenu_page() gate on the current user's capability.
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		// Start from clean admin-menu globals.
		$GLOBALS['menu']             = [];
		$GLOBALS['submenu']          = [];
		$GLOBALS['admin_page_hooks'] = [];
	}

	private function top_level_page(): AbstractAdminPage {
		return new class() extends AbstractAdminPage {
			public static function get_slug(): string {
				return 'wpf-test-admin';
			}

			protected function get_page_title(): string {
				return 'WPF Admin';
			}

			protected function get_menu_title(): string {
				return 'WPF';
			}

			public function render(): void {
				echo 'content';
			}
		};
	}

	public function test_register_hooks_wires_admin_menu(): void {
		$page = $this->top_level_page();
		$page->register_hooks();

		$this->assertNotFalse( has_action( 'admin_menu', [ $page, 'register_page' ] ) );
	}

	public function test_register_page_adds_a_top_level_menu(): void {
		$this->top_level_page()->register_page();

		$this->assertArrayHasKey( self::SLUG, $GLOBALS['admin_page_hooks'] );
	}

	public function test_register_page_adds_a_submenu_under_its_parent(): void {
		$page = new class() extends AbstractAdminPage {
			public static function get_slug(): string {
				return 'wpf-test-subpage';
			}

			protected function get_page_title(): string {
				return 'WPF Sub';
			}

			protected function get_menu_title(): string {
				return 'WPF Sub';
			}

			public function render(): void {}

			protected function get_parent_slug(): ?string {
				return 'options-general.php';
			}
		};

		$page->register_page();

		$slugs = wp_list_pluck( $GLOBALS['submenu']['options-general.php'] ?? [], 2 );
		$this->assertContains( 'wpf-test-subpage', $slugs );
	}
}
