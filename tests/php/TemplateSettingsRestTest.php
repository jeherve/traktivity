<?php
/**
 * Tests for the template settings endpoints.
 *
 * @package Traktivity
 */

/**
 * Reading and writing which templates are switched on.
 */
class TemplateSettingsRestTest extends WP_UnitTestCase {

	/**
	 * Stand up the REST server and start from a clean option.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( Traktivity_Templates::OPTION );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Make a request.
	 *
	 * @param string $method HTTP method.
	 * @param array  $body   Request body.
	 *
	 * @return WP_REST_Response
	 */
	private function request( $method, array $body = array() ) {
		$request = new WP_REST_Request( $method, '/traktivity/v1/templates' );

		if ( ! empty( $body ) ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Become an administrator.
	 */
	private function log_in_as_admin() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * A visitor cannot read the settings.
	 *
	 * These say what a site's admin screens look like, which is nobody else's
	 * business.
	 */
	public function test_reading_requires_a_capability() {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->request( 'GET' )->get_status() );
	}

	/**
	 * A subscriber cannot write them either.
	 */
	public function test_writing_requires_a_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = $this->request( 'POST', array( 'enabled' => array( 'single-traktivity_event' => true ) ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertFalse( Traktivity_Templates::is_enabled( 'single-traktivity_event' ) );
	}

	/**
	 * An administrator gets the full list, with everything off.
	 */
	public function test_reading_returns_everything_available() {
		$this->log_in_as_admin();

		$response = $this->request( 'GET' );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayHasKey( 'templates', $data );
		$this->assertArrayHasKey( 'isBlockTheme', $data );
		$this->assertArrayHasKey( 'themeStylesheet', $data );

		$slugs = wp_list_pluck( $data['templates'], 'slug' );

		$this->assertContains( 'single-traktivity_event', $slugs );
		$this->assertContains( 'traktivity-totals-on-archive', $slugs );

		foreach ( $data['templates'] as $template ) {
			$this->assertFalse( $template['enabled'], "{$template['slug']} is on by default." );
		}
	}

	/**
	 * Every entry says which kind it is, so the dashboard can link to it.
	 */
	public function test_entries_carry_their_type() {
		$this->log_in_as_admin();

		$types = wp_list_pluck( $this->request( 'GET' )->get_data()['templates'], 'type', 'slug' );

		$this->assertSame( 'wp_template', $types['single-traktivity_event'] );
		$this->assertSame( 'placement', $types['traktivity-totals-on-archive'] );
	}

	/**
	 * Switching one on persists it.
	 */
	public function test_writing_persists() {
		$this->log_in_as_admin();

		$response = $this->request(
			'POST',
			array( 'enabled' => array( 'archive-traktivity_event' => true ) )
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( Traktivity_Templates::is_enabled( 'archive-traktivity_event' ) );
		$this->assertFalse( Traktivity_Templates::is_enabled( 'single-traktivity_event' ) );
	}

	/**
	 * The response reports the stored state, not what was sent.
	 */
	public function test_response_reports_stored_state() {
		$this->log_in_as_admin();

		$data  = $this->request( 'POST', array( 'enabled' => array( 'taxonomy-trakt_show' => true ) ) )->get_data();
		$state = wp_list_pluck( $data['templates'], 'enabled', 'slug' );

		$this->assertTrue( $state['taxonomy-trakt_show'] );
		$this->assertFalse( $state['single-traktivity_event'] );
	}

	/**
	 * Switching everything off stores nothing rather than a list of falses.
	 */
	public function test_turning_everything_off() {
		$this->log_in_as_admin();

		$this->request( 'POST', array( 'enabled' => array( 'single-traktivity_event' => true ) ) );
		$this->request( 'POST', array( 'enabled' => array( 'single-traktivity_event' => false ) ) );

		$this->assertSame( array(), get_option( Traktivity_Templates::OPTION ) );
	}

	/**
	 * An unknown slug is dropped rather than stored.
	 *
	 * A key from an older version, or an invented one, would otherwise sit in
	 * the option forever.
	 */
	public function test_unknown_slugs_are_dropped() {
		$this->log_in_as_admin();

		$this->request(
			'POST',
			array(
				'enabled' => array(
					'single-traktivity_event' => true,
					'made-up-slug'            => true,
				),
			)
		);

		$this->assertSame( array( 'single-traktivity_event' => true ), get_option( Traktivity_Templates::OPTION ) );
	}

	/**
	 * A request with no list is rejected.
	 */
	public function test_missing_list_is_rejected() {
		$this->log_in_as_admin();

		$this->assertSame( 400, $this->request( 'POST' )->get_status() );
	}

	/**
	 * A template the theme already covers is flagged.
	 *
	 * A theme's own template wins, so offering to switch ours on without
	 * saying so would be offering nothing.
	 */
	public function test_theme_provided_templates_are_flagged() {
		$this->log_in_as_admin();

		$flags = wp_list_pluck( $this->request( 'GET' )->get_data()['templates'], 'themeProvides', 'slug' );

		$this->assertArrayHasKey( 'single-traktivity_event', $flags );
		$this->assertIsBool( $flags['single-traktivity_event'] );
		$this->assertFalse( $flags['traktivity-totals-on-archive'], 'A placement is never theme-provided.' );
	}
}
