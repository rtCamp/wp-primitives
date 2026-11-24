<?php
/**
 * Timer utility.
 *
 * @package rtCamp\WPPrimitives\Utils
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Utils;

/**
 * Class - Timer
 *
 * Named timers for one request. A timer can be started in one hook and stopped
 * in another when both use the same instance.
 *
 *     $timer = new Timer();
 *     $timer->start( 'render' );
 *     // ... Later, in another hook with the same instance:
 *     $elapsed = $timer->stop( 'render' );
 *
 * start(), stop() and lap() report an empty, duplicate, unstarted or stopped
 * label with `_doing_it_wrong()`. get() and get_all() emit no notices.
 *
 * Times are in seconds, as floats.
 *
 * @since 1.0.0
 */
class Timer {
	/**
	 * Active and completed timers, keyed by label.
	 *
	 * @var array<string, array{start: float, end: float|null, laps: array<string, float>}>
	 */
	private array $timers = [];

	/**
	 * Start a named timer.
	 *
	 * No-op (plus `_doing_it_wrong()`)  when the label is empty or already started.
	 *
	 * @param string $label Unique identifier for this timer.
	 */
	public function start( string $label ): void {
		if ( '' === $label ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Timer label must not be empty.', 'wp-primitives' ),
				'1.0.0'
			);
			return;
		}

		if ( isset( $this->timers[ $label ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: timer label. */
					esc_html__( 'Timer "%s" has already been started.', 'wp-primitives' ),
					esc_html( $label )
				),
				'1.0.0'
			);
			return;
		}

		$this->timers[ $label ] = [
			'start' => microtime( true ),
			'end'   => null,
			'laps'  => [],
		];
	}

	/**
	 * Stop a named timer and return its elapsed time.
	 *
	 * An empty or unstarted label returns 0.0 and triggers `_doing_it_wrong()`.
	 * A stopped timer returns its recorded elapsed time and triggers
	 * `_doing_it_wrong()`.
	 *
	 * @param string $label Timer to stop.
	 *
	 * @return float Elapsed seconds.
	 */
	public function stop( string $label ): float {
		if ( '' === $label ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Timer label must not be empty.', 'wp-primitives' ),
				'1.0.0'
			);
			return 0.0;
		}

		if ( ! isset( $this->timers[ $label ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: timer label. */
					esc_html__( 'Timer "%s" was never started.', 'wp-primitives' ),
					esc_html( $label )
				),
				'1.0.0'
			);
			return 0.0;
		}

		if ( null !== $this->timers[ $label ]['end'] ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: timer label. */
					esc_html__( 'Timer "%s" has already been stopped.', 'wp-primitives' ),
					esc_html( $label )
				),
				'1.0.0'
			);
			return $this->timers[ $label ]['end'] - $this->timers[ $label ]['start'];
		}

		$this->timers[ $label ]['end'] = microtime( true );

		return $this->timers[ $label ]['end'] - $this->timers[ $label ]['start'];
	}

	/**
	 * Record an intermediate split on a running timer.
	 *
	 * Stores the seconds elapsed since the timer started. Records nothing and
	 * triggers `_doing_it_wrong()` when either argument is empty or the timer is
	 * not running.
	 *
	 * @param string $label Timer label.
	 * @param string $name  Lap name.
	 */
	public function lap( string $label, string $name ): void {
		if ( '' === $label ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Timer label must not be empty.', 'wp-primitives' ),
				'1.0.0'
			);
			return;
		}

		if ( '' === $name ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html__( 'Lap name must not be empty.', 'wp-primitives' ),
				'1.0.0'
			);
			return;
		}

		if ( ! isset( $this->timers[ $label ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: timer label. */
					esc_html__( 'Timer "%s" was never started.', 'wp-primitives' ),
					esc_html( $label )
				),
				'1.0.0'
			);
			return;
		}

		if ( null !== $this->timers[ $label ]['end'] ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: timer label. */
					esc_html__( 'Timer "%s" has already been stopped.', 'wp-primitives' ),
					esc_html( $label )
				),
				'1.0.0'
			);
			return;
		}

		$this->timers[ $label ]['laps'][ $name ] = microtime( true ) - $this->timers[ $label ]['start'];
	}

	/**
	 * Get timing data for a single timer.
	 *
	 * Returns null for an empty or unknown label. For a running timer, `elapsed`
	 * is the time since it started.
	 *
	 * @param string $label Timer label.
	 *
	 * @return array{start: float, end: float|null, elapsed: float, laps: array<string, float>}|null
	 */
	public function get( string $label ): ?array {
		if ( '' === $label || ! isset( $this->timers[ $label ] ) ) {
			return null;
		}

		$timer = $this->timers[ $label ];

		$elapsed = null !== $timer['end']
			? $timer['end'] - $timer['start']
			: microtime( true ) - $timer['start'];

		return [
			'start'   => $timer['start'],
			'end'     => $timer['end'],
			'elapsed' => $elapsed,
			'laps'    => $timer['laps'],
		];
	}

	/**
	 * Get every recorded timer.
	 *
	 * The `elapsed` value of every running timer is measured against the same
	 * microtime() reading.
	 *
	 * @return array<string, array{start: float, end: float|null, elapsed: float, laps: array<string, float>}>
	 */
	public function get_all(): array {
		$now    = microtime( true );
		$result = [];

		foreach ( $this->timers as $label => $timer ) {
			$elapsed = null !== $timer['end']
				? $timer['end'] - $timer['start']
				: $now - $timer['start'];

			$result[ $label ] = [
				'start'   => $timer['start'],
				'end'     => $timer['end'],
				'elapsed' => $elapsed,
				'laps'    => $timer['laps'],
			];
		}

		return $result;
	}
}
