<?php
/**
 * Tests for the external reference builder.
 *
 * @package Traktivity
 */

/**
 * Linking a watch event out to Trakt.tv, IMDb and TMDb.
 */
class EventLinksTest extends WP_UnitTestCase {

	/**
	 * Build an event the way the sync would.
	 *
	 * @param array $meta       Post meta to attach.
	 * @param array $taxonomies Terms to attach, keyed by taxonomy.
	 *
	 * @return int Post ID.
	 */
	private function make_event( array $meta, array $taxonomies = array() ) {
		$post_id = self::factory()->post->create( array( 'post_type' => 'traktivity_event' ) );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		// Always an array: a bare "0" reads as empty to wp_set_object_terms(). See #702.
		foreach ( $taxonomies as $taxonomy => $terms ) {
			wp_set_object_terms( $post_id, (array) $terms, $taxonomy );
		}

		return $post_id;
	}

	/**
	 * A TV episode links out to all three services.
	 */
	public function test_tv_episode_links_to_every_service() {
		$post_id = $this->make_event(
			array(
				'trakt_show_id'   => 111,
				'imdb_episode_id' => 'tt1234567',
				'tmdb_show_id'    => 222,
			),
			array( 'trakt_show' => 'Some Show' )
		);

		$links = traktivity_get_event_links( $post_id );

		$this->assertSame( array( 'trakt', 'imdb', 'tmdb' ), array_keys( $links ) );
		$this->assertStringContainsString( 'id_type=show', $links['trakt']['url'] );
		$this->assertStringContainsString( '111', $links['trakt']['url'] );
		$this->assertSame( 'https://www.imdb.com/title/tt1234567/', $links['imdb']['url'] );
		$this->assertStringContainsString( '/tv/222', $links['tmdb']['url'] );
	}

	/**
	 * A movie uses the movie IDs, not the show ones.
	 */
	public function test_movie_uses_the_movie_ids() {
		$post_id = $this->make_event(
			array(
				'trakt_movie_id' => 333,
				'imdb_movie_id'  => 'tt7654321',
				'tmdb_movie_id'  => 444,
			)
		);

		$links = traktivity_get_event_links( $post_id );

		$this->assertStringContainsString( 'id_type=movie', $links['trakt']['url'] );
		$this->assertSame( 'https://www.imdb.com/title/tt7654321/', $links['imdb']['url'] );
		$this->assertSame( 'https://www.themoviedb.org/movie/444', $links['tmdb']['url'] );
	}

	/**
	 * TMDb deep-links to the episode when the numbers are known.
	 *
	 * TMDb has a page per episode, but the path needs the season and episode
	 * numbers, which live in taxonomies rather than in the stored IDs.
	 */
	public function test_tmdb_deep_links_to_the_episode() {
		$post_id = $this->make_event(
			array(
				'trakt_show_id' => 1,
				'tmdb_show_id'  => 555,
			),
			array(
				'trakt_show'    => 'Some Show',
				'trakt_season'  => '3',
				'trakt_episode' => '2',
			)
		);

		$this->assertSame(
			'https://www.themoviedb.org/tv/555/season/3/episode/2',
			traktivity_get_event_links( $post_id )['tmdb']['url']
		);
	}

	/**
	 * Without the numbers, TMDb falls back to the show.
	 */
	public function test_tmdb_falls_back_to_the_show() {
		$post_id = $this->make_event(
			array(
				'trakt_show_id' => 1,
				'tmdb_show_id'  => 666,
			),
			array( 'trakt_show' => 'Some Show' )
		);

		$this->assertSame(
			'https://www.themoviedb.org/tv/666',
			traktivity_get_event_links( $post_id )['tmdb']['url']
		);
	}

	/**
	 * A service with no stored ID is absent, not empty.
	 *
	 * Callers iterate the result straight into a list of links, so an entry
	 * with an empty URL would render as a dead link.
	 */
	public function test_missing_ids_are_absent_rather_than_empty() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 777 ),
			array( 'trakt_show' => 'Some Show' )
		);

		$links = traktivity_get_event_links( $post_id );

		$this->assertSame( array( 'trakt' ), array_keys( $links ) );
		$this->assertArrayNotHasKey( 'imdb', $links );
		$this->assertArrayNotHasKey( 'tmdb', $links );
	}

	/**
	 * An event with no external IDs at all returns nothing.
	 */
	public function test_event_with_no_ids_returns_nothing() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'traktivity_event' ) );

		$this->assertSame( array(), traktivity_get_event_links( $post_id ) );
	}

	/**
	 * A post of another type returns nothing.
	 */
	public function test_other_post_types_return_nothing() {
		$post_id = self::factory()->post->create();

		$this->assertSame( array(), traktivity_get_event_links( $post_id ) );
	}

	/**
	 * Every entry carries a label and a URL.
	 */
	public function test_entries_are_labelled() {
		$post_id = $this->make_event( array( 'trakt_movie_id' => 888 ) );

		$this->assertSame( array( 'label', 'url' ), array_keys( traktivity_get_event_links( $post_id )['trakt'] ) );
		$this->assertSame( 'Trakt', traktivity_get_event_links( $post_id )['trakt']['label'] );
	}

	/**
	 * An ID carrying URL-unsafe characters cannot break out of the path.
	 *
	 * These come from a third-party API, so they are encoded rather than
	 * trusted.
	 */
	public function test_ids_are_encoded() {
		$post_id = $this->make_event( array( 'trakt_movie_id' => 'a/b?c' ) );

		$url = traktivity_get_event_links( $post_id )['trakt']['url'];

		$this->assertStringContainsString( 'a%2Fb%3Fc', $url );
	}

	/**
	 * The links are filterable.
	 */
	public function test_links_are_filterable() {
		$post_id = $this->make_event( array( 'trakt_movie_id' => 999 ) );

		add_filter(
			'traktivity_event_links',
			static function ( $links ) {
				unset( $links['trakt'] );
				$links['letterboxd'] = array(
					'label' => 'Letterboxd',
					'url'   => 'https://letterboxd.com/',
				);
				return $links;
			}
		);

		$links = traktivity_get_event_links( $post_id );

		$this->assertArrayNotHasKey( 'trakt', $links );
		$this->assertSame( 'Letterboxd', $links['letterboxd']['label'] );
	}
}
