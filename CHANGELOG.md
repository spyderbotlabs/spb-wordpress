# Changelog

All notable changes to the SpyderBot Tracking WordPress plugin are documented here.

## [0.3.1] — 2026-10-30

### Changed
- Public package metadata / Composer lock refresh for GitHub Actions CI.
- Documentation cleanup for the public GitHub release.

## [0.3.0] — 2026-10-30

### Added
- Production environment preset (`api.spyderbot.net/server-track`).
- Real `status_code` on server-track payloads (404 / `http_response_code()`, not hard-coded 200).
- `languages/` directory + text-domain load path; Tested up to WordPress 7.1.
- `bin/build-zip.sh` packaging script (`composer build-zip` → `spyderbot-tracking-0.3.0.zip`).
- Masked tracking-key field (sentinel keep-on-save); Test connection with clear success/error.
- Excluded path prefixes; page-cache notice + AI bot UA bypass filters (WP Rocket, LiteSpeed Cache, W3TC, WP Super Cache).
- Shared `_spyd_sid` cookie (SameSite=Lax, 30-minute rolling) with remote `wfm.js` enqueue (async/defer, once).
- Server-side posts only for bot/LLM UAs with full ingest contract fields and `source: wordpress_plugin`.
- `uninstall.php`, Brain Monkey PHPUnit suite, GitHub Actions CI.

### Changed
- Fire-and-forget via `shutdown` + non-blocking `wp_remote_post` (≤1–1.5s).
- Client tracking uses hosted `wfm.js`; unused bundled `spyder.min.js` removed.

## [0.2.0] — prior
- Updated client path toward hosted wfm.js.

## [0.1.0] — prior
- Initial MVP: settings, server-track, client bundle hook.
