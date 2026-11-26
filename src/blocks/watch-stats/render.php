<?php
/**
 * Render the Watch Stats block.
 *
 * @package Traktivity
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

$traktivity_stats = Traktivity_Stats::get_summary();

/*
 * A site that has not synced yet would otherwise show a row of zeroes, which
 * reads as broken rather than as empty. Nothing logged, nothing rendered.
 */
if ( $traktivity_stats['entries'] < 1 ) {
	return;
}

$traktivity_available = array(
	'hours'    => array(
		'value' => number_format_i18n( $traktivity_stats['hours'] ),
		'label' => _n( 'Hour', 'Hours', $traktivity_stats['hours'], 'traktivity' ),
	),
	'entries'  => array(
		'value' => number_format_i18n( $traktivity_stats['entries'] ),
		'label' => _n( 'Entry', 'Entries', $traktivity_stats['entries'], 'traktivity' ),
	),
	'episodes' => array(
		'value' => number_format_i18n( $traktivity_stats['episodes'] ),
		'label' => _n( 'Episode', 'Episodes', $traktivity_stats['episodes'], 'traktivity' ),
	),
	'films'    => array(
		'value' => number_format_i18n( $traktivity_stats['films'] ),
		'label' => _n( 'Film', 'Films', $traktivity_stats['films'], 'traktivity' ),
	),
	'shows'    => array(
		'value' => number_format_i18n( $traktivity_stats['shows'] ),
		'label' => _n( 'Series', 'Series', $traktivity_stats['shows'], 'traktivity' ),
	),
);

if ( '' !== $traktivity_stats['since'] ) {
	$traktivity_available['since'] = array(
		'value' => $traktivity_stats['since'],
		'label' => __( 'Logging since', 'traktivity' ),
	);
}

$traktivity_chosen = isset( $attributes['figures'] ) && is_array( $attributes['figures'] )
	? $attributes['figures']
	: array_keys( $traktivity_available );

$traktivity_cells = array();

foreach ( $traktivity_chosen as $traktivity_key ) {
	if ( isset( $traktivity_available[ $traktivity_key ] ) ) {
		$traktivity_cells[] = $traktivity_available[ $traktivity_key ];
	}
}

if ( empty( $traktivity_cells ) ) {
	return;
}

$traktivity_layout  = isset( $attributes['layout'] ) && 'stack' === $attributes['layout'] ? 'stack' : 'row';
$traktivity_wrapper = get_block_wrapper_attributes(
	array( 'class' => 'traktivity-stats traktivity-stats--' . $traktivity_layout )
);
?>
<div <?php echo $traktivity_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared by get_block_wrapper_attributes(). ?>>
	<?php foreach ( $traktivity_cells as $traktivity_cell ) : ?>
		<div class="traktivity-stats__cell">
			<span class="traktivity-stats__value"><?php echo esc_html( $traktivity_cell['value'] ); ?></span>
			<span class="traktivity-stats__label"><?php echo esc_html( $traktivity_cell['label'] ); ?></span>
		</div>
	<?php endforeach; ?>
</div>
