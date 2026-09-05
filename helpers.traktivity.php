<?php
/**
 * Shared accessors for Traktivity data.
 *
 * A watch event is spread across six taxonomies and a handful of post meta,
 * and a show carries four more values in term meta. These functions are the
 * only supported way to read any of it: blocks, templates and themes go
 * through here rather than learning the storage layout.
 *
 * The signatures and the array shapes below are a contract. Blocks are built
 * against them, and tests/php/ContractsTest.php pins every key and type. Add
 * keys freely; renaming or removing one breaks callers, so raise it on the
 * 3.1.0 tracking issue first.
 *
 * @package Traktivity
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * The shape every event context carries.
 *
 * Returned in full whatever the event turns out to hold, so callers can read
 * a key without checking it exists first. Movies leave the show, season and
 * episode fields at their empty values.
 *
 * @since 3.1.0
 *
 * @return array{
 *     type: string, title: string, permalink: string, watched: string,
 *     watched_iso: string, runtime: int, year: string, image_id: int,
 *     show_name: string, show_link: string, season: int, episode: int,
 *     episode_code: string
 * } Empty event context.
 */
function traktivity_empty_event_context(): array {
	return array(
		'type'         => '',
		'title'        => '',
		'permalink'    => '',
		'watched'      => '',
		'watched_iso'  => '',
		'runtime'      => 0,
		'year'         => '',
		'image_id'     => 0,
		'show_name'    => '',
		'show_link'    => '',
		'season'       => 0,
		'episode'      => 0,
		'episode_code' => '',
	);
}

/**
 * Read the first term attached to a post in a taxonomy.
 *
 * Every one of these taxonomies is many-to-many in the database, but the sync
 * only ever attaches one show, one season and one episode to an event, so the
 * first term is the term.
 *
 * @since 3.1.0
 *
 * @param int    $post_id  Post ID.
 * @param string $taxonomy Taxonomy name.
 *
 * @return WP_Term|null First term, or null when there is none.
 */
function traktivity_first_term( int $post_id, string $taxonomy ): ?WP_Term {
	$terms = get_the_terms( $post_id, $taxonomy );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return null;
	}

	/*
	 * reset() rather than $terms[0]. get_the_terms() hands its array through a
	 * filter before returning, and anything narrowing it with array_filter()
	 * keeps the original keys, so index 0 is not guaranteed to be there.
	 * Anything that is not a term counts as no term, rather than reaching a
	 * caller that reads ->name off it.
	 */
	$term = reset( $terms );

	return $term instanceof WP_Term ? $term : null;
}

/**
 * Collect everything needed to describe one watch event.
 *
 * `type` is either 'tv' or 'movie'. `episode_code` is a composed 'S3E2' for
 * TV events that carry both numbers, and an empty string otherwise.
 *
 * @since 3.1.0
 *
 * @param int $post_id Event post ID.
 *
 * @return array Event context, in the shape of traktivity_empty_event_context().
 */
function traktivity_get_event( int $post_id ): array {
	$context = traktivity_empty_event_context();

	if ( $post_id < 1 || 'traktivity_event' !== get_post_type( $post_id ) ) {
		return $context;
	}

	/*
	 * Type comes from the meta rather than the trakt_type term. The sync names
	 * that term with a translated string, so its slug is 'movie' on an English
	 * site and something else entirely on a French one. The ID meta keys are
	 * the same in every language.
	 */
	$is_movie = '' !== (string) get_post_meta( $post_id, 'trakt_movie_id', true );

	$permalink = get_permalink( $post_id );
	$year      = traktivity_first_term( $post_id, 'trakt_year' );

	$context['type']        = $is_movie ? 'movie' : 'tv';
	$context['title']       = (string) get_the_title( $post_id );
	$context['permalink']   = is_string( $permalink ) ? $permalink : '';
	$context['watched']     = (string) get_the_date( '', $post_id );
	$context['watched_iso'] = (string) get_the_date( 'c', $post_id );
	$context['runtime']     = (int) get_post_meta( $post_id, 'trakt_runtime', true );
	$context['year']        = null === $year ? '' : $year->name;
	$context['image_id']    = (int) get_post_thumbnail_id( $post_id );

	if ( ! $is_movie ) {
		$show = traktivity_first_term( $post_id, 'trakt_show' );

		if ( null !== $show ) {
			$show_link = get_term_link( $show );

			$context['show_name'] = $show->name;
			$context['show_link'] = is_wp_error( $show_link ) ? '' : $show_link;
		}

		$season  = traktivity_first_term( $post_id, 'trakt_season' );
		$episode = traktivity_first_term( $post_id, 'trakt_episode' );

		$context['season']  = null === $season ? 0 : (int) $season->name;
		$context['episode'] = null === $episode ? 0 : (int) $episode->name;

		/*
		 * Both terms have to be there, rather than both being above zero.
		 * Season 0 is where a show's specials live, so 'S0E5' is a real
		 * episode code and testing for a positive number would drop it.
		 */
		if ( null !== $season && null !== $episode ) {
			$context['episode_code'] = sprintf( 'S%dE%d', $context['season'], $context['episode'] );
		}
	}

	/**
	 * Filter the context describing a single watch event.
	 *
	 * Keys documented in traktivity_empty_event_context() are relied on by the
	 * plugin's own blocks, so add to the array rather than removing from it.
	 *
	 * @since 3.1.0
	 *
	 * @param array $context Event context.
	 * @param int   $post_id Event post ID.
	 */
	$traktivity_filtered = apply_filters( 'traktivity_event_context', $context, $post_id );

	/*
	 * A filter returning something other than an array is ignored rather than
	 * cast: (array) null is empty and (array) 'x' is a one-item list, and
	 * either leaves callers with a context missing every documented key, which
	 * they read without checking.
	 */
	return is_array( $traktivity_filtered ) ? $traktivity_filtered : $context;
}

