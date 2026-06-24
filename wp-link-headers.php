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
require_once WP_LINK_HEADERS_DIR . 'includes/class-link-headers-installer.php';

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

// Register WP-CLI commands: `wp link-headers <subcommand>`.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once WP_LINK_HEADERS_DIR . 'includes/class-link-headers-cli.php';
	WP_CLI::add_command( 'link-headers', 'WP_Link_Headers_CLI' );
}

// In a network where the plugin is network-active, seed each new site.
add_action( 'wp_initialize_site', [ 'WP_Link_Headers_Installer', 'on_new_site' ], 900, 1 );

/**
 * Activation hook — seed default entries (home page, RSS feed, sitemap, llms.txt).
 *
 * Receives WordPress's $network_wide flag so a network activation seeds every
 * site. Reactivation leaves any existing per-site configuration untouched.
 *
 * @param bool $network_wide True when network-activated.
 */
register_activation_hook( __FILE__, function ( $network_wide ): void {
	require_once WP_LINK_HEADERS_DIR . 'includes/class-link-headers-installer.php';
	WP_Link_Headers_Installer::activate( (bool) $network_wide );
} );
