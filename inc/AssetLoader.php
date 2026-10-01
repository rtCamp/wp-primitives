<?php
/**
 * Asset loader.
 *
 * Concrete, injectable asset loader for WordPress scripts, styles, script
 * modules and block manifests. A plain class (not a trait) so the loading
 * behaviour has an instance identity that can be passed around and shared —
 * consumers either extend it or, when their base-class slot is taken, hold one.
 *
 * Registers an asset by name relative to the instance's assets directory, set at
 * construction: the source URL is built from the base URL and the dependency +
 * version metadata is read from the sibling `*.asset.php` manifest on disk.
 *
 * @package rtCamp\WPPrimitives
 *
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives;

/**
 * Class AssetLoader
 *
 * @since 1.0.0
 */
class AssetLoader {
	/**
	 * Default asset handle prefix. Subclasses override this constant to namespace
	 * their handles; handle() reads it via late static binding.
	 */
	public const HANDLE_PREFIX = 'wp-primitives-';

	/**
	 * Base directory path (plugin or theme root). Readable by subclasses that
	 * need to resolve their own paths (e.g. a block build directory).
	 *
	 * @var string
	 */
	protected string $base_dir;

	/**
	 * Base URL (plugin or theme root URL).
	 *
	 * @var string
	 */
	protected string $base_url;

	/**
	 * Built assets directory, relative to the base directory. No surrounding slashes.
	 *
	 * @var string
	 */
	protected string $assets_dir;

	/**
	 * Constructor.
	 *
	 * @param string $base_dir   Base directory path (plugin or theme root).
	 * @param string $base_url   Base URL (plugin or theme root URL).
	 * @param string $assets_dir Built assets directory, relative to the base directory.
	 */
	public function __construct( string $base_dir, string $base_url, string $assets_dir ) {
		$this->base_dir   = '' !== $base_dir ? trailingslashit( $base_dir ) : '';
		$this->base_url   = '' !== $base_url ? trailingslashit( $base_url ) : '';
		$this->assets_dir = $assets_dir;
	}

	/**
	 * Get the built assets directory (relative to the base directory).
	 *
	 * @return string Assets directory.
	 */
	public function get_assets_dir(): string {
		return $this->assets_dir;
	}

	/**
	 * Prefix a short name with HANDLE_PREFIX to form an asset handle. With the
	 * default prefix, handle( 'frontend' ) returns 'wp-primitives-frontend'.
	 * Subclasses namespace their handles by overriding the HANDLE_PREFIX
	 * constant; uniqueness is the caller's responsibility (pass distinct names).
	 *
	 * @param string $name Short handle name.
	 *
	 * @return string Prefixed handle.
	 */
	public function handle( string $name ): string {
		return static::HANDLE_PREFIX . $name;
	}

	/**
	 * Get the base directory path (trailing-slashed, or '' if unset).
	 *
	 * @return string Base directory.
	 */
	public function get_base_dir(): string {
		return $this->base_dir;
	}

	/**
	 * Whether an asset file exists under this loader's base + assets directory.
	 *
	 * @param string $filename  Asset path relative to the assets directory, excluding the extension.
	 * @param string $extension Asset file extension (e.g. 'js', 'css').
	 *
	 * @return bool True if the asset file exists.
	 */
	public function has_asset( string $filename, string $extension ): bool {
		// Mirror get_asset_meta()'s traversal guard: this is public API and may be
		// called directly with consumer-supplied values.
		if ( ! $this->is_safe_asset_path( $filename, $extension ) ) {
			return false;
		}

		$base = $this->base_dir . untrailingslashit( $this->assets_dir );

		return file_exists( sprintf( '%s/%s.%s', $base, $filename, $extension ) );
	}

	/**
	 * Whether a filename/extension pair is safe to interpolate into a path.
	 *
	 * Both parts matter. The extension is joined after a literal dot, so a
	 * leading-dot extension forms `..` out of two values that each look clean:
	 * `has_asset( '/', './outside' )` would otherwise build `<base>//../outside`
	 * and probe outside the assets directory. An extension is a bare suffix, so
	 * path separators in it are always wrong.
	 *
	 * @param string $filename  Asset path relative to the assets directory, excluding the extension.
	 * @param string $extension Asset file extension (e.g. 'js', 'css').
	 *
	 * @return bool True if the pair can be safely interpolated.
	 */
	private function is_safe_asset_path( string $filename, string $extension ): bool {
		if ( str_contains( $filename, '..' ) || str_contains( $extension, '..' ) ) {
			return false;
		}

		if ( str_starts_with( $extension, '.' ) ) {
			return false;
		}

		return ! preg_match( '#[/\\\\]#', $extension );
	}

