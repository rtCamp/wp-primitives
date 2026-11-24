<?php
/**
 * Base class for a WordPress Abilities API ability (WordPress 6.9+).
 *
 * Registration itself is performed by an {@see AbstractAbilityRegistrar}, which registers the shared
 * category and loops its abilities on the Abilities API init hooks.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

/**
 * Class AbstractAbility
 *
 * @since 1.0.0
 */
abstract class AbstractAbility {
	/**
	 * Return the fully-qualified ability name, e.g. "my-plugin/do-thing".
	 *
	 * Must match the Abilities API name pattern `<namespace prefix>/<ability slug>`.
	 *
	 * @return lowercase-string&non-falsy-string Ability name.
	 */
	abstract public function name(): string;

	/**
	 * Return the human-readable label.
	 *
	 * @return string Label.
	 */
	abstract protected function label(): string;

	/**
	 * Return the description of what the ability does.
	 *
	 * Written for the caller deciding whether to invoke the ability. For an ability exposed over MCP, that caller is
	 * the AI agent choosing a tool.
	 *
	 * @return string Description.
	 */
	abstract protected function description(): string;

	/**
	 * Return the slug of the category this ability belongs to.
	 *
	 * The Abilities API requires every ability to name a registered category.
	 * The {@see AbstractAbilityRegistrar} that registers this ability registers the category first, so the two normally return the same slug.
	 *
	 * @return string Category slug.
	 */
	abstract protected function category(): string;

	/**
	 * The JSON Schema describing the ability input.
	 *
	 * Return an empty array for an ability that takes no input.
	 *
	 * @return array<string, mixed> Input schema.
	 */
	abstract protected function input_schema(): array;

	/**
	 * The JSON Schema describing the ability output.
	 *
	 * @return array<string, mixed> Output schema.
	 */
	abstract protected function output_schema(): array;

	/**
	 * Execute the ability.
	 *
	 * @param mixed $input Input validated against the input schema.
	 *
	 * @return mixed|\WP_Error Result data, or an error.
	 */
	abstract public function execute( mixed $input ): mixed;

	/**
	 * Whether the current user may execute the ability.
	 *
	 * @param mixed $input Input the ability is being called with.
	 *
	 * @return bool|\WP_Error True to allow; false or a WP_Error to deny.
	 */
	protected function permission( mixed $input = null ): bool|\WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Available for overrides that use $input.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Return the ability meta.
	 *
	 * Leave empty to keep the Abilities API defaults. Override to change them, e.g.
	 * `[ 'show_in_rest' => true ]`
	 *
	 * @return array<string, mixed> Meta.
	 */
	protected function meta(): array {
		return [];
	}

	/**
	 * Builds the registration arguments for wp_register_ability().
	 *
	 * @return array<string, mixed> Registration args.
	 */
	public function args(): array {
		$args = [
			'label'               => $this->label(),
			'description'         => $this->description(),
			'category'            => $this->category(),
			'execute_callback'    => fn ( mixed $input = null ): mixed => $this->execute( $input ),
			'permission_callback' => fn ( mixed $input = null ): bool|\WP_Error => $this->permission( $input ),
		];

		$input_schema = $this->input_schema();
		if ( [] !== $input_schema ) {
			$args['input_schema'] = $input_schema;
		}

		$output_schema = $this->output_schema();
		if ( [] !== $output_schema ) {
			$args['output_schema'] = $output_schema;
		}

		$meta = $this->meta();
		if ( [] !== $meta ) {
			$args['meta'] = $meta;
		}

		return $args;
	}
}
