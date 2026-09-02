<?php
/**
 * WordPress test suite configuration.
 *
 * Only ever loaded inside the wp-env tests container, where these values are
 * fixed. The test suite drops and recreates its tables on every run, so it is
 * pointed at a database of its own rather than the one behind the site on 8889.
 *
 * @package Traktivity
 */

define( 'DB_NAME', 'traktivity_phpunit' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'password' );
define( 'DB_HOST', 'mysql' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

define( 'ABSPATH', '/var/www/html/' );
define( 'WP_DEFAULT_THEME', 'default' );

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Traktivity Tests' );
define( 'WP_PHP_BINARY', 'php' );

$table_prefix = 'wptests_';

define( 'AUTH_KEY', 'traktivity-tests' );
define( 'SECURE_AUTH_KEY', 'traktivity-tests' );
define( 'LOGGED_IN_KEY', 'traktivity-tests' );
define( 'NONCE_KEY', 'traktivity-tests' );
define( 'AUTH_SALT', 'traktivity-tests' );
define( 'SECURE_AUTH_SALT', 'traktivity-tests' );
define( 'LOGGED_IN_SALT', 'traktivity-tests' );
define( 'NONCE_SALT', 'traktivity-tests' );
