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
	$traktivity_dash_args = array(
		'api_url'                => esc_url_raw( rest_url() ),
		'site_url'               => esc_url_raw( home_url() ),
		'api_nonce'              => wp_create_nonce( 'wp_rest' ),
		'trakt_username'         => isset( $options['username'] ) ? esc_attr( $options['username'] ) : '',
		'trakt_key'              => isset( $options['api_key'] ) ? esc_attr( $options['api_key'] ) : '',
		'tmdb_key'               => isset( $options['tmdb_api_key'] ) ? esc_attr( $options['tmdb_api_key'] ) : '',
		'traktivity_step'        => isset( $options['step'] ) ? absint( $options['step'] ) : 1,
		'sync_status'            => isset( $options['full_sync'], $options['full_sync']['status'] ) ? esc_attr( $options['full_sync']['status'] ) : '',
		'sync_pages'             => isset( $options['full_sync'], $options['full_sync']['pages'] ) ? intval( $options['full_sync']['pages'] ) : null,
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
		'recent_list_title'      => esc_html__( 'Recent Events', 'traktivity' ),
	);
	wp_localize_script( 'traktivity-dashboard', 'traktivity_dash', $traktivity_dash_args );

	wp_register_style( 'traktivity-dashboard-styles', plugins_url( 'admin/css/dashboard.css', __FILE__ ), array(), $version );

	wp_enqueue_script( 'traktivity-dashboard' );
	wp_enqueue_style( 'traktivity-dashboard-styles' );
}
add_action( 'admin_enqueue_scripts', 'traktivity_dashboard_scripts' );

/**
 * Create Menu page.
 *
 * @since 1.0.0
 */
function traktivity_menu() {
	global $traktivity_settings_page;
	$traktivity_settings_page = add_submenu_page(
		'edit.php?post_type=traktivity_event',
		esc_html__( 'Trakt.tv Activity Settings', 'traktivity' ),
		esc_html__( 'Settings', 'traktivity' ),
		'manage_options',
		'traktivity_settings',
		'traktivity_do_settings'
	);
}
add_action( 'admin_menu', 'traktivity_menu' );

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
					esc_url( get_admin_url( null, 'edit.php?post_type=traktivity_event&page=traktivity_settings' ) ),
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

/**
 * Enqueue scripts on Traktivity admin page.
 *
 * @since 1.1.0
 *
 * @param int $hook Hook suffix for the current admin page.
 */
function traktivity_enqueue_admin_scripts( $hook ) {

	global $traktivity_settings_page;

	// Only add our script to our admin page.
	if ( $traktivity_settings_page != $hook ) {
		return;
	}

	wp_register_script( 'traktivity-settings', plugins_url( 'js/admin-settings.js' , __FILE__ ), array( 'jquery' ), TRAKTIVITY__VERSION );
	$traktivity_settings = array(
		'api_url'          => esc_url_raw( rest_url() ),
		'api_nonce'        => wp_create_nonce( 'wp_rest' ),
		'empty_message'    => esc_html__( 'Please fill in the Trakt.tv Username and Trakt.tv API Key fields first.', 'traktivity' ),
		'progress_message' => esc_html__( 'In Progress', 'traktivity' ),
	);
	wp_localize_script( 'traktivity-settings', 'traktivity_settings', $traktivity_settings );

	wp_register_style( 'traktivity-admin', plugins_url( 'css/admin-settings.css', __FILE__ ), array(), TRAKTIVITY__VERSION );

	wp_enqueue_script( 'traktivity-settings' );
	wp_enqueue_style( 'traktivity-admin' );
}
add_action( 'admin_enqueue_scripts', 'traktivity_enqueue_admin_scripts' );

/**
 * Create new set of options.
 *
 * @since 1.0.0
 */
function traktivity_options_init() {
	register_setting( 'traktivity_settings', 'traktivity', 'traktivity_settings_validate' );

	// Main Trakt.tv App Settings Section.
	add_settings_section(
		'traktivity_app_settings',
		__( 'Trakt.tv Settings', 'traktivity' ),
		'traktivity_app_settings_callback',
		'traktivity_settings'
	);
	add_settings_field(
		'username',
		__( 'Trakt.tv Username', 'traktivity' ),
		'traktivity_app_settings_username_callback',
		'traktivity_settings',
		'traktivity_app_settings'
	);
	add_settings_field(
		'api_key',
		__( 'Trakt.tv API Key', 'traktivity' ),
		'traktivity_app_settings_apikey_callback',
		'traktivity_settings',
		'traktivity_app_settings'
	);
	add_settings_section(
		'traktivity_tmdb_settings',
		__( 'The Movie Database Settings', 'traktivity' ),
		'traktivity_tmdb_settings_callback',
		'traktivity_settings'
	);
	add_settings_field(
		'tmdb_api_key',
		__( 'TMDB API Key', 'traktivity' ),
		'traktivity_app_settings_tmdb_api_key_callback',
		'traktivity_settings',
		'traktivity_tmdb_settings'
	);
	add_settings_section(
		'traktivity_sync_settings',
		__( 'Full Sync', 'traktivity' ),
		'traktivity_sync_settings_callback',
		'traktivity_settings'
	);
	add_settings_field(
		'full_sync',
		__( 'Sync status', 'traktivity' ),
		'traktivity_sync_settings_full_sync_callback',
		'traktivity_settings',
		'traktivity_sync_settings'
	);
}
add_action( 'admin_init', 'traktivity_options_init' );

