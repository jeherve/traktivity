<?php
/**
 * Tests for the Event Card block.
 *
 * @package Traktivity
 */

/**
 * Rendering one watch event as a card.
 */
class EventCardBlockTest extends WP_UnitTestCase {

	/**
	 * Register the block from its built directory, if there is one.
	 */
	public function set_up() {
		parent::set_up();

		$built = TRAKTIVITY__PLUGIN_DIR . 'build/blocks/event-card';

		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'traktivity/event-card' ) && is_dir( $built ) ) {
			register_block_type( $built );
		}
	}

	/**
	 * Build an event the way the sync would.
	 *
	 * @param array $meta       Post meta.
	 * @param array $taxonomies Terms, keyed by taxonomy.
	 * @param array $postarr    Overrides for the post.
	 *
	 * @return int Post ID.
	 */
	private function make_event( array $meta, array $taxonomies = array(), array $postarr = array() ) {
		$post_id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'  => 'traktivity_event',
					'post_title' => 'Episode Name',
				),
				$postarr
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
	 * Render the card against a post.
	 *
	 * Render_block() takes block context from the global post rather than from
	 * the parsed block, so the post has to be staged there.
	 *
	 * @param int   $post_id Post to render against.
	 * @param array $attrs   Block attributes.
	 *
	 * @return string Rendered HTML.
	 */
	private function render( $post_id, array $attrs = array() ) {
		$previous        = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$GLOBALS['post'] = get_post( $post_id );

		$html = render_block(
			array(
				'blockName'    => 'traktivity/event-card',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);

		$GLOBALS['post'] = $previous;

		return $html;
	}

	/**
	 * A TV episode shows its show, code, title and runtime.
	 */
	public function test_tv_card() {
		$post_id = $this->make_event(
			array(
				'trakt_show_id' => 1,
				'trakt_runtime' => 60,
			),
			array(
				'trakt_show'    => 'Some Show',
				'trakt_season'  => '3',
				'trakt_episode' => '2',
			)
		);

		$html = $this->render( $post_id );

		$this->assertStringContainsString( 'Some Show', $html );
		$this->assertStringContainsString( 'S3E2', $html );
		$this->assertStringContainsString( 'Episode Name', $html );
		$this->assertStringContainsString( '60 min', $html );
		$this->assertStringContainsString( 'traktivity-card--tv', $html );
	}

	/**
	 * A movie shows its year and no episode code.
	 */
	public function test_movie_card() {
		$post_id = $this->make_event(
			array(
				'trakt_movie_id' => 2,
				'trakt_runtime'  => 105,
			),
			array( 'trakt_year' => '2019' ),
			array( 'post_title' => 'A Film' )
		);

		$html = $this->render( $post_id );

		$this->assertStringContainsString( 'A Film', $html );
		$this->assertStringContainsString( '2019', $html );
		$this->assertStringContainsString( 'traktivity-card--movie', $html );
		$this->assertStringNotContainsString( 'traktivity-card__code', $html );
		$this->assertStringNotContainsString( 'S0E0', $html );
	}

	/**
	 * An event with no artwork gets the placeholder rather than a broken frame.
	 */
	public function test_missing_artwork_falls_back() {
		$post_id = $this->make_event( array( 'trakt_show_id' => 3 ) );

		$html = $this->render( $post_id );

		$this->assertStringContainsString( 'traktivity-frame--empty', $html );
		$this->assertStringContainsString( 'No artwork', $html );
		$this->assertStringNotContainsString( '<img', $html );
	}

	/**
	 * The image link is skipped by the keyboard.
	 *
	 * It points at the same place as the title, so without this every card
	 * costs two tab stops for one destination.
	 */
	public function test_image_link_is_not_a_second_tab_stop() {
		$post_id = $this->make_event( array( 'trakt_show_id' => 4 ) );

		$html = $this->render( $post_id );

		$this->assertStringContainsString( 'tabindex="-1"', $html );
		$this->assertStringContainsString( 'aria-hidden="true"', $html );
	}

	/**
	 * Artwork can be turned off entirely.
	 */
	public function test_artwork_can_be_hidden() {
		$post_id = $this->make_event( array( 'trakt_show_id' => 5 ) );

		$html = $this->render( $post_id, array( 'showImage' => false ) );

		$this->assertStringNotContainsString( 'traktivity-frame', $html );
		$this->assertStringContainsString( 'Episode Name', $html );
	}

	/**
	 * The heading level is configurable, and clamped to something valid.
	 */
	public function test_heading_level() {
		$post_id = $this->make_event( array( 'trakt_show_id' => 6 ) );

		$this->assertStringContainsString( '<h2 class="traktivity-card__title"', $this->render( $post_id, array( 'headingLevel' => 2 ) ) );
		$this->assertStringContainsString( '<h6 class="traktivity-card__title"', $this->render( $post_id, array( 'headingLevel' => 99 ) ) );
		$this->assertStringContainsString( '<h1 class="traktivity-card__title"', $this->render( $post_id, array( 'headingLevel' => -4 ) ) );
	}

	/**
	 * The show name is dropped on that show's own archive.
	 *
	 * Every card there would otherwise repeat the same name.
	 */
	public function test_show_name_dropped_on_a_show_archive() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 7 ),
			array(
				'trakt_show'    => 'Some Show',
				'trakt_season'  => '1',
				'trakt_episode' => '1',
			)
		);

		$term = get_term_by( 'name', 'Some Show', 'trakt_show' );

		$previous_query                         = $GLOBALS['wp_query'];
		$GLOBALS['wp_query']                    = new WP_Query();
		$GLOBALS['wp_query']->is_tax            = true;
		$GLOBALS['wp_query']->queried_object    = $term;
		$GLOBALS['wp_query']->queried_object_id = $term->term_id;

		$html = $this->render( $post_id );

		$GLOBALS['wp_query'] = $previous_query;

		$this->assertStringNotContainsString( 'traktivity-card__show', $html );
		$this->assertStringContainsString( 'S1E1', $html );
	}

	/**
	 * A post that is not an event renders nothing.
	 */
	public function test_other_post_types_render_nothing() {
		$post_id = self::factory()->post->create();

		$this->assertSame( '', trim( $this->render( $post_id ) ) );
	}

	/**
	 * An empty summary does not leave an empty paragraph behind.
	 */
	public function test_empty_excerpt_is_not_rendered() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 8 ),
			array(),
			array(
				'post_content' => '',
				'post_excerpt' => '',
			)
		);

		$this->assertStringNotContainsString( 'traktivity-card__excerpt', $this->render( $post_id, array( 'showExcerpt' => true ) ) );
	}
}
