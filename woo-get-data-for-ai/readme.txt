=== WP Agent Bridge (Data for AI) ===
Contributors: soyoo
Tags: woocommerce, ai, diagnostics, rest-api, audit
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.45.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Enterprise-grade, read-only inspection API for WordPress & WooCommerce. Securely exposes system state, logs, Elementor trees, WPCode & Code Snippets, and theme options to AI agents (Antigravity, Claude, Cursor).

== Description ==

WP Agent Bridge exposes a protected, read-only REST API (`agent-bridge/v1/`) designed for AI coding assistants and developer tools to inspect live WordPress and WooCommerce stores safely without full SSH, SFTP, or database credentials.

== Changelog ==

= 1.45.0 - 2026-10-28 =
* Feature: `GET /guardian/snapshot` now returns `meta.urls` (`cart`, `checkout`, `shop`) resolved with `wc_get_cart_url()`, `wc_get_checkout_url()` and `wc_get_page_permalink('shop')`, so visitor probes never guess localized paths.
* Feature: New global helper `soyoo_is_guardian_probe()` returning true when the User-Agent contains `SOYOO-Guardian-Probe`, for SOYOO tracking and feed plugins to ignore synthetic visitor probes.

= 1.44.0 - 2026-10-28 =
* Feature: New `GET /guardian/snapshot?since=<ISO 8601 UTC>&top=10` endpoint (module `guardian`) returning one dated raw-figures snapshot for nightly monitoring agents: PHP fatals with stable fingerprints, gateways and failed orders per gateway, payment log errors split into declined vs technical, paid orders and stuck order counters, Alma plugin/shield integrity and unrecovered high-ticket orders (>= 150) with recovery detection, best sellers out of stock, Action Scheduler and WP-Cron lag, wp_mail failures, Meta catalog feed metadata and Meta CAPI counters. No severity verdict is computed by the plugin.
* Feature: Passive change journal (`includes/class-change-journal.php`, non-autoloaded option, FIFO 300 entries): plugin/theme/core updates with from/to versions and actor (user id, auto, cron, wp-cli), plugin activations/deactivations, theme switches, payment gateway settings changes (changed keys only, never values), daily md5 drift check of `wp-content/mu-plugins/*.php`, and `wp_mail_failed` events without recipients.
* Performance: per-section timings in `meta.timings`, 60 s transient cache per `since`/`top`, bounded HPOS-compatible queries via `wc_get_orders()`, backward log streaming capped at 8 MB per file. A failing section returns `{"error": "..."}` without breaking the HTTP 200 response.
* i18n: `cli/sync-i18n.php` now also generates `woo-get-data-for-ai-fr_FR.l10n.php` (WordPress 6.5+ PHP translation file) on every sync.

= 1.43.5 - 2026-10-23 =
* Fix: Format `SKILL.md` YAML frontmatter `description` as a folded strip block scalar (`description: >-` with 2-space single-line indentation) to prevent YAML parser errors when site names contain colons (`:`), hashes (`#`), or typographic apostrophes.
* Enhancement: Automatically preserve any custom (non-generic) `description` from an existing target `SKILL.md` during skill regeneration (`Playbooks::generate_skill_markdown()` and `cli/sync.js`), regenerating only the markdown body.
* Fix: Enforce strict UTF-8 without BOM and LF (`\n`) line endings on generated `SKILL.md` output and serve raw markdown on `GET /capabilities?format=skill`.

= 1.43.4 - 2026-10-23 =
* Fix: Exclude `WP_Automatic_Updater` informational post-update messages from Crash Watch (`GET /logs/errors-summary`) and tighten fatal error regex matching.
