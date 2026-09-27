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
 * Named timing segments that persist across scopes within a single request: start a
 * timer in one hook or file and stop it in another, with no globals or hand-passed
 * `microtime( true )` values.
 *
 * Instance-based, not a singleton. The cross-scope sharing the start-here / stop-there
 * pattern needs is met by holding one instance and sharing it — register it as
 * `Shareable` in the consumer's container (the same pattern as {@see Cache}) so
 * every hook resolves the same object. A theme and a plugin
 * in the same process then each keep their own decoupled timer set, instead of writing
 * into one global instance:
 *
 *     $timer = new Timer();
 *     $timer->start( 'render' );
 *     // … later, in another hook holding the same instance …
 *     $elapsed = $timer->stop( 'render' ); // float seconds
 *
 * Misuse (empty/duplicate/never-started/already-stopped labels) is reported via
 * `_doing_it_wrong()` — the WordPress convention for developer error — rather than by
 * throwing. The read methods ({@see get()}, {@see get_all()}) stay silent and simply
 * return null / an empty entry, so inspecting timers never emits notices.
 *
 * Times are returned as float seconds, consistent with `$wpdb->queries` timing.
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
	 * No-op (plus `_doing_it_wrong()`) when the label is empty or already started — a
	 * second start would otherwise silently discard the original start time.
	 *
	 * @param string $label Unique identifier for this timer.
	 *
	 * @return void
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
	 * Return paths:
	 *   - empty label        → 0.0 plus `_doing_it_wrong()`;
	 *   - never-started label → 0.0 plus `_doing_it_wrong()`;
	 *   - already-stopped    → cached elapsed plus `_doing_it_wrong()` (no recompute);
	 *   - running            → fresh elapsed, and the timer is marked stopped.
	 *
	 * @param string $label Timer to stop.
	 *
	 * @return float Elapsed seconds (0.0 when never started or the label is empty).
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
	 * Stores the elapsed seconds since start at the moment {@see lap()} is called.
	 * Reports via `_doing_it_wrong()` (and records nothing) when either argument is
	 * empty, the timer was never started, or it has already been stopped.
	 *
	 * @param string $label Timer label.
	 * @param string $name  Lap name.
	 *
	 * @return void
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
	 * Silent read: returns null for an empty or unknown label, and never emits a
	 * notice. For a still-running timer, `elapsed` is the time since start at the
	 * moment {@see get()} is called, without stopping it.
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
	 * Each entry carries a computed `elapsed`; for running timers that is the
	 * time-since-start measured against a single snapshot taken when
	 * {@see get_all()} is called, so every running timer is read against the same
	 * "now" regardless of iteration order.
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
