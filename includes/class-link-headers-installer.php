<?php
/**
 * Installation / seeding logic.
 *
 * Centralizes the "default entries" used on activation, on new-site creation
 * in a network, and by the WP-CLI `seed` command.
 *
 * @package WP_Link_Headers
 */

defined( 'ABSPATH' ) || exit;

class WP_Link_Headers_Installer {

	/**
	 * Build the default set of entries for the *current* site.
	 *
	 * Returns four entries: home page, RSS feed, XML sitemap, and llms.txt.
	 * All URLs are resolved against the current blog, so this is safe to call
	 * inside a switch_to_blog() context.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function default_entries(): array {
		return [
			self::home_entry(),
			self::feed_entry(),
			self::sitemap_entry(),
			self::llms_entry(),
		];
	}

	/**
	 * Seed the option with the default entries, unless it already exists.
	 *
	 * @param bool $force When true, overwrite any existing configuration.
	 *
	 * @return bool True if entries were written, false if skipped.
	 */
	public static function seed( bool $force = false ): bool {
		$existing = get_option( WP_LINK_HEADERS_OPTION, false );

		if ( false !== $existing && ! $force ) {
			return false; // Already configured — leave it alone.
		}

		update_option( WP_LINK_HEADERS_OPTION, self::default_entries() );

		return true;
	}

	/**
	 * Activation handler. Seeds defaults network-wide or for a single site.
	 *
	 * @param bool $network_wide True when the plugin is network-activated.
	 */
	public static function activate( bool $network_wide ): void {
		if ( is_multisite() && $network_wide ) {
			foreach ( self::get_site_ids() as $site_id ) {
				switch_to_blog( $site_id );
				self::seed();
				restore_current_blog();
			}
			return;
		}

		self::seed();
	}

	/**
	 * When a new site is added to a network on which the plugin is network-active,
	 * seed that site with the default entries.
	 *
	 * Hooked to `wp_initialize_site` (fires after the new site's tables exist).
	 *
	 * @param WP_Site $new_site The newly created site.
	 */
	public static function on_new_site( $new_site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active_for_network( plugin_basename( WP_LINK_HEADERS_FILE ) ) ) {
			return; // Plugin is not network-active; nothing to do.
		}

		switch_to_blog( (int) $new_site->blog_id );
		self::seed();
		restore_current_blog();
	}

	// -------------------------------------------------------------------------
	// Individual default entries
	// -------------------------------------------------------------------------

	/**
	 * Home page entry (rel="describedby").
	 *
	 * Uses the static front page when one is configured (stored by post ID so
	 * the URL tracks permalink changes); otherwise falls back to home_url('/').
	 *
	 * @return array<string, mixed>
	 */
	private static function home_entry(): array {
		$entry = self::base_entry( 'describedby' );

		$front   = get_option( 'show_on_front', 'posts' );
		$page_id = (int) get_option( 'page_on_front', 0 );

		if ( 'page' === $front && $page_id > 0 ) {
			$entry['source']  = 'page';
			$entry['post_id'] = $page_id;
			$entry['label']   = get_the_title( $page_id ) ?: __( 'Home', 'wp-link-headers' );
			$entry['url']     = '';
		} else {
			$entry['source']  = 'custom';
			$entry['post_id'] = 0;
			$entry['label']   = __( 'Home', 'wp-link-headers' );
			$entry['url']     = home_url( '/' );
		}

		return $entry;
	}

	/**
	 * RSS feed entry (rel="alternate", type="application/rss+xml").
	 *
	 * @return array<string, mixed>
	 */
	private static function feed_entry(): array {
		$entry = self::base_entry( 'alternate' );

		$entry['source']     = 'custom';
		$entry['post_id']    = 0;
		$entry['label']      = __( 'RSS Feed', 'wp-link-headers' );
		$entry['url']        = get_feed_link();
		$entry['link_type']  = 'application/rss+xml';
		$entry['link_title'] = get_bloginfo( 'name' ) . ' ' . __( 'Feed', 'wp-link-headers' );

		return $entry;
	}

	/**
	 * XML sitemap entry (rel="alternate", type="application/xml").
	 *
	 * @return array<string, mixed>
	 */
	private static function sitemap_entry(): array {
		$entry = self::base_entry( 'alternate' );

		$entry['source']     = 'custom';
		$entry['post_id']    = 0;
		$entry['label']      = __( 'Sitemap', 'wp-link-headers' );
		$entry['url']        = self::sitemap_url();
		$entry['link_type']  = 'application/xml';
		$entry['link_title'] = __( 'XML Sitemap', 'wp-link-headers' );

		return $entry;
	}

	/**
	 * llms.txt entry (rel="describedby", type="text/plain").
	 *
	 * @return array<string, mixed>
	 */
	private static function llms_entry(): array {
		$entry = self::base_entry( 'describedby' );

		$entry['source']     = 'custom';
		$entry['post_id']    = 0;
		$entry['label']      = 'llms.txt';
		$entry['url']        = home_url( '/llms.txt' );
		$entry['link_type']  = 'text/plain';
		$entry['link_title'] = 'llms.txt';

		return $entry;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * A blank entry skeleton with the given rel value.
	 *
	 * @param string $rel
	 *
	 * @return array<string, mixed>
	 */
	private static function base_entry( string $rel ): array {
		return [
			'source'     => 'custom',
			'post_id'    => 0,
			'label'      => '',
			'url'        => '',
			'rel'        => $rel,
			'rel_custom' => '',
			'link_type'  => '',
			'link_title' => '',
			'enabled'    => true,
		];
	}

	/**
	 * Resolve the XML sitemap URL, preferring WordPress core sitemaps.
	 *
	 * @return string
	 */
	private static function sitemap_url(): string {
		if ( function_exists( 'wp_sitemaps_get_server' ) ) {
			$server = wp_sitemaps_get_server();
			if ( $server && method_exists( $server, 'index_url' ) ) {
				$url = $server->index_url();
				if ( $url ) {
					return $url;
				}
			}
		}

		return home_url( '/wp-sitemap.xml' );
	}

	/**
	 * All site IDs in the network (no paging limit).
	 *
	 * @return array<int, int>
	 */
	private static function get_site_ids(): array {
		return array_map( 'intval', get_sites( [
			'fields' => 'ids',
			'number' => 0,
		] ) );
	}
}
