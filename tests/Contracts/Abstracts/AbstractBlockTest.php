<?php
/**
 * AbstractBlock tests.
 *
 * Integration tests against real WordPress (wp-env): asserts the block is
 * registered with WP_Block_Type_Registry and wired to the instance's render
 * callback.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractBlock;
use rtCamp\WPPrimitives\Tests\TestCase;
use WP_Block_Type_Registry;

final class AbstractBlockTest extends TestCase {

	private const BLOCK_NAME = 'wp-primitives-test/block';

	private function block(): AbstractBlock {
		return new class() extends AbstractBlock {
			public static function get_name(): string {
				return 'wp-primitives-test/block';
			}

			public function render( array $attributes, string $content, \WP_Block $block ): string {
				return '<div class="wpf-block">rendered</div>';
			}
		};
	}

	public function tear_down(): void {
		if ( WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME ) ) {
			unregister_block_type( self::BLOCK_NAME );
		}

		parent::tear_down();
	}

	public function test_register_hooks_registers_block_on_init(): void {
		$block = $this->block();
		$block->register_hooks();

		$this->assertNotFalse( has_action( 'init', [ $block, 'register_block' ] ) );
	}

	public function test_register_block_registers_the_block_type(): void {
		$this->block()->register_block();

		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( self::BLOCK_NAME )
		);
	}

	public function test_registered_block_uses_the_instance_render_callback(): void {
		$block = $this->block();
		$block->register_block();

		$type = WP_Block_Type_Registry::get_instance()->get_registered( self::BLOCK_NAME );

		$this->assertSame( [ $block, 'render' ], $type->render_callback );
	}

	public function test_render_returns_markup(): void {
		$block  = $this->block();
		$output = $block->render( [], '', new \WP_Block( [ 'blockName' => self::BLOCK_NAME ] ) );

		$this->assertSame( '<div class="wpf-block">rendered</div>', $output );
	}

	public function test_register_block_uses_block_dir_when_provided(): void {
		$dir = sys_get_temp_dir() . '/wpf-block-' . str_replace( '.', '', uniqid( '', true ) );
		mkdir( $dir, 0777, true );
		file_put_contents(
			$dir . '/block.json',
			'{"$schema":"https://schemas.wp.org/trunk/block.json","apiVersion":3,"name":"wp-primitives-test/dir-block","title":"Dir Block","category":"widgets"}'
		);

		$block = new class( $dir ) extends AbstractBlock {
			public function __construct( private string $dir ) {}

			public static function get_name(): string {
				return 'wp-primitives-test/dir-block';
			}

			public function render( array $attributes, string $content, \WP_Block $block ): string {
				return '';
			}

			protected function get_block_dir(): ?string {
				return $this->dir;
			}
		};

		$block->register_block();

		$this->assertTrue(
			WP_Block_Type_Registry::get_instance()->is_registered( 'wp-primitives-test/dir-block' )
		);

		unregister_block_type( 'wp-primitives-test/dir-block' );
		unlink( $dir . '/block.json' );
		rmdir( $dir );
	}
}
