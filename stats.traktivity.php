<?php
/**
 * Stats functions.
 *
 * @package Traktivity
 */

defined( 'ABSPATH' ) || die( 'No script kiddies please!' );


/**
 * Build Stats from existing Traktivity data.
 *
 * @since 2.2.0
 */
class Traktivity_Stats {

	/**
	 * Convert minutes into string of days, hours, and minutes.
	 *
	 * @since 2.2.0
	 *
	 * @param string|int $minutes Number of minutes.
	 *
	 * @return string $runtime Time.
	 */
	public static function convert_time( $minutes = 0 ) {
		$minutes_per_hour = 60;
		$minutes_per_day  = 24 * $minutes_per_hour;
		$minutes_per_year = 365 * $minutes_per_day;

		// Get number of years.
		$years = (int) floor( $minutes / $minutes_per_year );

		// Get number of days.
		$days_minutes = $minutes % $minutes_per_year;
		$days         = (int) floor( $days_minutes / $minutes_per_day );

		// Get number of hours.
		$hour_minutes = $minutes % $minutes_per_day;
		$hours        = (int) floor( $hour_minutes / $minutes_per_hour );

		// Get the minutes left.
		$minutes_left = $hour_minutes % $minutes_per_hour;
		$minutes      = (int) ceil( $minutes_left );

		if ( 0 < $minutes ) {
			$display_minutes = sprintf(
				/* Translators: %1$d is the number of minutes */
				_n(
					'%1$d minute',
					'%1$d minutes',
					$minutes,
					'traktivity'
				),
				$minutes
			);
		} else {
			$display_minutes = '';
		}

		if ( 0 < $hours ) {
			$display_hours = sprintf(
				/* Translators: %1$d is the number of hours, %2$d is the number of minutes. */
				_n(
					'%1$d hour %2$s',
					'%1$d hours %2$s',
					$hours,
					'traktivity'
				),
				$hours,
				$display_minutes
			);
		} else {
			$display_hours = $display_minutes;
		}

		if ( 0 < $days ) {
			$display_days = sprintf(
				/* Translators: %1$d is the number of days, %2$s is the number of hours and minutes. */
				_n(
					'%1$d day %2$s',
					'%1$d days %2$s',
					$days,
					'traktivity'
				),
				$days,
				$display_hours
			);
		} else {
			$display_days = $display_hours;
		}

		if ( 0 < $years ) {
			$runtime = sprintf(
				/* Translators: %1$d is the number of years, %2$s is the number of days, hours and minutes. */
				_n(
					'%1$d year %2$s',
					'%1$d years %2$s',
					$years,
					'traktivity'
				),
				$years,
				$display_days
			);
		} else {
			$runtime = $display_days;
		}

		/*
		 * Each unit is placed with a trailing space so the next one can follow,
		 * which leaves a space behind whenever a unit is zero. "1 day " then
		 * lands mid-sentence on the dashboard as a double space.
		 */
		return trim( preg_replace( '/\s+/', ' ', $runtime ) );
	}

	/**
	 * The shape every stats summary carries.
	 *
	 * Returned in full on any site, so a caller can read a key without
	 * checking it exists. A site that has never synced reports zeroes and an
	 * empty `since`, which is how a block tells "nothing logged yet" from
	 * "nothing to show".
	 *
	 * @since 3.1.0
	 *
	 * @return array{
	 *     minutes: int, hours: int, runtime: string, entries: int,
	 *     episodes: int, films: int, shows: int, since: string, since_iso: string
	 * } Empty summary.
	 */
	public static function empty_summary() {
		return array(
			'minutes'   => 0,
			'hours'     => 0,
			'runtime'   => '',
			'entries'   => 0,
			'episodes'  => 0,
			'films'     => 0,
			'shows'     => 0,
			'since'     => '',
			'since_iso' => '',
		);
	}

	/**
	 * Name of the transient holding the cached summary.
	 *
	 * @var string
	 */
	const SUMMARY_TRANSIENT = 'traktivity_stats_summary';

