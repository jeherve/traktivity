<?php
/**
 * Tests for the Series Header block.
 *
 * @package Traktivity
 */

/**
 * Describing the series whose archive is being viewed.
 */
class ShowHeaderBlockTest extends WP_UnitTestCase {

	/**
	 * Register the block from its built directory, if there is one.
	 */
	public function set_up() {
		parent::set_up();

		$built = TRAKTIVITY__PLUGIN_DIR . 'build/blocks/show-header';

		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'traktivity/show-header' ) && is_dir( $built ) ) {
			register_block_type( $built );
		}
	}

	/**
	 * Create a series with episodes logged against it.
	 *
	 * @param string $name        Series name.
	 * @param int    $episodes    How many episodes to log.
	 * @param array  $meta        Term meta.
	 * @param string $description Term description.
	 *
	 * @return WP_Term The series.
	 */
	private function make_show( $name, $episodes = 2, array $meta = array(), $description = '' ) {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy'    => 'trakt_show',
				'name'        => $name,
				'description' => $description,
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

		return get_term( $term->term_id, 'trakt_show' );
	}

	/**
	 * Render the block as if on that series' archive.
	 *
	 * @param WP_Term|null $term  Series being viewed, or null for no archive.
	 * @param array        $attrs Block attributes.
	 *
	 * @return string Rendered HTML.
	 */
	private function render( $term, array $attrs = array() ) {
		$previous = $GLOBALS['wp_query'];

		$GLOBALS['wp_query'] = new WP_Query();

		if ( $term instanceof WP_Term ) {
			$GLOBALS['wp_query']->is_tax            = true;
			$GLOBALS['wp_query']->queried_object    = $term;
			$GLOBALS['wp_query']->queried_object_id = $term->term_id;
		}

		$html = render_block(
			array(
				'blockName'    => 'traktivity/show-header',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);

		$GLOBALS['wp_query'] = $previous;

		return $html;
	}

	/**
	 * A series archive gets a full header.
	 */
	public function test_full_header() {
		$term = $this->make_show(
			'Some Series',
			3,
			array(
				'show_network' => 'Some Network',
				'show_runtime' => 180,
			),
			'A description of the series.'
		);

		$html = $this->render( $term );

		$this->assertStringContainsString( 'Some Series', $html );
		$this->assertStringContainsString( 'Some Network', $html );
		$this->assertStringContainsString( '3 episodes watched', $html );
		$this->assertStringContainsString( '3 hours', $html );
		$this->assertStringContainsString( 'A description of the series.', $html );
		$this->assertStringContainsString( '<h1', $html );
	}

	/**
	 * Anywhere that is not a series archive renders nothing.
	 */
	public function test_renders_nothing_off_a_series_archive() {
		$this->make_show( 'Some Series' );

		$this->assertSame( '', trim( $this->render( null ) ) );
	}

	/**
	 * Another taxonomy's archive renders nothing either.
	 */
	public function test_renders_nothing_on_another_taxonomy() {
		$genre = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'trakt_genre',
				'name'     => 'Drama',
			)
		);

		$this->assertSame( '', trim( $this->render( $genre ) ) );
	}

	/**
	 * A series with nothing but a name still renders.
	 */
	public function test_bare_series() {
		$term = $this->make_show( 'Bare Series', 1 );

		$html = $this->render( $term );

		$this->assertStringContainsString( 'Bare Series', $html );
		$this->assertStringContainsString( '1 episode watched', $html );
		$this->assertStringContainsString( 'traktivity-frame--empty', $html );
		$this->assertStringNotContainsString( 'traktivity-showhead__network', $html );
		$this->assertStringNotContainsString( 'traktivity-showhead__synopsis', $html );
	}

	/**
	 * Each part can be turned off.
	 */
	public function test_parts_can_be_hidden() {
		$term = $this->make_show(
			'Some Series',
			2,
			array(
				'show_network' => 'Some Network',
				'show_runtime' => 120,
			),
			'A description.'
		);

		$this->assertStringNotContainsString( 'Some Network', $this->render( $term, array( 'showNetwork' => false ) ) );
		$this->assertStringNotContainsString( 'A description.', $this->render( $term, array( 'showSynopsis' => false ) ) );
		$this->assertStringNotContainsString( 'episodes watched', $this->render( $term, array( 'showStats' => false ) ) );
		$this->assertStringNotContainsString( 'traktivity-frame', $this->render( $term, array( 'showImage' => false ) ) );
	}

	/**
	 * External links are off by default and available on request.
	 */
	public function test_links() {
		$term = $this->make_show(
			'Linked Series',
			1,
			array(
				'show_external_ids' => array(
					'trakt' => 111,
					'imdb'  => 'tt1234567',
				),
			)
		);

		$this->assertStringNotContainsString( 'imdb.com', $this->render( $term ) );
		$this->assertStringContainsString( 'imdb.com', $this->render( $term, array( 'showLinks' => true ) ) );
	}

	/**
	 * The heading level is configurable and clamped.
	 */
	public function test_heading_level() {
		$term = $this->make_show( 'Some Series', 1 );

		$this->assertStringContainsString( '<h2', $this->render( $term, array( 'headingLevel' => 2 ) ) );
		$this->assertStringContainsString( '<h6', $this->render( $term, array( 'headingLevel' => 77 ) ) );
	}

	/**
	 * A synopsis someone has formatted keeps its formatting.
	 *
	 * The sync writes plain text, but WordPress allows limited markup in a
	 * term description and a site owner may well have edited one.
	 */
	public function test_synopsis_keeps_safe_markup() {
		$term = $this->make_show( 'Formatted Series', 1, array(), 'A <em>formatted</em> description.' );

		$html = $this->render( $term );

		$this->assertStringContainsString( '<em>formatted</em>', $html );
	}
}
