<?php
/**
 * Tests for the Top Series block.
 *
 * @package Traktivity
 */

/**
 * Ordering series by how many episodes have been logged.
 */
class TopShowsBlockTest extends WP_UnitTestCase {

	/**
	 * Register the block from its built directory, if there is one.
	 */
	public function set_up() {
		parent::set_up();

		$built = TRAKTIVITY__PLUGIN_DIR . 'build/blocks/top-shows';

		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'traktivity/top-shows' ) && is_dir( $built ) ) {
			register_block_type( $built );
		}
	}

	/**
	 * Create a series with a number of logged episodes.
	 *
	 * @param string $name     Series name.
	 * @param int    $episodes How many episodes to log against it.
	 * @param array  $meta     Term meta to attach.
	 *
	 * @return int Term ID.
	 */
	private function make_show( $name, $episodes, array $meta = array() ) {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'trakt_show',
				'name'     => $name,
			)
		);

		for ( $i = 0; $i < $episodes; $i++ ) {
			$post_id = self::factory()->post->create(
				array(
					'post_type'   => 'traktivity_event',
					'post_status' => 'publish',
				)
			);
			update_post_meta( $post_id, 'trakt_show_id', $term->term_id );
			wp_set_object_terms( $post_id, array( $name ), 'trakt_show' );
		}

		foreach ( $meta as $key => $value ) {
			update_term_meta( $term->term_id, $key, $value );
		}

		return $term->term_id;
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
				'blockName'    => 'traktivity/top-shows',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);
	}

	/**
	 * Series come back ordered by episode count, most first.
	 */
	public function test_ordered_by_count() {
		$this->make_show( 'Fewer Episodes', 1 );
		$this->make_show( 'More Episodes', 3 );

		$html = $this->render();

		$this->assertLessThan(
			strpos( $html, 'Fewer Episodes' ),
			strpos( $html, 'More Episodes' ),
			'The series with more episodes comes first.'
		);
	}

	/**
	 * Ordering by name is available for an A to Z index.
	 */
	public function test_ordered_by_name() {
		$this->make_show( 'Zed Series', 5 );
		$this->make_show( 'Alpha Series', 1 );

		$html = $this->render( array( 'orderby' => 'name' ) );

		$this->assertLessThan(
			strpos( $html, 'Zed Series' ),
			strpos( $html, 'Alpha Series' ),
			'Ordering by name runs A to Z regardless of count.'
		);
	}

	/**
	 * The number of series is limited.
	 */
	public function test_number_limits_the_grid() {
		$this->make_show( 'One', 3 );
		$this->make_show( 'Two', 2 );
		$this->make_show( 'Three', 1 );

		$this->assertSame( 2, substr_count( $this->render( array( 'number' => 2 ) ), 'class="traktivity-show"' ) );
	}

	/**
	 * Zero means every series, which is what makes this usable as an index.
	 */
	public function test_zero_shows_everything() {
		$this->make_show( 'One', 3 );
		$this->make_show( 'Two', 2 );
		$this->make_show( 'Three', 1 );

		$this->assertSame( 3, substr_count( $this->render( array( 'number' => 0 ) ), 'class="traktivity-show"' ) );
	}

	/**
	 * Series with nothing logged are left out.
	 */
	public function test_empty_series_are_hidden() {
		$this->make_show( 'Watched', 2 );
		self::factory()->term->create(
			array(
				'taxonomy' => 'trakt_show',
				'name'     => 'Never Watched',
			)
		);

		$html = $this->render();

		$this->assertStringContainsString( 'Watched', $html );
		$this->assertStringNotContainsString( 'Never Watched', $html );
	}

	/**
	 * A series with no artwork gets the placeholder.
	 */
	public function test_missing_artwork_falls_back() {
		$this->make_show( 'No Artwork', 1 );

		$html = $this->render();

		$this->assertStringContainsString( 'traktivity-frame--empty', $html );
		$this->assertStringContainsString( 'No artwork', $html );
	}

	/**
	 * The network is printed when there is one, and skipped when there is not.
	 */
	public function test_network() {
		$this->make_show( 'With Network', 2, array( 'show_network' => 'Some Network' ) );

		$this->assertStringContainsString( 'Some Network', $this->render() );
		$this->assertStringNotContainsString( 'Some Network', $this->render( array( 'showNetwork' => false ) ) );
	}

	/**
	 * A series with no network leaves no empty paragraph behind.
	 */
	public function test_missing_network_is_not_rendered() {
		$this->make_show( 'No Network', 1 );

		$this->assertStringNotContainsString( 'traktivity-show__network', $this->render() );
	}

	/**
	 * Each series links to its archive.
	 */
	public function test_series_link_to_their_archive() {
		$term_id = $this->make_show( 'Linked Series', 1 );

		$this->assertStringContainsString( get_term_link( $term_id, 'trakt_show' ), $this->render() );
	}

	/**
	 * The episode count is pluralised.
	 */
	public function test_counts_are_pluralised() {
		$this->make_show( 'Just One', 1 );

		$html = $this->render();

		$this->assertStringContainsString( '1 episode', $html );
		$this->assertStringNotContainsString( '1 episodes', $html );
	}

	/**
	 * A site with no series renders nothing.
	 */
	public function test_no_series_renders_nothing() {
		$this->assertSame( '', trim( $this->render() ) );
	}

	/**
	 * The column count reaches the markup.
	 */
	public function test_columns() {
		$this->make_show( 'A Series', 1 );

		$html = $this->render( array( 'columns' => 3 ) );

		$this->assertStringContainsString( 'traktivity-shows--cols-3', $html );
		$this->assertStringContainsString( '--traktivity-columns:3', $html );
	}
}
