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
function traktivity_first_term( int $post_id, string $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );

	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return null;
	}

	return $terms[0];
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
	return (array) apply_filters( 'traktivity_event_context', $context, $post_id );
}

/**
 * Compose a title that identifies what was watched.
 *
 * Episode titles mean nothing alone, so the show name and the episode code
 * are pulled back in from their separate taxonomies. Movies get their plain
 * title.
 *
 * Always plain text, never markup, so there is one escaping rule rather than
 * one per argument: callers escape it. Anything wanting a linked show name
 * builds that itself from the `show_name` and `show_link` keys on
 * traktivity_get_event(), which are kept separate for exactly this reason.
 *
 * Not yet implemented: returns the plain post title. See issue #685.
 *
 * @since 3.1.0
 *
 * @param int  $post_id   Event post ID.
 * @param bool $show_name Prefix the show name. Default true.
 *
 * @return string Composed title, unescaped plain text.
 */
function traktivity_get_event_title( int $post_id, bool $show_name = true ): string {
	unset( $show_name );

	return (string) get_the_title( $post_id );
}

/**
 * Build the external references stored against an event.
 *
 * Keyed by service ('trakt', 'imdb', 'tmdb'), each value an array with 'label'
 * and 'url'. A service with no stored ID is absent rather than present and
 * empty, so callers can iterate the result directly.
 *
 * Not yet implemented: returns an empty array. See issue #686.
 *
 * @since 3.1.0
 *
 * @param int $post_id Event post ID.
 *
 * @return array<string, array{label: string, url: string}> External links.
 */
function traktivity_get_event_links( int $post_id ): array {
	unset( $post_id );

	return array();
}

/**
 * Read the image stored against a show.
 *
 * Despite the meta key, this is a 16:9 still from TMDb rather than a 2:3
 * poster. Render it in a landscape frame; a portrait one crops faces off.
 *
 * Returns an empty array when the show has no image, so callers can test the
 * result rather than picking through the stored value's shape.
 *
 * Not yet implemented: returns an empty array. See issue #687.
 *
 * @since 3.1.0
 *
 * @param int $term_id trakt_show term ID.
 *
 * @return array Empty when the show has no image. Otherwise an 'id' attachment
 *               ID, a 'url' and an 'alt' string, in that order. The shape is
 *               pinned by tests/php/ContractsTest.php rather than by this
 *               annotation, which stays loose while the body is a stub.
 */
function traktivity_get_show_poster( int $term_id ): array {
	unset( $term_id );

	return array();
}

/**
 * Read the network a show airs on.
 *
 * Not yet implemented: returns an empty string. See issue #687.
 *
 * @since 3.1.0
 *
 * @param int $term_id trakt_show term ID.
 *
 * @return string Network name, or an empty string.
 */
function traktivity_get_show_network( int $term_id ): string {
	unset( $term_id );

	return '';
}

/**
 * Read the total time logged against a show, in minutes.
 *
 * Not yet implemented: returns 0. See issue #687.
 *
 * @since 3.1.0
 *
 * @param int $term_id trakt_show term ID.
 *
 * @return int Minutes watched.
 */
function traktivity_get_show_runtime( int $term_id ): int {
	unset( $term_id );

	return 0;
}

/**
 * Build the external references stored against a show.
 *
 * Same shape as traktivity_get_event_links().
 *
 * Not yet implemented: returns an empty array. See issue #687.
 *
 * @since 3.1.0
 *
 * @param int $term_id trakt_show term ID.
 *
 * @return array<string, array{label: string, url: string}> External links.
 */
function traktivity_get_show_links( int $term_id ): array {
	unset( $term_id );

	return array();
}
