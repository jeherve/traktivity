<?php
/**
 * Tests that the plugin loads and describes itself consistently.
 *
 * @package Traktivity
 */

/**
 * Plugin bootstrapping.
 */
class PluginTest extends WP_UnitTestCase {

	/**
	 * The version constant and the plugin header have to agree. WordPress.org
	 * reads the header, while the plugin uses the constant to bust asset
	 * caches, so a mismatch ships stale assets to everyone who updates.
	 */
	public function test_version_constant_matches_the_plugin_header() {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		$header = get_plugin_data( dirname( __DIR__, 2 ) . '/traktivity.php', false, false );

		$this->assertSame( $header['Version'], TRAKTIVITY__VERSION );
	}

	/**
	 * The event post type and its taxonomies are registered.
	 */
	public function test_post_type_and_taxonomies_are_registered() {
		$this->assertTrue( post_type_exists( 'traktivity_event' ) );

		foreach ( array( 'trakt_type', 'trakt_genre', 'trakt_year', 'trakt_show', 'trakt_season', 'trakt_episode' ) as $taxonomy ) {
			$this->assertTrue( taxonomy_exists( $taxonomy ), "Missing taxonomy $taxonomy." );
		}
	}

	/**
	 * A site that last ran a version whose full sync only ever imported part
	 * of the history gets that sync's status cleared, so the dashboard offers
	 * to run it again rather than reporting a partial import as complete.
	 */
	public function test_upgrade_clears_a_full_sync_status_from_before_3_0_1() {
		update_option(
			'traktivity',
			array(
				'username'  => 'jeherve',
				'full_sync' => array(
					'status' => 'done',
					'pages'  => 0,
				),
			)
		);

		Traktivity::get_instance()->maybe_upgrade();

		$options = get_option( 'traktivity' );

		$this->assertArrayNotHasKey( 'status', $options['full_sync'] );
		$this->assertArrayNotHasKey( 'pages', $options['full_sync'] );
		$this->assertSame( 'jeherve', $options['username'], 'The rest of the settings should be left alone.' );
		$this->assertSame( TRAKTIVITY__VERSION, $options['version'] );
	}

	/**
	 * The same option tracks the separate recalculation of each show's total
	 * runtime. That one was never broken, so clearing the history sync has to
	 * leave it be, or finished work gets reported as never run.
	 */
	public function test_upgrade_keeps_the_total_runtime_status() {
		update_option(
			'traktivity',
			array(
				'full_sync' => array(
					'status'  => 'done',
					'pages'   => 0,
					'runtime' => array(
						'status' => 'done',
						'items'  => 0,
					),
				),
			)
		);

		Traktivity::get_instance()->maybe_upgrade();

		$options = get_option( 'traktivity' );

		$this->assertSame( 'done', $options['full_sync']['runtime']['status'] );
		$this->assertArrayNotHasKey( 'status', $options['full_sync'], 'The history sync status should still be cleared.' );
	}

	/**
	 * Having run the routine once, a plain page load leaves a sync that is
	 * genuinely finished alone.
	 */
	public function test_upgrade_leaves_a_current_install_alone() {
		update_option(
			'traktivity',
			array(
				'version'   => TRAKTIVITY__VERSION,
				'full_sync' => array(
					'status' => 'done',
					'pages'  => 0,
				),
			)
		);

		Traktivity::get_instance()->maybe_upgrade();

		$options = get_option( 'traktivity' );

		$this->assertSame( 'done', $options['full_sync']['status'] );
	}

	/**
	 * Events are exposed to the REST API, which the dashboard's recent list
	 * reads through /wp/v2/traktivity_event.
	 */
	public function test_event_post_type_is_available_over_rest() {
		$post_type = get_post_type_object( 'traktivity_event' );

		$this->assertTrue( $post_type->show_in_rest );
	}
}
