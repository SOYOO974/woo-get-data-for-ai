# WP Agent Bridge — Project Context & Architecture Memory

> **Last Updated**: 2026-09-05  
> **Plugin Identifier / Slug**: `woo-get-data-for-ai`  
> **Main Plugin File**: `woo-get-data-for-ai/woo-get-data-for-ai.php`  
> **GitHub Repository**: `https://github.com/SOYOO974/woo-get-data-for-ai`  
> **Updates**: Integrated `plugin-update-checker` (PUC v5.6) configured for GitHub branch `main` and release assets.  
> **Security Mandate**: 100% Read-Only (`GET` requests only). Zero hardcoded secrets, tokens, or credentials.  
> **PUBLIC REPOSITORY PRIVACY WARNING**: This file is tracked in a public GitHub repository so the developer can access it across workstations. **It must NEVER contain sensitive, private, or confidential information** (no client domains, real store URLs, live API tokens, passwords, database dumps, or customer PII).

---

## 1. Project Mission & Vision
**WP Agent Bridge** is an enterprise-grade, lightweight, and ultra-secure WordPress & WooCommerce inspection plugin. Its sole purpose is to expose a protected, read-only REST API (`agent-bridge/v1/`) to enable AI coding assistants (Antigravity, Cursor, Claude, ChatGPT) and developers to instantly audit, diagnose bugs, and retrieve technical context from live sites without requiring risky SFTP/SSH access or database credentials.

---

## 2. Core Constraints & Standards

### A. Public GitHub & Zero-Secret Architecture
- The entire codebase is hosted publicly on GitHub (`https://github.com/SOYOO974/woo-get-data-for-ai`).
- **MANDATORY PRIVACY & DATA LEAK PREVENTION (CRITICAL FOR DEVELOPERS & AI AGENTS)**:
  - This repository and this document (`PROJECT_CONTEXT.md`) are public to facilitate cross-machine synchronization and open development.
  - **NEVER insert or commit any sensitive, private, or proprietary data**:
    - ❌ **NO real client site URLs, staging domains, or production hostnames** (always use `https://example.com` or `https://your-site.com`).
    - ❌ **NO live Bearer tokens, API keys, passwords, salts, or encryption secrets**.
    - ❌ **NO customer personally identifiable information (PII)**, real order data, or unredacted log excerpts from production sites.
    - ❌ **NO internal server IPs, private paths, or database credentials**.
  - Any AI assistant (Antigravity, Cursor, Claude, ChatGPT, etc.) creating, editing, or updating this document must systematically review and sanitize all additions before saving and committing.
- **NO hardcoded secrets, API keys, database credentials, internal domains, or tokens are allowed in any file.**
- **Token Management**:
  - The access token is generated on-demand inside the WordPress admin panel using `wp_generate_password(64, true, true)`.
  - Stored securely in `wp_options` as `wp_agent_bridge_token`.
  - Administrators can revoke and regenerate the token with 1 click.
  - Optional override supported via `WP_AGENT_BRIDGE_TOKEN` in `wp-config.php`.
- **Automatic Updates & Release Protocol**:
  - Integrated with `plugin-update-checker` (PUC v5.6) configured for GitHub releases/branch tracking (`enableReleaseAssets()`).
  - **MANDATORY RELEASE RULE FOR AGENTS & DEVELOPERS**:
    Every time changes/commits are pushed for a new version:
    1. **Version Bump**: Increment the version number in both the plugin header (`Version: X.Y.Z`) and the constant `WOO_GET_DATA_AI_VERSION` in `woo-get-data-for-ai/woo-get-data-for-ai.php`.
    2. **Changelog & Documentation**: Document all new features, bugfixes, and breaking changes in `PROJECT_CONTEXT.md` and `README.md`.
    3. **Create a GitHub Release**:
       - Push commits and create a corresponding Git tag (e.g. `v1.0.1` or `1.0.1`).
       - Draft and publish a formal **GitHub Release** on `https://github.com/SOYOO974/woo-get-data-for-ai/releases`.
       - Document the release notes clearly on GitHub.
       - Attach the zipped plugin directory (`woo-get-data-for-ai.zip`) as a Release Asset.
    > ⚠️ **Without creating a documented GitHub Release with a higher version tag, client WordPress sites will NOT trigger or detect the auto-update.**


