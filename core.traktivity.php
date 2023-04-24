<?php
/**
 * Core functions.
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );


/**
 * API Calls to get our data, and then store it in our custom post type and taxonomies.
 * The core of the plugin's work happens here.
 *
 * @since 1.0.0
 */
class Traktivity_Calls {

	/**
	 * Constructor
	 */
	function __construct() {
		// Check for new events and publish them every hour.
		add_action( 'traktivity_publish', array( $this, 'publish_event' ) );
		if ( ! wp_next_scheduled( 'traktivity_publish' ) ) {
			wp_schedule_event( time(), 'hourly', 'traktivity_publish' );
		}

		// Trigger a single event to launch the full sync loop.
		add_action( 'traktivity_full_sync', array( $this, 'full_sync' ) );

		// Trigger a single event to launch the full sync loop.
		add_action( 'traktivity_total_runtime_sync', array( $this, 'total_runtime_sync' ) );
	}

	/**
	 * Get option saved in the plugin's settings screen.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Option name.
	 *
	 * @return string $str Specific option.
	 */
	private function get_option( $name ) {
		$options = get_option( 'traktivity' );

		if ( isset( $options[ $name ] ) ) {
			return $options[ $name ];
		} else {
			return '';
		}
	}

	/**
	 * Save option in our array of 'traktivity' options.
	 *
	 * @since 1.1.0
	 *
	 * @param string       $name  Option name.
	 * @param string|array $value Option value.
	 */
	private function update_option( $name, $value ) {
		$options = get_option( 'traktivity' );

		if ( isset( $value ) && ! empty( $value ) ) {
			$options[ $name ] = $value;
			update_option( 'traktivity', $options );
		}
	}

	/**
	 * Remote call to get data from Trakt's API.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 * 	Optional. Array of possible query args for the call to Trakt.tv API.
	 *
	 *	@int int page  Number of page of results to be returned. Accepts integers.
	 *	@int int limit Number of results to return per page. Accepts integers.
	 * }
	 * @param bool  $test Optional. Pass true to only make a test request, and return the number of event pages.
	 *
	 * @return null|array|int
	 */
	private function get_trakt_activity( $args = array(), $test = false ) {

		// Start with an empty response.
		$response_body = array();

		// Get the username.
		$username = $this->get_option( 'username' );

		/**
		 * Query the API for that username, using our API key.
		 */
		$headers = array(
			'Content-Type'      => 'application/json',
			'trakt-api-version' => TRAKTIVITY__API_VERSION,
			'trakt-api-key'     => $this->get_option( 'api_key' ),
		);
		$query_url = sprintf(
			'%1$s/users/%2$s/history?extended=full',
			TRAKTIVITY__API_URL,
			$username
		);

		/**
		 * Filter the URL used to fetch activity data from Trakt.
		 *
		 * @since 2.3.0
		 *
		 * @param string $query_url Query URL.
		 * @param string $api_url   API URL. Default to TRAKTIVITY__API_URL.
		 * @param string $username  Trakt username.
		 */
		$query_url = apply_filters( 'traktivity_get_activity_url', $query_url, TRAKTIVITY__API_URL, $username );

		/**
		 * If one specified an array of $args when calling get_trakt_activity(),
		 * those arguments will be added to the query.
		 * Possible args could be `page` or `limit`.
		 */
		if ( isset( $args ) && is_array( $args ) && ! empty( $args ) ) {
			$query_url = add_query_arg(
				$args,
				$query_url
			);
		}

		$data = wp_remote_get(
			esc_url_raw( $query_url ),
			array(
				'headers' => $headers,
			)
		);

		if (
			is_wp_error( $data )
			|| 200 != $data['response']['code']
			|| empty( $data['body'] )
		) {
			return;
		}

		if ( isset( $test ) && true === $test ) {
			return (int) wp_remote_retrieve_header( $data, 'X-Pagination-Page-Count' );
		}

		$response_body = json_decode( $data['body'] );

		return $response_body;
	}

