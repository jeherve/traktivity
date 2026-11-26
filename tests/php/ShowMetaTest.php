<?php
/**
 * Tests for the show term meta accessors.
 *
 * @package Traktivity
 */

/**
 * Reading what the sync stores against each show.
 */
class ShowMetaTest extends WP_UnitTestCase {

	/**
	 * Re-register the show meta.
	 *
	 * WP_UnitTestCase::tear_down() calls unregister_all_meta_keys(), so
	 * whatever the bootstrap's init registered is gone by the time any test
	 * after the first one runs. Registering again here tests the function
	 * rather than the harness; test_registration_is_hooked() covers the wiring
	 * that gets it called on a real site.
	 */
	public function set_up() {
		parent::set_up();
		traktivity_register_show_meta();
	}

	/**
	 * The registration runs on init, so a real site gets it.
	 */
	public function test_registration_is_hooked() {
		$this->assertNotFalse( has_action( 'init', 'traktivity_register_show_meta' ) );
	}

	/**
	 * Create a show term.
	 *
	 * @param array $meta Term meta to attach.
	 *
	 * @return int Term ID.
	 */
	private function make_show( array $meta = array() ) {
		$term = self::factory()->term->create_and_get(
			array(
				'taxonomy' => 'trakt_show',
				'name'     => 'Some Show',
			)
		);

		foreach ( $meta as $key => $value ) {
			update_term_meta( $term->term_id, $key, $value );
		}

		return $term->term_id;
	}

	/**
	 * All four keys are registered, so they reach the REST API with a schema.
	 *
	 * @dataProvider data_registered_keys
	 *
	 * @param string $key Meta key.
	 */
	public function test_meta_is_registered( $key ) {
		$registered = get_registered_meta_keys( 'term', 'trakt_show' );

		$this->assertArrayHasKey( $key, $registered );
		$this->assertTrue( $registered[ $key ]['show_in_rest'] || is_array( $registered[ $key ]['show_in_rest'] ) );
	}

	/**
	 * The meta keys the sync writes.
	 *
	 * @return array<string, array{string}>
	 */
	public function data_registered_keys() {
		return array(
			'runtime'      => array( 'show_runtime' ),
			'network'      => array( 'show_network' ),
			'poster'       => array( 'show_poster' ),
			'external ids' => array( 'show_external_ids' ),
		);
	}

	/**
	 * A stored image comes back normalised, in a fixed key order.
	 */
	public function test_poster_is_normalised() {
		$term_id = $this->make_show(
			array(
				'show_poster' => array(
					'id'  => '42',
					'url' => 'https://example.com/still.jpg',
					'alt' => 'Some Show',
				),
			)
		);

		$poster = traktivity_get_show_poster( $term_id );

		$this->assertSame( array( 'id', 'url', 'alt' ), array_keys( $poster ) );
		$this->assertSame( 42, $poster['id'] );
		$this->assertSame( 'https://example.com/still.jpg', $poster['url'] );
	}

	/**
	 * A partial stored image still comes back whole.
	 *
	 * The sync stores whatever the sideload returned, and that can be an
	 * attachment with no URL if the lookup came back empty. Normalising here
	 * saves every caller from repeating the shape check.
	 */
	public function test_partial_poster_is_filled_in() {
		$term_id = $this->make_show( array( 'show_poster' => array( 'id' => 7 ) ) );

		$this->assertSame(
			array(
				'id'  => 7,
				'url' => '',
				'alt' => '',
			),
			traktivity_get_show_poster( $term_id )
		);
	}

	/**
	 * A show with no image, or a badly stored one, reports empty.
	 *
	 * @dataProvider data_empty_posters
	 *
	 * @param mixed $stored What the sync left behind.
	 */
	public function test_missing_poster_reports_empty( $stored ) {
		$term_id = $this->make_show();

		if ( null !== $stored ) {
			update_term_meta( $term_id, 'show_poster', $stored );
		}

		$this->assertSame( array(), traktivity_get_show_poster( $term_id ) );
	}

	/**
	 * Stored values that should all read as "no image".
	 *
	 * @return array<string, array{mixed}>
	 */
	public function data_empty_posters() {
		return array(
			'nothing stored' => array( null ),
			'empty array'    => array( array() ),
			'no id'          => array( array( 'url' => 'https://example.com/x.jpg' ) ),
			'zero id'        => array( array( 'id' => 0 ) ),
		);
	}

	/**
	 * Network and runtime read back as the types callers expect.
	 */
	public function test_network_and_runtime() {
		$term_id = $this->make_show(
			array(
				'show_network' => 'Some Network',
				'show_runtime' => '480',
			)
		);

		$this->assertSame( 'Some Network', traktivity_get_show_network( $term_id ) );
		$this->assertSame( 480, traktivity_get_show_runtime( $term_id ) );
	}

	/**
	 * A show with nothing stored reports empty rather than warning.
	 */
	public function test_bare_show_reports_empty() {
		$term_id = $this->make_show();

		$this->assertSame( '', traktivity_get_show_network( $term_id ) );
		$this->assertSame( 0, traktivity_get_show_runtime( $term_id ) );
		$this->assertSame( array(), traktivity_get_show_poster( $term_id ) );
		$this->assertSame( array(), traktivity_get_show_links( $term_id ) );
	}

	/**
	 * A term that does not exist is handled like any other miss.
	 */
	public function test_unknown_term_reports_empty() {
		$this->assertSame( '', traktivity_get_show_network( 999999 ) );
		$this->assertSame( 0, traktivity_get_show_runtime( 999999 ) );
		$this->assertSame( array(), traktivity_get_show_poster( 999999 ) );
		$this->assertSame( array(), traktivity_get_show_links( 999999 ) );
	}

	/**
	 * Show links point at the show on each service.
	 */
	public function test_show_links() {
		$term_id = $this->make_show(
			array(
				'show_external_ids' => array(
					'trakt' => 111,
					'imdb'  => 'tt1234567',
					'tmdb'  => 222,
				),
			)
		);

		$links = traktivity_get_show_links( $term_id );

		$this->assertSame( array( 'trakt', 'imdb', 'tmdb' ), array_keys( $links ) );
		$this->assertStringContainsString( 'id_type=show', $links['trakt']['url'] );
		$this->assertSame( 'https://www.imdb.com/title/tt1234567/', $links['imdb']['url'] );
		$this->assertSame( 'https://www.themoviedb.org/tv/222', $links['tmdb']['url'] );
	}

	/**
	 * A service with nothing stored is absent rather than empty.
	 */
	public function test_partial_show_links() {
		$term_id = $this->make_show(
			array(
				'show_external_ids' => array(
					'trakt' => 333,
					'imdb'  => '',
				),
			)
		);

		$this->assertSame( array( 'trakt' ), array_keys( traktivity_get_show_links( $term_id ) ) );
	}

	/**
	 * Show links are filterable.
	 */
	public function test_show_links_are_filterable() {
		$term_id = $this->make_show( array( 'show_external_ids' => array( 'trakt' => 444 ) ) );

		add_filter(
			'traktivity_show_links',
			static function ( $links ) {
				$links['trakt']['label'] = 'Trakt.tv';
				return $links;
			}
		);

		$this->assertSame( 'Trakt.tv', traktivity_get_show_links( $term_id )['trakt']['label'] );
	}
}
