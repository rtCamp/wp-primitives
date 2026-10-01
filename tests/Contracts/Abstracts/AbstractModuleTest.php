<?php
/**
 * AbstractModule tests.
 *
 * The module is an orchestrator: it has no WordPress registration of its own,
 * it delegates to the Registrable classes from get_classes() via the Loader
 * trait. Verified with the shared Loader fixtures.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractModule;
use rtCamp\WPPrimitives\Tests\Fixtures\PlainRegistrable;
use rtCamp\WPPrimitives\Tests\Fixtures\ShareableRegistrable;
use rtCamp\WPPrimitives\Tests\TestCase;

final class AbstractModuleTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		PlainRegistrable::$registered     = false;
		ShareableRegistrable::$registered = false;
	}

	public function test_register_hooks_registers_each_managed_class(): void {
		$module = new class() extends AbstractModule {
			protected function get_classes(): array {
				return [ PlainRegistrable::class ];
			}
		};

		$module->register_hooks();

		$this->assertTrue( PlainRegistrable::$registered );
	}

	public function test_shareable_managed_class_is_cached_and_retrievable(): void {
		$module = new class() extends AbstractModule {
			protected function get_classes(): array {
				return [ ShareableRegistrable::class ];
			}
		};

		$module->register_hooks();

		$this->assertInstanceOf(
			ShareableRegistrable::class,
			$module->get_shared( ShareableRegistrable::class )
		);
	}
}
