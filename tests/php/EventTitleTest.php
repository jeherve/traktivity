<?php
/**
 * Tests for the composed event title.
 *
 * @package Traktivity
 */

/**
 * Composing a title that identifies what was watched.
 */
class EventTitleTest extends WP_UnitTestCase {

	/**
	 * Build an event the way the sync would.
	 *
	 * @param array  $meta       Post meta to attach.
	 * @param array  $taxonomies Terms to attach, keyed by taxonomy.
	 * @param string $title      Post title.
	 *
	 * @return int Post ID.
	 */
	private function make_event( array $meta, array $taxonomies = array(), $title = 'Episode Name' ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => 'traktivity_event',
				'post_title' => $title,
			)
		);

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
	 * A TV episode gets its show and episode code.
	 */
	public function test_tv_episode_is_identified() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 1 ),
			array(
				'trakt_show'    => 'Some Show',
				'trakt_season'  => '3',
				'trakt_episode' => '2',
			)
		);

		$this->assertSame( 'Some Show S3E2: Episode Name', traktivity_get_event_title( $post_id ) );
	}

	/**
	 * The show name can be left out, for a listing that already names it.
	 */
	public function test_show_name_can_be_dropped() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 2 ),
			array(
				'trakt_show'    => 'Some Show',
				'trakt_season'  => '1',
				'trakt_episode' => '4',
			)
		);

		$this->assertSame( 'S1E4: Episode Name', traktivity_get_event_title( $post_id, false ) );
	}

	/**
	 * A movie keeps its plain title, with nothing bolted on.
	 */
	public function test_movie_keeps_its_title() {
		$post_id = $this->make_event( array( 'trakt_movie_id' => 3 ), array(), 'A Film' );

		$this->assertSame( 'A Film', traktivity_get_event_title( $post_id ) );
	}

	/**
	 * An episode with no season or episode terms still names its show.
	 *
	 * Each part is dropped on its own, so partial data degrades to something
	 * useful rather than falling back to the bare episode name.
	 */
	public function test_missing_numbers_still_names_the_show() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 4 ),
			array( 'trakt_show' => 'Some Show' )
		);

		$this->assertSame( 'Some Show: Episode Name', traktivity_get_event_title( $post_id ) );
	}

	/**
	 * An episode with no show still prints its episode code.
	 */
	public function test_missing_show_still_prints_the_code() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 5 ),
			array(
				'trakt_season'  => '2',
				'trakt_episode' => '7',
			)
		);

		$this->assertSame( 'S2E7: Episode Name', traktivity_get_event_title( $post_id ) );
	}

	/**
	 * An episode with neither falls back to the bare title, with no stray colon.
	 */
	public function test_no_extra_information_leaves_the_title_alone() {
		$post_id = $this->make_event( array( 'trakt_show_id' => 6 ) );

		$this->assertSame( 'Episode Name', traktivity_get_event_title( $post_id ) );
	}

	/**
	 * A post of another type is handled without composing anything.
	 */
	public function test_other_post_types_get_their_own_title() {
		$post_id = self::factory()->post->create( array( 'post_title' => 'A blog post' ) );

		$this->assertSame( 'A blog post', traktivity_get_event_title( $post_id ) );
	}

	/**
	 * A season 0 special is identified like any other episode.
	 */
	public function test_season_zero_special_is_identified() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 7 ),
			array(
				'trakt_show'    => 'Some Show',
				'trakt_season'  => '0',
				'trakt_episode' => '3',
			)
		);

		$this->assertSame( 'Some Show S0E3: Episode Name', traktivity_get_event_title( $post_id ) );
	}

	/**
	 * The composed title is filterable.
	 */
	public function test_title_is_filterable() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 8 ),
			array( 'trakt_show' => 'Some Show' )
		);

		add_filter(
			'traktivity_event_title_text',
			static function ( $title, $event ) {
				return $event['show_name'] . ' | ' . $title;
			},
			10,
			2
		);

		$this->assertSame( 'Some Show | Some Show: Episode Name', traktivity_get_event_title( $post_id ) );
	}

	/**
	 * Nothing here filters the_title.
	 *
	 * Composing titles site-wide would change the admin list, feeds and
	 * anything else calling get_the_title(). That was decided against on #683,
	 * so the helper and the block are the only ways in.
	 */
	public function test_the_title_is_left_untouched() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 9 ),
			array(
				'trakt_show'    => 'Some Show',
				'trakt_season'  => '1',
				'trakt_episode' => '1',
			)
		);

		$this->assertSame( 'Episode Name', get_the_title( $post_id ) );
	}
}
