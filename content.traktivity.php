<?php
/**
 * Extra content added to Trakt.tv Event Pages on the front end.
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );


/**
 * API Calls to get our data, and then store it in our custom post type and taxonomies.
 * The core of the plugin's work happens here.
 *
 * @since 1.1.0
 */
class Traktivity_Content {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'the_content', array( $this, 'credits' ), 5 );
	}

	/**
	 * Display credits at the bottom of each Event page.
	 *
	 * @since 1.1.0
	 *
	 * @param string $content Post content.
	 *
	 * @return string $content Post content, with credits appended when relevant.
	 */
	public function credits( $content ) {
		// Only add the credits to the detail pages of our post type.
		if ( ! is_singular( 'traktivity_event' ) || ! is_main_query() ) {
			return $content;
		}

		// If we have an image in that post, we'll add credits.
		if ( false !== strpos( $content, '<img' ) ) {
			/*
			 * The string carries its own markup so translators can move the
			 * link within the sentence, which means a translation decides what
			 * HTML ends up on the page. wp_kses() holds it to a plain link.
			 */
			$credits  = '<div class="tmdb_credits"><p><small>';
			$credits .= wp_kses(
				sprintf(
					/* Translators: %s is the URL of the TMDb website. */
					__( 'Image source: <a href="%s">themoviedb.org</a>', 'traktivity' ),
					esc_url( 'https://www.themoviedb.org/' )
				),
				array(
					'a' => array(
						'href' => array(),
					),
				)
			);
			$credits .= '</small></p></div>';

			/**
			 * Filter the sentence used in the credits.
			 * One could then use `add_filter( 'traktivity_event_credits_string', '__return_empty_string' );` to remove the credits.
			 *
			 * @since 1.1.0
			 *
			 * @param string $credits Credit text.
			 * @param string $content Post content.
			 */
			$credits = apply_filters( 'traktivity_event_credits_string', $credits, $content );

			return $content . $credits;
		}

		// Final fallback.
		return $content;
	}
}
new Traktivity_Content();
