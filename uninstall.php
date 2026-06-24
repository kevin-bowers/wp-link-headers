<?php
/**
 * Uninstall handler — runs when the plugin is deleted from WordPress.
 *
 * Removes every option the plugin created. The plugin's code is NOT loaded at
 * uninstall time, so the option name is hardcoded here rather than referencing
 * the WP_LINK_HEADERS_OPTION constant.
 *
 * @package WP_Link_Headers
 */

// Exit if WordPress did not invoke this file as an uninstaller.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

const WP_LINK_HEADERS_UNINSTALL_OPTION = 'wp_link_headers_entries';

/**
 * Delete the plugin's option from a single site.
 */
function wp_link_headers_delete_site_data(): void {
	delete_option( WP_LINK_HEADERS_UNINSTALL_OPTION );
}

if ( is_multisite() ) {
	// Remove the option from every site in the network.
	$site_ids = get_sites( [
		'fields' => 'ids',
		'number' => 0,
	] );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		wp_link_headers_delete_site_data();
		restore_current_blog();
	}
} else {
	wp_link_headers_delete_site_data();
}