	/**
	 * Build the public URL for an asset from the base URL and assets directory.
	 *
	 * @param string $filename  Path of the asset relative to the assets directory, excluding the extension.
	 * @param string $extension Asset extension (e.g. 'js', 'css').
	 *
	 * @return string Absolute asset URL.
	 */
	private function asset_src( string $filename, string $extension ): string {
		return sprintf(
			'%s/%s.%s',
			$this->base_url . untrailingslashit( $this->assets_dir ),
			$filename,
			$extension
		);
	}

	/**
	 * Resolve the effective dependencies and version for a registration.
	 *
	 * Explicit arguments win; otherwise values are inherited from $meta. The
	 * version is normalised to `string|false` so it passes straight to the
	 * wp_register_* functions (which read `false` as "no version").
	 *
	 * @param array{version: string, dependencies?: array<int, string>} $meta Resolved asset metadata.
	 * @param array<string>                                             $deps Caller dependencies (empty to inherit).
	 * @param ?string                                                   $ver  Caller version (null to inherit).
	 *
	 * @return array{0: array<string>, 1: string|false} Tuple of [ dependencies, version ].
	 */
	private function resolve_deps_and_version( array $meta, array $deps, ?string $ver ): array {
		$resolved_deps    = empty( $deps ) ? ( $meta['dependencies'] ?? [] ) : $deps;
		$resolved_version = $ver ?? $meta['version'];

		return [
			$resolved_deps,
			empty( $resolved_version ) ? false : $resolved_version,
		];
	}

	/**
	 * Register a block collection from its manifest file.
	 *
	 * Uses the block metadata-collection API (`wp_register_block_types_from_metadata_collection()`)
	 * when available — WordPress 6.8+ — which registers every block from a single
	 * manifest without reading each block.json. On the supported 6.5–6.7 range
	 * that function does not exist, so each block is registered from its own
	 * block.json on disk instead: slower, but functionally equivalent.
	 *
	 * @param string $block_path    Relative path to the block collection. E.g. `build/blocks`.
	 * @param string $manifest_file Path to the manifest file, relative to the base directory. E.g. `build/blocks-manifest.php`.
	 */
	public function register_block_manifest( string $block_path, string $manifest_file ): void {
		$base          = $this->base_dir;
		$manifest_path = $base . $manifest_file;
		if ( ! file_exists( $manifest_path ) ) {
			_doing_it_wrong(
				static::class,
				esc_html__( 'Block manifest file is missing. Blocks will not be registered.', 'wp-primitives' ),
				'1.0.0'
			);
			return;
		}

		$blocks_dir = $base . $block_path;

		if ( function_exists( 'wp_register_block_types_from_metadata_collection' ) ) {
			wp_register_block_types_from_metadata_collection( $blocks_dir, $manifest_path );
			return;
		}

		// Fallback for WordPress 6.5–6.7: register each block from disk. The
		// manifest is keyed by block directory name; the block.json lives in the
		// built block directory under $blocks_dir.
		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Existence checked above.
		$manifest = require $manifest_path;

		if ( ! is_array( $manifest ) ) {
			return;
		}

		foreach ( array_keys( $manifest ) as $block_dir ) {
			register_block_type( $blocks_dir . '/' . (string) $block_dir );
		}
	}

	/**
	 * Register a script.
	 *
	 * @param string   $handle    Name of the script. Should be unique.
	 * @param string   $filename  Path of the script relative to the assets directory, excluding the .js extension.
	 * @param string[] $deps      Optional. Registered script handles this depends on. Inherited from the asset file if empty.
	 * @param ?string  $ver       Optional. Version string. Inherited from the asset file (or filemtime) if null.
	 * @param bool     $in_footer Optional. Whether to enqueue the script before </body> instead of in the <head>.
	 *
	 * @return bool True on success; false if the asset file is missing.
	 */
	public function register_script( string $handle, string $filename, array $deps = [], ?string $ver = null, bool $in_footer = true ): bool {
		$meta = $this->get_asset_meta( $filename, 'js' );

		if ( null === $meta ) {
			return false;
		}

		[ $deps, $version ] = $this->resolve_deps_and_version( $meta, $deps, $ver );

		return wp_register_script(
			$handle,
			$this->asset_src( $filename, 'js' ),
			$deps,
			$version,
			$in_footer
		);
	}

	/**
	 * Register a CSS stylesheet.
	 *
	 * @param string   $handle   Name of the stylesheet. Should be unique.
	 * @param string   $filename Path of the stylesheet relative to the assets directory, excluding the .css extension.
	 * @param string[] $deps     Optional. Registered stylesheet handles this depends on. Inherited from the asset file if empty.
	 * @param ?string  $ver      Optional. Version string. Inherited from the asset file (or filemtime) if null.
	 * @param string   $media    Optional. The media for which this stylesheet has been defined.
	 *
	 * @return bool True on success; false if the asset file is missing.
	 */
	public function register_style( string $handle, string $filename, array $deps = [], ?string $ver = null, string $media = 'all' ): bool {
		$meta = $this->get_asset_meta( $filename, 'css' );

		if ( null === $meta ) {
			return false;
		}

		[ $deps, $version ] = $this->resolve_deps_and_version( $meta, $deps, $ver );

		return wp_register_style(
			$handle,
			$this->asset_src( $filename, 'css' ),
			$deps,
			$version,
			$media
		);
	}

