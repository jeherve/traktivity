<?php
/**
 * Render the Event Card block.
 *
 * @package Traktivity
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

$traktivity_post_id = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();

if ( $traktivity_post_id < 1 ) {
	return;
}

$traktivity_event = traktivity_get_event( $traktivity_post_id );

if ( '' === $traktivity_event['type'] ) {
	return;
}

$traktivity_aspect = isset( $attributes['imageAspect'] ) && 'poster' === $attributes['imageAspect'] ? 'poster' : 'landscape';
$traktivity_level  = isset( $attributes['headingLevel'] ) ? min( 6, max( 1, (int) $attributes['headingLevel'] ) ) : 3;
$traktivity_tag    = 'h' . $traktivity_level;

/*
 * On a single show's archive every card would repeat the same show name, so
 * the kicker is dropped there and the episode code carries the row instead.
 */
$traktivity_queried = get_queried_object();
if ( $traktivity_queried instanceof WP_Term && 'trakt_show' === $traktivity_queried->taxonomy ) {
	$traktivity_event['show_name'] = '';
}

$traktivity_wrapper = get_block_wrapper_attributes(
	array( 'class' => 'traktivity-card traktivity-card--' . $traktivity_event['type'] )
);
?>
<article <?php echo $traktivity_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared by get_block_wrapper_attributes(). ?>>
	<?php if ( ! empty( $attributes['showImage'] ) ) : ?>
		<?php
		/*
		 * The title below links to the same place. Without this the keyboard
		 * lands on the same destination twice per card, which on a 24-card
		 * archive is 24 wasted tab stops.
		 */
		?>
		<a class="traktivity-card__link" href="<?php echo esc_url( $traktivity_event['permalink'] ); ?>" tabindex="-1" aria-hidden="true">
			<?php echo Traktivity_Blocks::frame( $traktivity_event['image_id'], $traktivity_event['title'], $traktivity_aspect ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Traktivity_Blocks::frame(). ?>
		</a>
	<?php endif; ?>

	<div class="traktivity-card__body">
		<?php if ( '' !== $traktivity_event['show_name'] ) : ?>
			<p class="traktivity-card__show">
				<?php if ( '' !== $traktivity_event['show_link'] ) : ?>
					<a href="<?php echo esc_url( $traktivity_event['show_link'] ); ?>"><?php echo esc_html( $traktivity_event['show_name'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $traktivity_event['show_name'] ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<<?php echo esc_html( $traktivity_tag ); ?> class="traktivity-card__title">
			<a href="<?php echo esc_url( $traktivity_event['permalink'] ); ?>">
				<?php if ( '' !== $traktivity_event['episode_code'] ) : ?>
					<span class="traktivity-card__code"><?php echo esc_html( $traktivity_event['episode_code'] ); ?></span>
				<?php endif; ?>
				<?php echo esc_html( $traktivity_event['title'] ); ?>
			</a>
		</<?php echo esc_html( $traktivity_tag ); ?>>

		<?php if ( ! empty( $attributes['showExcerpt'] ) ) : ?>
			<?php $traktivity_excerpt = wp_trim_words( (string) get_the_excerpt( $traktivity_post_id ), 22 ); ?>
			<?php if ( '' !== $traktivity_excerpt ) : ?>
				<p class="traktivity-card__excerpt"><?php echo esc_html( $traktivity_excerpt ); ?></p>
			<?php endif; ?>
		<?php endif; ?>

		<p class="traktivity-card__meta">
			<time datetime="<?php echo esc_attr( $traktivity_event['watched_iso'] ); ?>"><?php echo esc_html( $traktivity_event['watched'] ); ?></time>

			<?php if ( $traktivity_event['runtime'] > 0 ) : ?>
				<span class="traktivity-sep" aria-hidden="true">&middot;</span>
				<?php
				printf(
					/* translators: %d: number of minutes. */
					esc_html( _n( '%d min', '%d min', $traktivity_event['runtime'], 'traktivity' ) ),
					(int) $traktivity_event['runtime']
				);
				?>
			<?php endif; ?>

			<?php if ( 'movie' === $traktivity_event['type'] && '' !== $traktivity_event['year'] ) : ?>
				<span class="traktivity-sep" aria-hidden="true">&middot;</span>
				<?php echo esc_html( $traktivity_event['year'] ); ?>
			<?php endif; ?>
		</p>
	</div>
</article>
