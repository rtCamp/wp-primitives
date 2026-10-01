<?php
/**
 * FeatureSelector utility tests.
 *
 * @package rtCamp\WPPrimitives\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Utils;

use rtCamp\WPPrimitives\Tests\TestCase;
use rtCamp\WPPrimitives\Utils\FeatureSelector;

/**
 * Tests for FeatureSelector.
 *
 * Integration tests against real WordPress (wp-env): toggles persist through the
 * options API and each test's writes are rolled back by WP_UnitTestCase. The
 * loud `_doing_it_wrong()` paths are asserted with setExpectedIncorrectUsage();
 * a path that should stay silent simply omits it (an unexpected notice fails the
 * test).
 *
 * Constants are process-global and immutable, so every constant-precedence test
 * uses a flag slug unique to that test and a guarded define() to stay idempotent
 * across re-runs in the same process.
 */
final class FeatureSelectorTest extends TestCase {

	/**
	 * Instance under test, constructed with the `my-plugin` context.
	 */
	private FeatureSelector $selector;

	public function set_up(): void {
		parent::set_up();

		$this->selector = new FeatureSelector( 'my-plugin' );
	}

	// --- register / registry ---------------------------------------------------

	public function test_register_registers_flags_from_slug_list(): void {
		$this->selector->register( [ 'feature-a', 'feature-b' ] );

		$this->assertSame( [ 'feature-a', 'feature-b' ], $this->selector->get_registered() );
	}

	public function test_register_stores_metadata_with_fallbacks(): void {
		$this->selector->register(
			[
				'rich-flag'   => [
					'name'        => 'Rich Flag',
					'description' => 'A flag with full metadata.',
				],
				'simple-flag' => [
					'name' => 'Simple Flag',
				],
				'bare-flag',
			]
		);

		$features = $this->selector->get_features();

		$this->assertSame( 'Rich Flag', $features['rich-flag']['name'] );
		$this->assertSame( 'A flag with full metadata.', $features['rich-flag']['description'] );

		$this->assertSame( 'Simple Flag', $features['simple-flag']['name'] );
		$this->assertSame( '', $features['simple-flag']['description'] );

		$this->assertSame( 'bare-flag', $features['bare-flag']['name'] );
		$this->assertSame( '', $features['bare-flag']['description'] );
	}

	public function test_register_resolves_closure_metadata_only_when_read(): void {
		$calls = 0;

		$this->selector->register(
			[
				'lazy-flag' => [
					'name'        => static function () use ( &$calls ): string {
						++$calls;
						return 'Lazy Flag';
					},
					'description' => static fn (): string => 'Read on demand.',
				],
			]
		);

		$this->assertSame( 0, $calls );

		$features = $this->selector->get_features();

		$this->assertSame( 1, $calls );
		$this->assertSame( 'Lazy Flag', $features['lazy-flag']['name'] );
		$this->assertSame( 'Read on demand.', $features['lazy-flag']['description'] );
	}

	public function test_register_does_not_call_strings_that_name_a_function(): void {
		$this->selector->register(
			[
				'date-flag' => [
					'name'        => 'date',
					'description' => 'phpinfo',
				],
			]
		);

		$features = $this->selector->get_features();

		$this->assertSame( 'date', $features['date-flag']['name'] );
		$this->assertSame( 'phpinfo', $features['date-flag']['description'] );
	}

	public function test_register_accepts_a_bare_slug_string(): void {
		$this->selector->register( 'single-flag' );

		$this->assertSame( [ 'single-flag' ], $this->selector->get_registered() );
		$this->assertSame( 'single-flag', $this->selector->get_features()['single-flag']['name'] );
	}

	public function test_register_keeps_first_registration_and_warns_on_duplicate(): void {
		$this->setExpectedIncorrectUsage( 'rtCamp\WPPrimitives\Utils\FeatureSelector::register' );

		$this->selector->register( [ 'my-flag' => [ 'name' => 'Original' ] ] );
		$this->selector->register( [ 'my-flag' => [ 'name' => 'Updated' ] ] );

		// First write wins; the duplicate is ignored and flagged loudly.
		$this->assertSame( 'Original', $this->selector->get_features()['my-flag']['name'] );
	}

	public function test_register_warns_on_slugs_that_normalize_to_the_same_key(): void {
		$this->setExpectedIncorrectUsage( 'rtCamp\WPPrimitives\Utils\FeatureSelector::register' );

		$this->selector->register( [ 'beta-search' ] );
		$this->selector->register( [ 'beta search' ] ); // Normalizes to the same key.

		// First registration wins; the colliding slug is rejected and flagged.
		$this->assertSame( [ 'beta-search' ], $this->selector->get_registered() );
	}

