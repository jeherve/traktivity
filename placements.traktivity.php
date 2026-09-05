<?php
/**
 * Where the plugin puts its own blocks and parts on the front end.
 *
 * A plugin cannot rely on core's Template Part block to place anything it
 * provides. That block resolves a part by querying wp_template_part posts and
 * then falling back to a theme file under parts/; it never asks
 * get_block_template(), so a part a plugin answers for is invisible to it. A
 * part registered the way templates.traktivity.php registers one is therefore
 * editable and previewable, and cannot be placed by hand.
 *
 * So the plugin places things itself, the two ways Jetpack's subscribe
 * placements do:
 *
 * - Hooked blocks, for a self-contained block anchored to another block in a
 *   template. The block lands in the template, and the site owner can move or
 *   delete it in the Site Editor and that sticks. Classic themes have no
 *   templates to hook into, so those fall back to the_content.
 * - block_template_part() called at a PHP hook, for the parts whose markup is a
 *   query loop rather than one block. Hooked blocks insert a single registered
 *   block, so a loop cannot travel that way.
 *
 * Every placement is off until switched on, same as the templates.
 *
 * @package Traktivity
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Put Traktivity's blocks and parts on the front end.
 *
 * @since 3.1.0
 */
class Traktivity_Placements {

	/**
	 * Hook everything up.
	 *
	 * @since 3.1.0
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'register_placements' ), 30 );
	}

	/**
	 * The placements the plugin offers.
	 *
	 * `block` placements travel as hooked blocks and name a registered block,
	 * an anchor to sit against, and where relative to it. `part` placements are
	 * rendered by the plugin at the named action, because their markup is a
	 * query loop rather than a single block.
	 *
	 * @since 3.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function available(): array {
		return array(
			'traktivity-totals-on-archive'    => array(
				'type'        => 'block',
				'block'       => 'traktivity/watch-stats',
				'anchor'      => 'core/query',
				'position'    => 'before',
				'context'     => 'archive-traktivity_event',
				'title'       => __( 'Totals above the archive', 'traktivity' ),
				'description' => __( 'Hours, entries, episodes, films and series, at the top of your archive.', 'traktivity' ),
			),
			'traktivity-latest-on-archive'    => array(
				'type'        => 'block',
				'block'       => 'traktivity/latest-watch',
				'anchor'      => 'core/query',
				'position'    => 'before',
				'context'     => 'archive-traktivity_event',
				'title'       => __( 'Last watched, above the archive', 'traktivity' ),
				'description' => __( 'The most recent thing you watched, shown large above the list.', 'traktivity' ),
			),
			'traktivity-series-after-entry'   => array(
				'type'        => 'block',
				'block'       => 'traktivity/top-shows',
				'anchor'      => 'core/post-content',
				'position'    => 'after',
				'context'     => 'single-traktivity_event',
				'title'       => __( 'Series you watch most, after an entry', 'traktivity' ),
				'description' => __( 'A grid of your most-watched series at the end of every entry.', 'traktivity' ),
			),
			'traktivity-recent-in-footer'     => array(
				'type'        => 'part',
				'part'        => 'traktivity-recent-compact',
				'action'      => 'wp_footer',
				'title'       => __( 'Recently watched, in the footer', 'traktivity' ),
				'description' => __( 'A short list of your latest entries at the bottom of every page.', 'traktivity' ),
			),
			'traktivity-recent-after-archive' => array(
				'type'        => 'part',
				'part'        => 'traktivity-recent-watches',
				'action'      => 'wp_footer',
				'title'       => __( 'Recently watched, as a grid', 'traktivity' ),
				'description' => __( 'The last few things you watched, as a grid of cards below the page.', 'traktivity' ),
			),
		);
	}

	/**
	 * Whether a placement is switched on.
	 *
	 * Shares the option with the templates, so the settings screen reads and
	 * writes one thing.
	 *
	 * @since 3.1.0
	 *
	 * @param string $slug Placement slug.
	 *
	 * @return bool
	 */
	public static function is_enabled( string $slug ): bool {
		return Traktivity_Templates::is_enabled( $slug );
	}

	/**
	 * Wire up whichever placements are switched on.
	 *
	 * @since 3.1.0
	 */
	public static function register_placements(): void {
		foreach ( self::available() as $slug => $placement ) {
			if ( ! self::is_enabled( $slug ) ) {
				continue;
			}

			if ( 'part' === $placement['type'] ) {
				self::hook_part( $placement );
				continue;
			}

			self::hook_block( $slug, $placement );
		}
	}

