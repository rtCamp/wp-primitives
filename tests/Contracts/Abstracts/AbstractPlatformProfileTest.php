<?php
/**
 * AbstractPlatformProfile tests.
 *
 * The contract here is the defaults: every rule bucket is empty/permissive so
 * a profile that declares nothing constrains nothing. These cover both the
 * bare-profile defaults and an override of each bucket, using anonymous
 * subclasses so each scenario is self-contained.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractPlatformProfile;
use rtCamp\WPPrimitives\Tests\TestCase;

/**
 * Tests for AbstractPlatformProfile.
 *
 * @since 1.0.0
 */
final class AbstractPlatformProfileTest extends TestCase {

	/**
	 * Bare profile that declares only the two required members.
	 */
	private function make_profile(): AbstractPlatformProfile {
		return new class() extends AbstractPlatformProfile {
			public function get_slug(): string {
				return 'bare';
			}

			public function get_name(): string {
				return 'Bare Platform';
			}
		};
	}

	/**
	 * Profile that overrides every rule bucket.
	 */
	private function make_constrained_profile(): AbstractPlatformProfile {
		return new class() extends AbstractPlatformProfile {
			public function get_slug(): string {
				return 'constrained';
			}

			public function get_name(): string {
				return 'Constrained Platform';
			}

			public function get_restricted_functions(): array {
				return [ 'exec', 'shell_exec' ];
			}

			public function get_writable_paths(): ?array {
				return [ 'wp-content/uploads', '/tmp' ];
			}

			public function get_supported_php_versions(): array {
				return [ '8.2', '8.3' ];
			}

			public function get_object_cache_constraints(): array {
				return [
					'max_object_bytes' => 1048576,
					'backend'          => 'memcached',
				];
			}

			public function get_incompatible_plugins(): array {
				return [ 'some-plugin' ];
			}
		};
	}

	public function test_identity_comes_from_the_subclass(): void {
		$profile = $this->make_profile();

		$this->assertSame( 'bare', $profile->get_slug() );
		$this->assertSame( 'Bare Platform', $profile->get_name() );
	}

	public function test_rule_buckets_default_to_empty(): void {
		$profile = $this->make_profile();

		$this->assertSame( [], $profile->get_restricted_functions() );
		$this->assertSame( [], $profile->get_supported_php_versions() );
		$this->assertSame( [], $profile->get_incompatible_plugins() );
	}

	public function test_writable_paths_default_to_unconstrained_not_empty(): void {
		$this->assertNull( $this->make_profile()->get_writable_paths() );
	}

	public function test_writable_paths_can_declare_that_nothing_is_writable(): void {
		// The state [] cannot express: null is "no constraint declared".
		$profile = new class() extends AbstractPlatformProfile {
			public function get_slug(): string {
				return 'read-only';
			}

			public function get_name(): string {
				return 'Read-only Platform';
			}

			public function get_writable_paths(): ?array {
				return [];
			}
		};

		$this->assertSame( [], $profile->get_writable_paths() );
		$this->assertNotNull( $profile->get_writable_paths() );
	}

	public function test_object_cache_constraints_default_to_unconstrained(): void {
		$this->assertSame(
			[
				'max_object_bytes' => null,
				'backend'          => null,
			],
			$this->make_profile()->get_object_cache_constraints()
		);
	}

	public function test_overrides_propagate(): void {
		$profile = $this->make_constrained_profile();

		$this->assertSame( [ 'exec', 'shell_exec' ], $profile->get_restricted_functions() );
		$this->assertSame( [ 'wp-content/uploads', '/tmp' ], $profile->get_writable_paths() );
		$this->assertSame( [ '8.2', '8.3' ], $profile->get_supported_php_versions() );
		$this->assertSame( [ 'some-plugin' ], $profile->get_incompatible_plugins() );
		$this->assertSame(
			[
				'max_object_bytes' => 1048576,
				'backend'          => 'memcached',
			],
			$profile->get_object_cache_constraints()
		);
	}
}
