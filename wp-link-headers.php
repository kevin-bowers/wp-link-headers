<?php
/**
 * Plugin Name:       WP Link Headers
 * Plugin URI:        https://github.com/kevin-bowers/wp-link-headers
 * Description:       Add HTTP Link headers to selected pages and posts so AI agents and crawlers can discover your content.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Kevin Bowers
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-link-headers
 */

defined( 'ABSPATH' ) || exit;

define( 'WP_LINK_HEADERS_VERSION', '1.0.0' );
define( 'WP_LINK_HEADERS_FILE', __FILE__ );
define( 'WP_LINK_HEADERS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WP_LINK_HEADERS_URL', plugin_dir_url( __FILE__ ) );
define( 'WP_LINK_HEADERS_OPTION', 'wp_link_headers_entries' );

require_once WP_LINK_HEADERS_DIR . 'includes/class-link-headers-output.php';
require_once WP_LINK_HEADERS_DIR . 'includes/class-link-headers-admin.php';

/**
 * Bootstrap the plugin.
 */
function wp_link_headers_init(): void {
	$output = new WP_Link_Headers_Output();
	$output->register();

	if ( is_admin() ) {
		$admin = new WP_Link_Headers_Admin();
		$admin->register();
	}
}
add_action( 'plugins_loaded', 'wp_link_headers_init' );

/**
 * Activation hook — seed the option with the home page as the first entry.
 *
 * Only runs on first activation; reactivation leaves existing entries untouched.
 */
register_activation_hook( __FILE__, function (): void {
	if ( false !== get_option( WP_LINK_HEADERS_OPTION ) ) {
		return; // Already configured — don't overwrite.
	}

	$entry = [
		'rel'        => 'describedby',
		'rel_custom' => '',
		'link_type'  => '',
		'link_title' => '',
		'enabled'    => true,
	];

	$front   = get_option( 'show_on_front', 'posts' );
	$page_id = (int) get_option( 'page_on_front', 0 );

	if ( 'page' === $front && $page_id > 0 ) {
		// Static front page — store the post ID so the URL stays correct
		// even if the site's permalink structure changes later.
		$entry['source']  = 'page';
		$entry['post_id'] = $page_id;
		$entry['label']   = get_the_title( $page_id ) ?: __( 'Home', 'wp-link-headers' );
		$entry['url']     = '';
	} else {
		// Blog posts index or any other "home" configuration — store the URL directly.
		$entry['source']  = 'custom';
		$entry['post_id'] = 0;
		$entry['label']   = __( 'Home', 'wp-link-headers' );
		$entry['url']     = home_url( '/' );
	}

	add_option( WP_LINK_HEADERS_OPTION, [ $entry ] );
} );
