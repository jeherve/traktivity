<?php
/**
 * Render the Latest Watch block.
 *
 * @package Traktivity
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

$traktivity_type  = isset( $attributes['type'] ) ? (string) $attributes['type'] : 'any';
$traktivity_level = isset( $attributes['headingLevel'] ) ? min( 6, max( 1, (int) $attributes['headingLevel'] ) ) : 2;
$traktivity_tag   = 'h' . $traktivity_level;

$traktivity_args = array(
	'post_type'      => 'traktivity_event',
	'post_status'    => 'publish',
	'posts_per_page' => 1,
	'fields'         => 'ids',
	'no_found_rows'  => true,
);

/*
 * Restricting by type reads the ID meta rather than the trakt_type taxonomy,
 * whose term names the sync builds from a translated string. Filtering on the
 * taxonomy would quietly return nothing on any site not running in English.
 */
$traktivity_meta = array();

if ( 'tv' === $traktivity_type ) {
	$traktivity_meta[] = array(
		'key'     => 'trakt_show_id',
		'compare' => 'EXISTS',
	);
} elseif ( 'movie' === $traktivity_type ) {
	$traktivity_meta[] = array(
		'key'     => 'trakt_movie_id',
		'compare' => 'EXISTS',
	);
}

/*
 * A hero with a placeholder where the artwork should be looks broken, and
 * plenty of events never get an image from TMDb. So the newest event that has
 * one is preferred, and the newest of any kind is the fallback when none does.
 */
$traktivity_latest = array();

if ( ! isset( $attributes['preferWithImage'] ) || $attributes['preferWithImage'] ) {
	$traktivity_with_image   = $traktivity_meta;
	$traktivity_with_image[] = array(
		'key'     => '_thumbnail_id',
		'compare' => 'EXISTS',
	);

	$traktivity_latest = get_posts(
		array_merge(
			$traktivity_args,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Indexed meta keys, one row.
			array( 'meta_query' => $traktivity_with_image )
		)
	);
}

if ( empty( $traktivity_latest ) ) {
	if ( ! empty( $traktivity_meta ) ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Indexed meta key, one row.
		$traktivity_args['meta_query'] = $traktivity_meta;
	}

	$traktivity_latest = get_posts( $traktivity_args );
}

if ( empty( $traktivity_latest ) ) {
	return;
}

$traktivity_event  = traktivity_get_event( (int) $traktivity_latest[0] );
$traktivity_kicker = isset( $attributes['kicker'] ) ? (string) $attributes['kicker'] : '';

$traktivity_wrapper = get_block_wrapper_attributes( array( 'class' => 'traktivity-hero' ) );
?>
<section <?php echo $traktivity_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared by get_block_wrapper_attributes(). ?>>
	<a class="traktivity-hero__link" href="<?php echo esc_url( $traktivity_event['permalink'] ); ?>" tabindex="-1" aria-hidden="true">
		<?php echo Traktivity_Blocks::frame( $traktivity_event['image_id'], $traktivity_event['title'], 'landscape' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Traktivity_Blocks::frame(). ?>
	</a>

	<?php
	/*
	 * The kicker belongs inside the body rather than beside it. The wrapper is
	 * a two-column grid, so a third child at this level takes a cell of its
	 * own: the kicker sat next to the image and pushed everything else onto a
	 * second row, leaving a hole where the text should have been.
	 */
	?>
	<div class="traktivity-hero__body">
		<?php if ( '' !== $traktivity_kicker ) : ?>
			<p class="traktivity-hero__kicker"><?php echo esc_html( $traktivity_kicker ); ?></p>
		<?php endif; ?>

		<?php if ( '' !== $traktivity_event['show_name'] ) : ?>
			<p class="traktivity-hero__show">
				<?php
				/*
				 * get_term_link() can fail, which empties show_link and leaves
				 * the name behind. The name is the useful half, so it degrades
				 * to plain text rather than disappearing, the way the event
				 * card and the event title already do.
				 */
				?>
				<?php if ( '' !== $traktivity_event['show_link'] ) : ?>
					<a href="<?php echo esc_url( $traktivity_event['show_link'] ); ?>"><?php echo esc_html( $traktivity_event['show_name'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $traktivity_event['show_name'] ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<<?php echo esc_html( $traktivity_tag ); ?> class="traktivity-hero__title">
			<a href="<?php echo esc_url( $traktivity_event['permalink'] ); ?>">
				<?php if ( '' !== $traktivity_event['episode_code'] ) : ?>
					<span class="traktivity-hero__code"><?php echo esc_html( $traktivity_event['episode_code'] ); ?></span>
				<?php endif; ?>
				<?php echo esc_html( $traktivity_event['title'] ); ?>
			</a>
		</<?php echo esc_html( $traktivity_tag ); ?>>

		<p class="traktivity-hero__meta">
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
		</p>
	</div>
</section>
