<?php
/**
 * Abstract Block class.
 *
 * Base class for a dynamic WordPress block.
 *
 * For a static block with no server-side render, use {@see \rtCamp\WPPrimitives\AssetLoader::register_block_manifest()}.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;

/**
 * Class - AbstractBlock
 */
abstract class AbstractBlock implements Registrable {
	/**
	 * Get the block name (including namespace).
	 *
	 * E.g. 'my-plugin/hero-banner'.
	 *
	 * @return non-empty-string
	 */
	abstract public static function get_name(): string;

	/**
	 * Render the block on the server side.
	 *
	 * @internal Called by WordPress as the block's render_callback.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Block inner content.
	 * @param \WP_Block            $block      Block instance.
	 *
	 * @return string Rendered block HTML.
	 */
	abstract public function render( array $attributes, string $content, \WP_Block $block ): string;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Register the block type with WordPress.
	 *
	 * Registers from get_block_dir() when that directory contains a block.json, and by name otherwise,
	 * so the server-side render still works before `npm run build`.
	 */
	public function register_block(): void {
		$args = $this->get_block_args();

		// Force our render method, overriding any render_callback from get_block_args().
		$args['render_callback'] = [ $this, 'render' ];
		$block_dir               = $this->get_block_dir();

		if ( $block_dir && file_exists( trailingslashit( $block_dir ) . 'block.json' ) ) {
			register_block_type( $block_dir, $args );
		} else {
			register_block_type( static::get_name(), $args );
		}
	}

	/**
	 * Get the path to the block's build directory (containing block.json).
	 *
	 * Return null to register without a block.json (purely programmatic block).
	 *
	 * @return string|null Absolute path to the block directory, or null.
	 */
	protected function get_block_dir(): ?string {
		return null;
	}

	/**
	 * Additional arguments to pass to register_block_type().
	 *
	 * Override to add attributes, supports, styles, etc.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_block_args(): array {
		return [];
	}
}
