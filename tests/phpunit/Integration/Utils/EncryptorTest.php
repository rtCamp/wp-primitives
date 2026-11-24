<?php
/**
 * Encryptor tests.
 *
 * @package rtCamp\WPPrimitives\Tests\Integration\Utils
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Integration\Utils;

use rtCamp\WPPrimitives\Tests\TestCase;
use rtCamp\WPPrimitives\Utils\Encryptor;

/**
 * Tests for Encryptor.
 */
final class EncryptorTest extends TestCase {

	/**
	 * A deterministic 32-byte key for reproducible tests.
	 */
	private const KEY = 'kkkkkkkkkkkkkkkkkkkkkkkkkkkkkkkk';

	private function encryptor( string $key = self::KEY ): Encryptor {
		return new Encryptor( $key );
	}

	/**
	 * Return the key passed to OpenSSL.
	 *
	 * Encryptor::cipher_key() is private, so this calls it by reflection.
	 *
	 * @param Encryptor $encryptor Instance to derive for.
	 */
	private function derived_key( Encryptor $encryptor ): string {
		return ( new \ReflectionMethod( Encryptor::class, 'cipher_key' ) )->invoke( $encryptor );
	}

	public function test_encrypt_decrypt_roundtrips_plaintext(): void {
		$encryptor = $this->encryptor();
		$plaintext = 'sensitive-secret-value';

		$encrypted = $encryptor->encrypt( $plaintext );

		$this->assertIsString( $encrypted );
		$this->assertNotSame( $plaintext, $encrypted );
		$this->assertSame( $plaintext, $encryptor->decrypt( $encrypted ) );
	}

	public function test_each_encryption_produces_different_ciphertext(): void {
		// GCM uses a random IV per call.
		$encryptor = $this->encryptor();

		$a = $encryptor->encrypt( 'hello world' );
		$b = $encryptor->encrypt( 'hello world' );

		$this->assertIsString( $a );
		$this->assertIsString( $b );
		$this->assertNotSame( $a, $b );
	}

	public function test_value_encrypted_with_one_key_does_not_decrypt_with_another(): void {
		$encrypted = $this->encryptor( str_repeat( 'a', 32 ) )->encrypt( 'secret' );
		$this->assertIsString( $encrypted );

		// A different key (different instance) must not authenticate the tag.
		$this->assertFalse( $this->encryptor( str_repeat( 'b', 32 ) )->decrypt( $encrypted ) );
	}

	public function test_decrypt_returns_false_for_invalid_base64(): void {
		$this->setExpectedIncorrectUsage( Encryptor::class . '::decrypt' );

		$this->assertFalse( $this->encryptor()->decrypt( 'not!valid!base64' ) );
	}

	public function test_decrypt_returns_false_for_tampered_ciphertext(): void {
		$encryptor = $this->encryptor();
		$encrypted = $encryptor->encrypt( 'secret' );
		$this->assertIsString( $encrypted );

		// Flip a byte in the middle of the payload.
		$decoded     = base64_decode( $encrypted, true );
		$decoded[20] = 'A' === $decoded[20] ? 'B' : 'A';
		$tampered    = base64_encode( $decoded );

		$this->assertFalse( $encryptor->decrypt( $tampered ) );
	}

	public function test_decrypt_rejects_a_truncated_tag_for_every_tag_value(): void {
		$encryptor = $this->encryptor();
		$iv        = str_repeat( "\x01", 12 );

		for ( $byte = 0; $byte < 256; $byte++ ) {
			$forged = base64_encode( $iv . chr( $byte ) );

			$this->assertFalse( $encryptor->decrypt( $forged ), sprintf( 'Tag byte 0x%02x was accepted.', $byte ) );
		}
	}

	/**
	 * @dataProvider data_short_payload_lengths
	 *
	 * @param int $length Decoded payload length, shorter than IV + tag.
	 */
	public function test_decrypt_rejects_payloads_shorter_than_iv_plus_tag( int $length ): void {
		$this->assertFalse( $this->encryptor()->decrypt( base64_encode( str_repeat( 'x', $length ) ) ) );
	}

	/**
	 * @return array<string, array{int}>
	 */
	public function data_short_payload_lengths(): array {
		return [
			'empty'               => [ 0 ],
			'shorter than the IV' => [ 5 ],
			'IV only'             => [ 12 ],
			'IV plus partial tag' => [ 27 ],
		];
	}

	public function test_empty_plaintext_roundtrips(): void {
		// The smallest valid payload is the IV plus a full tag, with no ciphertext.
		$encryptor = $this->encryptor();
		$encrypted = $encryptor->encrypt( '' );

		$this->assertIsString( $encrypted );
		$this->assertSame( 28, strlen( (string) base64_decode( $encrypted, true ) ) );
		$this->assertSame( '', $encryptor->decrypt( $encrypted ) );
	}

	/**
	 * An Encryptor that behaves as if the OpenSSL extension were missing.
	 */
	private function encryptor_without_openssl(): Encryptor {
		return new class( self::KEY ) extends Encryptor {
			protected function is_openssl_available(): bool {
				return false;
			}
		};
	}

	public function test_encrypt_fails_closed_without_openssl(): void {
		$encryptor = $this->encryptor_without_openssl();
		$this->setExpectedIncorrectUsage( Encryptor::class . '::encrypt' );

		$this->assertFalse( $encryptor->encrypt( 'secret' ) );
	}

