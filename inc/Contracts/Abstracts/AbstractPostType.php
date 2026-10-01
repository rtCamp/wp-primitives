<?php
/**
 * Abstract Post Type class.
 *
 * Provides a rich base for registering WordPress custom post types.
 * Subclasses declare the slug, labels and icon; the base class builds
 * the full options array and handles registration, including optional
 * taxonomy association and a post-registration hook.
 *
 * Usage:
 *
 *   class FooPostType extends AbstractPostType {
 *       public static function get_slug(): string    { return 'foo'; }
 *       public function get_singular_label(): string { return 'Foo'; }
 *       public function get_plural_label(): string   { return 'Foos'; }
 *       public function get_menu_icon(): string      { return 'dashicons-admin-post'; }
 *   }
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;

/**
 * Class - AbstractPostType
 */
abstract class AbstractPostType implements Registrable {

	/**
	 * Get the post type slug.
	 *
	 * @return lowercase-string&non-empty-string
	 */
	abstract public static function get_slug(): string;

	/**
	 * Get the singular label (e.g. "Article").
	 *
	 * @return non-empty-string
	 */
	abstract public function get_singular_label(): string;

	/**
	 * Get the plural label (e.g. "Articles").
	 *
	 * @return non-empty-string
	 */
	abstract public function get_plural_label(): string;

	/**
	 * Get the menu icon.
	 *
	 * Can be a Dashicons class ('dashicons-admin-post'), a base64-encoded
	 * SVG, a URL, or 'none' to leave it empty for CSS.
	 *
	 * @see https://developer.wordpress.org/resource/dashicons/
	 *
	 * @return string
	 */
	abstract public function get_menu_icon(): string;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register' ] );
	}

	/**
	 * Register the post type and its taxonomies.
	 *
	 * Calls after_register() when done — override that for any
	 * additional setup that must run after the post type exists.
	 */
	public function register(): void {
		$this->register_post_type();
		$this->register_taxonomies();
		$this->after_register();
	}

	/**
	 * Register the post type with WordPress.
	 */
	public function register_post_type(): void {
		register_post_type( static::get_slug(), $this->get_options() ); // phpcs:ignore WordPress.NamingConventions.ValidPostTypeSlug.NotStringLiteral
	}

	/**
	 * Associate supported taxonomies with this post type.
	 *
	 * Called after register_post_type(). Uses the slugs returned by
	 * get_supported_taxonomies() to call register_taxonomy_for_object_type().
	 */
	public function register_taxonomies(): void {
		foreach ( $this->get_supported_taxonomies() as $taxonomy ) {
			register_taxonomy_for_object_type( $taxonomy, static::get_slug() );
		}
	}

	/**
	 * Runs after the post type has been registered.
	 *
	 * Override to add any additional setup (e.g. flushing rewrite rules on
	 * first activation) without touching the registration logic.
	 */
	public function after_register(): void {}

	/**
	 * Get the full args array passed to register_post_type().
	 *
	 * Override to customise any option; or override the individual
	 * helper methods (get_editor_supports, is_hierarchical, …) instead.
	 *
	 * @return array<string, mixed>
	 */
	public function get_options(): array {
		$options = [
			'labels'            => $this->get_labels(),
			'public'            => true,
			'has_archive'       => true,
			'show_ui'           => true,
			'show_in_menu'      => true,
			'show_in_nav_menus' => false,
			'show_in_rest'      => true,
			'supports'          => $this->get_editor_supports(),
			'menu_icon'         => $this->get_menu_icon(),
			'hierarchical'      => $this->is_hierarchical(),
		];

		$menu_position = $this->get_menu_position();
		if ( null !== $menu_position ) {
			$options['menu_position'] = $menu_position;
		}

		$options = array_merge( $options, $this->get_custom_options() );

		return $options;
	}

	/**
	 * Get any additional options to merge into the post type args.
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
	 * Get the post type labels.
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
	 * Get the supported editor features.
	 *
	 * Override to add or remove entries (e.g. add 'custom-fields').
	 *
	 * @return list<string>
	 */
	public function get_editor_supports(): array {
		return [ 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'revisions' ];
	}

	/**
	 * Get taxonomy slugs to associate with this post type via
	 * register_taxonomy_for_object_type().
	 *
	 * @return list<string>
	 */
	public function get_supported_taxonomies(): array {
		return [];
	}

	/**
	 * Get the menu position for the post type.
	 *
	 * Return null to use the WordPress default.
	 *
	 * @return int|null
	 */
	public function get_menu_position(): ?int {
		return null;
	}

	/**
	 * Whether this post type is hierarchical (like pages).
	 *
	 * @return bool
	 */
	public function is_hierarchical(): bool {
		return false;
	}
}
