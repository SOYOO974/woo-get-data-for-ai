# WP Agent Bridge — Project Context & Architecture Memory

> **Last Updated**: 2026-09-06  
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
  - The access token is generated via `Security::generate_token()` as a 64-character hexadecimal string `[0-9a-f]` (RFC 6750 compliant, zero spaces, zero shell special characters).
  - Automatically heals / regenerates invalid or legacy tokens on-the-fly via `Security::get_active_token()`.
  - Stored securely in `wp_options` as `wp_agent_bridge_token`.
  - Administrators can revoke and regenerate the token with 1 click.
  - Optional override supported via `WP_AGENT_BRIDGE_TOKEN` in `wp-config.php`.
- **Automatic Updates & Release Protocol**:
  - Integrated with `plugin-update-checker` (PUC v5.6) configured for GitHub releases/branch tracking (`enableReleaseAssets()`).
  - **MANDATORY AUTO-RELEASE DIRECTIVE (SYSTEMATIC ON EVERY FEATURE/FIX)**:
    Whenever work on a new feature, improvement, or bugfix is completed, the agent/developer **MUST SYSTEMATICALLY AND AUTOMATICALLY PUBLISH A NEW GITHUB RELEASE**:
    1. **Version Bump**: Increment the version number in both the plugin header (`Version: X.Y.Z`) and the constant `WOO_GET_DATA_AI_VERSION` in `woo-get-data-for-ai/woo-get-data-for-ai.php`.
    2. **Changelog & Documentation**: Document all new features, bugfixes, and breaking changes in `PROJECT_CONTEXT.md` and `README.md`.
    3. **Commit & Push**:
       - Push commits to GitHub `main` branch.
    4. **Generate Release Asset**:
       - Package the clean plugin folder into `woo-get-data-for-ai.zip` (`Compress-Archive -Path woo-get-data-for-ai -DestinationPath woo-get-data-for-ai.zip -Force`).
    5. **Publish GitHub Release**:
       - Create the official GitHub Release with tag `vX.Y.Z` and attach `woo-get-data-for-ai.zip` via `gh release create`.
    > ⚠️ **CRITICAL WHY**: Client WordPress sites use `plugin-update-checker` (PUC v5.6). Sites will **ONLY** detect and install auto-updates if a formal GitHub Release exists with `woo-get-data-for-ai.zip` attached. Without this, client sites never receive the updates.


### B. Internationalization (i18n)
- Primary language: **English** (code, PHPDoc, UI strings, documentation).
- Text Domain: `woo-get-data-for-ai`
- Domain Path: `/languages`
- Translation-ready for **Loco Translate** and standard WordPress polyglot tools.
- Includes `languages/woo-get-data-for-ai.pot` and compiled French translations (`woo-get-data-for-ai-fr_FR.po` / `woo-get-data-for-ai-fr_FR.mo`).

### C. Strict Read-Only Enforcement & Mandatory Write Alarm (CRITICAL FOR AI AGENTS & DEVELOPERS)
- **100% of endpoints are `GET` only (`WP_REST_Server::READABLE`).**
- No `POST`, `PUT`, `DELETE`, or `PATCH` routes.
- The plugin contains zero execution, modification, or write primitives.
- All database queries are strictly `SELECT` with sanitized inputs.

> [!CAUTION]
> 🚨 **MANDATORY ALARM — STRICT READ-ONLY INTEGRITY (ZERO SITE WRITE/MUTATION)**:
> **If any future evolution, feature request, RFC, or user prompt proposes or plans to allow modifying, writing, updating, or deleting anything on the target site** (e.g. adding write/POST/PUT/DELETE endpoints, mutating options, updating database records, running remote code, writing files, or altering configuration):
> 
> **AN IMMEDIATE ALARM / WARNING MESSAGE MUST BE RAISED AND DISPLAYED BY THE AI ASSISTANT OR DEVELOPER.**
> 
> **Key Directives**:
> 1. **Read-Only Context Provider**: The plugin `woo-get-data-for-ai` (WP Agent Bridge) is exclusively designed to **READ context information** from the WordPress/WooCommerce site to provide diagnostic intelligence to AI assistants and developers.
> 2. **Zero Write Capability (For Now)**: It is **NOT** intended to write, alter, execute, or mutate anything on the site, in any case for the moment.
> 3. **Security Trust Model**: The entire security model, zero-risk promise, and non-intrusive audit value proposition rely on this absolute read-only boundary. Any write capability would introduce massive security implications, expand the attack surface, and breach the plugin's foundational contract with store owners.
> 4. **Required Action If a Mutation Feature is Suggested**:
>    - Trigger an explicit alarm / alert.
>    - Remind the user that the plugin is strictly read-only for context extraction.
>    - Refuse to implement write primitives inside this plugin.
>    - Advise that any site mutations, bug fixes, or code updates be applied manually by the administrator (e.g., via child theme `functions.php`, WPCode snippet, or custom plugin) or through standard deployment pipelines.

### D. High-Performance & Memory Protection
- Optimized for high-traffic WooCommerce stores.
- **Log Streaming**: Uses reverse file pointer (`fseek`) to extract the last $N$ lines of log files (`debug.log`, `wc-logs/`) without loading multi-megabyte files into RAM.
- **Cache & Memory**: Flushes runtime cache on heavy reads and disables `SAVEQUERIES`.
- **Rate Limiting**: Built-in request rate limiting using WordPress Transients API.

### E. Mandatory Checklist for Adding a New Data Source / Inspection Module (CRITICAL FOR AI AGENTS & DEVELOPERS)

Whenever adding capabilities to inspect a new data source (e.g. ACF fields, WooCommerce orders/coupons, MetaSlider, SEO plugins, automation tables, custom post types):

The following **7-step synchronization protocol is strictly mandatory** to maintain system integrity across admin settings, the AI onboarding engine, documentation, procedural playbooks, and the local CLI client:

1. **Dedicated Read-Only REST Controller (`includes/api/class-*-controller.php`)**:
   - Implement under namespace `WPAgentBridge\Api`.
   - Register endpoints strictly with `methods => \WP_REST_Server::READABLE` (`GET` only).
   - Hook into `rest_api_init` via `includes/class-plugin.php`.
   - Implement defensive checks (e.g. verify if target plugin is active or database table exists before querying).
   - Sanitize all outputs and apply secret/PII redaction via `Redaction::sanitize_output()`.

2. **Permissions Matrix Registration & Automatic Checkbox Generation (Tab 2)**:
   - **Register Module Definition**: Add the module key, localized `label`, localized `description`, and list of `endpoints` to `Permissions::get_module_definitions()` in `includes/class-permissions.php`.
     > 💡 *The Permissions tab (`includes/admin/views/tab-permissions.php`) automatically generates the UI checkbox and handles POST saving by looping over `Permissions::get_module_definitions()`.*
   - **Default Enabled**: Add `'<module_key>' => 1` to `$defaults` in `Permissions::get_permissions()`.
   - **Endpoint Guard**: In the REST controller's `permission_callback`, enforce authorization and check module status via:
     ```php
     Security::check_rest_permission($request) && Permissions::check_module_permission('<module_key>')
     ```