	/**
	 * Get Movie / Show / Episode poster from themoviedb.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $type        Item type. Accepts movie, show, episode.
	 * @param string   $id          Movie / Show / Episode TMDB ID.
	 * @param int|bool $season_num  Season number if we're talking about a TV episode. False if it doesn't apply.
	 * @param int|bool $episode_num Episode number if we're talking about a TV episode. False if it doesn't apply.
	 *
	 * @return null|array $image {
	 * 	Array of images details.
	 * 		@string string url   Image  URL.
	 * 		@int    int    width Image  Width.
	 * 		@int    int    height Image Height.
	 * }
	 */
	private function get_item_poster( $type, $id, $season_num, $episode_num ) {

		// Build route.
		if ( 'movie' === $type ) {
			$endpoint = sprintf(
				'movie/%s/images',
				esc_html( $id )
			);
		} elseif ( 'show' === $type ) {
			$endpoint = sprintf(
				'tv/%s/images',
				esc_html( $id )
			);
		} elseif ( 'episode' === $type ) {
			$endpoint = sprintf(
				'tv/%1$s/season/%2$s/episode/%3$s/images',
				esc_html( $id ),
				(int) $season_num,
				(int) $episode_num
			);
		} else {
			return;
		}

		$query_url = sprintf(
			'%1$s/%2$s/%3$s?api_key=%4$s',
			TRAKTIVITY__TMDB_API_URL,
			TRAKTIVITY__TMDB_API_VERSION,
			$endpoint,
			$this->get_option( 'tmdb_api_key' )
		);

		$data = wp_remote_get( esc_url_raw( $query_url ) );
		if (
			is_wp_error( $data )
			|| 200 != $data['response']['code']
			|| empty( $data['body'] )
		) {
			return;
		}

		$resp = json_decode( $data['body'] );

		if ( ! isset( $resp ) || ! is_object( $resp ) ) {
			return;
		}

		// We will pick the first backdrop and move from there.
		if ( ! empty( $resp->backdrops ) ) {
			$img_details = $resp->backdrops[0];
		} elseif ( ! empty( $resp->posters ) ) { // If there are no backdrops, we'll pick the first poster.
			$img_details = $resp->posters[0];
		} elseif ( ! empty( $resp->stills ) ) { // If there are no posters either, we'll pick from the stills.
			$img_details = $resp->stills[0];
		} else {
			return;
		}

		// Let's start with an empty $image array we'll fill in with some image details.
		$image = array();

		// Build the image URL.
		$image['url'] = sprintf(
			'https://image.tmdb.org/t/p/original%s',
			$img_details->file_path
		);

		// Add image width.
		$image['width'] = (int) $img_details->width;

		// Add image height.
		$image['height'] = (int) $img_details->height;

		return $image;
	}