/**
 * Compose a title that identifies what was watched.
 *
 * Episode titles mean nothing alone, so the show name and the episode code are
 * pulled back in from their separate taxonomies. Movies get their plain title.
 *
 * Always plain text, never markup, so there is one escaping rule rather than
 * one per argument: callers escape it. Anything wanting a linked show name
 * builds that itself from the `show_name` and `show_link` keys on
 * traktivity_get_event(), which are kept separate for exactly this reason.
 *
 * @since 3.1.0
 *
 * @param int  $post_id   Event post ID.
 * @param bool $show_name Prefix the show name. Default true.
 *
 * @return string Composed title, unescaped plain text.
 */
function traktivity_get_event_title( int $post_id, bool $show_name = true ): string {
	$event = traktivity_get_event( $post_id );

	if ( '' === $event['title'] ) {
		return (string) get_the_title( $post_id );
	}

	/*
	 * Built from whatever is actually there. An event missing its season term
	 * still has a show worth naming, and one missing its show still has an
	 * episode code worth printing, so each part is dropped on its own rather
	 * than the whole thing falling back to the bare episode name.
	 */
	$show = $show_name ? $event['show_name'] : '';
	$code = $event['episode_code'];

	/*
	 * The two go through their own string rather than being joined with a
	 * hard-coded space, so a translator can reorder them or change what sits
	 * between them. Several languages want neither a space nor this order.
	 */
	if ( '' !== $show && '' !== $code ) {
		$prefix = sprintf(
			/* translators: 1: show name. 2: episode code, e.g. "S3E2". */
			_x( '%1$s %2$s', 'Show name followed by episode code', 'traktivity' ),
			$show,
			$code
		);
	} else {
		$prefix = '' !== $show ? $show : $code;
	}

	if ( '' === $prefix ) {
		return $event['title'];
	}

	$title = sprintf(
		/* translators: 1: show name and episode code, e.g. "Some Show S3E2". 2: episode title. */
		_x( '%1$s: %2$s', 'Composed title for one watched episode', 'traktivity' ),
		$prefix,
		$event['title']
	);

	/**
	 * Filter the composed title for one watch event.
	 *
	 * Plain text in, plain text out. Returning markup here breaks callers,
	 * which escape the result on the way to the page.
	 *
	 * @since 3.1.0
	 *
	 * @param string $title   Composed title.
	 * @param array  $event   Event context, from traktivity_get_event().
	 * @param int    $post_id Event post ID.
	 */
	return (string) apply_filters( 'traktivity_event_title_text', $title, $event, $post_id );
}

/**
 * Pair a service with the label it is shown under.
 *
 * The URLs are built by the callers, since each service takes a different path
 * and a different ID. What is shared here is the label table and the two-key
 * shape every reference comes back in, so a service is named the same way
 * whether it was reached from an event or from a show.
 *
 * Callers encode the ID into the URL before calling this; nothing here
 * inspects what a URL contains.
 *
 * @since 3.1.0
 *
 * @param string $service Service key: 'trakt', 'imdb' or 'tmdb'.
 * @param string $url     Full URL, already built and encoded by the caller.
 *
 * @return array{label: string, url: string} One reference.
 */
