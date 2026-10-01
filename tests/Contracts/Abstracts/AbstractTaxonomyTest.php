<?php
/**
 * AbstractTaxonomy tests.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractTaxonomy;
use rtCamp\WPPrimitives\Tests\TestCase;

final class AbstractTaxonomyTest extends TestCase {

	/**
	 * Build a basic concrete AbstractTaxonomy.
	 */
	private function basic_taxonomy(): AbstractTaxonomy {
		return new class() extends AbstractTaxonomy {
			public static function get_slug(): string {
				return 'genre';
			}
			public static function get_object_types(): array {
				return [ 'book' ];
			}
			public function get_singular_label(): string {
				return 'Genre';
			}
			public function get_plural_label(): string {
				return 'Genres';
			}
		};
	}

	public function test_get_options_contains_documented_defaults(): void {
		$options = $this->basic_taxonomy()->get_options();

		$this->assertFalse( $options['hierarchical'] );
		$this->assertTrue( $options['public'] );
		$this->assertTrue( $options['show_in_rest'] );
		$this->assertTrue( $options['show_admin_column'] );
		$this->assertArrayHasKey( 'labels', $options );
	}

	public function test_get_labels_uses_singular_and_plural(): void {
		$labels = $this->basic_taxonomy()->get_labels();

		$this->assertSame( 'Genres', $labels['name'] );
		$this->assertSame( 'Genre', $labels['singular_name'] );
	}

	public function test_custom_options_override_defaults(): void {
		$tax = new class() extends AbstractTaxonomy {
			public static function get_slug(): string {
				return 'category';
			}
			public static function get_object_types(): array {
				return [ 'post' ];
			}
			public function get_singular_label(): string {
				return 'Category';
			}
			public function get_plural_label(): string {
				return 'Categories';
			}
			public function is_hierarchical(): bool {
				return true;
			}
			protected function get_custom_options(): array {
				return [
					'show_admin_column' => false,
					'rewrite'           => [ 'slug' => 'cat' ],
				];
			}
		};

		$options = $tax->get_options();

		$this->assertTrue( $options['hierarchical'] );
		$this->assertFalse( $options['show_admin_column'] );
		$this->assertSame( [ 'slug' => 'cat' ], $options['rewrite'] );
	}

	public function test_static_get_slug_and_object_types_are_accessible_without_instance(): void {
		// One of the reasons we kept get_slug() static — code that wires the
		// taxonomy into other systems shouldn't need to instantiate it first.
		$tax = $this->basic_taxonomy();

		$this->assertSame( 'genre', $tax::get_slug() );
		$this->assertSame( [ 'book' ], $tax::get_object_types() );
	}

	public function tear_down(): void {
		if ( taxonomy_exists( 'genre' ) ) {
			unregister_taxonomy( 'genre' );
		}

		parent::tear_down();
	}

	public function test_register_hooks_registers_taxonomy_on_init(): void {
		$tax = $this->basic_taxonomy();
		$tax->register_hooks();

		$this->assertNotFalse( has_action( 'init', [ $tax, 'register' ] ) );
	}

	public function test_register_actually_registers_the_taxonomy(): void {
		$this->basic_taxonomy()->register();

		$this->assertTrue( taxonomy_exists( 'genre' ) );

		$object = get_taxonomy( 'genre' );
		$this->assertContains( 'book', $object->object_type );
		$this->assertTrue( $object->show_in_rest );
	}
}
