=== WP Link Headers ===
Contributors:      kevinbowers
Tags:              link headers, agent discovery, AI, HTTP headers, SEO
Requires at least: 6.0
Tested up to:      6.7
Requires PHP:      8.0
Stable tag:        1.0.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Add HTTP Link headers to selected pages and posts so AI agents and crawlers can discover your WordPress content.

== Description ==

**WP Link Headers** lets you choose exactly which pages and posts should be advertised via HTTP `Link` headers — the standard mechanism that AI agents, feed readers, and discovery crawlers use to find related resources without having to parse HTML.

**Features**

* Select any combination of **pages** and **posts** from a live search UI.
* Add **custom URLs** (for external resources, APIs, sitemaps, etc.).
* Configure the **`rel=` value** per entry — choose from presets (`canonical`, `describedby`, `alternate`, `me`, `author`, `license`, `help`) or enter a **custom** rel token.
* Optionally set a **`type=`** MIME type and a **`title=`** attribute per entry.
* **Enable / disable** individual entries without deleting them.
* **Drag-to-reorder** entries to control header order.
* Headers are injected on **every response** (front-end pages and the REST API).
* **Live header preview** on the settings page shows exactly what will be sent.
* **Sensible defaults on activation** — automatically seeds the home page, RSS feed, XML sitemap, and `llms.txt`.
* **Multisite / network compatible** — network activation seeds every site, and new sites are seeded automatically.
* **WP-CLI integration** — manage entries from the command line.

**Defaults added on activation**

```
Link: <https://example.com/>; rel="describedby"
Link: <https://example.com/feed/>; rel="alternate"; type="application/rss+xml"; title="Site Name Feed"
Link: <https://example.com/wp-sitemap.xml>; rel="alternate"; type="application/xml"; title="XML Sitemap"
Link: <https://example.com/llms.txt>; rel="describedby"; type="text/plain"; title="llms.txt"
```

== Installation ==

1. Upload the `wp-link-headers` folder to `/wp-content/plugins/`.
2. Activate the plugin from **Plugins → Installed Plugins** (or **Network Activate** in a multisite network).
3. Go to **Settings → Link Headers** to manage your entries.

== WP-CLI ==

Manage entries with `wp link-headers <subcommand>`. In a multisite network, target a site with the global `--url=<site-url>` flag.

```
wp link-headers list                  # list entries (with their indexes)
wp link-headers add https://example.com/about --rel=describedby
wp link-headers add-post 42 --rel=describedby
wp link-headers enable 1
wp link-headers disable 2
wp link-headers remove 3
wp link-headers seed                  # add the 4 defaults (no-op if any exist)
wp link-headers seed --force          # overwrite with defaults
wp link-headers seed --network --force  # seed every site in the network
wp link-headers clear --yes           # remove all entries
```

== Multisite ==

* **Network Activate** seeds the four default entries on every existing site.
* Any site created afterwards is seeded automatically.
* Each site keeps its own independent set of entries (stored per-site, not network-wide), so admins can customize freely.
* Use `wp link-headers seed --network` to (re)seed all sites at once.

== Frequently Asked Questions ==

= Which HTTP header format does the plugin use? =

The standard `Link:` header as defined in [RFC 8288](https://www.rfc-editor.org/rfc/rfc8288).

= What is the `canonical` rel for? =

It signals to agents that a URL is the authoritative version of a resource. Most AI discovery tools and search engines recognise it.

= What is the `describedby` rel for? =

It indicates that the linked resource *describes* the current context — useful for pointing agents to documentation, API specs, or context pages.

= Does this affect page speed? =

No. Link headers are added at the HTTP layer before any HTML is rendered.

= Are the headers added to the REST API too? =

Yes. Every `WP_REST_Response` will carry the enabled Link headers.

= What happens to my settings when I deactivate or delete the plugin? =

Deactivating the plugin leaves your settings intact, so you can safely deactivate and reactivate (for example, while troubleshooting) without losing your configuration. **Deleting** the plugin removes all of its data (the `wp_link_headers_entries` option). On a multisite network, deletion cleans up every site.

== Changelog ==

= 1.0.0 =
* Initial release.
* Seeds the home page, RSS feed, XML sitemap, and llms.txt on activation.
* Multisite/network support, including auto-seeding of new sites.
* WP-CLI command: `wp link-headers`.
* Removes all stored data on uninstall (network-aware); deactivation preserves settings.
* Hardening: RFC 8288 header-value sanitization, source allowlisting, stricter AJAX input validation.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps needed.
