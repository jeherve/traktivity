<?php
/**
 * Block registration.
 *
 * Blocks live in src/blocks/<name>/ and are built into build/blocks/<name>/ by
 * wp-scripts, which picks up each block.json on its own. This file finds what
 * the build produced and registers it, so adding a block means adding a
 * directory rather than editing a list here.
 *
 * @package Traktivity
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Register the plugin's blocks, their category and their shared styles.
 *
 * @since 3.1.0
 */
class Traktivity_Blocks {

	/**
	 * Handle for the stylesheet shared by several blocks.
	 *
	 * @var string
	 */
	const SHARED_STYLE_HANDLE = 'traktivity-shared';

	/**
	 * Slug for the plugin's block category.
	 *
	 * @var string
	 */
	const CATEGORY = 'traktivity';

	/**
	 * Hook everything up.
	 *
	 * @since 3.1.0
	 */
	public static function init(): void {
		add_filter( 'block_categories_all', array( __CLASS__, 'register_category' ) );
		add_action( 'init', array( __CLASS__, 'register_shared_style' ), 5 );
		add_action( 'init', array( __CLASS__, 'register_blocks_on_init' ) );
	}

	/**
	 * Add a Traktivity category, so the blocks sit together in the inserter.
	 *
	 * @since 3.1.0
	 *
	 * @param array $categories Registered block categories.
	 *
	 * @return array Categories, with ours appended.
	 */
	public static function register_category( $categories ) {
		if ( ! is_array( $categories ) ) {
			return $categories;
		}

		foreach ( $categories as $category ) {
			if ( isset( $category['slug'] ) && self::CATEGORY === $category['slug'] ) {
				return $categories;
			}
		}

		$categories[] = array(
			'slug'  => self::CATEGORY,
			'title' => __( 'Traktivity', 'traktivity' ),
			'icon'  => null,
		);

		return $categories;
	}

	/**
	 * Register the stylesheet several blocks share.
	 *
	 * Registered at priority 5, ahead of the blocks themselves, because a
	 * block.json naming this handle in its `style` array needs it to exist by
	 * the time the block is registered.
	 *
	 * The frame and the missing-artwork placeholder are used by four blocks.
	 * Keeping them in one handle rather than repeating them in four block
	 * stylesheets means a page using two blocks downloads them once.
	 *
	 * @since 3.1.0
	 */
	public static function register_shared_style(): void {
		$relative = 'assets/blocks-shared.css';
		$path     = TRAKTIVITY__PLUGIN_DIR . $relative;

		wp_register_style(
			self::SHARED_STYLE_HANDLE,
			plugins_url( $relative, TRAKTIVITY__PLUGIN_DIR . 'traktivity.php' ),
			array(),
			file_exists( $path ) ? (string) filemtime( $path ) : TRAKTIVITY__VERSION
		);
	}

	/**
	 * Directories under build/blocks/ that hold a block.json.
	 *
	 * Reading the built directory rather than the source one, so the generated
	 * asset files sit alongside the metadata that points at them.
	 *
	 * @since 3.1.0
	 *
	 * @param string $base Directory to scan. Defaults to the plugin's build output.
	 *
	 * @return string[] Absolute paths, sorted so registration order is stable.
	 */
	public static function block_directories( string $base = '' ): array {
		if ( '' === $base ) {
			$base = TRAKTIVITY__PLUGIN_DIR . 'build/blocks';
		}

		if ( ! is_dir( $base ) ) {
			return array();
		}

		$directories = glob( untrailingslashit( $base ) . '/*', GLOB_ONLYDIR );

		if ( false === $directories ) {
			return array();
		}

		$blocks = array_values(
			array_filter(
				$directories,
				static function ( $directory ) {
					return file_exists( $directory . '/block.json' );
				}
			)
		);

		sort( $blocks );

		return $blocks;
	}

	/**
	 * Register the blocks on init.
	 *
	 * A thin void wrapper, because register_blocks() reports what it
	 * registered and an action callback must not return anything.
	 *
	 * @since 3.1.0
	 */
	public static function register_blocks_on_init(): void {
		self::register_blocks();
	}

	/**
	 * Register every block the build produced.
	 *
	 * @since 3.1.0
	 *
	 * @param string $base Directory to scan. Defaults to the plugin's build output.
	 *
	 * @return string[] Names of the blocks that registered.
	 */
	public static function register_blocks( string $base = '' ): array {
		$registered = array();

		foreach ( self::block_directories( $base ) as $directory ) {
			$type = register_block_type( $directory );

			if ( $type instanceof WP_Block_Type ) {
				$registered[] = $type->name;
			}
		}

		return $registered;
	}
}

Traktivity_Blocks::init();