	/**
	 * Upload, attach, and set an image as Featured Image for a post.
	 * Used to take the poster image for each show / episode / movie, and add it to a post.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url      Image URL.
	 * @param string $post_id  Post ID.
	 * @param string $title    Post Title.
	 * @param bool   $featured Should the image be a featured image.
	 *
	 * @return array $post_image {
	 * 	Array containing information about the post image.
	 *
	 * 		@int    int    id  Attachment ID for this image.
	 * 		@string string tag Image HTML tag of a large version of the image.
	 * }
	 */
	private function sideload_image( $url, $post_id, $title, $featured ) {
		// Start with an empty array.
		$post_image = array();

		/**
		 * Load necessary libs for media_sideload_image() to work.
		 */
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_read_image_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		/**
		 * Create our local image, based on the remote image URL.
		 * The image is then attached to our post ID.
		 * We use the post title as the image description.
		 */
		$local_img = media_sideload_image( $url, $post_id, $title, 'src' );

		// Was the upload successful? Let's update the post.
		if ( is_string( $local_img ) ) {
			/**
			 * Retrieve all images attached to the post.
			 */
			$images = get_attached_media( 'image', $post_id );
			if ( ! empty( $images ) ) {
				/**
				 * From here we have 3 case scenarios:
				 * 1. Movies: only one image was uploaded and is attached to that post: the movie poster.
				 * 2. Episode in an existing series: only one image was uploaded and is attached to that post: the episode screenshot (still).
				 * 3. Episode of a new series:
				 *  	First time this sideload_image runs, there will only be one image attached to the post: the episode screenshot.
				 *  	2nd time it runs, the show poster will be added as well. We want the function to return that second image then.
				 */

				/**
				 * If featured is set to true, we know we're in scenario 1 or 2.
				 * Let's set the image as featured image and set it as the one that will be returned.
				 */
				if ( true === $featured ) {
					// Let's only keep the first image. There should be only one, but just in case.
					$first_image = array_shift( $images );

					// Set the featured image.
					set_post_thumbnail( $post_id, $first_image->ID );

					// Set the attachment ID we'll add to the returned post image array.
					$post_image_id = $first_image->ID;
				} else {
					/**
					 * Handle scenario 3.
					 * We want the second attachment in that post.
					 * We don't want to make it a featured image.
					 */
					// Shift the first element off our array of attachments.
					array_shift( $images );

					// Do we have a post object left, meaning our upload above worked? Let's take that post's ID.
					if ( isset( $images[0] ) ) {
						$post_image_id = $images[0]->ID;
					} else {
						return $post_image;
					}
				}

				/**
				 * Finally, let's populate the $post_image we'll return.
				 */
				// Store the attachment ID.
				$post_image['id'] = (int) $post_image_id;

				/**
				 * If you use the Jetpack plugin and its Photon module,
				 * the image strings returned will use the Photon URL.
				 * We don't want that.
				 * The Photon URL will be added later on on the front end.
				 *
				 * Old versions of Jetpack used the Jetpack_Photon class to do this.
				 * New versions use the Image_CDN class.
				 * Let's handle both.
				 */
				if ( class_exists( '\Automattic\Jetpack\Image_CDN\Image_CDN' ) ) {
					remove_filter( 'image_downsize', array( \Automattic\Jetpack\Image_CDN\Image_CDN::instance(), 'filter_image_downsize' ) );
				} elseif ( class_exists( 'Jetpack_Photon' ) ) {
					remove_filter( 'image_downsize', array( Jetpack_Photon::instance(), 'filter_image_downsize' ) );
				}

				// Create a div containing a large version of the image, to be added to the post if needed.
				$post_image['tag'] = sprintf(
					'<div class="poster-image">%s</div>',
					wp_get_attachment_image( $post_image_id, 'large' )
				);

				// Re-enable Photon now that the image URL has been built.
				if ( class_exists( '\Automattic\Jetpack\Image_CDN\Image_CDN' ) ) {
					add_filter( 'image_downsize', array( \Automattic\Jetpack\Image_CDN\Image_CDN::instance(), 'filter_image_downsize' ), 10, 3 );
				} elseif ( class_exists( 'Jetpack_Photon' ) ) {
					add_filter( 'image_downsize', array( Jetpack_Photon::instance(), 'filter_image_downsize' ), 10, 3 );
				}
			} // End if().
		} // End if().

		return $post_image;
	}

