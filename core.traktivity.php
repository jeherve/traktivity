<?php
/**
 * Core functions.
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


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
	 *
	 * @return null|array
	 */
	private function get_trakt_activity( $args = array() ) {

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
			array( 'headers' => $headers )
		);

		if (
			is_wp_error( $data )
			|| 200 != $data['response']['code']
			|| empty( $data['body'] )
		) {
			return;
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
					$post_image_id = $images[0]->ID;
				}

				/**
				 * Finally, let's populate the $post_image we'll return.
				 */
				// Store the attachment ID.
				$post_image['id'] = (int) $post_image_id;

				// Create a div containing a large version of the image, to be added to the post if needed.
				$post_image['tag'] = sprintf(
					'<div class="poster-image">%s</div>',
					wp_get_attachment_image( $post_image_id, 'large' )
				);
			}
		}

		return $post_image;
	}

	/**
	 * Publish Trakt.tv Event.
	 *
	 * @since 1.0.0
	 */
	public function publish_event() {
		// Avoid timeouts during the data import process.
		set_time_limit( 0 );

		$trakt_events = $this->get_trakt_activity();

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
				$meta = array( 'trakt_event_id' => intval( $event->id ) );

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
					$taxonomies['trakt_year']  = intval( $event->year );

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
					$taxonomies['trakt_year']    = intval( $event->show->year );
					$taxonomies['trakt_show']    = esc_html( $event->show->title );
					$taxonomies['trakt_season']  = intval( $event->episode->season );
					$taxonomies['trakt_episode'] = intval( $event->episode->number );

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
				}

				// Grab the event title.
				if ( 'movie' === $event->type ) {
					$title = $event->movie->title;
				} elseif ( 'episode' === $event->type ) {
					/**
					 * For TV Shows, it might be best to append the show's name to the episode name.
					 * This way, slugs won't conflict when 2 shows have an episode that has the same name.
					 * (think common episode names like "Pilot" for example.)
					 */
					$title = sprintf(
						'%1$s -- %2$s',
						$event->episode->title,
						$event->show->title
					);
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
				);

				// Create our post.
				$post_id = wp_insert_post( $event_args );

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
						$post_image = $this->sideload_image( $image['url'], $post_id, $title, true );

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
								 */
								$show_image = $this->get_item_poster( 'show', $meta['tmdb_show_id'], false, false );
								if ( is_array( $show_image ) && ! empty( $show_image ) ) {
									$local_image = $this->sideload_image( $show_image['url'], $post_id, $taxonomies['trakt_show'], false );

									update_term_meta( $term_id, 'show_poster', $local_image );
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

							} // End adding extra info to new shows.
						}
					}
				} // End loop for each taxonomy that was created.

			} // End loop for each event.

		} // End check for valid array of events.

	} // End publish_event().

} // End class.
new Traktivity_Calls();
