<?php
/**
 * AbstractPlatformLog tests.
 *
 * The base class reads nothing itself — the subclass owns the source — so
 * these feed raw log lines to an anonymous subclass and assert on the parsed
 * entries. parse_error_log_line() carries the only real logic here and gets
 * the bulk of the coverage.
 *
 * @package rtCamp\WPPrimitives\Tests\Contracts\Abstracts
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Contracts\Abstracts;

use rtCamp\WPPrimitives\Contracts\Abstracts\AbstractPlatformLog;
use rtCamp\WPPrimitives\Tests\TestCase;

/**
 * Tests for AbstractPlatformLog.
 *
 * @since 1.0.0
 */
final class AbstractPlatformLogTest extends TestCase {

	/**
	 * Minimal concrete AbstractPlatformLog backed by injected raw lines.
	 *
	 * @param string[]  $lines     Raw log lines, oldest first.
	 * @param bool|null $available is_available() override; null keeps the default.
	 */
	private function make_log( array $lines = [], ?bool $available = null ): AbstractPlatformLog {
		return new class( $lines, $available ) extends AbstractPlatformLog {
			public function __construct(
				private readonly array $lines,
				private readonly ?bool $available
			) {}

			public function is_available(): bool {
				return null === $this->available ? parent::is_available() : $this->available;
			}

			public function get_recent_entries( int $limit = 100 ): array {
				if ( ! $this->is_available() ) {
					return [];
				}

				$parsed = array_map(
					fn ( string $line ): ?array => $this->parse_error_log_line( $line ),
					array_slice( $this->lines, -$limit )
				);

				return array_reverse( array_values( array_filter( $parsed ) ) );
			}

			public function parse( string $line ): ?array {
				return $this->parse_error_log_line( $line );
			}
		};
	}

	public function test_is_available_defaults_to_true(): void {
		$this->assertTrue( $this->make_log()->is_available() );
	}

	public function test_parses_fatal_error_with_colon_line_format(): void {
		$line  = '[04-Aug-2026 12:34:56 UTC] PHP Fatal error:  Uncaught Error: Call to undefined function foo() in /var/www/html/wp-content/plugins/x/y.php:12';
		$entry = $this->make_log()->parse( $line );

		$this->assertSame( '04-Aug-2026 12:34:56 UTC', $entry['timestamp'] );
		$this->assertSame( 'fatal error', $entry['level'] );
		$this->assertSame( 'Uncaught Error: Call to undefined function foo()', $entry['message'] );
		$this->assertSame( '/var/www/html/wp-content/plugins/x/y.php', $entry['file'] );
		$this->assertSame( 12, $entry['line'] );
		$this->assertSame( $line, $entry['raw'] );
	}

	public function test_parses_warning_with_on_line_format(): void {
		$entry = $this->make_log()->parse( '[04-Aug-2026 12:34:56 UTC] PHP Warning:  Undefined variable $x in /var/www/html/index.php on line 42' );

		$this->assertSame( 'warning', $entry['level'] );
		$this->assertSame( 'Undefined variable $x', $entry['message'] );
		$this->assertSame( '/var/www/html/index.php', $entry['file'] );
		$this->assertSame( 42, $entry['line'] );
	}

	public function test_parses_deprecated_notice(): void {
		$entry = $this->make_log()->parse( '[04-Aug-2026 12:34:56 UTC] PHP Deprecated:  Function old_thing() is deprecated in /srv/app.php on line 7' );

		$this->assertSame( 'deprecated', $entry['level'] );
		$this->assertSame( 7, $entry['line'] );
	}

	public function test_level_is_unknown_without_a_php_marker(): void {
		$entry = $this->make_log()->parse( '[04-Aug-2026 12:34:56 UTC] Something a plugin logged directly' );

		$this->assertSame( 'unknown', $entry['level'] );
		$this->assertSame( 'Something a plugin logged directly', $entry['message'] );
		$this->assertNull( $entry['file'] );
		$this->assertNull( $entry['line'] );
	}