	/**
	 * Publish Trakt.tv Event.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args {
	 * 	Optional. Array of possible query args for the call to Trakt.tv API.
	 *
	 *	@int int page  Number of page of results to be returned. Accepts integers.
	 *	@int int limit Number of results to return per page. Accepts integers.
	 * }
	 */
	public function publish_event( $args = array() ) {
		// Avoid timeouts during the data import process.
		set_time_limit( 0 );

		$trakt_events = $this->get_trakt_activity( $args, false );

		/**
		 * Only go through the event list if we have valid event array.
		 */
		if ( isset( $trakt_events ) && is_array( $trakt_events ) ) {
			foreach ( $trakt_events as $event ) {
				// Avoid errors when no type is attached to the event.
				if ( ! isset( $event->type ) ) {
					continue;
				}

				// Check if that event was already recorded in a previous run.
				$event_exists_args = array(
					'post_type'  => 'traktivity_event',
					'meta_query' => array(
						array(
							'key'     => 'trakt_event_id',
							'value'   => intval( $event->id ),
							'compare' => 'EXISTS',
						),
					),
				);
				$does_event_exist = new WP_Query( $event_exists_args );

				// If a post already exists with that ID, we can skip it.
				if ( $does_event_exist->have_posts() ) {
					continue;
				}

				/**
				 * Store the event ID returned by Trakt.tv as post meta.
				 *
				 * We'll keep adding to that $meta array to form an array of
				 * all the meta data to add to each post.
				 */
				$meta = array(
					'trakt_event_id' => intval( $event->id ),
				);

				/**
				 * Gather data about what we're watching.
				 * We'll collect that info in different taxonomies.
				 */
				$taxonomies = array();

				// Let's start with movies.
				if ( 'movie' === $event->type ) {

					$taxonomies['trakt_type']  = esc_html__( 'Movie', 'traktivity' );
					// Let's capitalize genres.
					$taxonomies['trakt_genre'] = array_map( 'ucwords', $event->movie->genres );
					$taxonomies['trakt_year']  = esc_html( $event->movie->year );

					$meta['trakt_movie_id']    = intval( $event->movie->ids->trakt );
					$meta['imdb_movie_id']     = esc_html( $event->movie->ids->imdb );
					$meta['tmdb_movie_id']     = esc_html( $event->movie->ids->tmdb );
					$meta['trakt_runtime']     = intval( $event->movie->runtime );

					$post_excerpt              = $event->movie->tagline;
					$post_content              = $event->movie->overview;

				} elseif ( 'episode' === $event->type ) { // Then let's gather info about series.

					$taxonomies['trakt_type']    = esc_html__( 'TV Series', 'traktivity' );
					// Let's capitalize genres.
					$taxonomies['trakt_genre']   = array_map( 'ucwords', $event->show->genres );
					$taxonomies['trakt_year']    = esc_html( $event->show->year );
					$taxonomies['trakt_show']    = esc_html( $event->show->title );
					$taxonomies['trakt_season']  = esc_html( $event->episode->season );
					$taxonomies['trakt_episode'] = esc_html( $event->episode->number );

					$meta['trakt_episode_id']    = intval( $event->episode->ids->trakt );
					$meta['trakt_show_id']       = intval( $event->show->ids->trakt );
					$meta['imdb_episode_id']     = esc_html( $event->episode->ids->imdb );
					$meta['imdb_show_id']        = esc_html( $event->show->ids->imdb );
					$meta['tmdb_episode_id']     = esc_html( $event->episode->ids->tmdb );
					$meta['tmdb_show_id']        = esc_html( $event->show->ids->tmdb );
					$meta['trakt_runtime']       = intval( $event->show->runtime );

					$post_excerpt                = $event->episode->overview;
					$post_content                = $event->episode->overview;

				} else { // If it's neither a movie nor a tv show, we don't need to log it.
					continue;
				} // End if().

				// Grab the event title.
				if ( 'movie' === $event->type ) {
					$title = $event->movie->title;
				} elseif ( 'episode' === $event->type ) {
					$title = $event->episode->title;
				} else {
					continue;
				}

				/**
				 * Filter the Events' Post Title.
				 *
				 * @since 1.0.0
				 *
				 * @param string $title Event title. By default it's the movie title, or the episode title followed by the show title.
				 * @param array  $event Array of details about the event.
				 */
				$title = apply_filters( 'traktivity_event_title', $title, $event );

				/**
				 * Get the first registered admin.
				 */
				$first_admin = get_users(
					array(
						'role'    => 'administrator',
						'orderby' => 'user_registered',
						'order'   => 'ASC',
						'fields'  => 'ID',
						'number'  => 1,
					)
				);
				/**
				 * Allow third-parties to provide their own author to be used.
				 *
				 * @since 2.3.1
				 *
				 * @param array $first_admin Array of user IDs, e.g. [ "1" ].
				 */
				$first_admin = apply_filters( 'traktivity_event_author', $first_admin );

				// Let it all come together as a list of things we'll add to the post we're creating.
				$event_args = array(
					'post_title'   => esc_html( $title ),
					'post_type'    => 'traktivity_event', // to-do: add a filter here and in cpt declaration for folks wanting to publish regular posts instead.
					'post_status'  => 'publish',
					'post_date'    => $event->watched_at,
					'tax_input'    => $taxonomies,
					'meta_input'   => $meta,
					'post_content' => esc_html( $post_content ),
					'post_excerpt' => esc_html( $post_excerpt ),
					'post_author'  => ( ! empty( $first_admin ) ? (int) $first_admin[0] : 0 )
				);

				// Create our post.
				$post_id = wp_insert_post( $event_args );

				/**
				 * Add event's runtime to stats.
				 */
				$stats = get_option( 'traktivity_stats' );

				// Increment our total watched counter, but only if we have a running tally.
				if ( ! empty( $stats['total_time_watched'] ) ) {
					$stats['total_time_watched'] += $meta['trakt_runtime'];
					update_option( 'traktivity_stats', $stats );
				} else {
					// No running tally? Run our function to create it once.
					Traktivity_Stats::total_time_watched();
				}

				/**
				 * Grab the event image, add it to the post content.
				 * We can only do this if the user specified a TMDB API Key.
				 */
				$tmdb_api_key = $this->get_option( 'tmdb_api_key' );
				if ( ! empty( $tmdb_api_key ) ) {
					if ( 'episode' === $event->type ) {
						$tmdb_id = $meta['tmdb_show_id'];
						$season_num = $taxonomies['trakt_season'];
						$episode_num = $taxonomies['trakt_episode'];
					} else {
						$tmdb_id = $meta['tmdb_movie_id'];
						$season_num = 0;
						$episode_num = 0;
					}
					$image = $this->get_item_poster( $event->type, $tmdb_id, $season_num, $episode_num );

					if ( is_array( $image ) && ! empty( $image ) ) {
						if ( 'episode' === $event->type ) {
							$image_title = $title . ' -- ' . $taxonomies['trakt_show'];
						} else {
							$image_title = $title;
						}
						$post_image = $this->sideload_image( $image['url'], $post_id, $image_title, true );

						if ( ! empty( $post_image ) ) {
							$post_with_image = array(
								'ID'           => $post_id,
								'post_content' => $post_image['tag'] . $post_content,
							);
							wp_update_post( $post_with_image );
						}
					}
				}

				/**
				 * Establish the relationship between terms and taxonomies.
				 */
				foreach ( $taxonomies as $taxonomy => $value ) {
					$term_taxonomy_ids = wp_set_object_terms( $post_id, $value, $taxonomy, true );
					/**
					 * Since wp_set_object_terms returned an array of term_taxonomy_ids after running,
					 * we can use it to add more info to each term.
					 * From Term taxonomy IDs, we'll get term IDs.
					 * Then from there, we'l update the term and add a description if needed.
					 */
					if ( is_array( $term_taxonomy_ids ) && ! empty( $term_taxonomy_ids ) ) {
						foreach ( $term_taxonomy_ids as $term_taxonomy_id ) {
							$term_id_object = get_term_by( 'term_taxonomy_id', $term_taxonomy_id, 'trakt_show', ARRAY_A );
							/**
							 * Let's increment our total runtime counter for that show.
							 */
							if (
								is_array( $term_id_object )
								&& 'trakt_show' === $term_id_object['taxonomy']
								&& ! empty( $meta['trakt_runtime'] )
							) {
								$term_id = (int) $term_id_object['term_id'];

								$runtime = get_term_meta( $term_id, 'show_runtime', true );
								// If we don't have data yet, run a sync for all existing episodes of that show.
								if ( empty( $runtime ) ) {
									$runtime = $this->series_total_runtime_sync( $term_id );
								} elseif ( is_numeric( $runtime ) ) {
									$runtime = $runtime + $meta['trakt_runtime'];
								}

								update_term_meta( $term_id, 'show_runtime', $runtime );
							}

							/**
							 * Let's search for show taxonomies with empty descriptions.
							 * This means these shows weren't existing before. We just created them.
							 * We will consequently give them a description, a show poster, and attach a list of IDs that can be used to retrieve more data later.
							 */
							if (
								is_array( $term_id_object )
								&& 'trakt_show' === $term_id_object['taxonomy']
								&& empty( $term_id_object['description'] )
							) {
								$term_id = (int) $term_id_object['term_id'];

								/**
								 * If we added a new show, we'll add its description here.
								 */
								$show_args = array(
									'description' => esc_html( $event->show->overview ),
								);
								wp_update_term( $term_id, 'trakt_show', $show_args );

								/**
								 * Get a poster image for that new show.
								 * We can only do this if the user specified a TMDB API Key.
								 */
								if ( ! empty( $tmdb_api_key ) ) {
									$show_image = $this->get_item_poster( 'show', $meta['tmdb_show_id'], false, false );
									if ( is_array( $show_image ) && ! empty( $show_image ) ) {
										$local_image = $this->sideload_image( $show_image['url'], $post_id, $taxonomies['trakt_show'], false );

										update_term_meta( $term_id, 'show_poster', $local_image );
									}
								}

								/**
								 * Add an array containing the IDs of the show on Trakt, IMDb, and TMDB.
								 */
								$show_ids = array(
									'trakt' => $meta['trakt_show_id'],
									'imdb'  => $meta['imdb_show_id'],
									'tmdb'  => $meta['tmdb_show_id'],
								);
								update_term_meta( $term_id, 'show_external_ids', $show_ids );

								/**
								 * Add the network of that show.
								 */
								update_term_meta( $term_id, 'show_network', esc_html( $event->show->network ) );
							} // End adding extra info to new shows.
						} // End foreach().
					} // End if().
				} // End loop for each taxonomy that was created.
			} // End loop for each event.
		} // End check for valid array of events.
	} // End publish_event().

