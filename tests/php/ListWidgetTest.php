<?php
/**
 * Tests for the event list widget.
 *
 * @package Traktivity
 */

/**
 * Episode details appended to widget titles.
 */
class ListWidgetTest extends WP_UnitTestCase {

	/**
	 * The widget under test.
	 *
	 * @var Traktivity_List_Widget
	 */
	private $widget;

	/**
	 * Stand up a widget instance.
	 */
	public function set_up() {
		parent::set_up();
		$this->widget = new Traktivity_List_Widget();
	}

	/**
	 * Build a TV event with the terms the widget needs.
	 *
	 * @param string $type_term Name to give the trakt_type term.
	 *
	 * @return int Post ID.
	 */
	private function make_tv_event( $type_term = 'TV Series' ) {
		$post_id = self::factory()->post->create( array( 'post_type' => 'traktivity_event' ) );

		update_post_meta( $post_id, 'trakt_show_id', 555 );
		wp_set_object_terms( $post_id, array( $type_term ), 'trakt_type' );
		wp_set_object_terms( $post_id, array( 'A Show' ), 'trakt_show' );
		wp_set_object_terms( $post_id, array( '4' ), 'trakt_season' );
		wp_set_object_terms( $post_id, array( '11' ), 'trakt_episode' );

		return $post_id;
	}

	/**
	 * A TV episode gets its show, season and episode appended.
	 */
	public function test_tv_episode_gets_its_details() {
		$title = $this->widget->custom_tv_event_title( 'Episode name', $this->make_tv_event() );

		$this->assertStringContainsString( 'episode-details', $title );
		$this->assertStringContainsString( 'A Show', $title );
		$this->assertStringContainsString( 'season 4', $title );
		$this->assertStringContainsString( 'episode 11', $title );
		$this->assertStringContainsString( 'Episode name', $title );
	}

	/**
	 * Details are appended on a site where trakt_type is translated.
	 *
	 * The old implementation tested has_term( 'TV Series', 'trakt_type' ), and
	 * the sync creates that term from a translated string, so every episode on
	 * a non-English install silently lost its details here.
	 */
	public function test_details_survive_a_translated_type_term() {
		$title = $this->widget->custom_tv_event_title( 'Episode name', $this->make_tv_event( 'Serie TV' ) );

		$this->assertStringContainsString( 'A Show', $title );
		$this->assertStringContainsString( 'season 4', $title );
	}

	/**
	 * A movie is handed back untouched.
	 */
	public function test_movie_title_is_left_alone() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'traktivity_event' ) );
		update_post_meta( $post_id, 'trakt_movie_id', 77 );

		$this->assertSame( 'A Film', $this->widget->custom_tv_event_title( 'A Film', $post_id ) );
	}

	/**
	 * An episode missing its numbers is handed back untouched.
	 */
	public function test_incomplete_episode_is_left_alone() {
		$post_id = self::factory()->post->create( array( 'post_type' => 'traktivity_event' ) );
		update_post_meta( $post_id, 'trakt_show_id', 556 );
		wp_set_object_terms( $post_id, array( 'A Show' ), 'trakt_show' );

		$this->assertSame( 'Episode name', $this->widget->custom_tv_event_title( 'Episode name', $post_id ) );
	}
}
