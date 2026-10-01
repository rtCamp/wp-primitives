<?php
/**
 * AbstractAbilityRegistrar tests for cores without the Abilities API.
 *
 * The registrar promises to be inert on WordPress older than 6.9: its hooks
 * never fire there, and calling its callbacks directly must still be a silent
 * no-op rather than a fatal "call to undefined function". These tests only run
 * on such cores (the 6.5 to 6.8 cells of the CI matrix) and are skipped on 6.9+.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractAbilityRegistrar;
use rtCamp\WPPrimitives\Tests\TestCase;

/**
 * Tests for AbstractAbilityRegistrar on pre-6.9 cores.
 */
final class AbstractAbilityRegistrarLegacyCoreTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( function_exists( 'wp_register_ability' ) || function_exists( 'wp_register_ability_category' ) ) {
			$this->markTestSkipped( 'Only meaningful on cores without the Abilities API (WordPress < 6.9).' );
		}
	}

	/**
	 * A registrar that records every seam the callbacks touch.
	 */
	private function make_recording_registrar(): AbstractAbilityRegistrar {
		return new class() extends AbstractAbilityRegistrar {
			/**
			 * Seams that were called, in order.
			 *
			 * @var array<int, string>
			 */
			public array $calls = [];

			protected function category_slug(): string {
				$this->calls[] = 'category_slug';
				return 'legacy-plugin';
			}

			protected function abilities(): array {
				$this->calls[] = 'abilities';
				return [];
			}

			protected function category_description(): string {
				$this->calls[] = 'category_description';
				return 'Never registered.';
			}

			protected function before_register(): void {
				$this->calls[] = 'before_register';
			}
		};
	}

	public function test_register_category_is_a_no_op_without_the_abilities_api(): void {
		$registrar = $this->make_recording_registrar();

		$registrar->register_category();

		$this->assertSame( [], $registrar->calls, 'no seam may run when the API is missing' );
	}

	public function test_register_abilities_is_a_no_op_without_the_abilities_api(): void {
		$registrar = $this->make_recording_registrar();

		$registrar->register_abilities();

		$this->assertSame( [], $registrar->calls, 'before_register() and abilities() must not run when the API is missing' );
	}

	public function test_hooks_are_still_added_so_a_later_core_upgrade_picks_them_up(): void {
		$registrar = $this->make_recording_registrar();

		$registrar->register_hooks();

		$this->assertSame( 10, has_action( 'wp_abilities_api_categories_init', [ $registrar, 'register_category' ] ) );
		$this->assertSame( 10, has_action( 'wp_abilities_api_init', [ $registrar, 'register_abilities' ] ) );
		$this->assertSame( [], $registrar->calls, 'adding the hooks must not touch any seam' );
	}
}
