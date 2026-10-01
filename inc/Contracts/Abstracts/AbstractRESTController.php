<?php
/**
 * Abstract REST controller class.
 *
 * Includes the shared namespace, version and hook registration.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use WP_REST_Controller;
use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;

/**
 * Class - AbstractRESTController
 */
abstract class AbstractRESTController extends WP_REST_Controller implements Registrable {
	/**
	 * Route namespace for the REST API routes.
	 *
	 * Usually at /wp-json/{namespace}/{route}
	 * Override in child class with your plugin/theme slug.
	 *
	 * @var string
	 */
	protected $namespace = '';

	/**
	 * Version number for the REST API routes.
	 *
	 * @var string
	 */
	protected string $version = '1';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register the controller's REST routes.
	 *
	 * The base throws to force a subclass override. A missing implementation is a
	 * programming error, so this raises `\LogicException` (was a bare `\Exception`).
	 * Implement it with `register_rest_route()` calls under `$this->namespace`.
	 *
	 * @throws \LogicException Always, unless a subclass overrides it.
	 *
	 * @codeCoverageIgnore
	 */
	public function register_routes(): void {
		throw new \LogicException( static::class . '::register_routes() must be overridden.' );
	}
}
