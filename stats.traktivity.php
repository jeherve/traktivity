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
	 * @return string $duration Time.
	 */
	public static function convert_time( $minutes = 0 ) {
		$minutes_per_hour = 60;
		$minutes_per_day = 24 * $minutes_per_hour;

		// Get number of days.
		$days = floor( $minutes / $minutes_per_day );

		// Get number of hours.
		$hour_minutes = $minutes % $minutes_per_day;
		$hours = floor( $hour_minutes / $minutes_per_hour );

		// Get the minutes left.
		$minutes_left = $hour_minutes % $minutes_per_hour;
		$minutes = ceil( $minutes_left );

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

		// Build the final string.
		$duration = sprintf(
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

		// Finally, let's return the final result.
		return $duration;
	}

	/**
	 * Record Stats.
	 *
	 * While all data is attached to each individual post and can then easily be queried,
	 * We also store some overall data for easy access later on, that won't need us running complicated queries.
	 *
	 * @since 2.2.0
	 *
	 * @param string $post_id  Post ID.
	 * @param array  $meta     Array of Meta data added to the post.
	 * @param string $date     Event date.
	 */
	private function record_stats( $post_id, $meta, $date ) {
		$stats = get_option( 'traktivity_stats' );

		// If that's the first time we're running this function, let's start with an empty object of stats.
		if ( ! is_object( $stats ) ) {
			$stats = new stdClass();
		}

	   // What do I need to store?
	   /*
	   Some ideas:
	   http://docs.trakt.apiary.io/#reference/users/stats/get-stats
	   https://developer.wordpress.com/docs/api/1.1/get/sites/%24site/stats/
	   http://wpengineer.com/968/wordpress-working-with-options/
	   - stats.
		   time
			   total
			   year
				   2016
					   total
					   01

	   */

		update_option( 'traktivity_stats', $stats );
	}
} // End class.
new Traktivity_Stats();