function traktivity_build_link( string $service, string $url ): array {
	$labels = array(
		'trakt' => __( 'Trakt', 'traktivity' ),
		'imdb'  => __( 'IMDb', 'traktivity' ),
		'tmdb'  => __( 'TMDb', 'traktivity' ),
	);

	return array(
		'label' => isset( $labels[ $service ] ) ? $labels[ $service ] : $service,
		'url'   => $url,
	);
}

/**
 * Build the external references stored against an event.
 *
 * Keyed by service ('trakt', 'imdb', 'tmdb'), each value an array with 'label'
 * and 'url'. A service with no stored ID is absent rather than present and
 * empty, so callers can iterate the result directly.
 *
 * Which ID a service gets depends on the event type, and on how precise a link
 * that service supports. IMDb has a page per episode, so TV events link
 * straight to it. TMDb needs the season and episode numbers in the path to do
 * the same, so it deep-links when they are known and falls back to the show.
 *
 * @since 3.1.0
 *
 * @param int $post_id Event post ID.
 *
 * @return array<string, array{label: string, url: string}> External links.
 */
function traktivity_get_event_links( int $post_id ): array {
	$event = traktivity_get_event( $post_id );

	if ( '' === $event['type'] ) {
		return array();
	}

	$is_movie = 'movie' === $event['type'];
	$links    = array();

	$trakt_id = (string) get_post_meta( $post_id, $is_movie ? 'trakt_movie_id' : 'trakt_show_id', true );
	if ( '' !== $trakt_id ) {
		$links['trakt'] = traktivity_build_link(
			'trakt',
			add_query_arg(
				'id_type',
				$is_movie ? 'movie' : 'show',
				'https://trakt.tv/search/trakt/' . rawurlencode( $trakt_id )
			)
		);
	}

	$imdb_id = (string) get_post_meta( $post_id, $is_movie ? 'imdb_movie_id' : 'imdb_episode_id', true );
	if ( '' !== $imdb_id ) {
		$links['imdb'] = traktivity_build_link( 'imdb', 'https://www.imdb.com/title/' . rawurlencode( $imdb_id ) . '/' );
	}

	$tmdb_id = (string) get_post_meta( $post_id, $is_movie ? 'tmdb_movie_id' : 'tmdb_show_id', true );
	if ( '' !== $tmdb_id ) {
		$tmdb_url = 'https://www.themoviedb.org/' . ( $is_movie ? 'movie' : 'tv' ) . '/' . rawurlencode( $tmdb_id );

		if ( ! $is_movie && '' !== $event['episode_code'] ) {
			$tmdb_url .= sprintf( '/season/%1$d/episode/%2$d', $event['season'], $event['episode'] );
		}

		$links['tmdb'] = traktivity_build_link( 'tmdb', $tmdb_url );
	}

	/**
	 * Filter the external references for one watch event.
	 *
	 * Entries are rendered as links, so anything added here needs a 'label'
	 * and a 'url'.
	 *
	 * @since 3.1.0
	 *
	 * @param array $links   External references, keyed by service.
	 * @param int   $post_id Event post ID.
	 * @param array $event   Event context, from traktivity_get_event().
	 */
	return (array) apply_filters( 'traktivity_event_links', $links, $post_id, $event );
}

/**
 * Register the term meta the sync stores against each show.
 *
 * Registering it puts the values in the REST API and gives them a schema, so
 * a block editor or an external client can read a show the same way the
 * plugin's own blocks do. The sync wrote all of this long before it was
 * registered, so these describe existing data rather than introducing it.
 *
 * @since 3.1.0
 */