3. **Procedural Playbooks & AI Skill Synchronization (`includes/class-playbooks.php`) (MANDATORY)**:
   - Update `includes/class-playbooks.php`:
     - Either integrate the new endpoint into an existing multi-step diagnostic Playbook (e.g. SEO, Tech Health, E-commerce, Analytics, Integrations).
     - Or register a new dedicated Playbook with `id`, `title`, `description`, `required_modules`, `optional_modules`, `intent_triggers`, and ordered `workflow` steps.
   - Verify that `GET /capabilities` (JSON mode) returns the new playbook and that `GET /capabilities?format=skill` renders the updated markdown skill correctly.
   - Verify that the playbook correctly adapts when optional or required permissions are toggled off.

4. **AI Mega-Prompt Generator Integration (Tab 3)**:
   - In `includes/admin/views/tab-ai-prompt.php`, ensure the strategic instructions and quickstart command examples include the new capability or playbook reference.

5. **In-Plugin Documentation & Scope Table (Tab 5)**:
   - Update `includes/admin/views/tab-docs.php` in **Section 4: Inspectable Technical Data** (`.docs-scope-table-wrap`).
   - Add a row specifying the technical domain, endpoint paths, and a summary of data returned to the AI.

6. **Local CLI Synchronization Client (`cli/sync.js`)**:
   - Add a dedicated command `pull:<source>` in `cli/sync.js` to dump the data locally into `./synced-site-data/<source>/`.
   - Integrate the new command into `pull:all`.
   - Update the usage help text in `cli/sync.js` and `README.md`.

7. **Repository Documentation & Release Protocol**:
   - Add the new endpoints to Section 4 ("REST API Endpoint Catalog") in `PROJECT_CONTEXT.md` and the table in `README.md`.
   - Follow the **Automatic Updates & Release Protocol (Section 2.A)**: increment version in plugin header & constant, document changelog in `PROJECT_CONTEXT.md` and `README.md`, commit, tag, and publish a formal GitHub Release with `woo-get-data-for-ai.zip` attached.

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
- `[x] System & Server Environment` (`/system`, `/capabilities`, `/ping`)
- `[x] WooCommerce Diagnosis & Overrides` (`/theme/overrides`, HPOS state)
- `[x] Theme Settings (Woodmart & Elessi)` (`/theme/options`, `/theme/child`)
- `[x] Code & Plugin Inspector` (`/code/plugins`, `/code/file`)
- `[x] Elementor Architecture` (`/elementor/list`, `/elementor/forms`, `/elementor/kit`)
- `[x] WPCode Snippets` (`/wpcode/snippets`)
- `[x] Error & WooCommerce Logs` (`/logs/sources`, `/logs/view`)
- `[x] FlowMattic Workflows` (`/flowmattic/export-all`, `/flowmattic/workflows`, `/flowmattic/workflow/{id}`)
- `[x] Independent Analytics (Visits & Conversion Rates)` (`/analytics/overview`, `/analytics/summary`, `/analytics/pages`, `/analytics/referrers`, `/analytics/campaigns`, `/analytics/devices`, `/analytics/geo`, `/analytics/conversions`)
- `[x] Custom Fields & Meta (ACF & Code)` (`/meta/fields`, `/meta/acf`, `/meta/post/{id}`)
- `[x] WooCommerce Store Data (Products, Orders, Settings)` (`/woocommerce/summary`, `/woocommerce/products`, `/woocommerce/product/{id}`, `/woocommerce/orders`, `/woocommerce/order/{id}`, `/woocommerce/settings`)
- `[x] Pages, Content & SEO` (`/content/pages`, `/content/page/{id}`, `/content/posts`, `/content/post/{id}`, `/content/seo-audit`)
*(When a module is toggled off, any API request to its endpoints returns HTTP 403 Forbidden).*

### Tab 3: AI Onboarding & Dynamic Bootstrap Prompt
- One-click "Copy Mega-Prompt for AI" widget.
- Dynamically generates a lightweight, future-proof Bootstrap Prompt for Antigravity/Claude/Cursor containing:
  - Active site name and REST Base URL.
  - Active Bearer Token.
  - **Dynamic Discovery Protocol**: Instructs the AI to run `GET /capabilities` on startup to discover all active modules, endpoints, and supported query parameters.
  - **Auto-updating Skill Guidance**: AI is instructed to create/update `.agents/skills/wp-agent-bridge/SKILL.md` from the live `/capabilities` output and re-check it regularly to detect plugin updates without manual prompt re-copying.
  - **Phase 2 Live Freshness Check**: Query live API before designing code or modifying custom snippets/forms.
  - **WPCode direct admin edit links rule**.
  - **Strict read-only & write prohibition alarm**: AI must display a clear warning/alarm and refuse any requested evolution that would allow writing to or modifying the target site.

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

### Tab 5: Documentation & Guide
- Complete on-site user manual and architectural reference.
- **Mission & Overview**: Explains plugin role and capabilities (WooCommerce overrides, themes, code sandbox, Elementor, WPCode, memory-safe logs).
- **Security & Architecture**: 100% read-only guarantee, Bearer token rotation, anti-brute force with IP lockout, real-time secret/PII redaction, `fseek` memory-safe streaming, and granular module permissions.
- **4-Step Quickstart**: Visual step-by-step onboarding guide highlighting the AI Mega-Prompt copy action and direct shortcuts.
- **Scope Reference Table**: Clear mapping of technical domains, REST endpoints, and returned data.
- **Open Source & GitHub Evolution**: Direct repository links (`https://github.com/SOYOO974/woo-get-data-for-ai`), guidelines for proposing new endpoints via Issues/PRs, automatic update mechanism via PUC v5.6, and support contact (`julien@soyoo.re`).

---

## 4. REST API Endpoint Catalog (`agent-bridge/v1/`)

