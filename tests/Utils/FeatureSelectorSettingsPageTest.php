<?php
/**
 * FeatureSelectorSettingsPage utility tests.
 *
 * @package rtCamp\WPPrimitives\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Utils;

use rtCamp\WPPrimitives\Tests\TestCase;
use rtCamp\WPPrimitives\Utils\FeatureSelector;
use rtCamp\WPPrimitives\Utils\FeatureSelectorSettingsPage;

/**
 * Tests for FeatureSelectorSettingsPage.
 *
 * Integration tests against real WordPress (wp-env): hooks, the registered
 * setting, the admin submenu, and settings sections/fields are read back from
 * WordPress' own registries rather than stubs.
 */
final class FeatureSelectorSettingsPageTest extends TestCase {

	/**
	 * Selector backing the page under test (context `my-plugin`).
	 */
	private FeatureSelector $selector;

	/**
	 * Page under test.
	 */
	private FeatureSelectorSettingsPage $page;

	public function set_up(): void {
		parent::set_up();

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/template.php';

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$GLOBALS['menu']                 = [];
		$GLOBALS['submenu']              = [];
		$GLOBALS['admin_page_hooks']     = [];
		$GLOBALS['wp_settings_sections'] = [];
		$GLOBALS['wp_settings_fields']   = [];

		$this->selector = new FeatureSelector( 'my-plugin' );
		$this->page     = $this->make_page( $this->selector );
	}

	public function tear_down(): void {
		// Several tests simulate an options.php form save by setting this
		// superglobal. Clear it here so a failing assertion mid-test cannot
		// leak the form-save context into whichever test runs next.
		unset( $_POST['option_page'] );

		parent::tear_down();
	}

	/**
	 * Instantiate a concrete FeatureSelectorSettingsPage backed by $selector.
	 *
	 * FeatureSelectorSettingsPage is abstract (get_selector() must be provided by
	 * the consumer), so tests use an anonymous class to supply the implementation.
	 *
	 * @param FeatureSelector $selector Selector to back the page.
	 */
	private function make_page( FeatureSelector $selector ): FeatureSelectorSettingsPage {
		return new class( $selector ) extends FeatureSelectorSettingsPage {
			public function __construct( private readonly FeatureSelector $sel ) {}

			protected function get_selector(): FeatureSelector {
				return $this->sel;
			}
		};
	}

