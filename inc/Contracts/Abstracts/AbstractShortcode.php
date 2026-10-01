<?php
/**
 * Abstract Shortcode class.
 *
 * Class to be extended by all shortcodes. Handles registration and provides a clean render interface.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;

/**
 * Class - AbstractShortcode
 */
abstract class AbstractShortcode implements Registrable {
	/**
	 * Get the shortcode tag name.
	 *
	 * @return non-empty-string
	 */
	abstract public static function get_tag(): string;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'init', [ $this, 'register_shortcode' ] );
	}

	/**
	 * Register the shortcode with WordPress.
	 */
	public function register_shortcode(): void {
		add_shortcode( static::get_tag(), [ $this, 'shortcode_callback' ] );
	}

	/**
	 * Shortcode callback wrapper. Parses attributes and delegates to render().
	 *
	 * @param array|string $atts    Shortcode attributes.
	 * @param string|null  $content Enclosed content (if any).
	 *
	 * @return string Rendered shortcode output.
	 */
	public function shortcode_callback( $atts, ?string $content = null ): string {
		$atts = shortcode_atts( $this->default_atts(), (array) $atts, static::get_tag() );

		return $this->render( $atts, $content );
	}

	/**
	 * Render the shortcode output.
	 *
	 * @param array<string, mixed> $atts    Parsed shortcode attributes.
	 * @param string|null          $content Enclosed content (if any).
	 *
	 * @return string The shortcode HTML output.
	 */
	abstract protected function render( array $atts, ?string $content ): string;

	/**
	 * Default shortcode attributes.
	 *
	 * Override in child class to define defaults.
	 *
	 * @return array<string, mixed>
	 */
	protected function default_atts(): array {
		return [];
	}
}
