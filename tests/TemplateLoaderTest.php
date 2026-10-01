<?php
/**
 * Tests for the TemplateLoader class.
 *
 * Runs against a real WordPress instance (wp-env): the theme hierarchy is
 * pointed at fixture directories via the template_directory/stylesheet_directory
 * filters (TestCase::set_theme_dirs), and rendering is asserted through real
 * load_template() output rather than a stub call log.
 *
 * @package rtCamp\WPPrimitives\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests;

use rtCamp\WPPrimitives\TemplateLoader;

/**
 * Class TemplateLoaderTest
 */
final class TemplateLoaderTest extends TestCase {

	/**
	 * Temp root holding the child-theme / parent-theme / package fixtures.
	 *
	 * @var string
	 */
	private string $tmp;

	/**
	 * Set up theme/package fixture directories.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->tmp = sys_get_temp_dir() . '/wpf-tpl-' . uniqid( '', true );

		mkdir( $this->tmp . '/child/my-plugin', 0777, true );
		mkdir( $this->tmp . '/parent/my-plugin', 0777, true );
		mkdir( $this->tmp . '/plugin/templates', 0777, true );
	}

	/**
	 * Remove the fixture tree.
	 */
	public function tear_down(): void {
		$this->rrmdir( $this->tmp );

		parent::tear_down();
	}

	/**
	 * Build a loader against the fixture package dir.
	 */
	private function loader(): TemplateLoader {
		return new TemplateLoader( 'my_plugin', $this->tmp . '/plugin/templates', 'my-plugin' );
	}

	/**
	 * Single-theme setup: stylesheet directory equals template directory.
	 */
	private function use_single_theme(): void {
		$this->set_theme_dirs( $this->tmp . '/parent', 'https://example.test/parent' );
	}

	/**
	 * Child-theme setup: distinct child (stylesheet) and parent (template) dirs.
	 */
	private function use_child_theme(): void {
		$this->set_theme_dirs(
			$this->tmp . '/parent',
			'https://example.test/parent',
			$this->tmp . '/child',
			'https://example.test/child'
		);
	}

	private function write( string $abs, string $body = '' ): void {
		$dir = dirname( $abs );

		if ( ! is_dir( $dir ) ) {
			mkdir( $dir, 0777, true );
		}

		file_put_contents( $abs, $body );
	}

	private function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		foreach ( scandir( $dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . '/' . $item;
			is_dir( $path ) ? $this->rrmdir( $path ) : unlink( $path );
		}

		rmdir( $dir );
	}

	public function test_resolves_from_package_when_no_theme_override(): void {
		$this->use_single_theme();
		$this->write( $this->tmp . '/plugin/templates/card.php' );

		$this->assertSame(
			$this->tmp . '/plugin/templates/card.php',
			$this->loader()->locate( 'card' )
		);
	}

	public function test_parent_theme_overrides_package(): void {
		$this->use_single_theme();
		$this->write( $this->tmp . '/plugin/templates/card.php' );
		$this->write( $this->tmp . '/parent/my-plugin/card.php' );

		$this->assertSame(
			$this->tmp . '/parent/my-plugin/card.php',
			$this->loader()->locate( 'card' )
		);
	}

	public function test_child_theme_overrides_parent(): void {
		$this->use_child_theme();
		$this->write( $this->tmp . '/parent/my-plugin/card.php' );
		$this->write( $this->tmp . '/child/my-plugin/card.php' );

		$this->assertSame(
			$this->tmp . '/child/my-plugin/card.php',
			$this->loader()->locate( 'card' )
		);
	}

	public function test_theme_package_layer_is_deduplicated(): void {
		// Theme consumer: its own templates ARE the parent-theme override path,
		// so the package layer must collapse rather than be searched twice.
		$this->use_single_theme();
		$theme_loader = new TemplateLoader( 'thm', $this->tmp . '/parent/templates', 'templates' );

		$method = new \ReflectionMethod( $theme_loader, 'get_template_paths' );
		$paths  = $method->invoke( $theme_loader );

		$this->assertSame( [ $this->tmp . '/parent/templates/' ], $paths );
	}