/**
 * Trakt.tv App settings section.
 *
 * @since 1.0.0
 */
function traktivity_app_settings_callback() {
	/**
	 * Display a connection test button when the username and API key fields are not empty.
	 *
	 * @since 1.1.0
	 */
	printf(
		'<div id="api_test_results">
			<button type="button" id="submit_connection_test" class="button button-large">%1$s</button>
			<p id="test_message" style="display:none;"></p>
		</div>',
		esc_html__( 'Test your connection to the Trakt.tv API.', 'traktivity' )
	);
	echo '<p>';
	printf(
		__( 'To use the plugin, you will need to create an API application on Trakt.tv first. <a href="%1$s">Click here</a> to create that app.', 'traktivity' ),
		esc_url( 'https://trakt.tv/oauth/applications/new' )
	);
	echo '<br/>';
	esc_html_e( 'In the Redirect uri field, you can enter your site URL. You can give it both checkin and scrobble permissions.', 'traktivity' );
	echo '<br/>';
	esc_html_e( 'Once you created your app, copy the "Client ID" value below. You will also want to enter your Trakt.tv username.', 'traktivity' );
	echo '</p>';
}

/**
 * The Movie Database Settings Section.
 *
 * @since 1.1.0
 */
function traktivity_tmdb_settings_callback() {
	echo '<p>';
	printf(
		__( 'To get images for each TV show, episode, and movie, we will also need to use another service, <a href="%s">The Movie DB API</a>.', 'traktivity'),
		esc_url( 'https://www.themoviedb.org/' )
	);
	echo '</p><p>';
	esc_html_e( 'This is optional. If you do not want to store and display images about the things you watch on your site, you can ignore this.', 'traktivity' );
	echo '</p><p>';
	printf(
		__( 'To register for an API key, <a href="%s">sign up and/or login to your account page on TMDb</a> and click the "API" link in the left hand sidebar. Once your application is approved, copy the contents of the "API Key (v3 auth)" field, and paste it below.', 'traktivity' ),
		esc_url( 'https://www.themoviedb.org/login' )
	);
	echo '</p>';
}

/**
 * Full Sync Settings Section.
 *
 * @since 1.1.0
 */
function traktivity_sync_settings_callback() {
	echo '<p>';
	esc_html_e( "By default, Traktivity only gathers data about the last 10 things you've watched, and then automatically logs all future things you'll watch. This section will allow you to perform a full synchronization of all the things you've ever watched.", 'traktivity' );
	echo '</p>';
}

/**
 * Trakt.tv App Settings option callbacks.
 *
 * @since 1.0.0
 */

/**
 * Trakt.tv Username option.
 */
function traktivity_app_settings_username_callback() {
	$options = (array) get_option( 'traktivity' );
	printf(
		'<input id="username" type="text" name="traktivity[username]" value="%s" />',
		isset( $options['username'] ) ? esc_attr( $options['username'] ) : ''
	);
}

/**
 * Trakt.tv API Key callback.
 */
function traktivity_app_settings_apikey_callback() {
	$options = (array) get_option( 'traktivity' );
	printf(
		'<input id="trakt_api_key" type="text" name="traktivity[api_key]" value="%s" class="regular-text" />',
		isset( $options['api_key'] ) ? esc_attr( $options['api_key'] ) : ''
	);
}

/**
 * TMDB API Key callback.
 */
function traktivity_app_settings_tmdb_api_key_callback() {
	$options = (array) get_option( 'traktivity' );
	printf(
		'<input id="tmdb_api_key" type="text" name="traktivity[tmdb_api_key]" value="%s" class="regular-text" />',
		isset( $options['tmdb_api_key'] ) ? esc_attr( $options['tmdb_api_key'] ) : ''
	);
}

/**
 * Full Sync callback.
 */
