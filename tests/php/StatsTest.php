<?php
/**
 * Tests for the watch time formatter.
 *
 * @package Traktivity
 */

/**
 * Traktivity_Stats::convert_time().
 */
class StatsTest extends WP_UnitTestCase {

	/**
	 * A count of minutes reads as the units a person would use.
	 *
	 * @dataProvider data_durations
	 *
	 * @param int    $minutes  Minutes watched.
	 * @param string $expected Expected wording.
	 */
	public function test_converts_minutes_into_readable_units( $minutes, $expected ) {
		$this->assertSame( $expected, Traktivity_Stats::convert_time( $minutes ) );
	}

	/**
	 * Durations either side of each unit boundary, and the singular and
	 * plural of each, since every unit is pluralised separately.
	 *
	 * @return array[]
	 */
	public function data_durations() {
		return array(
			'one minute'         => array( 1, '1 minute' ),
			'several minutes'    => array( 45, '45 minutes' ),
			'exactly one hour'   => array( 60, '1 hour' ),
			'two hours'          => array( 120, '2 hours' ),
			'an hour and a half' => array( 90, '1 hour 30 minutes' ),
			'exactly one day'    => array( 1440, '1 day' ),
			'two days'           => array( 2880, '2 days' ),
			'exactly one year'   => array( 525600, '1 year' ),
			'every unit at once' => array( 527100, '1 year 1 day 1 hour' ),
		);
	}

	/**
	 * Nothing watched should not produce a fatal or a stray unit.
	 */
	public function test_zero_minutes_is_handled() {
		$this->assertSame( '', Traktivity_Stats::convert_time( 0 ) );
	}

	/**
	 * Units are joined with a trailing space so the next can follow, which
	 * used to leave one behind when a unit was zero and read as a double
	 * space mid-sentence on the dashboard.
	 */
	public function test_no_stray_whitespace() {
		foreach ( array( 60, 1440, 525600, 527100 ) as $minutes ) {
			$runtime = Traktivity_Stats::convert_time( $minutes );

			$this->assertSame( trim( $runtime ), $runtime, "Padding around '$runtime'." );
			$this->assertStringNotContainsString( '  ', $runtime, "Double space in '$runtime'." );
		}
	}

	/**
	 * The option is read as a count of minutes even when stored as a string.
	 */
	public function test_total_time_watched_returns_an_integer() {
		update_option( 'traktivity_stats', array( 'total_time_watched' => '4242' ) );

		$this->assertSame( 4242, Traktivity_Stats::total_time_watched() );

		delete_option( 'traktivity_stats' );
	}
}
