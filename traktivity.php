<?php
/**
 * Plugin Name: Traktivity
 * Plugin URI: https://wordpress.org/plugins/traktivity
 * Description: Log your activity on Trakt.tv
 * Author: Jeremy Herve
 * Version: 1.0.0
 * Author URI: https://jeremy.hu
 * License: GPL2+
 * Text Domain: traktivity
 * Domain Path: /languages/
 *
 * @package Traktivity
 */

/**
 * Create our main plugin class.
 */
class Traktivity {
	private static $instance;

	static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Traktivity;
		}

		return self::$instance;
	}

	private function __construct() {

	}
}
// And boom.
Traktivity::get_instance();