	public function test_entry_without_a_location_leaves_file_and_line_null(): void {
		$entry = $this->make_log()->parse( '[04-Aug-2026 12:34:56 UTC] PHP Notice:  Something happened' );

		$this->assertSame( 'Something happened', $entry['message'] );
		$this->assertNull( $entry['file'] );
		$this->assertNull( $entry['line'] );
	}

	public function test_last_in_wins_when_the_message_contains_its_own(): void {
		$entry = $this->make_log()->parse( '[04-Aug-2026 12:34:56 UTC] PHP Warning:  Trouble in paradise in /srv/app.php on line 3' );

		$this->assertSame( 'Trouble in paradise', $entry['message'] );
		$this->assertSame( '/srv/app.php', $entry['file'] );
		$this->assertSame( 3, $entry['line'] );
	}

	/**
	 * @dataProvider data_non_entries
	 *
	 * @param string $line A line that is not a log entry.
	 */
	public function test_returns_null_for_non_entries( string $line ): void {
		$this->assertNull( $this->make_log()->parse( $line ) );
	}

	/**
	 * @return array<string, array{string}> Lines that should not parse.
	 */
	public function data_non_entries(): array {
		return [
			'stack frame'             => [ '#0 /var/www/html/wp-includes/plugin.php(205): my_callback()' ],
			'continuation'            => [ '  thrown in /var/www/html/x.php on line 9' ],
			'blank'                   => [ '' ],
			'whitespace'              => [ '   ' ],
			// Xdebug timestamps these, so they reach the parser looking like entries.
			'timestamped trace header' => [ '[04-Aug-2026 12:34:56 UTC] PHP Stack trace:' ],
			'timestamped trace frame'  => [ '[04-Aug-2026 12:34:56 UTC] PHP   1. {main}() /srv/e.php:0' ],
			'timestamped frame hash'   => [ '[04-Aug-2026 12:34:56 UTC] #0 /srv/e.php(3): boom()' ],
		];
	}

	public function test_a_message_starting_with_a_number_is_still_an_entry(): void {
		$entry = $this->make_log()->parse( '[04-Aug-2026 12:34:56 UTC] PHP Warning:  2 items failed in /srv/a.php on line 1' );

		$this->assertSame( '2 items failed', $entry['message'] );
		$this->assertSame( 1, $entry['line'] );
	}

	public function test_get_recent_entries_returns_newest_first_and_drops_unparseable_lines(): void {
		$entries = $this->make_log(
			[
				'[04-Aug-2026 10:00:00 UTC] PHP Warning:  First in /srv/a.php on line 1',
				'#0 /srv/a.php(1): boom()',
				'[04-Aug-2026 11:00:00 UTC] PHP Warning:  Second in /srv/b.php on line 2',
			]
		)->get_recent_entries();

		$this->assertCount( 2, $entries );
		$this->assertSame( 'Second', $entries[0]['message'] );
		$this->assertSame( 'First', $entries[1]['message'] );
	}

	public function test_get_recent_entries_respects_the_limit(): void {
		$entries = $this->make_log(
			[
				'[04-Aug-2026 10:00:00 UTC] PHP Warning:  First in /srv/a.php on line 1',
				'[04-Aug-2026 11:00:00 UTC] PHP Warning:  Second in /srv/b.php on line 2',
				'[04-Aug-2026 12:00:00 UTC] PHP Warning:  Third in /srv/c.php on line 3',
			]
		)->get_recent_entries( 2 );

		$this->assertCount( 2, $entries );
		$this->assertSame( 'Third', $entries[0]['message'] );
		$this->assertSame( 'Second', $entries[1]['message'] );
	}

	public function test_is_available_override_propagates(): void {
		$log = $this->make_log(
			[ '[04-Aug-2026 10:00:00 UTC] PHP Warning:  First in /srv/a.php on line 1' ],
			false
		);

		$this->assertFalse( $log->is_available() );
		$this->assertSame( [], $log->get_recent_entries() );
	}
}
