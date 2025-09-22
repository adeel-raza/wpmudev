<?php
// Load Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define path to WP tests dir if not already set.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/tmp/wordpress-tests-lib';
}

// Give WP access to the PHPUnit Polyfills.
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define(
		'WP_TESTS_PHPUNIT_POLYFILLS_PATH',
		dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills'
	);
}

// Load the WP test functions.
require_once $_tests_dir . '/includes/functions.php';

// Manually load your plugin for testing.
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/wpmudev-plugin-test.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Bootstrap WordPress test environment.
require $_tests_dir . '/includes/bootstrap.php';
