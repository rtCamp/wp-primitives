<?php
/**
 * Base test case for the framework's WordPress integration tests.
 *
 * Extends WP_UnitTestCase so tests run against a real WordPress instance
 * (provided by wp-env). Adds a few helpers for the patterns the framework's
 * tests need repeatedly: pointing the theme hierarchy at fixture directories
 * and reading back the real script/style/module registries.
 *
 * @package rtCamp\WPPrimitives\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests;

use WP_UnitTestCase;

/**
 * Class TestCase
 *
 * @since 0.0.1
 */
abstract class TestCase extends WP_UnitTestCase {

	/**
	 * Point WordPress' theme hierarchy at fixture directories.
	 *
	 * Filters get_template_directory()/get_stylesheet_directory() (and their URI
	 * variants) so the loaders resolve against on-disk fixtures instead of the
	 * active test theme. When no child paths are given, the stylesheet (child)
	 * directory equals the template (parent) directory — i.e. no child theme.
	 *
	 * @param string      $parent_dir Absolute path used as the (parent) template directory.
	 * @param string      $parent_uri URL used as the template directory URI.
	 * @param string|null $child_dir  Optional absolute path used as the stylesheet (child) directory.
	 * @param string|null $child_uri  Optional URL used as the stylesheet directory URI.
	 *
	 * @return void
	 */
	protected function set_theme_dirs( string $parent_dir, string $parent_uri, ?string $child_dir = null, ?string $child_uri = null ): void {
		$stylesheet_dir = $child_dir ?? $parent_dir;
		$stylesheet_uri = $child_uri ?? $parent_uri;

		add_filter( 'template_directory', static fn (): string => $parent_dir );
		add_filter( 'template_directory_uri', static fn (): string => $parent_uri );
		add_filter( 'stylesheet_directory', static fn (): string => $stylesheet_dir );
		add_filter( 'stylesheet_directory_uri', static fn (): string => $stylesheet_uri );
	}

	/**
	 * Get a registered script's dependency object, or null if not registered.
	 *
	 * @param string $handle Script handle.
	 *
	 * @return \_WP_Dependency|null Registered script, or null.
	 */
	protected function registered_script( string $handle ): ?\_WP_Dependency {
		return wp_scripts()->registered[ $handle ] ?? null;
	}

	/**
	 * Get a registered style's dependency object, or null if not registered.
	 *
	 * @param string $handle Style handle.
	 *
	 * @return \_WP_Dependency|null Registered style, or null.
	 */
	protected function registered_style( string $handle ): ?\_WP_Dependency {
		return wp_styles()->registered[ $handle ] ?? null;
	}

	/**
	 * Read a registered script module from WP_Script_Modules' private registry.
	 *
	 * WordPress exposes no public getter for registered modules, so this reaches
	 * the registry via reflection. The returned shape mirrors WordPress' own
	 * normalisation (each dependency is `[ 'id' => string, 'import' => string ]`).
	 *
	 * @param string $id Module identifier.
	 *
	 * @return array<string, mixed>|null Registered module data, or null if absent.
	 */
	protected function registered_script_module( string $id ): ?array {
		$modules    = wp_script_modules();
		$property   = new \ReflectionProperty( $modules, 'registered' );
		$registered = $property->getValue( $modules );

		return $registered[ $id ] ?? null;
	}
}
