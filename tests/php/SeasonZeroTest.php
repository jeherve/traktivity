<?php
/**
 * Regression test for season 0 specials.
 *
 * @package Traktivity
 */

/**
 * Attaching a season term when the season number is zero.
 *
 * A show's specials live in season 0. The sync used to hand the bare string
 * "0" to wp_set_object_terms(), which normalises its term list with empty(),
 * and empty( "0" ) is true in PHP. The list became empty, and with $append set
 * an empty list is a no-op, so no season term was ever attached and nothing
 * errored.
 *
 * See issue #702.
 */
class SeasonZeroTest extends WP_UnitTestCase {

	/**
	 * Season number the fake Trakt.tv reports for its one episode.
	 *
	 * @var int
	 */
	private $season = 0;

	/**
	 * Point the plugin at a username and key, and swap Trakt.tv for the fake.
	 *
	 * No TMDb key is set, so the sync makes no artwork requests.
	 */
	public function set_up() {
		parent::set_up();

		update_option(
			'traktivity',
			array(
				'username' => 'jeherve',
				'api_key'  => 'test-key',
			)
		);

		add_filter( 'pre_http_request', array( $this, 'fake_trakt' ), 10, 3 );
	}

	/**
	 * Drop the fake so it cannot leak into other tests.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_trakt' ), 10 );

		parent::tear_down();
	}

	/**
	 * Stand in for Trakt.tv's /users/:id/history endpoint, with one episode.
	 *
	 * The event has the shape Trakt.tv returns with extended=full, trimmed to
	 * the fields the sync reads. The show's runtime is zero so the sync does
	 * not go on to ask Trakt.tv for the rest of the show's episodes.
	 *
	 * @param false|array|WP_Error $response Preempted response. False to let the request through.
	 * @param array                $args     Request arguments.
	 * @param string               $url      Request URL.
	 *
	 * @return false|array Fake response, or the original for anything that is not Trakt.tv.
	 */
	public function fake_trakt( $response, $args, $url ) {
		if ( false === strpos( $url, 'api.trakt.tv' ) ) {
			return $response;
		}

		$event = array(
			'id'         => 9001,
			'watched_at' => '2026-03-04T20:00:00.000Z',
			'type'       => 'episode',
			'episode'    => array(
				'season'   => $this->season,
				'number'   => 5,
				'title'    => 'A Special',
				'overview' => 'Something extra.',
				'ids'      => array(
					'trakt' => 77,
					'imdb'  => 'tt0000077',
					'tmdb'  => 777,
				),
			),
			'show'       => array(
				'title'    => 'Severance',
				'year'     => 2022,
				'overview' => 'Work and life, split in two.',
				'network'  => 'Apple TV',
				'runtime'  => 0,
				'genres'   => array( 'drama', 'science-fiction' ),
				'ids'      => array(
					'trakt' => 11,
					'imdb'  => 'tt11280740',
					'tmdb'  => 95396,
				),
			),
		);

		return array(
			'headers'  => new WpOrg\Requests\Utility\CaseInsensitiveDictionary( array() ),
			'body'     => wp_json_encode( array( $event ) ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	}

	/**
	 * Run the sync against the fake, and read back the event it created.
	 *
	 * @param int $season Season number for the fake episode.
	 *
	 * @return int Post ID of the imported event.
	 */
	private function sync_one_episode( $season ) {
		$this->season = $season;

		( new Traktivity_Calls() )->publish_event();

		$posts = get_posts(
			array(
				'post_type'   => 'traktivity_event',
				'post_status' => 'any',
				'fields'      => 'ids',
			)
		);

		$this->assertCount( 1, $posts, 'The sync should have imported the one episode.' );

		return $posts[0];
	}

	/**
	 * The sync attaches the season term, including season 0.
	 *
	 * @dataProvider data_seasons
	 *
	 * @param int    $season   Season number Trakt.tv reports.
	 * @param string $expected Season term name.
	 */
	public function test_sync_attaches_the_season( $season, $expected ) {
		$post_id = $this->sync_one_episode( $season );

		$this->assertSame( array( $expected ), wp_list_pluck( get_the_terms( $post_id, 'trakt_season' ), 'name' ) );
	}

	/**
	 * Seasons to sync.
	 *
	 * @return array[]
	 */
	public function data_seasons() {
		return array(
			'specials'       => array( 0, '0' ),
			'ordinary event' => array( 3, '3' ),
		);
	}

	/**
	 * Genres, which arrive as a list rather than a single value, still attach.
	 */
	public function test_sync_attaches_every_genre() {
		$post_id = $this->sync_one_episode( 1 );

		$names = wp_list_pluck( get_the_terms( $post_id, 'trakt_genre' ), 'name' );
		sort( $names );

		$this->assertSame( array( 'Drama', 'Science-fiction' ), $names );
	}

	/**
	 * The bare string reproduces the original bug, so this test would have
	 * caught it.
	 */
	public function test_the_bare_string_is_what_used_to_fail() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'traktivity_event',
				'post_status' => 'publish',
			)
		);

		wp_set_object_terms( $post_id, '0', 'trakt_season', true );

		$this->assertFalse(
			get_the_terms( $post_id, 'trakt_season' ),
			'wp_set_object_terms() still swallows a bare "0", which is why the cast is there.'
		);
	}

	/**
	 * Once the term exists, the accessor composes the code for it.
	 */
	public function test_season_zero_gets_an_episode_code() {
		$post_id = $this->sync_one_episode( 0 );

		$this->assertSame( 'S0E5', traktivity_get_event( $post_id )['episode_code'] );
	}
}
