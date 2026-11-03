<?php
/**
 * AbstractRESTController tests.
 *
 * Integration tests against real WordPress (wp-env): asserts routes register
 * on rest_api_init, the endpoint dispatches, and the base register_routes()
 * throws to force subclass implementation.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractRESTController;
use rtCamp\WPPrimitives\Tests\TestCase;
use WP_REST_Request;

final class AbstractRESTControllerTest extends TestCase {

	public function set_up(): void {
		parent::set_up();

		// Force a fresh REST server so rest_api_init fires again for each test.
		$GLOBALS['wp_rest_server'] = null;
	}

	private function controller(): AbstractRESTController {
		return new class() extends AbstractRESTController {
			protected $namespace = 'wpf-test/v1';

			public function register_routes(): void {
				register_rest_route(
					$this->namespace,
					'/ping',
					[
						'methods'             => 'GET',
						'callback'            => static fn () => rest_ensure_response( [ 'pong' => true ] ),
						'permission_callback' => '__return_true',
					]
				);
			}
		};
	}

	public function test_register_hooks_registers_routes_on_rest_api_init(): void {
		$controller = $this->controller();
		$controller->register_hooks();

		$this->assertNotFalse( has_action( 'rest_api_init', [ $controller, 'register_routes' ] ) );
	}

	public function test_route_is_registered_with_the_rest_server(): void {
		$this->controller()->register_hooks();

		// rest_get_server() boots the server and fires rest_api_init.
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/wpf-test/v1/ping', $routes );
	}

	public function test_route_responds_successfully(): void {
		$this->controller()->register_hooks();

		$response = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wpf-test/v1/ping' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'pong' => true ], $response->get_data() );
	}

	public function test_base_register_routes_throws_to_force_implementation(): void {
		$controller = new class() extends AbstractRESTController {
			protected $namespace = 'wpf-test/v1';
		};

		$this->expectException( \LogicException::class );

		$controller->register_routes();
	}
}
