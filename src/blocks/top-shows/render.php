<?php
/**
 * Render the Top Series block.
 *
 * A Query Loop cannot express this: it orders posts, and this orders taxonomy
 * terms by how many posts they hold.
 *
 * @package Traktivity
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

$traktivity_number  = isset( $attributes['number'] ) ? max( 0, (int) $attributes['number'] ) : 12;
$traktivity_orderby = isset( $attributes['orderby'] ) && 'name' === $attributes['orderby'] ? 'name' : 'count';
$traktivity_level   = isset( $attributes['headingLevel'] ) ? min( 6, max( 1, (int) $attributes['headingLevel'] ) ) : 3;
$traktivity_columns = isset( $attributes['columns'] ) ? min( 6, max( 1, (int) $attributes['columns'] ) ) : 4;
$traktivity_tag     = 'h' . $traktivity_level;

$traktivity_shows = get_terms(
	array(
		'taxonomy'   => 'trakt_show',
		'hide_empty' => true,
		'orderby'    => $traktivity_orderby,
		'order'      => 'count' === $traktivity_orderby ? 'DESC' : 'ASC',

		/*
		 * A number of 0 means "every series", which is what makes this block
		 * usable as a full index on its own page. get_terms() reads 0 as
		 * unlimited already, so it is passed through rather than translated.
		 */
		'number'     => $traktivity_number,
	)
);

if ( is_wp_error( $traktivity_shows ) || empty( $traktivity_shows ) ) {
	return;
}

$traktivity_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'traktivity-shows traktivity-shows--cols-' . $traktivity_columns,
		'style' => '--traktivity-columns:' . $traktivity_columns,
	)
);
?>
<div <?php echo $traktivity_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared by get_block_wrapper_attributes(). ?>>
	<?php
	foreach ( $traktivity_shows as $traktivity_show ) :
		$traktivity_link    = get_term_link( $traktivity_show );
		$traktivity_link    = is_wp_error( $traktivity_link ) ? '' : (string) $traktivity_link;
		$traktivity_poster  = traktivity_get_show_poster( $traktivity_show->term_id );
		$traktivity_network = traktivity_get_show_network( $traktivity_show->term_id );

		/*
		 * The stored image is a 16:9 still from TMDb rather than the 2:3 poster
		 * the meta key suggests, so it gets a landscape frame. A portrait one
		 * crops faces off.
		 */
		$traktivity_art = Traktivity_Blocks::frame(
			isset( $traktivity_poster['id'] ) ? (int) $traktivity_poster['id'] : 0,
			$traktivity_show->name,
			'landscape'
		);
		?>
		<article class="traktivity-show">
			<?php if ( ! isset( $attributes['showImage'] ) || $attributes['showImage'] ) : ?>
				<a class="traktivity-show__link" href="<?php echo esc_url( $traktivity_link ); ?>" tabindex="-1" aria-hidden="true">
					<?php echo $traktivity_art; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Traktivity_Blocks::frame(). ?>
				</a>
			<?php endif; ?>

			<<?php echo esc_html( $traktivity_tag ); ?> class="traktivity-show__title">
				<a href="<?php echo esc_url( $traktivity_link ); ?>"><?php echo esc_html( $traktivity_show->name ); ?></a>
			</<?php echo esc_html( $traktivity_tag ); ?>>

			<?php if ( ! isset( $attributes['showCount'] ) || $attributes['showCount'] ) : ?>
				<p class="traktivity-show__count">
					<?php
					printf(
						/* translators: %s: number of episodes. */
						esc_html( _n( '%s episode', '%s episodes', (int) $traktivity_show->count, 'traktivity' ) ),
						esc_html( number_format_i18n( (int) $traktivity_show->count ) )
					);
					?>
				</p>
			<?php endif; ?>

			<?php if ( ( ! isset( $attributes['showNetwork'] ) || $attributes['showNetwork'] ) && '' !== $traktivity_network ) : ?>
				<p class="traktivity-show__network"><?php echo esc_html( $traktivity_network ); ?></p>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</div>
