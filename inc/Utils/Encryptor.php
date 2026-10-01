<?php
/**
 * WordPress-safe encryption utility.
 *
 * Useful for encrypting sensitive data before storing it in the database.
 *
 * @package rtCamp\WPPrimitives\Utils
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Utils;

/**
 * Class - Encryptor
 *
 * Instance-based, configured with a key (and optional cipher) at construction —
 * so different domains can use different keys, and it is trivially testable.
 *
 * Designed to be a service: construct it with a key, register an instance as
 * Shareable in a consumer's container, or extend it to change the cipher or the
 * key source (e.g. KMS) by overriding the {@see Encryptor::key()} seam.
 *
 * @since 1.0.0
 */
class Encryptor {

	/**
	 * GCM authentication tag length in bytes.
	 */
	protected const TAG_LENGTH = 16;

	/**
	 * IV length for GCM mode in bytes.
	 */
	protected const IV_LENGTH = 12;

	/**
	 * Constructor.
	 *
	 * The payload layout (IV + auth tag + ciphertext) and the tag handling are
	 * specific to GCM, so only GCM ciphers are accepted.
	 *
	 * @param string $key    Encryption key. May be left empty by a subclass that
	 *                       overrides {@see Encryptor::key()} to source it elsewhere.
	 * @param string $cipher OpenSSL GCM cipher method. Default 'aes-256-gcm'.
	 *
	 * @throws \InvalidArgumentException If $cipher is not a GCM cipher.
	 */
	public function __construct(
		protected string $key = '',
		protected string $cipher = 'aes-256-gcm',
	) {
		if ( ! str_ends_with( strtolower( $cipher ), '-gcm' ) ) {
			throw new \InvalidArgumentException(
				'Encryptor only supports GCM ciphers (the payload layout is GCM-specific); got: ' . $cipher // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Not rendered to the browser.
			);
		}
	}

	/**
	 * Encrypt a value using authenticated encryption (AES-256-GCM by default).
	 *
	 * @param string $raw_value The value to encrypt.
	 *
	 * @return string|false The encrypted value, or false on failure.
	 *
	 * @throws \RuntimeException If no encryption key is available.
	 */
	public function encrypt( string $raw_value ): string|false {
		if ( ! extension_loaded( 'openssl' ) ) {
			_doing_it_wrong(
				__METHOD__,
				'OpenSSL extension is not loaded. Encryption cannot proceed.',
				'1.0.0',
			);
			return false;
		}

		$iv  = random_bytes( static::IV_LENGTH );
		$tag = '';

		$value = openssl_encrypt(
			$raw_value,
			$this->cipher,
			$this->cipher_key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag,
			'',
			static::TAG_LENGTH
		);

		return false !== $value ? base64_encode( $iv . $tag . $value ) : false;
	}

	/**
	 * Decrypt a value produced by {@see Encryptor::encrypt()}.
	 *
	 * @param string $raw_value The encrypted value.
	 *
	 * @return string|false The decrypted value, or false on failure/tampering.
	 *
	 * @throws \RuntimeException If no encryption key is available.
	 */
	public function decrypt( string $raw_value ): string|false {
		if ( ! extension_loaded( 'openssl' ) ) {
			_doing_it_wrong(
				__METHOD__,
				'OpenSSL extension is not loaded. Decryption cannot proceed.',
				'1.0.0',
			);
			return false;
		}

		$decoded_value = base64_decode( $raw_value, true );
		if ( false === $decoded_value ) {
			// Don't leak potentially sensitive data, e.g. an unencrypted value that was accidentally passed in.
			_doing_it_wrong(
				__METHOD__,
				'Invalid input: not a valid base64-encoded string.',
				'1.0.0',
			);
			return false;
		}

		$iv         = substr( $decoded_value, 0, static::IV_LENGTH );
		$tag        = substr( $decoded_value, static::IV_LENGTH, static::TAG_LENGTH );
		$ciphertext = substr( $decoded_value, static::IV_LENGTH + static::TAG_LENGTH );

		return openssl_decrypt(
			$ciphertext,
			$this->cipher,
			$this->cipher_key(),
			OPENSSL_RAW_DATA,
			$iv,
			$tag
		);
	}

	/**
	 * Resolve the raw secret.
	 *
	 * Override seam: a subclass can source the secret from elsewhere (KMS, a
	 * rotated secret, an env var) without touching the crypto. Must never return
	 * an empty secret — encryption must not proceed with a weak/missing key.
	 *
	 * The return value is a *secret of any length*, not a cipher key: the
	 * derivation in {@see Encryptor::cipher_key()} turns it into one, so an
	 * override cannot accidentally hand OpenSSL a wrong-length key.
	 *
	 * @return string The raw secret.
	 *
	 * @throws \RuntimeException If no secret is available.
	 */
	protected function key(): string {
		if ( '' === $this->key ) {
			throw new \RuntimeException(
				'No encryption key provided. Pass a key to the Encryptor constructor or override Encryptor::key().'
			);
		}

		return $this->key;
	}

	/**
	 * Derive the cipher key actually handed to OpenSSL.
	 *
	 * Deliberately private and not part of the override seam: the derivation is
	 * a correctness guarantee for every key source, including subclasses that
	 * override {@see Encryptor::key()}. Were this folded into that seam, a KMS-
	 * or env-backed override would bypass it and fall back to OpenSSL's silent
	 * handling of a wrong-length key.
	 *
	 * OpenSSL NUL-pads a too-short key and truncates a too-long one, which would
	 * quietly weaken the cipher (a 16-byte secret becomes 16 real bytes + 16 zero
	 * bytes for AES-256) and make rotating only the tail of a long secret a no-op.
	 * Hashing maps any-length secret to a full-strength, fixed-length key
	 * deterministically. Only reached from encrypt()/decrypt(), which have already
	 * confirmed the OpenSSL extension is loaded.
	 *
	 * @return string Key of exactly the cipher's key length.
	 *
	 * @throws \RuntimeException If no secret is available.
	 */
	private function cipher_key(): string {
		$key_length = (int) openssl_cipher_key_length( $this->cipher );

		return substr( hash( 'sha256', $this->key(), true ), 0, $key_length );
	}
}
