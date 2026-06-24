<?php
/**
 * WP-CLI commands for managing Link header entries.
 *
 * Registered as `wp link-headers <subcommand>`. In a multisite network, target
 * a specific site with the global `--url=<site-url>` flag.
 *
 * @package WP_Link_Headers
 */

defined( 'ABSPATH' ) || exit;

/**
 * Manage the HTTP Link headers broadcast for agent discovery.
 */
class WP_Link_Headers_CLI {

	/** Valid sources for an entry. */
	private const SOURCES = [ 'page', 'post', 'custom' ];

	/**
	 * Lists configured Link header entries.
	 *
	 * Each entry shows its index — use that index with `enable`, `disable`,
	 * and `remove`.
	 *
	 * ## OPTIONS
	 *
	 * [--enabled-only]
	 * : Only show entries that are currently enabled.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp link-headers list
	 *     wp link-headers list --enabled-only --format=json
	 *     wp link-headers list --url=https://shop.example.com
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ): void {
		$entries = $this->get_entries();
		$rows    = [];

		foreach ( $entries as $i => $entry ) {
			if ( ! empty( $assoc_args['enabled-only'] ) && empty( $entry['enabled'] ) ) {
				continue;
			}

			$rows[] = [
				'index'   => $i,
				'enabled' => ! empty( $entry['enabled'] ) ? 'yes' : 'no',
				'source'  => $entry['source'] ?? 'custom',
				'rel'     => $entry['rel'] ?? '',
				'type'    => $entry['link_type'] ?? '',
				'url'     => $this->resolve_url( $entry ),
				'header'  => $this->header_value( $entry ),
			];
		}

		$format = $assoc_args['format'] ?? 'table';
		WP_CLI\Utils\format_items( $format, $rows, [ 'index', 'enabled', 'source', 'rel', 'type', 'url', 'header' ] );
	}

	/**
	 * Adds a custom URL as a Link header entry.
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : The absolute URL to advertise.
	 *
	 * [--rel=<rel>]
	 * : The link relation type.
	 * ---
	 * default: describedby
	 * ---
	 *
	 * [--type=<type>]
	 * : Optional MIME type (the `type=` attribute), e.g. application/rss+xml.
	 *
	 * [--title=<title>]
	 * : Optional human-readable title (the `title=` attribute).
	 *
	 * [--disabled]
	 * : Add the entry in a disabled state.
	 *
	 * ## EXAMPLES
	 *
	 *     wp link-headers add https://example.com/llms.txt --rel=describedby --type=text/plain
	 *     wp link-headers add https://example.com/feed/ --rel=alternate --type=application/rss+xml --title="My Feed"
	 */
	public function add( $args, $assoc_args ): void {
		$url = esc_url_raw( $args[0] );
		if ( ! $url ) {
			WP_CLI::error( 'A valid URL is required.' );
		}

		$entry = [
			'source'     => 'custom',
			'post_id'    => 0,
			'label'      => $url,
			'url'        => $url,
			'rel'        => sanitize_text_field( $assoc_args['rel'] ?? 'describedby' ),
			'rel_custom' => '',
			'link_type'  => sanitize_text_field( $assoc_args['type'] ?? '' ),
			'link_title' => sanitize_text_field( $assoc_args['title'] ?? '' ),
			'enabled'    => empty( $assoc_args['disabled'] ),
		];

		$entries   = $this->get_entries();
		$entries[] = $entry;
		$this->save_entries( $entries );

		WP_CLI::success( sprintf( 'Added entry #%d: %s', count( $entries ) - 1, $this->header_value( $entry ) ) );
	}

	/**
	 * Adds a page or post (by ID) as a Link header entry.
	 *
	 * The URL is resolved from the post's permalink at output time, so it stays
	 * correct even if the permalink structure changes.
	 *
	 * ## OPTIONS
	 *
	 * <post-id>
	 * : The ID of the page or post.
	 *
	 * [--rel=<rel>]
	 * : The link relation type.
	 * ---
	 * default: describedby
	 * ---
	 *
	 * [--title=<title>]
	 * : Optional human-readable title (the `title=` attribute).
	 *
	 * [--disabled]
	 * : Add the entry in a disabled state.
	 *
	 * ## EXAMPLES
	 *
	 *     wp link-headers add-post 42 --rel=describedby
	 *
	 * @subcommand add-post
	 */
	public function add_post( $args, $assoc_args ): void {
		$post_id = (int) $args[0];
		$post    = get_post( $post_id );

		if ( ! $post ) {
			WP_CLI::error( sprintf( 'No post found with ID %d.', $post_id ) );
		}

		$source = ( 'page' === $post->post_type ) ? 'page' : 'post';

		$entry = [
			'source'     => $source,
			'post_id'    => $post_id,
			'label'      => get_the_title( $post ),
			'url'        => '',
			'rel'        => sanitize_text_field( $assoc_args['rel'] ?? 'describedby' ),
			'rel_custom' => '',
			'link_type'  => '',
			'link_title' => sanitize_text_field( $assoc_args['title'] ?? '' ),
			'enabled'    => empty( $assoc_args['disabled'] ),
		];

		$entries   = $this->get_entries();
		$entries[] = $entry;
		$this->save_entries( $entries );

		WP_CLI::success( sprintf( 'Added %s #%d (%s) as entry #%d.', $source, $post_id, get_the_title( $post ), count( $entries ) - 1 ) );
	}