function traktivity_register_show_meta(): void {
	register_term_meta(
		'trakt_show',
		'show_runtime',
		array(
			'type'              => 'integer',
			'description'       => __( 'Total number of minutes logged against this show.', 'traktivity' ),
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => '__return_false',
		)
	);

	register_term_meta(
		'trakt_show',
		'show_network',
		array(
			'type'              => 'string',
			'description'       => __( 'Network the show airs on.', 'traktivity' ),
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => '__return_false',
		)
	);

	register_term_meta(
		'trakt_show',
		'show_poster',
		array(
			'type'          => 'object',
			'description'   => __( 'Image for the show, as stored by the sync.', 'traktivity' ),
			'single'        => true,
			'show_in_rest'  => array(
				'schema' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'id'  => array( 'type' => 'integer' ),
						'url' => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'alt' => array( 'type' => 'string' ),
					),
				),
			),
			'auth_callback' => '__return_false',
		)
	);

	register_term_meta(
		'trakt_show',
		'show_external_ids',
		array(
			'type'          => 'object',
			'description'   => __( 'Trakt.tv, IMDb and TMDb IDs for the show.', 'traktivity' ),
			'single'        => true,
			'show_in_rest'  => array(
				'schema' => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'properties'           => array(
						'trakt' => array( 'type' => array( 'integer', 'string' ) ),
						'imdb'  => array( 'type' => 'string' ),
						'tmdb'  => array( 'type' => array( 'integer', 'string' ) ),
					),
				),
			),
			'auth_callback' => '__return_false',
		)
	);
}
add_action( 'init', 'traktivity_register_show_meta' );

/**
 * Read the image stored against a show.
 *
 * Despite the meta key, this is a 16:9 still from TMDb rather than a 2:3
 * poster. Render it in a landscape frame; a portrait one crops faces off.
 *
 * Returns an empty array when the show has no image, so callers can test the
 * result rather than picking through the stored value's shape.
 *
 * @since 3.1.0
 *
 * @param int $term_id trakt_show term ID.
 *
 * @return array Empty when the show has no image. Otherwise an 'id' attachment
 *               ID, a 'url' and an 'alt' string, in that order.
 */
function traktivity_get_show_poster( int $term_id ): array {
	$poster = get_term_meta( $term_id, 'show_poster', true );

	/*
	 * The sync stores whatever sideload_image() handed back, which is an empty
	 * array when the download failed and can be a partial one when the
	 * attachment was made but the URL lookup came back empty. Normalising here
	 * means callers go straight to $poster['id'] rather than each repeating
	 * this.
	 */
	if ( ! is_array( $poster ) || empty( $poster['id'] ) ) {
		return array();
	}

	return array(
		'id'  => (int) $poster['id'],
		'url' => isset( $poster['url'] ) ? (string) $poster['url'] : '',
		'alt' => isset( $poster['alt'] ) ? (string) $poster['alt'] : '',
	);
}

/**
 * Read the network a show airs on.
 *
 * @since 3.1.0
 *
 * @param int $term_id trakt_show term ID.
 *
 * @return string Network name, or an empty string.
 */
function traktivity_get_show_network( int $term_id ): string {
	return (string) get_term_meta( $term_id, 'show_network', true );
}

/**
 * Read the total time logged against a show, in minutes.
 *
 * @since 3.1.0
 *
 * @param int $term_id trakt_show term ID.
 *
 * @return int Minutes watched.
 */
function traktivity_get_show_runtime( int $term_id ): int {
	return (int) get_term_meta( $term_id, 'show_runtime', true );
}

/**
 * Build the external references stored against a show.
 *
 * Same shape as traktivity_get_event_links(). These always point at the show
 * rather than at an episode, since a term has no episode to be precise about.
 *
 * @since 3.1.0
 *
 * @param int $term_id trakt_show term ID.
 *
 * @return array<string, array{label: string, url: string}> External links.
 */
function traktivity_get_show_links( int $term_id ): array {
	$ids = get_term_meta( $term_id, 'show_external_ids', true );

	if ( ! is_array( $ids ) ) {
		return array();
	}

	$links = array();

	if ( ! empty( $ids['trakt'] ) ) {
		$links['trakt'] = traktivity_build_link(
			'trakt',
			add_query_arg( 'id_type', 'show', 'https://trakt.tv/search/trakt/' . rawurlencode( (string) $ids['trakt'] ) )
		);
	}

	if ( ! empty( $ids['imdb'] ) ) {
		$links['imdb'] = traktivity_build_link( 'imdb', 'https://www.imdb.com/title/' . rawurlencode( (string) $ids['imdb'] ) . '/' );
	}

	if ( ! empty( $ids['tmdb'] ) ) {
		$links['tmdb'] = traktivity_build_link( 'tmdb', 'https://www.themoviedb.org/tv/' . rawurlencode( (string) $ids['tmdb'] ) );
	}

	/**
	 * Filter the external references for one show.
	 *
	 * @since 3.1.0
	 *
	 * @param array $links   External references, keyed by service.
	 * @param int   $term_id trakt_show term ID.
	 */
	return (array) apply_filters( 'traktivity_show_links', $links, $term_id );
}
