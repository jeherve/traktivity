<?php
/**
 * Tests for the Event Details block.
 *
 * @package Traktivity
 */

/**
 * Rendering the detail rows for one watch event.
 */
class EventDetailsBlockTest extends WP_UnitTestCase {

	/**
	 * Register the block from its built directory, if there is one.
	 */
	public function set_up() {
		parent::set_up();

		$built = TRAKTIVITY__PLUGIN_DIR . 'build/blocks/event-details';

		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'traktivity/event-details' ) && is_dir( $built ) ) {
			register_block_type( $built );
		}
	}

	/**
	 * Build an event the way the sync would.
	 *
	 * @param array $meta       Post meta.
	 * @param array $taxonomies Terms, keyed by taxonomy.
	 *
	 * @return int Post ID.
	 */
	private function make_event( array $meta, array $taxonomies = array() ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'  => 'traktivity_event',
				'post_title' => 'Episode Name',
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
	 * Render the details against a post.
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
				'blockName'    => 'traktivity/event-details',
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
	 * A full TV event gets every row.
	 */
	public function test_tv_event_rows() {
		$post_id = $this->make_event(
			array(
				'trakt_show_id'   => 1,
				'trakt_runtime'   => 60,
				'imdb_episode_id' => 'tt1234567',
			),
			array(
				'trakt_show'    => 'Some Show',
				'trakt_season'  => '3',
				'trakt_episode' => '2',
				'trakt_genre'   => 'Drama',
				'trakt_year'    => '2023',
			)
		);

		$html = $this->render( $post_id );

		$this->assertStringContainsString( 'Series', $html );
		$this->assertStringContainsString( 'Some Show', $html );
		$this->assertStringContainsString( 'Season', $html );
		$this->assertStringContainsString( 'Episode', $html );
		$this->assertStringContainsString( 'Drama', $html );
		$this->assertStringContainsString( '2023', $html );
		$this->assertStringContainsString( '60 minutes', $html );
		$this->assertStringContainsString( 'imdb.com', $html );
		$this->assertStringContainsString( '<dl', $html );
	}

	/**
	 * A movie gets no season or episode rows.
	 */
	public function test_movie_has_no_episode_rows() {
		$post_id = $this->make_event(
			array(
				'trakt_movie_id' => 2,
				'imdb_movie_id'  => 'tt7654321',
			),
			array( 'trakt_year' => '2019' )
		);

		$html = $this->render( $post_id );

		$this->assertStringNotContainsString( 'Season', $html );
		$this->assertStringNotContainsString( 'Series', $html );
		$this->assertStringContainsString( 'tt7654321', $html );
	}

	/**
	 * Rows with no data are left out rather than rendered empty.
	 *
	 * The Trakt row survives because trakt_show_id is what makes this a TV
	 * event in the first place, so every episode has at least that one link.
	 */
	public function test_rows_without_data_are_absent() {
		$post_id = $this->make_event( array( 'trakt_show_id' => 3 ) );

		$html = $this->render( $post_id );

		$this->assertStringNotContainsString( 'Runtime', $html );
		$this->assertStringNotContainsString( 'Genre', $html );
		$this->assertStringNotContainsString( 'Series', $html );
		$this->assertStringContainsString( 'Look up', $html );
	}

	/**
	 * With nothing left to say, the block renders nothing rather than an
	 * empty definition list.
	 */
	public function test_block_with_no_rows_renders_nothing() {
		$post_id = $this->make_event( array( 'trakt_show_id' => 4 ) );

		$this->assertSame( '', trim( $this->render( $post_id, array( 'showLinks' => false ) ) ) );
	}

	/**
	 * Each group of rows can be turned off.
	 */
	public function test_groups_can_be_hidden() {
		$post_id = $this->make_event(
			array(
				'trakt_show_id'   => 5,
				'trakt_runtime'   => 45,
				'imdb_episode_id' => 'tt1111111',
			),
			array( 'trakt_show' => 'Some Show' )
		);

		$no_links = $this->render( $post_id, array( 'showLinks' => false ) );
		$this->assertStringNotContainsString( 'imdb.com', $no_links );
		$this->assertStringContainsString( 'Some Show', $no_links );

		$no_runtime = $this->render( $post_id, array( 'showRuntime' => false ) );
		$this->assertStringNotContainsString( 'Runtime', $no_runtime );

		$no_tax = $this->render( $post_id, array( 'showTaxonomies' => false ) );
		$this->assertStringNotContainsString( 'Some Show', $no_tax );
		$this->assertStringContainsString( 'Runtime', $no_tax );
	}

	/**
	 * A runtime of one minute is singular.
	 */
	public function test_runtime_of_one_minute_is_singular() {
		$post_id = $this->make_event(
			array(
				'trakt_show_id' => 6,
				'trakt_runtime' => 1,
			)
		);

		$this->assertStringContainsString( '1 minute<', $this->render( $post_id ) );
	}

	/**
	 * A post that is not an event renders nothing.
	 */
	public function test_other_post_types_render_nothing() {
		$this->assertSame( '', trim( $this->render( self::factory()->post->create() ) ) );
	}
}
