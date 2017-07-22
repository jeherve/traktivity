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
	function __construct() {
		$widget_ops = array(
			'classname' => 'traktivity_list_widget',
			'description' => esc_html__( "Display some of the recent things you've watched in a widget.", 'traktivity' ),
			'customize_selective_refresh' => true,
		);
		parent::__construct(
			'traktivity_list_widget',
			esc_html__( 'Event List (Traktivity)', 'traktivity' ),
			$widget_ops
		);

		if ( is_customize_preview() ) {
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		}

		// Customize event titles for TV series.
		add_action( 'traktivity_list_widget_single_event_title', array( $this, 'custom_tv_event_title' ), 20, 2 );
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'traktivity-list-widget', plugins_url( 'css/list-widget.css', __FILE__ ), array(), TRAKTIVITY__VERSION );
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
			'type'            => get_terms( array(
				'taxonomy'   => 'trakt_type',
				'hide_empty' => true,
				'fields'     => 'names',
			) ),
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
	function widget( $args, $instance ) {
		$instance = wp_parse_args( $instance, $this->defaults() );

		// Enqueue front end assets.
		$this->enqueue_scripts();

		echo $args['before_widget'];

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $instance['title'] );
		if ( ! empty( $title ) ) {
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
			'tax_query'   => array(
				array(
					'taxonomy' => 'trakt_type',
					'field'    => 'name',
					'terms'    => $instance['type'],
				),
			),
		);
		$query = new WP_Query( $events_args );

		if ( $query->have_posts() ) {
			echo '<div class="traktivity-display-events">';

			// Loop through the entries we should return.
			while ( $query->have_posts() ) {
				$query->the_post();

				// Display event.
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
	function update( $new_instance, $old_instance ) {
		$instance                    = array();

		$instance['title']           = wp_kses( $new_instance['title'], array() );
		$instance['display_excerpt'] = isset( $new_instance['display_excerpt'] ) ? (bool) $new_instance['display_excerpt'] : false;
		$instance['display_image']   = isset( $new_instance['display_image'] ) ? (bool) $new_instance['display_image'] : false;

		// We allow numbers between 1 and 50.
		$instance['number']          = isset( $new_instance['number'] ) ? absint( $new_instance['number'] ) : 5;
		if ( $instance['number'] < 1 || 50 < $instance['number'] ) {
			$instance['number'] = 5;
		}

		// We only allow Event types that match what's existing on the site.
		$allowed_type_names = get_terms( array(
			'taxonomy'   => 'trakt_type',
			'hide_empty' => true,
			'fields'     => 'names',
		) );
		$instance['type'] = isset( $new_instance['type'] ) ? $new_instance['type'] : $allowed_type_names;
		foreach ( $instance['type'] as $term_name ) {
			if ( ! in_array( $term_name, $allowed_type_names ) ) {
				unset( $instance['type'][ $term_name ] );
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
	 * @return void
	 */
	function form( $instance ) {
		$instance = wp_parse_args( $instance, $this->defaults() );

		$allowed_event_types = get_terms( array(
			'taxonomy'   => 'trakt_type',
			'hide_empty' => true,
			'fields'     => 'names',
		) );
		$event_types = isset( $instance['type'] ) ? (array) $instance['type'] : $allowed_event_types;

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
				<?php foreach ( $allowed_event_types as $type ) {
					$checked = '';
					if ( in_array( $type, $event_types ) ) {
						$checked = 'checked="checked" ';
					} ?>

					<li><label>
						<input value="<?php echo esc_attr( $type ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>[]" id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>-<?php echo esc_attr( $type ); ?>" type="checkbox" <?php echo esc_html( $checked ); ?>>
						<?php echo esc_html( $type ); ?>
					</label></li>

				<?php } // End foreach(). ?>
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
	}

	/**
	 * Display a single Traktivity event in the widget.
	 *
	 * @param array $instance Instance of widget settings.
	 * @param int   $post_id  Post ID.
	 *
	 * @return string $event HTML for one event.
	 */
	function display_event( $instance, $post_id ) {
		$event = '<div class="traktivity-display-event">';

		$event_title = sprintf(
			'<h3 class="traktivity-event-title"><a href="%1$s" title="%2$s">%3$s</a></h3>',
			get_the_permalink(),
			the_title_attribute( array(
				'echo' => false,
			) ),
			get_the_title()
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
			$event .= '<p>' . get_the_excerpt() . '</p>';
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
	 * Episode titles often aren't really well known. When the event type is TV series,
	 * we'll add a new div including the show title, as well as the season and episode numbers.
	 *
	 * @since 1.2.0
	 *
	 * @param string $event_title  HTML output for the event title.
	 * @param int    $post_id Post ID.
	 */
	function custom_tv_event_title( $event_title, $post_id ) {
		if ( has_term( 'TV Series', 'trakt_type', $post_id ) ) {
			// Show title.
			$show_title_terms = get_the_terms( $post_id , 'trakt_show' );
			if ( $show_title_terms && ! is_wp_error( $show_title_terms ) ) {
				// We only want to keep one element.
				$first_show = true;
				foreach ( $show_title_terms as $term ) {
					if ( $first_show ) {
						$show_title = sprintf(
							'<a href="%1$s">%2$s</a>',
							esc_url( get_term_link( $term->term_id, 'trakt_show' ) ),
							esc_html( $term->name )
						);
						// Other shows won't be the first ones, we won't need them.
						$first_show = false;
					}
				}
			} else {
				$show_title = '';
			}

			// Season number.
			$show_season_terms = get_the_terms( $post_id , 'trakt_season' );
			if ( $show_season_terms && ! is_wp_error( $show_season_terms ) ) {
				// We only want to keep one element.
				$first_season_num = true;
				foreach ( $show_season_terms as $term ) {
					if ( $first_season_num ) {
						$season_number = $term->name;
						// Other shows won't be the first ones, we won't need them.
						$first_season_num = false;
					}
				}
			} else {
				$season_number = '';
			}

			// Episode number.
			$show_episode_terms = get_the_terms( $post_id , 'trakt_episode' );
			if ( $show_episode_terms && ! is_wp_error( $show_episode_terms ) ) {
				// We only want to keep one element.
				$first_episode_num = true;
				foreach ( $show_episode_terms as $term ) {
					if ( $first_episode_num ) {
						$episode_number = $term->name;
						// Other shows won't be the first ones, we won't need them.
						$first_episode_num = false;
					}
				}
			} else {
				$episode_number = '';
			}

			// If we don't have extra info, don't display it.
			if (
				empty( $show_title )
				|| empty( $season_number )
				|| empty( $episode_number )
			) {
				return $event_title;
			}

			// Build our new event title, including extra info.
			$event_title .= '<div class="episode-details">';

			$event_title .= sprintf(
				/* translators: additional informaton about each episode displayed in the widget listing recent watched TV shows. */
				_x(
					'%1$s, season %2$d, episode %3$d',
					'1: Episode title. 2. Show title. 3. Season number. 4. Episode number.',
					'traktivity'
				),
				$show_title,
				absint( $season_number ),
				absint( $episode_number )
			);

			$event_title .= '</div>';

			return $event_title;
		} else {
			return $event_title;
		} // End if().
	}
}
