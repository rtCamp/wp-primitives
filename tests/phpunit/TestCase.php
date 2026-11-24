<?php
/**
 * Base test case for WordPress integration tests.
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
	 * Point the template and stylesheet directories and URIs at fixtures.
	 *
	 * Without child paths, the stylesheet directory equals the template directory, which models a site with no child theme.
	 *
	 * @param string      $parent_dir Absolute path used as the template directory.
	 * @param string      $parent_uri URL used as the template directory URI.
	 * @param string|null $child_dir  Optional. Absolute path used as the stylesheet directory.
	 * @param string|null $child_uri  Optional. URL used as the stylesheet directory URI.
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
	 * Read a registered script module from WP_Script_Modules' private registry, using reflection.
	 *
	 * Each dependency in the result has the shape `[ 'id' => string, 'import' => string ]`.
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
