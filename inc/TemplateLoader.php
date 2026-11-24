<?php
/**
 * Loader for WordPress template parts with theme overrides.
 *
 * Allows plugins or child themes to override templates.
 *
 * Usage:
 *
 *   // Plugin: templates in the plugin, theme overrides at theme/my-plugin/.
 *   final class Templates extends TemplateLoader implements Shareable {
 *       public function __construct() {
 *           parent::__construct( 'my_plugin', MY_PLUGIN_PATH . 'templates', 'my-plugin' );
 *       }
 *   }
 *
 *   // Theme: templates in the theme, child theme overrides at the same path.
 *   final class Templates extends TemplateLoader implements Shareable {
 *       public function __construct() {
 *           parent::__construct( 'my_theme', get_template_directory() . '/templates', 'templates' );
 *       }
 *   }
 *
 *   $templates->render( 'content', 'card', [ 'title' => 'Hello' ] ); // echo
 *   $html = $templates->get( 'content', 'card', [ 'title' => 'Hello' ] ); // string
 *
 * @package rtCamp\WPPrimitives
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives;

/**
 * Class TemplateLoader
 *
 * @since 1.0.0
 */
class TemplateLoader {
	/**
	 * Prefix for the filter/action hook names (usually the package slug, snake_case).
	 *
	 * @var string
	 */
	private string $hook_prefix;

	/**
	 * Absolute path to the package's own template directory.
	 *
	 * @var string
	 */
	private string $template_dir;

	/**
	 * Directory name themes override into, e.g. 'my-plugin' resolves to
	 * `my-theme/my-plugin/{slug}.php`.
	 *
	 * @var string
	 */
	private string $template_theme_dir;

	/**
	 * Separator between the hook prefix and hook name.
	 *
	 * @var string
	 */
	private string $hook_separator;

	/**
	 * Request-level cache of located template paths, keyed by candidate set.
	 *
	 * @var array<string, string|false>
	 */
	private array $location_cache = [];

	/**
	 * Constructor.
	 *
	 * @param string $hook_prefix        Hook name prefix (usually the package slug, snake_case).
	 * @param string $template_dir       Absolute path to the package's template directory.
	 * @param string $template_theme_dir Directory name themes override into.
	 * @param string $hook_separator     Separator between prefix and hook name. Default '/'.
	 */
	public function __construct( string $hook_prefix, string $template_dir, string $template_theme_dir, string $hook_separator = '/' ) {
		$this->hook_prefix        = $hook_prefix;
		$this->template_dir       = $template_dir;
		$this->template_theme_dir = $template_theme_dir;
		$this->hook_separator     = $hook_separator;
	}

	/**
	 * Render a template part, echoing its output.
	 *
	 * Resolves the template from the child theme, parent theme or package, and loads it with load_template()
	 * and the filtered arguments. Does nothing when no template is found.
	 *
	 * @param non-empty-string     $slug Template slug.
	 * @param string|null          $name Optional. Template variation name.
	 * @param array<string, mixed> $args Optional. Data passed to the template.
	 */
	public function render( string $slug, ?string $name = null, array $args = [] ): void {
		/**
		 * Fires when a template part is requested.
		 *
		 * @param non-empty-string     $slug Template slug.
		 * @param string|null          $name Template variation name.
		 * @param array<string, mixed> $args Data to pass to the template.
		 */
		do_action( $this->hook( "get_template_part_$slug" ), $slug, $name, $args );

		$located = $this->locate( $slug, $name );

		if ( false === $located ) {
			return;
		}

		/**
		 * Filters the arguments passed to the template.
		 *
		 * @param array<string, mixed> $args Data to pass to the template.
		 * @param non-empty-string     $slug Template slug.
		 * @param string|null          $name Template variation name.
		 */
		$args = (array) apply_filters( $this->hook( 'template_args' ), $args, $slug, $name );

		load_template( $located, false, $args );
	}

	/**
	 * Render a template part and return its output as a string.
	 *
	 * @param non-empty-string     $slug Template slug.
	 * @param string|null          $name Optional. Template variation name.
	 * @param array<string, mixed> $args Optional. Data passed to the template.
	 *
	 * @return string Rendered template output, or '' if not found.
	 *
	 * @throws \Throwable Re-thrown after clearing the output buffer if the template throws.
	 */
	public function get( string $slug, ?string $name = null, array $args = [] ): string {
		ob_start();

		try {
			$this->render( $slug, $name, $args );
		} catch ( \Throwable $e ) {
			// Don't leave a half-rendered buffer open for the caller to inherit.
			ob_end_clean();
			throw $e;
		}

		return (string) ob_get_clean();
	}

	/**
	 * Locate a template part's file without rendering it.
	 *
	 * @param non-empty-string $slug Template slug.
	 * @param string|null      $name Optional. Template variation name.
	 *
	 * @return string|false Located template path, or false if not found.
	 */
	public function locate( string $slug, ?string $name = null ): string|false {
		$templates = $this->sanitize_template_names( $this->get_template_file_names( $slug, $name ) );

		if ( [] === $templates ) {
			return false;
		}

		$located = $this->find_template( $templates );

		/**
		 * Filters the located template path.
		 *
		 * @param string|false       $located   Full path to the located template.
		 * @param array<int, string> $templates Template files that were searched for.
		 */
		$located = apply_filters( $this->hook( 'located_template' ), $located, $templates );

		return is_string( $located ) ? $located : false;
	}

