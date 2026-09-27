<?php
/**
 * Component loader for resolving and rendering PHP component partials.
 *
 * A component is a self-contained, render-only package (PHP + built CSS/JS).
 * The loader resolves it following the WordPress theme hierarchy — child theme
 * overrides parent theme overrides plugin — renders the PHP in an isolated
 * scope, and (by default) registers + enqueues its CSS/JS.
 *
 * Each consuming package (a theme, a plugin) loads its own subclass with a
 * distinct context, so their components and asset handles never collide.
 *
 * @package rtCamp\WPPrimitives
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives;

/**
 * Class ComponentLoader
 *
 * @since 1.0.0
 */
class ComponentLoader {

	/**
	 * Component PHP directory, relative to each source root in the hierarchy.
	 *
	 * A component resolves to `<root>/src/components/{Name}/{Name}.php`, searched
	 * across get_asset_loaders() (child theme → parent theme → package).
	 *
	 * @var string
	 */
	protected string $php_dir = 'src/components';

	/**
	 * Component stylesheet directory, relative to the assets directory.
	 *
	 * A style resolves to `<assets dir>/css/components/{Name}.css`.
	 *
	 * @var string
	 */
	protected string $style_dir = 'css/components';

	/**
	 * Component script directory, relative to the assets directory.
	 *
	 * @var string
	 */
	protected string $script_dir = 'js/components';

	/**
	 * Default enqueue settings for components.
	 *
	 * @var array<string, bool>
	 */
	protected array $default_enqueue_settings = [
		'script' => true,
		'style'  => true,
	];

	/**
	 * Resolved component metadata cache.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $component_data_cache = [];

	/**
	 * Asset loader for the package's own base — the owning theme or plugin root.
	 *
	 * @var AssetLoader|null
	 */
	private ?AssetLoader $asset_loader;

	/**
	 * Memoised asset loaders to search for component assets, override-precedence first.
	 *
	 * @var array<int, AssetLoader>|null
	 */
	private ?array $asset_loaders = null;

	/**
	 * Constructor.
	 *
	 * @param AssetLoader|null $asset_loader Asset loader for the package's own
	 *                                       base (its theme or plugin root) —
	 *                                       typically the shared instance.
	 *                                       Optional: a subclass loaded
	 *                                       via the framework Loader instead
	 *                                       overrides get_asset_loader() to resolve
	 *                                       a shared instance lazily.
	 */
	public function __construct( ?AssetLoader $asset_loader = null ) {
		$this->asset_loader = $asset_loader;
	}

	/**
	 * Get the context slug identifying the package that owns this loader.
	 *
	 * Used to namespace asset handles and to target the component filters.
	 * Subclasses override this to return their own slug (e.g. a theme/plugin
	 * slug); the default keeps the framework's own namespace.
	 *
	 * @return string Context slug.
	 */
	protected function get_context(): string {
		return 'wp-primitives';
	}

	/**
	 * Build a context-namespaced hook name so one package's component filters
	 * don't fire for another's.
	 *
	 * The context is prefixed into the hook name (e.g. `my-plugin/component_before_render`),
	 * mirroring TemplateLoader's prefixed hooks — the loader context slug is still
	 * passed as an argument for handlers that want it.
	 *
	 * @param string $event Hook suffix (e.g. 'before_render').
	 *
	 * @return string Namespaced hook name.
	 */
	private function component_hook( string $event ): string {
		return $this->get_context() . '/component_' . $event;
	}

	/**
	 * Get the package's own asset loader — the owning theme or plugin.
	 *
	 * Returns the injected loader. A subclass loaded without constructor
	 * injection must override this to supply one (e.g. a shared instance).
	 *
	 * @return AssetLoader Asset loader instance.
	 *
	 * @throws \RuntimeException If no asset loader was injected or provided by an override.
	 */
	protected function get_asset_loader(): AssetLoader {
		if ( null === $this->asset_loader ) {
			throw new \RuntimeException( // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not rendered to the browser.
				static::class . ' requires an AssetLoader: inject one via the constructor or override get_asset_loader().'
			);
		}

		return $this->asset_loader;
	}

