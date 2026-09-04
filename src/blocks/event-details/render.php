<?php
/**
 * Render the Event Details block.
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

/*
 * Rows are built as label => value pairs. Most values are escaped as they are
 * added; the two taxonomy lists arrive from get_the_term_list() as markup and
 * go through wp_kses_post() instead. Nothing is escaped again at output, so a
 * new row has to escape itself here.
 */
$traktivity_rows = array();

if ( ! isset( $attributes['showTaxonomies'] ) || $attributes['showTaxonomies'] ) {
	if ( '' !== $traktivity_event['show_name'] ) {
		$traktivity_rows[ __( 'Series', 'traktivity' ) ] = '' !== $traktivity_event['show_link']
			? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $traktivity_event['show_link'] ), esc_html( $traktivity_event['show_name'] ) )
			: esc_html( $traktivity_event['show_name'] );
	}

	if ( '' !== $traktivity_event['episode_code'] ) {
		$traktivity_rows[ __( 'Season', 'traktivity' ) ]  = esc_html( number_format_i18n( $traktivity_event['season'] ) );
		$traktivity_rows[ __( 'Episode', 'traktivity' ) ] = esc_html( number_format_i18n( $traktivity_event['episode'] ) );
	}

	$traktivity_genres = get_the_term_list( $traktivity_post_id, 'trakt_genre', '', ', ' );
	if ( ! is_wp_error( $traktivity_genres ) && ! empty( $traktivity_genres ) ) {
		$traktivity_rows[ __( 'Genre', 'traktivity' ) ] = wp_kses_post( $traktivity_genres );
	}

	$traktivity_years = get_the_term_list( $traktivity_post_id, 'trakt_year', '', ', ' );
	if ( ! is_wp_error( $traktivity_years ) && ! empty( $traktivity_years ) ) {
		$traktivity_rows[ __( 'Released', 'traktivity' ) ] = wp_kses_post( $traktivity_years );
	}
}

if ( ( ! isset( $attributes['showRuntime'] ) || $attributes['showRuntime'] ) && $traktivity_event['runtime'] > 0 ) {
	$traktivity_rows[ __( 'Runtime', 'traktivity' ) ] = esc_html(
		sprintf(
			/* translators: %s: number of minutes. */
			_n( '%s minute', '%s minutes', $traktivity_event['runtime'], 'traktivity' ),
			number_format_i18n( $traktivity_event['runtime'] )
		)
	);
}

if ( ! isset( $attributes['showLinks'] ) || $attributes['showLinks'] ) {
	$traktivity_links = array();

	foreach ( traktivity_get_event_links( $traktivity_post_id ) as $traktivity_link ) {
		$traktivity_links[] = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $traktivity_link['url'] ),
			esc_html( $traktivity_link['label'] )
		);
	}

	if ( ! empty( $traktivity_links ) ) {
		$traktivity_rows[ __( 'Look up', 'traktivity' ) ] = implode(
			' <span class="traktivity-sep" aria-hidden="true">&middot;</span> ',
			$traktivity_links
		);
	}
}

if ( empty( $traktivity_rows ) ) {
	return;
}

$traktivity_wrapper = get_block_wrapper_attributes( array( 'class' => 'traktivity-details' ) );
?>
<dl <?php echo $traktivity_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared by get_block_wrapper_attributes(). ?>>
	<?php foreach ( $traktivity_rows as $traktivity_label => $traktivity_value ) : ?>
		<div class="traktivity-details__row">
			<dt><?php echo esc_html( $traktivity_label ); ?></dt>
			<dd><?php echo $traktivity_value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each value is escaped as it is built above. ?></dd>
		</div>
	<?php endforeach; ?>
</dl>
