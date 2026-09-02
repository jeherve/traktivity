<?php
/**
 * Bootstrap file for PHPStan.
 *
 * Defines the constants the plugin sets up at runtime, so static analysis
 * can resolve them without loading WordPress.
 *
 * @package Traktivity
 */

define( 'TRAKTIVITY__VERSION', '3.0.0' );
define( 'TRAKTIVITY__API_URL', 'https://api.trakt.tv' );
define( 'TRAKTIVITY__API_VERSION', '2' );
define( 'TRAKTIVITY__TMDB_API_URL', 'https://api.themoviedb.org' );
define( 'TRAKTIVITY__TMDB_API_VERSION', '3' );
define( 'TRAKTIVITY__PLUGIN_DIR', __DIR__ . '/../../' );