| Endpoint | Method | Purpose |
| :--- | :--- | :--- |
| `GET /ping` | GET | Connectivity check, server timestamp, site name |
| `GET /capabilities` | GET | Dynamic schema, active permissions, procedural diagnostic Playbooks, and ready-to-use Agent SKILL.md generator (`?format=skill\|markdown`) |
| `GET /system` | GET | WP/WC/PHP/MySQL versions, active plugins & updates, HPOS status, Action Scheduler queue |
| `GET /system/database` | GET | In-depth database diagnostic: table sizes, top 15 largest tables, autoload footprint analysis with 800KB alert threshold, transient counts, and object cache status |
| `GET /system/mail` | GET | SMTP & transactional email diagnostic: active provider (FluentSMTP, WP Mail SMTP, Post SMTP), credentials redaction, PHP `mail()` spam risk detection, and recent delivery failures |
| `GET /system/security` | GET | Security hardening audit: `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`, `WP_DEBUG_DISPLAY`, XML-RPC exposure, SSL enforcement, DB prefix, detected security and caching plugins |
| `GET /theme/options` | GET | Decoded options for **Woodmart** (`xts-woodmart-options`), **Elessi** (`elessi_options` / Redux), and Customizer theme mods (sensitive keys redacted) |
| `GET /theme/overrides` | GET | WooCommerce template overrides in the active theme with version comparison to core WC |
| `GET /theme/child` | GET | Code and header info of the child theme's `functions.php` and `style.css` |
| `GET /code/plugins` | GET | File trees of active or all plugins and `wp-content/mu-plugins/` (`?status=active|inactive|all`, default: `active`) |
| `GET /code/file` | GET | Source code of a specific PHP/JS/CSS file (strictly sandboxed via `realpath()`) |
| `GET /elementor/export-all` | GET | Bulk export of all Elementor pages, templates, kit & forms in 1 optimized request (`?status=all|publish|draft|active|inactive`, default: `all`) |
| `GET /elementor/list` | GET | Elementor pages, posts, and templates (`elementor_library`) (`?status=all|publish|draft|active|inactive`, default: `all`) |
| `GET /elementor/item/{id}` | GET | Full decoded `_elementor_data` JSON tree and page settings |
| `GET /elementor/forms` | GET | Inventory of all Elementor forms (field definitions, actions, webhook URLs) |
| `GET /elementor/kit` | GET | Global colors, system fonts, and design tokens from the active Elementor Kit |
| `GET /wpcode/snippets` | GET | Listing of all WPCode snippets (`?status=all|active|inactive`, default: `all`, recommended: `active`) with global `active_count` and `inactive_count` |
| `GET /wpcode/snippet/{id}` | GET | Full source code and configuration of a targeted snippet |
| `GET /logs/sources` | GET | Available log files (`debug.log`, `uploads/wc-logs/*.log`, custom logs) with sizes & dates |
| `GET /logs/view` | GET | Memory-safe tail extraction of the last $N$ lines with optional error filtering |
| `GET /logs/custom` | GET | Memory-safe tail inspection of specific log files in `wp-content/` with strict path sandboxing (`?file=nom-du-log`) |
| `GET /logs/errors-summary` | GET | Crash Watch: aggregated and deduplicated recent fatal PHP errors and exceptions from `debug.log` and `wc-logs` with component attribution (`?limit=15`) |
| `GET /crons` | GET | WP-Cron registered jobs, next execution timestamps (GMT & local), recurrence intervals, overdue tasks, and hook arguments |
| `GET /action-scheduler` | GET | Action Scheduler queue (in-progress, failed, pending), hook, group, attempts, arguments, and error logs from `actionscheduler_logs` |
| `GET /flowmattic/export-all` | GET | Bulk export of all FlowMattic workflows in 1 optimized request (`?status=all|active|inactive`, default: `all`) |
| `GET /flowmattic/workflows` | GET | List FlowMattic workflows with execution stats (`?status=all|active|inactive`, default: `all`) |
| `GET /flowmattic/workflow/{id}` | GET | FlowMattic workflow detail or native importable JSON (`?format=export`) |
| `GET /analytics/overview` | GET | Consolidated 360° traffic & conversion audit in 1 call (summary, top pages, referrers, campaigns, devices) |
| `GET /analytics/summary` | GET | Traffic KPIs (visitors, views, bounce rate, duration) and WooCommerce conversion rate, net sales, AOV, % growth |
| `GET /analytics/pages` | GET | Performance & conversion rate per page / product (`views`, `visitors`, `orders`, `net_sales`, `conversion_rate`) |
| `GET /analytics/referrers` | GET | Traffic acquisition sources & referring domains with associated orders, sales, and conversion rates |
| `GET /analytics/campaigns` | GET | Marketing UTM campaigns ROI tracking (`utm_source`, `utm_medium`, `utm_campaign`, `orders`, `net_sales`) |
| `GET /analytics/devices` | GET | Breakdown and conversion comparison across device types (Desktop vs Mobile vs Tablet), browsers, and OS |
| `GET /analytics/geo` | GET | Geographic distribution of visitors and orders by country and city |
| `GET /analytics/conversions` | GET | Recent order and conversion stream with attribution (landing page, country, device, browser, amount) |
| `GET /meta/fields` | GET | Unified catalog of custom meta fields defined in code (`register_post_meta`) and ACF (`acf_get_field_groups`), with optional database discovery (`?source=all\|code\|acf\|db`, `?post_type=`, `?object_type=`, `?search=`, `?include_db=true`) |
| `GET /meta/acf` | GET | Deep ACF inspection: field groups, recursive subfield hierarchy (`repeater`, `flexible_content`, `group`), location rules, and registered Options Pages |
| `GET /meta/post/{id}` | GET | Inspect all metadata for a specific post/product/order (resolved ACF fields, code-registered meta, and full categorized raw postmeta) |
| `GET /woocommerce/summary` | GET | High-level store health, product counts by status/stock/type, order counts by status, HPOS state, active payment gateways, and shipping zones |
| `GET /woocommerce/products` | GET | Paginated WooCommerce product catalog with SKU, prices, stock, categories, tags, attributes, and variations (`?status=publish\|draft\|all`, `?type=`, `?stock_status=`, `?category=`, `?search=`, `?per_page=20`, `?page=1`) |
| `GET /woocommerce/product/{id}` | GET | Detailed product inspection including variations breakdown, dimensions, images, unified SEO object, and sanitized postmeta custom fields |
| `GET /woocommerce/orders` | GET | Recent orders with strict GDPR/PII anonymization (masked customer details, redacted emails/phones/addresses), item lines, totals, and gateways (`?status=processing\|completed\|failed\|all`, `?search=`, `?customer_id=`, `?per_page=10`) |
| `GET /woocommerce/order/{id}` | GET | Deep order diagnostics: item line metadata, shipping, fees, coupon lines, refunds, order notes (payment gateway responses), and sanitized metadata |
| `GET /woocommerce/settings` | GET | Store configuration: currency, tax settings, stock management, active payment gateways (secrets redacted), and shipping zones/methods |
| `GET /woocommerce/analytics/sales` | GET | 100% native WooCommerce sales report: net sales, gross sales, orders count, AOV, refunds, daily trend, and growth percentage compared to previous period (`?range=last_30_days`, `?start_date=`, `?end_date=`) |
| `GET /woocommerce/analytics/top-performers` | GET | Top products by net revenue & volume sold, and top coupons with discount totals (`?limit=10`, `?range=last_30_days`) |
| `GET /woocommerce/analytics/stock` | GET | Stock financial valuation, low stock alerts, and dormant stock (0 sales in last 90 days) (`?low_stock_threshold=`) |
| `GET /woocommerce/webhooks` | GET | WooCommerce webhooks inventory, delivery URLs, topics, and failure counters (`failure_count >= 5`) |
| `GET /content/pages` | GET | Paginated WordPress pages list with hierarchy, slug, status, template PHP, editor type (Gutenberg/Classic/Elementor), special page flags, and quick SEO preview (`?status=publish\|draft\|all`, `?parent=`, `?search=`, `?per_page=20`, `?page=1`) |
| `GET /content/page/{id}` | GET | Deep page inspection: raw/rendered content, Gutenberg blocks summary, detected shortcodes, word count, parent/child hierarchy, and unified normalized SEO metadata |
| `GET /content/posts` | GET | Paginated blog posts list with categories, tags, author, editor type, and quick SEO preview (`?status=publish\|draft\|all`, `?category=`, `?tag=`, `?search=`, `?per_page=20`) |
| `GET /content/post/{id}` | GET | Deep post or custom post type inspection: raw/rendered content, blocks, taxonomies, sanitized postmeta, and full unified SEO object |
| `GET /content/seo-audit` | GET | Site-wide SEO audit report across pages, posts, WooCommerce products, and categories: missing meta descriptions, title issues, noindex warnings on published products/checkout, thin content, and category descriptions (`?include_posts=true\|false`, `?include_products=true\|false`, `?include_categories=true\|false`, `?limit=100`, `?limit_products=50`) |

