<?php
/**
 * Logger utility tests.
 *
 * @package rtCamp\WPPrimitives\Tests\Utils
 */

declare( strict_types = 1 );

namespace rtCamp\WPPrimitives\Tests\Utils;

use rtCamp\WPPrimitives\Tests\TestCase;
use rtCamp\WPPrimitives\Utils\Logger;

/**
 * Tests for Logger.
 *
 * Runs against real WordPress (wp-env), where WP_DEBUG is true — so the default logger
 * writes, and wp_json_encode() is the real function rather than a stub. Output is
 * captured by pointing PHP's error_log at a per-test temp file. The "silent in
 * production" path is exercised through a subclass that overrides the is_enabled() seam
 * to return false, since the WP_DEBUG constant cannot be toggled mid-request.
 *
 * Assertions match on the unique message each test logs rather than on an empty file,
 * so unrelated error_log noise from the environment can't make a test flaky.
 */
final class LoggerTest extends TestCase {

	/**
	 * Temp file capturing error_log() output for the current test.
	 *
	 * @var string
	 */
	private string $log_file;

	/**
	 * Original error_log ini value, restored in tearDown().
	 *
	 * @var string
	 */
	private string $original_error_log;

	/**
	 * Point PHP's error_log at a fresh temp file for the duration of the test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$tmp = tempnam( sys_get_temp_dir(), 'rt-logger-' );
		$this->assertNotFalse( $tmp, 'Failed to create temp file for capturing error_log output.' );

		$this->log_file           = $tmp;
		$this->original_error_log = (string) ini_get( 'error_log' );
		ini_set( 'error_log', $this->log_file );
	}

	/**
	 * Restore the original error_log destination and remove the temp file.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		ini_set( 'error_log', $this->original_error_log );

		if ( file_exists( $this->log_file ) ) {
			unlink( $this->log_file );
		}

		parent::tearDown();
	}

	/**
	 * Read whatever has been written to the captured error_log so far.
	 *
	 * @return string
	 */
	private function captured(): string {
		return (string) file_get_contents( $this->log_file );
	}

	public function test_writes_level_prefix_message_and_context(): void {
		( new Logger( 'test-prefix' ) )->info( 'cache warmed', [ 'items' => 42 ] );

		$contents = $this->captured();
		$this->assertStringContainsString( '[INFO]', $contents );
		$this->assertStringContainsString( '[test-prefix]', $contents );
		$this->assertStringContainsString( 'cache warmed', $contents );
		$this->assertStringContainsString( '{"items":42}', $contents );
	}

	public function test_each_level_writes_its_uppercased_label(): void {
		$logger = new Logger( 'lvl' );

		$logger->debug( 'd-msg' );
		$logger->info( 'i-msg' );
		$logger->warning( 'w-msg' );
		$logger->error( 'e-msg' );

		$contents = $this->captured();
		$this->assertStringContainsString( '[DEBUG] [lvl] d-msg', $contents );
		$this->assertStringContainsString( '[INFO] [lvl] i-msg', $contents );
		$this->assertStringContainsString( '[WARNING] [lvl] w-msg', $contents );
		$this->assertStringContainsString( '[ERROR] [lvl] e-msg', $contents );
	}

	public function test_default_prefix_is_rtcamp(): void {
		( new Logger() )->error( 'boom' );

		$this->assertStringContainsString( '[ERROR] [rtcamp] boom', $this->captured() );
	}

	public function test_no_context_omits_the_json_segment(): void {
		( new Logger( 'p' ) )->info( 'plain-line-no-context' );

		$contents = $this->captured();
		$this->assertStringContainsString( '[INFO] [p] plain-line-no-context', $contents );
		// No trailing space + JSON when context is empty.
		$this->assertStringNotContainsString( 'plain-line-no-context {', $contents );
	}

	public function test_default_logger_is_enabled_under_wp_debug(): void {
		// The wp-env test environment defines WP_DEBUG = true, so the default
		// is_enabled() gate is open and the line is written.
		$this->assertTrue( defined( 'WP_DEBUG' ) && WP_DEBUG, 'wp-env should run tests with WP_DEBUG enabled.' );

		( new Logger( 'enabled' ) )->info( 'written-because-debug' );

		$this->assertStringContainsString( 'written-because-debug', $this->captured() );
	}

	public function test_unencodable_context_is_dropped_without_trailing_space(): void {
		// INF can't be JSON-encoded, so wp_json_encode() returns false. The message
		// must still log, with the context dropped and no stray trailing space.
		( new Logger( 'p' ) )->error( 'enc-fail', [ 'x' => INF ] );

		$contents = $this->captured();
		$this->assertStringContainsString( '[ERROR] [p] enc-fail', $contents );
		$this->assertStringNotContainsString( 'enc-fail ', $contents );
	}

	public function test_writes_nothing_when_logging_disabled(): void {
		$logger = new class( 'silent' ) extends Logger {
			protected function is_enabled(): bool {
				return false;
			}
		};

		$logger->error( 'should-not-be-written' );

		$this->assertStringNotContainsString( 'should-not-be-written', $this->captured() );
	}
}