	public function test_register_skips_malformed_entries(): void {
		// Malformed: int key with array value, string key with string value.
		// No setExpectedIncorrectUsage(): skipping is silent, so an unexpected
		// _doing_it_wrong() here would fail the test.
		$this->selector->register(
			[
				0          => [ 'name' => 'Nameless' ],
				'odd-pair' => 'not-an-array',
			]
		);

		$this->assertSame( [], $this->selector->get_registered() );
	}

	// --- is_enabled / enable / disable ------------------------------------------

	public function test_enabled_by_default(): void {
		$this->selector->register( [ 'feature-default-on' ] );

		$this->assertTrue( $this->selector->is_enabled( 'feature-default-on' ) );
	}

	public function test_enable_and_disable_persist_to_option(): void {
		$this->selector->register( [ 'feature-toggle' ] );

		$this->selector->enable( 'feature-toggle' );
		$this->assertTrue( $this->selector->is_enabled( 'feature-toggle' ) );
		$this->assertTrue( get_option( 'my_plugin_features' )['feature-toggle'] );

		$this->selector->disable( 'feature-toggle' );
		$this->assertFalse( $this->selector->is_enabled( 'feature-toggle' ) );
		$this->assertFalse( get_option( 'my_plugin_features' )['feature-toggle'] );
	}

	public function test_enable_refuses_and_warns_for_an_unregistered_flag(): void {
		// A typo'd toggle is a programming error: nothing is persisted, and the
		// caller is flagged loudly rather than silently believing it took effect.
		$this->setExpectedIncorrectUsage( 'rtCamp\WPPrimitives\Utils\FeatureSelector::enable' );

		$this->assertFalse( $this->selector->enable( 'drak-mode' ) );
		$this->assertFalse( get_option( 'my_plugin_features', false ) );
	}

	public function test_disable_refuses_and_warns_for_an_unregistered_flag(): void {
		$this->setExpectedIncorrectUsage( 'rtCamp\WPPrimitives\Utils\FeatureSelector::disable' );

		$this->assertFalse( $this->selector->disable( 'drak-mode' ) );
		$this->assertFalse( get_option( 'my_plugin_features', false ) );
	}

	public function test_disable_turns_off_a_never_stored_default_on_flag(): void {
		$this->selector->register( [ 'fresh-flag' ] );

		// Enabled by default, with no option ever written.
		$this->assertTrue( $this->selector->is_enabled( 'fresh-flag' ) );
		$this->assertFalse( get_option( 'my_plugin_features', false ) );

		// disable() must persist `false` even though no row existed yet.
		$this->selector->disable( 'fresh-flag' );

		$this->assertFalse( $this->selector->is_enabled( 'fresh-flag' ) );
		$this->assertFalse( get_option( 'my_plugin_features' )['fresh-flag'] );
	}

	public function test_all_flags_for_a_context_share_one_option_row(): void {
		$this->selector->register( [ 'feature-a', 'feature-b' ] );

		$this->selector->enable( 'feature-a' );
		$this->selector->disable( 'feature-b' );

		// All toggles live in one option (a single autoloaded row), not one
		// option per flag.
		$this->assertSame(
			[
				'feature-a' => true,
				'feature-b' => false,
			],
			get_option( 'my_plugin_features' )
		);
		$this->assertFalse( get_option( 'my_plugin_feature_feature_a', false ) );
		$this->assertFalse( get_option( 'my_plugin_feature_feature_b', false ) );
	}

	public function test_unregistered_flag_fails_closed(): void {
		$this->selector->register( [ 'dark-mode' ] );

		// A mistyped or never-registered slug returns false rather than inheriting
		// the default-on, and the read stays silent (no _doing_it_wrong()).
		$this->assertFalse( $this->selector->is_enabled( 'drak-mode' ) );
		$this->assertFalse( $this->selector->is_enabled( 'totally-unknown' ) );
	}

	public function test_unregistered_flag_fails_closed_even_with_a_defined_constant(): void {
		if ( ! defined( 'MY_PLUGIN_FEATURE_PHANTOM' ) ) {
			define( 'MY_PLUGIN_FEATURE_PHANTOM', true );
		}

		// The registry is authoritative: a flag that was never registered stays
		// off even if a matching override constant happens to be defined — the
		// registry check runs before the constant lookup.
		$this->assertFalse( $this->selector->is_enabled( 'phantom' ) );
	}

	public function test_true_constant_overrides_disabled_option(): void {
		if ( ! defined( 'MY_PLUGIN_FEATURE_FORCED_ON' ) ) {
			define( 'MY_PLUGIN_FEATURE_FORCED_ON', true );
		}

		$this->selector->register( [ 'forced-on' ] );

		// Even with the option explicitly set to false, the constant wins.
		$this->selector->disable( 'forced-on' );

		$this->assertTrue( $this->selector->is_enabled( 'forced-on' ) );
	}

