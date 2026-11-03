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
 * Each instance is scoped to one context slug (a theme or plugin slug). All
 * flag states live together in a single WP option as a serialised associative
 * array, rather than one option row per flag. This keeps the options table clean
 * and makes it trivial to read, export, or reset all flags at once.
 *
 * Flag resolution order in is_enabled():
 *   0. Registered?   — unregistered slugs are always off; nothing below runs
 *   1. PHP constant  — define in wp-config.php to lock a flag hard
 *   2. Stored array  — value from the shared option for this flag's key
 *   3. default true  — a registered flag is on unless explicitly turned off
 *
 * Storage scheme (context "my-plugin"):
 *   option name   → my_plugin_features  (one row; value is an associative array)
 *   array key     → dark-mode           (flag slug, dashes preserved)
 *   constant name → MY_PLUGIN_FEATURE_DARK_MODE
 *
 * Context slugs are normalized with underscores (my_plugin_features). Flag
 * keys preserve dashes — only spaces and other non-[a-z0-9-] characters are
 * collapsed to a single dash. Constant names always use underscores (derived
 * independently via the underscore normalizer).
 *
 * Usage:
 *   $flags = new FeatureSelector( 'my-plugin' );
 *   $flags->register( [ 'dark-mode', 'beta-search' => [ 'name' => 'Beta search' ] ] );
 *   $flags->is_enabled( 'dark-mode' );
 *
 * Extend and implement as Shareable to share a single registry instance across
 * the framework container. Override shared_option_key() to change where flags
 * are stored; flag_key() and constant_name() are independent and must be
 * overridden separately if needed.
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
	 * Normalized flag key → original slug map for O(1) collision checks.
	 *
	 * @var array<string, string>
	 */
	private array $flag_keys = [];

	/**
	 * Constructor.
	 *
	 * @param string $context Package slug — namespaces the shared option key
	 *                        and override constants so flags from different
	 *                        packages never collide. Omit only if you're certain
	 *                        nothing else uses the bare `features` option name.
	 */
	public function __construct(
		protected string $context = '',
	) {}

	/**
	 * Return the context slug as passed to the constructor (not normalized).
	 *
	 * @return string
	 */
	public function get_context(): string {
		return $this->context;
	}

	/**
	 * Register one or more flags.
	 *
	 * Accepts a single slug string, a list of slugs, or a slug => metadata map —
	 * or any mix of the three. Valid metadata keys: name (defaults to the slug),
	 * description. Either may be a closure returning the string, so translated
	 * text is only built when get_features() reads it, not at registration.
	 * Malformed entries are silently skipped.
	 *
	 * First registration wins. Collision is checked on the normalized flag key
	 * so two slugs that normalize identically ("beta-search" and "beta search")
	 * are treated as duplicates and the second is rejected via _doing_it_wrong().
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
	 * Resolves in order: registry check → PHP constant → stored array value → default true.
	 * Unregistered slugs always return false (silently — no _doing_it_wrong()).
	 * String 'false' constants (a common wp-config mistake) are treated as false.
	 *
	 * @param string $flag Flag slug as passed to register().
	 */
	public function is_enabled( string $flag ): bool {
		// Registry is authoritative: unregistered flags are always off.
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
	 * @return bool True if the change was persisted; false if the flag is not registered.
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
	 * Unlike the old per-flag approach there's no update_option() no-op edge
	 * case: we're always writing a changed array (the key is added or toggled),
	 * and update_option() calls add_option() internally when the row is absent.
	 *
	 * A PHP constant override still wins at read time regardless of what's stored.
	 *
	 * @param string $flag Flag slug.
	 *
	 * @return bool True if the change was persisted; false if the flag is not registered.
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
	 * The single WP option name that stores all flag states for this context.
	 *
	 * Format: {context}_features (e.g. my_plugin_features).
	 * Override this to change where flags are stored.
	 */
	public function shared_option_key(): string {
		$context = $this->normalize( $this->context );

		return '' === $context ? 'features' : $context . '_features';
	}

	/**
	 * Normalized array key for a flag within the stored option.
	 *
	 * Lowercases the slug and collapses any run of characters outside [a-z0-9-]
	 * to a single dash, preserving existing dashes. This keeps stored keys
	 * human-readable (e.g. dark-mode, beta-search) and consistent with typical
	 * WP slug conventions. Contrast with normalize(), which uses underscores for
	 * PHP option-name and constant-name contexts.
	 *
	 * @param string $flag Flag slug.
	 */
	public function flag_key( string $flag ): string {
		return trim( (string) preg_replace( '/[^a-z0-9-]+/', '-', strtolower( $flag ) ), '-' );
	}

	/**
	 * PHP constant name for a flag — define in wp-config.php to hard-lock it.
	 *
	 * Format: {CONTEXT}_FEATURE_{FLAG} (e.g. MY_PLUGIN_FEATURE_DARK_MODE).
	 *
	 * @param string $flag Flag slug.
	 */
	public function constant_name( string $flag ): string {
		$context = $this->normalize( $this->context );
		$prefix  = '' === $context ? 'FEATURE_' : strtoupper( $context ) . '_FEATURE_';

		// Derive the constant from flag_key() — the same partition used for storage
		// — so two slugs that get distinct storage keys can't collapse to one
		// constant. (normalize() collapses runs of dashes, so `beta-search` and
		// `beta--search` would otherwise share MY_PLUGIN_FEATURE_BETA_SEARCH and one
		// define() would lock both.) Unchanged for ordinary single-dash slugs.
		return $prefix . strtoupper( str_replace( '-', '_', $this->flag_key( $flag ) ) );
	}

	/**
	 * Lowercase + collapse anything outside [a-z0-9_] to a single underscore.
	 *
	 * Not sanitize_key() — that drops invalid characters instead of replacing them,
	 * so "my.plugin" and "myplugin" would silently share the same key.
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
