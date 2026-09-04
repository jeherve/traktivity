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
	 *     episodes: int, films: int, shows: int, since: string
	 * } Empty summary.
	 */
	public static function empty_summary() {
		return array(
			'minutes'  => 0,
			'hours'    => 0,
			'runtime'  => '',
			'entries'  => 0,
			'episodes' => 0,
			'films'    => 0,
			'shows'    => 0,
			'since'    => '',
		);
	}

	/**
	 * Headline figures for everything logged.
	 *
	 * `runtime` is the `convert_time()` string, ready to print. `since` is the
	 * date of the oldest event, formatted for display.
	 *
	 * Not yet implemented: returns the empty shape. See issue #688.
	 *
	 * @since 3.1.0
	 *
	 * @return array Summary, in the shape of empty_summary().
	 */
	public static function get_summary() {
		return self::empty_summary();
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
