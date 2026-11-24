<?php
/**
 * Abstract Taxonomy class.
 *
 * Provides a rich base for registering WordPress custom taxonomies.
 *
 * Usage:
 *
 *   class FooTaxonomy extends AbstractTaxonomy {
 *       public static function get_slug(): string         { return 'foo'; }
 *       public static function get_object_types(): array  { return [ 'post' ]; }
 *       public function get_singular_label(): string      { return 'Foo'; }
 *       public function get_plural_label(): string        { return 'Foos'; }
 *   }
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;

/**
 * Class - AbstractTaxonomy
 */
abstract class AbstractTaxonomy implements Registrable {
	/**
	 * Get the taxonomy slug.
	 *
	 * @return lowercase-string&non-empty-string
	 */
	abstract public static function get_slug(): string;

	/**
	 * Get the object types associated with the taxonomy.
	 *
	 * These are usually the post types.
	 *
	 * @return list<lowercase-string&non-empty-string>
	 */
	abstract public static function get_object_types(): array;

	/**
	 * Get the singular label (e.g. "Genre").
	 *
	 * @return non-empty-string
	 */
	abstract public function get_singular_label(): string;

	/**
	 * Get the plural label (e.g. "Genres").
	 *
	 * @return non-empty-string
	 */
	abstract public function get_plural_label(): string;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register' ] );
	}

	/**
	 * Register the taxonomy.
	 */
	public function register(): void {
		$this->register_taxonomy();
		$this->after_register();
	}

	/**
	 * Register the taxonomy with WordPress.
	 */
	public function register_taxonomy(): void {
		register_taxonomy( static::get_slug(), static::get_object_types(), $this->get_options() );
	}

	/**
	 * Runs after the taxonomy has been registered.
	 *
	 * Override to add any additional setup without touching the registration logic.
	 */
	public function after_register(): void {}

	/**
	 * Get the full args array passed to register_taxonomy().
	 *
	 * To change a single option, override its helper method, such as
	 * is_hierarchical() or get_labels(), or use get_custom_options().
	 *
	 * @return array<string, mixed>
	 */
	public function get_options(): array {
		$options = [
			'labels'            => $this->get_labels(),
			'hierarchical'      => $this->is_hierarchical(),
			'public'            => true,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_admin_column' => true,
			'show_in_rest'      => true,
			'query_var'         => true,
		];

		return array_merge( $options, $this->get_custom_options() );
	}

	/**
	 * Get extra options to merge into the register_taxonomy() args.
	 *
	 * Override to add or override any options not covered by the other
	 * helper methods.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_custom_options(): array {
		return [];
	}

	/**
	 * Get the taxonomy labels.
	 *
	 * Override to add more labels or customise as needed.
	 *
	 * @return array<string, string>
	 */
	public function get_labels(): array {
		return [
			'name'          => $this->get_plural_label(),
			'singular_name' => $this->get_singular_label(),
		];
	}

	/**
	 * Whether this taxonomy is hierarchical (like categories).
	 */
	public function is_hierarchical(): bool {
		return false;
	}
}
