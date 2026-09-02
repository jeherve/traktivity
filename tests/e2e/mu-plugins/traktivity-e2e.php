<?php
/**
 * Test scaffolding for the end-to-end suite.
 *
 * Loaded only inside the wp-env container, by way of the mappings entry in
 * .wp-env.json. It is never part of a release.
 *
 * It does two things. It answers outbound requests to Trakt.tv and TMDb
 * locally, so the wizard can be driven without real API keys and without
 * depending on two third-party services being up. And it exposes a reset
 * route, so each spec can start from a clean install.
 *
 * @package Traktivity
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

const TRAKTIVITY_E2E_VALID_TRAKT_KEY = 'e2e-valid-trakt-key';
const TRAKTIVITY_E2E_VALID_TMDB_KEY  = 'e2e-valid-tmdb-key';

/**
 * Answer Trakt.tv and TMDb requests without leaving the container.
 *
 * @param false|array $preempt Whether to short-circuit the request.
 * @param array       $args    Request arguments.
 * @param string      $url     Request URL.
 *
 * @return false|array A canned response, or false to let the request proceed.
 */
function traktivity_e2e_mock_http( $preempt, $args, $url ) {
	if ( false !== strpos( $url, 'api.trakt.tv' ) ) {
		$key   = isset( $args['headers']['trakt-api-key'] ) ? $args['headers']['trakt-api-key'] : '';
		$valid = TRAKTIVITY_E2E_VALID_TRAKT_KEY === $key;

		return array(
			'headers'  => array( 'X-Pagination-Page-Count' => '1' ),
			'body'     => $valid ? wp_json_encode( array() ) : wp_json_encode( array( 'error' => 'invalid' ) ),
			'response' => array(
				'code'    => $valid ? 200 : 403,
				'message' => $valid ? 'OK' : 'Forbidden',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	if ( false !== strpos( $url, 'api.themoviedb.org' ) ) {
		$query = array();
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$valid = isset( $query['api_key'] ) && TRAKTIVITY_E2E_VALID_TMDB_KEY === $query['api_key'];

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'results' => array() ) ),
			'response' => array(
				'code'    => $valid ? 200 : 401,
				'message' => $valid ? 'OK' : 'Unauthorized',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	return $preempt;
}
add_filter( 'pre_http_request', 'traktivity_e2e_mock_http', 10, 3 );

/**
 * Register the reset route the specs call between tests.
 *
 * @return void
 */
function traktivity_e2e_register_routes() {
	register_rest_route(
		'traktivity-e2e/v1',
		'/options',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'traktivity_e2e_options',
			'permission_callback' => static function () {
				return current_user_can( 'manage_options' );
			},
		)
	);

	register_rest_route(
		'traktivity-e2e/v1',
		'/sync-state',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'traktivity_e2e_sync_state',
			'permission_callback' => static function () {
				return current_user_can( 'manage_options' );
			},
		)
	);

	register_rest_route(
		'traktivity-e2e/v1',
		'/reset',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'traktivity_e2e_reset',
			'permission_callback' => static function () {
				return current_user_can( 'manage_options' );
			},
		)
	);
}
add_action( 'rest_api_init', 'traktivity_e2e_register_routes' );

/**
 * Return the plugin's stored options, so specs can assert on what was saved.
 *
 * @return WP_REST_Response The traktivity option.
 */
function traktivity_e2e_options() {
	return new WP_REST_Response( (array) get_option( 'traktivity' ), 200 );
}

/**
 * Report whether a full sync has been asked for.
 *
 * Triggering a sync schedules a cron event; the full_sync option is written
 * later, by the callback that event runs. A test checking one or the other
 * races cron, so both are reported and either one answers the question.
 *
 * @return WP_REST_Response Whether the event is scheduled, and the option.
 */
function traktivity_e2e_sync_state() {
	$options = (array) get_option( 'traktivity' );

	return new WP_REST_Response(
		array(
			'scheduled' => (bool) wp_next_scheduled( 'traktivity_full_sync' ),
			'option'    => isset( $options['full_sync'] ) ? $options['full_sync'] : null,
		),
		200
	);
}

/**
 * Put the plugin back to a freshly installed state.
 *
 * @return WP_REST_Response Confirmation that the options were cleared.
 */
function traktivity_e2e_reset() {
	delete_option( 'traktivity' );
	delete_option( 'traktivity_stats' );

	return new WP_REST_Response( array( 'reset' => true ), 200 );
}
