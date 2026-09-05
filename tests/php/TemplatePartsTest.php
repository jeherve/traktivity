<?php
/**
 * Tests for the editable template parts.
 *
 * @package Traktivity
 */

/**
 * Providing template parts a site owner can edit and place themselves.
 */
class TemplatePartsTest extends WP_UnitTestCase {

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
	 * Switch to a block theme, since parts only resolve under one.
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
	 * Switch parts on.
	 *
	 * @param string[] $slugs Part slugs to enable.
	 */
	/**
	 * Switch on the placements that need the given parts.
	 *
	 * A part has no switch of its own: it backs a placement, and is provided
	 * exactly when that placement is on.
	 *
	 * @param string[] $parts Part slugs to make available.
	 */
	private function enable( array $parts ) {
		$slugs = array();

		foreach ( Traktivity_Placements::available() as $slug => $placement ) {
			if ( 'part' === $placement['type'] && in_array( $placement['part'], $parts, true ) ) {
				$slugs[] = $slug;
			}
		}

		update_option( Traktivity_Templates::OPTION, array_fill_keys( $slugs, true ) );
	}

	/**
	 * Every advertised part points at markup that exists and is substituted.
	 *
	 * @dataProvider data_parts
	 *
	 * @param string $slug Part slug.
	 * @param array  $part Part definition.
	 */
	public function test_part_markup_is_usable( $slug, $part ) {
		$content = Traktivity_Templates::content( $part['file'] );

		$this->assertNotSame( '', $content, "{$slug} has no markup." );
		$this->assertStringContainsString( '<!-- wp:', $content, "{$slug} does not look like block markup." );
		$this->assertStringNotContainsString( '{{', $content, "{$slug} still carries an unsubstituted placeholder." );
	}

	/**
	 * Each part the plugin can provide.
	 *
	 * @return array<string, array{string, array}>
	 */
	public function data_parts() {
		$cases = array();

		foreach ( Traktivity_Templates::available_parts() as $slug => $part ) {
			$cases[ $slug ] = array( $slug, $part );
		}

		return $cases;
	}

	/**
	 * Parts reference only blocks that exist.
	 *
	 * @dataProvider data_parts
	 *
	 * @param string $slug Part slug.
	 * @param array  $part Part definition.
	 */
	public function test_parts_only_use_blocks_that_exist( $slug, $part ) {
		$content  = Traktivity_Templates::content( $part['file'] );
		$registry = WP_Block_Type_Registry::get_instance();

		preg_match_all( '/<!-- wp:(traktivity\/[a-z-]+)/', $content, $matches );

		$this->assertNotEmpty( $matches[1], "{$slug} uses no Traktivity block, so it has no reason to exist." );

		foreach ( array_unique( $matches[1] ) as $block ) {
			$built = TRAKTIVITY__PLUGIN_DIR . 'build/blocks/' . str_replace( 'traktivity/', '', $block );

			if ( ! $registry->is_registered( $block ) && is_dir( $built ) ) {
				register_block_type( $built );
			}

			$this->assertTrue( $registry->is_registered( $block ), "{$slug} references {$block}, which is not registered." );
		}
	}

	/**
	 * A part ID is namespaced to the active theme.
	 *
	 * That is what lets a saved copy take over: WordPress stores an edited
	 * part under this ID, get_block_template() stops coming back empty, and
	 * our filter steps aside.
	 */
	public function test_part_id_is_namespaced_to_the_theme() {
		$this->assertSame(
			get_stylesheet() . '//traktivity-recent-compact',
			Traktivity_Templates::part_id( 'traktivity-recent-compact' )
		);
	}

	/**
	 * An enabled part is handed back for its own ID.
	 */
	public function test_enabled_part_is_provided() {
		if ( ! $this->use_block_theme() ) {
			$this->markTestSkipped( 'No block theme available to switch to.' );
		}

		$this->enable( array( 'traktivity-recent-compact' ) );

		$id   = Traktivity_Templates::part_id( 'traktivity-recent-compact' );
		$part = Traktivity_Templates::provide_template_part( null, $id, 'wp_template_part' );

		$this->assertInstanceOf( 'WP_Block_Template', $part );
		$this->assertSame( 'traktivity-recent-compact', $part->slug );
		$this->assertSame( 'plugin', $part->source );
		$this->assertFalse( $part->has_theme_file );
		$this->assertStringContainsString( 'traktivity/event-card', $part->content );
	}

	/**
	 * Enabled parts appear in the lists the editor reads.
	 *
	 * Answering for a single ID is enough to render a part and to open it from
	 * a direct link, and not enough for anything to find it: the Site Editor's
	 * parts list and the Template Part block's picker both read the
	 * collection. Without this the parts are editable but unplaceable.
	 */
	public function test_enabled_parts_are_listed() {
		if ( ! $this->use_block_theme() ) {
			$this->markTestSkipped( 'No block theme available to switch to.' );
		}

		$this->enable( array( 'traktivity-recent-watches', 'traktivity-recent-compact' ) );

		$listed = Traktivity_Templates::list_template_parts( array(), array(), 'wp_template_part' );
		$slugs  = wp_list_pluck( $listed, 'slug' );

		$this->assertContains( 'traktivity-recent-compact', $slugs );
		$this->assertContains( 'traktivity-recent-watches', $slugs );
	}

