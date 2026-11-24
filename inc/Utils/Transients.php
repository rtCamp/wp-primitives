<?php
/**
 * Transients utility.
 *
 * @package rtCamp\WPPrimitives\Utils
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Utils;

/**
 * Class - Transients
 *
 * Wraps the WordPress transient API and prefixes every key, so two instances
 * with different prefixes can use the same key.
 *
 *     $store = new Transients( 'my-module' );
 *     $store->set( 'user_count', 42, HOUR_IN_SECONDS );
 *     $store->get( 'user_count' ); // 42
 *
 * Uses regular (single-site) transients.
 *
 * @since 1.0.0
 */
class Transients {
	/**
	 * Longest transient name WordPress can store.
	 *
	 * A DB-backed transient is two option rows, `_transient_{name}` and
	 * `_transient_timeout_{name}`, and `option_name` is varchar(191), so the name
	 * itself must be 172 characters or fewer (see set_transient()).
	 */
	protected const MAX_NAME_LENGTH = 172;

	/**
	 * Construct a Transients wrapper bound to a key prefix.
	 *
	 * @param string $prefix Prefix added to every key.
	 */
	public function __construct(
		protected readonly string $prefix,
	) {}

	/**
	 * Get a transient value.
	 *
	 * @param string $key Unprefixed key.
	 *
	 * @return mixed Stored value, or `false` if missing or expired.
	 */
	public function get( string $key ): mixed {
		return get_transient( $this->resolve_key( $key ) );
	}

	/**
	 * Set a transient with an optional expiration.
	 *
	 * @param string $key        Unprefixed key.
	 * @param mixed  $value      Value to store. Must be serialisable.
	 * @param int    $expiration TTL in seconds. Defaults to one day; `0` means no expiry.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function set( string $key, mixed $value, int $expiration = DAY_IN_SECONDS ): bool {
		return set_transient( $this->resolve_key( $key ), $value, $expiration );
	}

	/**
	 * Delete a transient.
	 *
	 * @param string $key Unprefixed key.
	 *
	 * @return bool True if the transient was deleted, false otherwise.
	 */
	public function delete( string $key ): bool {
		return delete_transient( $this->resolve_key( $key ) );
	}

	/**
	 * Prefix a key with this instance's prefix.
	 *
	 * Format: `<strlen(prefix)>:<prefix>_<key>`.
	 * The length makes the boundary between prefix and key unambiguous, so `( 'mod', 'a_b' )`
	 * and `( 'mod_a', 'b' )` give different names.
	 *
	 * A name longer than {@see Transients::MAX_NAME_LENGTH} is replaced by
	 * `h:<md5 of the name>`, because WordPress truncates the option rows and
	 * would not find the value again. A hashed name cannot equal an unhashed one,
	 * which always starts with a digit.
	 *
	 * @param string $key Unprefixed key.
	 *
	 * @return string Transient name passed to WordPress.
	 */
	protected function resolve_key( string $key ): string {
		$name = strlen( $this->prefix ) . ':' . $this->prefix . '_' . $key;

		return strlen( $name ) > static::MAX_NAME_LENGTH ? 'h:' . md5( $name ) : $name;
	}
}
