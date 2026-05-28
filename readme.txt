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

**Example output**

```
Link: <https://example.com/about>; rel="canonical"
Link: <https://example.com/blog/how-ai-works>; rel="describedby"; type="text/html"; title="How AI Works"
Link: <https://example.com/sitemap.xml>; rel="alternate"; type="application/xml"
```

== Installation ==

1. Upload the `wp-link-headers` folder to `/wp-content/plugins/`.
2. Activate the plugin from **Plugins → Installed Plugins**.
3. Go to **Settings → Link Headers** to manage your entries.

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

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release — no upgrade steps needed.
