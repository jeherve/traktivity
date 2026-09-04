<?php
/**
 * Plugin Name: Traktivity
 * Plugin URI: https://wordpress.org/plugins/traktivity
 * Description: Log your activity on Trakt.tv
 * Author: Jeremy Herve
 * Version: 3.1.0
 * Author URI: https://jeremy.hu
 * Requires at least: 7.0
 * Requires PHP: 7.4
 * License: GPL2+
 * Text Domain: traktivity
 * Domain Path: /languages/
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

define( 'TRAKTIVITY__VERSION', '3.1.0' );
define( 'TRAKTIVITY__API_URL', 'https://api.trakt.tv' );
define( 'TRAKTIVITY__API_VERSION', '2' );
define( 'TRAKTIVITY__TMDB_API_URL', 'https://api.themoviedb.org' );
define( 'TRAKTIVITY__TMDB_API_VERSION', '3' );
define( 'TRAKTIVITY__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Create our main plugin class.
 */
class Traktivity {
	/**
	 * Instance.
	 *
	 * @var Traktivity|null $instance
	 */
	private static $instance;

	/**
	 * Get things started.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = new Traktivity();
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
		// Run one-time routines after an update.
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ) );
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
	 * Run one-time routines when the plugin is updated.
	 *
	 * The version the site last ran is recorded alongside the other settings,
	 * so this can tell an update from a plain page load.
	 *
	 * @since 3.0.1
	 */
	public function maybe_upgrade() {
		$options = get_option( 'traktivity' );

		// Nothing configured yet, so there is nothing to bring forward.
		if ( ! is_array( $options ) ) {
			return;
		}

		$previous = isset( $options['version'] ) ? (string) $options['version'] : '0';

		if ( version_compare( $previous, TRAKTIVITY__VERSION, '>=' ) ) {
			return;
		}

		/*
		 * Before 3.0.1, a full synchronization asked Trakt.tv how many pages of
		 * history there were at one page size and then walked those pages at
		 * another, so it covered a tenth of the history and marked itself done.
		 * Every site that ran it is sitting on a partial import that the
		 * dashboard refuses to run again, so clear that status and let them.
		 *
		 * Events are keyed on their Trakt.tv ID and skipped if already present,
		 * so the run that follows only fills in what is missing.
		 *
		 * Only the history keys go. The same option also tracks the separate
		 * recalculation of each show's total runtime, under 'runtime', and that
		 * one worked: clearing it too would report finished work as never run.
		 */
		if ( version_compare( $previous, '3.0.1', '<' ) && isset( $options['full_sync'] ) ) {
			unset( $options['full_sync']['status'], $options['full_sync']['pages'] );
		}

		$options['version'] = TRAKTIVITY__VERSION;

		update_option( 'traktivity', $options );
	}

	/**
	 * Load plugin files.
	 *
	 * @since 1.0.0
	 */
	public function load_plugin() {
		// Load core functions.
		require_once TRAKTIVITY__PLUGIN_DIR . 'core.traktivity.php';
		require_once TRAKTIVITY__PLUGIN_DIR . 'cpt.traktivity.php';
		require_once TRAKTIVITY__PLUGIN_DIR . 'rest.traktivity.php';
		require_once TRAKTIVITY__PLUGIN_DIR . 'content.traktivity.php';
		require_once TRAKTIVITY__PLUGIN_DIR . 'stats.traktivity.php';
		require_once TRAKTIVITY__PLUGIN_DIR . 'helpers.traktivity.php';

		/*
		 * Wired ahead of the code that fills them, so the 3.1.0 blocks and
		 * templates work lands in a file that already exists rather than
		 * every branch editing this list and conflicting with the others.
		 */
		require_once TRAKTIVITY__PLUGIN_DIR . 'blocks.traktivity.php';
		require_once TRAKTIVITY__PLUGIN_DIR . 'templates.traktivity.php';

		// Settings panel.
		if ( is_admin() ) {
			require_once TRAKTIVITY__PLUGIN_DIR . 'admin.traktivity.php';
		}

		// Widgets.
		require_once TRAKTIVITY__PLUGIN_DIR . 'widgets/list-events.php';
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