	public function test_decrypt_fails_closed_without_openssl(): void {
		$encrypted = $this->encryptor()->encrypt( 'secret' );
		$this->assertIsString( $encrypted );

		$encryptor = $this->encryptor_without_openssl();
		$this->setExpectedIncorrectUsage( Encryptor::class . '::decrypt' );

		$this->assertFalse( $encryptor->decrypt( $encrypted ) );
	}

	public function test_key_can_be_overridden_by_a_subclass(): void {
		$encryptor = new class() extends Encryptor {
			protected function key(): string {
				return str_repeat( 'z', 32 );
			}
		};

		$encrypted = $encryptor->encrypt( 'from-subclass' );
		$this->assertIsString( $encrypted );
		$this->assertSame( 'from-subclass', $encryptor->decrypt( $encrypted ) );
	}

	public function test_missing_key_throws(): void {
		$this->expectException( \RuntimeException::class );

		( new Encryptor() )->encrypt( 'no key configured' );
	}

	public function test_non_gcm_cipher_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );

		new Encryptor( self::KEY, 'aes-256-cbc' );
	}

	public function test_alternate_gcm_cipher_roundtrips(): void {
		$encryptor = new Encryptor( self::KEY, 'aes-128-gcm' );

		$encrypted = $encryptor->encrypt( 'gcm variant' );
		$this->assertIsString( $encrypted );
		$this->assertSame( 'gcm variant', $encryptor->decrypt( $encrypted ) );
	}

	/**
	 * @dataProvider data_secret_lengths
	 *
	 * @param string $secret Secret of a length other than the cipher key length.
	 */
	public function test_any_length_secret_derives_a_full_cipher_length_key( string $secret ): void {
		// OpenSSL NUL-pads a short key and truncates a long one, so the secret is hashed to the cipher key length first.
		$this->assertSame( 32, strlen( $this->derived_key( new Encryptor( $secret ) ) ) );

		$encryptor = $this->encryptor( $secret );
		$encrypted = $encryptor->encrypt( 'any-length secret' );

		$this->assertIsString( $encrypted );
		$this->assertSame( 'any-length secret', $encryptor->decrypt( $encrypted ) );
	}

	/**
	 * @return array<string, array{string}>
	 */
	public function data_secret_lengths(): array {
		return [
			'single byte'      => [ 'k' ],
			'shorter than key' => [ 'short-secret' ],
			'exactly key size' => [ self::KEY ],
			'longer than key'  => [ str_repeat( 'k', 100 ) ],
		];
	}

	public function test_derived_key_length_follows_the_cipher(): void {
		$this->assertSame( 16, strlen( $this->derived_key( new Encryptor( 'short-secret', 'aes-128-gcm' ) ) ) );
		$this->assertSame( 32, strlen( $this->derived_key( new Encryptor( 'short-secret', 'aes-256-gcm' ) ) ) );
	}

	public function test_secrets_differing_only_past_the_key_length_are_not_equivalent(): void {
		// OpenSSL truncates an over-long key, so without hashing only the first 32 bytes of the secret would count.
		$shared = str_repeat( 'k', 32 );
		$first  = $shared . 'tail-one';
		$second = $shared . 'tail-two';

		$this->assertNotSame(
			$this->derived_key( new Encryptor( $first ) ),
			$this->derived_key( new Encryptor( $second ) )
		);

		$encrypted = $this->encryptor( $first )->encrypt( 'rotated' );
		$this->assertIsString( $encrypted );
		$this->assertFalse( $this->encryptor( $second )->decrypt( $encrypted ) );
	}

	public function test_subclass_key_override_still_goes_through_the_derivation(): void {
		$override = new class() extends Encryptor {
			protected function key(): string {
				return 'short';
			}
		};

		$this->assertSame( 32, strlen( $this->derived_key( $override ) ) );

		// The same secret derives the same key from key() or the constructor.
		$encrypted = $override->encrypt( 'via override' );
		$this->assertIsString( $encrypted );
		$this->assertSame( 'via override', $this->encryptor( 'short' )->decrypt( $encrypted ) );
	}

	public function test_subclass_key_override_of_an_over_long_secret_is_not_truncated(): void {
		$shared = str_repeat( 'k', 32 );

		$first = new class() extends Encryptor {
			protected function key(): string {
				return str_repeat( 'k', 32 ) . 'tail-one';
			}
		};

		$encrypted = $first->encrypt( 'override rotation' );
		$this->assertIsString( $encrypted );

		// Rotating only the tail of an over-long secret must actually change the key.
		$this->assertFalse( $this->encryptor( $shared . 'tail-two' )->decrypt( $encrypted ) );
		$this->assertSame( 'override rotation', $this->encryptor( $shared . 'tail-one' )->decrypt( $encrypted ) );
	}

	public function test_short_secret_is_not_equivalent_to_its_nul_padded_form(): void {
		// OpenSSL NUL-pads a short key, so without hashing 'secret' and its NUL-padded form would be the same key.
		$secret = 'secret';
		$padded = $secret . str_repeat( "\0", 32 - strlen( $secret ) );

		$this->assertNotSame(
			$this->derived_key( new Encryptor( $secret ) ),
			$this->derived_key( new Encryptor( $padded ) )
		);

		$encrypted = $this->encryptor( $secret )->encrypt( 'not padded' );
		$this->assertIsString( $encrypted );
		$this->assertFalse( $this->encryptor( $padded )->decrypt( $encrypted ) );
	}
}
