<?php
/**
 * Abstract Admin Page class.
 *
 * Provides a clean interface for registering WordPress admin menu pages.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;

/**
 * Class - AbstractAdminPage
 */
abstract class AbstractAdminPage implements Registrable {
	/**
	 * Get the menu slug (page identifier).
	 *
	 * @return non-empty-string
	 */
	abstract public static function get_slug(): string;

	/**
	 * Get the page title (shown in browser tab).
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
	 * Render the admin page content.
	 */
	abstract public function render(): void;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'register_page' ] );
	}

	/**
	 * Register the admin page.
	 */
	public function register_page(): void {
		$parent = $this->get_parent_slug();

		if ( $parent ) {
			add_submenu_page(
				$parent,
				$this->get_page_title(),
				$this->get_menu_title(),
				$this->get_capability(),
				static::get_slug(),
				[ $this, 'render' ],
				$this->get_position()
			);
		} else {
			add_menu_page(
				$this->get_page_title(),
				$this->get_menu_title(),
				$this->get_capability(),
				static::get_slug(),
				[ $this, 'render' ],
				$this->get_icon(),
				$this->get_position()
			);
		}
	}

	/**
	 * Get the parent menu slug.
	 *
	 * Return null for a top-level menu, or a slug string for a submenu.
	 * E.g. 'options-general.php' for Settings submenu.
	 *
	 * @return string|null
	 */
	protected function get_parent_slug(): ?string {
		return null;
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
	 * Get the menu icon (for top-level menus only).
	 *
	 * @return string Dashicon class or SVG URL.
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
