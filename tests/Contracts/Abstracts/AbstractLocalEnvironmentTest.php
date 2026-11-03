<?php
/**
 * AbstractLocalEnvironment tests.
 *
 * Detection belongs to the concrete adapter, so these cover the base's
 * defaults — debug-log path, services, filesystem writability — and that an
 * adapter's overrides win. Anonymous subclasses keep each scenario
 * self-contained.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractLocalEnvironment;
use rtCamp\WPPrimitives\Tests\TestCase;

/**
 * Tests for AbstractLocalEnvironment.
 *
 * @since 1.0.0
 */
final class AbstractLocalEnvironmentTest extends TestCase {

	/**
	 * Bare adapter that declares only the two required members.
	 *
	 * @param bool $active is_active() return value.
	 */
	private function make_environment( bool $active = true ): AbstractLocalEnvironment {
		return new class( $active ) extends AbstractLocalEnvironment {
			public function __construct( private readonly bool $active ) {}

			public function get_name(): string {
				return 'bare-env';
			}

			public function is_active(): bool {
				return $this->active;
			}
		};
	}

	/**
	 * Adapter that overrides every seam.
	 */
	private function make_custom_environment(): AbstractLocalEnvironment {
		return new class() extends AbstractLocalEnvironment {
			public function get_name(): string {
				return 'custom-env';
			}

			public function is_active(): bool {
				return true;
			}

			public function get_debug_log_path(): string {
				return '/srv/logs/custom.log';
			}

			public function get_available_services(): array {
				return [ 'memcached', 'elasticsearch' ];
			}

			public function is_filesystem_writable(): bool {
				return false;
			}
		};
	}

	public function test_identity_and_detection_come_from_the_subclass(): void {
		$this->assertSame( 'bare-env', $this->make_environment()->get_name() );
		$this->assertTrue( $this->make_environment()->is_active() );
		$this->assertFalse( $this->make_environment( false )->is_active() );
	}

	public function test_debug_log_path_defaults_to_wp_content(): void {
		$this->assertSame(
			WP_CONTENT_DIR . '/debug.log',
			$this->make_environment()->get_debug_log_path()
		);
	}

	public function test_services_default_to_none(): void {
		$this->assertSame( [], $this->make_environment()->get_available_services() );
	}

	public function test_filesystem_defaults_to_writable(): void {
		$this->assertTrue( $this->make_environment()->is_filesystem_writable() );
	}

	public function test_overrides_propagate(): void {
		$environment = $this->make_custom_environment();

		$this->assertSame( '/srv/logs/custom.log', $environment->get_debug_log_path() );
		$this->assertSame( [ 'memcached', 'elasticsearch' ], $environment->get_available_services() );
		$this->assertFalse( $environment->is_filesystem_writable() );
	}
}
