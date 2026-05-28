<?php
/**
 * Admin UI: settings page for managing Link header entries.
 *
 * @package WP_Link_Headers
 */

defined( 'ABSPATH' ) || exit;

class WP_Link_Headers_Admin {

	private const NONCE_ACTION = 'wp_link_headers_save';
	private const NONCE_FIELD  = 'wp_link_headers_nonce';
	private const CAP           = 'manage_options';

	/** Preset rel values shown in the dropdown. */
	private const REL_PRESETS = [
		'canonical'   => 'canonical',
		'describedby' => 'describedby',
		'alternate'   => 'alternate',
		'me'          => 'me',
		'author'      => 'author',
		'license'     => 'license',
		'help'        => 'help',
		'custom'      => '— custom —',
	];

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'add_menu_page' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_wplh_search_posts', [ $this, 'ajax_search_posts' ] );
		add_action( 'wp_ajax_wplh_get_entry_row', [ $this, 'ajax_get_entry_row' ] );
	}

	// -------------------------------------------------------------------------
	// Menu
	// -------------------------------------------------------------------------

	public function add_menu_page(): void {
		add_options_page(
			__( 'Link Headers', 'wp-link-headers' ),
			__( 'Link Headers', 'wp-link-headers' ),
			self::CAP,
			'wp-link-headers',
			[ $this, 'render_settings_page' ]
		);
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_wp-link-headers' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wp-link-headers-admin',
			WP_LINK_HEADERS_URL . 'assets/css/admin.css',
			[],
			WP_LINK_HEADERS_VERSION
		);

		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_script(
			'wp-link-headers-admin',
			WP_LINK_HEADERS_URL . 'assets/js/admin.js',
			[ 'jquery', 'jquery-ui-sortable' ],
			WP_LINK_HEADERS_VERSION,
			true
		);

		wp_localize_script(
			'wp-link-headers-admin',
			'wplhData',
			[
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'wplh_ajax' ),
				'relPresets' => array_keys( self::REL_PRESETS ),
				'i18n'     => [
					'confirmRemove' => __( 'Remove this entry?', 'wp-link-headers' ),
					'searching'     => __( 'Searching…', 'wp-link-headers' ),
					'noResults'     => __( 'No results found.', 'wp-link-headers' ),
					'addCustomUrl'  => __( 'Add custom URL', 'wp-link-headers' ),
				],
			]
		);
	}

	// -------------------------------------------------------------------------
	// Settings page render + save
	// -------------------------------------------------------------------------

	public function render_settings_page(): void {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'wp-link-headers' ) );
		}

		// Handle form submission.
		if ( isset( $_POST[ self::NONCE_FIELD ] ) ) {
			$this->save_entries();
		}

		$entries = get_option( WP_LINK_HEADERS_OPTION, [] );
		if ( ! is_array( $entries ) ) {
			$entries = [];
		}

		// Build a header preview.
		$output  = new WP_Link_Headers_Output();
		$previews = [];
		foreach ( $entries as $entry ) {
			if ( empty( $entry['enabled'] ) ) {
				continue;
			}
			if ( in_array( $entry['source'] ?? '', [ 'page', 'post' ], true ) ) {
				$url = get_permalink( (int) ( $entry['post_id'] ?? 0 ) );
				if ( $url ) {
					$entry['url'] = $url;
				}
			}
			$v = $output->build_header_value( $entry );
			if ( $v ) {
				$previews[] = 'Link: ' . $v;
			}
		}

		?>
		<div class="wrap wplh-wrap">
			<h1 class="wp-heading-inline">
				<?php esc_html_e( 'WP Link Headers', 'wp-link-headers' ); ?>
			</h1>
			<p class="description">
				<?php esc_html_e( 'Select pages or posts to broadcast as HTTP Link headers on every response. AI agents and discovery crawlers use these to find your content.', 'wp-link-headers' ); ?>
			</p>

			<?php settings_errors( 'wp_link_headers' ); ?>

			<!-- Live header preview -->
			<?php if ( $previews ) : ?>
			<div class="wplh-preview-box">
				<strong><?php esc_html_e( 'Live header preview', 'wp-link-headers' ); ?></strong>
				<pre><?php echo esc_html( implode( "\n", $previews ) ); ?></pre>
			</div>
			<?php endif; ?>

			<form method="post" id="wplh-form" action="">
				<?php wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD ); ?>

				<div class="wplh-entries-container">
					<table class="wplh-table widefat" id="wplh-entries-table">
						<thead>
							<tr>
								<th class="wplh-col-handle" aria-label="<?php esc_attr_e( 'Drag to reorder', 'wp-link-headers' ); ?>">☰</th>
								<th><?php esc_html_e( 'Resource', 'wp-link-headers' ); ?></th>
								<th><?php esc_html_e( 'URL', 'wp-link-headers' ); ?></th>
								<th><?php esc_html_e( 'rel=', 'wp-link-headers' ); ?></th>
								<th><?php esc_html_e( 'type=', 'wp-link-headers' ); ?></th>
								<th><?php esc_html_e( 'title=', 'wp-link-headers' ); ?></th>
								<th><?php esc_html_e( 'Enabled', 'wp-link-headers' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'wp-link-headers' ); ?></th>
							</tr>
						</thead>
						<tbody id="wplh-entries-body">
							<?php
							foreach ( $entries as $i => $entry ) {
								$this->render_entry_row( $i, $entry );
							}
							?>
						</tbody>
					</table>

					<?php if ( empty( $entries ) ) : ?>
					<p class="wplh-empty-notice" id="wplh-empty-notice">
						<?php esc_html_e( 'No entries yet. Use the form below to add pages, posts, or custom URLs.', 'wp-link-headers' ); ?>
					</p>
					<?php endif; ?>
				</div>

				<!-- Add new entry panel -->
				<div class="wplh-add-panel postbox">
					<div class="postbox-header">
						<h2 class="hndle"><?php esc_html_e( 'Add a Link header entry', 'wp-link-headers' ); ?></h2>
					</div>
					<div class="inside">
						<div class="wplh-add-tabs">
							<button type="button" class="wplh-tab-btn active" data-tab="search">
								<?php esc_html_e( 'Page / Post', 'wp-link-headers' ); ?>
							</button>
							<button type="button" class="wplh-tab-btn" data-tab="custom">
								<?php esc_html_e( 'Custom URL', 'wp-link-headers' ); ?>
							</button>
						</div>

						<!-- Tab: search pages/posts -->
						<div class="wplh-tab-content active" id="wplh-tab-search">
							<div class="wplh-search-row">
								<label for="wplh-search-input"><?php esc_html_e( 'Search pages &amp; posts:', 'wp-link-headers' ); ?></label>
								<input type="text" id="wplh-search-input" class="regular-text"
									placeholder="<?php esc_attr_e( 'Type a title…', 'wp-link-headers' ); ?>" autocomplete="off" />
								<span class="wplh-spinner spinner"></span>
							</div>
							<ul id="wplh-search-results" class="wplh-results-list" aria-live="polite"></ul>
						</div>

						<!-- Tab: custom URL -->
						<div class="wplh-tab-content" id="wplh-tab-custom">
							<table class="form-table wplh-custom-form">
								<tr>
									<th><label for="wplh-custom-url"><?php esc_html_e( 'URL', 'wp-link-headers' ); ?></label></th>
									<td>
										<input type="url" id="wplh-custom-url" class="regular-text"
											placeholder="https://example.com/page" />
									</td>
								</tr>
								<tr>
									<th><label for="wplh-custom-rel"><?php esc_html_e( 'rel=', 'wp-link-headers' ); ?></label></th>
									<td><?php $this->render_rel_select( 'wplh-custom-rel', '' ); ?></td>
								</tr>
								<tr class="wplh-custom-rel-row" style="display:none">
									<th><label for="wplh-custom-rel-value"><?php esc_html_e( 'Custom rel value', 'wp-link-headers' ); ?></label></th>
									<td>
										<input type="text" id="wplh-custom-rel-value" class="regular-text"
											placeholder="<?php esc_attr_e( 'e.g. webmention', 'wp-link-headers' ); ?>" />
									</td>
								</tr>
								<tr>
									<th><label for="wplh-custom-type"><?php esc_html_e( 'type= (optional)', 'wp-link-headers' ); ?></label></th>
									<td>
										<input type="text" id="wplh-custom-type" class="regular-text"
											placeholder="<?php esc_attr_e( 'e.g. text/html', 'wp-link-headers' ); ?>" />
									</td>
								</tr>
								<tr>
									<th><label for="wplh-custom-title"><?php esc_html_e( 'title= (optional)', 'wp-link-headers' ); ?></label></th>
									<td>
										<input type="text" id="wplh-custom-title" class="regular-text"
											placeholder="<?php esc_attr_e( 'Human-readable label', 'wp-link-headers' ); ?>" />
									</td>
								</tr>
							</table>
							<p>
								<button type="button" id="wplh-add-custom-btn" class="button button-secondary">
									<?php esc_html_e( 'Add Custom URL', 'wp-link-headers' ); ?>
								</button>
							</p>
						</div>
					</div>
				</div>

				<p class="submit">
					<?php submit_button( __( 'Save Link Headers', 'wp-link-headers' ), 'primary', 'submit', false ); ?>
				</p>
			</form>
		</div>

		<!-- Hidden template row (cloned by JS) -->
		<template id="wplh-row-template">
			<?php $this->render_entry_row( '__IDX__', $this->empty_entry() ); ?>
		</template>
		<?php
	}

	// -------------------------------------------------------------------------
	// Row rendering
	// -------------------------------------------------------------------------

	/**
	 * Render a single table row for an entry.
	 *
	 * @param int|string           $index  Row index (or __IDX__ for the template).
	 * @param array<string, mixed> $entry  Entry data.
	 */
	public function render_entry_row( $index, array $entry ): void {
		$source    = $entry['source'] ?? 'custom';
		$post_id   = (int) ( $entry['post_id'] ?? 0 );
		$url       = $entry['url'] ?? '';
		$rel       = $entry['rel'] ?? 'canonical';
		$link_type = $entry['link_type'] ?? '';
		$link_title = $entry['link_title'] ?? '';
		$enabled   = ! empty( $entry['enabled'] );
		$label     = $entry['label'] ?? '';

		// Resolve label & URL for page/post sources.
		if ( in_array( $source, [ 'page', 'post' ], true ) && $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$label = $label ?: get_the_title( $post );
				$url   = get_permalink( $post ) ?: $url;
			}
		}

		$name = "entries[{$index}]";
		$row_class = $enabled ? 'wplh-row-enabled' : 'wplh-row-disabled';
		?>
		<tr class="wplh-entry-row <?php echo esc_attr( $row_class ); ?>" data-index="<?php echo esc_attr( (string) $index ); ?>">
			<td class="wplh-col-handle">
				<span class="wplh-drag-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'wp-link-headers' ); ?>"></span>
			</td>

			<td class="wplh-col-resource">
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[source]"   value="<?php echo esc_attr( $source ); ?>" />
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[post_id]"  value="<?php echo esc_attr( (string) $post_id ); ?>" />
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[label]"    value="<?php echo esc_attr( $label ); ?>" />

				<?php if ( in_array( $source, [ 'page', 'post' ], true ) ) : ?>
					<span class="wplh-badge wplh-badge-<?php echo esc_attr( $source ); ?>">
						<?php echo esc_html( ucfirst( $source ) ); ?>
					</span>
					<?php echo esc_html( $label ?: __( '(untitled)', 'wp-link-headers' ) ); ?>
				<?php else : ?>
					<span class="wplh-badge wplh-badge-custom">
						<?php esc_html_e( 'Custom', 'wp-link-headers' ); ?>
					</span>
					<?php echo esc_html( $label ?: $url ); ?>
				<?php endif; ?>
			</td>

			<td class="wplh-col-url">
				<?php if ( in_array( $source, [ 'page', 'post' ], true ) ) : ?>
					<span class="wplh-url-resolved description">
						<?php echo esc_html( $url ?: __( '(permalink)', 'wp-link-headers' ) ); ?>
					</span>
				<?php else : ?>
					<input type="url" name="<?php echo esc_attr( $name ); ?>[url]"
						value="<?php echo esc_attr( $url ); ?>"
						class="regular-text"
						placeholder="https://" />
				<?php endif; ?>
			</td>

			<td class="wplh-col-rel">
				<?php $this->render_rel_select( $name . '[rel]', $rel, $name . '[rel_custom]', $entry['rel_custom'] ?? '' ); ?>
			</td>

			<td class="wplh-col-type">
				<input type="text" name="<?php echo esc_attr( $name ); ?>[link_type]"
					value="<?php echo esc_attr( $link_type ); ?>"
					class="small-text"
					placeholder="<?php esc_attr_e( 'text/html', 'wp-link-headers' ); ?>" />
			</td>

			<td class="wplh-col-title">
				<input type="text" name="<?php echo esc_attr( $name ); ?>[link_title]"
					value="<?php echo esc_attr( $link_title ); ?>"
					class="regular-text"
					placeholder="<?php esc_attr_e( 'optional', 'wp-link-headers' ); ?>" />
			</td>

			<td class="wplh-col-enabled">
				<label class="wplh-toggle">
					<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[enabled]"
						value="1" <?php checked( $enabled ); ?> />
					<span class="wplh-toggle-slider"></span>
				</label>
			</td>

			<td class="wplh-col-actions">
				<button type="button" class="button button-link-delete wplh-remove-btn"
					title="<?php esc_attr_e( 'Remove entry', 'wp-link-headers' ); ?>">
					<?php esc_html_e( 'Remove', 'wp-link-headers' ); ?>
				</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Render the rel= <select> element.
	 *
	 * @param string $select_name    Name for the <select>.
	 * @param string $current_rel    Currently selected value.
	 * @param string $custom_name    Name for the hidden/custom text input.
	 * @param string $custom_value   Current custom value.
	 */
	private function render_rel_select(
		string $select_name,
		string $current_rel,
		string $custom_name = '',
		string $custom_value = ''
	): void {
		// Determine if the current rel is a preset.
		$is_preset = array_key_exists( $current_rel, self::REL_PRESETS );
		$select_val = ( $is_preset || '' === $current_rel ) ? $current_rel : 'custom';
		if ( ! $is_preset && '' !== $current_rel ) {
			$custom_value = $custom_value ?: $current_rel;
		}
		?>
		<select name="<?php echo esc_attr( $select_name ); ?>" class="wplh-rel-select">
			<?php foreach ( self::REL_PRESETS as $value => $label ) : ?>
			<option value="<?php echo esc_attr( $value ); ?>"
				<?php selected( $select_val, $value ); ?>>
				<?php echo esc_html( $label ); ?>
			</option>
			<?php endforeach; ?>
		</select>
		<?php if ( $custom_name ) : ?>
		<input type="text"
			name="<?php echo esc_attr( $custom_name ); ?>"
			class="wplh-rel-custom-input"
			value="<?php echo esc_attr( $custom_value ); ?>"
			placeholder="<?php esc_attr_e( 'e.g. webmention', 'wp-link-headers' ); ?>"
			<?php echo 'custom' !== $select_val ? 'style="display:none"' : ''; ?> />
		<?php endif; ?>
		<?php
	}

	// -------------------------------------------------------------------------
	// Save
	// -------------------------------------------------------------------------

	private function save_entries(): void {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ?? '' ) ), self::NONCE_ACTION ) ) {
			add_settings_error( 'wp_link_headers', 'bad_nonce', __( 'Security check failed.', 'wp-link-headers' ), 'error' );
			return;
		}

		if ( ! current_user_can( self::CAP ) ) {
			add_settings_error( 'wp_link_headers', 'no_cap', __( 'Insufficient permissions.', 'wp-link-headers' ), 'error' );
			return;
		}

		$raw     = isset( $_POST['entries'] ) && is_array( $_POST['entries'] ) ? wp_unslash( $_POST['entries'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$entries = [];

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$source = sanitize_text_field( $row['source'] ?? 'custom' );

			// Resolve effective rel.
			$rel_select = sanitize_text_field( $row['rel'] ?? 'canonical' );
			if ( 'custom' === $rel_select ) {
				$rel = sanitize_text_field( $row['rel_custom'] ?? '' );
			} else {
				$rel = $rel_select;
			}

			if ( ! $rel ) {
				$rel = 'canonical';
			}

			$entry = [
				'source'     => $source,
				'post_id'    => (int) ( $row['post_id'] ?? 0 ),
				'label'      => sanitize_text_field( $row['label'] ?? '' ),
				'url'        => esc_url_raw( $row['url'] ?? '' ),
				'rel'        => $rel,
				'rel_custom' => sanitize_text_field( $row['rel_custom'] ?? '' ),
				'link_type'  => sanitize_mime_type( $row['link_type'] ?? '' ),
				'link_title' => sanitize_text_field( $row['link_title'] ?? '' ),
				'enabled'    => ! empty( $row['enabled'] ),
			];

			// For page/post entries the URL is resolved at output time; clear it.
			if ( in_array( $source, [ 'page', 'post' ], true ) ) {
				$entry['url'] = '';
			}

			$entries[] = $entry;
		}

		update_option( WP_LINK_HEADERS_OPTION, $entries );

		add_settings_error(
			'wp_link_headers',
			'saved',
			__( 'Link headers saved.', 'wp-link-headers' ),
			'success'
		);
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * Search pages and posts by title.
	 * Returns JSON: [ { id, title, type, permalink }, … ]
	 */
	public function ajax_search_posts(): void {
		check_ajax_referer( 'wplh_ajax', 'nonce' );

		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$search   = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
		$per_page = 20;

		if ( strlen( $search ) < 1 ) {
			wp_send_json_success( [] );
		}

		$results = [];

		foreach ( [ 'page', 'post' ] as $post_type ) {
			$query = new WP_Query( [
				'post_type'      => $post_type,
				'post_status'    => 'publish',
				's'              => $search,
				'posts_per_page' => $per_page,
				'no_found_rows'  => true,
			] );

			foreach ( $query->posts as $p ) {
				$results[] = [
					'id'        => $p->ID,
					'title'     => get_the_title( $p ) ?: '(' . $p->post_name . ')',
					'type'      => $post_type,
					'permalink' => get_permalink( $p ),
				];
			}
		}

		wp_send_json_success( $results );
	}

	/**
	 * Return HTML for a new entry row (used when user picks from search results).
	 * Expects POST: post_id, source (page|post).
	 */
	public function ajax_get_entry_row(): void {
		check_ajax_referer( 'wplh_ajax', 'nonce' );

		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$post_id = (int) ( $_POST['post_id'] ?? 0 );
		$source  = sanitize_text_field( $_POST['source'] ?? 'post' );
		$index   = sanitize_text_field( $_POST['index'] ?? '0' );

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( 'Post not found', 404 );
		}

		$entry = [
			'source'  => $source,
			'post_id' => $post_id,
			'label'   => get_the_title( $post ),
			'rel'     => 'canonical',
			'enabled' => true,
		];

		ob_start();
		$this->render_entry_row( $index, $entry );
		$html = ob_get_clean();

		wp_send_json_success( [ 'html' => $html ] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Return an empty entry structure (used for the template row).
	 *
	 * @return array<string, mixed>
	 */
	private function empty_entry(): array {
		return [
			'source'     => 'custom',
			'post_id'    => 0,
			'label'      => '',
			'url'        => '',
			'rel'        => 'canonical',
			'rel_custom' => '',
			'link_type'  => '',
			'link_title' => '',
			'enabled'    => true,
		];
	}
}
