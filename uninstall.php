<?php
/**
 * Cleaning up on uninstall.
 *
 * @package Traktivity
 */

if (
	! defined( 'WP_UNINSTALL_PLUGIN' )
	|| ! WP_UNINSTALL_PLUGIN
	|| dirname( WP_UNINSTALL_PLUGIN ) != dirname( plugin_basename( __FILE__ ) )
) {
	exit;
}

// Delete option.
delete_option( 'traktivity' );
delete_option( 'traktivity_stats' );

// Remove scheduled API calls.
wp_clear_scheduled_hook( 'traktivity_publish' );
