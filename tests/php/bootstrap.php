<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads the WordPress test suite shipped by wp-phpunit, then loads the plugin
 * into it, so the tests run against a real WordPress rather than mocks.
 *
 * @package Traktivity
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$traktivity_tests_dir = getenv( 'WP_PHPUNIT__DIR' );

if ( ! $traktivity_tests_dir ) {
	$traktivity_tests_dir = dirname( __DIR__, 2 ) . '/vendor/wp-phpunit/wp-phpunit';
}

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );

require_once $traktivity_tests_dir . '/includes/functions.php';

/**
 * Load the plugin before WordPress finishes booting.
 */
tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/traktivity.php';
	}
);

require $traktivity_tests_dir . '/includes/bootstrap.php';
