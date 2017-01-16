<?php
/**
 * Core functions.
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );


/**
 * Trakt.tv API Calls
 *
 * @since 1.0.0
 */
class Traktivity_Calls {

	/**
	 * Constructor
	 */
	function __construct() {
/*
		add_action( 'traktivity_publish', array( $this, 'publish_event' ) );
		if ( ! wp_next_scheduled( 'traktivity_publish' ) ) {
			wp_schedule_event( time(), 'hourly', 'traktivity_publish' );
		}
*/
		add_action( 'wp_head', array( $this, 'publish_event' ) );
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
	 * @return null|array
	 */
	private function get_trakt_activity() {

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
/*
 to-do: maybe up that limit here. Not sure, it's really only useful the first time, for past views. Maybe make it a filter.
 http://docs.trakt.apiary.io/#reference/users/history/get-watched-history
*/
			'%1$s/users/%2$s/history?page=1&limit=10&extended=full',
			TRAKTIVITY__API_URL,
			$username
		);

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
	 * Publish GitHub Event.
	 *
	 * @since 1.0
	 */
	public function publish_event() {
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

					$taxonomies['trakt_type'] = esc_html__( 'movie', 'traktivity' );
					// Let's capitalize genres.
					$taxonomies['trakt_genre'] = array_map( 'ucwords', $event->movie->genres );
					$taxonomies['trakt_year'] = intval( $event->year );

					$meta['trakt_movie_id'] = intval( $event->movie->ids->trakt );
					$meta['imdb_movie_id'] = esc_html( $event->movie->ids->imdb );
					$meta['tmdb_movie_id'] = esc_html( $event->movie->ids->tmdb );
					$meta['trakt_runtime'] = intval( $event->movie->runtime );

					$post_excerpt = $event->movie->tagline;
					$post_content = $event->movie->overview;
					//var_dump( $event );

				} elseif ( 'episode' === $event->type ) { // Then let's gather info about series.

					$taxonomies['trakt_type'] = esc_html__( 'TV Series', 'traktivity' );
					// Let's capitalize genres.
					$taxonomies['trakt_genre'] = array_map( 'ucwords', $event->show->genres );
					$taxonomies['trakt_year'] = intval( $event->show->year );
					$taxonomies['trakt_show'] = esc_html( $event->show->title );
					$taxonomies['trakt_season'] = intval( $event->episode->season );
					$taxonomies['trakt_episode'] = intval( $event->episode->number );

					$meta['trakt_episode_id'] = intval( $event->episode->ids->trakt );
					$meta['trakt_show_id'] = intval( $event->show->ids->trakt );
					$meta['imdb_episode_id'] = esc_html( $event->episode->ids->imdb );
					$meta['imdb_show_id'] = esc_html( $event->show->ids->imdb );
					$meta['tmdb_episode_id'] = esc_html( $event->episode->ids->tmdb );
					$meta['tmdb_show_id'] = esc_html( $event->show->ids->tmdb );
					$meta['trakt_runtime'] = intval( $event->show->runtime );

					$post_excerpt = $event->episode->overview;
					$post_content = $event->episode->overview;
					//var_dump( $event );

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

/*
Register meta Maybe
https://wordpress.stackexchange.com/questions/211703/need-a-simple-but-complete-example-of-adding-metabox-to-taxonomy
*/

				// Let it all come together as a list of things we'll add to the post we're creating.
				$event_args = array(
					/**
					 * Filter the Events' Post Title.
					 *
					 * @since 1.0.0
					 *
					 * @param string $title Event title. By default it's the movie title, or the episode title followed by the show title.
					 * @param array  $event Array of details about the event.
					 */
					'post_title'   => apply_filters( 'traktivity_event_title', esc_html( $title ), $event ),
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
							if ( is_array( $term_id_object ) ) {
								$term_id = (int) $term_id_object['term_id'];
								// If we added a new show, we'll add its description here.
								$show_args = array(
									'description' => esc_html( $event->show->overview ),
								);
								wp_update_term( $term_id, 'trakt_show', $show_args );
							}
						}
					}
				} // End loop for each taxonomy that was created.

/*
Upload poster images from tmdb
https://gist.github.com/m1r0/f22d5237ee93bcccb0d9
https://codex.wordpress.org/Function_Reference/wp_insert_attachment
https://developers.themoviedb.org/3/movies/get-movie-images
https://developers.themoviedb.org/3/authentication
https://developers.themoviedb.org/3/tv/get-tv-images
*/
			} // End loop for each event.
		} // End check for valid array of events.
	} // End publish_event().
} // End class.
new Traktivity_Calls();
