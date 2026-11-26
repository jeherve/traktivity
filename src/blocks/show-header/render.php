<?php
/**
 * Render the Series Header block.
 *
 * @package Traktivity
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block default content.
 * @var WP_Block $block      Block instance.
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

$traktivity_term = get_queried_object();

/*
 * A template block for one archive. Anywhere else there is no series to
 * describe, so it renders nothing rather than guessing at one.
 */
if ( ! $traktivity_term instanceof WP_Term || 'trakt_show' !== $traktivity_term->taxonomy ) {
	return;
}

$traktivity_level   = isset( $attributes['headingLevel'] ) ? min( 6, max( 1, (int) $attributes['headingLevel'] ) ) : 1;
$traktivity_tag     = 'h' . $traktivity_level;
$traktivity_poster  = traktivity_get_show_poster( $traktivity_term->term_id );
$traktivity_network = traktivity_get_show_network( $traktivity_term->term_id );
$traktivity_minutes = traktivity_get_show_runtime( $traktivity_term->term_id );
$traktivity_runtime = $traktivity_minutes > 0 ? Traktivity_Stats::convert_time( $traktivity_minutes ) : '';

$traktivity_wrapper = get_block_wrapper_attributes( array( 'class' => 'traktivity-showhead' ) );
?>
<header <?php echo $traktivity_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Prepared by get_block_wrapper_attributes(). ?>>
	<?php if ( ! isset( $attributes['showImage'] ) || $attributes['showImage'] ) : ?>
		<?php
		/*
		 * A 16:9 still from TMDb rather than the 2:3 poster the meta key
		 * suggests, so it gets a landscape frame. Built into a variable rather
		 * than inline so the escaping annotation sits on one line the sniff can
		 * read, and built in here so a header with its image turned off never
		 * asks for the attachment.
		 */
		$traktivity_art = Traktivity_Blocks::frame(
			isset( $traktivity_poster['id'] ) ? (int) $traktivity_poster['id'] : 0,
			$traktivity_term->name,
			'landscape'
		);
		?>
		<div class="traktivity-showhead__art">
			<?php echo $traktivity_art; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in Traktivity_Blocks::frame(). ?>
		</div>
	<?php endif; ?>

	<div class="traktivity-showhead__body">
		<?php if ( ( ! isset( $attributes['showNetwork'] ) || $attributes['showNetwork'] ) && '' !== $traktivity_network ) : ?>
			<p class="traktivity-showhead__network"><?php echo esc_html( $traktivity_network ); ?></p>
		<?php endif; ?>

		<<?php echo esc_html( $traktivity_tag ); ?> class="traktivity-showhead__title"><?php echo esc_html( $traktivity_term->name ); ?></<?php echo esc_html( $traktivity_tag ); ?>>

		<?php if ( ! isset( $attributes['showStats'] ) || $attributes['showStats'] ) : ?>
			<p class="traktivity-showhead__stats">
				<?php
				printf(
					/* translators: %s: number of episodes. */
					esc_html( _n( '%s episode watched', '%s episodes watched', (int) $traktivity_term->count, 'traktivity' ) ),
					esc_html( number_format_i18n( (int) $traktivity_term->count ) )
				);
				?>
				<?php if ( '' !== $traktivity_runtime ) : ?>
					<span class="traktivity-sep" aria-hidden="true">&middot;</span>
					<?php echo esc_html( $traktivity_runtime ); ?>
				<?php endif; ?>
			</p>
		<?php endif; ?>

		<?php if ( ( ! isset( $attributes['showSynopsis'] ) || $attributes['showSynopsis'] ) && '' !== $traktivity_term->description ) : ?>
			<?php
			/*
			 * The sync stores this as plain text, but WordPress allows limited
			 * markup in a term description and a site owner may well have
			 * edited one. wp_kses_post() keeps their formatting rather than
			 * printing their tags at them.
			 */
			?>
			<div class="traktivity-showhead__synopsis"><?php echo wp_kses_post( wpautop( $traktivity_term->description ) ); ?></div>
		<?php endif; ?>

		<?php
		if ( ! empty( $attributes['showLinks'] ) ) :
			$traktivity_links = traktivity_get_show_links( $traktivity_term->term_id );
			?>
			<?php if ( ! empty( $traktivity_links ) ) : ?>
				<p class="traktivity-showhead__links">
					<?php
					$traktivity_rendered = array();

					foreach ( $traktivity_links as $traktivity_link ) {
						$traktivity_rendered[] = sprintf(
							'<a href="%1$s">%2$s</a>',
							esc_url( $traktivity_link['url'] ),
							esc_html( $traktivity_link['label'] )
						);
					}

					echo implode( ' <span class="traktivity-sep" aria-hidden="true">&middot;</span> ', $traktivity_rendered ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each link is escaped as it is built above.
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</header>
