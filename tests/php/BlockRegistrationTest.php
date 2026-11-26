<?php
/**
 * Tests for block registration.
 *
 * @package Traktivity
 */

/**
 * Finding and registering whatever the build produced.
 */
class BlockRegistrationTest extends WP_UnitTestCase {

	/**
	 * Fixture directory standing in for build/blocks.
	 *
	 * @var string
	 */
	private $fixtures;

	/**
	 * Point the registrar at the fixtures.
	 */
	public function set_up() {
		parent::set_up();
		$this->fixtures = __DIR__ . '/fixtures/blocks';
	}

	/**
	 * Registered blocks are unregistered again, so tests stay independent.
	 */
	public function tear_down() {
		$registry = WP_Block_Type_Registry::get_instance();

		if ( $registry->is_registered( 'traktivity/example' ) ) {
			unregister_block_type( 'traktivity/example' );
		}

		parent::tear_down();
	}

	/**
	 * Only directories holding a block.json are treated as blocks.
	 */
	public function test_only_block_directories_are_found() {
		$found = Traktivity_Blocks::block_directories( $this->fixtures );

		$this->assertCount( 1, $found );
		$this->assertStringEndsWith( '/example', $found[0] );
	}

	/**
	 * A missing build directory is not an error.
	 *
	 * The plugin has to survive being installed without its build output, so
	 * this returns nothing rather than warning.
	 */
	public function test_missing_directory_returns_nothing() {
		$this->assertSame( array(), Traktivity_Blocks::block_directories( __DIR__ . '/nope' ) );
	}

	/**
	 * A built block registers.
	 */
	public function test_blocks_register() {
		$registered = Traktivity_Blocks::register_blocks( $this->fixtures );

		$this->assertSame( array( 'traktivity/example' ), $registered );
		$this->assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( 'traktivity/example' ) );
	}

	/**
	 * A block declaring the shared handle keeps it.
	 *
	 * Four blocks reuse the frame and placeholder rules, so each block.json
	 * lists the shared handle alongside its own stylesheet. If registration
	 * dropped it, every one of those blocks would render unstyled.
	 */
	public function test_shared_style_handle_survives_registration() {
		Traktivity_Blocks::register_blocks( $this->fixtures );

		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'traktivity/example' );

		$this->assertContains( Traktivity_Blocks::SHARED_STYLE_HANDLE, (array) $type->style_handles );
	}

	/**
	 * The shared stylesheet is registered before any block needs it.
	 */
	public function test_shared_style_is_registered() {
		Traktivity_Blocks::register_shared_style();

		$this->assertTrue( wp_style_is( Traktivity_Blocks::SHARED_STYLE_HANDLE, 'registered' ) );
	}

	/**
	 * The shared stylesheet actually ships.
	 */
	public function test_shared_stylesheet_exists() {
		$this->assertFileExists( TRAKTIVITY__PLUGIN_DIR . 'assets/blocks-shared.css' );
	}

	/**
	 * The block category is added for the inserter.
	 */
	public function test_category_is_added() {
		$categories = Traktivity_Blocks::register_category( array( array( 'slug' => 'text' ) ) );
		$slugs      = wp_list_pluck( $categories, 'slug' );

		$this->assertContains( Traktivity_Blocks::CATEGORY, $slugs );
	}

	/**
	 * The category is not added twice.
	 */
	public function test_category_is_not_duplicated() {
		$once  = Traktivity_Blocks::register_category( array( array( 'slug' => 'text' ) ) );
		$twice = Traktivity_Blocks::register_category( $once );

		$this->assertSame( $once, $twice );
	}

	/**
	 * Registration is wired to init.
	 */
	public function test_registration_is_hooked() {
		$this->assertNotFalse( has_action( 'init', array( 'Traktivity_Blocks', 'register_blocks_on_init' ) ) );
		$this->assertNotFalse( has_action( 'init', array( 'Traktivity_Blocks', 'register_shared_style' ) ) );
		$this->assertNotFalse( has_filter( 'block_categories_all', array( 'Traktivity_Blocks', 'register_category' ) ) );
	}
}
