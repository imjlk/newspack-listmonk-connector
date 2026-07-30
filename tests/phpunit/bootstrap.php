<?php
/**
 * PHPUnit bootstrap.
 *
 * @package Newspack_Listmonk_Connector
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = '/wordpress-phpunit';
}

require_once $_tests_dir . '/includes/functions.php';

$polyfills = dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';
if ( file_exists( $polyfills ) ) {
	require_once $polyfills;
}

tests_add_filter(
	'muplugins_loaded',
	static function () {
		require dirname( __DIR__, 2 ) . '/connector-for-newspack-newsletters-and-listmonk.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';