	/**
	 * Find the options-general.php submenu row for a given menu slug.
	 *
	 * Submenu rows are `[ menu_title, capability, menu_slug, page_title ]`.
	 *
	 * @param string $slug Menu slug to look up.
	 *
	 * @return array<int, string>|null Matching row, or null.
	 */
	private function submenu_row( string $slug ): ?array {
		foreach ( $GLOBALS['submenu']['options-general.php'] ?? [] as $row ) {
			if ( $slug === $row[2] ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Capture render_field() output for a registered flag.
	 */
	private function render_field_output( string $slug ): string {
		ob_start();
		$this->page->render_field( $this->selector->get_features()[ $slug ] );

		return (string) ob_get_clean();
	}

	// --- hooks ---------------------------------------------------------------

	public function test_register_hooks_keeps_field_registration_off_rest_api_init(): void {
		$this->page->register_hooks();

		$this->assertNotFalse( has_action( 'admin_menu', [ $this->page, 'register_page' ] ) );
		$this->assertNotFalse( has_action( 'admin_init', [ $this->page, 'register_settings' ] ) );
		$this->assertNotFalse( has_action( 'rest_api_init', [ $this->page, 'register_settings' ] ) );

		// add_settings_section/field are undefined during REST requests —
		// register_fields must be wired to admin_init only.
		$this->assertNotFalse( has_action( 'admin_init', [ $this->page, 'register_fields' ] ) );
		$this->assertFalse( has_action( 'rest_api_init', [ $this->page, 'register_fields' ] ) );
	}

	// --- page registration -----------------------------------------------------

	public function test_register_page_uses_context_derived_slug(): void {
		$this->page->register_page();

		$row = $this->submenu_row( 'my-plugin-features' );

		$this->assertNotNull( $row );
		$this->assertSame( 'manage_options', $row[1] );
		$this->assertSame( 'My Plugin Features', $row[3] );
	}

	public function test_menu_slug_slugifies_a_non_slug_context(): void {
		$page = $this->make_page( new FeatureSelector( 'My Plugin v2.0' ) );

		$page->register_page();

		$this->assertNotNull( $this->submenu_row( 'my-plugin-v2-0-features' ) );
	}

	public function test_empty_context_page_slug_is_bare_features(): void {
		$page = $this->make_page( new FeatureSelector() );

		$page->register_page();

		$row = $this->submenu_row( 'features' );

		$this->assertNotNull( $row );
		$this->assertSame( 'Features', $row[3] );
	}

	// --- settings registration ---------------------------------------------------

	public function test_register_settings_registers_a_single_array_setting(): void {
		$this->selector->register( [ 'dark-mode', 'beta-search' ] );

		$this->page->register_settings();

		$registered = get_registered_settings();

		// All flags share one option: the page registers exactly one setting and
		// no per-flag settings.
		$this->assertArrayHasKey( 'my_plugin_features', $registered );
		$this->assertArrayNotHasKey( 'my_plugin_feature_dark_mode', $registered );

		$this->assertSame( 'array', $registered['my_plugin_features']['type'] );
		$this->assertSame( [], $registered['my_plugin_features']['default'] );

		// On a genuine options.php form save the sanitize callback rebuilds the
		// stored array: a checked flag becomes true, an unchecked (absent) flag
		// becomes false. Simulate that form-save context.
		$_POST['option_page'] = 'my_plugin_features';

		$this->assertSame(
			[
				'dark-mode'   => true,
				'beta-search' => false,
			],
			$this->page->sanitize_settings( [ 'dark-mode' => '1' ] )
		);
	}

	public function test_saving_preserves_a_locked_flags_stored_value_and_still_renders_it(): void {
		if ( ! defined( 'MY_PLUGIN_FEATURE_REG_LOCKED' ) ) {
			define( 'MY_PLUGIN_FEATURE_REG_LOCKED', true );
		}

		// The locked flag was turned on and stored before the constant existed.
		update_option( 'my_plugin_features', [ 'reg-locked' => true ] );

		$this->selector->register( [ 'reg-locked', 'free-flag' ] );

		$this->page->register_fields();

		// Saving the page (nothing submitted) rebuilds the stored array: the free
		// flag follows the empty form and turns off, but the locked flag carries
		// no field, so its stored value is preserved rather than reset to false.
		$_POST['option_page'] = 'my_plugin_features';
		$sanitized            = $this->page->sanitize_settings( [] );

		$this->assertTrue( $sanitized['reg-locked'] );
		$this->assertFalse( $sanitized['free-flag'] );

		// It still renders as a (disabled) field so the lock stays visible.
		$this->assertArrayHasKey(
			'reg-locked',
			$GLOBALS['wp_settings_fields']['my-plugin-features']['my_plugin_features_section']
		);
	}

	public function test_programmatic_enable_after_register_settings_does_not_reset_other_flags(): void {
		// Regression: sanitize_settings() is a sanitize_option_{key} filter that
		// fires on EVERY write of the shared option. register_settings() installs
		// it (as it would on any admin/REST request), then a programmatic enable()
		// writes the option. That write must NOT route through the form rebuild and
		// reset the other default-on, never-stored flags to false.
		$this->selector->register( [ 'feature-a', 'feature-b', 'feature-c' ] );
		$this->page->register_settings();

		$this->selector->enable( 'feature-a' );

		$this->assertTrue( $this->selector->is_enabled( 'feature-a' ) );
		$this->assertTrue( $this->selector->is_enabled( 'feature-b' ), 'a default-on flag must stay on after an unrelated programmatic enable()' );
		$this->assertTrue( $this->selector->is_enabled( 'feature-c' ), 'a default-on flag must stay on after an unrelated programmatic enable()' );
	}

	public function test_flags_registered_after_hooks_still_appear(): void {
		// Consumers register flags at plugins_loaded/init — after register_hooks()
		// but before admin_init fires register_fields(). The field list must be
		// read lazily at that point, not captured when the hooks were wired.
		$this->page->register_hooks();
		$this->selector->register( [ 'late-flag' ] );

		$this->page->register_fields();

		$this->assertArrayHasKey(
			'late-flag',
			$GLOBALS['wp_settings_fields']['my-plugin-features']['my_plugin_features_section']
		);
	}

	public function test_register_fields_adds_section_and_one_field_per_flag(): void {
		$this->selector->register(
			[
				'dark-mode' => [
					'name'        => 'Dark Mode',
					'description' => 'Switch the UI to dark.',
				],
				'beta-search',
			]
		);

		$this->page->register_fields();

		$this->assertArrayHasKey(
			'my_plugin_features_section',
			$GLOBALS['wp_settings_sections']['my-plugin-features']
		);

		$fields = $GLOBALS['wp_settings_fields']['my-plugin-features']['my_plugin_features_section'];

		$this->assertSame( [ 'dark-mode', 'beta-search' ], array_keys( $fields ) );
		$this->assertSame( 'Dark Mode', $fields['dark-mode']['title'] );
		$this->assertSame( 'Switch the UI to dark.', $fields['dark-mode']['args']['description'] );
	}

	// --- rendering -----------------------------------------------------------------

	public function test_render_field_outputs_named_checkbox_checked_when_enabled(): void {
		$this->selector->register( [ 'dark-mode' ] );
		$this->selector->enable( 'dark-mode' );

		$output = $this->render_field_output( 'dark-mode' );

		$this->assertStringContainsString( 'name="my_plugin_features[dark-mode]"', $output );
		$this->assertStringContainsString( 'checked', $output );
		$this->assertStringNotContainsString( 'disabled', $output );
	}

	public function test_render_field_omits_description_paragraph_when_empty(): void {
		$this->selector->register( [ 'dark-mode' ] );

		$this->assertStringNotContainsString( 'class="description"', $this->render_field_output( 'dark-mode' ) );
	}

	public function test_render_field_prints_description_when_present(): void {
		$this->selector->register( [ 'dark-mode' => [ 'description' => 'Switch the UI to dark.' ] ] );
		$this->selector->disable( 'dark-mode' ); // Default is on; force off so "unchecked" is meaningful.

		$output = $this->render_field_output( 'dark-mode' );

		$this->assertStringContainsString( 'Switch the UI to dark.', $output );
		$this->assertStringNotContainsString( 'checked', $output );
	}

	public function test_render_field_locks_checkbox_when_constant_defined(): void {
		if ( ! defined( 'MY_PLUGIN_FEATURE_LOCKED_FLAG' ) ) {
			define( 'MY_PLUGIN_FEATURE_LOCKED_FLAG', true );
		}

		$this->selector->register( [ 'locked-flag' ] );

		$output = $this->render_field_output( 'locked-flag' );

		$this->assertStringContainsString( 'disabled', $output );
		$this->assertStringContainsString( 'checked', $output ); // Constant value is reflected.
		$this->assertStringContainsString( 'MY_PLUGIN_FEATURE_LOCKED_FLAG', $output );
	}

	public function test_render_outputs_form_with_group_and_fields(): void {
		$this->selector->register( [ 'dark-mode' ] );
		$this->page->register_fields();

		ob_start();
		$this->page->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'action="options.php"', $output );
		$this->assertStringContainsString( 'name="my_plugin_features[dark-mode]"', $output );
		$this->assertStringContainsString( 'type="submit"', $output );
		// settings_fields() emits the option-group hidden field.
		$this->assertStringContainsString( 'option_page', $output );
	}

	public function test_render_bails_for_user_without_capability(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		ob_start();
		$this->page->render();

		$this->assertSame( '', ob_get_clean() );
	}
}
