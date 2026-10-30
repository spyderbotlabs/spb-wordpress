# SpyderBot Tracking — WordPress Plugin

WordPress plugin for [SpyderBot](https://spyderbot.net) AI Traffic: browser script (`wfm.js`) plus server-side bot capture. License: [GPL-2.0-or-later](LICENSE).

**v0.3.0** — [GitHub releases](https://github.com/spyderbotlabs/spb-wordpress/releases).

## Features

- **Settings → SpyderBot Tracking**
  - Tracking key (stored in `wp_options`, masked in the UI)
  - API environment: Production
  - Toggles: browser script (`wfm.js`), server-side bot tracking
  - Excluded path prefixes
  - Page-cache notice + “Exclude AI bot UAs from page cache”
  - **Test connection** (blocking POST, clear success/error)
- **Server-side bot tracking** on front-end HTML (`GET`/`HEAD`): fire-and-forget on `shutdown` with `wp_remote_post( blocking => false )`, timeout ≤1–1.5s
- **Browser script**: enqueue hosted `wfm.js` once (async/defer) + `preconnect`; shares `_spyd_sid`
- **Cache compat**: WP Rocket / LiteSpeed Cache / W3 Total Cache / WP Super Cache UA filters + `DONOTCACHEPAGE` for bots when PHP runs

## Install

1. Download `spyderbot-tracking-0.3.0.zip` from [Releases](https://github.com/spyderbotlabs/spb-wordpress/releases), or clone this repo and run `composer build-zip`.
2. Upload via Plugins → Add New → Upload Plugin (or unpack into `wp-content/plugins/spyderbot-tracking`).
3. Activate, then open Settings → SpyderBot Tracking.
4. Paste your AI Traffic **project tracking key**, save, and click **Test connection**.

## Configuration

| Option | Default | Notes |
|--------|---------|--------|
| Tracking key | _(empty)_ | Never commit real keys |
| Environment | Production | `https://api.spyderbot.net/server-track` + script `https://spyderbot.net/wfm.js` |
| Browser script | on | Remote `wfm.js?key=…&v=…` |
| Server-side | on | Bots only (`source: wordpress_plugin`) |
| Excluded paths | _(none)_ | One path prefix per line |
| Cache bot bypass | on | Filters listed above |

### Ingest contract (body)

`url`, `referrer`, `session_id`, `client_ip`, `country`, `method`, `status_code`, `utm_source`, `utm_medium`, `utm_campaign`, `source` (= `wordpress_plugin`). Header `X-Tracking-Key` + visitor `User-Agent`.

`client_ip` from `CF-Connecting-IP` → `X-Forwarded-For` → `REMOTE_ADDR`. `country` from `CF-IPCountry`.

## Page cache

| Situation | Coverage |
|-----------|----------|
| WP Rocket / LiteSpeed / W3TC / WP Super Cache with bypass enabled | Bot UAs added to reject lists / non-cacheable; PHP can run and POST |
| Edge HTML cache with no origin hit (e.g. Cloudflare cache everything) | **Not covered** — use the [Cloudflare Worker](https://github.com/spyderbotlabs/spb-cloudflare-worker) |
| Logged-in admins / AJAX / REST / cron / feeds | Skipped by design |

## Development

```bash
# PHP 7.4+ / 8.x
composer install
composer test   # or: vendor/bin/phpunit
composer build-zip
```

Tests use [Brain Monkey](https://brain-wp.github.io/BrainMonkey/) (no full WordPress checkout).

Browser path is hosted `wfm.js` (no bundled client script).

## Uninstall

`uninstall.php` deletes plugin options and the test-connection transient (including multisite per-blog cleanup).

## License

Plugin PHP: **GPL-2.0-or-later**.  
Hosted `wfm.js` is served by SpyderBot app origins.

## Links

- Integrations: https://spyderbot.net/integrations
- Cloudflare Worker: https://github.com/spyderbotlabs/spb-cloudflare-worker
