<?php
/**
 * AbstractShortcode tests.
 *
 * Integration tests against real WordPress (wp-env): asserts the tag is
 * registered and that do_shortcode() renders through the callback with the
 * default attributes merged.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractShortcode;
use rtCamp\WPPrimitives\Tests\TestCase;

final class AbstractShortcodeTest extends TestCase {

	private const TAG = 'wpf_test_sc';

	private function shortcode(): AbstractShortcode {
		return new class() extends AbstractShortcode {
			public static function get_tag(): string {
				return 'wpf_test_sc';
			}

			protected function default_atts(): array {
				return [ 'foo' => 'bar' ];
			}

			protected function render( array $atts, ?string $content ): string {
				return ( $content ?? '' ) . '|' . $atts['foo'];
			}
		};
	}

	public function tear_down(): void {
		remove_shortcode( self::TAG );

		parent::tear_down();
	}

	public function test_register_hooks_registers_shortcode_on_init(): void {
		$shortcode = $this->shortcode();
		$shortcode->register_hooks();

		$this->assertNotFalse( has_action( 'init', [ $shortcode, 'register_shortcode' ] ) );
	}

	public function test_register_shortcode_adds_the_tag(): void {
		$this->shortcode()->register_shortcode();

		$this->assertTrue( shortcode_exists( self::TAG ) );
	}

	public function test_renders_content_and_merges_default_atts(): void {
		$this->shortcode()->register_shortcode();

		$this->assertSame( 'hi|bar', do_shortcode( '[wpf_test_sc]hi[/wpf_test_sc]' ) );
	}

	public function test_explicit_atts_override_defaults(): void {
		$this->shortcode()->register_shortcode();

		$this->assertSame( 'hi|baz', do_shortcode( '[wpf_test_sc foo="baz"]hi[/wpf_test_sc]' ) );
	}

	public function test_default_atts_are_empty_when_not_overridden(): void {
		$shortcode = new class() extends AbstractShortcode {
			public static function get_tag(): string {
				return 'wpf_test_sc';
			}

			protected function render( array $atts, ?string $content ): string {
				return wp_json_encode( $atts );
			}
		};

		$shortcode->register_shortcode();

		$this->assertSame( '[]', do_shortcode( '[wpf_test_sc]' ) );
	}
}
