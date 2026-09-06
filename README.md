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
- **Dynamic AI Discovery & Playbooks (`/capabilities`)**: Self-describing schema and procedural diagnostic Playbooks allowing AI assistants to discover available modules, active permissions, query parameters, and step-by-step audit recipes.
- **Auto-Updating Skill Generator (`?format=skill`)**: When queried with `?format=skill`, the API dynamically generates a complete, ready-to-save `SKILL.md` markdown file for Antigravity, Cursor, and Claude agents.
- **2-Click AI Onboarding**: Tab featuring a streamlined Bootstrap Prompt that empowers AI agents to auto-generate and maintain their local skill without prompt bloat or stagnation.
- **Granular Permissions Matrix**: Toggle access to specific modules (System, Themes, Code, Elementor, WPCode, Logs, FlowMattic, Analytics) via checkboxes in the admin panel.
- **Deep WooCommerce Diagnostics**: Audits template overrides in child/parent themes, detects outdated templates, and inspects HPOS (High-Performance Order Storage) status.
- **Theme Settings Export**: Deep inspection and decoding of **Woodmart** (`xts-woodmart-options`), **Elessi** (`elessi_options` / Redux), and child theme `functions.php` / `style.css`.
- **Sandboxed Code Inspector**: Safely inspects file trees of active plugins and `mu-plugins`, and reads specific PHP/JS/CSS files with strict `realpath` validation.
- **High-Performance Log Streaming**: Memory-safe reverse file tailing (`fseek`) for `debug.log` and `uploads/wc-logs/*.log` preventing PHP memory exhaustion on heavy production sites.
- **Automatic Data Redaction**: Real-time regex engine that masks Stripe secret keys (`sk_live_*`), API tokens, passwords, database credentials, and customer email addresses before output.
- **FlowMattic Automations**: Bulk and targeted export of all workflows, triggers, actions, and execution task stats in native importable JSON format.
- **Independent Analytics Intelligence**: Complete visibility over site traffic, unique visitors, pageviews, acquisition channels, UTM campaigns, device breakdowns, and WooCommerce **conversion rates**, net sales, and AOV.
- **Custom Fields & ACF Meta**: Complete discovery of meta fields registered in code (`register_post_meta`), Advanced Custom Fields (ACF) field groups, recursive subfields (repeaters, flexible content), location rules, options pages, and single post metadata inspection.
- **WooCommerce Store & Catalog Data**: Read-only access to products, stock status, variations, e-commerce settings, and recent orders with **strict GDPR/PII anonymization** (masked names, redacted emails/phones/addresses) and gateway error diagnostics via order notes.
- **Native WooCommerce Sales & Stock Intelligence**: 100% native commercial reporting without external tracking plugins: gross/net sales, paid orders, AOV, refunds, % growth vs prior period, top products and coupons, and stock valuation & dormant inventory.
- **SMTP & Transactional Email Diagnostics**: Provider detection (**FluentSMTP**, **WP Mail SMTP**, **Post SMTP**, **Easy WP SMTP**), credentials sanitization, recent delivery failures, and PHP `mail()` unauthenticated spam risk alert.
- **Security Hardening & Protection Audit**: Audit of constants (`DISALLOW_FILE_EDIT`, `WP_DEBUG_DISPLAY`), XML-RPC exposure, SSL enforcement, DB prefix, detected security and caching plugins.
- **Database Health & Autoload Analysis**: Audit of SQL table sizes, top heavy tables, transient accumulations, and `wp_options` autoload footprint with performance alerts (> 800 KB threshold).
- **Crash Watch Fatal Error Dashboard**: Targeted reverse-tail extraction of recent critical PHP fatal errors and exceptions with component attribution for instant bug diagnostics.
- **WordPress Pages, Content & Unified SEO**: Complete inspection of WordPress pages hierarchy, raw and rendered Gutenberg block trees, detected shortcodes, templates, and **unified SEO metadata** normalized across **Yoast SEO**, **Rank Math**, **SEOPress**, and **All in One SEO** with site-wide audit capabilities across pages, blog posts, WooCommerce products, and categories.
- **Code Drift Fingerprinting & Instant ZIP Export**: Instant cryptographic checksums (`/code/checksums`) for local vs remote code drift detection, and on-the-fly clean ZIP archive downloads (`/code/zip`) with zero `.git` or log clutter.
- **9 Battle-Tested Procedural Playbooks**: Multi-step diagnostic sequences for SEO, technical health, failed orders, native sales/stock, SMTP/webhooks, store analytics, integrations, theme compatibility, and local vs prod code drift.
- **Automatic Updates via GitHub**: Fully integrated with `plugin-update-checker` (PUC v5.6).

