<?php
/**
 * Abstract Ability.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

/**
 * Class AbstractAbility
 *
 * Base class for a WordPress Abilities API ability (WordPress 6.9+). A
 * subclass declares the pieces of an ability — name, label, description,
 * category, schemas, and the execute callback — and args() maps them to the
 * argument array wp_register_ability() expects. Registration itself is
 * performed by an {@see AbstractAbilityRegistrar}, which registers the shared
 * category and loops its abilities on the Abilities API init hooks.
 *
 * Exposure is opt-in: meta() defaults to empty, so an ability is not exposed
 * over REST and carries no MCP flag unless the subclass says so. The
 * permission gate defaults to `manage_options` (fail-closed).
 *
 * @since 1.0.0
 */
abstract class AbstractAbility {

	/**
	 * Return the fully-qualified ability name, e.g. "my-plugin/do-thing".
	 *
	 * Must match the Abilities API name pattern `^[a-z0-9-]+/[a-z0-9-]+$`
	 * (namespace prefix, a slash, then the ability slug).
	 *
	 * @return string Ability name.
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
	 * Written for the caller deciding whether to invoke the ability — for an
	 * MCP-exposed ability that caller is an AI agent choosing a tool.
	 *
	 * @return string Description.
	 */
	abstract protected function description(): string;

	/**
	 * Return the slug of the category this ability belongs to.
	 *
	 * The Abilities API requires every ability to name a registered category.
	 * The {@see AbstractAbilityRegistrar} that registers this ability
	 * registers the category first, so the two normally return the same slug.
	 *
	 * @return string Category slug.
	 */
	abstract protected function category(): string;

	/**
	 * Return the JSON Schema describing the ability input.
	 *
	 * Return an empty array for an ability that takes no input; args() then
	 * omits the key entirely.
	 *
	 * @return array<string, mixed> Input schema.
	 */
	abstract protected function input_schema(): array;

	/**
	 * Return the JSON Schema describing the ability output.
	 *
	 * Return an empty array to leave the output unspecified; args() then
	 * omits the key entirely.
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
	 * Decide whether the current user may execute the ability.
	 *
	 * Defaults to `manage_options` — fail-closed, administrators only.
	 * Override to gate on a different capability or on the input.
	 *
	 * @param mixed $input Input the ability is being called with.
	 *
	 * @return bool|\WP_Error True to allow; false or a WP_Error to deny.
	 */
	protected function permission( mixed $input = null ): bool|\WP_Error { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- $input is the override seam; the default gate ignores it.
		return current_user_can( 'manage_options' );
	}

	/**
	 * Return the ability meta.
	 *
	 * Defaults to empty, which keeps the Abilities API defaults — not exposed
	 * over REST, no MCP flag. Override to opt in, e.g.
	 * `[ 'show_in_rest' => true ]`.
	 *
	 * @return array<string, mixed> Meta.
	 */
	protected function meta(): array {
		return [];
	}

	/**
	 * Build the registration arguments for wp_register_ability().
	 *
	 * The schema and meta keys are included only when non-empty, so the
	 * Abilities API applies its own defaults otherwise. Both callbacks are
	 * wrapped in closures with a defaulted parameter: when an ability
	 * declares no input schema, core invokes its callbacks with no arguments
	 * at all, and the wrappers keep that call safe for the required
	 * execute() signature (and keep permission() protected).
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
