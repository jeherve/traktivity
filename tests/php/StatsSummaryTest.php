<?php
/**
 * Tests for the stats summary and its cache.
 *
 * @package Traktivity
 */

/**
 * Headline figures for everything logged.
 */
class StatsSummaryTest extends WP_UnitTestCase {

	/**
	 * Start each test from a cold cache and an unguarded flush.
	 */
	public function set_up() {
		parent::set_up();
		Traktivity_Stats::reset_flush_guard();
		Traktivity_Stats::flush( true );
	}

	/**
	 * Create a published event.
	 *
	 * @param string $id_key  Either trakt_show_id or trakt_movie_id.
	 * @param int    $runtime Minutes.
	 * @param string $date    Post date.
	 *
	 * @return int Post ID.
	 */
	private function make_event( $id_key, $runtime = 60, $date = '2026-03-04 20:00:00' ) {
		$post_id = self::factory()->post->create(
			array(
				'post_type'   => 'traktivity_event',
				'post_status' => 'publish',
				'post_date'   => $date,
			)
		);

		update_post_meta( $post_id, $id_key, 1 );
		update_post_meta( $post_id, 'trakt_runtime', $runtime );

		return $post_id;
	}

	/**
	 * A site with nothing logged reports zeroes rather than failing.
	 *
	 * Blocks use this to tell "nothing synced yet" from "nothing to show", so
	 * it needs to be a clean empty rather than an error.
	 */
	public function test_empty_site_reports_zeroes() {
		$this->assertSame( Traktivity_Stats::empty_summary(), Traktivity_Stats::get_summary() );
	}

	/**
	 * Episodes, films and totals are counted separately.
	 */
	public function test_counts() {
		$this->make_event( 'trakt_show_id', 60 );
		$this->make_event( 'trakt_show_id', 45 );
		$this->make_event( 'trakt_movie_id', 120 );

		Traktivity_Stats::flush( true );
		$summary = Traktivity_Stats::get_summary();

		$this->assertSame( 3, $summary['entries'] );
		$this->assertSame( 2, $summary['episodes'] );
		$this->assertSame( 1, $summary['films'] );
		$this->assertSame( 225, $summary['minutes'] );
		$this->assertSame( 3, $summary['hours'] );
		$this->assertNotSame( '', $summary['runtime'] );
	}

	/**
	 * Counts do not depend on the language the site runs in.
	 *
	 * The sync names trakt_type terms with a translated string, so counting
	 * through that taxonomy would report zero films on a French site. These
	 * count by ID meta instead, which matches how traktivity_get_event()
	 * decides an event's type.
	 */
	public function test_counts_survive_translated_type_terms() {
		$movie = $this->make_event( 'trakt_movie_id', 100 );
		wp_set_object_terms( $movie, array( 'Filme' ), 'trakt_type' );

		$episode = $this->make_event( 'trakt_show_id', 30 );
		wp_set_object_terms( $episode, array( 'Serie TV' ), 'trakt_type' );

		Traktivity_Stats::flush( true );
		$summary = Traktivity_Stats::get_summary();

		$this->assertSame( 1, $summary['films'] );
		$this->assertSame( 1, $summary['episodes'] );
	}

	/**
	 * Distinct shows are counted, and empty ones left out.
	 */
	public function test_show_count() {
		$post_id = $this->make_event( 'trakt_show_id' );
		wp_set_object_terms( $post_id, array( 'Some Show' ), 'trakt_show' );

		self::factory()->term->create(
			array(
				'taxonomy' => 'trakt_show',
				'name'     => 'Never Watched',
			)
		);

		Traktivity_Stats::flush( true );

		$this->assertSame( 1, Traktivity_Stats::get_summary()['shows'] );
	}

	/**
	 * The oldest event sets the "logging since" date, in both formats.
	 */
	public function test_since_uses_the_oldest_event() {
		$this->make_event( 'trakt_show_id', 60, '2022-08-15 10:00:00' );
		$this->make_event( 'trakt_show_id', 60, '2026-03-04 20:00:00' );

		Traktivity_Stats::flush( true );
		$summary = Traktivity_Stats::get_summary();

		$this->assertStringContainsString( '2022', $summary['since'] );
		$this->assertStringStartsWith( '2022-08-15', $summary['since_iso'] );
	}

	/**
	 * The summary is cached rather than recomputed per request.
	 */
	public function test_summary_is_cached() {
		$this->make_event( 'trakt_show_id' );
		Traktivity_Stats::flush( true );

		$first = Traktivity_Stats::get_summary();

		$this->assertIsArray( get_transient( 'traktivity_stats_summary' ) );

		// A second event, with no flush, must not show up while the cache stands.
		$this->make_event( 'trakt_show_id' );

		$this->assertSame( $first['entries'], Traktivity_Stats::get_summary()['entries'] );
	}

	/**
	 * Deleting an event no longer leaves a stale total.
	 *
	 * The running total used to be incremented on insert and never touched on
	 * delete, with no hook to invalidate it, so it drifted with no way back
	 * short of deleting the option by hand.
	 */
	public function test_deleting_an_event_corrects_the_total() {
		$keep   = $this->make_event( 'trakt_show_id', 60 );
		$remove = $this->make_event( 'trakt_show_id', 90 );

		Traktivity_Stats::flush( true );
		$this->assertSame( 150, Traktivity_Stats::get_summary()['minutes'] );

		Traktivity_Stats::reset_flush_guard();
		wp_delete_post( $remove, true );

		$this->assertSame( 60, Traktivity_Stats::get_summary()['minutes'] );
		$this->assertSame( 1, Traktivity_Stats::get_summary()['entries'] );
		$this->assertNotSame( 0, $keep );
	}

	/**
	 * Saving an event drops the cache too.
	 */
	public function test_saving_an_event_drops_the_cache() {
		Traktivity_Stats::flush( true );
		Traktivity_Stats::get_summary();

		Traktivity_Stats::reset_flush_guard();
		$this->make_event( 'trakt_show_id' );

		$this->assertFalse( get_transient( 'traktivity_stats_summary' ) );
	}

	/**
	 * A post of another type leaves the cache alone.
	 */
	public function test_other_post_types_leave_the_cache_alone() {
		$this->make_event( 'trakt_show_id' );
		Traktivity_Stats::flush( true );
		Traktivity_Stats::get_summary();

		Traktivity_Stats::reset_flush_guard();
		self::factory()->post->create( array( 'post_title' => 'A blog post' ) );

		$this->assertIsArray( get_transient( 'traktivity_stats_summary' ) );
	}

	/**
	 * A sync flushes once, not once per event.
	 *
	 * Hundreds of events land in a single request, and flushing on each would
	 * force the running total to be recounted every time.
	 */
	public function test_flush_runs_once_per_request() {
		Traktivity_Stats::flush( true );
		Traktivity_Stats::get_summary();
		Traktivity_Stats::reset_flush_guard();

		$this->make_event( 'trakt_show_id' );
		$this->assertFalse( get_transient( 'traktivity_stats_summary' ) );

		// Warm it again; the next save in the same request must not clear it.
		Traktivity_Stats::get_summary();
		$this->make_event( 'trakt_show_id' );

		$this->assertIsArray( get_transient( 'traktivity_stats_summary' ) );
	}

	/**
	 * The summary is filterable.
	 */
	public function test_summary_is_filterable() {
		add_filter(
			'traktivity_stats_summary',
			static function ( $summary ) {
				$summary['extra'] = 'added';
				return $summary;
			}
		);

		Traktivity_Stats::flush( true );

		$this->assertSame( 'added', Traktivity_Stats::get_summary()['extra'] );
	}
}