---

## 📂 Repository Structure

```
├── PROJECT_CONTEXT.md          # Architecture memory and technical specification
├── ROADMAP.md                  # Commercial & technical roadmap (Agencies & Black Friday LTD)
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
| `GET /capabilities?format={json\|skill\|markdown}` | Dynamic discovery catalog: active modules, endpoints, procedural Playbooks, and ready-to-use Agent `SKILL.md` generator. |
| `GET /system` | Server limits (PHP, RAM, execution time), WP core info, active plugins with update status, HPOS state, and Action Scheduler queue. |
| `GET /system/database` | In-depth database diagnostic: table sizes, top 15 largest tables, autoload footprint analysis with 800KB alert threshold, transient counts, and object cache status. |
| `GET /system/mail` | SMTP & transactional email diagnostic: active provider (FluentSMTP, WP Mail SMTP, Post SMTP), credentials redaction, PHP `mail()` spam risk detection, and recent delivery failures. |
| `GET /system/security` | Security hardening audit: `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`, `WP_DEBUG_DISPLAY`, XML-RPC exposure, SSL enforcement, DB prefix, detected security and caching plugins. |
| `GET /theme/options` | Decoded options for **Woodmart** (`xts-woodmart-options`), **Elessi** (`elessi_options`), and theme mods. |
| `GET /theme/overrides` | Audit of WooCommerce template overrides with version comparison against core WooCommerce. |
| `GET /theme/child` | Code and metadata for child theme `functions.php` and `style.css`. |
| `GET /code/plugins?status={active\|inactive\|all}` | File trees for active or all plugins and `wp-content/mu-plugins/` (default: `active`). |
| `GET /code/file?path={relative_path}` | Sandboxed code viewer for specific PHP, JS, or CSS files. |
| `GET /code/checksums?path={path}&algo={md5\|sha256}` | Cryptographic file checksum map (MD5 / SHA256), modified dates, and byte sizes for instant local vs prod drift verification. |
| `GET /code/zip?path={path}&format={stream\|base64}` | Clean, on-the-fly ZIP archive export of plugins or child themes without `.git`, logs, or sensitive files (default: `stream`). |
| `GET /elementor/export-all?status={publish\|draft\|all}` | Bulk export of Elementor pages, templates, kit & forms with status counts and `is_published` flag. |
| `GET /elementor/list?status={publish\|draft\|all}` | Pages and templates built with Elementor with status filtering (`publish`, `draft`, `all`). |
| `GET /elementor/item/{id}` | Decoded JSON element tree (`_elementor_data`) and page settings. |
| `GET /elementor/forms` | Inventory of Elementor forms, fields, and submit actions (webhooks, emails). |
| `GET /elementor/kit` | Global colors, system fonts, and design tokens from the active Elementor Kit. |
| `GET /wpcode/snippets?status={active\|inactive\|all}` | Custom PHP, JS, and CSS snippets stored in WPCode with global `active_count` and `inactive_count` (recommended for diagnostics: `active`). |
| `GET /wpcode/snippet/{id}` | Source code and metadata of a specific snippet. |
| `GET /logs/sources` | Available log files (`debug.log`, `uploads/wc-logs/*.log`, custom `wp-content/` logs) with file sizes and dates. |
| `GET /logs/view?source={file}&lines=200` | Memory-safe tail extraction of the latest log lines. |
| `GET /logs/custom?file={filename}&lines=200` | Tail inspection of specific custom logs in `wp-content/` (e.g. `komela-order-status-sync.log`). |
| `GET /logs/errors-summary?limit=15` | Crash Watch: aggregated and deduplicated recent fatal PHP errors and exceptions from `debug.log` and `wc-logs` with component attribution. |
| `GET /crons` | WP-Cron registered jobs, next execution timestamps (GMT & local), recurrence intervals, overdue tasks, and hook arguments. |
| `GET /action-scheduler` | Action Scheduler queue (in-progress, failed, pending), hook, group, attempts, arguments, and error logs from `actionscheduler_logs`. |
| `GET /flowmattic/export-all?status={active\|inactive\|all}` | Bulk export of FlowMattic automation workflows with `active_count` and `inactive_count`. |
| `GET /flowmattic/workflows?status={active\|inactive\|all}` | List FlowMattic workflows (ID, name, status, triggers, steps, tasks executed). |
| `GET /flowmattic/workflow/{id}?format=export` | Download a workflow in FlowMattic's native importable JSON format. |
| `GET /analytics/overview` | 360° consolidated audit in 1 request (traffic KPIs, conversion rate, top pages, referrers, campaigns, devices). |
| `GET /analytics/summary?range={range}` | Traffic KPIs (visitors, views, bounce rate, duration) and WooCommerce conversion rate, sales, AOV, and % growth. |
| `GET /analytics/pages?sort=views&limit=25` | Content and product performance with pageviews, visitors, orders, and product conversion rates. |
| `GET /analytics/referrers` | Traffic sources (domains, search engines, social media) with attributed orders and conversion rates. |
| `GET /analytics/campaigns` | Marketing UTM tracking (`utm_source`, `utm_medium`, `utm_campaign`) with revenue attribution. |
| `GET /analytics/devices` | Device types (Mobile vs Desktop vs Tablet), browsers, and OS comparison with conversion rates. |
| `GET /analytics/geo` | Geographic distribution of visitors and orders by country and city. |
| `GET /analytics/conversions` | Recent conversion stream (orders, form submissions) with attribution (zero PII). |
| `GET /meta/fields?post_type={type}` | Unified catalog of custom meta fields defined in code (`register_post_meta`) and ACF (groups, recursive subfields, location rules, options pages), with optional DB discovery. |
| `GET /meta/acf?status={status}` | Deep inspection of ACF environment, field groups, recursive subfields, location rules, and registered options pages. |
| `GET /meta/post/{id}` | Inspect all metadata for a specific post/product/order (resolved ACF fields, code-registered meta, and full categorized raw postmeta). |
| `GET /woocommerce/summary` | High-level store health, product counts by status/stock/type, order counts by status, HPOS state, active payment gateways, and shipping zones. |
| `GET /woocommerce/products?status={publish\|draft\|all}` | Paginated WooCommerce product catalog with SKU, prices, stock, categories, tags, attributes, and variations. |
| `GET /woocommerce/product/{id}` | Detailed product inspection including variations breakdown, dimensions, images, unified SEO object, and sanitized postmeta custom fields. |
| `GET /woocommerce/orders?status={status}` | Recent orders with strict GDPR/PII anonymization (masked customer details, redacted emails/phones/addresses), item lines, totals, and gateways. |
| `GET /woocommerce/order/{id}` | Deep order diagnostics: item line metadata, shipping, fees, coupon lines, refunds, order notes (payment gateway responses), and sanitized metadata. |
| `GET /woocommerce/settings` | Store configuration: currency, tax settings, stock management, active payment gateways (secrets redacted), and shipping zones/methods. |
| `GET /woocommerce/analytics/sales?range={range}` | 100% native WooCommerce sales report: net sales, gross sales, orders count, AOV, refunds, daily trend, and growth percentage compared to previous period. |
| `GET /woocommerce/analytics/top-performers?limit=10` | Top products by net revenue & volume sold, and top coupons with discount totals. |
| `GET /woocommerce/analytics/stock` | Stock financial valuation, low stock alerts, and dormant stock (0 sales in last 90 days). |
| `GET /woocommerce/webhooks` | WooCommerce webhooks inventory, delivery URLs, topics, and failure counters (`failure_count >= 5`). |
| `GET /content/pages?status={status}` | Paginated WordPress pages list with hierarchy, slug, status, template PHP, editor type (Gutenberg/Classic/Elementor), special page flags, and quick SEO preview. |
| `GET /content/page/{id}` | Deep page inspection: raw/rendered content, Gutenberg blocks summary, detected shortcodes, word count, parent/child hierarchy, and unified normalized SEO metadata. |
| `GET /content/posts?status={status}` | Paginated blog posts list with categories, tags, author, editor type, and quick SEO preview. |
| `GET /content/post/{id}` | Deep post or custom post type inspection: raw/rendered content, blocks, taxonomies, sanitized postmeta, and full unified SEO object. |
| `GET /content/seo-audit` | Site-wide SEO audit report across pages, posts, WooCommerce products, and categories: missing meta descriptions, title issues, noindex warnings on published products/checkout, thin content, and category descriptions (`?include_posts=true\|false`, `?include_products=true\|false`, `?include_categories=true\|false`). |

---

## 🎯 Procedural AI Playbooks (9 Automated Investigation Recipes)

To avoid trial-and-error querying, the plugin includes pre-configured procedural investigation recipes that an AI can trigger based on user intent:

1. **360° SEO & Content Audit** (`seo_content_audit`): Runs `/content/seo-audit`, analyzes `/content/pages`, and inspects `/content/page/{id}` for flagged URLs.
2. **Technical Health & Background Tasks** (`tech_health_crons`): Correlates `/system`, `/system/database`, `/system/security`, `/action-scheduler`, `/crons`, and `/logs/errors-summary` (Crash Watch).
3. **Failed Orders & Checkout Troubleshooting** (`ecommerce_troubleshoot`): Queries `/woocommerce/orders`, inspects `/woocommerce/order/{id}`, gateway logs `/logs/view`, active checkout hooks `/wpcode/snippets`, SMTP delivery `/system/mail`, and webhook health `/woocommerce/webhooks`.
4. **Store Performance & UTM Marketing Funnel** (`store_analytics_roi`): Combines `/analytics/overview`, `/woocommerce/summary`, `/analytics/campaigns`, and `/analytics/devices`.
5. **Integration & Automations Mapping** (`integration_automation_map`): Maps `/elementor/forms`, `/flowmattic/workflows`, `/meta/fields`, and `/wpcode/snippets`.
6. **Theme & WooCommerce Compatibility** (`theme_wc_compatibility`): Checks template drift with `/theme/overrides`, child theme code with `/theme/child`, and options with `/theme/options`.
7. **Native E-Commerce Sales & Stock Valuation** (`store_sales_stock_audit`): Analyzes `/woocommerce/analytics/sales`, `/woocommerce/analytics/top-performers`, `/woocommerce/analytics/stock`, and `/woocommerce/summary`.
8. **Transactional Emails & Webhooks Diagnostics** (`email_webhook_diagnostics`): Investigates delivery failures with `/system/mail`, `/woocommerce/webhooks`, `/action-scheduler`, and `/logs/view`.
9. **Code Drift & Extension Synchronization** (`code_sync_drift_audit`): Fingerprints directory checksums with `/code/checksums` to compare against local workspace files, and downloads complete clean ZIP archives with `/code/zip` in 1 single call.

> 💡 **Instant Setup**: Run `curl -s -H 'Authorization: Bearer <TOKEN>' 'https://your-site.com/wp-json/agent-bridge/v1/capabilities?format=skill' > .agents/skills/wp-agent-bridge/SKILL.md` in your project to immediately equip your AI with all active routes and playbooks!

---

## 💻 Local CLI Client (`cli/`)

A zero-dependency Node.js client is included to dump and synchronize site data directly into your local workspace.
It segregates active and inactive elements into distinct local subdirectories (`snippets/active/` vs `snippets/inactive/`, `flowmattic/workflows/active/` vs `flowmattic/workflows/inactive/`, `elementor/pages/published/` vs `elementor/pages/draft/`) to ensure AI code assistants never grep through dead or archived code during diagnostics.

### Usage
```bash
# 1. Navigate to the cli directory
cd cli

# 2. Configure credentials
cp .env.example .env
# Edit .env with your SITE_URL and AGENT_BRIDGE_TOKEN

# 3. Run synchronization commands
node sync.js pull:all          # Synchronizes everything into ./synced-site-data
node sync.js pull:all --status=active # Pulls ONLY active elements (production live code)
node sync.js pull:capabilities # Dumps capabilities & schema to capabilities.json & capabilities.md
node sync.js pull:skill        # Dumps ready-to-use live agent SKILL.md
node sync.js pull:system       # Generates system-report.md
node sync.js pull:scheduler    # Dumps WP-Cron & Action Scheduler to ./scheduler/ (crons & queue)
node sync.js pull:theme        # Dumps Woodmart/Elessi options & WC overrides
node sync.js pull:code         # Dumps plugins & mu-plugins code tree
node sync.js pull:checksums --path=plugins/my-plugin # Generates integrity checksums fingerprint
node sync.js pull:elementor    # Dumps Elementor pages, forms, and kits (organized by published/draft)
node sync.js pull:snippets     # Dumps WPCode snippets (organized into snippets/active/ and snippets/inactive/)
node sync.js pull:flowmattic   # Dumps FlowMattic workflows (organized into active/ and inactive/)
node sync.js pull:analytics    # Dumps Independent Analytics to overview.json & summary.md
node sync.js pull:meta         # Dumps Custom Fields & ACF schemas to ./meta/ (fields.json, acf.json, meta-summary.md)
node sync.js pull:woocommerce  # Dumps WooCommerce store data (summary, settings, products, anonymized orders)
node sync.js pull:content      # Dumps WordPress pages, Gutenberg trees, and SEO audit to ./content/
node sync.js pull:logs         # Downloads tail of debug.log, wc-logs, and custom logs
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

