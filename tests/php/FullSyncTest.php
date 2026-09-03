<?php
/**
 * Tests for the full history sync loop.
 *
 * @package Traktivity
 */

/**
 * Traktivity_Calls::full_sync().
 *
 * These tests stand a fake Trakt.tv in front of the sync and check which slice
 * of the history it actually walks. The fake paginates the way Trakt.tv does:
 * `limit` defaults to 100 when the caller omits it, and the page count in the
 * response header is derived from whatever limit ended up applying.
 */
class FullSyncTest extends WP_UnitTestCase {

	/**
	 * Total events in the fake history. Enough to need more than one run at
	 * Traktivity's batch size, so resuming gets exercised too.
	 *
	 * @var int
	 */
	private const HISTORY_SIZE = 1500;

	/**
	 * Trakt.tv's own default page size, applied when a request omits `limit`.
	 *
	 * @var int
	 */
	private const TRAKT_DEFAULT_LIMIT = 100;

	/**
	 * Every history request the sync made, as `array( page, limit )` pairs.
	 *
	 * @var array<int, array{page: int|null, limit: int}>
	 */
	private $requests = array();

	/**
	 * IDs of every event the fake Trakt.tv handed back to the sync.
	 *
	 * @var int[]
	 */
	private $served = array();

	/**
	 * Point the plugin at a username and key, and swap Trakt.tv for the fake.
	 */
	public function set_up() {
		parent::set_up();

		update_option(
			'traktivity',
			array(
				'username' => 'jeherve',
				'api_key'  => 'test-key',
			)
		);

		$this->requests = array();
		$this->served   = array();

		add_filter( 'pre_http_request', array( $this, 'fake_trakt' ), 10, 3 );
	}

	/**
	 * Drop the fake so it cannot leak into other tests.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'fake_trakt' ), 10 );

		parent::tear_down();
	}

	/**
	 * Stand in for Trakt.tv's /users/:id/history endpoint.
	 *
	 * @param false|array|WP_Error $response Preempted response. False to let the request through.
	 * @param array                $args     Request arguments.
	 * @param string               $url      Request URL.
	 *
	 * @return false|array Fake response, or false for anything that is not the history endpoint.
	 */
	public function fake_trakt( $response, $args, $url ) {
		if ( false === strpos( $url, 'api.trakt.tv' ) ) {
			return $response;
		}

		$query = array();
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		$limit = isset( $query['limit'] ) ? (int) $query['limit'] : self::TRAKT_DEFAULT_LIMIT;
		$page  = isset( $query['page'] ) ? (int) $query['page'] : null;

		$this->requests[] = array(
			'page'  => $page,
			'limit' => $limit,
		);

		/*
		 * Trakt.tv returns the history newest first, so page 1 holds the most
		 * recent events. Hand back bare objects: the sync skips events with no
		 * type, which keeps this about pagination rather than post creation.
		 */
		$offset = ( ( null === $page ? 1 : $page ) - 1 ) * $limit;
		$end    = min( $offset + $limit, self::HISTORY_SIZE );
		$body   = array();

		for ( $i = $offset; $i < $end; $i++ ) {
			$id             = $i + 1;
			$this->served[] = $id;
			$body[]         = array( 'id' => $id );
		}

		/*
		 * WP_Http hands headers back in a case-insensitive dictionary, and
		 * Trakt.tv sends these lowercase over HTTP/2 while the plugin reads
		 * them in title case. Build the same structure a real request would,
		 * so the test cannot pass on a casing the network would never produce.
		 */
		$headers = new WpOrg\Requests\Utility\CaseInsensitiveDictionary(
			array(
				'x-pagination-item-count' => (string) self::HISTORY_SIZE,
				'x-pagination-limit'      => (string) $limit,
				'x-pagination-page-count' => (string) (int) ceil( self::HISTORY_SIZE / $limit ),
			)
		);

		return array(
			'headers'  => $headers,
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
		);
	}

