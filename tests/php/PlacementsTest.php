<?php
/**
 * Tests for where the plugin puts its blocks and parts.
 *
 * @package Traktivity
 */

/**
 * Placing blocks and parts on the front end.
 *
 * Core's Template Part block cannot resolve a part a plugin provides, so
 * nothing here relies on it. Blocks travel as hooked blocks and parts are
 * rendered by the plugin at an action, which is how Jetpack's subscribe
 * placements work.
 */
class PlacementsTest extends WP_UnitTestCase {

	/**
	 * Theme active before a test switched it.
	 *
	 * @var string
	 */
	private $previous_theme = '';

	/**
	 * Start each test with nothing switched on.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Traktivity_Templates::OPTION );
	}

	/**
	 * Put any switched theme back.
	 */
	public function tear_down() {
		if ( '' !== $this->previous_theme ) {
			switch_theme( $this->previous_theme );
			$this->previous_theme = '';
		}

		parent::tear_down();
	}

	/**
	 * Switch to a block theme, since hooked blocks need templates.
	 *
	 * @return bool Whether a block theme was available.
	 */
	private function use_block_theme() {
		if ( ! wp_get_theme( 'twentytwentyfour' )->exists() ) {
			return false;
		}

		$this->previous_theme = get_stylesheet();
		switch_theme( 'twentytwentyfour' );

		return true;
	}

	/**
	 * Switch placements on.
	 *
	 * @param string[] $slugs Placement slugs.
	 */
	private function enable( array $slugs ) {
		update_option( Traktivity_Templates::OPTION, array_fill_keys( $slugs, true ) );
	}

	/**
	 * Build the context a hooked block filter receives for one template.
	 *
	 * @param string $slug Template slug.
	 *
	 * @return WP_Block_Template
	 */
	private function template_context( $slug ) {
		$template       = new WP_Block_Template();
		$template->slug = $slug;
		$template->type = 'wp_template';

		return $template;
	}

	/**
	 * Every placement names a block or a part that actually exists.
	 *
	 * A placement pointing at a renamed block would insert a block error, and
	 * one pointing at a missing part would render nothing, neither of which
	 * fails loudly on its own.
	 *
	 * @dataProvider data_placements
	 *
	 * @param string $slug      Placement slug.
	 * @param array  $placement Placement definition.
	 */
	public function test_placement_targets_exist( $slug, $placement ) {
		if ( 'block' === $placement['type'] ) {
			$built = TRAKTIVITY__PLUGIN_DIR . 'build/blocks/' . str_replace( 'traktivity/', '', $placement['block'] );

			$this->assertDirectoryExists( $built, "{$slug} names a block with no built directory." );
			return;
		}

		$parts = Traktivity_Templates::available_parts();

		$this->assertArrayHasKey( $placement['part'], $parts, "{$slug} names a part that does not exist." );
		$this->assertNotSame( '', Traktivity_Templates::content( $parts[ $placement['part'] ]['file'] ) );
	}

	/**
	 * Every placement the plugin offers.
	 *
	 * @return array<string, array{string, array}>
	 */
	public function data_placements() {
		$cases = array();

		foreach ( Traktivity_Placements::available() as $slug => $placement ) {
			$cases[ $slug ] = array( $slug, $placement );
		}

		return $cases;
	}

	/**
	 * Nothing is placed by default.
	 */
	public function test_nothing_is_enabled_by_default() {
		foreach ( array_keys( Traktivity_Placements::available() ) as $slug ) {
			$this->assertFalse( Traktivity_Placements::is_enabled( $slug ), "{$slug} is on by default." );
		}
	}

	/**
	 * An enabled block placement hooks its block against the right anchor.
	 */
	public function test_block_placement_hooks_its_block() {
		if ( ! $this->use_block_theme() ) {
			$this->markTestSkipped( 'No block theme available to switch to.' );
		}

		$this->enable( array( 'traktivity-series-after-entry' ) );
		Traktivity_Placements::register_placements();

		$hooked = apply_filters(
			'hooked_block_types',
			array(),
			'after',
			'core/post-content',
			$this->template_context( 'single-traktivity_event' )
		);

		$this->assertContains( 'traktivity/top-shows', $hooked );
	}

	/**
	 * A placement stays out of templates it was not meant for.
	 */
	public function test_block_placement_respects_its_context() {
		if ( ! $this->use_block_theme() ) {
			$this->markTestSkipped( 'No block theme available to switch to.' );
		}

		$this->enable( array( 'traktivity-series-after-entry' ) );
		Traktivity_Placements::register_placements();

		$wrong_template = apply_filters(
			'hooked_block_types',
			array(),
			'after',
			'core/post-content',
			$this->template_context( 'single' )
		);
		$wrong_anchor   = apply_filters(
			'hooked_block_types',
			array(),
			'after',
			'core/post-title',
			$this->template_context( 'single-traktivity_event' )
		);
		$wrong_position = apply_filters(
			'hooked_block_types',
			array(),
			'before',
			'core/post-content',
			$this->template_context( 'single-traktivity_event' )
		);

		$this->assertNotContains( 'traktivity/top-shows', $wrong_template );
		$this->assertNotContains( 'traktivity/top-shows', $wrong_anchor );
		$this->assertNotContains( 'traktivity/top-shows', $wrong_position );
	}