	/**
	 * Register a script module.
	 *
	 * @param string                                                $handle   Name of the script module. Should be unique.
	 * @param string                                                $filename Path of the module relative to the assets directory, excluding the .js extension.
	 * @param array<int, string|array{id: string, import?: string}> $deps     Optional. Module dependencies — each a module-ID string or an [id, import] array. Inherited from the asset file if empty.
	 * @param ?string                                               $ver      Optional. Version string. Inherited from the asset file (or filemtime) if null.
	 *
	 * @return bool False if the asset file is missing; otherwise true. (wp_register_script_module()
	 *              returns void, so success past the file check cannot be reported.)
	 */
	public function register_script_module( string $handle, string $filename, array $deps = [], ?string $ver = null ): bool {
		$meta = $this->get_asset_meta( $filename, 'js' );

		if ( null === $meta ) {
			return false;
		}

		$module_deps = empty( $deps ) ? ( $meta['dependencies'] ?? [] ) : $deps;

		// wp_register_script_module() expects each dependency in id/import form;
		// normalise plain module-ID strings while leaving array entries intact.
		$module_deps = array_map(
			static fn ( string|array $dep ): array => is_array( $dep ) ? $dep : [ 'id' => $dep ],
			$module_deps
		);

		$version = $ver ?? $meta['version'];

		wp_register_script_module(
			$handle,
			$this->asset_src( $filename, 'js' ),
			$module_deps,
			empty( $version ) ? false : $version
		);

		return true;
	}

	/**
	 * Resolve asset metadata for an asset by name (relative path + extension).
	 *
	 * Returns null if the filename is rejected for path traversal, or — with a
	 * warning — if the asset file itself is missing. The `.asset.php` manifest is
	 * optional, and an invalid one is ignored with a warning (see read_asset_manifest()).
	 *
	 * @param string $filename  Asset path relative to the assets directory, excluding the extension.
	 * @param string $extension Asset file extension (e.g. 'js', 'css').
	 *
	 * @return array{version: string, dependencies?: array<int, string>}|null Metadata, or null if the filename is rejected (path traversal) or the asset file is missing.
	 */
	private function get_asset_meta( string $filename, string $extension ): ?array {
		// $filename feeds a require() in read_asset_manifest(); reject traversal
		// in either part up front. The in-tree callers pre-sanitise and pass a
		// literal extension, but this class is public API and may be called
		// directly, so the guard covers both values.
		if ( ! $this->is_safe_asset_path( $filename, $extension ) ) {
			return null;
		}

		$base          = $this->base_dir . untrailingslashit( $this->assets_dir );
		$manifest_file = sprintf( '%s/%s.asset.php', $base, $filename );
		$asset_file    = sprintf( '%s/%s.%s', $base, $filename, $extension );

		// The actual asset file is required — if it's missing, there is nothing to register.
		if ( ! file_exists( $asset_file ) ) {
			_doing_it_wrong(
				static::class,
				sprintf(
					/* translators: 1: The asset filename. 2: The asset extension. */
					esc_html__( 'Asset file "%1$s.%2$s" is missing. The asset will not be registered.', 'wp-primitives' ),
					esc_html( $filename ),
					esc_html( $extension )
				),
				'1.0.0'
			);
			return null;
		}

		return $this->read_asset_manifest( $manifest_file, $asset_file );
	}

	/**
	 * Read an optional `.asset.php` manifest, falling back to the asset's filemtime.
	 *
	 * @param string $manifest_file Absolute path to the `.asset.php` manifest.
	 * @param string $asset_file    Absolute path to the asset file the version falls back to.
	 *
	 * @return array{version: string, dependencies?: array<int, string>} Metadata (dependencies + version).
	 */
	private function read_asset_manifest( string $manifest_file, string $asset_file ): array {
		if ( file_exists( $manifest_file ) ) {
			// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Existence checked above.
			$meta = require $manifest_file;

			if ( ! is_array( $meta ) ) {
				_doing_it_wrong(
					static::class,
					sprintf(
						/* translators: %s: The asset manifest path. */
						esc_html__( 'Asset manifest "%s" is invalid; the file modification time will be used as the version.', 'wp-primitives' ),
						esc_html( $manifest_file )
					),
					'1.0.0'
				);
				$meta = [ 'dependencies' => [] ];
			}
		} else {
			$meta = [ 'dependencies' => [] ];
		}

		if ( ! isset( $meta['version'] ) ) {
			$meta['version'] = (string) filemtime( $asset_file );
		}

		return $meta;
	}
}
