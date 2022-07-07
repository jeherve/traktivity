<?php
/**
 * REST API endpoints.
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );


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
		 * Get existing credentials.
		 *
		 * @since 2.0.0
		 */
		register_rest_route( 'traktivity/v1', '/settings', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_settings' ),
			'permission_callback' => array( $this, 'permissions_check' ),
		) );

		/**
		 * Edit Settings.
		 *
		 * @since 2.0.0
		 */
		register_rest_route( 'traktivity/v1', '/settings/edit', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'post_settings' ),
			'permission_callback' => array( $this, 'permissions_check' ),
		) );

		/**
		 * Check the validity of our Trakt.tv credentials.
		 *
		 * @since 1.1.0
		 */
		register_rest_route( 'traktivity/v1', '/connection/(?P<user>[a-z0-9\-\.]+)/(?P<trakt>[a-zA-Z0-9-]+)', array(
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

		/**
		 * Check the validity of our TMDb credentials.
		 *
		 * @since 2.0.0
		 */
		register_rest_route( 'traktivity/v1', '/tmdb/(?P<tmdb>[a-zA-Z0-9-]+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'test_tmdb_api_connection' ),
			'permission_callback' => array( $this, 'permissions_check' ),
			'args'                => array(
				'tmdb' => array(
					'required'          => true,
					'validate_callback' => array( $this, 'validate_string' ),
				),
			),
		) );

		/**
		 * Check Sync status for Traktivity.
		 *
		 * @since 1.1.0
		 */
		register_rest_route( 'traktivity/v1', '/sync', array(
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => array( $this, 'trigger_sync' ),
			'permission_callback' => array( $this, 'permissions_check' ),
		) );

		/**
		 * Traktivity Stats Info.
		 *
		 * @since 2.2.0
		 */
		register_rest_route( 'traktivity/v1', '/stats', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => array( $this, 'get_stats' ),
			'permission_callback' => '__return_true',
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
				array(
					'status' => 404,
				)
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
			array(
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $data ) ) {
			$response = array(
				'message' => esc_html__( 'Trakt.tv is unavailable right now. Try again later.', 'traktivity' ),
				'code'    => (int) 500,
			);
			return new WP_REST_Response( $response, 500 );
		}

		$code = $data['response']['code'];

		/**
		 * Tweak our endpoint response message based on the response from Trakt.tv API.
		 *
		 * @see http://docs.trakt.apiary.io/#introduction/status-codes
		 */
		if ( 403 === $code ) {
			$message = __( 'Invalid API key or unapproved app.' , 'traktivity' );
		} elseif ( 429 === $code ) {
			$message = __( 'Rate Limit Exceeded with your Trakt.tv App.', 'traktivity' );
		} elseif ( 404 === $code ) {
			$message = __( 'This Trakt.tv username does not exist.', 'traktivity' );
		} elseif ( '2' === substr( $code, 0, 1 ) ) {
			$message = __( 'Your Trakt.tv API key is working.', 'traktivity' );
			// Let's overwrite the response code. If it's a success, we don't care what success response code, 200 is good enough.
			$code = 200;
		} elseif ( '5' === substr( $code, 0, 1 ) ) {
			$message = __( 'Trakt.tv is unavailable right now. Try again later.', 'traktivity' );
		} else {
			$message = __( 'Something is not working as it should. Please double check that both your username and your API keys are correct.
				If everything looks good, but you still see this message, please let me know, I\'ll see what I can do to help.
				Post in the WordPress.org support forums and give me as many details as possible about your setup.
				Thank you!', 'traktivity' );
		}

		$response = array(
			'message' => esc_html( $message ),
			'code'    => (int) $code,
		);
		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Check the status of our TMDb connection.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response $response Status of our TMDb connection. Response code matches the response from the API.
	 */
	public function test_tmdb_api_connection( $request ) {
		// Get parameter from request.
		if ( ! isset( $request['tmdb'] ) ) {
			return new WP_Error(
				'not_found',
				esc_html__( 'You did not specify your TMDb API key.', 'traktivity' ),
				array(
					'status' => 404,
				)
			);
		}

		/**
		 * Query the API using the API key provided in the API request.
		 * We'll query a random endpoint, discover/movie.
		 *
		 * @see https://developers.themoviedb.org/3/discover
		 */
		$query_url = sprintf(
			'%1$s/%2$s/%3$s?api_key=%4$s',
			TRAKTIVITY__TMDB_API_URL,
			TRAKTIVITY__TMDB_API_VERSION,
			'discover/movie',
			esc_attr( $request['tmdb'] )
		);
		$data = wp_remote_get( esc_url_raw( $query_url ) );

		$code = $data['response']['code'];

		/**
		 * Tweak our endpoint response message based on the response from TMDb API.
		 *
		 * @see https://www.themoviedb.org/documentation/api/status-codes
		 */
		if ( 429 === $code ) {
			$message = __( 'Rate Limit Exceeded with your TMDb App. Try again later, but give it some time!', 'traktivity' );
		} elseif ( '4' === substr( $code, 0, 1 ) ) {
			$message = __( 'Your TMDb API key does not exist, or is not valid.', 'traktivity' );
		} elseif ( '2' === substr( $code, 0, 1 ) ) {
			$message = __( 'Your TMDb API key is working.', 'traktivity' );
			// Let's overwrite the response code. If it's a success, we don't care what success response code, 200 is good enough.
			$code = 200;
		} elseif ( '5' === substr( $code, 0, 1 ) ) {
			$message = __( 'TMDb is unavailable right now. Try again later.', 'traktivity' );
		} else {
			$message = __( 'Something is not working as it should. Please double check that both your username and your API keys are correct.
				If everything looks good, but you still see this message, please let me know, I\'ll see what I can do to help.
				Post in the WordPress.org support forums and give me as many details as possible about your setup.
				Thank you!', 'traktivity' );
		}

		$response = array(
			'message' => esc_html( $message ),
			'code'    => (int) $code,
		);
		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Trigger a full synchronization of all past events.
	 *
	 * @since 1.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response $response Response from the Sync function.
	 */
	public function trigger_sync( $request ) {
		$options = (array) get_option( 'traktivity' );

		// Return an error if we have no API Keys to run an import.
		if ( ! isset( $options['username'], $options['api_key'] ) ) {
			return new WP_REST_Response(
				esc_html__( 'You did not specify your username or a Trakt.tv API key.', 'traktivity' ),
				200
			);
		}

		// If the 'total_runtime' argument was sent with the request, only recalculate total runtime for each series.
		if (
			isset( $request['type'] )
			&& 'total_runtime' === $request['type']
		) {
			if ( ! wp_next_scheduled( 'traktivity_total_runtime_sync' ) ) {
				wp_schedule_single_event( time(), 'traktivity_total_runtime_sync' );
			}

			return new WP_REST_Response(
				esc_html__( 'We are now recalcutating total runtime for each one of the shows you have watched. Give it a bit of time.', 'traktivity' ),
				200
			);
		}

		// Return an error if Synchronization is already complete. No need to run it again.
		if (
			isset( $options['full_sync'], $options['full_sync']['status'] )
			&& 'done' === $options['full_sync']['status']
		) {
			return new WP_REST_Response(
				esc_html__( 'Synchronization is complete.', 'traktivity' ),
				200
			);
		}

		// Return an error if Synchronization is currently in progress. Let's let it finish.
		if (
			isset( $options['full_sync'], $options['full_sync']['status'] )
			&& 'in_progress' === $options['full_sync']['status']
		) {
			// Relaunch full sync if it was running before but was stopped.
			if ( ! wp_next_scheduled( 'traktivity_full_sync' ) ) {
				wp_schedule_single_event( time(), 'traktivity_full_sync' );
			}

			// Return a response to let the user know about the sync progress so far.
			return new WP_REST_Response(
				esc_html__( 'Synchronization is in progress. Give it some time!', 'traktivity' ),
				200
			);
		}

		// No errors? Schedule a single event that will start in 2 seconds and trigger the full sync.
		if ( ! wp_next_scheduled( 'traktivity_full_sync' ) ) {
			wp_schedule_single_event( time(), 'traktivity_full_sync' );
		}

		return new WP_REST_Response(
			esc_html__( 'Synchronization has started. Give it a bit of time now. You can monitor progress in the All Trakt.tv Events menu.', 'traktivity' ),
			200
		);
	}

	/**
	 * Get existing settings in an object.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response $response Response from the Sync function.
	 */
	public function get_settings( $request ) {
		$options = (array) get_option( 'traktivity' );

		$settings = new stdClass();

		if (
			isset( $options['username'], $options['api_key'] )
			&& ( ! empty( $options['username'] ) && ! empty( $options['api_key'] ) )
		) {
			$settings->trakt->username = $options['username'];
			$settings->trakt->key = $options['api_key'];
		}

		if ( isset( $options['tmdb_api_key'] ) && ! empty( $options['tmdb_api_key'] ) ) {
			$settings->tmdb->key = $options['tmdb_api_key'];
		}

		if ( isset( $options['step'] ) && ! empty( $options['step'] ) ) {
			$settings->tmdb->step = $options['step'];
		}

		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * Edit settings.
	 *
	 * @since 2.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response $response Response from the Sync function.
	 */
	public function post_settings( $request ) {
		$options = (array) get_option( 'traktivity' );

		if ( isset( $request ) ) {
			if ( isset( $request['trakt']['username'] ) && ! empty( $request['trakt']['username'] ) ) {
				$options['username'] = esc_attr( $request['trakt']['username'] );
			}

			if ( isset( $request['trakt']['key'] ) && ! empty( $request['trakt']['key'] ) ) {
				$options['api_key'] = esc_attr( $request['trakt']['key'] );
			}

			if ( isset( $request['tmdb']['key'] ) && ! empty( $request['tmdb']['key'] ) ) {
				$options['tmdb_api_key'] = esc_attr( $request['tmdb']['key'] );
			}

			if ( isset( $request['step'] ) && ! empty( $request['step'] ) ) {
				$options['step'] = absint( $request['step'] );
			}

			update_option( 'traktivity', $options );
			return new WP_REST_Response( $request, 200 );
		}

		return new WP_Error(
			'cant-update',
			esc_html__( 'Could not update your settings.', 'traktivity' ),
			array(
				'status' => 500,
			)
		);
	}

	/**
	 * Get stats from the Stats option.
	 *
	 * @since 2.2.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response $response Response with all stats.
	 */
	public function get_stats( $request ) {
		$stats = get_option( 'traktivity_stats' );

		return new WP_REST_Response(
			$stats,
			200
		);
	}
} // End class.
new Traktivity_Api();