	/**
	 * Asset loaders to search for a component asset, in override precedence.
	 *
	 * Follows the WordPress template hierarchy (child theme > parent theme >
	 * plugin), but only includes the override layers that sit *above* the
	 * package's own loader and that actually exist — so no redundant loaders are
	 * spawned:
	 *
	 * - self is the parent theme → [ child?, self ]
	 * - self is the child theme  → [ self ]
	 * - self is a plugin         → [ child?, parent, self ]
	 *
	 * where `child?` is present only when a distinct child theme is active.
	 *
	 * @return array<int, AssetLoader> Asset loaders, override-precedence first.
	 */
	protected function get_asset_loaders(): array {
		if ( null !== $this->asset_loaders ) {
			return $this->asset_loaders;
		}

		$self       = $this->get_asset_loader();
		$assets_dir = $self->get_assets_dir();
		$self_dir   = $self->get_base_dir();
		$stylesheet = trailingslashit( get_stylesheet_directory() );
		$template   = trailingslashit( get_template_directory() );
		$has_child  = $stylesheet !== $template;

		$loaders = [];

		// Child theme override layer: a distinct child theme exists and self isn't it.
		if ( $has_child && $self_dir !== $stylesheet ) {
			$loaders[] = new AssetLoader( $stylesheet, get_stylesheet_directory_uri(), $assets_dir );
		}

		// Parent theme override layer: self is neither the parent nor the child theme.
		if ( $self_dir !== $template && $self_dir !== $stylesheet ) {
			$loaders[] = new AssetLoader( $template, get_template_directory_uri(), $assets_dir );
		}

		// The package's own loader is lowest precedence — overrides above win.
		$loaders[] = $self;

		$this->asset_loaders = $loaders;

		return $this->asset_loaders;
	}

	/**
	 * Clear request-level lookup caches: resolved component metadata and the
	 * memoised asset-loader hierarchy (so a mid-request theme change is picked up).
	 */
	public function clear_cache(): void {
		$this->component_data_cache = [];
		$this->asset_loaders        = null;
	}

	/**
	 * Render a component by name.
	 *
	 * Resolves the component file from child theme, parent theme, or plugin paths,
	 * then includes it with the provided arguments available in scope.
	 *
	 * @param string               $name    Component name (e.g. 'Button', 'Card').
	 * @param array<string, mixed> $args    Arguments to pass to the component.
	 * @param array<string, mixed> $options {
	 *     Optional. Resolution and asset enqueue options.
	 *
	 *     @type bool   $script         Whether to enqueue the component's script. Default true (see get_enqueue_settings()).
	 *     @type bool   $style          Whether to enqueue the component's style. Default true (see get_enqueue_settings()).
	 *     @type bool   $allow_override Whether theme/child overrides may win, or the component resolves
	 *                                  from the package's own directory only. Default true.
	 * }
	 *
	 * @return void
	 */
	public function render( string $name, array $args = [], array $options = [] ): void {
		$this->render_component( $name, $args, $options, static::class . '::render' );
	}

	/**
	 * Get the rendered HTML of a component as a string.
	 *
	 * Uses output buffering to capture the component output instead of
	 * sending it directly to the browser.
	 *
	 * @param string               $name    Component name (e.g. 'Button', 'Card').
	 * @param array<string, mixed> $args    Arguments to pass to the component.
	 * @param array<string, mixed> $options Optional. Resolution options. See render().
	 *
	 * @return string Rendered component HTML, or empty string if not found.
	 *
	 * @throws \Throwable Re-thrown after clearing the output buffer if rendering fails.
	 */
	public function get( string $name, array $args = [], array $options = [] ): string {
		ob_start();

		try {
			$this->render_component( $name, $args, $options, static::class . '::get' );
		} catch ( \Throwable $e ) {
			ob_end_clean();
			throw $e;
		}

		return (string) ob_get_clean();
	}

