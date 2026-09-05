# WP Agent Bridge (Woo & WP Data for AI)

[![License: GPL-2.0](https://img.shields.io/badge/License-GPL%202.0-blue.svg)](http://www.gnu.org/licenses/gpl-2.0.txt)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net)
[![GitHub Updates](https://img.shields.io/badge/Updates-PUC%20v5.6-success.svg)](https://github.com/SOYOO974/woo-get-data-for-ai)

**WP Agent Bridge** is an enterprise-grade, lightweight, and ultra-secure WordPress and WooCommerce inspection plugin. It exposes a protected, read-only REST API (`agent-bridge/v1/`) designed specifically for AI coding assistants (**Antigravity**, **Claude**, **Cursor**, **ChatGPT**) and developer tools.

It allows AI assistants to instantly inspect live site configurations, debug logs, Elementor trees, WPCode snippets, and theme settings (Woodmart, Elessi) safely without requiring full SSH, SFTP, or database access.

---

## 🌟 Key Capabilities

- **100% Read-Only Security**: All endpoints strictly enforce HTTP `GET` (`WP_REST_Server::READABLE`). No remote write or database modification primitives exist.
- **Zero-Secret Public Codebase**: Designed for public hosting on GitHub. Tokens are generated on-demand inside WordPress admin, never stored in plugin files.
- **Dynamic AI Discovery (`/capabilities`)**: Self-describing schema endpoint allowing AI assistants to automatically discover available modules, active permissions, and supported parameters.
- **2-Click AI Onboarding**: Tab featuring a streamlined Bootstrap Prompt that empowers AI agents to auto-generate and maintain their local skill (`SKILL.md`) without prompt bloat.
- **Granular Permissions Matrix**: Toggle access to specific modules (System, Themes, Code, Elementor, WPCode, Logs, FlowMattic, Analytics) via checkboxes in the admin panel.
- **Deep WooCommerce Diagnostics**: Audits template overrides in child/parent themes, detects outdated templates, and inspects HPOS (High-Performance Order Storage) status.
- **Theme Settings Export**: Deep inspection and decoding of **Woodmart** (`xts-woodmart-options`), **Elessi** (`elessi_options` / Redux), and child theme `functions.php` / `style.css`.
- **Sandboxed Code Inspector**: Safely inspects file trees of active plugins and `mu-plugins`, and reads specific PHP/JS/CSS files with strict `realpath` validation.
- **High-Performance Log Streaming**: Memory-safe reverse file tailing (`fseek`) for `debug.log` and `uploads/wc-logs/*.log` preventing PHP memory exhaustion on heavy production sites.
- **Automatic Data Redaction**: Real-time regex engine that masks Stripe secret keys (`sk_live_*`), API tokens, passwords, database credentials, and customer email addresses before output.
- **FlowMattic Automations**: Bulk and targeted export of all workflows, triggers, actions, and execution task stats in native importable JSON format.
- **Independent Analytics Intelligence**: Complete visibility over site traffic, unique visitors, pageviews, acquisition channels, UTM campaigns, device breakdowns, and WooCommerce **conversion rates**, net sales, and AOV.
- **Automatic Updates via GitHub**: Fully integrated with `plugin-update-checker` (PUC v5.6).

---

## 📂 Repository Structure

```
├── PROJECT_CONTEXT.md          # Architecture memory and technical specification
├── README.md                   # Plugin documentation
├── .gitignore                  # Git exclusions
├── cli/                        # Standalone local synchronization client
│   ├── sync.js                 # Zero-dependency Node.js CLI script
│   ├── package.json            # CLI metadata and scripts
│   └── .env.example            # Environment configuration template
└── woo-get-data-for-ai/        # WordPress plugin root directory
    ├── woo-get-data-for-ai.php # Main entry point & PUC configuration
    ├── plugin-update-checker/  # Bundled PUC v5.6 updater library
    ├── languages/              # Gettext i18n catalogs (.pot and .po/.mo for French)
    ├── assets/                 # Admin CSS and JS assets
    └── includes/               # Plugin architecture classes & REST controllers
```

---

## 🚀 Installation & Setup

### 1. Installation
1. Download or clone this repository.
2. Copy the `woo-get-data-for-ai` folder into your site's `wp-content/plugins/` directory (or zip it and upload via **WordPress Admin > Plugins > Add New**).
3. Activate the plugin in WordPress.

### 2. Configuration
1. Navigate to **WordPress Admin > Settings > Agent Bridge**.
2. **General & Status Tab**:
   - Check your REST Base URL: `https://your-site.com/wp-json/agent-bridge/v1/`.
   - Copy your 64-character Bearer Access Token.
   - *(Optional)* Define a static token in `wp-config.php`:
     ```php
     define('WP_AGENT_BRIDGE_TOKEN', 'your_secure_64_character_token_here');
     ```
   - *(Optional)* Configure an IP whitelist to restrict access to specific developer IPs.
3. **Permissions Matrix Tab**:
   - Check the modules you wish to allow external AI tools to query.
4. **AI Mega-Prompt & Skill Tab**:
   - Click **"Copy Mega-Prompt for AI"** and paste it directly into Antigravity, Cursor, or Claude Desktop!
5. **Documentation & Guide Tab**:
   - Consult the complete on-site manual, architectural pillars, 4-step quickstart, and GitHub contribution guidelines.

---

## 📡 REST API Catalog (`agent-bridge/v1/`)

All requests must authenticate using the 64-character hexadecimal Bearer token in the `Authorization` header:
```bash
Authorization: Bearer <YOUR_ACCESS_TOKEN>
```
*(Fallback headers `X-Agent-Bridge-Token` or `X-API-Key`, or URI parameter `?access_token=<TOKEN>` are also supported if your web server strips the Authorization header).*

| Endpoint | Description |
| :--- | :--- |
| `GET /ping` | Health check, server time, site name, and plugin version. |
| `GET /capabilities` | Dynamic discovery catalog & schema: lists all modules, permissions, endpoints, and supported query parameters for AI agents. |
| `GET /system` | Server limits (PHP, RAM, execution time), WP core info, active plugins with update status, HPOS state, and Action Scheduler queue. |
| `GET /theme/options` | Decoded options for **Woodmart** (`xts-woodmart-options`), **Elessi** (`elessi_options`), and theme mods. |
| `GET /theme/overrides` | Audit of WooCommerce template overrides with version comparison against core WooCommerce. |
| `GET /theme/child` | Code and metadata for child theme `functions.php` and `style.css`. |
| `GET /code/plugins` | File trees for active plugins and `wp-content/mu-plugins/`. |
| `GET /code/file?path={relative_path}` | Sandboxed code viewer for specific PHP, JS, or CSS files. |
| `GET /elementor/export-all` | Bulk export of all Elementor pages, templates, kit & forms in 1 optimized request. |
| `GET /elementor/list` | Pages and templates built with Elementor. |
| `GET /elementor/item/{id}` | Decoded JSON element tree (`_elementor_data`) and page settings. |
| `GET /elementor/forms` | Inventory of Elementor forms, fields, and submit actions (webhooks, emails). |
| `GET /elementor/kit` | Global colors, system fonts, and design tokens from the active Elementor Kit. |
| `GET /wpcode/snippets` | Custom PHP, JS, and CSS snippets stored in WPCode (or Code Snippets plugin). |
| `GET /wpcode/snippet/{id}` | Source code and metadata of a specific snippet. |
| `GET /logs/sources` | Available log files (`debug.log`, `uploads/wc-logs/*.log`) with file sizes and dates. |
| `GET /logs/view?source={file}&lines=200` | Memory-safe tail extraction of the latest log lines. |
| `GET /flowmattic/export-all` | Bulk export of all FlowMattic automation workflows in 1 optimized request. |
| `GET /flowmattic/workflows` | List all FlowMattic workflows (ID, name, status, triggers, steps, tasks executed). |
| `GET /flowmattic/workflow/{id}?format=export` | Download a workflow in FlowMattic's native importable JSON format. |
| `GET /analytics/overview` | 360° consolidated audit in 1 request (traffic KPIs, conversion rate, top pages, referrers, campaigns, devices). |
| `GET /analytics/summary?range={range}` | Traffic KPIs (visitors, views, bounce rate, duration) and WooCommerce conversion rate, sales, AOV, and % growth. |
| `GET /analytics/pages?sort=views&limit=25` | Content and product performance with pageviews, visitors, orders, and product conversion rates. |
| `GET /analytics/referrers` | Traffic sources (domains, search engines, social media) with attributed orders and conversion rates. |
| `GET /analytics/campaigns` | Marketing UTM tracking (`utm_source`, `utm_medium`, `utm_campaign`) with revenue attribution. |
| `GET /analytics/devices` | Device types (Mobile vs Desktop vs Tablet), browsers, and OS comparison with conversion rates. |
| `GET /analytics/geo` | Geographic distribution of visitors and orders by country and city. |
| `GET /analytics/conversions` | Recent conversion stream (orders, form submissions) with attribution (zero PII). |

---

## 💻 Local CLI Client (`cli/`)

A zero-dependency Node.js client is included to dump and synchronize site data directly into your local workspace.

### Usage
```bash
# 1. Navigate to the cli directory
cd cli

# 2. Configure credentials
cp .env.example .env
# Edit .env with your SITE_URL and AGENT_BRIDGE_TOKEN

# 3. Run synchronization commands
node sync.js pull:all          # Synchronizes everything into ./synced-site-data
node sync.js pull:capabilities # Dumps capabilities & schema to capabilities.json & capabilities.md
node sync.js pull:system       # Generates system-report.md
node sync.js pull:theme        # Dumps Woodmart/Elessi options & WC overrides
node sync.js pull:elementor    # Dumps Elementor pages, forms, and kits
node sync.js pull:snippets     # Dumps WPCode snippets to individual .php/.js files
node sync.js pull:flowmattic   # Dumps FlowMattic workflows to native JSON files
node sync.js pull:analytics    # Dumps Independent Analytics to overview.json & summary.md
node sync.js pull:logs         # Downloads tail of debug.log & wc-logs
```

---

## 🔒 Security & Privacy

- **Read-Only**: No endpoints allow `POST`, `PUT`, `DELETE`, or `PATCH`.
- **Anti-Brute-Force & IP Lockout**: Automatically tracks failed authentication attempts. After 5 failed attempts (configurable), offending IPs are blocked for 30 minutes (configurable), returning HTTP 429. Includes 1-click unlock from admin.
- **Secret Redaction**: Recursively scrubs Stripe keys (`sk_live_...`), passwords, salts, and webhook secrets before sending any JSON payload.
- **PII Scrubbing**: Replaces client email addresses in logs with masked identifiers (`[REDACTED_EMAIL@...]`).
- **Path Traversal Protection**: Uses `realpath()` to restrict file reading strictly within `WP_PLUGIN_DIR`, `get_theme_root()`, `WPMU_PLUGIN_DIR`, and `uploads/wc-logs/`. Strictly blocks access to `wp-config.php`, `.env`, `.git`, or `.htaccess`.
- **Rate Limiting**: Throttles queries to 300 requests/minute per client IP for authenticated requests via WordPress Transients.
- **Lightweight DB Logging**: Logs are stored in a dedicated table (`wp_agent_bridge_logs`) with automatic rotation (default: 500 rows) and cumulative summary metrics to prevent database bloat.


---

## 🌐 Internationalization (i18n)

- Default language: **English**.
- Fully compatible with **Loco Translate**.
- French translations are included in `woo-get-data-for-ai/languages/` (`woo-get-data-for-ai-fr_FR.po` and `.mo`).

---

## 🔄 Release & Auto-Update Protocol (GitHub)

For WordPress installations to automatically detect and apply updates via `plugin-update-checker`:

1. **Version Bump**: Increment the version in `woo-get-data-for-ai/woo-get-data-for-ai.php` (both the header `Version: X.Y.Z` and the constant `WOO_GET_DATA_AI_VERSION`).
2. **Commit & Tag**: Commit all changes and create a Git tag matching the version (e.g., `git tag v1.0.1 && git push origin v1.0.1`).
3. **GitHub Release**:
   - Go to [GitHub Releases](https://github.com/SOYOO974/woo-get-data-for-ai/releases) and create a **New Release** based on your tag.
   - Document the release notes and changelog.
   - Attach the zipped plugin directory (`woo-get-data-for-ai.zip`) to the release assets.
   - Publish the release.

---

## 📄 License
This project is licensed under the GPL-2.0+ License.
Developed by **SOYOO**.

