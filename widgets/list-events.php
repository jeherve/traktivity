<?php
/**
 * Traktivity List Events Widget.
 *
 * @since 1.2.0
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );

/**
 * Register List Events widget.
 *
 * @since 1.2.0
 */
function traktivity_list_widget_init() {
	register_widget( 'Traktivity_List_Widget' );
}
add_action( 'widgets_init', 'traktivity_list_widget_init' );

/**
 * Display some of the recent things you've watched in a widget.
 */
class Traktivity_List_Widget extends WP_Widget {

	/**
	 * Constructor
	 */
	public function __construct() {
		$widget_ops = array(
			'classname'                   => 'traktivity_list_widget',
			'description'                 => esc_html__( "Display some of the recent things you've watched in a widget.", 'traktivity' ),
			'customize_selective_refresh' => true,
		);
		parent::__construct(
			'traktivity_list_widget',
			esc_html__( 'Event List (Traktivity)', 'traktivity' ),
			$widget_ops
		);

		/*
		 * Customize event titles for TV series. Fired with apply_filters() at
		 * the call site, so it registers as a filter; add_action() worked only
		 * because the two share an implementation.
		 */
		add_filter( 'traktivity_list_widget_single_event_title', array( $this, 'custom_tv_event_title' ), 20, 2 );
	}

	/**
	 * Return an associative array of default values
	 *
	 * These values are used in new widgets.
	 *
	 * @return array Array of default values for the Widget's options.
	 */
	public function defaults() {
		return array(
			'title'           => esc_html__( 'Recently Watched', 'traktivity' ),
			'type'            => get_terms(
				array(
					'taxonomy'   => 'trakt_type',
					'hide_empty' => true,
					'fields'     => 'names',
				)
			),
			'number'          => 5, // Never more than 50 though.
			'display_excerpt' => false,
			'display_image'   => false,
		);
	}

