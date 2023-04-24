<?php
/**
 * Plugin Name: Traktivity
 * Plugin URI: https://wordpress.org/plugins/traktivity
 * Description: Log your activity on Trakt.tv
 * Author: Jeremy Herve
 * Version: 2.3.5
 * Author URI: https://jeremy.hu
 * License: GPL2+
 * Text Domain: traktivity
 * Domain Path: /languages/
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

define( 'TRAKTIVITY__VERSION',          '2.3.5' );
define( 'TRAKTIVITY__API_URL',          'https://api.trakt.tv' );
define( 'TRAKTIVITY__API_VERSION',      '2' );
define( 'TRAKTIVITY__TMDB_API_URL',     'https://api.themoviedb.org' );
define( 'TRAKTIVITY__TMDB_API_VERSION', '3' );
define( 'TRAKTIVITY__PLUGIN_DIR',       plugin_dir_path( __FILE__ ) );

/**
 * Create our main plugin class.
 */
class Traktivity {
	/**
	 * Instance.
	 *
	 * @var $instance
	 */
	private static $instance;

	/**
	 * Get things started.
	 */
	static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Traktivity;
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Load translations.
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		// Load plugin.
		add_action( 'plugins_loaded', array( $this, 'load_plugin' ) );
		// Flush rewrite rewrite_rules.
		add_action( 'add_option_traktivity_event', array( $this, 'flush_rules_on_enable' ) );
		add_action( 'update_option_traktivity_event', array( $this, 'flush_rules_on_enable' ) );
	}

	/**
	 * Load translations.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'traktivity', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Load plugin files.
	 *
	 * @since 1.0.0
	 */
	public function load_plugin() {
		// Load core functions.
		require_once( TRAKTIVITY__PLUGIN_DIR . 'core.traktivity.php' );
		require_once( TRAKTIVITY__PLUGIN_DIR . 'cpt.traktivity.php' );
		require_once( TRAKTIVITY__PLUGIN_DIR . 'rest.traktivity.php' );
		require_once( TRAKTIVITY__PLUGIN_DIR . 'content.traktivity.php' );
		require_once( TRAKTIVITY__PLUGIN_DIR . 'stats.traktivity.php' );

		// Settings panel.
		if ( is_admin() ) {
			require_once( TRAKTIVITY__PLUGIN_DIR . 'admin.traktivity.php' );
		}

		// Widgets.
		require_once( TRAKTIVITY__PLUGIN_DIR . 'widgets/list-events.php' );
	}

	/**
	 * Flush rewrite rules.
	 *
	 * @since 1.0.0
	 */
	public function flush_rules_on_enable() {
		flush_rewrite_rules();
	}
}
// And boom.
Traktivity::get_instance();
