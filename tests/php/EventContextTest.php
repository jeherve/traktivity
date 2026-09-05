<?php
/**
 * Tests for the event context accessor.
 *
 * @package Traktivity
 */

/**
 * Reassembling one watch event from its taxonomies and meta.
 */
class EventContextTest extends WP_UnitTestCase {

	/**
	 * Build an event the way the sync would.
	 *
	 * @param array $meta       Post meta to attach.
	 * @param array $taxonomies Terms to attach, keyed by taxonomy.
	 * @param array $postarr    Overrides for wp_insert_post().
	 *
	 * @return int Post ID.
	 */
	private function make_event( array $meta = array(), array $taxonomies = array(), array $postarr = array() ) {
		$post_id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'  => 'traktivity_event',
					'post_title' => 'It Is All Good',
					'post_date'  => '2026-03-04 20:00:00',
				),
				$postarr
			)
		);

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}

		/*
		 * Always an array. Passing the bare string "0" makes
		 * wp_set_object_terms() treat the list as empty, because empty( "0" )
		 * is true in PHP, and silently attach nothing. That is issue #702 on
		 * the sync side; here it would just make the season 0 test lie.
		 */
		foreach ( $taxonomies as $taxonomy => $terms ) {
			wp_set_object_terms( $post_id, (array) $terms, $taxonomy );
		}

		return $post_id;
	}

	/**
	 * A TV event reassembles into something that identifies an episode.
	 */
	public function test_tv_event_is_described_in_full() {
		$post_id = $this->make_event(
			array(
				'trakt_show_id' => 1234,
				'trakt_runtime' => 60,
			),
			array(
				'trakt_show'    => 'Some Show',
				'trakt_season'  => '3',
				'trakt_episode' => '2',
				'trakt_year'    => '2023',
			)
		);

		$event = traktivity_get_event( $post_id );

		$this->assertSame( 'tv', $event['type'] );
		$this->assertSame( 'It Is All Good', $event['title'] );
		$this->assertSame( 'Some Show', $event['show_name'] );
		$this->assertSame( 3, $event['season'] );
		$this->assertSame( 2, $event['episode'] );
		$this->assertSame( 'S3E2', $event['episode_code'] );
		$this->assertSame( 60, $event['runtime'] );
		$this->assertSame( '2023', $event['year'] );
		$this->assertNotSame( '', $event['show_link'] );
		$this->assertNotSame( '', $event['permalink'] );
	}

	/**
	 * A movie carries no show, season or episode.
	 */
	public function test_movie_has_no_episode_fields() {
		$post_id = $this->make_event(
			array(
				'trakt_movie_id' => 99,
				'trakt_runtime'  => 105,
			),
			array( 'trakt_year' => '2019' ),
			array( 'post_title' => 'A Film' )
		);

		$event = traktivity_get_event( $post_id );

		$this->assertSame( 'movie', $event['type'] );
		$this->assertSame( 'A Film', $event['title'] );
		$this->assertSame( '', $event['show_name'] );
		$this->assertSame( '', $event['show_link'] );
		$this->assertSame( 0, $event['season'] );
		$this->assertSame( 0, $event['episode'] );
		$this->assertSame( '', $event['episode_code'] );
		$this->assertSame( 105, $event['runtime'] );
	}

	/**
	 * Type is read from the meta, not the translated trakt_type term.
	 *
	 * The sync names that term with esc_html__( 'Movie' ), so its slug is
	 * 'movie' only on an English site. Detecting the type from the term would
	 * silently mistype every event on a translated install, which is exactly
	 * the sort of bug nobody running an English site would ever see.
	 */
	public function test_type_survives_a_translated_type_term() {
		$post_id = $this->make_event(
			array( 'trakt_movie_id' => 42 ),
			array( 'trakt_type' => 'Filme' )
		);

		$this->assertSame( 'movie', traktivity_get_event( $post_id )['type'] );

		$tv_id = $this->make_event(
			array( 'trakt_show_id' => 43 ),
			array(
				'trakt_type' => 'Serie TV',
				'trakt_show' => 'Another Show',
			)
		);

		$this->assertSame( 'tv', traktivity_get_event( $tv_id )['type'] );
	}

	/**
	 * A season 0 special still gets an episode code.
	 *
	 * Specials live in season 0, so testing that the season number is above
	 * zero rather than that the term exists would drop the code for every one
	 * of them.
	 *
	 * The sync does not currently manage to attach a season 0 term at all
	 * (#702), so this covers the accessor rather than today's data. It should
	 * keep passing once that is fixed, which is the point of writing it now.
	 */
	public function test_season_zero_special_keeps_its_code() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 7 ),
			array(
				'trakt_show'    => 'Show With Specials',
				'trakt_season'  => '0',
				'trakt_episode' => '5',
			)
		);

		$this->assertSame( 'S0E5', traktivity_get_event( $post_id )['episode_code'] );
	}

	/**
	 * An event missing its season or episode gets no half-built code.
	 */
	public function test_partial_episode_data_produces_no_code() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 8 ),
			array(
				'trakt_show'   => 'Half A Show',
				'trakt_season' => '2',
			)
		);

		$event = traktivity_get_event( $post_id );

		$this->assertSame( 2, $event['season'] );
		$this->assertSame( 0, $event['episode'] );
		$this->assertSame( '', $event['episode_code'] );
	}

	/**
	 * An event with no image reports 0 rather than false.
	 */
	public function test_missing_image_reports_zero() {
		$post_id = $this->make_event( array( 'trakt_show_id' => 9 ) );

		$this->assertSame( 0, traktivity_get_event( $post_id )['image_id'] );
	}

	/**
	 * A post of another type gets the empty shape rather than a partial one.
	 */
	public function test_other_post_types_get_the_empty_shape() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'A blog post' ) );

		$this->assertSame( traktivity_empty_event_context(), traktivity_get_event( $post_id ) );
	}

	/**
	 * A post ID that does not exist is handled like any other miss.
	 */
	public function test_unknown_post_gets_the_empty_shape() {
		$this->assertSame( traktivity_empty_event_context(), traktivity_get_event( 999999 ) );
		$this->assertSame( traktivity_empty_event_context(), traktivity_get_event( 0 ) );
	}

	/**
	 * The context is filterable.
	 */
	public function test_context_is_filterable() {
		$post_id = $this->make_event( array( 'trakt_show_id' => 10 ) );

		add_filter(
			'traktivity_event_context',
			static function ( $context ) {
				$context['extra'] = 'added';
				return $context;
			}
		);

		$this->assertSame( 'added', traktivity_get_event( $post_id )['extra'] );
	}
}
