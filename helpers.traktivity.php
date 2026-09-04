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
 * Collect everything needed to describe one watch event.
 *
 * `type` is either 'tv' or 'movie'. `episode_code` is a composed 'S3E2' for
 * TV events that carry both numbers, and an empty string otherwise.
 *
 * Not yet implemented: returns the empty shape. See issue #684.
 *
 * @since 3.1.0
 *
 * @param int $post_id Event post ID.
 *
 * @return array Event context, in the shape of traktivity_empty_event_context().
 */
function traktivity_get_event( int $post_id ): array {
	unset( $post_id );

	return traktivity_empty_event_context();
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