### B. Internationalization (i18n)
- Primary language: **English** (code, PHPDoc, UI strings, documentation).
- Text Domain: `woo-get-data-for-ai`
- Domain Path: `/languages`
- Translation-ready for **Loco Translate** and standard WordPress polyglot tools.
- Includes `languages/woo-get-data-for-ai.pot` and compiled French translations (`woo-get-data-for-ai-fr_FR.po` / `woo-get-data-for-ai-fr_FR.mo`).

### C. Strict Read-Only Enforcement
- **100% of endpoints are `GET` only (`WP_REST_Server::READABLE`).**
- No `POST`, `PUT`, `DELETE`, or `PATCH` routes.
- The plugin contains zero execution, modification, or write primitives.
- All database queries are strictly `SELECT` with sanitized inputs.

### D. High-Performance & Memory Protection
- Optimized for high-traffic WooCommerce stores.
- **Log Streaming**: Uses reverse file pointer (`fseek`) to extract the last $N$ lines of log files (`debug.log`, `wc-logs/`) without loading multi-megabyte files into RAM.
- **Cache & Memory**: Flushes runtime cache on heavy reads and disables `SAVEQUERIES`.
- **Rate Limiting**: Built-in request rate limiting using WordPress Transients API.

---

## 3. Administration Interface (Tabbed Settings)

Located under **WordPress Admin > Settings > Agent Bridge**:

### Tab 1: General & Status
- Live connection & health indicator.
- REST API Base URL display (`/wp-json/agent-bridge/v1/`).
- Bearer Token viewer (masked by default, with copy and show/hide toggles).
- One-click Token Regeneration button with confirmation dialog.
- Optional IP Whitelist configuration (comma-separated list of allowed IPs).

### Tab 2: Granular Permissions Matrix
Enables/disables modules on a per-site basis:
- `[x] System & Server Environment` (`/system`, `/ping`)
- `[x] WooCommerce Diagnosis & Overrides` (`/theme/overrides`, HPOS state)
- `[x] Theme Settings (Woodmart & Elessi)` (`/theme/options`, `/theme/child`)
- `[x] Code & Plugin Inspector` (`/code/plugins`, `/code/file`)
- `[x] Elementor Architecture` (`/elementor/list`, `/elementor/forms`, `/elementor/kit`)
- `[x] WPCode Snippets` (`/wpcode/snippets`)
- `[x] Error & WooCommerce Logs` (`/logs/sources`, `/logs/view`)
*(When a module is toggled off, any API request to its endpoints returns HTTP 403 Forbidden).*

### Tab 3: AI Onboarding & Mega-Prompt Generator
- One-click "Copy AI Prompt" widget.
- Dynamically generates a ready-to-use prompt for Antigravity/Claude/Cursor containing:
  - Active site name and REST Base URL.
  - Active Bearer Token.
  - List of active endpoints according to the permissions matrix.
  - Usage examples with `curl -s`, parameter options (`filter`, `lines`, `status`, `target`).
  - Full instructions for the AI to interact with the site or generate an automated Skill (`SKILL.md`).
  - Status handling rules (HTTP 403, 429).
  - Missing data & plugin evolution protocol (instructs AI to draft a feature request / email to `julien@soyoo.re`).

### Tab 4: Access Logs & Country Analytics
- Custom lightweight table: `{$wpdb->prefix}agent_bridge_logs` with auto-purge keeping the latest 500 records.
- Historical cumulative metrics preserved in `wp_options` (`wp_agent_bridge_log_summary`).
- Summary metrics:
  - Total requests (last 24h & last 7 days).
  - Top client IP addresses.
  - Breakdown of requests by country.
- **GeoIP Detection**:
  1. Instant resolution via Cloudflare header `HTTP_CF_IPCOUNTRY` (0ms overhead).
  2. Fallback via cached IP geolocation transient for non-Cloudflare environments.
- Real-time audit log table:
  - Timestamp (local time).
  - Client IP & Country flag / name.
  - Endpoint queried & HTTP status code (`200`, `401`, `403`).
  - Client User-Agent.

---

## 4. REST API Endpoint Catalog (`agent-bridge/v1/`)

