<?php
/**
 * AbstractAbility tests.
 *
 * AbstractAbility is pure argument-mapping: it never calls the Abilities API
 * itself, so these tests run on every supported WordPress version. The
 * registration round-trip lives in AbstractAbilityRegistrarTest. Tests use
 * anonymous classes throughout so each scenario is self-contained.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractAbility;
use rtCamp\WPPrimitives\Tests\TestCase;

/**
 * Tests for AbstractAbility.
 *
 * @since 0.0.1
 */
final class AbstractAbilityTest extends TestCase {

	/**
	 * Concrete AbstractAbility with every seam injectable.
	 *
	 * @param string        $name          Ability name.
	 * @param string        $category      Category slug.
	 * @param array         $input_schema  Input schema.
	 * @param array         $output_schema Output schema.
	 * @param array         $meta          Meta override.
	 * @param \Closure|null $permission    Optional permission() override.
	 */
	private function make_ability(
		string $name = 'test-plugin/do-thing',
		string $category = 'test-plugin',
		array $input_schema = [],
		array $output_schema = [],
		array $meta = [],
		?\Closure $permission = null
	): AbstractAbility {
		return new class( $name, $category, $input_schema, $output_schema, $meta, $permission ) extends AbstractAbility {
			public function __construct(
				private readonly string $ability_name,
				private readonly string $ability_category,
				private readonly array $input,
				private readonly array $output,
				private readonly array $meta_override,
				private readonly ?\Closure $permission_override
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
				return $this->input;
			}

			protected function output_schema(): array {
				return $this->output;
			}

			protected function meta(): array {
				return $this->meta_override;
			}

			protected function permission( mixed $input = null ): bool|\WP_Error {
				if ( null !== $this->permission_override ) {
					return ( $this->permission_override )( $input );
				}

				return parent::permission( $input );
			}

			public function execute( mixed $input ): array|\WP_Error {
				return [ 'received' => $input ];
			}
		};
	}

	public function test_args_maps_label_description_category_and_callbacks(): void {
		$ability = $this->make_ability();
		$args    = $ability->args();

		$this->assertSame( 'Do Thing', $args['label'] );
		$this->assertSame( 'Does the thing.', $args['description'] );
		$this->assertSame( 'test-plugin', $args['category'] );
		$this->assertInstanceOf( \Closure::class, $args['execute_callback'] );
		$this->assertInstanceOf( \Closure::class, $args['permission_callback'] );
		$this->assertSame( [ 'received' => [ 'x' => 1 ] ], $args['execute_callback']( [ 'x' => 1 ] ) );
	}

	public function test_callbacks_are_safe_to_invoke_without_arguments(): void {
		// Core invokes both callbacks with zero arguments when the ability
		// declares no input schema; the closure wrappers absorb that.
		$args = $this->make_ability()->args();

		$this->assertSame( [ 'received' => null ], $args['execute_callback']() );
		$this->assertFalse( $args['permission_callback']() );
	}

	public function test_args_includes_schemas_when_provided(): void {
		$input_schema  = [
			'type'       => 'object',
			'properties' => [ 'message' => [ 'type' => 'string' ] ],
		];
		$output_schema = [ 'type' => 'object' ];

		$args = $this->make_ability( input_schema: $input_schema, output_schema: $output_schema )->args();

		$this->assertSame( $input_schema, $args['input_schema'] );
		$this->assertSame( $output_schema, $args['output_schema'] );
	}

	public function test_args_omits_empty_schemas(): void {
		$args = $this->make_ability()->args();

		$this->assertArrayNotHasKey( 'input_schema', $args );
		$this->assertArrayNotHasKey( 'output_schema', $args );
	}

	public function test_args_omits_meta_by_default(): void {
		$this->assertArrayNotHasKey( 'meta', $this->make_ability()->args() );
	}

	public function test_meta_override_propagates(): void {
		$args = $this->make_ability( meta: [ 'show_in_rest' => true ] )->args();

		$this->assertSame( [ 'show_in_rest' => true ], $args['meta'] );
	}

	public function test_default_permission_denies_anonymous(): void {
		wp_set_current_user( 0 );

		$this->assertFalse( $this->make_ability()->args()['permission_callback']() );
	}

	public function test_default_permission_denies_subscriber(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );

		$this->assertFalse( $this->make_ability()->args()['permission_callback']() );
	}

	public function test_default_permission_allows_administrator(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->assertTrue( $this->make_ability()->args()['permission_callback']() );
	}

	public function test_permission_override_propagates(): void {
		$ability = $this->make_ability(
			permission: static fn ( mixed $input = null ): bool => current_user_can( 'edit_posts' )
		);

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertFalse( $ability->args()['permission_callback']() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'contributor' ] ) );
		$this->assertTrue( $ability->args()['permission_callback']() );
	}

	public function test_permission_callback_receives_input(): void {
		$received = new \ArrayObject();
		$ability  = $this->make_ability(
			permission: static function ( mixed $input = null ) use ( $received ): bool {
				$received->append( $input );
				return true;
			}
		);

		$ability->args()['permission_callback']( [ 'key' => 'value' ] );

		$this->assertSame( [ [ 'key' => 'value' ] ], $received->getArrayCopy() );
	}

	/**
	 * @dataProvider data_results
	 *
	 * @param mixed $result Value the ability returns.
	 */
	public function test_execute_may_return_any_schema_valid_type( mixed $result ): void {
		$ability = new class( $result ) extends AbstractAbility {

			/**
			 * @param mixed $result Value to return from execute().
			 */
			public function __construct( private mixed $result ) {}

			public function name(): string {
				return 'test-plugin/scalar';
			}

			protected function label(): string {
				return 'Scalar';
			}

			protected function description(): string {
				return 'Returns a non-array result.';
			}

			protected function category(): string {
				return 'test-plugin';
			}

			protected function input_schema(): array {
				return [];
			}

			protected function output_schema(): array {
				return [];
			}

			public function execute( mixed $input ): mixed {
				return $this->result;
			}
		};

		$this->assertSame( $result, $ability->args()['execute_callback']() );
	}

	/**
	 * @return array<string, array{mixed}> Result values.
	 */
	public function data_results(): array {
		return [
			'string'     => [ 'just a string' ],
			'boolean'    => [ true ],
			'integer'    => [ 42 ],
			'list array' => [ [ 'a', 'b' ] ],
			'null'       => [ null ],
		];
	}
}
