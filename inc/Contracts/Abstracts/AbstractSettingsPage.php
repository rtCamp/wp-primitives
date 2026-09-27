<?php
/**
 * Abstract Settings Page class.
 *
 * Provides a clean interface for registering a WordPress settings page
 * with the Settings API. Handles menu registration, settings registration,
 * and rendering in a single class.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;

/**
 * Class - AbstractSettingsPage
 */
abstract class AbstractSettingsPage implements Registrable {
	/**
	 * Get the menu/page slug.
	 *
	 * @return non-empty-string
	 */
	abstract public static function get_slug(): string;

	/**
	 * Get the page title (shown in the browser tab).
	 *
	 * @return string
	 */
	abstract protected function get_page_title(): string;

	/**
	 * Get the menu title (shown in the admin sidebar).
	 *
	 * @return string
	 */
	abstract protected function get_menu_title(): string;

	/**
	 * Get the settings definitions.
	 *
	 * Each key is the option name, and the value is the array of args
	 * passed to register_setting().
	 *
	 * @return array<string, array<string, mixed>>
	 */
	abstract protected function get_settings(): array;

	/**
	 * Render the settings page output.
	 */
	abstract public function render(): void;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );

		// The menu and render() are gated by get_capability(), but options.php gates
		// the *save* on option_page_capability_{group}, which defaults to
		// manage_options. Align them so a lowered get_capability() can actually save,
		// instead of rendering a page that silently fails to persist.
		add_filter(
			'option_page_capability_' . $this->get_option_group(),
			fn (): string => $this->get_capability()
		);
	}

	/**
	 * Register the settings page in the admin menu.
	 */
	public function register_page(): void {
		$parent = $this->get_parent_slug();

		if ( $parent ) {
			add_submenu_page(
				$parent,
				$this->get_page_title(),
				$this->get_menu_title(),
				$this->get_capability(),
				$this->get_menu_slug(),
				[ $this, 'render' ],
				$this->get_position()
			);
		} else {
			add_menu_page(
				$this->get_page_title(),
				$this->get_menu_title(),
				$this->get_capability(),
				$this->get_menu_slug(),
				[ $this, 'render' ],
				$this->get_icon(),
				$this->get_position()
			);
		}
	}

	/**
	 * Get the menu slug registered with the admin menu.
	 *
	 * Defaults to the page slug. Override to derive the menu slug from
	 * instance state (static get_slug() cannot).
	 *
	 * @return non-empty-string
	 */
	protected function get_menu_slug(): string {
		return static::get_slug();
	}

	/**
	 * Register the settings with the Settings API.
	 *
	 * Iterates over get_settings() and calls register_setting() for each.
	 */
	public function register_settings(): void {
		foreach ( $this->get_settings() as $option_name => $args ) {
			register_setting( $this->get_option_group(), $option_name, $args );
		}
	}

	/**
	 * Get the option group for register_setting().
	 *
	 * Defaults to the page slug. Override if needed.
	 *
	 * @return non-empty-string
	 */
	protected function get_option_group(): string {
		return static::get_slug();
	}

	/**
	 * Get the parent menu slug.
	 *
	 * Return null for a top-level menu page, or a slug for a submenu.
	 * Common values: 'options-general.php', 'tools.php', 'edit.php'.
	 *
	 * @return string|null
	 */
	protected function get_parent_slug(): ?string {
		return 'options-general.php';
	}

	/**
	 * Get the required capability to access this page.
	 *
	 * @return string
	 */
	protected function get_capability(): string {
		return 'manage_options';
	}

	/**
	 * Get the menu icon (top-level menus only).
	 *
	 * @return string Dashicon class or SVG data URI.
	 */
	protected function get_icon(): string {
		return '';
	}

	/**
	 * Get the menu position.
	 *
	 * @return int|null
	 */
	protected function get_position(): ?int {
		return null;
	}
}