| Endpoint | Method | Purpose |
| :--- | :--- | :--- |
| `GET /ping` | GET | Connectivity check, server timestamp, site name |
| `GET /system` | GET | WP/WC/PHP/MySQL versions, active plugins & updates, HPOS status, Action Scheduler queue |
| `GET /theme/options` | GET | Decoded options for **Woodmart** (`xts-woodmart-options`), **Elessi** (`elessi_options` / Redux), and Customizer theme mods (sensitive keys redacted) |
| `GET /theme/overrides` | GET | WooCommerce template overrides in the active theme with version comparison to core WC |
| `GET /theme/child` | GET | Code and header info of the child theme's `functions.php` and `style.css` |
| `GET /code/plugins` | GET | File trees of active plugins and `wp-content/mu-plugins/` |
| `GET /code/file` | GET | Source code of a specific PHP/JS/CSS file (strictly sandboxed via `realpath()`) |
| `GET /elementor/list` | GET | Elementor pages, posts, and templates (`elementor_library`) |
| `GET /elementor/item/{id}` | GET | Full decoded `_elementor_data` JSON tree and page settings |
| `GET /elementor/forms` | GET | Inventory of all Elementor forms (field definitions, actions, webhook URLs) |
| `GET /elementor/kit` | GET | Global colors, system fonts, and design tokens from the active Elementor Kit |
| `GET /wpcode/snippets` | GET | Listing of all WPCode snippets (PHP, JS, CSS, HTML, active state, hooks, source) |
| `GET /wpcode/snippet/{id}` | GET | Full source code and configuration of a targeted snippet |
| `GET /logs/sources` | GET | Available log files (`debug.log`, `uploads/wc-logs/*.log`, custom logs) with sizes & dates |
| `GET /logs/view` | GET | Memory-safe tail extraction of the last $N$ lines with optional error filtering |

---

## 5. Security, Redaction & Anti-Brute-Force Engine
- **Anti-Brute-Force & IP Lockout**:
  - Automatically tracks consecutive failed authentication attempts per client IP.
  - After exceeding threshold (configurable, default: 5 attempts), client IP is temporarily locked out for XX minutes (configurable, default: 30 minutes).
  - Returns HTTP 429 Too Many Requests with remaining minutes countdown.
  - Active lockouts are monitored in WordPress Admin (Settings > Agent Bridge > General & Status) with a 1-click manual "Unlock IP" action.
  - Counter resets automatically on successful authentication.
- **Secret Redaction**: Recursively masks sensitive fields before JSON output:
  - Stripe secret/restricted keys (`sk_live_...`, `rk_live_...`)
  - Payment gateway API keys & webhook secrets (Alma, PayPal, Mollie)
  - SMTP passwords, JWT secrets, database passwords, WordPress salts
  - Customer PII (emails, phone numbers in order debug logs)
- **Path Sandboxing**:
  - `realpath()` validation ensures file requests cannot escape `WP_PLUGIN_DIR`, `get_theme_root()`, `WPMU_PLUGIN_DIR`, or `uploads/wc-logs/`.
  - Blocklist strictly denies access to `wp-config.php`, `.env`, `.git`, `.htaccess`.


---

## 6. Local CLI Client (`cli/sync.js`)
- Standalone Node.js script supporting `.env` configuration.
- Commands: `pull:all`, `pull:system`, `pull:theme`, `pull:elementor`, `pull:snippets`, `pull:logs`.
- Generates a cleanly structured local export under `./synced-site-data/`.

---

## 7. Version Changelog

### v1.0.2 (2026-09-05)
- **Enhanced AI Mega-Prompt**:
  - Added explicit instructions for missing data handling: AI drafts feature requests / ready-to-send emails to `julien@soyoo.re` with proposed endpoint routes and controller code.
  - Added documentation for query parameters in curl examples (`filter` and custom `lines` up to 1000 in `/logs/view`, `status=active` in `/code/plugins`, `target=all` in `/theme/options`).
  - Added HTTP status code handling guidance for AI agents (HTTP 403 module permissions, HTTP 429 rate limit / anti-brute-force lockout).
  - Clarified silent mode `curl -s` and read-only action guidelines.
  - Increased prompt textarea height in admin settings for better usability.

### v1.0.1
- Initial public release with PUC v5.6 updater, admin tabs, security redaction, and core REST controllers.