	/**
	 * Render a part at the action its placement names.
	 *
	 * @since 3.1.0
	 *
	 * @param array $placement Placement definition.
	 */
	private static function hook_part( array $placement ): void {
		add_action(
			$placement['action'],
			static function () use ( $placement ) {
				self::render_part( $placement['part'] );
			}
		);
	}

	/**
	 * Print one of the plugin's parts.
	 *
	 * Named rather than inline so it can be called directly, including from a
	 * test that does not want to fire a whole page's worth of wp_footer.
	 *
	 * @since 3.1.0
	 *
	 * @param string $part Part slug.
	 */
	public static function render_part( string $part ): void {
		if ( ! wp_is_block_theme() ) {
			return;
		}

		echo '<div class="traktivity-placement traktivity-placement--' . esc_attr( $part ) . '">';
		block_template_part( $part );
		echo '</div>';
	}

	/**
	 * Put a block into a template, or after the content on a classic theme.
	 *
	 * @since 3.1.0
	 *
	 * @param string $slug      Placement slug.
	 * @param array  $placement Placement definition.
	 */
	private static function hook_block( string $slug, array $placement ): void {
		if ( ! wp_is_block_theme() ) {
			self::hook_block_for_classic_theme( $placement );
			return;
		}

		add_filter(
			'hooked_block_types',
			static function ( $hooked_blocks, $relative_position, $anchor_block, $context ) use ( $placement ) {
				if (
					$anchor_block === $placement['anchor']
					&& $relative_position === $placement['position']
					&& self::is_context( $context, $placement['context'] )
				) {
					$hooked_blocks[] = $placement['block'];
				}

				return $hooked_blocks;
			},
			10,
			4
		);

		unset( $slug );
	}

	/**
	 * Append a block after the content, for themes with no templates to hook.
	 *
	 * Only the single-entry placements have a sensible classic-theme home. An
	 * archive placement has nowhere to go without a template, so it is skipped
	 * rather than dropped somewhere arbitrary.
	 *
	 * @since 3.1.0
	 *
	 * @param array $placement Placement definition.
	 */
	private static function hook_block_for_classic_theme( array $placement ): void {
		if ( 'single-traktivity_event' !== $placement['context'] ) {
			return;
		}

		add_filter(
			'the_content',
			static function ( $content ) use ( $placement ) {
				if ( ! is_singular( 'traktivity_event' ) || ! in_the_loop() || ! is_main_query() ) {
					return $content;
				}

				return $content . do_blocks( '<!-- wp:' . $placement['block'] . ' /-->' );
			},
			100
		);
	}

	/**
	 * Whether the template a hooked block is being offered to is the one wanted.
	 *
	 * A template part or pattern arrives here too, and an array turns up on
	 * themes that do not hand over a WP_Block_Template at all, so the queried
	 * template is checked by slug and the array case falls back to asking
	 * WordPress what is being viewed.
	 *
	 * @since 3.1.0
	 *
	 * @param WP_Block_Template|WP_Post|array $context The template the anchor block belongs to.
	 * @param string                          $wanted  Template slug the placement asked for.
	 *
	 * @return bool
	 */
	private static function is_context( $context, string $wanted ): bool {
		if ( $context instanceof WP_Block_Template ) {
			return $context->slug === $wanted;
		}

		if ( is_array( $context ) ) {
			return 'single-traktivity_event' === $wanted
				? is_singular( 'traktivity_event' )
				: is_post_type_archive( 'traktivity_event' );
		}

		return false;
	}

	/**
	 * Everything the settings screen needs to describe the placements.
	 *
	 * @since 3.1.0
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function for_settings(): array {
		$entries = array();

		foreach ( self::available() as $slug => $placement ) {
			$entries[] = array(
				'slug'          => $slug,
				'type'          => 'placement',
				'editSlug'      => 'part' === $placement['type'] ? $placement['part'] : '',
				'editType'      => 'part' === $placement['type'] ? 'wp_template_part' : '',
				'title'         => $placement['title'],
				'description'   => $placement['description'],
				'enabled'       => self::is_enabled( $slug ),
				'themeProvides' => false,
			);
		}

		return $entries;
	}
}

Traktivity_Placements::init();
