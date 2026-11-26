<?php
/**
 * Render the Event Title block.
 *
 * Core's Post Title prints the bare episode name, which identifies nothing on
 * its own. The show and the episode numbers live in three other taxonomies, so
 * the heading has to be composed rather than read off the post.
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

$traktivity_level = isset( $attributes['level'] ) ? min( 6, max( 1, (int) $attributes['level'] ) ) : 1;
$traktivity_tag   = 'h' . $traktivity_level;
$traktivity_show  = ! isset( $attributes['showShowName'] ) || $attributes['showShowName'];
$traktivity_link  = ! isset( $attributes['linkShowName'] ) || $attributes['linkShowName'];

$traktivity_wrapper = get_block_wrapper_attributes( array( 'class' => 'traktivity-event-title' ) );

/*
 * The parts are assembled here rather than through
 * traktivity_get_event_title(), which returns plain text by design. A linked
 * show name needs markup, and building it from the separate show_name and
 * show_link keys keeps each part escapable on its own.
 */
$traktivity_parts = array();

if ( $traktivity_show && '' !== $traktivity_event['show_name'] ) {
	if ( $traktivity_link && '' !== $traktivity_event['show_link'] ) {
		$traktivity_parts[] = sprintf(
			'<span class="traktivity-event-title__show"><a href="%1$s">%2$s</a></span>',
			esc_url( $traktivity_event['show_link'] ),
			esc_html( $traktivity_event['show_name'] )
		);
	} else {
		$traktivity_parts[] = sprintf(
			'<span class="traktivity-event-title__show">%s</span>',
			esc_html( $traktivity_event['show_name'] )
		);
	}
}

if ( '' !== $traktivity_event['episode_code'] ) {
	$traktivity_parts[] = sprintf(
		'<span class="traktivity-event-title__code">%s</span>',
		esc_html( $traktivity_event['episode_code'] )
	);
}

$traktivity_name = esc_html( $traktivity_event['title'] );

if ( ! empty( $attributes['isLink'] ) && '' !== $traktivity_event['permalink'] ) {
	$traktivity_name = sprintf(
		'<a href="%1$s">%2$s</a>',
		esc_url( $traktivity_event['permalink'] ),
		$traktivity_name
	);
}

$traktivity_parts[] = sprintf( '<span class="traktivity-event-title__name">%s</span>', $traktivity_name );
?>
<<?php echo esc_html( $traktivity_tag ); ?> <?php echo $traktivity_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared by get_block_wrapper_attributes(). ?>>
	<?php echo implode( ' ', $traktivity_parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each part is escaped as it is built above. ?>
</<?php echo esc_html( $traktivity_tag ); ?>>