---

### 4.B Procedural AI Playbooks & Self-Updating Skills System (`includes/class-playbooks.php`)

To prevent AI prompt stagnation and trial-and-error querying across 25+ endpoints, the plugin features an intelligent **Playbooks Engine**:
- **Zero-Prompt Stagnation**: Instead of memorizing static endpoint lists, AI agents query `GET /capabilities?format=skill` to instantly generate an up-to-date `.agents/skills/wp-agent-bridge/SKILL.md` workspace skill.
- **Permission-Adaptive Workflows**: When an administrator disables a module in the Permissions matrix, dependent Playbooks and individual workflow steps are automatically excluded from the catalog so the AI never triggers `403 Forbidden` errors.
- **Built-in Procedural Playbooks (8 Battle-Tested Investigation Sequences)**:
  1. `seo_content_audit`: 360° SEO, meta tags, critical noindex detection on pages/products, OpenGraph coverage, and Gutenberg content hierarchy (`/content/seo-audit`, `/content/pages`, `/content/page/{id}`).
  2. `tech_health_crons`: Technical health, PHP/MySQL versions, memory limits, database autoload bloat, security hardening audit, stalled Action Scheduler queues, overdue WP-Crons, and Crash Watch fatal error dashboard (`/system`, `/system/database`, `/system/security`, `/action-scheduler`, `/crons`, `/logs/errors-summary`).
  3. `ecommerce_troubleshoot`: Order failure diagnostics, payment gateway error notes, coupon/fee inspection, gateway logs, active checkout hooks, SMTP mail delivery check, and WooCommerce webhook health (`/woocommerce/orders`, `/woocommerce/order/{id}`, `/logs/view`, `/wpcode/snippets`, `/system/mail`, `/woocommerce/webhooks`).
  4. `store_analytics_roi`: Store performance, net sales, conversion rates, traffic acquisition channels, UTM marketing campaigns, and device comparison (`/analytics/overview`, `/woocommerce/summary`, `/analytics/campaigns`, `/analytics/devices`).
  5. `integration_automation_map`: Full integration mapping: Elementor forms with webhooks, active FlowMattic automation recipes, custom ACF/code meta fields, and active WPCode snippets (`/elementor/forms`, `/flowmattic/workflows`, `/meta/fields`, `/wpcode/snippets`).
  6. `theme_wc_compatibility`: Child theme code, Woodmart/Elessi theme options, and WooCommerce template version drift detection (`/theme/overrides`, `/theme/child`, `/theme/options`).
  7. `store_sales_stock_audit`: Native WooCommerce commercial intelligence: gross/net sales, paid orders, AOV, refunds, % growth vs prior period, top products by revenue/qty, top coupons, and stock valuation & dormant inventory (`/woocommerce/analytics/sales`, `/woocommerce/analytics/top-performers`, `/woocommerce/analytics/stock`, `/woocommerce/summary`).
  8. `email_webhook_diagnostics`: Transactional email delivery and webhook integration diagnostics: SMTP provider detection (FluentSMTP, WP Mail SMTP, Post SMTP), credentials redaction, PHP `mail()` spam risk, and failing WooCommerce webhooks (`/system/mail`, `/woocommerce/webhooks`, `/action-scheduler`, `/logs/view`).

---

## 5. Security, Redaction & Anti-Brute-Force Engine
- **Anti-Brute-Force & IP Lockout**:
  - Automatically tracks consecutive failed authentication attempts per client IP.
  - After exceeding threshold (configurable, default: 5 attempts), client IP is temporarily locked out for XX minutes (configurable, default: 30 minutes).
  - Returns HTTP 429 Too Many Requests with remaining minutes countdown.
  - Active lockouts are monitored in WordPress Admin (Settings > Agent Bridge > General & Status) with a 1-click manual "Unlock IP" action.
  - Counter resets automatically on successful authentication.
- **Rate Limiting**:
  - Authenticated requests with valid Bearer token are limited to 300 requests/minute per client IP (using WordPress Transients) to smoothly accommodate bulk export and CLI sync scripts without false positive 429 errors.
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
- Commands: `pull:all`, `pull:capabilities`, `pull:system`, `pull:scheduler`, `pull:theme`, `pull:elementor`, `pull:snippets`, `pull:flowmattic`, `pull:analytics`, `pull:meta`, `pull:woocommerce`, `pull:logs`.
- Optional status filtering: `--status=active|inactive|all`.
- Organized local filesystem layout preventing AI false positives during workspace grep:
  - Snippets segregated into `snippets/active/` and `snippets/inactive/`.
  - FlowMattic workflows segregated into `flowmattic/workflows/active/` and `flowmattic/workflows/inactive/`.
  - Elementor definitions segregated into `elementor/pages/published/`, `elementor/pages/draft/`, `elementor/templates/published/`, `elementor/templates/draft/`.
- Optimized for v1.0.4+: `pull:elementor` automatically uses the bulk `/elementor/export-all` endpoint to pull all pages, templates, kit, and forms in 1 HTTP call (with fallback).
- Optimized for v1.0.5: `pull:flowmattic` automatically uses the bulk `/flowmattic/export-all` endpoint to export all automation workflows locally into `./synced-site-data/flowmattic/workflows/` in native FlowMattic JSON format, plus a Markdown summary table.
- Optimized for v1.1.0: `pull:analytics` pulls `/analytics/overview?range=last_30_days` and generates an executive Markdown summary (`./synced-site-data/analytics/summary.md`) with KPIs, conversion rates, and top performers.
- Optimized for v1.2.0: `pull:capabilities` pulls `/capabilities` to export the dynamic API catalog (`./synced-site-data/capabilities.json`) and a Markdown summary table (`./synced-site-data/capabilities.md`).
- Optimized for v1.3.0: `pull:scheduler` pulls WP-Cron schedules and Action Scheduler queue diagnostics.
- Optimized for v1.5.0: `pull:meta` dumps custom meta fields and ACF schemas into `./synced-site-data/meta/`.
- Optimized for v1.6.0: `pull:woocommerce` dumps WooCommerce store summary, e-commerce settings, products catalog, and anonymized recent orders into `./synced-site-data/woocommerce/`.
- Optimized for v1.7.0: `pull:content` dumps WordPress pages, posts, and executive SEO audit report into `./synced-site-data/content/`.
- Optimized for v1.9.0: `pull:woocommerce` dumps native sales analytics, top performers, stock valuation, and webhooks inventory; `pull:system` dumps SMTP mail diagnostics and security hardening audit.
- Generates a cleanly structured local export under `./synced-site-data/`.

---

## 7. Version Changelog

