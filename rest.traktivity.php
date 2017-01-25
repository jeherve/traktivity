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
		register_rest_route( 'traktivity/v1', '/connection/(?P<user>[a-z\-]+)/(?P<trakt>[a-zA-Z0-9-]+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'test_trakt_api_connection' ),
			'permission_callback' => array( $this, 'permissions_check' ),
			'args'                => array(
				'user'  => array(
					'required'          => true,
					'validate_callback' => array( $this, 'validate_string' ),
				),
				'trakt' => array(
					'required'          => true,
					'validate_callback' => array( $this, 'validate_string' ),
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
		//return true;
		return current_user_can( 'manage_options' );
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
	public function validate_string( $param, $request, $key ) {
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
		if ( isset( $request['user'], $request['trakt'] ) ) {
			$user  = $request['user'];
			$trakt = $request['trakt'];
		} else {
			return new WP_Error(
				'not_found',
				esc_html__( 'You did not specify your username or a Trakt.tv API key.', 'traktivity' ),
				array( 'status' => 404 )
			);
		}

		/**
		 * Query the API using the API key provided in the API request.
		 */
		$headers = array(
			'Content-Type'      => 'application/json',
			'trakt-api-version' => TRAKTIVITY__API_VERSION,
			'trakt-api-key'     => esc_html( $trakt ),
		);
		$query_url = sprintf(
			'%1$s/users/%2$s/history?limit=1',
			TRAKTIVITY__API_URL,
			esc_html( $user )
		);
		$data = wp_remote_get(
			esc_url_raw( $query_url ),
			array( 'headers' => $headers )
		);

		$code = $data['response']['code'];

		/**
		 * Tweak our endpoint response message based on the response from Trakt.tv API.
		 *
		 * @see http://docs.trakt.apiary.io/#introduction/status-codes
		 */
		if ( 403 === $code ) {
			$message = __( 'Invalid API key or unapproved app.' , 'traktivity' );
		} elseif ( 429 === $code ) {
			$message = __( 'Rate Limit Exceeded.', 'traktivity' );
		} elseif ( '2' === substr( $code, 0, 1 ) ) {
			$message = __( 'Your API key is working.', 'traktivity' );
			// Let's overwrite the response code. If it's a success, we don't care what success response code, 200 is good enough.
			$code = 200;
		} elseif ( '5' === substr( $code, 0, 1 ) ) {
			$message = __( 'Trakt.tv is unavailable right now. Try again later.', 'traktivity' );
		} else {
			$message = sprintf(
				__( 'Something is not working as it should. Please double check that both your username and your API keys are correct.
				If everything looks good, but you still see this message, please let me know, I\'ll see what I can do to help.
				<a href="%s">Send me an email</a> and give me as many details as possible about your setup.
				It would also help if you could let me know your Trakt.tv API key so I can run some tests.
				Thank you!', 'traktivity' ),
				'https://jeremy.hu/contact/'
			);
		}

		$response = array(
			'message' => $message,
			'code'    => (int) $code,
		);
		return new WP_REST_Response( $response, 200 );
	}
} // End class.
new Traktivity_Api();