	public function test_false_constant_overrides_enabled_option(): void {
		if ( ! defined( 'MY_PLUGIN_FEATURE_FORCED_OFF' ) ) {
			define( 'MY_PLUGIN_FEATURE_FORCED_OFF', false );
		}

		$this->selector->register( [ 'forced-off' ] );

		$this->selector->enable( 'forced-off' );

		$this->assertFalse( $this->selector->is_enabled( 'forced-off' ) );
	}

	public function test_string_false_constant_disables_the_flag(): void {
		if ( ! defined( 'MY_PLUGIN_FEATURE_STRING_OFF' ) ) {
			define( 'MY_PLUGIN_FEATURE_STRING_OFF', 'false' );
		}

		$this->selector->register( [ 'string-off' ] );

		// A constant typed as the string 'false' (a common wp-config mistake)
		// must disable the flag — a bare `(bool) 'false'` would be true.
		$this->assertFalse( $this->selector->is_enabled( 'string-off' ) );
	}

	// --- key derivation ----------------------------------------------------------

	public function test_key_derivation_is_symmetrical(): void {
		$this->assertSame( 'demo-flag', $this->selector->flag_key( 'demo-flag' ) );
		$this->assertSame( 'MY_PLUGIN_FEATURE_DEMO_FLAG', $this->selector->constant_name( 'demo-flag' ) );
		$this->assertSame( 'my_plugin_features', $this->selector->shared_option_key() );
	}

	public function test_slugs_with_repeated_dashes_get_distinct_constants(): void {
		// Regression: the constant used to be derived via normalize(), which
		// collapses runs of dashes, so these two slugs shared one constant and a
		// single define() would lock both. flag_key() keeps them apart, and the
		// constant must follow the same partition as storage.
		$this->assertNotSame(
			$this->selector->flag_key( 'beta-search' ),
			$this->selector->flag_key( 'beta--search' )
		);

		$this->assertNotSame(
			$this->selector->constant_name( 'beta-search' ),
			$this->selector->constant_name( 'beta--search' )
		);

		$this->assertSame( 'MY_PLUGIN_FEATURE_BETA_SEARCH', $this->selector->constant_name( 'beta-search' ) );
		$this->assertSame( 'MY_PLUGIN_FEATURE_BETA__SEARCH', $this->selector->constant_name( 'beta--search' ) );
	}

	public function test_constant_name_follows_the_same_partition_as_storage(): void {
		// Two slugs that flag_key() maps to the same storage key must also map to
		// the same constant — otherwise a flag could be locked under one spelling
		// and read under another.
		$this->assertSame(
			$this->selector->flag_key( 'Dark Mode' ),
			$this->selector->flag_key( 'dark-mode' )
		);

		$this->assertSame(
			$this->selector->constant_name( 'Dark Mode' ),
			$this->selector->constant_name( 'dark-mode' )
		);
	}

	public function test_empty_context_uses_bare_feature_prefix(): void {
		$selector = new FeatureSelector();

		$this->assertSame( 'demo-flag', $selector->flag_key( 'demo-flag' ) );
		$this->assertSame( 'FEATURE_DEMO_FLAG', $selector->constant_name( 'demo-flag' ) );
		$this->assertSame( 'features', $selector->shared_option_key() );
	}

	public function test_context_is_normalized(): void {
		// Context normalization uses underscores for the option name.
		$this->assertSame(
			'my_plugin_features',
			( new FeatureSelector( 'My-Plugin' ) )->shared_option_key()
		);

		// Runs of spaces/dots and other invalid characters collapse to one underscore.
		$this->assertSame(
			'my_plugin_v2_0_features',
			( new FeatureSelector( 'My Plugin v2.0' ) )->shared_option_key()
		);
	}

	public function test_same_flag_in_different_contexts_does_not_collide(): void {
		$other = new FeatureSelector( 'other-plugin' );

		$this->selector->register( [ 'shared-flag' ] );
		$other->register( [ 'shared-flag' ] );

		// Same slug, opposite states: each context reads its own option key,
		// so toggling one cannot affect the other.
		$this->selector->enable( 'shared-flag' );
		$other->disable( 'shared-flag' );

		$this->assertTrue( $this->selector->is_enabled( 'shared-flag' ) );
		$this->assertFalse( $other->is_enabled( 'shared-flag' ) );
	}

	public function test_get_context_returns_constructor_context(): void {
		$this->assertSame( 'my-plugin', $this->selector->get_context() );
		$this->assertSame( '', ( new FeatureSelector() )->get_context() );
	}
}
