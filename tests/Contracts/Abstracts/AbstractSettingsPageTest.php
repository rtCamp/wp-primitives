<?php
/**
 * AbstractSettingsPage tests.
 *
 * Integration tests against real WordPress (wp-env): asserts the three hooks
 * are wired, settings register with the Settings API, the page is added as a
 * submenu under its parent, and the get_menu_slug() seam redirects the slug
 * registered with the admin menu.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractSettingsPage;
use rtCamp\WPPrimitives\Tests\TestCase;

final class AbstractSettingsPageTest extends TestCase {

	private const SLUG   = 'wpf-test-settings';
	private const OPTION = 'wpf_test_option';

	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$GLOBALS['menu']             = [];
		$GLOBALS['submenu']          = [];
		$GLOBALS['admin_page_hooks'] = [];
	}

	public function tear_down(): void {
		unregister_setting( self::SLUG, self::OPTION );

		parent::tear_down();
	}

	private function settings_page(): AbstractSettingsPage {
		return new class() extends AbstractSettingsPage {
			public static function get_slug(): string {
				return 'wpf-test-settings';
			}

			protected function get_page_title(): string {
				return 'WPF Settings';
			}

			protected function get_menu_title(): string {
				return 'WPF Settings';
			}

			protected function get_settings(): array {
				return [
					'wpf_test_option' => [
						'type'    => 'string',
						'default' => 'x',
					],
				];
			}

			public function render(): void {
				echo 'form';
			}
		};
	}

	public function test_register_hooks_wires_menu_settings_and_rest(): void {
		$page = $this->settings_page();
		$page->register_hooks();

		$this->assertNotFalse( has_action( 'admin_menu', [ $page, 'register_page' ] ) );
		$this->assertNotFalse( has_action( 'admin_init', [ $page, 'register_settings' ] ) );
		$this->assertNotFalse( has_action( 'rest_api_init', [ $page, 'register_settings' ] ) );
	}

	public function test_register_settings_registers_the_setting(): void {
		$this->settings_page()->register_settings();

		$this->assertArrayHasKey( self::OPTION, get_registered_settings() );
	}

	public function test_register_hooks_aligns_the_save_capability_with_get_capability(): void {
		// options.php gates the save on option_page_capability_{group}, which
		// defaults to manage_options. A page that lowers get_capability() would
		// otherwise render for a user who then silently fails to save.
		$page = new class() extends AbstractSettingsPage {
			public static function get_slug(): string {
				return 'wpf-test-settings';
			}
			protected function get_capability(): string {
				return 'edit_posts';
			}
			protected function get_page_title(): string {
				return 'WPF Settings';
			}
			protected function get_menu_title(): string {
				return 'WPF Settings';
			}
			protected function get_settings(): array {
				return [];
			}
			public function render(): void {}
		};

		$page->register_hooks();

		$this->assertSame(
			'edit_posts',
			apply_filters( 'option_page_capability_' . self::SLUG, 'manage_options' )
		);
	}

	public function test_save_capability_filter_is_scoped_to_this_pages_option_group(): void {
		$this->settings_page()->register_hooks();

		// Another plugin's option group must be left untouched.
		$this->assertSame(
			'manage_options',
			apply_filters( 'option_page_capability_some_other_group', 'manage_options' )
		);
	}

	public function test_register_page_adds_submenu_under_settings(): void {
		$this->settings_page()->register_page();

		$slugs = wp_list_pluck( $GLOBALS['submenu']['options-general.php'] ?? [], 2 );
		$this->assertContains( self::SLUG, $slugs );
	}

	public function test_register_page_uses_an_overridden_menu_slug(): void {
		$page = new class() extends AbstractSettingsPage {
			public static function get_slug(): string {
				return 'wpf-test-settings';
			}
			protected function get_menu_slug(): string {
				return 'wpf-test-overridden-slug';
			}
			protected function get_page_title(): string {
				return 'WPF Settings';
			}
			protected function get_menu_title(): string {
				return 'WPF Settings';
			}
			protected function get_settings(): array {
				return [];
			}
			public function render(): void {}
		};

		$page->register_page();

		// get_menu_slug() overrides the static get_slug() as the admin-menu slug.
		$slugs = wp_list_pluck( $GLOBALS['submenu']['options-general.php'] ?? [], 2 );
		$this->assertContains( 'wpf-test-overridden-slug', $slugs );
		$this->assertNotContains( self::SLUG, $slugs );
	}

	public function test_register_page_adds_a_top_level_menu_when_parent_is_null(): void {
		$page = new class() extends AbstractSettingsPage {
			public static function get_slug(): string {
				return 'wpf-test-top-settings';
			}
			protected function get_page_title(): string {
				return 'WPF Top';
			}
			protected function get_menu_title(): string {
				return 'WPF Top';
			}
			protected function get_settings(): array {
				return [];
			}
			public function render(): void {}
			protected function get_parent_slug(): ?string {
				return null;
			}
		};

		$page->register_page();

		$this->assertArrayHasKey( 'wpf-test-top-settings', $GLOBALS['admin_page_hooks'] );
	}

	public function test_render_outputs_page_markup(): void {
		ob_start();
		$this->settings_page()->render();

		$this->assertSame( 'form', (string) ob_get_clean() );
	}
}
