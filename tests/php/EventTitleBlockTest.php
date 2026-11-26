<?php
/**
 * Tests for the Event Title block.
 *
 * @package Traktivity
 */

/**
 * Rendering a heading that identifies what was watched.
 */
class EventTitleBlockTest extends WP_UnitTestCase {

	/**
	 * Register the block from its built directory, if there is one.
	 */
	public function set_up() {
		parent::set_up();

		$built = TRAKTIVITY__PLUGIN_DIR . 'build/blocks/event-title';

		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'traktivity/event-title' ) && is_dir( $built ) ) {
			register_block_type( $built );
		}
	}

	/**
	 * Build an event the way the sync would.
	 *
	 * @param array  $meta       Post meta.
	 * @param array  $taxonomies Terms, keyed by taxonomy.
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
	 * Render the title against a post.
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
				'blockName'    => 'traktivity/event-title',
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
	 * Build a full TV event.
	 *
	 * @return int Post ID.
	 */
	private function make_tv_event() {
		return $this->make_event(
			array( 'trakt_show_id' => 1 ),
			array(
				'trakt_show'    => 'Some Show',
				'trakt_season'  => '3',
				'trakt_episode' => '2',
			)
		);
	}

	/**
	 * A TV episode is identified in full.
	 */
	public function test_tv_title() {
		$html = $this->render( $this->make_tv_event() );

		$this->assertStringContainsString( 'Some Show', $html );
		$this->assertStringContainsString( 'S3E2', $html );
		$this->assertStringContainsString( 'Episode Name', $html );
		$this->assertStringContainsString( '<h1', $html );
	}

	/**
	 * The show name links to its archive by default.
	 */
	public function test_show_name_is_linked() {
		$this->assertStringContainsString( '<a href', $this->render( $this->make_tv_event() ) );
	}

	/**
	 * The show name link can be turned off without dropping the name.
	 */
	public function test_show_name_link_can_be_dropped() {
		$html = $this->render( $this->make_tv_event(), array( 'linkShowName' => false ) );

		$this->assertStringContainsString( 'Some Show', $html );
		$this->assertStringNotContainsString( '<a href', $html );
	}

	/**
	 * The show name can be dropped entirely.
	 */
	public function test_show_name_can_be_dropped() {
		$html = $this->render( $this->make_tv_event(), array( 'showShowName' => false ) );

		$this->assertStringNotContainsString( 'Some Show', $html );
		$this->assertStringContainsString( 'S3E2', $html );
	}

	/**
	 * A movie gets its plain title, with no leftover markup.
	 */
	public function test_movie_title() {
		$post_id = $this->make_event( array( 'trakt_movie_id' => 2 ), array(), 'A Film' );

		$html = $this->render( $post_id );

		$this->assertStringContainsString( 'A Film', $html );
		$this->assertStringNotContainsString( 'traktivity-event-title__code', $html );
		$this->assertStringNotContainsString( 'traktivity-event-title__show', $html );
	}

	/**
	 * The heading level is configurable and clamped.
	 */
	public function test_heading_level() {
		$post_id = $this->make_tv_event();

		$this->assertStringContainsString( '<h3', $this->render( $post_id, array( 'level' => 3 ) ) );
		$this->assertStringContainsString( '<h6', $this->render( $post_id, array( 'level' => 42 ) ) );
	}

	/**
	 * The title can link to the entry, for a listing.
	 */
	public function test_title_can_link_to_the_entry() {
		$post_id = $this->make_event( array( 'trakt_movie_id' => 3 ), array(), 'A Film' );

		$html = $this->render( $post_id, array( 'isLink' => true ) );

		$this->assertStringContainsString( get_permalink( $post_id ), $html );
	}

	/**
	 * An episode missing its numbers still names the show.
	 */
	public function test_partial_data_still_identifies_something() {
		$post_id = $this->make_event(
			array( 'trakt_show_id' => 4 ),
			array( 'trakt_show' => 'Some Show' )
		);

		$html = $this->render( $post_id );

		$this->assertStringContainsString( 'Some Show', $html );
		$this->assertStringNotContainsString( 'traktivity-event-title__code', $html );
	}

	/**
	 * A post that is not an event renders nothing.
	 */
	public function test_other_post_types_render_nothing() {
		$this->assertSame( '', trim( $this->render( self::factory()->post->create() ) ) );
	}
}
