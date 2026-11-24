<?php
/**
 * FeatureSelector utility.
 *
 * @package rtCamp\WPPrimitives\Utils
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Utils;

/**
 * Feature-flag registry for a single package.
 *
 * Each instance belongs to one theme or plugin slug, its context. All flag
 * states are stored in one option as an associative array.
 *
 * For the context "my-plugin" and the flag "dark-mode":
 *
 *   option name:   my_plugin_features
 *   array key:     dark-mode
 *   constant name: MY_PLUGIN_FEATURE_DARK_MODE
 *
 * Usage:
 *   $flags = new FeatureSelector( 'my-plugin' );
 *   $flags->register( [ 'dark-mode', 'beta-search' => [ 'name' => 'Beta search' ] ] );
 *   $flags->is_enabled( 'dark-mode' );
 *
 * Override shared_option_key() to store flags in another option.
 *
 * @since 1.0.0
 */
class FeatureSelector {
	/**
	 * Registered flags keyed by slug. Name and description may still be closures;
	 * get_features() resolves them.
	 *
	 * @var array<string, array{slug: string, name: string|\Closure(): string, description: string|\Closure(): string}>
	 */
	protected array $registered = [];

	/**
	 * Original slugs keyed by normalized flag key, for collision checks.
	 *
	 * @var array<string, string>
	 */
	private array $flag_keys = [];

	/**
	 * Constructor.
	 *
	 * @param string $context Theme or plugin slug. Prefixes the option name and the override constants. When empty, the
	 *                        option is named `features`.
	 */
	public function __construct(
		protected string $context = '',
	) {}

	/**
	 * Return the context slug as passed to the constructor (not normalized).
	 */
	public function get_context(): string {
		return $this->context;
	}