	/**
	 * Shared render pipeline backing render() and get(): resolve the component,
	 * include its file, then register + enqueue its assets.
	 *
	 * @param string               $name    Component name (e.g. 'Button', 'Card').
	 * @param array<string, mixed> $args    Arguments to pass to the component.
	 * @param array<string, mixed> $options Resolution and asset enqueue options.
	 * @param string|null          $caller  Optional caller name for incorrect-usage notices.
	 *
	 * @return void
	 */
	protected function render_component( string $name, array $args = [], array $options = [], ?string $caller = null ): void {

		$options   = $this->get_render_options( $options );
		$component = $this->get_component_data( $name, $options );

		if ( false === $component ) {
			_doing_it_wrong(
				$caller ?? __METHOD__, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- This is a function name, not rendered output.
				sprintf(
					/* translators: %s: Component name. */
					esc_html__( 'Component "%s" could not be resolved.', 'wp-primitives' ),
					esc_html( $name )
				),
				'1.0.0'
			);

			return;
		}

		$context = $this->get_context();

		/**
		 * Fires before a component is rendered.
		 *
		 * @param string               $name    Component name.
		 * @param array<string, mixed> $args    Component arguments.
		 * @param string               $context Loader context slug.
		 */
		do_action( $this->component_hook( 'before_render' ), $name, $args, $context );

		// Delegate to a private static method so the component file loads in an
		// isolated scope with no access to $this. self:: (not static::) is
		// deliberate — the loader must not late-bind to a subclass here.
		self::load_template( (string) $component['file'], $args, $name, $options );

		$this->enqueue_component_assets( $component, $options );

		/**
		 * Fires after a component is rendered.
		 *
		 * @param string               $name    Component name.
		 * @param array<string, mixed> $args    Component arguments.
		 * @param string               $context Loader context slug.
		 */
		do_action( $this->component_hook( 'after_render' ), $name, $args, $context );
	}

	/**
	 * Get default enqueue settings for components.
	 *
	 * @return array<string, bool> Default enqueue settings.
	 */
	protected function get_enqueue_settings(): array {
		return $this->default_enqueue_settings;
	}

	/**
	 * Get normalized render options.
	 *
	 * @param array<string, mixed> $options Render options.
	 *
	 * @return array<string, mixed> Render options with enqueue settings resolved.
	 */
	private function get_render_options( array $options ): array {
		$enqueue = wp_parse_args( $options, $this->get_enqueue_settings() );

		$options['script']         = ! empty( $enqueue['script'] );
		$options['style']          = ! empty( $enqueue['style'] );
		$options['allow_override'] = (bool) ( $options['allow_override'] ?? true );

		return $options;
	}

	/**
	 * Resolve a component's metadata (resolved file path + asset info).
	 *
	 * The component PHP is located across the source hierarchy (child theme →
	 * parent theme → package), at `<root>/{php_dir}/{Name}/{Name}.php`.
	 *
	 * @param string               $name    Component name.
	 * @param array<string, mixed> $options Resolution options.
	 *
	 * @return array<string, mixed>|false Component metadata on success, false if not found.
	 */
	private function get_component_data( string $name, array $options = [] ): array|false {

		$component_name = $this->normalize_component_name( $name );

		if ( false === $component_name ) {
			return false;
		}

		$allow_override = (bool) ( $options['allow_override'] ?? true );

		$cache_key = $this->get_cache_key(
			[
				$this->get_context(),
				$component_name,
				$options['script'] ?? false,
				$options['style'] ?? false,
				$allow_override,
			]
		);

		if ( isset( $this->component_data_cache[ $cache_key ] ) ) {
			return $this->component_data_cache[ $cache_key ];
		}

		$file = $this->locate_component_file( $component_name, $allow_override );

		if ( false === $file ) {
			return false;
		}

		$component = [
			'name'   => $component_name,
			'file'   => $file,
			'assets' => $this->get_component_assets( $component_name, $options ),
		];

		$this->component_data_cache[ $cache_key ] = $component;

		return $component;
	}

