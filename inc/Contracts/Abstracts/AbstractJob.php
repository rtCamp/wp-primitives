<?php
/**
 * Abstract Job.
 *
 * @package rtCamp\WPPrimitives\Contracts\Abstracts
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Interfaces\Registrable;

/**
 * Class AbstractJob
 *
 * A background job that runs through Action Scheduler
 * (https://actionscheduler.org/), the de facto WordPress queue/scheduling
 * library. Action Scheduler is never a dependency of this package — every
 * scheduling method here guards on {@see AbstractJob::is_available()} and
 * degrades to a silent no-op when the library isn't loaded.
 *
 * `register_hooks()` always attaches the listener regardless of Action
 * Scheduler's presence: `handle()` runs off a plain WordPress action
 * (`static::get_hook()`), so `do_action( static::get_hook(), $args )` runs
 * the job synchronously even without Action Scheduler installed — scheduling
 * is an enhancement, not a requirement.
 *
 * Args are always a single associative array. `schedule_*()` wraps the
 * caller's `$args` as Action Scheduler's one positional argument, and
 * `register_hooks()` registers the listener with a fixed `accepted_args = 1`
 * to match — so `handle()` always receives one plain array regardless of
 * what it contains. Scheduling through the raw `as_*()` functions directly
 * (bypassing this class's own `schedule_*()` methods) breaks that contract.
 *
 * @since 1.0.0
 */
abstract class AbstractJob implements Registrable {