	/**
	 * Clear the request-level located-template cache.
	 */
	public function clear_cache(): void {
		$this->location_cache = [];
	}

	/**
	 * Build the candidate template file names from a slug and optional name.
	 *
	 * @param string      $slug Template slug.
	 * @param string|null $name Template variation name.
	 *
	 * @return array<int, string> Candidate file names, most specific first.
	 */
	private function get_template_file_names( string $slug, ?string $name ): array {
		$templates = [];

		if ( null !== $name && '' !== $name ) {
			$templates[] = "{$slug}-{$name}.php";
		}

		$templates[] = "{$slug}.php";

		/**
		 * Filters the list of template file names to search for.
		 *
		 * @param array<int, string> $templates Template file names.
		 * @param string             $slug      Template slug.
		 * @param string|null        $name      Template variation name.
		 */
		return (array) apply_filters( $this->hook( 'template_file_names' ), $templates, $slug, $name );
	}

	/**
	 * Sanitize each candidate path segment-by-segment, dropping empties and traversal.
	 *
	 * @param array<int, string> $templates Raw candidate file names.
	 *
	 * @return array<int, string> Sanitized, re-indexed candidate names.
	 */
	private function sanitize_template_names( array $templates ): array {
		$sanitized = array_map(
			static function ( string $template ): string {
				$segments = array_filter(
					array_map( 'sanitize_file_name', explode( '/', $template ) ),
					static fn ( string $seg ): bool => '' !== $seg && '..' !== $seg
				);

				return implode( '/', $segments );
			},
			$templates
		);

		return array_values( array_filter( $sanitized, static fn ( string $t ): bool => '' !== $t ) );
	}

	/**
	 * Find the first existing template across the search paths, with caching.
	 *
	 * @param array<int, string> $templates Sanitized candidate file names.
	 *
	 * @return string|false Full path to the template, or false if none found.
	 */
	private function find_template( array $templates ): string|false {
		$paths = $this->get_template_paths();

		// Use the active theme paths to prevent a cross-theme cache collision.
		$cache_key = md5( implode( '|', $paths ) . "\0" . implode( '|', $templates ) );

		if ( array_key_exists( $cache_key, $this->location_cache ) ) {
			return $this->location_cache[ $cache_key ];
		}

		$found = false;

		// Per locate_template(), a `{slug}-{name}.php` in any path beats `{slug}.php` in a higher-precedence path.
		foreach ( $templates as $template ) {
			$template = ltrim( $template, '/\\' );

			foreach ( $paths as $path ) {
				$full_path = $path . $template;

				if ( file_exists( $full_path ) ) {
					$found = $full_path;
					break 2;
				}
			}
		}

		$this->location_cache[ $cache_key ] = $found;

		return $found;
	}

	/**
	 * Get the ordered, trailing-slashed search paths (child > parent > package).
	 *
	 * Only the theme layers above the package are included. Templates inside the
	 * parent theme are overridden only by the child theme, and templates inside
	 * the child theme have no override layer.
	 *
	 * @return array<int, string> Paths in resolution order, most specific first.
	 */
	private function get_template_paths(): array {
		$stylesheet = trailingslashit( get_stylesheet_directory() );
		$template   = trailingslashit( get_template_directory() );
		$package    = trailingslashit( $this->template_dir );
		$has_child  = $stylesheet !== $template;

		// Where the package's own templates live decides which theme layers can override them.
		$in_child  = str_starts_with( $package, $stylesheet );
		$in_parent = str_starts_with( $package, $template );

		$paths = [];

		// A child theme is active and the package is not in it.
		if ( $has_child && ! $in_child ) {
			$paths[1] = $stylesheet . $this->template_theme_dir;
		}

		// The package is in neither theme.
		if ( ! $in_parent && ! $in_child ) {
			$paths[10] = $template . $this->template_theme_dir;
		}

		// The package's own templates have the lowest precedence.
		$paths[100] = $this->template_dir;

		/**
		 * Filters the template search paths, keyed by priority (lower = higher precedence).
		 *
		 * @param array<int, string> $paths Search paths keyed by priority.
		 */
		$paths = (array) apply_filters( $this->hook( 'template_paths' ), $paths );

		ksort( $paths, SORT_NUMERIC );

		// Normalise; array_unique guards against a filter re-adding an existing path.
		return array_values( array_unique( array_map( 'trailingslashit', $paths ) ) );
	}

	/**
	 * Build a fully-qualified hook name from the prefix and a hook suffix.
	 *
	 * @param non-empty-string $name Hook suffix.
	 *
	 * @return non-empty-string Prefixed hook name.
	 */
	private function hook( string $name ): string {
		return $this->hook_prefix . $this->hook_separator . $name;
	}
}
