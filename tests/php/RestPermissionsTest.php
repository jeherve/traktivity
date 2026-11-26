<?php
/**
 * Tests for who can reach the plugin's REST routes.
 *
 * @package Traktivity
 */

/**
 * Every route in the namespace is an admin route.
 *
 * These endpoints exist to drive the dashboard, which is behind
 * manage_options. Nothing here is part of the plugin's public surface: what a
 * site publishes about its watch history is the blocks and the archives, which
 * a site owner switches on deliberately.
 */
class RestPermissionsTest extends WP_UnitTestCase {

	/**
	 * Register the plugin's routes for each test.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( 'traktivity_stats' );

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Clean up.
	 */
	public function tear_down() {
		delete_option( 'traktivity_stats' );
		parent::tear_down();
	}

	/**
	 * Ask for the stats.
	 *
	 * @return WP_REST_Response
	 */
	private function get_stats() {
		return rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/traktivity/v1/stats' ) );
	}

	/**
	 * No route answers a caller who has not proved anything.
	 *
	 * The stats route used to, because '__return_true' was the quickest way to
	 * satisfy the permission callback WordPress 5.5 started insisting on. That
	 * published the traktivity_stats option to anyone who asked for it, on
	 * every site running the plugin, whether or not the site owner had
	 * switched on anything that shows those figures.
	 */
	public function test_no_route_is_open_to_everyone() {
		$routes = rest_get_server()->get_routes( 'traktivity/v1' );

		$this->assertNotEmpty( $routes, 'The plugin registered no routes.' );

		foreach ( $routes as $route => $handlers ) {
			// The namespace index itself is core's, and is meant to be readable.
			if ( '/traktivity/v1' === $route ) {
				continue;
			}

			foreach ( $handlers as $handler ) {
				/*
				 * A route with no callback at all is worse than one that
				 * returns true: WordPress lets the request through and only
				 * grumbles about it in the log.
				 */
				$this->assertArrayHasKey(
					'permission_callback',
					$handler,
					"Route $route checks nothing at all."
				);

				$this->assertNotSame(
					'__return_true',
					$handler['permission_callback'],
					"Route $route is readable by anyone."
				);
			}
		}
	}

	/**
	 * A visitor cannot read the stats.
	 */
	public function test_stats_are_not_readable_by_a_visitor() {
		wp_set_current_user( 0 );

		$this->assertSame( 401, $this->get_stats()->get_status() );
	}

	/**
	 * Neither can a logged-in user without the capability.
	 */
	public function test_stats_are_not_readable_by_a_subscriber() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( 403, $this->get_stats()->get_status() );
	}

	/**
	 * An administrator still gets them.
	 */
	public function test_stats_are_readable_by_an_administrator() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		update_option( 'traktivity_stats', array( 'total_time_watched' => 4242 ) );

		$response = $this->get_stats();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( array( 'total_time_watched' => 4242 ), $response->get_data() );
	}
}
