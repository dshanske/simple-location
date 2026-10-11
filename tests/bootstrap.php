<?php
/**
 * PHPUnit bootstrap file
 *
 * Supports both the WordPress test library (bin/install-wp-tests.sh) and the
 * ClassicPress test library (bin/install-cp-tests.sh).
 *
 * @package Simple_Location
 */

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
$_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
if ( false !== $_phpunit_polyfills_path ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
}

// WP_TESTS_DIR takes precedence, then CP_TESTS_DIR, then the default install locations of each script.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = getenv( 'CP_TESTS_DIR' );
}

if ( ! $_tests_dir ) {
	$_tmp_dir = rtrim( sys_get_temp_dir(), '/\\' );
	foreach ( array( '/wordpress-tests-lib', '/classicpress-tests-lib' ) as $_candidate ) {
		if ( file_exists( $_tmp_dir . $_candidate . '/includes/functions.php' ) ) {
			$_tests_dir = $_tmp_dir . $_candidate;
			break;
		}
	}
}

if ( ! $_tests_dir || ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo 'Could not find the test library, have you run bin/install-wp-tests.sh or bin/install-cp-tests.sh ?' . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
	require dirname( __DIR__ ) . '/simple-location.php';
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';

// Fake providers, so tests never call live APIs.
require __DIR__ . '/includes/load-test-providers.php';

// Builds JPEGs with chosen EXIF data.
require __DIR__ . '/includes/class-sloc-test-exif.php';

// Report which platform is under test so CI logs show it.
if ( function_exists( 'classicpress_version' ) ) {
	$_platform = sprintf( 'ClassicPress %s', classicpress_version() );
} else {
	$_platform = sprintf( 'WordPress %s', get_bloginfo( 'version' ) );
}
printf( 'Testing Simple Location against %s on PHP %s' . PHP_EOL, $_platform, PHP_VERSION ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