	public function test_theme_child_overrides_the_theme_package(): void {
		// Theme ships card.php; a child theme overrides it. Same loader, no plugin layer.
		$this->use_child_theme();
		$theme_loader = new TemplateLoader( 'thm', $this->tmp . '/parent/templates', 'templates' );

		$this->write( $this->tmp . '/parent/templates/card.php' );
		$this->assertSame(
			$this->tmp . '/parent/templates/card.php',
			$theme_loader->locate( 'card' )
		);

		$this->write( $this->tmp . '/child/templates/card.php' );
		$theme_loader->clear_cache();
		$this->assertSame(
			$this->tmp . '/child/templates/card.php',
			$theme_loader->locate( 'card' )
		);
	}

	public function test_child_theme_package_has_no_parent_fallback(): void {
		// A loader owned by the child theme: nothing sits above it, so the parent
		// theme is NOT searched as a fallback (mirrors ComponentLoader).
		$this->use_child_theme();
		$child_loader = new TemplateLoader( 'ct', $this->tmp . '/child/templates', 'templates' );

		$method = new \ReflectionMethod( $child_loader, 'get_template_paths' );

		$this->assertSame( [ $this->tmp . '/child/templates/' ], $method->invoke( $child_loader ) );
	}

	public function test_name_variant_is_preferred_over_base_slug(): void {
		$this->use_single_theme();
		$this->write( $this->tmp . '/plugin/templates/card.php' );
		$this->write( $this->tmp . '/plugin/templates/card-featured.php' );

		$this->assertSame(
			$this->tmp . '/plugin/templates/card-featured.php',
			$this->loader()->locate( 'card', 'featured' )
		);
	}

	public function test_name_variant_wins_across_layers_over_base_slug(): void {
		// WP locate_template precedence: a more specific name dominates location.
		// The parent's card-featured.php must beat the child's card.php.
		$this->use_child_theme();
		$this->write( $this->tmp . '/child/my-plugin/card.php' );
		$this->write( $this->tmp . '/parent/my-plugin/card-featured.php' );

		$this->assertSame(
			$this->tmp . '/parent/my-plugin/card-featured.php',
			$this->loader()->locate( 'card', 'featured' )
		);
	}

	public function test_falls_back_to_base_slug_when_variant_missing(): void {
		$this->use_single_theme();
		$this->write( $this->tmp . '/plugin/templates/card.php' );

		$this->assertSame(
			$this->tmp . '/plugin/templates/card.php',
			$this->loader()->locate( 'card', 'featured' )
		);
	}

	public function test_locate_returns_false_when_not_found(): void {
		$this->use_single_theme();

		$this->assertFalse( $this->loader()->locate( 'missing' ) );
	}

