<?php
$_tests_dir = getenv('WP_TESTS_DIR') ?: '/tmp/wordpress-tests-lib';
if (!file_exists($_tests_dir . '/includes/functions.php')) {
    echo "WordPress test environment not found. Set WP_TESTS_DIR.\n";
    exit(1);
}
require_once $_tests_dir . '/includes/functions.php';
tests_add_filter('muplugins_loaded', function () {
    require dirname(__DIR__) . '/wordpress/wpforge/wpforge.php';
});
require $_tests_dir . '/includes/bootstrap.php';