	/**
	 * Removes an entry by its index.
	 *
	 * ## OPTIONS
	 *
	 * <index>
	 * : The index of the entry to remove (see `wp link-headers list`).
	 *
	 * ## EXAMPLES
	 *
	 *     wp link-headers remove 2
	 */
	public function remove( $args, $assoc_args ): void {
		$index   = (int) $args[0];
		$entries = $this->get_entries();

		if ( ! array_key_exists( $index, $entries ) ) {
			WP_CLI::error( sprintf( 'No entry at index %d.', $index ) );
		}

		$removed = $entries[ $index ];
		unset( $entries[ $index ] );
		$this->save_entries( $entries );

		WP_CLI::success( sprintf( 'Removed entry #%d: %s', $index, $this->header_value( $removed ) ) );
	}

	/**
	 * Enables an entry by its index.
	 *
	 * ## OPTIONS
	 *
	 * <index>
	 * : The index of the entry to enable.
	 *
	 * ## EXAMPLES
	 *
	 *     wp link-headers enable 1
	 */
	public function enable( $args, $assoc_args ): void {
		$this->set_enabled( (int) $args[0], true );
	}

	/**
	 * Disables an entry by its index (without removing it).
	 *
	 * ## OPTIONS
	 *
	 * <index>
	 * : The index of the entry to disable.
	 *
	 * ## EXAMPLES
	 *
	 *     wp link-headers disable 1
	 */
	public function disable( $args, $assoc_args ): void {
		$this->set_enabled( (int) $args[0], false );
	}

	/**
	 * Seeds the default entries (home page, RSS feed, sitemap, llms.txt).
	 *
	 * By default this is a no-op if any entries already exist. Use --force to
	 * overwrite, or --network to seed every site in a multisite network.
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Overwrite existing entries with the defaults.
	 *
	 * [--network]
	 * : Apply to every site in the network (multisite only).
	 *
	 * ## EXAMPLES
	 *
	 *     wp link-headers seed
	 *     wp link-headers seed --force
	 *     wp link-headers seed --network --force
	 */
	public function seed( $args, $assoc_args ): void {
		$force   = ! empty( $assoc_args['force'] );
		$network = ! empty( $assoc_args['network'] );

		if ( $network ) {
			if ( ! is_multisite() ) {
				WP_CLI::error( '--network requires a multisite installation.' );
			}

			$site_ids = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
			$count    = 0;

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				if ( WP_Link_Headers_Installer::seed( $force ) ) {
					$count++;
					WP_CLI::log( sprintf( 'Seeded site %d (%s).', $site_id, home_url( '/' ) ) );
				} else {
					WP_CLI::log( sprintf( 'Skipped site %d (already configured).', $site_id ) );
				}
				restore_current_blog();
			}

			WP_CLI::success( sprintf( 'Seeded %d of %d site(s).', $count, count( $site_ids ) ) );
			return;
		}

		if ( WP_Link_Headers_Installer::seed( $force ) ) {
			WP_CLI::success( 'Seeded default Link header entries.' );
		} else {
			WP_CLI::warning( 'Entries already exist. Use --force to overwrite.' );
		}
	}

	/**
	 * Removes all Link header entries.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp link-headers clear --yes
	 */
	public function clear( $args, $assoc_args ): void {
		WP_CLI::confirm( 'Remove ALL Link header entries for this site?', $assoc_args );
		$this->save_entries( [] );
		WP_CLI::success( 'Cleared all Link header entries.' );
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function get_entries(): array {
		$entries = get_option( WP_LINK_HEADERS_OPTION, [] );
		return is_array( $entries ) ? array_values( $entries ) : [];
	}

	/**
	 * @param array<int, array<string, mixed>> $entries
	 */
	private function save_entries( array $entries ): void {
		update_option( WP_LINK_HEADERS_OPTION, array_values( $entries ) );
	}

	/**
	 * Flip the enabled flag on an entry and report.
	 */
	private function set_enabled( int $index, bool $enabled ): void {
		$entries = $this->get_entries();

		if ( ! array_key_exists( $index, $entries ) ) {
			WP_CLI::error( sprintf( 'No entry at index %d.', $index ) );
		}

		$entries[ $index ]['enabled'] = $enabled;
		$this->save_entries( $entries );

		WP_CLI::success( sprintf( '%s entry #%d.', $enabled ? 'Enabled' : 'Disabled', $index ) );
	}

	/**
	 * Resolve the effective URL of an entry for display.
	 *
	 * @param array<string, mixed> $entry
	 */
	private function resolve_url( array $entry ): string {
		if ( in_array( $entry['source'] ?? '', [ 'page', 'post' ], true ) ) {
			return (string) get_permalink( (int) ( $entry['post_id'] ?? 0 ) );
		}
		return (string) ( $entry['url'] ?? '' );
	}

	/**
	 * Build the full Link header value for display, reusing the output class.
	 *
	 * @param array<string, mixed> $entry
	 */
	private function header_value( array $entry ): string {
		$entry['url'] = $this->resolve_url( $entry );
		$output       = new WP_Link_Headers_Output();
		return $output->build_header_value( $entry );
	}
}