	/**
	 * Outputs the HTML for this widget.
	 *
	 * @param array $args     An array of standard parameters for widgets in this theme.
	 * @param array $instance An array of settings for this widget instance.
	 *
	 * @return void Echoes its output.
	 **/
	public function widget( $args, $instance ) {
		$instance = wp_parse_args( $instance, $this->defaults() );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper markup supplied by the theme via register_sidebar().
		echo $args['before_widget'];

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $instance['title'] );
		if ( ! empty( $title ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper markup supplied by the theme via register_sidebar().
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		/**
		 * Fires before the output of the Traktivity List Widget, after the title.
		 *
		 * @since 1.2.0
		 */
		do_action( 'traktivity_list_widget_before' );

		// Make a custom WP_Query matching the events we want to return.
		$events_args = array(
			'post_type'      => 'traktivity_event',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $instance['number'],
			'paged'          => 1,
			'tax_query'      => array(
				array(
					'taxonomy' => 'trakt_type',
					'field'    => 'name',
					'terms'    => $instance['type'],
				),
			),
		);
		$query       = new WP_Query( $events_args );

		if ( $query->have_posts() ) {
			echo '<div class="traktivity-display-events">';

			// Loop through the entries we should return.
			while ( $query->have_posts() ) {
				$query->the_post();

				// Display event.
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- display_event() escapes each value as it builds the markup.
				echo $this->display_event( $instance, $query->post->ID );

			}

			echo '</div><!-- .traktivity-display-events -->';

			// Restore original post data.
			wp_reset_postdata();
		} else {
			esc_html_e( 'I did not log any of the movies or TV series I watched yet. Come back later!', 'traktivity' );
		}

		/**
		 * Fires after the output of the Traktivity List Widget.
		 *
		 * @since 1.2.0
		 */
		do_action( 'traktivity_list_widget_after' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Wrapper markup supplied by the theme via register_sidebar().
		echo $args['after_widget'];
	}


	/**
	 * Deals with the settings when they are saved by the admin. Here is
	 * where any validation should be dealt with.
	 *
	 * @param array $new_instance New configuration values.
	 * @param array $old_instance Old configuration values.
	 *
	 * @return array $instance Instance of settings to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();

		$instance['title']           = wp_kses( $new_instance['title'], array() );
		$instance['display_excerpt'] = isset( $new_instance['display_excerpt'] ) ? (bool) $new_instance['display_excerpt'] : false;
		$instance['display_image']   = isset( $new_instance['display_image'] ) ? (bool) $new_instance['display_image'] : false;

		// We allow numbers between 1 and 50.
		$instance['number'] = isset( $new_instance['number'] ) ? absint( $new_instance['number'] ) : 5;
		if ( $instance['number'] < 1 || 50 < $instance['number'] ) {
			$instance['number'] = 5;
		}

		// We only allow Event types that match what's existing on the site.
		$allowed_type_names = get_terms(
			array(
				'taxonomy'   => 'trakt_type',
				'hide_empty' => true,
				'fields'     => 'names',
			)
		);
		$instance['type']   = isset( $new_instance['type'] ) ? $new_instance['type'] : $allowed_type_names;
		foreach ( $instance['type'] as $key => $term_name ) {
			if ( ! in_array( $term_name, $allowed_type_names, true ) ) {
				unset( $instance['type'][ $key ] );
			}
		}

		// Return settings to be saved.
		return $instance;
	}


	/**
	 * Displays the form for this widget on the Widgets page of the WP Admin area.
	 *
	 * @param array $instance Instance configuration.
	 *
	 * @return string
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( $instance, $this->defaults() );

		$allowed_event_types = get_terms(
			array(
				'taxonomy'   => 'trakt_type',
				'hide_empty' => true,
				'fields'     => 'names',
			)
		);
		$event_types         = isset( $instance['type'] ) ? (array) $instance['type'] : $allowed_event_types;

		?>
		<!-- Title -->
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'traktivity' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>

		<!-- Event Type -->
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"><?php esc_html_e( 'Types of events to display:', 'traktivity' ); ?></label>
			<ul>
				<?php
				foreach ( $allowed_event_types as $type ) {
					?>

					<li><label>
						<input value="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>[]" id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>-<?php echo esc_attr( $type ); ?>" type="checkbox" <?php checked( in_array( $type, $event_types, true ) ); ?>>
						<?php echo esc_html( $type ); ?>
					</label></li>

				<?php } ?>
			</ul>
		</p>

		<!-- Number of items to display. -->
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'number' ) ); ?>"><?php esc_html_e( 'Number of events to show (no more than 50):', 'traktivity' ); ?></label>
			<input id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'number' ) ); ?>" type="number" value="<?php echo (int) $instance['number']; ?>" min="1" max="50" />
		</p>

		<!-- Display event excerpt -->
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'display_excerpt' ) ); ?>"><?php esc_html_e( 'Display event excerpt:', 'traktivity' ); ?></label>
			<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'display_excerpt' ) ); ?>" <?php checked( $instance['display_excerpt'], 1 ); ?> />
		</p>

		<!-- Display event image -->
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'display_image' ) ); ?>"><?php esc_html_e( 'Display event image:', 'traktivity' ); ?></label>
			<input type="checkbox" name="<?php echo esc_attr( $this->get_field_name( 'display_image' ) ); ?>" <?php checked( $instance['display_image'], 1 ); ?> />
		</p>

		<?php
		return '';
	}

	/**
	 * Display a single Traktivity event in the widget.
	 *
	 * @param array $instance Instance of widget settings.
	 * @param int   $post_id  Post ID.
	 *
	 * @return string $event HTML for one event.
	 */
	public function display_event( $instance, $post_id ) {
		$event = '<div class="traktivity-display-event">';

		$event_title = sprintf(
			'<h3 class="traktivity-event-title"><a href="%1$s" title="%2$s">%3$s</a></h3>',
			esc_url( get_the_permalink() ),
			the_title_attribute(
				array(
					'echo' => false,
				)
			),
			esc_html( get_the_title() )
		);

		/**
		 * Filter the Event title.
		 *
		 * @since 1.2.0
		 *
		 * @param string $event_title  HTML output for the event title.
		 * @param int    $post_id Post ID.
		 */
		$event_title = apply_filters( 'traktivity_list_widget_single_event_title', $event_title, $post_id );

		$event .= $event_title;

		if ( true === $instance['display_image'] ) {
			$event .= get_the_post_thumbnail( $post_id, 'large' );
		}

		if ( true === $instance['display_excerpt'] ) {
			$event .= '<p>' . wp_kses_post( get_the_excerpt() ) . '</p>';
		}

		$event .= '</div>';

		/**
		 * Filter the output of each event in the Traktivity List Widget.
		 *
		 * @since 1.2.0
		 *
		 * @param string $event    HTML for one event.
		 * @param array  $instance Instance of widget settings.
		 * @param int    $post_id  Post ID.
		 */
		return apply_filters( 'traktivity_list_widget_event_output', $event, $instance, $post_id );
	}

	/**
	 * Custom event title for TV episodes.
	 * Episode titles often aren't really well known. When the event is a TV episode,
	 * we'll add a new div including the show title, as well as the season and episode numbers.
	 *
	 * @since 1.2.0
	 *
	 * @param string $event_title HTML output for the event title.
	 * @param int    $post_id     Post ID.
	 *
	 * @return string Event title, with episode details appended when we have them.
	 */
	public function custom_tv_event_title( $event_title, $post_id ) {
		$event = traktivity_get_event( (int) $post_id );

		/*
		 * This used to test has_term( 'TV Series', 'trakt_type' ) and walk the
		 * show, season and episode taxonomies by hand. The term name is
		 * translated when the sync creates it, so that test only ever matched
		 * on an English site, and every episode on a translated install lost
		 * its details here.
		 */
		if (
			'tv' !== $event['type']
			|| '' === $event['show_name']
			|| '' === $event['show_link']
			|| '' === $event['episode_code']
		) {
			return $event_title;
		}

		$show_title = sprintf(
			'<a href="%1$s">%2$s</a>',
			esc_url( $event['show_link'] ),
			esc_html( $event['show_name'] )
		);

		$event_title .= '<div class="episode-details">';

		$event_title .= sprintf(
			/* translators: 1: link to the show. 2: season number. 3: episode number. */
			_x(
				'%1$s, season %2$d, episode %3$d',
				'Episode details listed under each event in the recent watches widget',
				'traktivity'
			),
			$show_title,
			absint( $event['season'] ),
			absint( $event['episode'] )
		);

		$event_title .= '</div>';

		return $event_title;
	}
}