### v1.9.0 (2026-09-06)
- **Intelligence E-Commerce 100% Native (`Woocommerce_Controller`)** :
  - `GET /woocommerce/analytics/sales` : Rapport commercial autonome sans plugin tiers (CA brut, CA net, volume commandes, panier moyen AOV, remboursements, ventilation quotidienne et pourcentages de croissance vs période N-1 équivalente). Supporte `wc_order_stats`, HPOS `wc_orders` et tables d'agrégation historiques.
  - `GET /woocommerce/analytics/top-performers` : Classement des 10 meilleurs produits par chiffre d'affaires et volume vendu, et analyse des coupons promotionnels les plus utilisés (`wc_order_product_lookup` / `wc_order_coupon_lookup`).
  - `GET /woocommerce/analytics/stock` : Audit financier et logistique du stock : valorisation totale marchande du catalogue, alertes de stock faible sous le seuil critique, et détection du stock dormant (produits avec unités physiques mais 0 vente sur les 90 derniers jours).
  - `GET /woocommerce/webhooks` : Cartographie complète des webhooks WooCommerce avec URL de destination caviardée, topic déclencheur, statut et compteurs d'échecs de distribution (`failure_count >= 5`).
- **Diagnostic SMTP & Transactionnel (`System_Controller`)** :
  - `GET /system/mail` : Détection automatique du transporteur SMTP actif (**FluentSMTP**, **WP Mail SMTP**, **Post SMTP**, **Easy WP SMTP**), masquage strict des identifiants/mots de passe, extraction des 10 derniers échecs d'envoi et alerte critique si le site utilise PHP `mail()` non authentifié à fort risque de spam.
- **Audit de Sécurité & Durcissement Système (`System_Controller`)** :
  - `GET /system/security` : Analyse des constantes WordPress (`DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`, `FORCE_SSL_ADMIN`, `WP_DEBUG_DISPLAY`), accessibilité `xmlrpc.php`, exposition du tag générateur WordPress, préfixe de base de données, détection des plugins de sécurité actifs (Wordfence, Solid Security, Sucuri, SecuPress, MalCare) et des moteurs de cache (WP Rocket, LiteSpeed, W3TC, Redis Object Cache).
- **Enrichissement des Playbooks Procéduraux (`Playbooks`)** :
  - Enrichissement du Playbook `tech_health_crons` avec la santé base de données (`/system/database`), le durcissement sécurité (`/system/security`) et Crash Watch (`/logs/errors-summary`).
  - Enrichissement du Playbook `ecommerce_troubleshoot` avec le diagnostic SMTP (`/system/mail`) et l'état des webhooks (`/woocommerce/webhooks`).
  - **Nouveau Playbook 7** : `store_sales_stock_audit` (Analyse commerciale, top produits et valorisation du stock).
  - **Nouveau Playbook 8** : `email_webhook_diagnostics` (Résolution des échecs de livraison email et synchronisations ERP/CRM).
- **Permissions & Capabilities** :
  - Déclaration de tous les nouveaux endpoints dans `get_module_definitions()` et `get_capabilities_catalog()`.
  - Mise à jour de la documentation d'administration (Onglet 5) et du prompt système d'onboarding (Onglet 3).
- **Client CLI Local (`cli/sync.js`)** :
  - `pullWooCommerce()` enrichi : téléchargement de `analytics-sales.json`, `top-performers.json`, `stock-analytics.json`, `webhooks.json` et génération de `sales-report.md` et `stock-health.md`.
  - `pullSystem()` enrichi : téléchargement de `mail.json` et `security.json` avec intégration dans `system-report.md`.

### v1.8.0 (2026-09-06)
- **Moteur de Playbooks Procéduraux Dynamiques (`Playbooks`)** :
  - Intégration de 6 Playbooks d'investigation initiaux (`seo_content_audit`, `tech_health_crons`, `ecommerce_troubleshoot`, `store_analytics_cro`, `integrations_automations_audit`, `theme_builder_investigation`) avec signaux clés et déclencheurs d'intention.
  - Exposition de `GET /capabilities?format=skill` : Générateur automatique de compétence (`SKILL.md`) prête à l'emploi pour agents IA (Antigravity, Cursor, Claude).
  - Restructuration du catalogue `/capabilities` avec section `playbooks`.

### v1.7.0 (2026-09-06)
- **Module Pages, Contenu & SEO Unifié (`Content_Controller`)** :
  - **Nouveau Contrôleur REST (`class-content-controller.php`)** en 100% lecture seule (`GET`).
  - `GET /content/pages` : Catalogue des pages WordPress avec hiérarchie (parent/enfant), slug, statut, template PHP, type d'éditeur (Gutenberg, Elementor, Classic), rôles spéciaux (accueil, blog, privacy, boutique, panier, checkout) et aperçu SEO rapide.
  - `GET /content/page/{id}` : Fiche détaillée de page avec contenu brut et rendu HTML (`the_content`), arborescence des blocs Gutenberg, shortcodes détectés, word count, hiérarchie parent/enfants et métadonnées SEO complètes.
  - `GET /content/posts` : Liste paginée des articles de blog avec catégories, étiquettes, auteur, word count et aperçu SEO.
  - `GET /content/post/{id}` : Diagnostic approfondi d'un article ou CPT avec contenu complet, taxonomies et objet SEO unifié.
  - `GET /content/seo-audit` : Audit SEO exhaustif du site avec détection automatique du plugin actif (**Rank Math**, **Yoast SEO**, **SEOPress**, **All in One SEO** ou moteur natif WP), visibilité publique (`blog_public`), couverture des méta-descriptions, analyse de longueur des balises title (< 30 ou > 65 caractères), détection des balises `noindex` critiques, couverture des images OpenGraph et détection du thin content (< 150 mots).
  - **Extension E-commerce de l'Audit SEO** : Support de `include_products=true` (audit des fiches produits avec alerte critique immédiate si un produit publié est en noindex) et `include_categories=true` (détection des catégories WooCommerce `product_cat` sans texte descriptif ou orphelines de balises SEO).
- **Enrichissement SEO WooCommerce (`Woocommerce_Controller`)** :
  - `GET /woocommerce/product/{id}` : Intégration automatique du bloc `seo` unifié (titre personnalisé, méta-description, noindex, image OpenGraph, type de schéma) pour chaque produit inspecté.
- **Audit Santé Base de Données & Autoload (`System_Controller`)** :
  - `GET /system/database` : Diagnostic en temps réel de l'infrastructure SQL :
    - Volume total de la base (données + index) et classement des 15 plus grosses tables avec nombre de lignes et moteur de stockage.
    - **Autoload Footprint Analysis** : Calcul du poids total des données chargées automatiquement dans `wp_options` (`autoload != 'no'`), seuil d'alerte configuré à 800 Ko (cause n°1 de dégradation du TTFB sur WordPress) et top 10 des options les plus lourdes.
    - Comptage des transients expirés orphelins non nettoyés.
    - Détection de la présence d'un cache objet externe (`wp_using_ext_object_cache()`, Redis / Memcached).
- **Crash Watch — Dashboard des Erreurs Fatales PHP (`Logs_Controller`)** :
  - `GET /logs/errors-summary` : Détection ciblée et agrégation sans surcharge mémoire (via reverse tailing `fseek`) des 15 dernières erreurs critiques (`PHP Fatal error`, `Uncaught Exception`, `Parse error`, `Allowed memory size`, `Maximum execution time`) depuis `debug.log` et les logs récents WooCommerce (`fatal-errors-*.log`).
  - Dédoublonnage intelligent avec horodatage de première et dernière occurrence, nombre de répétitions, fichier source et attribution automatique au composant incriminé (plugin, thème, coeur WP).
