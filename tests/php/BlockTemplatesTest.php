<?php
/**
 * Tests for the default block templates.
 *
 * @package Traktivity
 */

/**
 * Providing templates for the post type and taxonomies the plugin registers.
 */
class BlockTemplatesTest extends WP_UnitTestCase {

	/**
	 * Start each test with nothing switched on.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( Traktivity_Templates::OPTION );
	}

	/**
	 * Switch templates on.
	 *
	 * @param string[] $slugs Template slugs to enable.
	 */
	private function enable( array $slugs ) {
		update_option( Traktivity_Templates::OPTION, array_fill_keys( $slugs, true ) );
	}

	/**
	 * Stand up a query that looks like a series archive.
	 *
	 * WP_Query::is_tax() reads both $this->is_tax and the queried object, and a
	 * bare WP_Query has neither until a real request has been parsed, so both
	 * are staged here the way WordPress would set them.
	 *
	 * @param string $slug Series slug.
	 *
	 * @return WP_Query
	 */
	private function series_archive_query( $slug ) {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'trakt_show',
				'name'     => ucwords( str_replace( '-', ' ', $slug ) ),
				'slug'     => $slug,
			)
		);

		$query                    = new WP_Query();
		$query->is_tax            = true;
		$query->is_archive        = true;
		$query->queried_object    = $term;
		$query->queried_object_id = $term->term_id;

		return $query;
	}

	/**
	 * Every advertised template points at markup that exists and parses.
	 *
	 * @dataProvider data_templates
	 *
	 * @param string $slug     Template slug.
	 * @param array  $template Template definition.
	 */
	public function test_template_markup_is_usable( $slug, $template ) {
		$content = Traktivity_Templates::content( $template['file'] );

		$this->assertNotSame( '', $content, "{$slug} has no markup." );
		$this->assertStringContainsString( '<!-- wp:', $content, "{$slug} does not look like block markup." );
		$this->assertStringNotContainsString( '{{', $content, "{$slug} still carries an unsubstituted placeholder." );
	}

	/**
	 * Each template the plugin can provide.
	 *
	 * @return array<string, array{string, array}>
	 */
	public function data_templates() {
		$cases = array();

		foreach ( Traktivity_Templates::available() as $slug => $template ) {
			$cases[ $slug ] = array( $slug, $template );
		}

		return $cases;
	}

	/**
	 * Templates reference only blocks that exist.
	 *
	 * A template naming a block that was renamed or never registered shows a
	 * block error on the front end rather than failing loudly, so it is worth
	 * checking here.
	 *
	 * @dataProvider data_templates
	 *
	 * @param string $slug     Template slug.
	 * @param array  $template Template definition.
	 */
	public function test_templates_only_use_traktivity_blocks_that_exist( $slug, $template ) {
		$content  = Traktivity_Templates::content( $template['file'] );
		$registry = WP_Block_Type_Registry::get_instance();

		preg_match_all( '/<!-- wp:(traktivity\/[a-z-]+)/', $content, $matches );

		foreach ( array_unique( $matches[1] ) as $block ) {
			$built = TRAKTIVITY__PLUGIN_DIR . 'build/blocks/' . str_replace( 'traktivity/', '', $block );

			if ( ! $registry->is_registered( $block ) && is_dir( $built ) ) {
				register_block_type( $built );
			}

			$this->assertTrue( $registry->is_registered( $block ), "{$slug} references {$block}, which is not registered." );
		}
	}

	/**
	 * Nothing is switched on by default.
	 *
	 * An update must not reshape an existing site without being asked.
	 */
	public function test_nothing_is_enabled_by_default() {
		foreach ( array_keys( Traktivity_Templates::available() ) as $slug ) {
			$this->assertFalse( Traktivity_Templates::is_enabled( $slug ), "{$slug} is on by default." );
		}
	}

	/**
	 * Switching one on leaves the others alone.
	 */
	public function test_templates_are_enabled_individually() {
		$this->enable( array( 'single-traktivity_event' ) );

		$this->assertTrue( Traktivity_Templates::is_enabled( 'single-traktivity_event' ) );
		$this->assertFalse( Traktivity_Templates::is_enabled( 'archive-traktivity_event' ) );
	}

	/**
	 * An enabled template registers, and resolves through the hierarchy.
	 *
	 * Needs a block theme active, since the registry is only consulted by one.
	 * The suite runs under whatever theme wp-env installed, so this switches to
	 * a bundled block theme for the duration.
	 */
	public function test_enabled_template_registers() {
		$theme = wp_get_theme( 'twentytwentyfour' );

		if ( ! $theme->exists() ) {
			$this->markTestSkipped( 'No block theme available to switch to.' );
		}

		$previous = get_stylesheet();
		switch_theme( 'twentytwentyfour' );

		$this->enable( array( 'single-traktivity_event' ) );
		Traktivity_Templates::register_templates();

		$registered = get_block_templates( array( 'slug__in' => array( 'single-traktivity_event' ) ) );
		$slugs      = wp_list_pluck( $registered, 'slug' );

		switch_theme( $previous );

		$this->assertContains( 'single-traktivity_event', $slugs );
	}

	/**
	 * A template that is off does not register.
	 */
	public function test_disabled_template_does_not_register() {
		$theme = wp_get_theme( 'twentytwentyfour' );

		if ( ! $theme->exists() ) {
			$this->markTestSkipped( 'No block theme available to switch to.' );
		}

		$previous = get_stylesheet();
		switch_theme( 'twentytwentyfour' );

		Traktivity_Templates::register_templates();

		$registered = get_block_templates( array( 'slug__in' => array( 'archive-traktivity_event' ) ) );
		$slugs      = wp_list_pluck( $registered, 'slug' );

		switch_theme( $previous );

		$this->assertNotContains( 'archive-traktivity_event', $slugs );
	}

	/**
	 * Nothing registers on a classic theme.
	 *
	 * The registry is only consulted by block themes, so registering into it
	 * would be noise.
	 */
	public function test_nothing_registers_on_a_classic_theme() {
		if ( ! class_exists( 'WP_Block_Templates_Registry' ) ) {
			$this->markTestSkipped( 'Needs the block template registry.' );
		}

		$registry = WP_Block_Templates_Registry::get_instance();
		$name     = 'traktivity//archive-traktivity_event';

		if ( $registry->is_registered( $name ) ) {
			unregister_block_template( $name );
		}

		$this->enable( array( 'archive-traktivity_event' ) );

		add_filter( 'wp_is_block_theme', '__return_false' );
		Traktivity_Templates::register_templates();
		remove_filter( 'wp_is_block_theme', '__return_false' );

		$this->assertFalse( $registry->is_registered( $name ) );
	}

	/**
	 * An unreadable file falls through rather than registering a blank page.
	 */
	public function test_missing_file_yields_no_content() {
		$this->assertSame( '', Traktivity_Templates::content( 'does-not-exist.html' ) );
	}

	/**
	 * The file name cannot escape the templates directory.
	 */
	public function test_file_names_cannot_traverse() {
		$this->assertSame( '', Traktivity_Templates::content( '../../wp-config.php' ) );
	}

	/**
	 * A series archive reads oldest first while our template is in use.
	 *
	 * A series is a run watched in order, so newest-first is the wrong way
	 * round for it.
	 */
	public function test_series_archive_reads_oldest_first() {
		$this->enable( array( 'taxonomy-trakt_show' ) );

		$query    = $this->series_archive_query( 'some-series' );
		$previous = $GLOBALS['wp_the_query'];

		$GLOBALS['wp_the_query'] = $query;
		Traktivity_Templates::shape_archive_query( $query );
		$GLOBALS['wp_the_query'] = $previous;

		$this->assertSame( 'ASC', $query->get( 'order' ) );
	}

	/**
	 * The ordering is left alone when our template is not in use.
	 */
	public function test_ordering_is_untouched_when_the_template_is_off() {
		$query    = $this->series_archive_query( 'another-series' );
		$previous = $GLOBALS['wp_the_query'];

		$GLOBALS['wp_the_query'] = $query;
		Traktivity_Templates::shape_archive_query( $query );
		$GLOBALS['wp_the_query'] = $previous;

		$this->assertSame( '', $query->get( 'order' ) );
	}

	/**
	 * Our archives show the number of entries their markup declares.
	 *
	 * A Query Loop with inherit set takes its page size from the main query,
	 * so the perPage in the template is ignored and the site's "Blog pages
	 * show at most" wins. At the default of ten that leaves a four-column grid
	 * two and a half rows tall.
	 */
	public function test_archive_page_size_follows_the_template() {
		$this->enable( array( 'archive-traktivity_event', 'taxonomy-trakt_show' ) );

		$archive             = new WP_Query();
		$archive->is_archive = true;
		$archive->set( 'post_type', 'traktivity_event' );
		$archive->is_post_type_archive = true;

		$previous                = $GLOBALS['wp_the_query'];
		$GLOBALS['wp_the_query'] = $archive;
		Traktivity_Templates::shape_archive_query( $archive );

		$series                  = $this->series_archive_query( 'paged-series' );
		$GLOBALS['wp_the_query'] = $series;
		Traktivity_Templates::shape_archive_query( $series );
		$GLOBALS['wp_the_query'] = $previous;

		$this->assertSame( 24, $archive->get( 'posts_per_page' ) );
		$this->assertSame( 48, $series->get( 'posts_per_page' ) );
	}

	/**
	 * The page size is filterable, so a site can hand control back to itself.
	 */
	public function test_archive_page_size_is_filterable() {
		$this->enable( array( 'archive-traktivity_event' ) );

		add_filter( 'traktivity_archive_posts_per_page', static fn() => 5 );

		$archive                       = new WP_Query();
		$archive->is_archive           = true;
		$archive->is_post_type_archive = true;
		$archive->set( 'post_type', 'traktivity_event' );

		$previous                = $GLOBALS['wp_the_query'];
		$GLOBALS['wp_the_query'] = $archive;
		Traktivity_Templates::shape_archive_query( $archive );
		$GLOBALS['wp_the_query'] = $previous;

		$this->assertSame( 5, $archive->get( 'posts_per_page' ) );
	}

	/**
	 * An archive whose template is off keeps the site's own page size.
	 */
	public function test_page_size_untouched_when_the_template_is_off() {
		$archive                       = new WP_Query();
		$archive->is_archive           = true;
		$archive->is_post_type_archive = true;
		$archive->set( 'post_type', 'traktivity_event' );

		$previous                = $GLOBALS['wp_the_query'];
		$GLOBALS['wp_the_query'] = $archive;
		Traktivity_Templates::shape_archive_query( $archive );
		$GLOBALS['wp_the_query'] = $previous;

		$this->assertSame( '', $archive->get( 'posts_per_page' ) );
	}

	/**
	 * Every template carries a translatable title and description.
	 *
	 * They are the only signpost in the Site Editor's template list.
	 */
	public function test_templates_are_described() {
		foreach ( Traktivity_Templates::available() as $slug => $template ) {
			$this->assertNotSame( '', $template['title'], "{$slug} has no title." );
			$this->assertNotSame( '', $template['description'], "{$slug} has no description." );
		}
	}

	/**
	 * Registration is wired to init.
	 */
	public function test_registration_is_hooked() {
		$this->assertNotFalse( has_action( 'init', array( 'Traktivity_Templates', 'register_templates' ) ) );
		$this->assertNotFalse( has_action( 'pre_get_posts', array( 'Traktivity_Templates', 'shape_archive_query' ) ) );
	}
}