	/**
	 * A part already in the list is not added a second time.
	 *
	 * Once someone edits one, WordPress has its own copy, and ours must not
	 * shadow it.
	 */
	public function test_existing_parts_are_not_duplicated() {
		if ( ! $this->use_block_theme() ) {
			$this->markTestSkipped( 'No block theme available to switch to.' );
		}

		$this->enable( array( 'traktivity-recent-compact' ) );

		$saved         = new WP_Block_Template();
		$saved->slug   = 'traktivity-recent-compact';
		$saved->source = 'custom';

		$listed = Traktivity_Templates::list_template_parts( array( $saved ), array(), 'wp_template_part' );
		$slugs  = wp_list_pluck( $listed, 'slug' );

		$this->assertSame( 1, count( array_keys( $slugs, 'traktivity-recent-compact', true ) ) );
		$this->assertSame( 'custom', $listed[0]->source );
	}

	/**
	 * A slug filter is honoured, so a targeted lookup stays targeted.
	 */
	public function test_listing_honours_a_slug_filter() {
		if ( ! $this->use_block_theme() ) {
			$this->markTestSkipped( 'No block theme available to switch to.' );
		}

		$this->enable( array( 'traktivity-recent-watches', 'traktivity-recent-compact' ) );

		$listed = Traktivity_Templates::list_template_parts(
			array(),
			array( 'slug__in' => array( 'traktivity-recent-compact' ) ),
			'wp_template_part'
		);

		$this->assertSame( array( 'traktivity-recent-compact' ), wp_list_pluck( $listed, 'slug' ) );
	}

	/**
	 * Whole templates and classic themes are left alone.
	 */
	public function test_listing_leaves_other_requests_alone() {
		$this->enable( array( 'traktivity-recent-compact' ) );

		$this->assertSame( array(), Traktivity_Templates::list_template_parts( array(), array(), 'wp_template' ) );

		add_filter( 'wp_is_block_theme', '__return_false' );
		$classic = Traktivity_Templates::list_template_parts( array(), array(), 'wp_template_part' );
		remove_filter( 'wp_is_block_theme', '__return_false' );

		$this->assertSame( array(), $classic );
	}

	/**
	 * The listing filter is wired up.
	 */
	public function test_listing_filter_is_hooked() {
		$this->assertNotFalse( has_filter( 'get_block_templates', array( 'Traktivity_Templates', 'list_template_parts' ) ) );
	}

	/**
	 * A part that is switched off is not provided.
	 */
	public function test_disabled_part_is_not_provided() {
		$id = Traktivity_Templates::part_id( 'traktivity-recent-compact' );

		$this->assertNull( Traktivity_Templates::provide_template_part( null, $id, 'wp_template_part' ) );
	}

	/**
	 * Something else already claiming the ID wins.
	 *
	 * This is how an edited copy takes over: once WordPress has saved one,
	 * the filter is handed a template and hands it straight back.
	 */
	public function test_an_existing_template_wins() {
		$this->enable( array( 'traktivity-recent-compact' ) );

		$existing          = new WP_Block_Template();
		$existing->slug    = 'traktivity-recent-compact';
		$existing->source  = 'custom';
		$existing->content = 'Edited by the site owner.';

		$provided = Traktivity_Templates::provide_template_part(
			$existing,
			Traktivity_Templates::part_id( 'traktivity-recent-compact' ),
			'wp_template_part'
		);

		$this->assertSame( 'custom', $provided->source );
		$this->assertSame( 'Edited by the site owner.', $provided->content );
	}

	/**
	 * An unrelated ID is left alone.
	 */
	public function test_other_ids_are_left_alone() {
		$this->enable( array( 'traktivity-recent-compact' ) );

		$this->assertNull(
			Traktivity_Templates::provide_template_part( null, get_stylesheet() . '//header', 'wp_template_part' )
		);
	}

	/**
	 * A whole template asking for the same ID is left alone.
	 */
	public function test_whole_templates_are_left_alone() {
		$this->enable( array( 'traktivity-recent-compact' ) );

		$this->assertNull(
			Traktivity_Templates::provide_template_part(
				null,
				Traktivity_Templates::part_id( 'traktivity-recent-compact' ),
				'wp_template'
			)
		);
	}

	/**
	 * Nothing is provided on a classic theme.
	 */
	public function test_nothing_is_provided_on_a_classic_theme() {
		$this->enable( array( 'traktivity-recent-compact' ) );

		add_filter( 'wp_is_block_theme', '__return_false' );
		$part = Traktivity_Templates::provide_template_part(
			null,
			Traktivity_Templates::part_id( 'traktivity-recent-compact' ),
			'wp_template_part'
		);
		remove_filter( 'wp_is_block_theme', '__return_false' );

		$this->assertNull( $part );
	}

	/**
	 * Every part carries a translatable title and description.
	 *
	 * They are the only signpost in the Site Editor's parts list, which is
	 * the only place anyone will find these.
	 */
	public function test_parts_are_described() {
		foreach ( Traktivity_Templates::available_parts() as $slug => $part ) {
			$this->assertNotSame( '', $part['title'], "{$slug} has no title." );
			$this->assertNotSame( '', $part['description'], "{$slug} has no description." );
		}
	}

	/**
	 * Parts carry no hard-coded term or post IDs.
	 *
	 * Term IDs differ per site, so a query filtered by one works on exactly
	 * one install.
	 *
	 * @dataProvider data_parts
	 *
	 * @param string $slug Part slug.
	 * @param array  $part Part definition.
	 */
	public function test_parts_carry_no_hardcoded_ids( $slug, $part ) {
		$content = Traktivity_Templates::content( $part['file'] );

		$this->assertStringNotContainsString( 'taxQuery', $content, "{$slug} filters by term ID." );
		$this->assertDoesNotMatchRegularExpression( '/"(postId|termId)":\s*\d+/', $content, "{$slug} carries a hard-coded ID." );
	}

	/**
	 * The filter is wired up.
	 */
	public function test_filter_is_hooked() {
		$this->assertNotFalse( has_filter( 'get_block_template', array( 'Traktivity_Templates', 'provide_template_part' ) ) );
	}
}
