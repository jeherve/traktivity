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
	 * Attach terms the way the sync does, and read them back.
	 *
	 * @param mixed $value What the sync would pass for the season.
	 *
	 * @return string[] Term names attached.
	 */
	private function attach_season( $value ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'traktivity_event',
				'post_status' => 'publish',
			)
		);

		wp_set_object_terms( $post_id, (array) $value, 'trakt_season', true );

		$terms = get_the_terms( $post_id, 'trakt_season' );

		return is_wp_error( $terms ) || empty( $terms ) ? array() : wp_list_pluck( $terms, 'name' );
	}

	/**
	 * A season 0 special gets its season term.
	 */
	public function test_season_zero_is_attached() {
		$this->assertSame( array( '0' ), $this->attach_season( '0' ) );
	}

	/**
	 * Ordinary seasons keep working.
	 */
	public function test_ordinary_seasons_still_work() {
		$this->assertSame( array( '3' ), $this->attach_season( '3' ) );
	}

	/**
	 * Values that arrive as an array, like genres, are unaffected.
	 */
	public function test_array_values_are_unaffected() {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'traktivity_event',
				'post_status' => 'publish',
			)
		);

		wp_set_object_terms( $post_id, (array) array( 'Drama', 'Science Fiction' ), 'trakt_genre', true );

		$names = wp_list_pluck( get_the_terms( $post_id, 'trakt_genre' ), 'name' );
		sort( $names );

		$this->assertSame( array( 'Drama', 'Science Fiction' ), $names );
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
		$post_id = self::factory()->post->create( array( 'post_type' => 'traktivity_event' ) );

		update_post_meta( $post_id, 'trakt_show_id', 1 );
		wp_set_object_terms( $post_id, array( 'Some Show' ), 'trakt_show' );
		wp_set_object_terms( $post_id, (array) '0', 'trakt_season' );
		wp_set_object_terms( $post_id, (array) '5', 'trakt_episode' );

		$this->assertSame( 'S0E5', traktivity_get_event( $post_id )['episode_code'] );
	}
}
