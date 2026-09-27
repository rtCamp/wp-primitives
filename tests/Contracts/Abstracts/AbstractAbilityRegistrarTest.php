<?php
/**
 * AbstractAbilityRegistrar tests.
 *
 * These exercise the real Abilities API (WordPress 6.9+): the registrar's
 * hooks fire through core's lazy registry initialization, then abilities are
 * read back with wp_get_ability(). On older cores every test is skipped.
 * Both core registries are singletons, so they are reset via reflection
 * around every test (the suite runs in random order). The hooks each
 * registrar adds need no such cleanup — WP_UnitTestCase::tear_down() restores
 * $wp_filter wholesale after every test. Tests use anonymous classes
 * throughout so each scenario is self-contained.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractAbility;
use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractAbilityRegistrar;
use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;
use rtCamp\WPPrimitives\Tests\TestCase;

/**
 * Tests for AbstractAbilityRegistrar.
 *
 * @since 0.0.1
 */
final class AbstractAbilityRegistrarTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wp_register_ability' ) ) {
			$this->markTestSkipped( 'The Abilities API requires WordPress 6.9+.' );
		}

		$this->reset_abilities_registries();
	}

	protected function tearDown(): void {
		$this->reset_abilities_registries();

		parent::tearDown();
	}

	/**
	 * Null both core registry singletons so each test starts from a cold,
	 * uninitialized Abilities API.
	 */
	private function reset_abilities_registries(): void {
		foreach ( [ \WP_Abilities_Registry::class, \WP_Ability_Categories_Registry::class ] as $registry ) {
			if ( ! class_exists( $registry ) ) {
				continue;
			}

			$property = new \ReflectionProperty( $registry, 'instance' );
			$property->setAccessible( true );
			$property->setValue( null, null );
		}
	}

	/**
	 * Initialize the Abilities API exactly like production: the first registry
	 * access fires wp_abilities_api_categories_init, then wp_abilities_api_init.
	 */
	private function init_abilities_api(): void {
		\WP_Abilities_Registry::get_instance();
	}

	/**
	 * Minimal concrete AbstractAbility.
	 *
	 * @param string $name     Ability name.
	 * @param string $category Category slug.
	 */
	private function make_ability( string $name, string $category = 'test-plugin' ): AbstractAbility {
		return new class( $name, $category ) extends AbstractAbility {
			public function __construct(
				private readonly string $ability_name,
				private readonly string $ability_category
			) {}

			public function name(): string {
				return $this->ability_name;
			}

			protected function label(): string {
				return 'Do Thing';
			}

			protected function description(): string {
				return 'Does the thing.';
			}

			protected function category(): string {
				return $this->ability_category;
			}

			protected function input_schema(): array {
				return [
					'type'       => 'object',
					'properties' => [ 'message' => [ 'type' => 'string' ] ],
				];
			}

			protected function output_schema(): array {
				return [];
			}

			public function execute( mixed $input ): array|\WP_Error {
				return [ 'received' => $input ];
			}
		};
	}

	/**
	 * Concrete AbstractAbilityRegistrar with injectable seams.
	 *
	 * The Abilities API rejects empty category descriptions, so the abstract
	 * category_description() always returns a real string here.
	 *
	 * @param string $slug        Category slug.
	 * @param array  $abilities   Abilities to register.
	 * @param string $label       Optional category_label() override.
	 * @param string $description Category description.
	 */
	private function make_registrar(
		string $slug,
		array $abilities,
		string $label = '',
		string $description = 'Tools for testing.'
	): AbstractAbilityRegistrar {
		return new class( $slug, $abilities, $label, $description ) extends AbstractAbilityRegistrar {
			public function __construct(
				private readonly string $slug,
				private readonly array $ability_list,
				private readonly string $label_override,
				private readonly string $desc
			) {}

			protected function category_slug(): string {
				return $this->slug;
			}

			protected function abilities(): array {
				return $this->ability_list;
			}

			protected function category_label(): string {
				return '' !== $this->label_override ? $this->label_override : parent::category_label();
			}

			protected function category_description(): string {
				return $this->desc;
			}
		};
	}

	public function test_implements_registrable(): void {
		$this->assertInstanceOf( Registrable::class, $this->make_registrar( 'test-plugin', [] ) );
	}

	public function test_register_hooks_adds_both_actions(): void {
		$registrar = $this->make_registrar( 'test-plugin', [] );
		$registrar->register_hooks();

		$this->assertSame( 10, has_action( 'wp_abilities_api_categories_init', [ $registrar, 'register_category' ] ) );
		$this->assertSame( 10, has_action( 'wp_abilities_api_init', [ $registrar, 'register_abilities' ] ) );
	}

	public function test_ability_is_registered_and_retrievable(): void {
		$this->make_registrar( 'test-plugin', [ $this->make_ability( 'test-plugin/do-thing' ) ] )->register_hooks();

		$this->init_abilities_api();

		$registered = wp_get_ability( 'test-plugin/do-thing' );

		$this->assertInstanceOf( \WP_Ability::class, $registered );
		$this->assertSame( 'Do Thing', $registered->get_label() );
		$this->assertSame( 'test-plugin', $registered->get_category() );
	}

	public function test_category_is_registered_with_label_and_description(): void {
		$this->make_registrar( 'test-plugin', [], 'Test Tools', 'Tools for testing.' )->register_hooks();

		$this->init_abilities_api();

		$this->assertTrue( wp_has_ability_category( 'test-plugin' ) );

		$category = wp_get_ability_category( 'test-plugin' );

		$this->assertInstanceOf( \WP_Ability_Category::class, $category );
		$this->assertSame( 'Test Tools', $category->get_label() );
		$this->assertSame( 'Tools for testing.', $category->get_description() );
	}

	public function test_default_category_label_title_cases_slug(): void {
		$this->make_registrar( 'my-cool-plugin', [] )->register_hooks();

		$this->init_abilities_api();

		$category = wp_get_ability_category( 'my-cool-plugin' );

		$this->assertInstanceOf( \WP_Ability_Category::class, $category );
		$this->assertSame( 'My Cool Plugin', $category->get_label() );
	}

	public function test_category_registration_is_idempotent_across_registrars(): void {
		$this->make_registrar( 'test-plugin', [], 'First Label' )->register_hooks();
		$this->make_registrar( 'test-plugin', [], 'Second Label' )->register_hooks();

		$this->init_abilities_api();

		// The first registrar wins; the second skips its wp_register_ability_category()
		// call instead of triggering a duplicate-registration notice.
		$category = wp_get_ability_category( 'test-plugin' );

		$this->assertInstanceOf( \WP_Ability_Category::class, $category );
		$this->assertSame( 'First Label', $category->get_label() );
	}

	public function test_registrar_registers_multiple_abilities(): void {
		$this->make_registrar(
			'test-plugin',
			[
				$this->make_ability( 'test-plugin/first-thing' ),
				$this->make_ability( 'test-plugin/second-thing' ),
			]
		)->register_hooks();

		$this->init_abilities_api();

		$this->assertInstanceOf( \WP_Ability::class, wp_get_ability( 'test-plugin/first-thing' ) );
		$this->assertInstanceOf( \WP_Ability::class, wp_get_ability( 'test-plugin/second-thing' ) );
	}

	public function test_before_register_runs_before_abilities_are_read(): void {
		$log       = new \ArrayObject();
		$registrar = new class( $log, $this->make_ability( 'test-plugin/do-thing' ) ) extends AbstractAbilityRegistrar {
			public function __construct(
				private readonly \ArrayObject $log,
				private readonly AbstractAbility $ability
			) {}

			protected function category_slug(): string {
				return 'test-plugin';
			}

			protected function category_description(): string {
				return 'Tools for testing.';
			}

			protected function before_register(): void {
				$this->log->append( 'before_register' );
			}

			protected function abilities(): array {
				$this->log->append( 'abilities' );
				return [ $this->ability ];
			}
		};
		$registrar->register_hooks();

		$this->init_abilities_api();

		$this->assertSame( [ 'before_register', 'abilities' ], $log->getArrayCopy() );
	}

	public function test_execute_round_trip_as_administrator(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->make_registrar( 'test-plugin', [ $this->make_ability( 'test-plugin/do-thing' ) ] )->register_hooks();

		$this->init_abilities_api();

		$result = wp_get_ability( 'test-plugin/do-thing' )->execute( [ 'message' => 'hi' ] );

		$this->assertSame( [ 'received' => [ 'message' => 'hi' ] ], $result );
	}

	public function test_execute_is_denied_below_manage_options(): void {
		$this->make_registrar( 'test-plugin', [ $this->make_ability( 'test-plugin/do-thing' ) ] )->register_hooks();

		$this->init_abilities_api();

		$ability = wp_get_ability( 'test-plugin/do-thing' );
		$this->assertInstanceOf( \WP_Ability::class, $ability );

		wp_set_current_user( 0 );
		$anonymous = $ability->execute( [ 'message' => 'hi' ] );
		$this->assertInstanceOf( \WP_Error::class, $anonymous );
		$this->assertSame( 'ability_invalid_permissions', $anonymous->get_error_code() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$subscriber = $ability->execute( [ 'message' => 'hi' ] );
		$this->assertInstanceOf( \WP_Error::class, $subscriber );
		$this->assertSame( 'ability_invalid_permissions', $subscriber->get_error_code() );
	}
}
