<?php
/**
 * Admin Settings Page.
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

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
			array( 'settings' => sprintf( '<a href="%s">%s</a>', esc_url( get_admin_url( null, 'edit.php?post_type=traktivity_event&page=traktivity_settings' ) ), __( 'Settings', 'traktivity' ) ) ),
			array( 'support' => sprintf( '<a href="%s">%s</a>', 'https://wordpress.org/support/plugin/traktivity', __( 'Help', 'traktivity' ) ) ),
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
		'api_url' => esc_url_raw( rest_url() ),
		'api_nonce' => wp_create_nonce( 'wp_rest' ),
	);
	wp_localize_script( 'traktivity-settings', 'traktivity_settings', $traktivity_settings );

	wp_enqueue_script( 'traktivity-settings' );
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
		'traktivity'
	);
	add_settings_field(
		'username',
		__( 'Trakt.tv Username.', 'traktivity' ),
		'traktivity_app_settings_username_callback',
		'traktivity',
		'traktivity_app_settings'
	);
	add_settings_field(
		'api_key',
		__( 'Trakt.tv API Key', 'traktivity' ),
		'traktivity_app_settings_apikey_callback',
		'traktivity',
		'traktivity_app_settings'
	);
	add_settings_field(
		'tmdb_api_key',
		__( 'TMDB API Key', 'traktivity' ),
		'traktivity_app_settings_tmdb_api_key_callback',
		'traktivity',
		'traktivity_app_settings'
	);
}
add_action( 'admin_init', 'traktivity_options_init' );

/**
 * Trakt.tv App settings section.
 *
 * @since 1.0.0
 */
function traktivity_app_settings_callback() {
	echo '<p>';
	printf(
		__( 'To use the plugin, you will need to create an API application on Trakt.tv first. <a href="%1$s">click here</a> to create that app.', 'traktivity' ),
		esc_url( 'https://trakt.tv/oauth/applications/new' )
	);
	echo '<br/>';
	esc_html_e( 'In the Redirect uri field, you can enter your site URL. You can give it both checkin and scrobble permissions.', 'traktivity' );
	echo '<br/>';
	esc_html_e( 'Once you created your app, copy the "Client ID" value below. You will also want to enter your Trakt.tv username.', 'traktivity' );
	echo '<br/>';
	esc_html_e( 'To get images for each TV show, episode, and movie, we will also need to use another service, The Movie Database API.', 'traktivity' );
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
			<form id="traktivity_settings" method="post" action="options.php">
				<?php
					settings_fields( 'traktivity_settings' );
					/**
					 * Fires at the top of the Settings page.
					 *
					 * @since 1.0.0
					 */
					do_action( 'traktivity_start_settings' );
					do_settings_sections( 'traktivity' );
				?>
				<div id="api_test_results" class="notice" style="display:none;"></div>
				<?php
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