	/**
	 * An archive placement asks for the width the archive is laid out at.
	 *
	 * The hooked_block_types filter only names a block, so the one that lands
	 * carries no attributes. Without this a content-width band sits above a
	 * wide grid, which the template used to avoid by setting align by hand.
	 */
	public function test_archive_placement_is_wide() {
		if ( ! $this->use_block_theme() ) {
			$this->markTestSkipped( 'No block theme available to switch to.' );
		}

		$this->enable( array( 'traktivity-totals-on-archive' ) );
		Traktivity_Placements::register_placements();

		// Built from the block name, the way core builds it, slash and dash included.
		$block = 'traktivity/watch-stats';
		$hook  = 'hooked_block_' . $block;

		$hooked = apply_filters(
			$hook,
			array(
				'blockName' => $block,
				'attrs'     => array(),
			),
			$block,
			'before',
			array( 'blockName' => 'core/query' )
		);

		$this->assertSame( 'wide', $hooked['attrs']['align'] );
	}

	/**
	 * A placement that is off hooks nothing.
	 */
	public function test_disabled_placement_hooks_nothing() {
		if ( ! $this->use_block_theme() ) {
			$this->markTestSkipped( 'No block theme available to switch to.' );
		}

		Traktivity_Placements::register_placements();

		$hooked = apply_filters(
			'hooked_block_types',
			array(),
			'after',
			'core/post-content',
			$this->template_context( 'single-traktivity_event' )
		);

		$this->assertNotContains( 'traktivity/top-shows', $hooked );
	}

	/**
	 * A part placement renders itself at the action it names.
	 */
	public function test_part_placement_hooks_its_action() {
		if ( ! $this->use_block_theme() ) {
			$this->markTestSkipped( 'No block theme available to switch to.' );
		}

		$this->enable( array( 'traktivity-recent-in-footer' ) );
		Traktivity_Placements::register_placements();

		// The part is a query loop, so it needs something to loop over.
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'traktivity_event',
				'post_status' => 'publish',
				'post_title'  => 'Something Watched',
			)
		);
		update_post_meta( $post_id, 'trakt_show_id', 1 );

		$this->assertTrue( (bool) has_action( 'wp_footer' ) );

		/*
		 * The callback is called directly rather than by firing wp_footer,
		 * which drags in the rest of the page and a core deprecation notice
		 * that has nothing to do with this.
		 */
		ob_start();
		Traktivity_Placements::render_part( 'traktivity-recent-compact' );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'traktivity-placement--traktivity-recent-compact', $output );
		$this->assertStringContainsString( 'Something Watched', $output );
	}

	/**
	 * A single-entry placement still lands on a classic theme.
	 *
	 * There are no templates to hook into, so it goes after the content
	 * instead, the same fallback Jetpack uses for its post-end placement.
	 */
	public function test_classic_theme_falls_back_to_the_content() {
		$this->enable( array( 'traktivity-series-after-entry' ) );

		add_filter( 'wp_is_block_theme', '__return_false' );
		Traktivity_Placements::register_placements();
		remove_filter( 'wp_is_block_theme', '__return_false' );

		$this->assertNotFalse( has_filter( 'the_content' ) );
	}

	/**
	 * An archive placement is skipped on a classic theme.
	 *
	 * There is no sensible place for it without a template, and dropping it
	 * into the content of every entry would be worse than leaving it out.
	 */
	public function test_archive_placement_is_skipped_on_a_classic_theme() {
		$this->enable( array( 'traktivity-totals-on-archive' ) );

		add_filter( 'wp_is_block_theme', '__return_false' );
		Traktivity_Placements::register_placements();
		remove_filter( 'wp_is_block_theme', '__return_false' );

		$post_id = self::factory()->post->create( array( 'post_type' => 'traktivity_event' ) );

		$this->assertStringNotContainsString(
			'traktivity-stats',
			apply_filters( 'the_content', 'Some content.' ),
			'An archive placement has no classic-theme home.'
		);
		$this->assertNotSame( 0, $post_id );
	}

	/**
	 * Every placement is described for the settings screen.
	 */
	public function test_placements_are_described() {
		foreach ( Traktivity_Placements::for_settings() as $entry ) {
			$this->assertNotSame( '', $entry['title'], "{$entry['slug']} has no title." );
			$this->assertNotSame( '', $entry['description'], "{$entry['slug']} has no description." );
			$this->assertSame( 'placement', $entry['type'] );
		}
	}

	/**
	 * Placements are stored in the same option as the templates.
	 */
	public function test_placements_share_the_templates_option() {
		Traktivity_Templates::save_enabled( array( 'traktivity-totals-on-archive' => true ) );

		$this->assertTrue( Traktivity_Placements::is_enabled( 'traktivity-totals-on-archive' ) );
	}

	/**
	 * Registration is wired to init.
	 */
	public function test_registration_is_hooked() {
		$this->assertNotFalse( has_action( 'init', array( 'Traktivity_Placements', 'register_placements' ) ) );
	}
}
