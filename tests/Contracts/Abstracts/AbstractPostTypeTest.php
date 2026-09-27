<?php
/**
 * AbstractPostType tests.
 *
 * Asserts the option-building contract (defaults, label assembly, the
 * get_custom_options() override merge) and the real WordPress registration:
 * register() actually registers the post type and associates its taxonomies.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractPostType;
use rtCamp\WPPrimitives\Tests\TestCase;

final class AbstractPostTypeTest extends TestCase {

	/**
	 * Build a basic concrete AbstractPostType.
	 */
	private function basic_post_type(): AbstractPostType {
		return new class() extends AbstractPostType {
			public static function get_slug(): string {
				return 'book';
			}
			public function get_singular_label(): string {
				return 'Book';
			}
			public function get_plural_label(): string {
				return 'Books';
			}
			public function get_menu_icon(): string {
				return 'dashicons-book';
			}
		};
	}

	public function test_get_options_contains_the_documented_defaults(): void {
		$options = $this->basic_post_type()->get_options();

		$this->assertTrue( $options['public'] );
		$this->assertTrue( $options['has_archive'] );
		$this->assertTrue( $options['show_in_rest'] );
		$this->assertTrue( $options['show_ui'] );
		$this->assertSame( 'dashicons-book', $options['menu_icon'] );
		$this->assertFalse( $options['hierarchical'] );
		$this->assertArrayHasKey( 'labels', $options );
	}

	public function test_get_labels_uses_singular_and_plural(): void {
		$labels = $this->basic_post_type()->get_labels();

		$this->assertSame( 'Books', $labels['name'] );
		$this->assertSame( 'Book', $labels['singular_name'] );
	}

	public function test_get_options_excludes_menu_position_when_null(): void {
		$options = $this->basic_post_type()->get_options();

		$this->assertArrayNotHasKey( 'menu_position', $options );
	}

	public function test_get_options_includes_menu_position_when_set(): void {
		$post_type = new class() extends AbstractPostType {
			public static function get_slug(): string {
				return 'book';
			}
			public function get_singular_label(): string {
				return 'Book';
			}
			public function get_plural_label(): string {
				return 'Books';
			}
			public function get_menu_icon(): string {
				return 'dashicons-book';
			}
			public function get_menu_position(): ?int {
				return 25;
			}
		};

		$this->assertSame( 25, $post_type->get_options()['menu_position'] );
	}

	public function test_custom_options_override_defaults_and_add_new_keys(): void {
		$post_type = new class() extends AbstractPostType {
			public static function get_slug(): string {
				return 'product';
			}
			public function get_singular_label(): string {
				return 'Product';
			}
			public function get_plural_label(): string {
				return 'Products';
			}
			public function get_menu_icon(): string {
				return 'dashicons-cart';
			}
			protected function get_custom_options(): array {
				return [
					'has_archive'     => false,         // Override.
					'capability_type' => 'product',     // New key.
				];
			}
		};

		$options = $post_type->get_options();

		$this->assertFalse( $options['has_archive'] );
		$this->assertSame( 'product', $options['capability_type'] );
		$this->assertTrue( $options['public'] ); // Untouched default.
	}

	public function test_is_hierarchical_defaults_to_false(): void {
		$this->assertFalse( $this->basic_post_type()->is_hierarchical() );
	}

	public function test_get_supported_taxonomies_defaults_to_empty_array(): void {
		$this->assertSame( [], $this->basic_post_type()->get_supported_taxonomies() );
	}

	public function test_get_editor_supports_returns_sane_defaults(): void {
		$supports = $this->basic_post_type()->get_editor_supports();

		$this->assertContains( 'title', $supports );
		$this->assertContains( 'editor', $supports );
		$this->assertContains( 'thumbnail', $supports );
	}

	public function tear_down(): void {
		if ( post_type_exists( 'book' ) ) {
			unregister_post_type( 'book' );
		}

		parent::tear_down();
	}

	public function test_register_hooks_registers_post_type_on_init(): void {
		$post_type = $this->basic_post_type();
		$post_type->register_hooks();

		$this->assertNotFalse( has_action( 'init', [ $post_type, 'register' ] ) );
	}

	public function test_register_actually_registers_the_post_type(): void {
		$this->basic_post_type()->register();

		$object = get_post_type_object( 'book' );
		$this->assertNotNull( $object );
		$this->assertTrue( $object->public );
		$this->assertTrue( $object->show_in_rest );
	}

	public function test_supported_taxonomies_are_associated_with_the_post_type(): void {
		register_taxonomy( 'wpf_genre', [], [ 'public' => true ] );

		$post_type = new class() extends AbstractPostType {
			public static function get_slug(): string {
				return 'book';
			}
			public function get_singular_label(): string {
				return 'Book';
			}
			public function get_plural_label(): string {
				return 'Books';
			}
			public function get_menu_icon(): string {
				return 'dashicons-book';
			}
			public function get_supported_taxonomies(): array {
				return [ 'wpf_genre' ];
			}
		};

		$post_type->register();

		$this->assertContains( 'wpf_genre', get_object_taxonomies( 'book' ) );

		unregister_taxonomy( 'wpf_genre' );
	}
}
