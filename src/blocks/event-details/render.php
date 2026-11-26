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
 * Rows are a list of label and value pairs rather than an array keyed by
 * label, because labels are translated and two can come back as the same text.
 * Values are escaped as they are added, and nothing is escaped again at
 * output, so a new row has to escape itself here.
 */
$traktivity_rows = array();

$traktivity_linked = static function ( string $name, string $link ): string {
	return '' !== $link
		? sprintf( '<a href="%1$s">%2$s</a>', esc_url( $link ), esc_html( $name ) )
		: esc_html( $name );
};

if ( ! isset( $attributes['showTaxonomies'] ) || $attributes['showTaxonomies'] ) {
	if ( '' !== $traktivity_event['show_name'] ) {
		$traktivity_rows[] = array(
			__( 'Series', 'traktivity' ),
			$traktivity_linked( $traktivity_event['show_name'], $traktivity_event['show_link'] ),
		);
	}

	if ( '' !== $traktivity_event['episode_code'] ) {
		$traktivity_rows[] = array( __( 'Season', 'traktivity' ), esc_html( number_format_i18n( $traktivity_event['season'] ) ) );
		$traktivity_rows[] = array( __( 'Episode', 'traktivity' ), esc_html( number_format_i18n( $traktivity_event['episode'] ) ) );
	}

	if ( ! empty( $traktivity_event['genres'] ) ) {
		$traktivity_genres = array();

		foreach ( $traktivity_event['genres'] as $traktivity_genre ) {
			$traktivity_genres[] = $traktivity_linked( $traktivity_genre['name'], $traktivity_genre['link'] );
		}

		$traktivity_rows[] = array( __( 'Genre', 'traktivity' ), implode( ', ', $traktivity_genres ) );
	}

	if ( '' !== $traktivity_event['year'] ) {
		$traktivity_rows[] = array(
			__( 'Released', 'traktivity' ),
			$traktivity_linked( $traktivity_event['year'], $traktivity_event['year_link'] ),
		);
	}
}

if ( ( ! isset( $attributes['showRuntime'] ) || $attributes['showRuntime'] ) && $traktivity_event['runtime'] > 0 ) {
	$traktivity_rows[] = array(
		__( 'Runtime', 'traktivity' ),
		esc_html(
			sprintf(
				/* translators: %s: number of minutes. */
				_n( '%s minute', '%s minutes', $traktivity_event['runtime'], 'traktivity' ),
				number_format_i18n( $traktivity_event['runtime'] )
			)
		),
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
		$traktivity_rows[] = array(
			__( 'Look up', 'traktivity' ),
			implode(
				' <span class="traktivity-sep" aria-hidden="true">&middot;</span> ',
				$traktivity_links
			),
		);
	}
}

if ( empty( $traktivity_rows ) ) {
	return;
}

$traktivity_wrapper = get_block_wrapper_attributes( array( 'class' => 'traktivity-details' ) );
?>
<dl <?php echo $traktivity_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared by get_block_wrapper_attributes(). ?>>
	<?php foreach ( $traktivity_rows as list( $traktivity_label, $traktivity_value ) ) : ?>
		<div class="traktivity-details__row">
			<dt><?php echo esc_html( $traktivity_label ); ?></dt>
			<dd><?php echo $traktivity_value; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each value is escaped as it is built above. ?></dd>
		</div>
	<?php endforeach; ?>
</dl>
