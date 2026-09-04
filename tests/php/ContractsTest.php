<?php
/**
 * Tests for the shared data contracts.
 *
 * @package Traktivity
 */

/**
 * The shapes blocks and templates are built against.
 *
 * These pin key sets and value types rather than behaviour, so that the
 * accessors in issues #684 to #688 can be filled in without silently changing
 * what a caller receives. A renamed or dropped key fails here, which is the
 * whole point: a block written against `episode_code` should not compile
 * cleanly against an implementation that ships `episodeCode`.
 *
 * Assert the exact key set, not just that expected keys are present. A new key
 * is a deliberate change to a documented contract, so it should mean updating
 * this file rather than slipping in unnoticed.
 */
class ContractsTest extends WP_UnitTestCase {

	/**
	 * Every shared accessor exists, with the arity callers rely on.
	 *
	 * @dataProvider data_accessors
	 *
	 * @param string $accessor Function name.
	 * @param int    $required Number of required parameters.
	 */
	public function test_accessor_exists( $accessor, $required ) {
		$this->assertTrue( function_exists( $accessor ), "{$accessor}() is missing." );

		$reflection = new ReflectionFunction( $accessor );
		$this->assertSame(
			$required,
			$reflection->getNumberOfRequiredParameters(),
			"{$accessor}() takes an unexpected number of required arguments."
		);
	}

	/**
	 * The shared accessors and how many arguments they require.
	 *
	 * @return array<string, array{string, int}>
	 */
	public function data_accessors() {
		return array(
			'event context' => array( 'traktivity_get_event', 1 ),
			'event title'   => array( 'traktivity_get_event_title', 1 ),
			'event links'   => array( 'traktivity_get_event_links', 1 ),
			'show poster'   => array( 'traktivity_get_show_poster', 1 ),
			'show network'  => array( 'traktivity_get_show_network', 1 ),
			'show runtime'  => array( 'traktivity_get_show_runtime', 1 ),
			'show links'    => array( 'traktivity_get_show_links', 1 ),
		);
	}

	/**
	 * An event context always carries the full documented key set.
	 */
	public function test_event_context_shape() {
		$expected = array(
			'type',
			'title',
			'permalink',
			'watched',
			'watched_iso',
			'runtime',
			'year',
			'image_id',
			'show_name',
			'show_link',
			'season',
			'episode',
			'episode_code',
		);

		$context = traktivity_empty_event_context();

		sort( $expected );
		$actual = array_keys( $context );
		sort( $actual );

		$this->assertSame( $expected, $actual, 'The event context key set changed.' );
	}

	/**
	 * Every event context value is the type callers assume.
	 */
	public function test_event_context_types() {
		$context = traktivity_empty_event_context();

		foreach ( array( 'type', 'title', 'permalink', 'watched', 'watched_iso', 'year', 'show_name', 'show_link', 'episode_code' ) as $key ) {
			$this->assertIsString( $context[ $key ], "Event context '{$key}' should be a string." );
		}

		foreach ( array( 'runtime', 'image_id', 'season', 'episode' ) as $key ) {
			$this->assertIsInt( $context[ $key ], "Event context '{$key}' should be an integer." );
		}
	}

	/**
	 * The empty context is what an event with nothing stored reports.
	 *
	 * Guards against an implementation that returns a partial array, or null,
	 * for a post it cannot describe.
	 */
	public function test_event_accessor_returns_the_full_shape() {
		$post_id = $this->factory->post->create();

		$this->assertSame(
			array_keys( traktivity_empty_event_context() ),
			array_keys( traktivity_get_event( $post_id ) ),
			'traktivity_get_event() returned a different shape to the documented one.'
		);
	}

	/**
	 * A composed title always says something.
	 *
	 * The `: string` return type is enforced by PHP, so the only thing worth
	 * pinning here is that a title never comes back empty for a post that has
	 * one. An implementation that composed show and episode but dropped the
	 * episode name would still satisfy the signature.
	 */
	public function test_event_title_is_not_empty() {
		$post_id = $this->factory->post->create( array( 'post_title' => 'A title' ) );

		$this->assertNotSame( '', traktivity_get_event_title( $post_id ) );
	}

	/**
	 * Link accessors return an array, keyed by service, with label and url.
	 *
	 * @dataProvider data_link_accessors
	 *
	 * @param string $accessor Function name.
	 */
	public function test_link_accessor_shape( $accessor ) {
		$links = $accessor( 0 );

		$this->assertIsArray( $links, "{$accessor}() should return an array." );

		foreach ( $links as $service => $link ) {
			$this->assertIsString( $service, 'Links are keyed by service name.' );
			$this->assertSame(
				array( 'label', 'url' ),
				array_keys( $link ),
				"{$accessor}() entries carry a label and a url."
			);
		}
	}

	/**
	 * The accessors that return external links.
	 *
	 * @return array<string, array{string}>
	 */
	public function data_link_accessors() {
		return array(
			'event links' => array( 'traktivity_get_event_links' ),
			'show links'  => array( 'traktivity_get_show_links' ),
		);
	}

	/**
	 * A populated show image is fully described, never half-filled.
	 *
	 * Callers test the array rather than picking through the stored value, so
	 * a partial result would push that shape-checking back out to them.
	 */
	public function test_show_poster_populated_shape() {
		$term = self::factory()->term->create_and_get( array( 'taxonomy' => 'trakt_show' ) );

		update_term_meta(
			$term->term_id,
			'show_poster',
			array(
				'id'  => 12,
				'url' => 'https://example.com/still.jpg',
				'alt' => 'Some Show',
			)
		);

		$this->assertSame( array( 'id', 'url', 'alt' ), array_keys( traktivity_get_show_poster( $term->term_id ) ) );
	}

	/**
	 * A show with nothing stored reports empty rather than failing.
	 *
	 * PHP enforces the `: string` and `: int` return types, so what is left to
	 * check is the behaviour on a term ID that does not exist: callers render
	 * these directly and an implementation that warned, or returned a stray
	 * `false`, would surface on the page.
	 */
	public function test_show_accessors_handle_a_missing_term() {
		$this->assertSame( '', traktivity_get_show_network( 0 ) );
		$this->assertSame( 0, traktivity_get_show_runtime( 0 ) );
		$this->assertSame( array(), traktivity_get_show_poster( 0 ) );
	}

	/**
	 * A stats summary always carries the full documented key set.
	 */
	public function test_stats_summary_shape() {
		$expected = array( 'minutes', 'hours', 'runtime', 'entries', 'episodes', 'films', 'shows', 'since', 'since_iso' );
		$actual   = array_keys( Traktivity_Stats::get_summary() );

		sort( $expected );
		sort( $actual );

		$this->assertSame( $expected, $actual, 'The stats summary key set changed.' );
	}

	/**
	 * Every stats summary value is the type callers assume.
	 */
	public function test_stats_summary_types() {
		$summary = Traktivity_Stats::get_summary();

		foreach ( array( 'minutes', 'hours', 'entries', 'episodes', 'films', 'shows' ) as $key ) {
			$this->assertIsInt( $summary[ $key ], "Stats summary '{$key}' should be an integer." );
		}

		foreach ( array( 'runtime', 'since', 'since_iso' ) as $key ) {
			$this->assertIsString( $summary[ $key ], "Stats summary '{$key}' should be a string." );
		}
	}
}
