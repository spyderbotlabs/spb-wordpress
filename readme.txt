=== SpyderBot Tracking ===
Contributors: spyderbot
Tags: analytics, llm, tracking, geo, spyderbot, ai-traffic
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI Traffic tracking for WordPress — browser script (wfm.js) and server-side bot capture for SpyderBot.

== Description ==

SpyderBot Tracking connects your WordPress site to SpyderBot AI Traffic so you can measure LLM referrals, AI bot visits, and GEO performance.

* **Browser script** — loads hosted `wfm.js` once (async/defer) for human/engagement tracking
* **Server-side** — fire-and-forget POST for crawlers / LLM bots that do not execute JavaScript
* **Shared session** — first-party cookie `_spyd_sid` (SameSite=Lax, 30-minute rolling) shared with wfm.js
* **Page cache awareness** — detects WP Rocket / LiteSpeed Cache / W3TC / WP Super Cache and can exclude known AI bot User-Agents from page cache

Get your **Tracking key** from SpyderBot → AI Traffic → your project (project ingest key). Do not use a legacy website key.

== Installation ==

1. Upload the `spyderbot-tracking` folder to `/wp-content/plugins/` (or install the ZIP via Plugins → Add New).
2. Activate through the Plugins menu.
3. Go to Settings → SpyderBot Tracking.
4. Paste your Tracking key, enable browser and/or server tracking.
5. Click **Test connection**.

== Frequently Asked Questions ==

= Which API URL is used? =

Production defaults to `https://api.spyderbot.net/server-track` (canonical public ingest). The browser script loads from `https://spyderbot.net/wfm.js`.

= Why are bots missed when page cache is on? =

Full-page caches serve HTML without running PHP. Enable “Exclude AI bot UAs from page cache”, or install the SpyderBot Cloudflare Worker if the site is on Cloudflare.

= Does server-side tracking count humans? =

No. The plugin only POSTs for bot/LLM User-Agents. Humans are handled by `wfm.js` to avoid double counting.

== Privacy ==

This plugin may send the following to SpyderBot ingest endpoints:

* Page URL and referrer
* Session identifier (`_spyd_sid`)
* Client IP / country (when available via Cloudflare or proxies)
* User-Agent
* Optional UTM parameters
* Engagement events when the browser script is enabled

Data is processed according to the [SpyderBot Privacy Policy](https://spyderbot.net/privacy).

== Changelog ==

= 0.3.2 =
* Composer lock compatible with PHP 7.4+ CI matrix

= 0.3.1 =
* Public release packaging / CI metadata refresh

= 0.3.0 =
* Production environment preset (api.spyderbot.net)
* Real HTTP status_code on server-track payloads
* Zip packaging script, languages/ text domain path, Tested up to 7.1
* Environment selector, masked key, excluded paths, cache-compat, wfm.js enqueue, bot-only server track, tests

= 0.2.0 =
* Updated client bundle

= 0.1.0 =
* Initial release