	/**
	 * Oldest Action Scheduler this class will talk to.
	 *
	 * 3.6.0 added action priorities and the `$unique` parameter to the API
	 * signatures; older copies drop both silently.
	 *
	 * @var string
	 */
	public const MINIMUM_ACTION_SCHEDULER_VERSION = '3.6.0';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action(
			static::get_hook(),
			function ( array $args = [] ): void {
				$this->handle( $args );
			},
			$this->get_hook_priority(),
			1
		);
	}

	/**
	 * Whether a supported Action Scheduler is loaded and its data store is ready.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		// The API check comes first: is_initialized() itself is missing from copies
		// older than the floor, so calling it unguarded would fatal. self:: is
		// deliberate — the helper is private, so there is nothing to late-bind.
		if ( ! class_exists( \ActionScheduler::class, false ) || ! self::supports_required_api() ) {
			return false;
		}

		return \ActionScheduler::is_initialized();
	}

	/**
	 * Whether the loaded copy exposes the API this class calls.
	 *
	 * @return bool
	 */
	private static function supports_required_api(): bool {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return false;
		}

		$version = class_exists( \ActionScheduler_Versions::class, false )
			? \ActionScheduler_Versions::instance()->latest_version()
			: false;

		if ( is_string( $version ) ) {
			return version_compare( $version, static::MINIMUM_ACTION_SCHEDULER_VERSION, '>=' );
		}

		// A theme-loaded copy registers no version, so fall back to the signature
		// itself: $priority, added in the minimum supported release, is 7th.
		return ( new \ReflectionFunction( 'as_schedule_recurring_action' ) )->getNumberOfParameters() >= 7;
	}

	/**
	 * Enqueues the job to run as soon as possible.
	 *
	 * @param array<string, mixed> $args Arguments passed to {@see handle()}.
	 *
	 * @return int|null The action ID, 0 when Action Scheduler declined to schedule
	 *                  it (most often an `is_unique()` duplicate), or null when
	 *                  Action Scheduler is unavailable and nothing was attempted.
	 */
	public function schedule_async( array $args = [] ): ?int {
		if ( ! static::is_available() ) {
			return null;
		}

		return as_enqueue_async_action( static::get_hook(), [ $args ], $this->get_group(), $this->is_unique(), $this->get_queue_priority() );
	}

	/**
	 * Schedules the job to run once, at a given time.
	 *
	 * @param int                  $timestamp Unix timestamp to run at.
	 * @param array<string, mixed> $args      Arguments passed to {@see handle()}.
	 *
	 * @return int|null The action ID, 0 when Action Scheduler declined to schedule
	 *                  it, or null when Action Scheduler is unavailable.
	 */
	public function schedule_at( int $timestamp, array $args = [] ): ?int {
		if ( ! static::is_available() ) {
			return null;
		}

		return as_schedule_single_action( $timestamp, static::get_hook(), [ $args ], $this->get_group(), $this->is_unique(), $this->get_queue_priority() );
	}

	/**
	 * Schedules the job to run repeatedly.
	 *
	 * @param int                  $timestamp           When the first run happens.
	 * @param int                  $interval_in_seconds How long to wait between runs.
	 * @param array<string, mixed> $args                Arguments passed to {@see handle()}.
	 *
	 * @return int|null The action ID, 0 when Action Scheduler declined to schedule
	 *                  it, or null when the interval is not positive or Action
	 *                  Scheduler is unavailable.
	 */
	public function schedule_recurring( int $timestamp, int $interval_in_seconds, array $args = [] ): ?int {
		// Action Scheduler turns a 0 interval into a one-off action and walks a
		// negative one backwards, leaving it permanently overdue.
		if ( $interval_in_seconds < 1 ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %d: The interval, in seconds, that was passed. */
					esc_html__( 'A positive interval is required, %d given. Use schedule_at() for a one-off job.', 'wp-primitives' ),
					esc_html( (string) $interval_in_seconds )
				),
				'1.0.0'
			);

			return null;
		}

		if ( ! static::is_available() ) {
			return null;
		}

		return as_schedule_recurring_action( $timestamp, $interval_in_seconds, static::get_hook(), [ $args ], $this->get_group(), $this->is_unique(), $this->get_queue_priority() );
	}

	/**
	 * Whether a matching pending or running action is already scheduled.
	 *
	 * @param array<string, mixed> $args Arguments to match.
	 *
	 * @return bool
	 */
	public function is_scheduled( array $args = [] ): bool {
		if ( ! static::is_available() ) {
			return false;
		}

		return as_has_scheduled_action( static::get_hook(), [ $args ], $this->get_group() );
	}

	/**
	 * Cancels the next matching pending occurrence, if any.
	 *
	 * @param array<string, mixed> $args Arguments to match.
	 *
	 * @return int|null The cancelled action ID, or null when none matched or Action Scheduler is unavailable.
	 */
	public function unschedule( array $args = [] ): ?int {
		if ( ! static::is_available() ) {
			return null;
		}

		return as_unschedule_action( static::get_hook(), [ $args ], $this->get_group() );
	}

	/**
	 * Return the WordPress action hook this job runs on.
	 *
	 * @return string e.g. "my-plugin/send-welcome-email".
	 */
	abstract public static function get_hook(): string;

	/**
	 * Do the actual work.
	 *
	 * @param array<string, mixed> $args The arguments the job was scheduled with.
	 *
	 * @return void
	 */
	abstract protected function handle( array $args ): void;

	/**
	 * Return the Action Scheduler group this job's actions belong to.
	 *
	 * Empty by default — override to group related jobs for bulk
	 * management/inspection.
	 *
	 * @return string
	 */
	protected function get_group(): string {
		return '';
	}

	/**
	 * Return the WordPress hook priority `register_hooks()` registers `handle()` at.
	 *
	 * Orders this listener against other callbacks on the same hook — unrelated
	 * to {@see get_queue_priority()}.
	 *
	 * @return int
	 */
	protected function get_hook_priority(): int {
		return 10;
	}

	/**
	 * Return the Action Scheduler queue priority the `schedule_*()` methods pass.
	 *
	 * Orders this job against other queued actions — lower runs first. Action
	 * Scheduler clamps it to 0-255, unlike a WordPress hook priority.
	 *
	 * @return int
	 */
	protected function get_queue_priority(): int {
		return 10;
	}

	/**
	 * Whether a scheduled action should be unique.
	 *
	 * When true, Action Scheduler skips scheduling if a pending or running
	 * action already exists with the same hook, group, and arguments (arguments
	 * count only from Action Scheduler 4.0.0).
	 *
	 * @return bool
	 */
	protected function is_unique(): bool {
		return false;
	}
}
