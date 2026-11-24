<?php
/**
 * PHPUnit bootstrap.
 *
 * @package rtCamp\WPPrimitives\Tests
 */

declare( strict_types = 1 );

define( 'TESTS_REPO_ROOT_DIR', dirname( __DIR__, 2 ) );

// Load Composer dependencies (the framework's PSR-4 classes + dev tooling).
if ( file_exists( TESTS_REPO_ROOT_DIR . '/vendor/autoload.php' ) ) {
	require_once TESTS_REPO_ROOT_DIR . '/vendor/autoload.php';
}

// wp-env provides its own test config.
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) && false !== getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) );
}

/*
 * Locate the WordPress `tests/phpunit/` directory, in order of preference:
 *  - WP_TESTS_DIR        env var (a WordPress clone's tests/phpunit).
 *  - WP_DEVELOP_DIR      env var (a WordPress clone root) + /tests/phpunit.
 *  - WP_PHPUNIT__DIR     env var (the wp-phpunit composer package).
 *  - A wordpress-develop checkout, with this package in src/wp-content/plugins/.
 *  - /tmp/wordpress-tests-lib (WP-CLI scaffold default).
 */
if ( false !== getenv( 'WP_TESTS_DIR' ) ) {
	$_test_root = getenv( 'WP_TESTS_DIR' );
} elseif ( false !== getenv( 'WP_DEVELOP_DIR' ) ) {
	$_test_root = getenv( 'WP_DEVELOP_DIR' ) . '/tests/phpunit';
} elseif ( false !== getenv( 'WP_PHPUNIT__DIR' ) ) {
	$_test_root = getenv( 'WP_PHPUNIT__DIR' );
} elseif ( file_exists( TESTS_REPO_ROOT_DIR . '/../../../../tests/phpunit/includes/functions.php' ) ) {
	$_test_root = TESTS_REPO_ROOT_DIR . '/../../../../tests/phpunit';
} else { // Fallback.
	$_test_root = '/tmp/wordpress-tests-lib';
}

// Fail with an actionable message instead of a raw "failed to open stream" if none of the paths above found the WordPress test suite.
if ( ! file_exists( $_test_root . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		"WordPress test suite not found at {$_test_root}.\n" .
		"Start the environment with `npm run wp-env start`, or set WP_TESTS_DIR to a WordPress tests/phpunit directory.\n"
	);
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_test_root . '/includes/functions.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable

// Start up the WP testing environment.
require $_test_root . '/includes/bootstrap.php'; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
