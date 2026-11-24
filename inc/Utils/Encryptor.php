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
 * Each instance has its own key and cipher. To read the key from another source such as a KMS,
 * override {@see Encryptor::key()}.
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
		if ( ! $this->is_openssl_available() ) {
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
		if ( ! $this->is_openssl_available() ) {
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

		// A valid payload always carries a full IV and a full-length tag. Reject
		// anything shorter before it reaches OpenSSL: GCM verifies a truncated tag
		// at its truncated length, so a 13-byte payload (IV + 1 tag byte, empty
		// ciphertext) would decrypt to '' for 1 in 256 forged tags.
		if ( strlen( $decoded_value ) < static::IV_LENGTH + static::TAG_LENGTH ) {
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
	 * Whether the OpenSSL extension is available.
	 *
	 * Tests override this to cover the path where encrypt() and decrypt() return false.
	 *
	 * @return bool True when OpenSSL can be used.
	 */
	protected function is_openssl_available(): bool {
		return extension_loaded( 'openssl' );
	}

	/**
	 * Resolve the raw secret.
	 *
	 * Subclasses override this to read the secret from another source, such as a KMS or an environment variable.
	 * Overrides must throw rather than return an empty string.
	 *
	 * The secret can be any length. {@see Encryptor::cipher_key()} derives the cipher key from it.
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
	 * Derive the cipher key passed to OpenSSL.
	 *
	 * OpenSSL NUL-pads a short key and truncates a long one. For AES-256, a
	 * 16-byte secret would become 16 secret bytes and 16 zero bytes, and
	 * changing only the tail of a long secret would have no effect. Hashing
	 * turns a secret of any length into a key of the cipher's length.
	 *
	 * This method is private so that overrides of {@see Encryptor::key()} still
	 * go through the derivation.
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
