<?php
/**
 * PHPUnit bootstrap.
 *
 * Boots a real WordPress test environment (provided by wp-env) so the framework
 * is exercised against actual WordPress APIs rather than hand-written stubs.
 * The framework is a library — there is no plugin or theme to activate; tests
 * instantiate its classes directly once WordPress is loaded.
 *
 * @package rtCamp\WPPrimitives\Tests
 */

declare( strict_types = 1 );

define( 'TESTS_FRAMEWORK_DIR', dirname( __DIR__ ) );

// Load Composer dependencies (the framework's PSR-4 classes + dev tooling).
if ( file_exists( TESTS_FRAMEWORK_DIR . '/vendor/autoload.php' ) ) {
	require_once TESTS_FRAMEWORK_DIR . '/vendor/autoload.php';
}

// When run in the wp-env context, point at the test config it provides.
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) && false !== getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) );
}

/*
 * Locate the WordPress `tests/phpunit/` directory, in order of preference:
 *  - WP_TESTS_DIR        env var (a WordPress clone's tests/phpunit).
 *  - WP_DEVELOP_DIR      env var (a WordPress clone root) + /tests/phpunit.
 *  - WP_PHPUNIT__DIR     env var (the wp-phpunit composer package).
 *  - wp-env fallback path relative to this package.
 *  - /tmp/wordpress-tests-lib (WP-CLI scaffold default).
 */
if ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	$_test_root = getenv( 'WP_TESTS_DIR' );
} elseif ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$_test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} elseif ( false !== getenv( 'WP_PHPUNIT__DIR' ) ) {
	$_test_root = getenv( 'WP_PHPUNIT__DIR' );
} elseif ( file_exists( TESTS_FRAMEWORK_DIR . '/../../../../tests/phpunit/includes/functions.php' ) ) {
	$_test_root = TESTS_FRAMEWORK_DIR . '/../../../../tests/phpunit';
} else { // Fallback.
	$_test_root = '/tmp/wordpress-tests-lib';
}

// Fail with an actionable message instead of a raw "failed to open stream" if
// none of the resolution paths above found the WordPress test suite (e.g. when
// phpunit is run directly without the wp-env environment up).
if ( ! file_exists( $_test_root . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		"WordPress test suite not found at {$_test_root}.\n" .
		"Start the environment with `npm run wp-env start`, or set WP_TESTS_DIR to a WordPress tests/phpunit directory.\n"
	);
	exit( 1 );
}

require_once $_test_root . '/includes/functions.php';

// Load fixtures that contain multiple classes per file (PSR-4 only autoloads
// single-class files matching the class name).
require_once __DIR__ . '/Fixtures/LoaderFixtures.php';

// Start up the WP testing environment.
require $_test_root . '/includes/bootstrap.php';
