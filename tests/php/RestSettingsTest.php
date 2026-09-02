<?php
/**
 * Tests for the settings REST endpoints.
 *
 * @package Traktivity
 */

/**
 * Credentials are stored as given, and never travel in a URL.
 */
class RestSettingsTest extends WP_UnitTestCase {

	/**
	 * Register the plugin's routes for each test.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( 'traktivity' );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		delete_option( 'traktivity' );
		parent::tear_down();
	}

	/**
	 * Save credentials as an administrator.
	 *
	 * @param string $username Trakt.tv username.
	 * @param string $key      Trakt.tv API key.
	 *
	 * @return WP_REST_Response
	 */
	private function save( $username, $key ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/traktivity/v1/settings/edit' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'trakt' => array(
						'username' => $username,
						'key'      => $key,
					),
				)
			)
		);

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * An underscore in an API key is the case reported in #679.
	 */
	public function test_stores_a_key_containing_an_underscore() {
		$key = 'sInBe5tDXUF8hPn7t_NUyCw4GkyVBONYWQQyyi8uIoI';

		$this->assertSame( 200, $this->save( 'je_herve', $key )->get_status() );

		$options = get_option( 'traktivity' );
		$this->assertSame( 'je_herve', $options['username'] );
		$this->assertSame( $key, $options['api_key'] );
	}

	/**
	 * Escaping a credential changes it, and the changed value is what gets
	 * sent to the API.
	 *
	 * @dataProvider data_awkward_credentials
	 *
	 * @param string $key A credential that used to be mangled on the way in.
	 */
	public function test_stores_credentials_without_altering_them( $key ) {
		$this->assertSame( 200, $this->save( 'jeherve', $key )->get_status() );

		$options = get_option( 'traktivity' );
		$this->assertSame( $key, $options['api_key'] );
	}

	/**
	 * Credentials that earlier versions stored in a changed form.
	 *
	 * @return array[]
	 */
	public function data_awkward_credentials() {
		return array(
			'underscore'     => array( 'abc_def_123' ),
			'ampersand'      => array( 'abc&def' ),
			'apostrophe'     => array( "abc'def" ),
			'angle brackets' => array( 'abc<def>' ),
			'quotes'         => array( 'abc"def' ),
		);
	}

	/**
	 * A value that cannot be a token is refused rather than stored.
	 *
	 * @dataProvider data_unusable_credentials
	 *
	 * @param string $key An unusable credential.
	 */
	public function test_refuses_credentials_that_cannot_be_tokens( $key ) {
		$response = $this->save( 'jeherve', $key );

		$this->assertSame( 400, $response->get_status() );
		$this->assertArrayNotHasKey( 'api_key', (array) get_option( 'traktivity' ) );
	}

	/**
	 * Values no API would ever issue.
	 *
	 * @return array[]
	 */
	public function data_unusable_credentials() {
		return array(
			'inner space' => array( 'has space' ),
			'tab'         => array( "tab\there" ),
			'newline'     => array( "line\nbreak" ),
			'null byte'   => array( "null\0byte" ),
		);
	}

	/**
	 * The credential checks read what was stored. Taking them as path
	 * segments wrote both keys into every server access log.
	 */
	public function test_credential_check_routes_take_no_parameters() {
		$routes = rest_get_server()->get_routes( 'traktivity/v1' );

		$this->assertArrayHasKey( '/traktivity/v1/connection', $routes );
		$this->assertArrayHasKey( '/traktivity/v1/tmdb', $routes );

		foreach ( array_keys( $routes ) as $route ) {
			$this->assertStringNotContainsString(
				'(?P<',
				$route,
				"Route $route takes a path parameter; credentials must not travel in a URL."
			);
		}
	}

	/**
	 * Settings are not readable or writable by just anyone.
	 */
	public function test_settings_require_a_capable_user() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$request  = new WP_REST_Request( 'GET', '/traktivity/v1/settings' );
		$response = rest_get_server()->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
	}
}