	/**
	 * Add up runtime from all recorded events for a specific series.
	 *
	 * @since 2.1.0
	 *
	 * @param int $term_id Series' term ID.
	 *
	 * @return string $runtime Total runtime for this series.
	 */
	private function series_total_runtime_sync( $term_id ) {
		$runtime = 0;

		$query_args = array(
			'post_type'      => 'traktivity_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'trakt_show',
					'field'    => 'term_id',
					'terms'    => $term_id,
				),
			),
		);
		$all_episodes = new WP_Query( $query_args );
		while ( $all_episodes->have_posts() ) {
			$all_episodes->the_post();

			$runtime = $runtime + get_post_meta( $all_episodes->post->ID, 'trakt_runtime', true );
		} // End while().
		wp_reset_postdata();

		return $runtime;
	}

	/**
	 * Recalculate total runtime for each series.
	 *
	 * @since 2.2.0
	 *
	 * @return bool $done Returns true when done.
	 */
	public function total_runtime_sync() {
		// Get sync status.
		$status = $this->get_option( 'full_sync' );

		// Let's not start sync if it's already running.
		if (
			! empty( $status['runtime'] )
			&& 'in_progress' === $status['runtime']['status']
		) {
			return true;
		}

		/**
		 * First let's get a list of term IDs for each one of the shows currently recorded.
		 */
		$shows = get_terms( array(
			'taxonomy'   => 'trakt_show',
			'hide_empty' => false,
			'fields'     => 'tt_ids',
		) );

		// Stop right here if we have no shows.
		if ( ! is_array( $shows ) || empty( $shows ) ) {
			return false;
		}

		// Set it to in progress.
		if ( ! isset( $status['runtime'] ) ) {
			$status['runtime'] = array(
				'status' => 'in_progress',
				'items'  => count( $shows ),
			);
		}

		// Then loop through each show and calculate total runtime.
		foreach ( $shows as $show ) {
			$runtime = $this->series_total_runtime_sync( $show );
			update_term_meta( $show, 'show_runtime', $runtime );
		}

		// We're done. Save options.
		$status['runtime'] = array(
			'status' => 'done',
			'items'  => 0,
		);
		$this->update_option( 'full_sync', $status );

		return true;
	}

	/**
	 * Get all past Trakt.tv events from all Trakt.tv pages.
	 *
	 * @since 1.1.0
	 *
	 * @return bool $done Returns true when done.
	 */
	public function full_sync() {
		/**
		 * First, let's get info about the sync.
		 *
		 * The 'full_sync' option can be one of 2 things:
		 * 1. Empty string -> Option doesn't exist, Sync was never run before. Sync will start and an option will be set.
		 * 2. Array $args {
		 * 		string status Sync Status. Can be 'in_progress' or 'done'.
		 *		int    pages  Number of pages left to sync.
		 * }
		 */
		$status = $this->get_option( 'full_sync' );

		// If sync already ran successfully, we can stop here.
		if ( ! empty( $status ) && isset( $status['status'] ) && 'done' === $status['status'] ) {
			return true;
		}

		/**
		 * If the option doesn't exist, that means we never ran sync before.
		 * Let's get started by changing the status to 'in_progress', and get some data.
		 */
		if ( empty( $status ) ) {
			$status = array(
				'status' => 'in_progress',
				'pages'  => (int) $this->get_trakt_activity( array(), true ),
			);
			// Update our option.
			$this->update_option( 'full_sync', $status );
		}

		// Set WP_IMPORTING to avoid triggering things like subscription emails.
		defined( 'WP_IMPORTING' ) || define( 'WP_IMPORTING', true );

		// let's start looping.
		do {
			$args = array(
				'page'  => $status['pages'],
				'limit' => 10,
			);
			$trakt_events = $this->publish_event( $args );

			// One page less to go.
			$status['pages']--;
		} while ( 'in_progress' === $status['status'] && 0 != $status['pages'] );

		// We're done. Save options.
		$status = array(
			'status' => 'done',
			'pages'  => 0,
		);
		$this->update_option( 'full_sync', $status );

		return true;
	}
} // End class.
new Traktivity_Calls();