	public function test_get_rethrows_and_balances_the_buffer_when_a_template_throws(): void {
		// get() must clear its own output buffer and re-throw rather than leave a
		// half-rendered buffer open for the caller to inherit.
		$this->use_single_theme();
		$this->write( $this->tmp . '/plugin/templates/boom.php', '<?php throw new \RuntimeException( "kaboom" );' );

		$level = ob_get_level();

		try {
			$this->loader()->get( 'boom' );
			$this->fail( 'Expected the template exception to propagate.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'kaboom', $e->getMessage() );
		}

		$this->assertSame( $level, ob_get_level() );
	}

	public function test_render_loads_with_filtered_args(): void {
		$this->use_single_theme();
		$this->write( $this->tmp . '/plugin/templates/card.php', '<?php echo wp_json_encode( $args );' );

		add_filter(
			'my_plugin/template_args',
			static function ( array $args ): array {
				$args['injected'] = true;
				return $args;
			}
		);

		$output = $this->loader()->get( 'card', null, [ 'title' => 'Hi' ] );

		$this->assertSame(
			[ 'title' => 'Hi', 'injected' => true ],
			json_decode( $output, true )
		);
	}

	public function test_locate_does_not_render(): void {
		$this->use_single_theme();
		$this->write( $this->tmp . '/plugin/templates/card.php', '<?php echo "RENDERED";' );

		ob_start();
		$located = $this->loader()->locate( 'card' );
		$output  = (string) ob_get_clean();

		$this->assertSame( $this->tmp . '/plugin/templates/card.php', $located );
		$this->assertSame( '', $output );
	}

	public function test_get_returns_rendered_output(): void {
		$this->use_single_theme();
		$this->write( $this->tmp . '/plugin/templates/greeting.php', '<?php echo "Hi " . $args["who"]; ?>' );

		$this->assertSame(
			'Hi World',
			$this->loader()->get( 'greeting', null, [ 'who' => 'World' ] )
		);
	}

	public function test_get_returns_empty_when_not_found(): void {
		$this->use_single_theme();

		$this->assertSame( '', $this->loader()->get( 'missing' ) );
	}

	public function test_located_template_filter_can_override_result(): void {
		$this->use_single_theme();
		$this->write( $this->tmp . '/plugin/templates/card.php' );

		add_filter( 'my_plugin/located_template', static fn(): string => '/forced/path.php' );

		$this->assertSame( '/forced/path.php', $this->loader()->locate( 'card' ) );
	}

	public function test_template_paths_filter_can_add_a_source(): void {
		$this->use_single_theme();
		$extra = $this->tmp . '/extra';
		$this->write( $extra . '/card.php' );

		add_filter(
			'my_plugin/template_paths',
			function ( array $paths ) use ( $extra ): array {
				$paths[5] = $extra; // Higher precedence than the package (100).
				return $paths;
			}
		);

		$this->assertSame( $extra . '/card.php', $this->loader()->locate( 'card' ) );
	}

	public function test_result_is_cached_until_cleared(): void {
		$this->use_single_theme();
		$this->write( $this->tmp . '/plugin/templates/card.php' );

		$loader = $this->loader();
		$this->assertSame( $this->tmp . '/plugin/templates/card.php', $loader->locate( 'card' ) );

		// Remove the file; the cached path is still returned.
		unlink( $this->tmp . '/plugin/templates/card.php' );
		$this->assertSame( $this->tmp . '/plugin/templates/card.php', $loader->locate( 'card' ) );

		// After clearing, the now-missing template resolves to false.
		$loader->clear_cache();
		$this->assertFalse( $loader->locate( 'card' ) );
	}

	public function test_a_theme_switch_is_not_served_from_the_previous_themes_cache(): void {
		// Regression: the location cache used to key on candidate names only, so a
		// Shareable loader reused across a switch_theme()/switch_to_blog() in one
		// request kept serving the old theme's hit. The key now includes the
		// resolved paths, which change with the active theme.
		$this->use_single_theme();
		$this->write( $this->tmp . '/parent/my-plugin/card.php' );
		$this->write( $this->tmp . '/child/my-plugin/card.php' );

		$loader = $this->loader();
		$this->assertSame( $this->tmp . '/parent/my-plugin/card.php', $loader->locate( 'card' ) );

		// Switch to a child theme without clearing the loader.
		$this->use_child_theme();

		$this->assertSame( $this->tmp . '/child/my-plugin/card.php', $loader->locate( 'card' ) );
	}

	public function test_switching_back_reuses_the_original_themes_entry(): void {
		// The key is per-path-set rather than a blanket invalidation, so switching
		// back must return the first theme's result again.
		$this->use_single_theme();
		$this->write( $this->tmp . '/parent/my-plugin/card.php' );
		$this->write( $this->tmp . '/child/my-plugin/card.php' );

		$loader = $this->loader();
		$parent_hit = $loader->locate( 'card' );

		$this->use_child_theme();
		$child_hit = $loader->locate( 'card' );

		$this->use_single_theme();

		$this->assertNotSame( $parent_hit, $child_hit );
		$this->assertSame( $parent_hit, $loader->locate( 'card' ) );
	}

	public function test_locate_returns_false_when_no_candidate_names(): void {
		$this->use_single_theme();

		// A filter that empties the candidate list short-circuits to false.
		add_filter( 'my_plugin/template_file_names', static fn (): array => [] );

		$this->assertFalse( $this->loader()->locate( 'card' ) );
	}

	public function test_traversal_segments_are_stripped(): void {
		$this->use_single_theme();
		$this->write( $this->tmp . '/plugin/templates/card.php' );

		// '../' segments are removed, so this cannot escape the search roots.
		$this->assertFalse( $this->loader()->locate( '../../etc/passwd' ) );
	}
}
