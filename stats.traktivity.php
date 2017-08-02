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
	 * Constructor
	 */
	function __construct() {}

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
		$minutes_per_day = 24 * $minutes_per_hour;
		$minutes_per_year = 365 * $minutes_per_day;

		// Get number of years.
		$years = floor( $minutes / $minutes_per_year );

		// Get number of days.
		$days_minutes = $minutes % $minutes_per_year;
		$days = floor( $days_minutes / $minutes_per_day );

		// Get number of hours.
		$hour_minutes = $minutes % $minutes_per_day;
		$hours = floor( $hour_minutes / $minutes_per_hour );

		// Get the minutes left.
		$minutes_left = $hour_minutes % $minutes_per_hour;
		$minutes = ceil( $minutes_left );

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
			return sprintf(
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

		return $runtime;
	}

	/**
	 * Create an option where we store the Total time spent in front of a screen.
	 *
	 * @since 2.2.0
	 *
	 * @return string $time Total time spent in front of a screen.
	 */
	public static function total_time_watched() {
		$stats = get_option( 'traktivity_stats' );

		// If that's the first time we're running this function, let's start with an empty array of stats.
		if ( empty( $stats ) ) {
			$stats = array();
		}

		// If the total time is already set, let's stop here.
		if ( ! empty( $stats['total_time_watched'] ) ) {
			return $stats['total_time_watched'];
		}

		// Let's pull all trakt_runtime post meta from all Traktivity events.
		global $wpdb;
		$post_meta = 'trakt_runtime';

		$all_runtimes = $wpdb->get_col( $wpdb->prepare( "
			SELECT pm.meta_value FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			WHERE pm.meta_key = '%s'
			AND p.post_status = 'publish'
			AND p.post_type = 'traktivity_event'
		", $post_meta ) );

		if ( ! empty( $all_runtimes ) ) {
			$stats['total_time_watched'] = array_sum( $all_runtimes );
			// Save the value as an option.
			update_option( 'traktivity_stats', $stats );

			return $stats['total_time_watched'];
		}

		// Fallback.
		return 0;
	}
} // End class.
new Traktivity_Stats();
