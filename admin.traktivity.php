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

	// Get the original key of the dashboard submenu item.
	foreach ( $submenu['edit.php?post_type=traktivity_event'] as $key => $details ) {
		if ( 'traktivity_dashboard' == $details[2] ) {
			$index = $key;
		}
	}

	// Set the 'Dashboard' submenu as item with key '4'.
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
	if ( $traktivity_dashboard_page != $hook ) {
		return;
	}

	$version = defined( 'WP_DEBUG' ) && true === WP_DEBUG ? time() : TRAKTIVITY__VERSION;
	wp_register_script( 'traktivity-dashboard', plugins_url( '_build/admin.js' , __FILE__ ), array(), $version, true );

	$options = (array) get_option( 'traktivity' );
	$stats = (array) get_option( 'traktivity_stats' );
	$traktivity_dash_args = array(
		'api_url'                => esc_url_raw( rest_url() ),
		'site_url'               => esc_url_raw( home_url() ),
		'api_nonce'              => wp_create_nonce( 'wp_rest' ),
		'dash_url'               => esc_url( get_admin_url( null, 'edit.php?post_type=traktivity_event&page=traktivity_dashboard' ) ),
		'trakt_username'         => isset( $options['username'] ) ? esc_attr( $options['username'] ) : '',
		'trakt_key'              => isset( $options['api_key'] ) ? esc_attr( $options['api_key'] ) : '',
		'tmdb_key'               => isset( $options['tmdb_api_key'] ) ? esc_attr( $options['tmdb_api_key'] ) : '',
		'traktivity_step'        => isset( $options['step'] ) ? absint( $options['step'] ) : 1,
		'sync_status'            => isset( $options['full_sync'], $options['full_sync']['status'] ) ? esc_attr( $options['full_sync']['status'] ) : '',
		'sync_pages'             => isset( $options['full_sync'], $options['full_sync']['pages'] ) ? intval( $options['full_sync']['pages'] ) : null,
		'sync_runtime'           => isset( $options['full_sync'], $options['full_sync']['runtime']['status'] ) ? esc_attr( $options['full_sync']['runtime']['status'] ) : '',
		'total_time_watched'     => isset( $stats['total_time_watched'] ) ? Traktivity_Stats::convert_time( $stats['total_time_watched'] ) : '',
		'title'                  => esc_html__( 'Traktivity Dashboard', 'traktivity' ),
		'tagline'                => esc_html__( 'Log your activity in front of the screen.', 'traktivity' ),
		'intro'                  => esc_html__( "Do you like to go to the movies and would like to remember what movies you saw, and when? Traktivity is for you! Are you a TV addict, and want to keep track of all the shows you've binge-watched? Traktivity is for you!", 'traktivity' ),
		'description'            => esc_html__( "This plugin relies on 2 external services to gather information about the things you watch: Trakt.tv is where you'll be marking shows or movies as watched, and The Movie DB is where the plugin will go grab images for each one of those shows or movies.", 'traktivity' ),
		'nav_dash'               => esc_html__( 'Dashboard', 'traktivity' ),
		'nav_params'             => esc_html__( 'Settings', 'traktivity' ),
		'nav_faq'                => esc_html__( 'FAQ', 'traktivity' ),
		'form_trakt_title'       => esc_html__( 'Trakt.tv Settings', 'traktivity' ),
		'form_trakt_username'    => esc_html__( 'Trakt.tv Username', 'traktivity' ),
		'form_trakt_key'         => esc_html__( 'Trakt.tv API Key', 'traktivity' ),
		'form_trakt_intro'       => esc_html__( 'To use the plugin, you will need to create an API application on Trakt.tv first.', 'traktivity' ),
		'form_trakt_create_app'  => esc_html__( 'Click here to create that app.', 'traktivity' ),
		'form_trakt_api_url'     => esc_url( 'https://trakt.tv/oauth/applications/new' ),
		'form_trakt_api_options' => esc_html__( 'In the Redirect uri field, you can enter your site URL. You can give it both checkin and scrobble permissions.', 'traktivity' ),
		'form_trakt_api_fields'  => esc_html__( 'Once you created your app, copy the "Client ID" value below. You will also want to enter your Trakt.tv username.', 'traktivity' ),
		'form_tmdb_title'        => esc_html__( 'The Movie Database Settings', 'traktivity' ),
		'form_tmdb_key'          => esc_html__( 'TMDB API Key', 'traktivity' ),
		'form_tmdb_intro'        => esc_html__( 'To get images for each TV show, episode, and movie, we will also need to use another service, The Movie DB API.', 'traktivity' ),
		'form_tmdb_intro_opt'    => esc_html__( 'This is optional. If you do not want to store and display images about the things you watch on your site, you can ignore this.', 'traktivity' ),
		'form_tmdb_api_url'      => esc_url( 'https://www.themoviedb.org/login' ),
		'form_tmdb_create_app'   => esc_html__( 'To register for an API key, sign up and/or login to your account page on TMDb and click the "API" link in the left hand sidebar. Once your application is approved, copy the contents of the "API Key (v3 auth)" field, and paste it below.', 'traktivity' ),
		'notice_saved'           => esc_html__( 'Changes have been saved.', 'traktivity' ),
		'notice_error'           => esc_html__( 'Changes could not be saved.', 'traktivity' ),
		'intro_next'             => esc_html__( 'Let\'s get started!', 'traktivity' ),
		'button_next'            => esc_html__( 'Next', 'traktivity' ),
		'button_skip'            => esc_html__( 'Skip', 'traktivity' ),
		'sync_title'             => esc_html__( 'You did it! Traktivity will now start logging all the movies and TV shows you watch.', 'traktivity' ),
		'sync_description'       => esc_html__( "One more thing: by default, Traktivity only gathers data about the last 10 things you've watched, and then automatically logs all future things you'll watch. Thanks to the button below, you can launch a full synchronization of all the things you've ever watched. It can take a while, though!", 'traktivity' ),
		'launch_sync'            => esc_html__( 'Start synchronization', 'traktivity' ),
		'recent_list_title'      => esc_html__( 'Recently Watched', 'traktivity' ),
		'dashboard_intro_q'      => esc_html__( 'I am all set! What now?', 'traktivity' ),
		'dashboard_intro_a'      => esc_html__( 'Now that you have added an API from each service, Traktivity will start monitoring your Trakt.tv account. Every hour, it will check your profile to see if you have watched something new. If you have, it will be added to your WordPress site. You will see a new entry under "Trakt.tv Events" in this menu, with tons of details about what you have watched.', 'traktivity' ),
		'dashboard_sup_trakt_q'  => esc_html__( 'Can I support Trakt.tv? That service is awesome!', 'traktivity' ),
		'dashboard_sup_trakt_a'  => esc_html__( 'It is! If you\'d like to support the Trakt.tv service, you can sign up for a VIP account at trakt.tv/vip. By doing so you will get rid of the ads and unlock lots of VIP features!', 'traktivity' ),
		'dash_faq_who'           => esc_html__( 'Who is behind this great plugin?', 'traktivity' ),
		'trakt_dash_credits'     => esc_html__( 'Traktivity is not endorsed or certified by TMDb or Trakt.tv. It is just a little plugin developed by a TV addict, just like you. :)', 'traktivity' ),
		'sync_runtime_title'     => esc_html__( 'Recalculate total runtime for each one of the series you have watched.', 'traktivity' ),
		'sync_runtime_desc'      => esc_html__( 'If you used the Traktivity plugin before version 2.1.0 was released, it did not track the amount of time you had spent watching each series. This form allows you to recalculate runtime for all your series at once.', 'traktivity' ),
		'stats_overview_title'   => esc_html__( 'In a nutshell', 'traktivity' ),
		'tt_watched_desc'        => sprintf(
			/* Translators: %1$s is a unit of time, in years, days, hours, or minutes. Always plural. */
			esc_html__( 'You have already spent %1$s watching movies and TV series. Congrats!', 'traktivity' ),
			isset( $stats['total_time_watched'] ) ? Traktivity_Stats::convert_time( $stats['total_time_watched'] ) : esc_html__( 'quite some time', 'traktivity' )
		),
	);
	wp_localize_script( 'traktivity-dashboard', 'traktivity_dash', $traktivity_dash_args );

	wp_register_style( 'traktivity-dashboard-styles', plugins_url( 'admin/css/dashboard.css', __FILE__ ), array(), $version );

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