	/**
	 * Whether the cache has already been dropped this request.
	 *
	 * A sync inserts hundreds of events in one request, each firing save_post.
	 * Without this, every one of them would drop the cache and force the
	 * running total to be recounted.
	 *
	 * @var bool
	 */
	private static $flushed_this_request = false;

	/**
	 * Count published events matching an ID meta key.
	 *
	 * Counting through the trakt_type taxonomy looks tempting and is wrong:
	 * the sync names those terms with a translated string, so the 'movie' slug
	 * only exists on an English site. The ID meta keys are the same in every
	 * language, and match how traktivity_get_event() decides an event's type,
	 * so the counts here agree with what a block renders.
	 *
	 * @since 3.1.0
	 *
	 * Counting is a plain query rather than wp_count_posts(), which reads a
	 * cache that is not reliably dropped when a post is deleted: right after
	 * wp_delete_post() it still reports the old total, while the query below
	 * reports the real one. Since the figures here are cached for half a day
	 * anyway, there is nothing to gain by reading a stale cache faster.
	 *
	 * @param string $meta_key Meta key that must exist, or an empty string to
	 *                         count every published event.
	 *
	 * @return int Number of published events.
	 */
	private static function count_events( $meta_key = '' ) {
		$args = array(
			'post_type'              => 'traktivity_event',
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'fields'                 => 'ids',
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( '' !== $meta_key ) {
			$args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One indexed meta key, and the result is cached for half a day.
				array(
					'key'     => $meta_key,
					'compare' => 'EXISTS',
				),
			);
		}

		$query = new WP_Query( $args );

		return (int) $query->found_posts;
	}

