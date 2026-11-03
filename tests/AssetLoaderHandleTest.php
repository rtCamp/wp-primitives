<?php
/**
 * Tests for AssetLoader::handle() and the overridable HANDLE_PREFIX constant.
 *
 * @package rtCamp\WPPrimitives\Tests
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests;

use PHPUnit\Framework\TestCase;
use rtCamp\WPPrimitives\AssetLoader;

final class AssetLoaderHandleTest extends TestCase {

	public function test_handle_applies_the_default_prefix(): void {
		$loader = new AssetLoader( '', '', 'assets' );

		$this->assertSame( 'wp-primitives-frontend', $loader->handle( 'frontend' ) );
		$this->assertSame( 'wp-primitives-my-script', $loader->handle( 'my-script' ) );
	}

	public function test_subclass_prefix_wins_via_late_static_binding(): void {
		$loader = new AssetLoaderWithPluginPrefix( '', '', 'assets' );

		$this->assertSame( 'plugin-frontend', $loader->handle( 'frontend' ) );
		$this->assertSame( 'plugin-admin', $loader->handle( 'admin' ) );
	}

	public function test_empty_subclass_prefix_returns_the_name_unchanged(): void {
		$loader = new AssetLoaderWithEmptyPrefix( '', '', 'assets' );

		$this->assertSame( 'frontend', $loader->handle( 'frontend' ) );
	}
}

/**
 * Subclass overriding HANDLE_PREFIX, to prove the override is honoured.
 */
final class AssetLoaderWithPluginPrefix extends AssetLoader {
	public const HANDLE_PREFIX = 'plugin-';
}

/**
 * Subclass with an empty prefix.
 */
final class AssetLoaderWithEmptyPrefix extends AssetLoader {
	public const HANDLE_PREFIX = '';
}
