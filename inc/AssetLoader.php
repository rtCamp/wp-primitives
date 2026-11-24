<?php
/**
 * Asset loader.
 *
 * Registers WordPress scripts, styles, script modules and block manifests.
 * Consumers extend it or hold an instance.
 *
 * Assets are registered by name relative to the assets directory passed to the
 * constructor. The source URL is built from the base URL, and the dependencies
 * and version are read from the sibling `*.asset.php` manifest.
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
	 * Asset handle prefix used by handle().
	 *
	 * Subclasses override it to namespace their handles.
	 */
	public const HANDLE_PREFIX = 'wp-primitives-';

	/**
	 * Base directory path (plugin or theme root).
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
	 * Prefix a short name with HANDLE_PREFIX to form an asset handle.
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
		if ( ! $this->is_safe_asset_path( $filename, $extension ) ) {
			return false;
		}

		$base = $this->base_dir . untrailingslashit( $this->assets_dir );

		return file_exists( sprintf( '%s/%s.%s', $base, $filename, $extension ) );
	}

	/**
	 * Whether a filename/extension pair is safe to interpolate into a path.
	 *
	 * The extension is joined after a literal dot, so a leading-dot extension
	 * forms `..` from two values that pass the `..` check on their own.
	 *
	 * For example, `has_asset( '/', './outside' )` would build `<base>//../outside`.
	 * Path separators are rejected in the extension.
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
	 * @return non-empty-string Absolute asset URL.
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
	 * @param array{version: string, dependencies?: array<int, non-empty-string>} $meta Resolved asset metadata.
	 * @param array<non-empty-string>                                             $deps Caller dependencies (empty to inherit).
	 * @param ?string                                                             $ver  Caller version (null to inherit).
	 *
	 * @return array{0: array<non-empty-string>, 1: string|false} Tuple of [ dependencies, version ].
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

		// Fallback for WordPress 6.5 to 6.7: register each block from its own block.json.
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
	 * @param non-empty-string   $handle    Name of the script. Should be unique.
	 * @param string             $filename  Path of the script relative to the assets directory, excluding the .js extension.
	 * @param non-empty-string[] $deps      Optional. Registered script handles this depends on. Inherited from the asset file if empty.
	 * @param ?string            $ver       Optional. Version string. Inherited from the asset file (or filemtime) if null.
	 * @param bool               $in_footer Optional. Whether to enqueue the script before </body> instead of in the <head>.
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
	 * @param non-empty-string   $handle   Name of the stylesheet. Should be unique.
	 * @param string             $filename Path of the stylesheet relative to the assets directory, excluding the .css extension.
	 * @param non-empty-string[] $deps     Optional. Registered stylesheet handles this depends on. Inherited from the asset file if empty.
	 * @param ?string            $ver      Optional. Version string. Inherited from the asset file (or filemtime) if null.
	 * @param string             $media    Optional. The media for which this stylesheet has been defined.
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
	 * @param array<int, string|array{id: string, import?: string}> $deps     Optional. Module dependencies, each a module ID string or an [id, import] array. Inherited from the asset file if empty.
	 * @param ?string                                               $ver      Optional. Version string. Inherited from the asset file (or filemtime) if null.
	 *
	 * @return bool False if the asset file is missing, otherwise true. wp_register_script_module()
	 *              returns void, so a failure after the file check is not reported.
	 */
	public function register_script_module( string $handle, string $filename, array $deps = [], ?string $ver = null ): bool {
		$meta = $this->get_asset_meta( $filename, 'js' );

		if ( null === $meta ) {
			return false;
		}

		$module_deps = empty( $deps ) ? ( $meta['dependencies'] ?? [] ) : $deps;

		// wp_register_script_module() expects each dependency as an [id, import] array.
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
	 * Triggers a _doing_it_wrong() notice when the asset file is missing. The
	 * `.asset.php` manifest is optional; see {@see AssetLoader::read_asset_manifest()}.
	 *
	 * @param string $filename  Asset path relative to the assets directory, excluding the extension.
	 * @param string $extension Asset file extension (e.g. 'js', 'css').
	 *
	 * @return array{version: string, dependencies?: array<int, non-empty-string>}|null Metadata, or null if the filename is rejected (path traversal) or the asset file is missing.
	 */
	private function get_asset_meta( string $filename, string $extension ): ?array {
		// $filename feeds a require() in read_asset_manifest().
		if ( ! $this->is_safe_asset_path( $filename, $extension ) ) {
			return null;
		}

		$base          = $this->base_dir . untrailingslashit( $this->assets_dir );
		$manifest_file = sprintf( '%s/%s.asset.php', $base, $filename );
		$asset_file    = sprintf( '%s/%s.%s', $base, $filename, $extension );

		// Without an asset file, there's nothing to register.
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
	 * An invalid manifest triggers a _doing_it_wrong() notice and is ignored.
	 *
	 * @param string $manifest_file Absolute path to the `.asset.php` manifest.
	 * @param string $asset_file    Absolute path to the asset file the version falls back to.
	 *
	 * @return array{version: string, dependencies?: array<int, non-empty-string>} Metadata (dependencies + version).
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
