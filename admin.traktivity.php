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
	$traktivity_settings_page = add_options_page(
		esc_html__( 'Trakt.tv', 'traktivity' ),
		esc_html__( 'Trakt.tv Activity Settings', 'traktivity' ),
		'manage_options',
		'traktivity',
		'traktivity_do_settings'
	);
}
add_action( 'admin_menu', 'traktivity_menu' );

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
		'<input type="text" name="traktivity[username]" value="%s" />',
		isset( $options['username'] ) ? esc_attr( $options['username'] ) : ''
	);
}

/**
 * Trakt.tv API Key callback.
 */
function traktivity_app_settings_apikey_callback() {
	$options = (array) get_option( 'traktivity' );
	printf(
		'<input type="text" name="traktivity[api_key]" value="%s" class="regular-text" />',
		isset( $options['api_key'] ) ? esc_attr( $options['api_key'] ) : ''
	);
}

/**
 * TMDB API Key callback.
 */
function traktivity_app_settings_tmdb_api_key_callback() {
	$options = (array) get_option( 'traktivity' );
	printf(
		'<input type="text" name="traktivity[tmdb_api_key]" value="%s" class="regular-text" />',
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
	?>
	<div id="traktivity_settings" class="wrap">
		<h1><?php esc_html_e( 'Trakt.tv Activity Settings', 'traktivity' ); ?></h1>
			<form method="post" action="options.php">
				<?php
					settings_fields( 'traktivity_settings' );
					/**
					 * Fires at the top of the Settings page.
					 *
					 * @since 1.0.0
					 */
					do_action( 'traktivity_start_settings' );
					do_settings_sections( 'traktivity' );
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