- **Permissions & Capabilities** :
  - Enregistrement du module `'content'` dans la matrice des permissions (Onglet 2) activé par défaut (`1`).
  - Auto-documentation complète de `/content/*`, `/system/database` et `/logs/errors-summary` dans le catalogue machine-readable `/capabilities`.
- **Client CLI Local (`cli/sync.js`)** :
  - Commande `pull:content` (alias `pull:pages`, `pull:seo`) intégrant le téléchargement des pages, des articles et la génération du rapport exécutif Markdown `seo-audit.md`.
  - Commande `pull:system` enrichie avec l'export de `database.json` et la section BDD/Autoload dans `system-report.md`.
  - Commande `pull:logs` implémentée avec génération de `errors-summary.md` (Crash Watch), `sources.json` et extraction du tail `debug.log`.
  - Intégration dans la commande globale `pull:all`.

### v1.6.0 (2026-09-06)
- **Module d'Inspection Technique WooCommerce (`Woocommerce_Controller`)** :
  - **Nouveau Contrôleur REST (`class-woocommerce-controller.php`)** en 100% lecture seule (`GET`).
  - `GET /woocommerce/summary` : Météo globale du store, versions, devise, état HPOS (`authoritative_source`), compteurs produits (statuts, types, ruptures de stock), compteurs commandes, passerelles de paiement actives et zones de livraison.
  - `GET /woocommerce/products` : Catalogue de produits paginé et filtrable (`status`, `type`, `stock_status`, `category`, `search`), avec SKU, prix, stock, attributs et variations.
  - `GET /woocommerce/product/{id}` : Fiche détaillée d'un produit avec variations, dimensions, images et ensemble des métadonnées `postmeta` caviardées (champs ACF, identifiants ERP, règles spécifiques).
  - `GET /woocommerce/orders` : Flux des commandes récentes paginées et filtrables avec **anonymisation stricte RGPD/PII** (noms masqués en `J*** D***`, emails caviardés `[REDACTED_EMAIL@...]`, téléphones et adresses physiques masqués).
  - `GET /woocommerce/order/{id}` : Diagnostic approfondi d'une commande incluant articles avec métadonnées d'éléments, frais, expédition, codes promos, remboursements, et **historique chronologique des notes de commande (`order notes`)** indispensable pour auditer les retours d'erreurs des passerelles de paiement (Stripe, Alma, etc.).
  - `GET /woocommerce/settings` : Paramètres généraux e-commerce, règles de taxes, gestion des stocks, passerelles de paiement installées/activées (avec clés de secrets caviardées via `Redaction`), et zones/méthodes de livraison.
  - **Permissions & Capabilities** : Module `'woocommerce'` intégré à la matrice des permissions (Onglet 2) et auto-documenté dynamiquement dans `/capabilities`.
  - **AI Mega-Prompt** : Mise à jour de la Phase 2 (Live Freshness Check) et commandes rapides curl pour WooCommerce.
  - **Client CLI** : Commande `pull:woocommerce` (alias `pull:wc`) intégrée dans `pull:all` avec génération automatique de `summary.md`.

### v1.5.0 (2026-09-06)
- **Module Découverte Custom Fields & ACF (`Meta_Controller`)** :
  - Ajout des endpoints `/meta/fields`, `/meta/acf`, et `/meta/post/{id}` pour inspecter les champs personnalisés enregistrés dans le code et les groupes de champs ACF.
  - Commande CLI `pull:meta` ajoutée.

### v1.4.0 (2026-09-05)
- **Standardisation du Filtrage Actif / Inactif & Prévention des Faux-Positifs IA** :
  - **Contrôleur WPCode (`Wpcode_Controller`)** :
    - Correction du bug `status=inactive` qui renvoyait l'ensemble des snippets sur le CPT `wpcode` et la table SQL `{$wpdb->prefix}snippets`.
    - Suppression du plafond de 200 snippets (`posts_per_page => -1`) garantissant l'exhaustivité même sur les sites à fort volume (200+ snippets sur Conforama.re).
    - Compteurs consolidés à la racine du JSON : `total`, `active_count`, `inactive_count`, `filter`, `count`.
    - Exposition explicite sur chaque snippet de `"status": "active"|"inactive"` et du booléen `"is_active": true|false`.
    - Ajout du flag `is_active` sur `GET /wpcode/snippet/{id}`.
  - **Contrôleur FlowMattic (`Flowmattic_Controller`)** :
    - Standardisation de la racine de réponse sur `GET /flowmattic/workflows` et `GET /flowmattic/export-all` avec `total` (global base), `active_count`, `inactive_count`, `matched_count`, et `filter`.
    - Maintien du flag booléen `is_active` sur chaque workflow quel que soit le filtre appliqué.
  - **Contrôleur Elementor (`Elementor_Controller`)** :
    - Support du filtrage par statut `status=all|publish|draft|active|inactive` sur `GET /elementor/list` et `GET /elementor/export-all`.
    - Requête d'agrégation rapide sur `$wpdb->posts` exposant à la racine : `total`, `published_count`, `draft_count`, `private_count`, `active_count`, `inactive_count`, et `filter`.
    - Exposition explicite de `"status"`, `"is_published"`, et `"is_active"` sur chaque page et modèle.
  - **Contrôleur Code / Plugins (`Code_Controller`)** :
    - Correction du filtrage `status=inactive` dans `get_plugins_code_tree`.
    - Compteurs consolidés à la racine : `total`, `active_count`, `inactive_count`, `filter`, `count`.
    - Exposition de `"status"` et `"is_active"` sur chaque plugin.
  - **Catalogue Dynamique `/capabilities` (`Permissions`)** :
    - Documentation détaillée du paramètre `status` et de ses valeurs par défaut sur `/wpcode/snippets`, `/flowmattic/workflows`, `/flowmattic/export-all`, `/elementor/list`, `/elementor/export-all`, et `/code/plugins`.
  - **Générateur de Mega Prompt Admin (Tab 3)** :
    - Ajout de la **Phase 4: Gestion Stricte Actif vs Inactif (Zéro Faux-Positif)** instruisant les agents IA (Antigravity, Cursor, Claude) à toujours interroger `?status=active` en priorité pour les diagnostics de production et à séparer les dossiers locaux.
  - **Client de Synchronisation Locale CLI (`cli/sync.js`)** :
    - Prise en charge du paramètre CLI `--status=active|inactive|all`.
    - Séparation automatique des fichiers sur disque : `snippets/active/` vs `snippets/inactive/`, `flowmattic/workflows/active/` vs `flowmattic/workflows/inactive/`, `elementor/pages/published/` vs `elementor/pages/draft/`.