	/**
	 * Run the sync until it reports itself done, or until it stops making
	 * progress. Each run stands in for one firing of the cron event.
	 *
	 * @param int $max_runs Give up after this many runs, so a stalled sync fails the test rather than hanging it.
	 *
	 * @return int Number of runs it took.
	 */
	private function sync_to_completion( $max_runs = 20 ) {
		for ( $run = 1; $run <= $max_runs; $run++ ) {
			do_action( 'traktivity_full_sync' );

			$options = get_option( 'traktivity' );

			if ( isset( $options['full_sync']['status'] ) && 'done' === $options['full_sync']['status'] ) {
				return $run;
			}
		}

		$this->fail( sprintf( 'Sync did not finish within %d runs.', $max_runs ) );
	}

	/**
	 * The whole history ends up imported.
	 *
	 * Traktivity asks Trakt.tv how many pages of history there are, then walks
	 * that many pages. Both calls have to agree on a page size: ask for the
	 * count at Trakt.tv's default of 100 and then walk the pages at 10, and the
	 * loop only ever covers a tenth of the history before declaring itself
	 * done.
	 */
	public function test_sync_walks_the_entire_history() {
		$this->sync_to_completion();

		$missing = array_diff( range( 1, self::HISTORY_SIZE ), $this->served );

		$this->assertSame(
			array(),
			array_values( $missing ),
			sprintf( 'The sync never fetched %d of the %d events in the history.', count( $missing ), self::HISTORY_SIZE )
		);
	}

	/**
	 * The page-count request and the pages it walks use the same page size.
	 *
	 * This is the root cause of the partial import, stated directly: the count
	 * describes pages of a given size, so fetching pages of a different size
	 * makes the count mean nothing.
	 */
	public function test_page_count_request_and_page_requests_agree_on_limit() {
		$this->sync_to_completion();

		$limits = array_unique( wp_list_pluck( $this->requests, 'limit' ) );

		$this->assertCount(
			1,
			$limits,
			'The sync mixed page sizes: ' . implode( ', ', $limits ) . '.'
		);
	}

	/**
	 * A run that is cut short resumes where it stopped.
	 *
	 * The sync runs on cron and can be killed at any point. It records how many
	 * pages are left after each one, so the next run picks those up rather than
	 * starting the whole history over.
	 */
	public function test_progress_survives_a_run_that_does_not_finish() {
		do_action( 'traktivity_full_sync' );

		$options = get_option( 'traktivity' );

		$this->assertSame(
			'in_progress',
			$options['full_sync']['status'],
			'A history this size should take more than one run.'
		);
		$this->assertGreaterThan( 0, $options['full_sync']['pages'], 'Pages left should be recorded.' );

		$after_first_run = $options['full_sync']['pages'];
		$first_run_pages = wp_list_pluck( $this->requests, 'page' );

		do_action( 'traktivity_full_sync' );

		$options = get_option( 'traktivity' );

		$this->assertLessThan(
			$after_first_run,
			$options['full_sync']['pages'],
			'The second run should have fewer pages left than the first.'
		);

		$second_run_pages = array_slice( wp_list_pluck( $this->requests, 'page' ), count( $first_run_pages ) );

		$this->assertSame(
			array(),
			array_values( array_intersect( $first_run_pages, $second_run_pages ) ),
			'The second run re-fetched pages the first run had already done.'
		);
	}

	/**
	 * An unfinished run queues the next one, so the sync finishes on its own.
	 */
	public function test_an_unfinished_run_schedules_the_next_one() {
		do_action( 'traktivity_full_sync' );

		$this->assertNotFalse(
			wp_next_scheduled( 'traktivity_full_sync' ),
			'A sync with pages left should have queued another run.'
		);
	}

	/**
	 * A failed page-count request leaves no sync state behind, so the next run
	 * asks Trakt.tv again instead of sitting in progress with nothing to fetch.
	 */
	public function test_a_failed_page_count_request_does_not_start_a_sync() {
		remove_filter( 'pre_http_request', array( $this, 'fake_trakt' ), 10 );
		add_filter( 'pre_http_request', '__return_empty_array' );

		do_action( 'traktivity_full_sync' );

		remove_filter( 'pre_http_request', '__return_empty_array' );

		$options = get_option( 'traktivity' );

		$this->assertArrayNotHasKey( 'full_sync', $options );
	}
}
