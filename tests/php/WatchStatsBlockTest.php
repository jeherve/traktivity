<?php
/**
 * Tests for the Watch Stats block.
 *
 * @package Traktivity
 */

/**
 * Rendering the headline figures.
 */
class WatchStatsBlockTest extends WP_UnitTestCase {

	/**
	 * Register the block and start from a cold stats cache.
	 */
	public function set_up() {
		parent::set_up();

		$built = TRAKTIVITY__PLUGIN_DIR . 'build/blocks/watch-stats';

		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'traktivity/watch-stats' ) && is_dir( $built ) ) {
			register_block_type( $built );
		}

		Traktivity_Stats::reset_flush_guard();
		Traktivity_Stats::flush( true );
	}

	/**
	 * Create a published event.
	 *
	 * @param string $id_key  Either trakt_show_id or trakt_movie_id.
	 * @param int    $runtime Minutes.
	 *
	 * @return int Post ID.
	 */
	private function make_event( $id_key, $runtime = 60 ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'traktivity_event',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $post_id, $id_key, 1 );
		update_post_meta( $post_id, 'trakt_runtime', $runtime );

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
				'blockName'    => 'traktivity/watch-stats',
				'attrs'        => $attrs,
				'innerHTML'    => '',
				'innerBlocks'  => array(),
				'innerContent' => array(),
			)
		);
	}

	/**
	 * A site with nothing logged renders nothing.
	 *
	 * A row of zeroes reads as broken rather than as empty, which is the
	 * wrong first impression for someone who has just activated the plugin.
	 */
	public function test_empty_site_renders_nothing() {
		$this->assertSame( '', trim( $this->render() ) );
	}

	/**
	 * The figures are rendered once there is something to count.
	 */
	public function test_figures_are_rendered() {
		// Two of each, so every label lands on its plural form.
		$this->make_event( 'trakt_show_id', 120 );
		$this->make_event( 'trakt_show_id', 120 );
		$this->make_event( 'trakt_movie_id', 120 );
		$this->make_event( 'trakt_movie_id', 120 );
		Traktivity_Stats::flush( true );

		$html = $this->render();

		$this->assertStringContainsString( 'traktivity-stats', $html );
		$this->assertStringContainsString( 'Hours', $html );
		$this->assertStringContainsString( 'Entries', $html );
		$this->assertStringContainsString( 'Episodes', $html );
		$this->assertStringContainsString( 'Films', $html );
		$this->assertStringContainsString( 'Series', $html );
		$this->assertStringContainsString( 'Logging since', $html );
	}

	/**
	 * Only the chosen figures appear, in the order they were chosen.
	 */
	public function test_figures_can_be_chosen_and_ordered() {
		$this->make_event( 'trakt_show_id' );
		$this->make_event( 'trakt_show_id' );
		Traktivity_Stats::flush( true );

		$html = $this->render( array( 'figures' => array( 'films', 'entries' ) ) );

		$this->assertStringNotContainsString( 'Episodes', $html );

		/*
		 * Both labels have to be there before their positions mean anything: a
		 * missing one makes strpos() return false, which compares as earlier
		 * than every real position and would pass the order check below on a
		 * block that rendered nothing.
		 */
		$this->assertStringContainsString( 'Films', $html );
		$this->assertStringContainsString( 'Entries', $html );

		$this->assertLessThan(
			strpos( $html, 'Entries' ),
			strpos( $html, 'Films' ),
			'Figures follow the order they were chosen in.'
		);
	}

	/**
	 * An unknown figure name is ignored rather than rendered empty.
	 */
	public function test_unknown_figures_are_ignored() {
		$this->make_event( 'trakt_show_id' );
		Traktivity_Stats::flush( true );

		$html = $this->render( array( 'figures' => array( 'entries', 'nonsense' ) ) );

		$this->assertStringContainsString( 'Entry', $html );
		$this->assertStringNotContainsString( 'nonsense', $html );
		$this->assertSame( 1, substr_count( $html, 'traktivity-stats__cell' ) );
	}

	/**
	 * Choosing no figures renders nothing, not an empty band.
	 */
	public function test_no_figures_renders_nothing() {
		$this->make_event( 'trakt_show_id' );
		Traktivity_Stats::flush( true );

		$this->assertSame( '', trim( $this->render( array( 'figures' => array() ) ) ) );
	}

	/**
	 * The layout attribute reaches the markup.
	 */
	public function test_layout() {
		$this->make_event( 'trakt_show_id' );
		Traktivity_Stats::flush( true );

		$this->assertStringContainsString( 'traktivity-stats--stack', $this->render( array( 'layout' => 'stack' ) ) );
		$this->assertStringContainsString( 'traktivity-stats--row', $this->render( array( 'layout' => 'row' ) ) );
	}

	/**
	 * Labels are singular where the count is one.
	 */
	public function test_labels_are_pluralised() {
		$this->make_event( 'trakt_show_id' );
		Traktivity_Stats::flush( true );

		$html = $this->render( array( 'figures' => array( 'entries' ) ) );

		$this->assertStringContainsString( 'Entry', $html );
		$this->assertStringNotContainsString( 'Entries', $html );
	}
}
