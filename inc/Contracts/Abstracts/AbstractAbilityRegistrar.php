<?php
/**
 * Abstract Ability Registrar.
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
 * Registers a group of {@see AbstractAbility} services with the WordPress
 * Abilities API (WordPress 6.9+): the shared category on
 * `wp_abilities_api_categories_init`, then each ability on
 * `wp_abilities_api_init` — the only hooks those registrations are legal on.
 * Core fires both lazily, the first time the abilities registry is accessed.
 *
 * On cores older than 6.9 the hooks never fire, so the registrar is inert and
 * the package's WordPress floor is unchanged. The function_exists guards
 * inside the callbacks are belt-and-braces for partial backports.
 *
 * Category registration is idempotent: the wp_register_ability_category()
 * call is skipped when the slug is already registered, so several registrars
 * can share one category and load in any order.
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
	 *
	 * The Abilities API rejects a category without a non-empty description,
	 * so there is no default. Written for the caller browsing the category —
	 * for MCP-exposed abilities that caller is an AI agent.
	 *
	 * @return string Category description.
	 */
	abstract protected function category_description(): string;

	/**
	 * Return the human-readable category label.
	 *
	 * Defaults to a title-cased version of the slug ("my-plugin" → "My Plugin").
	 * Override to provide a more descriptive name.
	 *
	 * @return string Category label.
	 */
	protected function category_label(): string {
		return ucwords( str_replace( [ '-', '_' ], ' ', $this->category_slug() ) );
	}

	/**
	 * Runs immediately before the abilities are registered.
	 *
	 * Empty by default. Override for one-time preparation the abilities
	 * depend on — ensuring storage exists, priming options, and so on.
	 */
	protected function before_register(): void {}
}
