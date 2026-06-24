# WP Link Headers

> Add HTTP `Link` headers to selected pages and posts so AI agents and crawlers can discover your WordPress content.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759b)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4)
![License](https://img.shields.io/badge/License-GPL--2.0--or--later-blue)
![Multisite](https://img.shields.io/badge/Multisite-compatible-success)

WP Link Headers lets you choose exactly which pages, posts, and URLs are advertised via HTTP `Link` headers — the standard mechanism ([RFC 8288](https://www.rfc-editor.org/rfc/rfc8288)) that AI agents, feed readers, and discovery crawlers use to find related resources without parsing HTML.

```http
GET / HTTP/1.1

Link: <https://example.com/>; rel="describedby"
Link: <https://example.com/feed/>; rel="alternate"; type="application/rss+xml"; title="Example Feed"
Link: <https://example.com/wp-sitemap.xml>; rel="alternate"; type="application/xml"; title="XML Sitemap"
Link: <https://example.com/llms.txt>; rel="describedby"; type="text/plain"; title="llms.txt"
```

---

## Table of contents

- [Features](#features)
- [Installation](#installation)
- [Defaults added on activation](#defaults-added-on-activation)
- [Usage](#usage)
- [WP-CLI](#wp-cli)
- [Multisite](#multisite)
- [How it works](#how-it-works)
- [Data &amp; privacy](#data--privacy)
- [Requirements](#requirements)
- [License](#license)

---

## Features

- 🔎 **Pick pages & posts** from a live search box — no IDs to copy.
- 🔗 **Add custom URLs** for external resources, feeds, sitemaps, APIs, etc.
- 🏷️ **Configurable `rel=` per entry** — presets (`canonical`, `describedby`, `alternate`, `me`, `author`, `license`, `help`) or a custom token.
- 🧩 **Optional `type=` and `title=`** attributes per entry.
- 🟢 **Enable / disable** individual entries without deleting them.
- ↕️ **Drag to reorder** to control header order.
- 👁️ **Live header preview** on the settings page shows exactly what gets sent.
- 🌐 **Injected on every response** — front-end pages **and** the REST API.
- ⚡ **Sensible defaults on activation** — home page, RSS feed, XML sitemap, and `llms.txt`.
- 🏢 **Multisite / network compatible**, with auto-seeding of new sites.
- 💻 **WP-CLI integration** for scripted management.

## Installation

1. Copy the `wp-link-headers` folder into `wp-content/plugins/`.
2. Activate **WP Link Headers** from **Plugins → Installed Plugins** (or **Network Activate** on multisite).
3. Manage entries under **Settings → Link Headers**.

> On activation the plugin seeds four sensible defaults (below) so you get value immediately — adjust or remove them anytime.

## Defaults added on activation

| Resource | `rel=` | `type=` | Source |
|----------|--------|---------|--------|
| Home page | `describedby` | — | Static front page (stored by ID) or `home_url('/')` |
| RSS feed | `alternate` | `application/rss+xml` | `get_feed_link()` |
| XML sitemap | `alternate` | `application/xml` | Core sitemaps (`/wp-sitemap.xml`) |
| `llms.txt` | `describedby` | `text/plain` | `home_url('/llms.txt')` |

Existing configuration is never overwritten — defaults are only seeded when no entries exist yet.

## Usage

Go to **Settings → Link Headers**:

- **Add a Page / Post** — start typing a title, pick a result. Its URL is resolved from the permalink at output time, so it stays correct even if your permalink structure changes.
- **Add a Custom URL** — enter any absolute URL with its `rel`, and optional `type`/`title`.
- **Reorder** rows by dragging the handle; **toggle** the switch to enable/disable; **Remove** to delete.
- Watch the **Live header preview** update to confirm the exact headers that will be emitted.

### Which `rel` should I use?

| Goal | Suggested `rel` |
|------|-----------------|
| Point agents at the authoritative URL | `canonical` |
| Say "this resource describes the site/context" (about page, `llms.txt`, docs) | `describedby` |
| Offer an alternate representation (feed, sitemap, JSON) | `alternate` (+ a `type`) |
| Personal identity / authorship | `me`, `author` |

## WP-CLI

Manage entries from the command line with `wp link-headers <subcommand>`. On multisite, target a site with the global `--url=<site-url>` flag.

```bash
# List entries (with their indexes)
wp link-headers list
wp link-headers list --enabled-only --format=json

# Add a custom URL
wp link-headers add https://example.com/about --rel=describedby
wp link-headers add https://example.com/feed/ --rel=alternate --type=application/rss+xml --title="My Feed"

# Add a page/post by ID (URL resolved from its permalink)
wp link-headers add-post 42 --rel=describedby

# Toggle and remove (by index from `list`)
wp link-headers enable 1
wp link-headers disable 2
wp link-headers remove 3

# Seed the four defaults
wp link-headers seed                    # no-op if any entries exist
wp link-headers seed --force            # overwrite with defaults
wp link-headers seed --network --force  # every site in the network

# Remove all entries for a site
wp link-headers clear --yes
```

## Multisite

- **Network Activate** seeds the four defaults on every existing site.
- Sites created afterwards are seeded automatically.
- Each site keeps its **own independent** set of entries (stored per-site, not network-wide).
- Re-seed everything at once with `wp link-headers seed --network`.

## How it works

| Concern | Implementation |
|---------|----------------|
| Front-end headers | `send_headers` action emits one `Link:` header per enabled entry |
| REST API headers | `rest_post_dispatch` filter adds the same headers to `WP_REST_Response` |
| Page/post URLs | Resolved via `get_permalink()` at output time (never stored stale) |
| Storage | A single per-site option, `wp_link_headers_entries` |
| New network sites | `wp_initialize_site` hook seeds defaults when network-active |

### Project structure

```
wp-link-headers/
├── wp-link-headers.php                 # Loader, hooks, WP-CLI registration
├── uninstall.php                       # Removes data on deletion (network-aware)
├── includes/
│   ├── class-link-headers-output.php   # Emits the HTTP Link headers
│   ├── class-link-headers-admin.php    # Settings UI + AJAX
│   ├── class-link-headers-installer.php# Default entries, seeding, activation
│   └── class-link-headers-cli.php      # `wp link-headers` commands
├── assets/
│   ├── css/admin.css
│   └── js/admin.js
└── readme.txt                          # WordPress.org-format readme
```

## Data &amp; privacy

- The only data stored is the `wp_link_headers_entries` option (per site).
- The plugin **only emits** URLs as headers — it never fetches or contacts them.
- **Deactivating** the plugin **preserves** your settings (safe to deactivate/reactivate while troubleshooting).
- **Deleting** the plugin removes all of its data via `uninstall.php` (every site on multisite).

## Requirements

- WordPress 6.0 or later
- PHP 8.0 or later

## License

[GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html) © Kevin Bowers