### v1.3.0 (2026-09-05)
- **Background Process Diagnostics & Schedulers (`Scheduler_Controller`)**:
  - Added dedicated REST Controller (`Scheduler_Controller`) under `WPAgentBridge\Api`.
  - Added `GET /agent-bridge/v1/crons`:
    - Interrogates `_get_cron_array()` and registered schedules via `wp_get_schedules()`.
    - Returns list of crons with next execution timestamps (GMT & local), `human_diff` countdown, overdue detection (`is_overdue`), recurrence intervals (`hourly`, `twicedaily`, `daily`), and sanitized arguments.
    - Global system status: `DISABLE_WP_CRON` constant state, `ALTERNATE_WP_CRON`, server time, total registered crons, and overdue count.
    - Supported parameters: `status` (`all` [default], `overdue`, `future`), `search` (hook name filter), `limit` (default: 100, max: 500).
  - Added `GET /agent-bridge/v1/action-scheduler`:
    - Interrogates WooCommerce Action Scheduler database tables (`{$wpdb->prefix}actionscheduler_actions`).
    - Focuses on actionable/stuck tasks by default: `status=in-progress,failed,pending` (or custom comma-separated list or `all`).
    - Retrieves action ID, hook name, group slug, status, scheduled date (GMT & local), last attempt, attempts count, claim ID, recurrence, and arguments.
    - For `failed` and `in-progress` actions, automatically retrieves the latest failure/error messages directly from `{$wpdb->prefix}actionscheduler_logs` to diagnose exceptions, timeouts, and fatal errors instantly.
    - Summary counts header: pending, in-progress, failed, complete, canceled, and total tracked actions.
    - Supported parameters: `status`, `hook`, `search`, `group`, `per_page` (default: 50, max: 100), `page` (default: 1).
  - Added new permission module `scheduler` ("WP-Cron & Action Scheduler") to Granular Permissions Matrix (Tab 2) enabled by default (`1`).
- **Custom Log Files Inspection (`GET /logs/custom`)**:
  - Added `GET /agent-bridge/v1/logs/custom?file=nom-du-log`:
    - Allows tail-reading arbitrary custom log files located in `wp-content/` (e.g. `komela-order-status-sync.log`, `wc-logs/...`) without being restricted to `debug.log`.
    - Strict path sandboxing: canonical `realpath()` validation strictly within `WP_CONTENT_DIR`, directory traversal prevention (`..`, null bytes), file extension whitelist strictly restricted to `.log` and `.txt`, and blocklist of sensitive configuration files (`wp-config`, `.env`, `.git`).
    - Memory-safe reverse file streaming via `tail_file()` (`fseek`) reading last $N$ lines from bottom up (default: 200, max: 1000) with optional substring/regex `filter`.
    - Real-time secret and PII redaction on every output line.
  - Enhanced `GET /logs/sources` to automatically scan `WP_CONTENT_DIR` for `*.log` files and expose them under `source_type: 'custom'`.
  - Harmonized `GET /logs/view` to support custom logs via the unified secure path resolver.
- **Local CLI Synchronization Client (`cli/sync.js`)**:
  - Added `pull:scheduler` command dumping `./synced-site-data/scheduler/crons.json`, `crons-summary.md`, `action-scheduler.json`, and `action-scheduler-summary.md`.
  - Enhanced `pullLogs()` to automatically download custom `.log` files discovered in `wp-content/`.
  - Integrated `pull:scheduler` into default `pull:all` command.
- **Admin UI & Documentation**:
  - Updated Tab 2 permissions matrix with `scheduler` module.
  - Updated Tab 3 AI Bootstrap prompt with `/crons`, `/action-scheduler`, and `/logs/custom` guidelines.
  - Updated Tab 5 technical scope table.
- **Dynamic Capabilities Discovery (`GET /capabilities`)**:
  - Exposes self-describing API catalog containing all modules, their enabled/disabled permission status, full endpoint paths, supported query parameters, and human-readable descriptions.
  - Implemented `Permissions::get_capabilities_catalog()` to serve as a machine-readable schema for AI development agents (Antigravity, Cursor, Claude).
- **Streamlined AI Bootstrap Prompt (Tab 3)**:
  - Replaced the heavy, static 150-line Mega-Prompt with a future-proof, lightweight Bootstrap Prompt.
  - Decreased token overhead and eliminated prompt desynchronization: instructs the AI to query `GET /capabilities` on startup to automatically generate and maintain its local skill (`.agents/skills/wp-agent-bridge/SKILL.md`).
  - Instructs the AI to periodically re-query `/capabilities` on new tasks to discover newly added data types after plugin updates without requiring manual prompt copy-pasting.
- **Local CLI Synchronization Client (`cli/sync.js`)**:
  - Added `pull:capabilities` command generating `capabilities.json` and a Markdown catalog reference `capabilities.md`.
  - Integrated `pull:capabilities` into the default `pull:all` command.
- **In-Plugin Documentation (Tab 5)**:
  - Updated scope table with `/capabilities`.

### v1.5.0 (2026-09-06)
- **Custom Fields & Meta Inspection Engine (`Meta_Controller`)**:
  - Added dedicated REST Controller (`WPAgentBridge\Api\Meta_Controller`) registering 3 high-performance read-only endpoints:
    - `GET /meta/fields`: Unified catalog of all custom fields defined across the site with flexible filtering (`?source=all|code|acf|db`, `?post_type=product`, `?object_type=post|term|user|comment`, `?search=`, `?include_db=true`, `?limit_db=50`).
    - `GET /meta/acf`: Deep inspection of Advanced Custom Fields ecosystem including field group sources (PHP code `acf_add_local_field_group`, Local JSON `acf-json/`, or DB `acf-field-group`), humanized location rules, complete recursive subfields tree (`repeater`, `group`, `flexible_content` layouts), choices, and registered ACF Options Pages (`acf_add_options_page`).
    - `GET /meta/post/{id}`: Single post/product/order metadata inspector returning resolved formatted and raw ACF field values, code-registered meta values, and a full categorized breakdown of raw `get_post_meta()` into public custom fields and system/hidden `_` fields with safe deserialization and truncation protection.
  - **Universal ACF Detection**: Leverages native ACF functions (`acf_get_field_groups()`, `acf_get_fields()`, `acf_get_options_pages()`) when active, with automatic fallback parsing of theme `acf-json/` directory files if ACF is deactivated.
  - **Core Code-Registered Meta**: Introspects WordPress core global `$wp_meta_keys` and `get_registered_meta_keys()` across all registered post types, taxonomies, users, and comments.
  - **Database Discovery**: Fast, optimized `$wpdb->postmeta` distinct key discovery excluding core WordPress noise/transients.
  - **Granular Permissions Matrix**: Registered `'meta'` module in `Permissions::get_module_definitions()`, enabled by default, and exposed in dynamic self-describing `/capabilities` catalog.
  - **AI Mega-Prompt & Onboarding**: Added live freshness check and quickstart curl command for meta fields in `tab-ai-prompt.php`.
  - **Local CLI Client Integration**: Added `node sync.js pull:meta` command in `cli/sync.js`, integrated into `pull:all`, generating `fields.json`, `acf.json`, and `meta-summary.md`.