function traktivity_sync_settings_full_sync_callback() {
	$options = (array) get_option( 'traktivity' );
	if ( isset( $options['full_sync'], $options['full_sync']['status'] ) ) {
		if ( 'done' === $options['full_sync']['status'] ) {
			printf(
				__( 'All events have already been synchronized. Check them <a href="%s">here</a>.', 'traktivity' ),
				esc_url( get_admin_url( null, 'edit.php?post_type=traktivity_event' ) )
			);
		} else {
			printf(
				__( 'Synchronization in progress. There are still %d pages to process.', 'traktivity' ),
				absint( $options['full_sync']['pages'] )
			);
		}
	} else {
		// we push to start the sync here.
		printf(
			'<input id="full_sync" type="button" name="traktivity[full_sync]" value="%s" class="button button-secondary" />',
			esc_html__( 'Start synchronization', 'traktivity' )
		);
		// Hidden paragraph where we will add the Sync status via JS, depending on the response from the sync endpoint.
		echo '<p id="full_sync_details" style="display:none;"></p>';
	}
}

/**
 * Sanitize and validate input.
 *
 * @since 1.0.0
 *
 * @param  array $input Saved options.
 * @return array $input Sanitized options.
 */
function traktivity_settings_validate( $input ) {
	$input['username']      = sanitize_text_field( $input['username'] );
	$input['api_key']       = sanitize_key( $input['api_key'] );
	$input['tmdb_api_key']  = sanitize_key( $input['tmdb_api_key'] );

	return $input;
}

/**
 * Settings Screen.
 *
 * @since 1.0.0
 */
function traktivity_do_settings() {
	// Is the user allowed to see this page.
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div id="traktivity_settings" class="wrap">
		<h1><?php esc_html_e( 'Trakt.tv Activity', 'traktivity' ); ?></h1>
			<?php
				printf(
					'
					<div class="tagline"><span>%1$s</span></div>
					<p><strong>%2$s</strong></p>
					<p>%3$s</p>
					',
					esc_html__( 'Log your activity in front of the screen.', 'traktivity' ),
					esc_html__( "Do you like to go to the movies and would like to remember what movies you saw, and when? Traktivity is for you! Are you a TV addict, and want to keep track of all the shows you've binge-watched? Traktivity is for you!", 'traktivity' ),
					esc_html__( "This plugin relies on 2 external services to gather information about the things you watch: Trakt.tv is where you'll be marking shows or movies as watched, and The Movie DB is where the plugin will go grab images for each one of those shows or movies.", 'traktivity' )
				);
			?>
			<form id="traktivity_settings" method="post" action="options.php">
				<?php
					/**
					 * Fires at the top of the Settings page.
					 *
					 * @since 1.0.0
					 */
					do_action( 'traktivity_start_settings' );

					settings_fields( 'traktivity_settings' );
					do_settings_sections( 'traktivity_settings' );
					submit_button();

					/**
					 * Fires at the bottom of the Settings page.
					 *
					 * @since 1.0.0
					 */
					do_action( 'traktivity_end_settings' );
				?>
			</form>
			<?php
				printf(
					'<h2>%s</h2>',
					esc_html__( 'FAQ', 'traktivity' )
				);

				printf(
					'<p><strong>%s</strong></p>',
					esc_html__( 'I am all set! What now?', 'traktivity' )
				);
				printf(
					'<p>%s</p>',
					esc_html__( 'Now that you have added an API from each service, Traktivity will start monitoring your Trakt.tv account. Every hour, it will check your profile to see if you have watched something new. If you have, it will be added to your WordPress site. You will see a new entry under "Trakt.tv Events" in this menu, with tons of details about what you have watched.', 'traktivity' )
				);

				printf(
					'<p><strong>%s</strong></p>',
					esc_html__( 'Can I support Trakt.tv? That service is awesome!', 'traktivity' )
				);
				printf(
					__( '<p>It is! If you\'d like to support the Trakt.tv service, you can sign up for a VIP account <a href="%s">here</a>. By doing so you will get rid of the ads and unlock lots of VIP features!</p>', 'traktivity' ),
					esc_url( 'https://trakt.tv/vip' )
				);

				printf(
					'<h2>%s</h2>',
					esc_html__( 'Credits', 'traktivity' )
				);
				printf(
					__( 'Traktivity is not endorsed or certified by TMDb or Trakt.tv. It is just a little plugin developed by <a href="%s">a TV addict</a>, just like you. :)', 'traktivity' ),
					esc_url( 'https://jeremy.hu' )
				);
			?>
		<?php
		/**
		 * Fires at the bottom of the Settings page, after the form.
		 *
		 * @since 1.0.0
		 */
		do_action( 'traktivity_after_settings' );
		?>
	</div><!-- .wrap -->
	<?php
}
