<?php
/**
 * Timer utility tests.
 *
 * @package rtCamp\WPPrimitives\Tests\Utils
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Utils;

use rtCamp\WPPrimitives\Tests\TestCase;
use rtCamp\WPPrimitives\Utils\Timer;

/**
 * Tests for Timer.
 *
 * Runs against real WordPress (wp-env) so `_doing_it_wrong()` routes through WP's real
 * handler and the polyfilled setExpectedIncorrectUsage() picks misuse up without
 * stubbing. Timer is instance-based, so each test gets a fresh instance — no process
 * state leaks between tests (unlike the old singleton form).
 *
 * usleep() may sleep slightly longer than asked depending on scheduler load, so timing
 * assertions only check `> 0.0` with `< 1.0` as a runaway guard — never an exact value.
 */
final class TimerTest extends TestCase {

	/**
	 * Fresh timer per test.
	 *
	 * @var Timer
	 */
	private Timer $timer;

	/**
	 * Build a fresh, isolated Timer for each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->timer = new Timer();
	}

	public function test_start_then_stop_returns_positive_elapsed(): void {
		$this->timer->start( 'basic' );
		usleep( 10000 );
		$elapsed = $this->timer->stop( 'basic' );

		$this->assertGreaterThan( 0.0, $elapsed );
		$this->assertLessThan( 1.0, $elapsed );
	}

	public function test_stop_without_start_returns_zero_and_warns(): void {
		$this->setExpectedIncorrectUsage( Timer::class . '::stop' );

		$this->assertSame( 0.0, $this->timer->stop( 'never_started' ) );
	}

	public function test_start_twice_warns_and_keeps_the_first_start(): void {
		$this->setExpectedIncorrectUsage( Timer::class . '::start' );

		$this->timer->start( 'double_start' );
		$first = $this->timer->get( 'double_start' );

		usleep( 5000 );
		$this->timer->start( 'double_start' ); // Warns; first start wins.
		$second = $this->timer->get( 'double_start' );

		$this->assertSame( $first['start'], $second['start'] );
	}

	public function test_empty_label_start_warns_and_stores_nothing(): void {
		$this->setExpectedIncorrectUsage( Timer::class . '::start' );

		$this->timer->start( '' );

		$this->assertNull( $this->timer->get( '' ) );
	}

	public function test_stop_empty_label_warns_and_returns_zero(): void {
		$this->setExpectedIncorrectUsage( Timer::class . '::stop' );

		$this->assertSame( 0.0, $this->timer->stop( '' ) );
	}

	public function test_stop_already_stopped_returns_cached_elapsed(): void {
		$this->timer->start( 'stop_twice' );
		usleep( 5000 );
		$first = $this->timer->stop( 'stop_twice' );

		usleep( 5000 );
		$this->setExpectedIncorrectUsage( Timer::class . '::stop' );
		$second = $this->timer->stop( 'stop_twice' ); // Warns; returns cached elapsed.

		$this->assertSame( $first, $second );
	}

	public function test_lap_records_intermediate_time_in_order(): void {
		$this->timer->start( 'with_laps' );
		usleep( 5000 );
		$this->timer->lap( 'with_laps', 'checkpoint_1' );
		usleep( 5000 );
		$this->timer->lap( 'with_laps', 'checkpoint_2' );
		$this->timer->stop( 'with_laps' );

		$data = $this->timer->get( 'with_laps' );

		$this->assertArrayHasKey( 'checkpoint_1', $data['laps'] );
		$this->assertArrayHasKey( 'checkpoint_2', $data['laps'] );
		$this->assertGreaterThan( 0.0, $data['laps']['checkpoint_1'] );
		$this->assertGreaterThan( $data['laps']['checkpoint_1'], $data['laps']['checkpoint_2'] );
	}

	public function test_lap_without_start_warns(): void {
		$this->setExpectedIncorrectUsage( Timer::class . '::lap' );

		$this->timer->lap( 'ghost', 'nope' );
	}

	public function test_lap_after_stop_warns(): void {
		$this->setExpectedIncorrectUsage( Timer::class . '::lap' );

		$this->timer->start( 'stopped_lap' );
		$this->timer->stop( 'stopped_lap' );
		$this->timer->lap( 'stopped_lap', 'too_late' );
	}

	public function test_lap_empty_label_warns_and_records_nothing(): void {
		$this->setExpectedIncorrectUsage( Timer::class . '::lap' );

		$this->timer->start( 'lap_target' );
		$this->timer->lap( '', 'split' );

		$data = $this->timer->get( 'lap_target' );
		$this->assertEmpty( $data['laps'], 'an empty label must not record a lap on any timer' );
	}

	public function test_lap_empty_name_warns_and_records_nothing(): void {
		$this->setExpectedIncorrectUsage( Timer::class . '::lap' );

		$this->timer->start( 'empty_lap_name' );
		$this->timer->lap( 'empty_lap_name', '' );

		$data = $this->timer->get( 'empty_lap_name' );
		$this->assertEmpty( $data['laps'] );
	}

	public function test_get_running_timer_returns_elapsed_so_far(): void {
		$this->timer->start( 'still_running' );
		usleep( 10000 );

		$data = $this->timer->get( 'still_running' );

		$this->assertNull( $data['end'] );
		$this->assertGreaterThan( 0.0, $data['elapsed'] );
	}

	public function test_get_unknown_or_empty_label_returns_null_silently(): void {
		$this->assertNull( $this->timer->get( 'does_not_exist' ) );
		$this->assertNull( $this->timer->get( '' ) );
	}

	public function test_get_all_includes_running_and_stopped(): void {
		$this->timer->start( 'all_one' );
		$this->timer->start( 'all_two' );
		$this->timer->stop( 'all_one' );

		$all = $this->timer->get_all();

		$this->assertArrayHasKey( 'all_one', $all );
		$this->assertArrayHasKey( 'all_two', $all );
		$this->assertNotNull( $all['all_one']['end'] );
		$this->assertNull( $all['all_two']['end'] );
	}
}