### v1.1.0 (2026-09-05)
- **Independent Analytics (Visits & Conversion Rates) Integration**:
  - Added dedicated REST Controller (`Analytics_Controller`) under `WPAgentBridge\Api`.
  - Exposes 8 read-only diagnostic and business endpoints under `/wp-json/agent-bridge/v1/analytics/`:
    - `GET /analytics/overview`: 360° store audit in 1 single HTTP request (summary KPIs, top 10 pages, top 10 referrers, top 10 UTM campaigns, and device breakdowns).
    - `GET /analytics/summary`: Comprehensive traffic KPIs (visitors, pageviews, sessions, bounce rate, duration) and WooCommerce e-commerce performance (orders, gross sales, refunds, net sales, **conversion rate**, earnings per visitor, AOV) with period-over-period % growth comparisons.
    - `GET /analytics/pages`: Content & product performance with traffic, bounce rates, and **product-level conversion rates**.
    - `GET /analytics/referrers`: Traffic acquisition sources and referring domains with their specific conversion rates.
    - `GET /analytics/campaigns`: Marketing UTM tracking (`utm_source`, `utm_medium`, `utm_campaign`) with revenue attribution.
    - `GET /analytics/devices`: Device types (Mobile vs Desktop vs Tablet), browsers, and OS comparison to instantly diagnose mobile checkout UX bottlenecks.
    - `GET /analytics/geo`: Geographic distribution of visitors and orders by country and city.
    - `GET /analytics/conversions`: Recent conversion and order feed with technical and geographic attribution (zero PII, customer IPs/emails are not exposed).
  - Robust date range parser supporting standard relative periods (`today`, `yesterday`, `last_7_days`, `last_30_days`, `this_month`, `last_month`, `last_90_days`, `this_year`, `last_year`, `all_time`) or custom exact intervals (`?start=YYYY-MM-DD&end=YYYY-MM-DD`) with automatic site timezone to UTC conversion.
  - Resilient architecture: Direct read-only `$wpdb` SQL querying calqued on Independent Analytics schemas, ensuring 100% compatibility with both Free and Pro editions across any WordPress setup.
  - Added `analytics` module to Granular Permissions Matrix in WordPress admin with one-click enable/disable toggle.
  - Integrated into dynamic AI Mega-Prompt and documentation tab.
  - Added `pull:analytics` command to `cli/sync.js`.
- **FlowMattic Workflows Inspection & Native JSON Export**:
  - Added dedicated REST Controller (`Flowmattic_Controller`) under `WPAgentBridge\Api`.
  - Added `GET /flowmattic/workflows` to inspect all workflows, their triggers, actions count, status, and executed task counts from `flowmattic_tasks`.
  - Added `GET /flowmattic/workflow/{id}` supporting `?format=export` (or `?format=raw`) producing the exact JSON structure exported by FlowMattic's native export button (`flowmattic_export_workflow()`), stripped of transient `capturedData` and ready for 1:1 re-import.
  - Added `GET /flowmattic/export-all` for high-performance bulk workflow exports in 1 request.
  - Graceful fallback: safely detects if FlowMattic plugin is active or if `{$wpdb->prefix}flowmattic_workflows` table exists, returning clear status messages without errors.
  - Added `flowmattic` module to Granular Permissions Matrix and admin documentation.
  - Integrated FlowMattic into AI Mega-Prompt (Phases 1 & 2 workspace initialization and live freshness checks).
  - Integrated `pull:flowmattic` into `cli/sync.js`.

### v1.0.4 (2026-09-05)
- **System Controller Crash 500 Fix**:
  - Replaced non-existent `OrderUtil::is_custom_order_tables_in_sync()` with container-based `DataSynchronizer::is_sync_in_progress()` inside `try/catch`.
  - Added defensive `method_exists()` check on `OrderUtil::custom_orders_table_usage_is_enabled()`.
  - Wrapped Action Scheduler status interrogation in `try/catch` with `method_exists($as_store, 'action_counts')`.
- **Bulk Elementor Export Route (`GET /elementor/export-all`)**:
  - Added new high-performance endpoint with pagination (`page`, `per_page` up to 100).
  - Returns complete decoded JSON widget trees (`_elementor_data`), page settings, global design kit (`kit`), and forms mapping in a single response.
  - Includes `wp_cache_flush_runtime()` memory cleanup per iteration.
- **Authenticated Rate-Limiting Increase**:
  - Increased allowed rate limit from 120 to 300 req/min for authenticated clients, preventing HTTP 429 throttling during local synchronization.
- **HTML Entity Decoding & Mega-Prompt Onboarding Standard**:
  - Fixed HTML entities escaping (`&amp;`, `&#039;`) in site title and module labels via `html_entity_decode()`.
  - Overhauled Mega-Prompt to standard agency onboarding protocol: Workspace initialization (autonomous Skill, WPCode `.php` exports, Elementor structure), systematic Live Freshness Check before tasks, direct WPCode admin edit links (`page=wpcode-snippet-manager&snippet_id=<ID>`), and strict Read-Only alarm directives.
- **Admin Notice Placement Fix & Header Protection**:
  - Added official WordPress `<hr class="wp-header-end">` anchor and screen-reader `<h1>` at the top of the settings page so core WordPress `common.js` inserts notices cleanly above the plugin card.
  - Converted internal header title to `<h2 class="header-title">` to prevent third-party scripts from targeting the inside of the header card as an insertion point.
  - Added defensive CSS guard (`.agent-bridge-header .notice { display: none !important; }`) and JavaScript relocation logic in `admin.js` to ensure theme recommendations (e.g. TGMPA) and third-party notices never break the header flexbox row.

### v1.0.3 (2026-09-05)
- **Token Authentication & RFC 6750 Compliance Bugfix**:
  - Replaced legacy `wp_generate_password(64, true, true)` with `Security::generate_token()` generating a 64-character hexadecimal string `[0-9a-f]`.
  - Resolved production HTTP 401 `agent_bridge_invalid_token` caused by spaces and shell characters (`$`, quotes, backticks, brackets) breaking header regex extraction and terminal commands.
  - Added transparent **Auto-Healing Migration**: `Security::get_active_token()` validates token compliance against RFC 6750; if legacy or invalid tokens containing spaces/special chars are detected in `wp_options`, a fresh 64-char hex token is generated and persisted automatically.
  - Robust multi-header extraction: improved Bearer regex, stripped accidental enclosing quotes (`"..."`, `'...'`), added alternative fallback headers (`X-Agent-Bridge-Token`, `X-API-Key`) and RFC 6750 Section 2.3 URI Query Parameter fallback (`?access_token=` / `?token=`).
  - Added 1-click **Reset All Failures & Unblock All IPs** button in admin General tab (`agent_bridge_reset_failures` AJAX handler) and automated failure reset on token regeneration.
  - Updated AI Mega-Prompt curl examples to use single quotes (`curl -s -H 'Authorization: Bearer <TOKEN>' ...`) to guarantee zero shell expansion across Bash, Zsh, and PowerShell.

### v1.0.2 (2026-09-05)
- **Enhanced AI Mega-Prompt**:
  - Added explicit instructions for missing data handling: AI drafts feature requests / ready-to-send emails to `julien@soyoo.re` with proposed endpoint routes and controller code.
  - Added documentation for query parameters in curl examples (`filter` and custom `lines` up to 1000 in `/logs/view`, `status=active` in `/code/plugins`, `target=all` in `/theme/options`).
  - Added HTTP status code handling guidance for AI agents (HTTP 403 module permissions, HTTP 429 rate limit / anti-brute-force lockout).
  - Clarified silent mode `curl -s` and read-only action guidelines.
  - Increased prompt textarea height in admin settings for better usability.

### v1.0.1
- Initial public release with PUC v5.6 updater, admin tabs, security redaction, and core REST controllers.

