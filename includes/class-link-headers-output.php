<?php
/**
 * Outputs HTTP Link headers on every WordPress response.
 *
 * @package WP_Link_Headers
 */

defined( 'ABSPATH' ) || exit;

class WP_Link_Headers_Output {

	/**
	 * Wire up WordPress hooks.
	 */
	public function register(): void {
		// send_headers fires before output, on every front-end and REST request.
		add_action( 'send_headers', [ $this, 'send_link_headers' ] );

		// Also cover REST API responses.
		add_filter( 'rest_post_dispatch', [ $this, 'inject_rest_link_headers' ], 10, 3 );
	}

	/**
	 * Emit one Link header per enabled entry.
	 */
	public function send_link_headers(): void {
		$entries = $this->get_enabled_entries();

		foreach ( $entries as $entry ) {
			$header = $this->build_header( $entry );
			if ( $header ) {
				// false = do not replace — allows multiple Link headers.
				header( $header, false );
			}
		}
	}

	/**
	 * Inject Link headers into REST API responses (WP_REST_Response).
	 *
	 * @param WP_REST_Response $result  The REST response.
	 * @param WP_REST_Server   $server  Server instance.
	 * @param WP_REST_Request  $request The request.
	 *
	 * @return WP_REST_Response
	 */
	public function inject_rest_link_headers( $result, $server, $request ) {
		$entries = $this->get_enabled_entries();

		foreach ( $entries as $entry ) {
			$value = $this->build_header_value( $entry );
			if ( $value ) {
				$result->header( 'Link', $value, false );
			}
		}

		return $result;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Return only enabled entries with a resolved URL.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_enabled_entries(): array {
		$raw = get_option( WP_LINK_HEADERS_OPTION, [] );

		if ( ! is_array( $raw ) ) {
			return [];
		}

		$enabled = [];

		foreach ( $raw as $entry ) {
			if ( empty( $entry['enabled'] ) ) {
				continue;
			}

			// Resolve URL for page/post entries.
			if ( in_array( $entry['source'] ?? '', [ 'page', 'post' ], true ) ) {
				$url = get_permalink( (int) ( $entry['post_id'] ?? 0 ) );
				if ( ! $url ) {
					continue;
				}
				$entry['url'] = $url;
			}

			if ( empty( $entry['url'] ) ) {
				continue;
			}

			$enabled[] = $entry;
		}

		return $enabled;
	}

	/**
	 * Build a full "Link: ..." header string.
	 *
	 * @param array<string, mixed> $entry
	 *
	 * @return string Empty string if entry is invalid.
	 */
	private function build_header( array $entry ): string {
		$value = $this->build_header_value( $entry );

		return $value ? 'Link: ' . $value : '';
	}

	/**
	 * Build just the header value (without the "Link: " prefix).
	 *
	 * Format: <URL>; rel="REL"[; type="TYPE"][; title="TITLE"]
	 *
	 * @param array<string, mixed> $entry
	 *
	 * @return string
	 */
	public function build_header_value( array $entry ): string {
		$url = esc_url_raw( $entry['url'] ?? '' );
		$rel = $this->sanitize_param( $entry['rel'] ?? 'canonical' );

		if ( ! $url || ! $rel ) {
			return '';
		}

		$parts = [ '<' . $url . '>', 'rel="' . $rel . '"' ];

		$type = $this->sanitize_param( $entry['link_type'] ?? '' );
		if ( '' !== $type ) {
			$parts[] = 'type="' . $type . '"';
		}

		$title = $this->sanitize_param( $entry['link_title'] ?? '' );
		if ( '' !== $title ) {
			$parts[] = 'title="' . $title . '"';
		}

		return implode( '; ', $parts );
	}

	/**
	 * Sanitize a value for inclusion in a quoted Link-header parameter.
	 *
	 * Strips CR/LF and other control characters (which could split the response
	 * into multiple headers) and removes the double-quote and backslash
	 * characters that would otherwise break out of an RFC 8288 quoted-string.
	 *
	 * @param mixed $value
	 *
	 * @return string
	 */
	private function sanitize_param( $value ): string {
		$value = (string) $value;

		// Collapse any control characters (CR, LF, tab, NUL, vertical tab) to a space.
		$value = preg_replace( '/[\x00-\x1F\x7F]+/', ' ', $value );

		// Remove characters that could escape the quoted-string.
		$value = str_replace( [ '"', '\\' ], '', (string) $value );

		return trim( $value );
	}
}
