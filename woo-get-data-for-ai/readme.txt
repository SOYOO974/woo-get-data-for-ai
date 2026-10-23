=== WP Agent Bridge (Data for AI) ===
Contributors: soyoo
Tags: woocommerce, ai, diagnostics, rest-api, audit
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.43.5
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Enterprise-grade, read-only inspection API for WordPress & WooCommerce. Securely exposes system state, logs, Elementor trees, WPCode & Code Snippets, and theme options to AI agents (Antigravity, Claude, Cursor).

== Description ==

WP Agent Bridge exposes a protected, read-only REST API (`agent-bridge/v1/`) designed for AI coding assistants and developer tools to inspect live WordPress and WooCommerce stores safely without full SSH, SFTP, or database credentials.

== Changelog ==

= 1.43.5 - 2026-10-23 =
* Fix: Format `SKILL.md` YAML frontmatter `description` as a folded strip block scalar (`description: >-` with 2-space single-line indentation) to prevent YAML parser errors when site names contain colons (`:`), hashes (`#`), or typographic apostrophes.
* Enhancement: Automatically preserve any custom (non-generic) `description` from an existing target `SKILL.md` during skill regeneration (`Playbooks::generate_skill_markdown()` and `cli/sync.js`), regenerating only the markdown body.
* Fix: Enforce strict UTF-8 without BOM and LF (`\n`) line endings on generated `SKILL.md` output and serve raw markdown on `GET /capabilities?format=skill`.

= 1.43.4 - 2026-10-23 =
* Fix: Exclude `WP_Automatic_Updater` informational post-update messages from Crash Watch (`GET /logs/errors-summary`) and tighten fatal error regex matching.