	/**
	 * Locate a component's PHP file across the resolution hierarchy.
	 *
	 * Searches each loader's base directory for `<base>/{php_dir}/{Name}/{Name}.php`,
	 * first hit wins.
	 *
	 * @param string $component_name Component name.
	 * @param bool   $allow_override Whether theme/child overrides may win, or only the package's own dir.
	 *
	 * @return string|false Absolute path to the component file, or false if not found.
	 */
	private function locate_component_file( string $component_name, bool $allow_override ): string|false {
		$relative = trim( $this->php_dir, '/\\' ) . '/' . $component_name . '/' . $component_name . '.php';

		foreach ( $this->resolution_loaders( $allow_override ) as $loader ) {
			$file = $loader->get_base_dir() . $relative;

			if ( is_readable( $file ) ) {
				return $file;
			}
		}

		return false;
	}

	/**
	 * Loaders to search when resolving a component, in override precedence.
	 *
	 * The full override hierarchy when overrides are allowed, otherwise only the
	 * package's own loader — so the component resolves from its own directory.
	 *
	 * @param bool $allow_override Whether theme/child overrides may win.
	 *
	 * @return array<int, AssetLoader> Loaders to search, override-precedence first.
	 */
	private function resolution_loaders( bool $allow_override ): array {
		return $allow_override ? $this->get_asset_loaders() : [ $this->get_asset_loader() ];
	}

	/**
	 * Resolve the asset filenames a component requests.
	 *
	 * Returns the asset name (relative to the assets directory, no extension)
	 * for each requested type; existence and the override base are resolved at
	 * registration time across get_asset_loaders().
	 *
	 * @param string               $component_name Component name.
	 * @param array<string, mixed> $options        Component render options.
	 *
	 * @return array<string, string> Asset filename keyed by type ('style', 'script').
	 */
	private function get_component_assets( string $component_name, array $options ): array {
		$subpaths = [
			'style'  => $this->style_dir,
			'script' => $this->script_dir,
		];
		$assets   = [];

		foreach ( $subpaths as $asset_type => $subpath ) {
			if ( empty( $options[ $asset_type ] ) ) {
				continue;
			}

			$assets[ $asset_type ] = trim( $subpath, '/\\' ) . '/' . $component_name;
		}

		return $assets;
	}

	/**
	 * Create a stable cache key for request-level lookup caches.
	 *
	 * @param array<mixed> $parts Cache key parts.
	 *
	 * @return string Cache key.
	 */
	private function get_cache_key( array $parts ): string {
		$encoded_parts = wp_json_encode( $parts );

		return md5( is_string( $encoded_parts ) ? $encoded_parts : '' );
	}

	/**
	 * Enqueue assets for a rendered component.
	 *
	 * @param array<string, mixed> $component Component metadata.
	 * @param array<string, mixed> $options   Component render options.
	 *
	 * @return void
	 */
	private function enqueue_component_assets( array $component, array $options ): void {
		if ( empty( $component['name'] ) || empty( $component['assets'] ) || ! is_array( $component['assets'] ) ) {
			return;
		}

		$name    = (string) $component['name'];
		$context = $this->get_context();

		foreach ( [ 'style', 'script' ] as $asset_type ) {
			if ( empty( $component['assets'][ $asset_type ] ) ) {
				continue;
			}

			$enqueue = ! empty( $options[ $asset_type ] );

			/**
			 * Filters whether a component's asset should be registered and enqueued.
			 *
			 * Return false to skip auto-enqueue — e.g. for components that are
			 * imported directly and whose assets are handled elsewhere.
			 *
			 * @param bool   $enqueue    Whether to register + enqueue the asset.
			 * @param string $name       Component name.
			 * @param string $asset_type 'style' or 'script'.
			 * @param string $context    Loader context slug.
			 */
			$enqueue = (bool) apply_filters( $this->component_hook( 'should_enqueue' ), $enqueue, $name, $asset_type, $context );

			if ( ! $enqueue ) {
				continue;
			}

			$handle = $this->component_asset_handle( $name, $asset_type );

			if ( ! $this->ensure_component_asset_registered( $asset_type, $handle, (string) $component['assets'][ $asset_type ], (bool) ( $options['allow_override'] ?? true ) ) ) {
				continue;
			}

			'style' === $asset_type ? wp_enqueue_style( $handle ) : wp_enqueue_script( $handle );
		}
	}

