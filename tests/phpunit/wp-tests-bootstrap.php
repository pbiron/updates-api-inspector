<?php
/**
 * Updates API Inspector PHPUnit bootstrap file.
 *
 * @since 0.2.0
 *
 * @package updates-api-inspector
 */

// activate the following plugins during tests.
$GLOBALS['wp_tests_options'] = array(
	'active_plugins' => array(
		PLUGIN_TEST_NAME . '/plugin.php',
	),
);

// activate the following plugins during multisite tests.
$GLOBALS['wp_tests_site_options'] = array(
	'active_sitewide_plugins' => array(
		PLUGIN_TEST_NAME . '/plugin.php' => time(),
	),
);

require getenv( 'WP_PHPUNIT__DIR' ) . '/includes/bootstrap.php';

require 'testcase-' . PLUGIN_TEST_NAME . '.php';
