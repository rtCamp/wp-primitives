<?php
/**
 * Interface for WP_CLI commands.
 *
 * @package rtCamp\WPPrimitives\Contracts\Interfaces
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Interfaces;

/**
 * Interface - CLICommand
 */
interface CLICommand {
	/**
	 * Get the command name.
	 */
	public static function get_name(): string;

	/**
	 * Get the command description.
	 */
	public static function get_description(): string;

	/**
	 * Run the command.
	 *
	 * @param array<int, mixed>    $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public static function run( array $args, array $assoc_args ): void;
}
