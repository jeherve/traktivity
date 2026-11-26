<?php
/**
 * Tests for the Latest Watch block.
 *
 * @package Traktivity
 */

/**
 * Showing the most recent thing watched.
 */
class LatestWatchBlockTest extends WP_UnitTestCase {

	/**
	 * Register the block from its built directory, if there is one.
	 */
	public function set_up() {
		parent::set_up();

		$built = TRAKTIVITY__PLUGIN_DIR . 'build/blocks/latest-watch';

		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'traktivity/latest-watch' ) && is_dir( $built ) ) {
			register_block_type( $built );
		}
	}

	/**
	 * Create a published event.
	 *
	 * @param string $id_key    Either trakt_show_id or trakt_movie_id.
	 * @param string $date      Post date.
	 * @param string $title     Post title.
	 * @param bool   $has_image Whether to attach a featured image.
	 *
	 * @return int Post ID.
	 */
	private function make_event( $id_key, $date, $title, $has_image = false ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'traktivity_event',
				'post_status' => 'publish',
				'post_date'   => $date,
				'post_title'  => $title,
			)
		);

		update_post_meta( $post_id, $id_key, 1 );

		if ( $has_image ) {
			$attachment = self::factory()->attachment->create_object(
				array(
					'file'           => 'still.jpg',
					'post_parent'    => $post_id,
					'post_mime_type' => 'image/jpeg',
				)
			);
			set_post_thumbnail( $post_id, $attachment );
		}

		return $post_id;
	}

	/**
	 * Render the block.
	 *
	 * @param array $attrs Block attributes.
	 *
	 * @return string Rendered HTML.
	 */
	private function render( array $attrs = array() ) {
		return render_block(
			array(
				'blockName'    => 'traktivity/latest-watch',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);
	}

	/**
	 * The most recent event is the one rendered.
	 */
	public function test_most_recent_event_is_shown() {
		$this->make_event( 'trakt_show_id', '2022-01-01 10:00:00', 'Older Episode' );
		$this->make_event( 'trakt_show_id', '2026-03-04 20:00:00', 'Newer Episode' );

		$html = $this->render();

		$this->assertStringContainsString( 'Newer Episode', $html );
		$this->assertStringNotContainsString( 'Older Episode', $html );
	}

	/**
	 * An entry with artwork wins over a newer one without.
	 *
	 * A large blank where the artwork should be reads as broken, and TMDb has
	 * no image for plenty of entries.
	 */
	public function test_entry_with_artwork_is_preferred() {
		$this->make_event( 'trakt_show_id', '2026-01-01 10:00:00', 'Has Artwork', true );
		$this->make_event( 'trakt_show_id', '2026-03-04 20:00:00', 'No Artwork' );

		$html = $this->render();

		$this->assertStringContainsString( 'Has Artwork', $html );
		$this->assertStringNotContainsString( 'No Artwork<', $html );
	}

	/**
	 * With no artwork anywhere, the newest entry is shown regardless.
	 */
	public function test_falls_back_when_nothing_has_artwork() {
		$this->make_event( 'trakt_show_id', '2026-03-04 20:00:00', 'Newest Episode' );

		$html = $this->render();

		$this->assertStringContainsString( 'Newest Episode', $html );
		$this->assertStringContainsString( 'traktivity-frame--empty', $html );
	}

	/**
	 * The preference can be turned off, giving the newest entry outright.
	 */
	public function test_preference_can_be_turned_off() {
		$this->make_event( 'trakt_show_id', '2026-01-01 10:00:00', 'Has Artwork', true );
		$this->make_event( 'trakt_show_id', '2026-03-04 20:00:00', 'Newest Episode' );

		$html = $this->render( array( 'preferWithImage' => false ) );

		$this->assertStringContainsString( 'Newest Episode', $html );
	}

	/**
	 * The block can be restricted to TV or to films.
	 */
	public function test_type_can_be_restricted() {
		$this->make_event( 'trakt_show_id', '2026-01-01 10:00:00', 'An Episode' );
		$this->make_event( 'trakt_movie_id', '2026-03-04 20:00:00', 'A Film' );

		$tv = $this->render( array( 'type' => 'tv' ) );
		$this->assertStringContainsString( 'An Episode', $tv );
		$this->assertStringNotContainsString( 'A Film', $tv );

		$films = $this->render( array( 'type' => 'movie' ) );
		$this->assertStringContainsString( 'A Film', $films );
		$this->assertStringNotContainsString( 'An Episode', $films );
	}

	/**
	 * A site with nothing logged renders nothing.
	 */
	public function test_empty_site_renders_nothing() {
		$this->assertSame( '', trim( $this->render() ) );
	}

	/**
	 * A type with nothing logged renders nothing rather than the wrong thing.
	 */
	public function test_empty_type_renders_nothing() {
		$this->make_event( 'trakt_show_id', '2026-03-04 20:00:00', 'An Episode' );

		$this->assertSame( '', trim( $this->render( array( 'type' => 'movie' ) ) ) );
	}

	/**
	 * The label above the entry is optional.
	 */
	public function test_kicker() {
		$this->make_event( 'trakt_show_id', '2026-03-04 20:00:00', 'An Episode' );

		$this->assertStringContainsString( 'Just watched', $this->render( array( 'kicker' => 'Just watched' ) ) );
		$this->assertStringNotContainsString( 'traktivity-hero__kicker', $this->render() );
	}

	/**
	 * The image link is skipped by the keyboard, since the title repeats it.
	 */
	public function test_image_link_is_not_a_second_tab_stop() {
		$this->make_event( 'trakt_show_id', '2026-03-04 20:00:00', 'An Episode' );

		$html = $this->render();

		$this->assertStringContainsString( 'tabindex="-1"', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
	}

	/**
	 * The heading level is configurable and clamped.
	 */
	public function test_heading_level() {
		$this->make_event( 'trakt_show_id', '2026-03-04 20:00:00', 'An Episode' );

		$this->assertStringContainsString( '<h4', $this->render( array( 'headingLevel' => 4 ) ) );
		$this->assertStringContainsString( '<h6', $this->render( array( 'headingLevel' => 88 ) ) );
	}
}
