<?php
/**
 * AbstractFeature tests.
 *
 * AbstractFeature is a framework contract: the concrete FeatureSelector and
 * the abstract get_slug() / get_feature_registry() are the moving parts.
 * Tests use anonymous classes throughout so each scenario is self-contained.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractFeature;
use rtCamp\WPPrimitives\Contracts\Interfaces\ConditionallyRegistrable;
use rtCamp\WPPrimitives\Tests\TestCase;
use rtCamp\WPPrimitives\Utils\FeatureSelector;

/**
 * Tests for AbstractFeature.
 *
 * @since 0.0.1
 */
final class AbstractFeatureTest extends TestCase {

	/**
	 * Concrete AbstractFeature backed by a local FeatureSelector.
	 *
	 * Constructor promotion assigns $registry before parent::__construct() runs,
	 * so get_feature_registry() is safe to call from the parent constructor.
	 *
	 * @param FeatureSelector $registry    Selector instance to register into.
	 * @param string          $slug        Flag slug.
	 * @param string          $name        Optional override for get_name().
	 * @param string          $description Optional override for get_description().
	 */
	private function make_feature(
		FeatureSelector $registry,
		string $slug,
		string $name = '',
		string $description = ''
	): AbstractFeature {
		return new class( $registry, $slug, $name, $description ) extends AbstractFeature {
			public function __construct(
				private readonly FeatureSelector $registry,
				private readonly string $slug,
				private readonly string $name_override,
				private readonly string $desc_override
			) {
				parent::__construct();
			}

			protected function get_slug(): string {
				return $this->slug;
			}

			protected function get_feature_registry(): FeatureSelector {
				return $this->registry;
			}

			protected function get_name(): string {
				return '' !== $this->name_override ? $this->name_override : parent::get_name();
			}

			protected function get_description(): string {
				return '' !== $this->desc_override ? $this->desc_override : parent::get_description();
			}

			public function register_hooks(): void {}
		};
	}

	public function test_implements_conditionally_registrable(): void {
		$registry = new FeatureSelector( 'test' );
		$feature  = $this->make_feature( $registry, 'my-flag' );

		$this->assertInstanceOf( ConditionallyRegistrable::class, $feature );
	}

	public function test_construction_registers_the_flag_in_the_selector(): void {
		$registry = new FeatureSelector( 'test' );
		$this->make_feature( $registry, 'my-flag' );

		$this->assertContains( 'my-flag', $registry->get_registered() );
	}

	public function test_default_name_title_cases_the_slug(): void {
		$registry = new FeatureSelector( 'test' );
		$this->make_feature( $registry, 'author-bio' );

		$this->assertSame( 'Author Bio', $registry->get_features()['author-bio']['name'] );
	}

	public function test_custom_name_override_is_used(): void {
		$registry = new FeatureSelector( 'test' );
		$this->make_feature( $registry, 'my-flag', 'Custom Name' );

		$this->assertSame( 'Custom Name', $registry->get_features()['my-flag']['name'] );
	}

	public function test_default_description_is_empty(): void {
		$registry = new FeatureSelector( 'test' );
		$this->make_feature( $registry, 'my-flag' );

		$this->assertSame( '', $registry->get_features()['my-flag']['description'] );
	}

	public function test_custom_description_override_is_stored(): void {
		$registry = new FeatureSelector( 'test' );
		$this->make_feature( $registry, 'my-flag', '', 'Enables the widget.' );

		$this->assertSame( 'Enables the widget.', $registry->get_features()['my-flag']['description'] );
	}

	public function test_name_and_description_are_read_when_the_registry_is_read(): void {
		$registry = new FeatureSelector( 'test' );
		$feature  = new class( $registry ) extends AbstractFeature {
			public int $reads = 0;

			public function __construct(
				private readonly FeatureSelector $registry
			) {
				parent::__construct();
			}

			protected function get_slug(): string {
				return 'lazy-flag';
			}

			protected function get_feature_registry(): FeatureSelector {
				return $this->registry;
			}

			protected function get_name(): string {
				++$this->reads;
				return 'Lazy Flag';
			}

			protected function get_description(): string {
				++$this->reads;
				return 'Read on demand.';
			}

			public function register_hooks(): void {}
		};

		$this->assertSame( 0, $feature->reads );

		$meta = $registry->get_features()['lazy-flag'];

		$this->assertSame( 2, $feature->reads );
		$this->assertSame( 'Lazy Flag', $meta['name'] );
		$this->assertSame( 'Read on demand.', $meta['description'] );
	}

	public function test_can_register_returns_true_when_flag_is_enabled(): void {
		$registry = new FeatureSelector( 'test' );
		$feature  = $this->make_feature( $registry, 'my-flag' );

		$this->assertTrue( $feature->can_register() );
	}

	public function test_can_register_returns_false_when_flag_is_disabled(): void {
		$registry = new FeatureSelector( 'test' );
		$feature  = $this->make_feature( $registry, 'my-flag' );

		$registry->disable( 'my-flag' );

		$this->assertFalse( $feature->can_register() );
	}

	public function test_can_register_follows_re_enable(): void {
		$registry = new FeatureSelector( 'test' );
		$feature  = $this->make_feature( $registry, 'my-flag' );

		$registry->disable( 'my-flag' );
		$registry->enable( 'my-flag' );

		$this->assertTrue( $feature->can_register() );
	}
}