	/**
	 * Build the (filterable) asset handle for a component asset.
	 *
	 * @param string $name       Component name.
	 * @param string $asset_type 'style' or 'script'.
	 *
	 * @return string Asset handle.
	 */
	private function component_asset_handle( string $name, string $asset_type ): string {
		$context = $this->get_context();
		$handle  = sanitize_key( $context ) . '-component-' . sanitize_key( $name ) . '-' . $asset_type;

		/**
		 * Filters a component's asset handle.
		 *
		 * @param string $handle     Default handle.
		 * @param string $name       Component name.
		 * @param string $asset_type 'style' or 'script'.
		 * @param string $context    Loader context slug.
		 */
		return (string) apply_filters( $this->component_hook( 'asset_handle' ), $handle, $name, $asset_type, $context );
	}

	/**
	 * Ensure a component asset is registered.
	 *
	 * Searches the asset loaders in override precedence and registers via the
	 * first one that holds the file.
	 *
	 * @param string $asset_type     'style' or 'script'.
	 * @param string $handle         Asset handle. Should be unique.
	 * @param string $filename       Asset filename relative to the assets dir, excluding extension.
	 * @param bool   $allow_override Whether theme/child overrides may win, or only the package's own dir.
	 *
	 * @return bool Whether the asset is registered or registerable.
	 */
	private function ensure_component_asset_registered( string $asset_type, string $handle, string $filename, bool $allow_override ): bool {
		$is_style = 'style' === $asset_type;

		if ( $is_style ? wp_style_is( $handle, 'registered' ) : wp_script_is( $handle, 'registered' ) ) {
			return true;
		}

		$extension = $is_style ? 'css' : 'js';

		foreach ( $this->resolution_loaders( $allow_override ) as $loader ) {
			if ( ! $loader->has_asset( $filename, $extension ) ) {
				continue;
			}

			return $is_style
				? $loader->register_style( $handle, $filename )
				: $loader->register_script( $handle, $filename );
		}

		return false;
	}

	/**
	 * Normalize and validate a component name before using it in filesystem paths.
	 *
	 * Trims whitespace, bounds the length, and allows only alphanumeric
	 * characters, underscores and dashes — which inherently blocks empty names,
	 * path separators and traversal sequences.
	 *
	 * @param string $name Component name to normalize and validate.
	 *
	 * @return string|false Normalized component name, or false when invalid.
	 */
	private function normalize_component_name( string $name ): string|false {
		$name = trim( $name );

		if ( strlen( $name ) > 128 || 1 !== preg_match( '/^[A-Za-z0-9_-]+$/', $name ) ) {
			return false;
		}

		return $name;
	}

	/**
	 * Isolate the scope for the required component file.
	 *
	 * The component receives $args, $name and the resolved $options in scope
	 * (and no access to $this), so it can render and forward options to nested
	 * components.
	 *
	 * @param string               $__file  Component file path.
	 * @param array<string, mixed> $args    Component arguments.
	 * @param string               $name    Component name.
	 * @param array<string, mixed> $options Resolved render options.
	 *
	 * @return void
	 */
	private static function load_template( string $__file, array $args, string $name, array $options ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- $args, $name and $options are available to templates.
		require $__file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable, WordPressVIPMinimum.Files.IncludingFile.NotAbsolutePath -- Component file path is resolved and readability-checked before inclusion.
	}
}
