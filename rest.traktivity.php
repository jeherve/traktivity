<?php
/**
 * REST API endpoints.
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


/**
 * Custom REST API endpoints.
 * We'll use it to check the status of the plugin, and return aggregated data.
 *
 * @since 1.1.0
 */
class Traktivity_Api {

	/**
	 * Constructor
	 */
	function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_endpoints' ) );
	}

	/**
	 * Register all endpoints.
	 *
	 * @since 1.1.0
	 */
	public function register_endpoints() {
		/**
		 * Check the validity of our Trakt.tv credentials.
		 *
		 * @since 1.1.0
		 */
		register_rest_route( 'traktivity/v1', '/connection/(?P<api>[a-z\-]+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'test_trakt_api_connection' ),
			'permission_callback' => array( $this, 'permissions_check' ),
			'args'                => array(
				'api' => array(
					'required'          => true,
					'validate_callback' => array( $this, 'validate_api_key' ),
				),
			),
		) );
	}

	/**
	 * Check permissions for each one of our requests.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return bool $permission Returns true if user is allowed to call the API.
	 */
	public function permissions_check( $request ) {
		return true;
		//return current_user_can( 'manage_options' );
	}

	/**
	 * Validate an API key.
	 *
	 * @since 1.1.0
	 *
	 * @param string          $param   Parameter that needs to be validated.
	 * @param WP_REST_Request $request Full details about the request.
	 * @param string          $key     key argument.
	 *
	 * @return bool $validated Is the API key in a valid format.
	 */
	public function validate_api_key( $param, $request, $key ) {
		return is_string( $param );
	}

	/**
	 * Check the status of our Trakt.tv connection.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response $response Status of our Trakt.tv connection. Response code matches the response from the API.
	 */
	public function test_trakt_api_connection( $request ) {
		// Get parameter from request.
		if ( isset( $data['api'] ) ) {
			$param = $request['api'];
		} else {
			return new WP_Error(
				'not_found',
				esc_html__( 'You did not specify a Trakt.tv API key.', 'traktivity' ),
				array( 'status' => 404 )
			);
		}

		/**
		 * Query the API using the API key provided in the API request.
		 */
		$headers = array(
			'Content-Type'      => 'application/json',
			'trakt-api-version' => TRAKTIVITY__API_VERSION,
			'trakt-api-key'     => $param,
		);
		$query_url = sprintf(
			'%1$s/movies/popular',
			TRAKTIVITY__API_URL
		);
		$data = wp_remote_get(
			esc_url_raw( $query_url ),
			array( 'headers' => $headers )
		);
		$response_code = $data['response']['code'];

		return new WP_REST_Response( (int) $response_code, 200 );
	}
} // End class.
new Traktivity_Api();
