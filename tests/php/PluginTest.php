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
	 * Events are exposed to the REST API, which the dashboard's recent list
	 * reads through /wp/v2/traktivity_event.
	 */
	public function test_event_post_type_is_available_over_rest() {
		$post_type = get_post_type_object( 'traktivity_event' );

		$this->assertTrue( $post_type->show_in_rest );
	}
}
