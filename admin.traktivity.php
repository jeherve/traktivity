<?php
/**
 * Admin Settings Page.
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Create Dashboard Page.
 */
function traktivity_dashboard_menu() {
	global $traktivity_dashboard_page;
	$traktivity_dashboard_page = add_submenu_page(
		'edit.php?post_type=traktivity_event',
		esc_html__( 'Trakt.tv Activity Dashboard', 'traktivity' ),
		esc_html__( 'Dashboard', 'traktivity' ),
		'manage_options',
		'traktivity_dashboard',
		'traktivity_do_dashboard'
	);
}
add_action( 'admin_menu', 'traktivity_dashboard_menu', 1 );

/**
 * Dashboard should be at the top.
 *
 * @since 2.0.0
 *
 * @param array $menu_ord Array of items in our Traktivity menu.
 */
function traktivity_submenu_order( $menu_ord ) {
	global $submenu;

	// Stop right here if we are looking at a Network Admin screen.
	if ( is_network_admin() ) {
		return $menu_ord;
	}

	// Stop if the user is not an admin.
	if ( ! current_user_can( 'manage_options' ) ) {
		return $menu_ord;
	}

	// Nothing to reorder if our own menu was never registered.
	if ( ! isset( $submenu['edit.php?post_type=traktivity_event'] ) ) {
		return $menu_ord;
	}

	// Get the original key of the dashboard submenu item.
	$index = null;
	foreach ( $submenu['edit.php?post_type=traktivity_event'] as $key => $details ) {
		if ( 'traktivity_dashboard' === $details[2] ) {
			$index = $key;
		}
	}

	if ( null === $index ) {
		return $menu_ord;
	}

	// Set the 'Dashboard' submenu as item with key '4'.
	// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordering the submenu is the purpose of this callback.
	$submenu['edit.php?post_type=traktivity_event'][4] = $submenu['edit.php?post_type=traktivity_event'][ $index ];

	// Remove the original dashboard submenu.
	unset( $submenu['edit.php?post_type=traktivity_event'][ $index ] );

	// Reorder the submenu so our new item, with key 4, is the first to appear.
	ksort( $submenu['edit.php?post_type=traktivity_event'] );

	return $menu_ord;
}
add_filter( 'custom_menu_order', 'traktivity_submenu_order' );

/**
 * Dashboard placeholder div.
 *
 * @since 2.0.0
 */
function traktivity_do_dashboard() {
	echo '<div id="main" class="wrap"></div>';
}

/**
 * Enqueue Dashboard scripts.
 *
 * @since 2.0.0
 *
 * @param int $hook Hook suffix for the current admin page.
 */
function traktivity_dashboard_scripts( $hook ) {
	global $traktivity_dashboard_page;

	// Only add our script to our Dashboard page.
	if ( $traktivity_dashboard_page !== $hook ) {
		return;
	}

	$asset_file = TRAKTIVITY__PLUGIN_DIR . 'build/index.asset.php';

	// The build has not been generated; there is nothing to render into.
	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = require $asset_file;

	wp_register_script(
		'traktivity-dashboard',
		plugins_url( 'build/index.js', __FILE__ ),
		$asset['dependencies'],
		$asset['version'],
		true
	);

	$options = (array) get_option( 'traktivity' );
	$summary = Traktivity_Stats::get_summary();

	/*
	 * Data only. Every user-facing string lives in the JavaScript, wrapped in
	 * @wordpress/i18n and translated through wp_set_script_translations() below.
	 */
	$dashboard_data = array(
		'step'             => isset( $options['step'] ) ? absint( $options['step'] ) : 1,
		'traktUsername'    => isset( $options['username'] ) ? (string) $options['username'] : '',
		'traktKey'         => isset( $options['api_key'] ) ? (string) $options['api_key'] : '',
		'tmdbKey'          => isset( $options['tmdb_api_key'] ) ? (string) $options['tmdb_api_key'] : '',
		'syncStatus'       => isset( $options['full_sync']['status'] ) ? (string) $options['full_sync']['status'] : '',
		'syncPages'        => isset( $options['full_sync']['pages'] ) ? intval( $options['full_sync']['pages'] ) : 0,
		'syncRuntime'      => isset( $options['full_sync']['runtime']['status'] ) ? (string) $options['full_sync']['runtime']['status'] : '',

		/*
		 * Formatted here rather than in JavaScript: turning a minute count
		 * into "3 days, 4 hours" needs _n() for each unit.
		 */
		'totalTimeWatched' => $summary['runtime'],

		/*
		 * Everything the display section needs. The stylesheet is here because
		 * a Site Editor link has to name a template by its theme-namespaced ID,
		 * and the block-theme flag because those links go nowhere useful
		 * otherwise.
		 */
		'templates'        => Traktivity_Templates::for_settings(),
		'isBlockTheme'     => wp_is_block_theme(),
		'themeStylesheet'  => get_stylesheet(),
		'siteEditorUrl'    => admin_url( 'site-editor.php' ),
		'hasEvents'        => $summary['entries'] > 0,
	);

	/*
	 * wp_add_inline_script() rather than wp_localize_script(), because
	 * localize runs every string through html_entity_decode(). That is right
	 * for translations and wrong for API keys, which are opaque tokens that
	 * should reach the browser exactly as stored.
	 */
	wp_add_inline_script(
		'traktivity-dashboard',
		'window.traktivityDashboard = ' . wp_json_encode( $dashboard_data ) . ';',
		'before'
	);

	// Let translators reach the strings in the bundle.
	wp_set_script_translations( 'traktivity-dashboard', 'traktivity' );

	/*
	 * The dashboard is built from @wordpress/components, so it needs the
	 * wp-components stylesheet underneath its own layout rules.
	 */
	wp_register_style(
		'traktivity-dashboard-styles',
		plugins_url( 'build/style-index.css', __FILE__ ),
		array( 'wp-components' ),
		$asset['version']
	);

	// wp-scripts emits style-index-rtl.css alongside the stylesheet on each
	// build; this is what makes WordPress serve it to right-to-left locales.
	wp_style_add_data( 'traktivity-dashboard-styles', 'rtl', 'replace' );

	wp_enqueue_script( 'traktivity-dashboard' );
	wp_enqueue_style( 'traktivity-dashboard-styles' );
}
add_action( 'admin_enqueue_scripts', 'traktivity_dashboard_scripts' );

/**
 * Add link to the Settings page to the plugin menu.
 *
 * @since 1.1.0
 *
 * @param array $links Array of links appearing in the Plugins menu for our plugin.
 */
function traktivity_plugin_settings_link( $links ) {
	if ( current_user_can( 'manage_options' ) ) {
		return array_merge(
			array(
				'settings' => sprintf(
					'<a href="%s">%s</a>',
					esc_url( get_admin_url( null, 'edit.php?post_type=traktivity_event&page=traktivity_dashboard' ) ),
					__( 'Settings', 'traktivity' )
				),
			),
			array(
				'support' => sprintf(
					'<a href="%s">%s</a>',
					'https://wordpress.org/support/plugin/traktivity',
					__( 'Help', 'traktivity' )
				),
			),
			$links
		);
	}

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( TRAKTIVITY__PLUGIN_DIR . 'traktivity.php' ), 'traktivity_plugin_settings_link' );
