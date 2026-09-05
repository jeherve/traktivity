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
	public function __construct() {
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
		register_rest_route(
			'traktivity/v1',
			'/settings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		/**
		 * Edit Settings.
		 *
		 * @since 2.0.0
		 */
		register_rest_route(
			'traktivity/v1',
			'/settings/edit',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'post_settings' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		/**
		 * Check the validity of our Trakt.tv credentials.
		 *
		 * @since 1.1.0
		 */
		register_rest_route(
			'traktivity/v1',
			'/connection',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'test_trakt_api_connection' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		/**
		 * Check the validity of our TMDb credentials.
		 *
		 * @since 2.0.0
		 */
		register_rest_route(
			'traktivity/v1',
			'/tmdb',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'test_tmdb_api_connection' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		/**
		 * Check Sync status for Traktivity.
		 *
		 * @since 1.1.0
		 */
		register_rest_route(
			'traktivity/v1',
			'/sync',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'trigger_sync' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		/**
		 * Read which block templates and parts are switched on.
		 *
		 * @since 3.1.0
		 */
		register_rest_route(
			'traktivity/v1',
			'/templates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_templates' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);

		/**
		 * Switch block templates and parts on or off.
		 *
		 * @since 3.1.0
		 */
		register_rest_route(
			'traktivity/v1',
			'/templates',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'post_templates' ),
				'permission_callback' => array( $this, 'permissions_check' ),
				'args'                => array(
					'enabled' => array(
						'type'        => 'object',
						'required'    => true,
						'description' => __( 'Template slugs mapped to whether they are switched on.', 'traktivity' ),
					),
				),
			)
		);

		/**
		 * Traktivity Stats Info.
		 *
		 * Behind the same capability as the rest of the namespace. This route
		 * hands back the traktivity_stats option as it stands, and it was
		 * public only because '__return_true' was the quickest way to silence
		 * the notice WordPress 5.5 started printing for a route with no
		 * permission callback.
		 *
		 * @since 2.2.0
		 */
		register_rest_route(
			'traktivity/v1',
			'/stats',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_stats' ),
				'permission_callback' => array( $this, 'permissions_check' ),
			)
		);
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
	 * Is this value usable as a credential?
	 *
	 * Trakt.tv and TMDb decide what their tokens look like, and the plugin has
	 * no say in it. Rather than guess an alphabet and reject the next format
	 * they introduce, this only turns away what cannot be a token at all: an
	 * empty value, or one carrying whitespace or control characters.
	 *
	 * @since 3.0.0
	 *
	 * @param mixed $value Value to check.
	 *
	 * @return bool Whether the value can be used as a credential.
	 */
	private function is_valid_credential( $value ) {
		return (
			is_string( $value )
			&& '' !== $value
			&& ! preg_match( '/[\s\x00-\x1F\x7F]/', $value )
		);
	}

	/**
	 * Check the status of our Trakt.tv connection.
	 *
	 * @since 1.0.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response|WP_Error $response Status of our Trakt.tv connection. Response code matches the response from the API.
	 */
	public function test_trakt_api_connection( $request ) {
		$options = (array) get_option( 'traktivity' );
		$user    = isset( $options['username'] ) ? $options['username'] : '';
		$trakt   = isset( $options['api_key'] ) ? $options['api_key'] : '';

		if ( empty( $user ) || empty( $trakt ) ) {
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
		$headers   = array(
			'Content-Type'      => 'application/json',
			'trakt-api-version' => TRAKTIVITY__API_VERSION,
			'trakt-api-key'     => $trakt,
		);
		$query_url = sprintf(
			'%1$s/users/%2$s/history?limit=1',
			TRAKTIVITY__API_URL,
			rawurlencode( $user )
		);
		$data      = wp_remote_get(
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
			$message = __( 'Invalid API key or unapproved app.', 'traktivity' );
		} elseif ( 429 === $code ) {
			$message = __( 'Rate Limit Exceeded with your Trakt.tv App.', 'traktivity' );
		} elseif ( 404 === $code ) {
			$message = __( 'This Trakt.tv username does not exist.', 'traktivity' );
		} elseif ( $code >= 200 && $code < 300 ) {
			$message = __( 'Your Trakt.tv API key is working.', 'traktivity' );
			// Let's overwrite the response code. If it's a success, we don't care what success response code, 200 is good enough.
			$code = 200;
		} elseif ( $code >= 500 && $code < 600 ) {
			$message = __( 'Trakt.tv is unavailable right now. Try again later.', 'traktivity' );
		} else {
			$message = __(
				'Something is not working as it should. Please double check that both your username and your API keys are correct.
				If everything looks good, but you still see this message, please let me know, I\'ll see what I can do to help.
				Post in the WordPress.org support forums and give me as many details as possible about your setup.
				Thank you!',
				'traktivity'
			);
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
	 * @return WP_REST_Response|WP_Error $response Status of our TMDb connection. Response code matches the response from the API.
	 */
	public function test_tmdb_api_connection( $request ) {
		$options = (array) get_option( 'traktivity' );
		$tmdb    = isset( $options['tmdb_api_key'] ) ? $options['tmdb_api_key'] : '';

		if ( empty( $tmdb ) ) {
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
			rawurlencode( $tmdb )
		);
		$data      = wp_remote_get( esc_url_raw( $query_url ) );

		if ( is_wp_error( $data ) ) {
			$response = array(
				'message' => esc_html__( 'TMDb is unavailable right now. Try again later.', 'traktivity' ),
				'code'    => 500,
			);
			return new WP_REST_Response( $response, 500 );
		}

		$code = wp_remote_retrieve_response_code( $data );

		/**
		 * Tweak our endpoint response message based on the response from TMDb API.
		 *
		 * @see https://www.themoviedb.org/documentation/api/status-codes
		 */
		if ( 429 === $code ) {
			$message = __( 'Rate Limit Exceeded with your TMDb App. Try again later, but give it some time!', 'traktivity' );
		} elseif ( $code >= 400 && $code < 500 ) {
			$message = __( 'Your TMDb API key does not exist, or is not valid.', 'traktivity' );
		} elseif ( $code >= 200 && $code < 300 ) {
			$message = __( 'Your TMDb API key is working.', 'traktivity' );
			// Let's overwrite the response code. If it's a success, we don't care what success response code, 200 is good enough.
			$code = 200;
		} elseif ( $code >= 500 && $code < 600 ) {
			$message = __( 'TMDb is unavailable right now. Try again later.', 'traktivity' );
		} else {
			$message = __(
				'Something is not working as it should. Please double check that both your username and your API keys are correct.
				If everything looks good, but you still see this message, please let me know, I\'ll see what I can do to help.
				Post in the WordPress.org support forums and give me as many details as possible about your setup.
				Thank you!',
				'traktivity'
			);
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

		/*
		 * Someone asking for a synchronization is a fresh start, so clear the
		 * count of requests that failed in a row. A sync that gave up because
		 * Trakt.tv kept refusing it would otherwise carry that tally into this
		 * attempt and stop again on the first hiccup.
		 */
		if ( isset( $options['full_sync']['failures'] ) ) {
			unset( $options['full_sync']['failures'] );
			update_option( 'traktivity', $options );
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
			$settings->trakt->key      = $options['api_key'];
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
	 * @return WP_REST_Response|WP_Error $response Response from the Sync function.
	 */
	public function post_settings( $request ) {
		$options = (array) get_option( 'traktivity' );

		$submitted = array(
			'username'     => isset( $request['trakt']['username'] ) ? $request['trakt']['username'] : null,
			'api_key'      => isset( $request['trakt']['key'] ) ? $request['trakt']['key'] : null,
			'tmdb_api_key' => isset( $request['tmdb']['key'] ) ? $request['tmdb']['key'] : null,
		);

		foreach ( $submitted as $option_name => $value ) {
			// Not supplied on this request; leave whatever is already stored.
			if ( null === $value || '' === $value ) {
				continue;
			}

			if ( ! $this->is_valid_credential( $value ) ) {
				return new WP_Error(
					'invalid-credential',
					esc_html__( 'That does not look like a usable username or API key. Check for stray spaces, and that you copied the whole value.', 'traktivity' ),
					array(
						'status' => 400,
						'param'  => $option_name,
					)
				);
			}

			/*
			 * Stored exactly as supplied. These are opaque tokens belonging to
			 * Trakt.tv and TMDb, and escaping one changes it: esc_attr() turned
			 * an ampersand into &amp;, so the key sent to the API was not the
			 * key the user pasted in.
			 */
			$options[ $option_name ] = $value;
		}

		if ( ! empty( $request['step'] ) ) {
			$options['step'] = absint( $request['step'] );
		}

		update_option( 'traktivity', $options );

		return new WP_REST_Response( $request, 200 );
	}

	/**
	 * Read which block templates and parts are switched on.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response $response What the plugin can provide, and its state.
	 */
	public function get_templates( $request ) {
		unset( $request );

		return new WP_REST_Response(
			array(
				'templates'       => Traktivity_Templates::for_settings(),
				'isBlockTheme'    => wp_is_block_theme(),
				'themeStylesheet' => get_stylesheet(),
			),
			200
		);
	}

	/**
	 * Switch block templates and parts on or off.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 *
	 * @return WP_REST_Response $response The stored state.
	 */
	public function post_templates( $request ) {
		$enabled = $request->get_param( 'enabled' );

		if ( ! is_array( $enabled ) ) {
			return new WP_REST_Response(
				array(
					'code'    => 400,
					'message' => esc_html__( 'The list of templates was not readable.', 'traktivity' ),
				),
				400
			);
		}

		/*
		 * Unknown slugs are dropped rather than stored, so a stale key from an
		 * older version cannot accumulate in the option.
		 */
		Traktivity_Templates::save_enabled( $enabled );

		return new WP_REST_Response(
			array( 'templates' => Traktivity_Templates::for_settings() ),
			200
		);
	}

	/**
	 * Get Traktivity stats.
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
}
new Traktivity_Api();
