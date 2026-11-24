<?php
/**
 * Abstract Ability Registrar.
 *
 * Registers a group of {@see AbstractAbility} instances with the WordPress Abilities API (WordPress 6.9+).
 * The category is registered on `wp_abilities_api_categories_init` and each ability on `wp_abilities_api_init`.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;

/**
 * Class AbstractAbilityRegistrar
 *
 * @since 1.0.0
 */
abstract class AbstractAbilityRegistrar implements Registrable {
	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/**
	 * Registers the ability category, unless another registrar already has.
	 */
	public function register_category(): void {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( wp_has_ability_category( $this->category_slug() ) ) {
			return;
		}

		wp_register_ability_category(
			$this->category_slug(),
			[
				'label'       => $this->category_label(),
				'description' => $this->category_description(),
			]
		);
	}

	/**
	 * Registers every ability the registrar manages.
	 */
	public function register_abilities(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$this->before_register();

		foreach ( $this->abilities() as $ability ) {
			wp_register_ability( $ability->name(), $ability->args() );
		}
	}

	/**
	 * Return the slug of the category the abilities are grouped under.
	 *
	 * @return string Category slug.
	 */
	abstract protected function category_slug(): string;

	/**
	 * Return the abilities to register.
	 *
	 * @return AbstractAbility[] Ability instances.
	 */
	abstract protected function abilities(): array;

	/**
	 * Return the category description.
	 * Written for the caller browsing the category. For abilities exposed over MCP, that caller is an AI agent.
	 *
	 * @return string Category description.
	 */
	abstract protected function category_description(): string;

	/**
	 * Return the human-readable category label.
	 *
	 * Defaults to the slug in title case, so "my-plugin" becomes "My Plugin".
	 *
	 * @return string Category label.
	 */
	protected function category_label(): string {
		return ucwords( str_replace( [ '-', '_' ], ' ', $this->category_slug() ) );
	}

	/**
	 * Runs immediately before the abilities are registered.
	 *
	 * Empty by default. Override for setup the abilities depend on, such as creating storage or priming options.
	 */
	protected function before_register(): void {}
}