	/**
	 * Register one or more flags.
	 *
	 * Accepts a slug, a list of slugs, a slug => metadata map, or a mix of
	 * these. Metadata keys are `name`, which defaults to the slug, and
	 * `description`. Either can be a closure that returns the string, which
	 * get_features() calls. Malformed entries are skipped without a notice.
	 *
	 * Slugs with the same {@see FeatureSelector::flag_key()}, such as
	 * "beta-search" and "beta search", collide. The first registration is kept
	 * and later ones trigger _doing_it_wrong().
	 *
	 * @param array<int|string, string|array{name?: string|\Closure(): string, description?: string|\Closure(): string}>|string $features Flags to register.
	 */
	public function register( array|string $features ): void {
		foreach ( (array) $features as $key => $value ) {
			if ( is_int( $key ) && is_string( $value ) ) {
				$slug = $value;
				$meta = [];
			} elseif ( is_string( $key ) && is_array( $value ) ) {
				$slug = $key;
				$meta = $value;
			} else {
				continue;
			}

			$flag_key = $this->flag_key( $slug );

			if ( isset( $this->flag_keys[ $flag_key ] ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						/* translators: 1: slug being registered. 2: already-registered slug it collides with. 3: shared normalized key. */
						esc_html__( 'Feature flag "%1$s" collides with already-registered "%2$s" (both normalize to "%3$s"); keeping the first registration.', 'wp-primitives' ),
						esc_html( $slug ),
						esc_html( $this->flag_keys[ $flag_key ] ),
						esc_html( $flag_key )
					),
					'1.0.0'
				);

				continue;
			}

			$this->flag_keys[ $flag_key ] = $slug;
			$this->registered[ $slug ]    = [
				'slug'        => $slug,
				'name'        => $meta['name'] ?? $slug,
				'description' => $meta['description'] ?? '',
			];
		}
	}

	/**
	 * Check whether a flag is enabled.
	 *
	 * Returns false for an unregistered slug, without a notice. Otherwise
	 * returns the first of:
	 *
	 * 1. The override constant from constant_name(). A string 'false' counts as false.
	 * 2. The stored value for the flag.
	 * 3. True.
	 *
	 * @param string $flag Flag slug as passed to register().
	 */
	public function is_enabled( string $flag ): bool {
		// Unregistered flags are always off.
		if ( ! isset( $this->flag_keys[ $this->flag_key( $flag ) ] ) ) {
			return false;
		}

		$constant = $this->constant_name( $flag );

		if ( defined( $constant ) ) {
			$value = constant( $constant );

			return 'false' !== $value && (bool) $value;
		}

		$stored = (array) get_option( $this->shared_option_key(), [] );
		$key    = $this->flag_key( $flag );

		return array_key_exists( $key, $stored ) ? (bool) $stored[ $key ] : true;
	}

	/**
	 * Enable a flag and persist the change to the database.
	 *
	 * @param string $flag Flag slug.
	 *
	 * @return bool True if the option was updated. False if the flag is not registered or the stored value was already
	 *              true.
	 */
	public function enable( string $flag ): bool {
		if ( ! isset( $this->flag_keys[ $this->flag_key( $flag ) ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: flag slug that was not registered. */
					esc_html__( 'Feature flag "%s" is not registered; enable() ignored.', 'wp-primitives' ),
					esc_html( $flag )
				),
				'1.0.0'
			);

			return false;
		}

		return $this->write_flag( $flag, true );
	}

	/**
	 * Disable a flag and persist the change to the database.
	 *
	 * An override constant from constant_name() takes precedence over the stored value.
	 *
	 * @param string $flag Flag slug.
	 *
	 * @return bool True if the option was updated. False if the flag isn't registered or the stored value was already
	 *              false.
	 */
	public function disable( string $flag ): bool {
		if ( ! isset( $this->flag_keys[ $this->flag_key( $flag ) ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: flag slug that was not registered. */
					esc_html__( 'Feature flag "%s" is not registered; disable() ignored.', 'wp-primitives' ),
					esc_html( $flag )
				),
				'1.0.0'
			);

			return false;
		}

		return $this->write_flag( $flag, false );
	}

	/**
	 * Return registered flag slugs in registration order.
	 *
	 * @return array<int, string>
	 */
	public function get_registered(): array {
		return array_keys( $this->registered );
	}

	/**
	 * Return full metadata for every registered flag, with closures resolved.
	 *
	 * @return array<string, array{slug: string, name: string, description: string}>
	 */
	public function get_features(): array {
		$resolve = static fn ( string|\Closure $value ): string => $value instanceof \Closure ? (string) $value() : $value;

		return array_map(
			static fn ( array $meta ): array => [
				'slug'        => $meta['slug'],
				'name'        => $resolve( $meta['name'] ),
				'description' => $resolve( $meta['description'] ),
			],
			$this->registered
		);
	}

	/**
	 * Name of the option that stores all flag states for this context.
	 *
	 * Format: {context}_features, e.g. my_plugin_features.
	 */
	public function shared_option_key(): string {
		$context = $this->normalize( $this->context );

		return '' === $context ? 'features' : $context . '_features';
	}

	/**
	 * Normalized array key for a flag within the stored option.
	 *
	 * Lowercases the slug and replaces each run of characters outside [a-z0-9-]
	 * with a single dash. Leading and trailing dashes are trimmed.
	 *
	 * @param string $flag Flag slug.
	 */
	public function flag_key( string $flag ): string {
		return trim( (string) preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $flag ) ), '-' );
	}

	/**
	 * Name of the constant that overrides a flag's stored state.
	 *
	 * Format: {CONTEXT}_FEATURE_{FLAG}, e.g. MY_PLUGIN_FEATURE_DARK_MODE.
	 *
	 * @param string $flag Flag slug.
	 */
	public function constant_name( string $flag ): string {
		$context = $this->normalize( $this->context );
		$prefix  = '' === $context ? 'FEATURE_' : strtoupper( $context ) . '_FEATURE_';

		// Built from flag_key() rather than normalize() which collapses multiple dashes into one.
		return $prefix . strtoupper( str_replace( '-', '_', $this->flag_key( $flag ) ) );
	}

	/**
	 * Lowercase and replace each run of characters outside [a-z0-9_] with a single underscore.
	 *
	 * Unlike sanitize_key(), which drops those characters, this keeps "my.plugin" and "myplugin" distinct.
	 *
	 * @param string $value Context or flag slug.
	 */
	protected function normalize( string $value ): string {
		return trim( (string) preg_replace( '/[^a-z0-9_]+/', '_', strtolower( $value ) ), '_' );
	}

	/**
	 * Read the shared option, set one flag key, and write the array back.
	 *
	 * @param string $flag    Flag slug.
	 * @param bool   $enabled New state.
	 */
	private function write_flag( string $flag, bool $enabled ): bool {
		$stored                             = (array) get_option( $this->shared_option_key(), [] );
		$stored[ $this->flag_key( $flag ) ] = $enabled;

		return update_option( $this->shared_option_key(), $stored );
	}
}
