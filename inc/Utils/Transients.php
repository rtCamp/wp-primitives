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
 * Wraps WordPress's transient API with a per-instance key prefix, so two modules
 * living in the same PHP process that happen to pick the same logical key never
 * clobber each other — the classic `set_transient( 'user_count', … )` collision.
 *
 * This is the one utility here that is multi-instance out of necessity rather than
 * convenience: there is no shared/singleton form, because isolated key namespaces
 * are the entire point. Each consumer constructs its own wrapper with a unique
 * prefix, and every key is namespaced before it reaches WordPress:
 *
 *     $store = new Transients( 'my-module' );
 *     $store->set( 'user_count', 42, HOUR_IN_SECONDS );
 *     $store->get( 'user_count' ); // 42 — isolated from any other module's 'user_count'.
 *
 * Regular (single-site) transients only; a multisite / site-transient variant can
 * arrive later by overriding the {@see resolve_key()} seam, with no breaking change.
 * Not `final`, and internal calls go through `$this` so such overrides take effect —
 * the same extension pattern as {@see Cache::resolve_group()}.
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
	 * @param string $prefix Per-instance namespace prepended to every key. A
	 *                       construction-time identity — readonly, not runtime-mutable.
	 */
	public function __construct(
		protected readonly string $prefix,
	) {}

	/**
	 * Get a transient value.
	 *
	 * Returns `mixed` because transients can hold any serialisable value (mirrors
	 * WordPress's own `get_transient()` signature).
	 *
	 * @param string $key Logical key (namespaced before lookup).
	 *
	 * @return mixed Stored value, or `false` if missing or expired.
	 */
	public function get( string $key ): mixed {
		return get_transient( $this->resolve_key( $key ) );
	}

	/**
	 * Set a transient with an optional expiration.
	 *
	 * @param string $key        Logical key (namespaced before write).
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
	 * @param string $key Logical key (namespaced before delete).
	 *
	 * @return bool True if the transient was deleted, false otherwise.
	 */
	public function delete( string $key ): bool {
		return delete_transient( $this->resolve_key( $key ) );
	}

	/**
	 * Namespace a logical key with this instance's prefix.
	 *
	 * Format: `<strlen(prefix)>:<prefix>_<key>`. The leading length header is
	 * digits-only and terminated by the first `:`, so the prefix/key boundary stays
	 * unambiguous even when either part contains underscores. Without it,
	 * `( 'mod', 'a_b' )` and `( 'mod_a', 'b' )` would both collapse to `mod_a_b` and
	 * silently target the same WordPress transient.
	 *
	 * A namespaced key longer than {@see Transients::MAX_NAME_LENGTH} is replaced by
	 * `h:<md5 of the namespaced key>`. WordPress cannot store such a name: the
	 * option rows are truncated, so the value is never found again (or, just past
	 * the limit, its expiry is silently lost). Keys within the limit are unchanged,
	 * and a hashed key can never equal an unhashed one, which always starts with
	 * digits.
	 *
	 * Protected override seam (mirrors {@see Cache::resolve_group()}): subclass to
	 * change the namespacing scheme, for instance a multisite / site-transient variant.
	 *
	 * @param string $key Logical key supplied by the caller.
	 *
	 * @return string Fully namespaced key passed to WordPress's transient API.
	 */
	protected function resolve_key( string $key ): string {
		$name = strlen( $this->prefix ) . ':' . $this->prefix . '_' . $key;

		return strlen( $name ) > static::MAX_NAME_LENGTH ? 'h:' . md5( $name ) : $name;
	}
}