	/**
	 * Headline figures for everything logged.
	 *
	 * `runtime` is the convert_time() string, ready to print. `since` is the
	 * date of the oldest event in the site's own date format, with `since_iso`
	 * alongside it for anything that needs to reformat or sort.
	 *
	 * These change only when a sync runs, so the whole set is cached rather
	 * than recomputed per request.
	 *
	 * @since 3.1.0
	 *
	 * @return array Summary, in the shape of empty_summary().
	 */
	public static function get_summary() {
		$cached = get_transient( self::SUMMARY_TRANSIENT );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$minutes = self::total_time_watched();
		$shows   = wp_count_terms(
			array(
				'taxonomy'   => 'trakt_show',
				'hide_empty' => true,
			)
		);

		$oldest = get_posts(
			array(
				'post_type'      => 'traktivity_event',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'ASC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		$summary = array(
			'minutes'   => $minutes,
			'hours'     => (int) floor( $minutes / 60 ),
			'runtime'   => self::convert_time( $minutes ),
			'entries'   => self::count_events(),
			'episodes'  => self::count_events( 'trakt_show_id' ),
			'films'     => self::count_events( 'trakt_movie_id' ),
			'shows'     => is_wp_error( $shows ) ? 0 : (int) $shows,
			'since'     => empty( $oldest ) ? '' : (string) get_the_date( '', $oldest[0] ),
			'since_iso' => empty( $oldest ) ? '' : (string) get_the_date( 'c', $oldest[0] ),
		);

		/**
		 * Filter the headline figures for the site.
		 *
		 * Keys documented in empty_summary() are read by the plugin's own
		 * blocks, so add to the array rather than removing from it.
		 *
		 * @since 3.1.0
		 *
		 * @param array $summary Stats summary.
		 */
		$summary = (array) apply_filters( 'traktivity_stats_summary', $summary );

		/*
		 * A callback that drops a key would leave that hole in the cache for
		 * half a day, and callers read these keys without checking they exist,
		 * so anything missing goes back before the array is cached or returned.
		 */
		$summary = wp_parse_args( $summary, self::empty_summary() );

		set_transient( self::SUMMARY_TRANSIENT, $summary, 12 * HOUR_IN_SECONDS );

		return $summary;
	}

	/**
	 * Drop the cached figures so the next read recounts.
	 *
	 * The running total is reset rather than adjusted. Adjusting it means
	 * getting every path right forever, and a counter that is only ever nudged
	 * drifts as soon as one path is missed; deleting an event used to do
	 * exactly that, with no way to force a recount short of deleting the
	 * option by hand. A recount is one indexed query.
	 *
	 * @since 3.1.0
	 *
	 * @param bool $force Flush again even if this request already has.
	 */
	public static function flush( $force = false ) {
		if ( self::$flushed_this_request && ! $force ) {
			return;
		}

		self::$flushed_this_request = true;

		delete_transient( self::SUMMARY_TRANSIENT );

		$stats = get_option( 'traktivity_stats' );

		if ( is_array( $stats ) && isset( $stats['total_time_watched'] ) ) {
			unset( $stats['total_time_watched'] );
			update_option( 'traktivity_stats', $stats );
		}
	}

	/**
	 * Reset the once-per-request guard.
	 *
	 * Tests only: PHPUnit runs many requests' worth of work in one process, so
	 * without this the second flush in a suite would be a no-op.
	 *
	 * @since 3.1.0
	 */
	public static function reset_flush_guard() {
		self::$flushed_this_request = false;
	}

	/**
	 * Drop the cached figures when an event is added, changed or removed.
	 *
	 * @since 3.1.0
	 *
	 * @param int          $post_id Post ID.
	 * @param WP_Post|null $post    Post object, as passed by both hooks.
	 */
	public static function flush_on_event_change( $post_id, $post = null ) {
		/*
		 * deleted_post fires once the row is gone, so the object the hook
		 * hands over is the only thing guaranteed to still describe it. Today
		 * the lookup happens to work because core cleans the post cache after
		 * the hook rather than before, which is not something to rely on. The
		 * lookup stays as a fallback for anything firing this with one
		 * argument.
		 */
		$type = $post instanceof WP_Post ? $post->post_type : get_post_type( $post_id );

		if ( 'traktivity_event' === $type ) {
			self::flush();
		}
	}

	/**
	 * Create an option where we store the Total time spent in front of a screen.
	 *
	 * @since 2.2.0
	 *
	 * @return int $time Total time spent in front of a screen, in minutes.
	 */
	public static function total_time_watched() {
		$stats = get_option( 'traktivity_stats' );

		// If that's the first time we're running this function, let's start with an empty array of stats.
		if ( empty( $stats ) ) {
			$stats = array();
		}

		// If the total time is already set, let's stop here.
		if ( ! empty( $stats['total_time_watched'] ) ) {
			return (int) $stats['total_time_watched'];
		}

		// Let's pull all trakt_runtime post meta from all Traktivity events.
		global $wpdb;
		$post_meta = 'trakt_runtime';

		/*
		 * %s is deliberately unquoted: prepare() quotes string placeholders
		 * itself, so wrapping it here is redundant.
		 *
		 * The result is stored in the traktivity_stats option just below,
		 * which is what the rest of the plugin reads, so this uncached direct
		 * query runs at most once per stats refresh.
		 */
		$all_runtimes = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Result is cached in the traktivity_stats option.
			$wpdb->prepare(
				"
			SELECT pm.meta_value FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = %s
			AND p.post_status = 'publish'
			AND p.post_type = 'traktivity_event'
		",
				$post_meta
			)
		);

		if ( ! empty( $all_runtimes ) ) {
			$stats['total_time_watched'] = (int) array_sum( $all_runtimes );
			// Save the value as an option.
			update_option( 'traktivity_stats', $stats );

			return $stats['total_time_watched'];
		}

		// Fallback.
		return 0;
	}
}

add_action( 'save_post', array( 'Traktivity_Stats', 'flush_on_event_change' ), 10, 2 );
add_action( 'deleted_post', array( 'Traktivity_Stats', 'flush_on_event_change' ), 10, 2 );
