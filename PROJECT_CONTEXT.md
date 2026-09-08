# WP Agent Bridge — Project Context & Architecture Memory

> **Last Updated**: 2026-09-07  
> **Plugin Identifier / Slug**: `woo-get-data-for-ai`  
> **Main Plugin File**: `woo-get-data-for-ai/woo-get-data-for-ai.php`  
> **GitHub Repository**: `https://github.com/SOYOO974/woo-get-data-for-ai`  
> **Commercial & LTD Roadmap**: [ROADMAP.md](ROADMAP.md) (Stratégie Agences & Lancement Black Friday)  
> **Updates**: Integrated `plugin-update-checker` (PUC v5.6) configured for GitHub branch `main` and release assets.  
> **Security Mandate**: 100% Read-Only (`GET` requests only). Zero hardcoded secrets, tokens, or credentials.  
> **PUBLIC REPOSITORY PRIVACY WARNING**: This file is tracked in a public GitHub repository so the developer can access it across workstations. **It must NEVER contain sensitive, private, or confidential information** (no client domains, real store URLs, live API tokens, passwords, database dumps, or customer PII).

---

## 1. Project Mission & Vision
**WP Agent Bridge** is an enterprise-grade, lightweight, and ultra-secure WordPress & WooCommerce inspection plugin. Its sole purpose is to expose a protected, read-only REST API (`agent-bridge/v1/`) to enable AI coding assistants (Antigravity, Cursor, Claude, ChatGPT) and developers to instantly audit, diagnose bugs, and retrieve technical context from live sites without requiring risky SFTP/SSH access or database credentials.

### Zero-Bloat & Anti-"Usine à Gaz" Vision
As the plugin expands its diagnostic capabilities, **avoiding feature bloat, over-engineering, and never becoming an "usine à gaz" is an absolute, foundational priority**. The plugin is purposefully engineered as an ultra-lightweight, razor-sharp diagnostic bridge. It does one thing and does it exceptionally well: exposing live WordPress/WooCommerce site context to AI coding assistants with near-zero runtime footprint and zero impact on the host store.

### Commercial Strategy & Agency Roadmap
For the strategic and technical product roadmap targeting web agencies and the Black Friday Lifetime Deal (LTD) launch (including licensing infrastructure, white-labeling, multi-tokens governance, form plugins support, MCP server, and LTD packaging), refer directly to [ROADMAP.md](ROADMAP.md).

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
    2. **Synchronize Procedural Playbooks**: Ensure any added endpoints fit within the 7 Strategic MECE Master Pillars.
    3. **Synchronize Internationalization (i18n) & Loco Translate (MANDATORY)**: Run `php cli/sync-i18n.php` to regenerate `.pot`, update French `.po`, and compile `.mo` (100% coverage mandatory).
    4. **Changelog & Documentation**: Document all new features, bugfixes, and breaking changes in `PROJECT_CONTEXT.md` and `README.md`.
    5. **Commit & Push**: Push commits to GitHub `main` branch.
    6. **Generate Release Asset**: Package the clean plugin folder into `woo-get-data-for-ai.zip` (`tar -a -cf woo-get-data-for-ai.zip woo-get-data-for-ai` — CRITICAL: enforce forward slashes for Linux compatibility, do NOT use PowerShell `Compress-Archive`).
    7. **Publish GitHub Release**: Create the official GitHub Release with tag `vX.Y.Z` and attach `woo-get-data-for-ai.zip` via `gh release create`.
    > ⚠️ **CRITICAL WHY**: Client WordPress sites use `plugin-update-checker` (PUC v5.6). Sites will **ONLY** detect and install auto-updates if a formal GitHub Release exists with `woo-get-data-for-ai.zip` attached. Without this, client sites never receive the updates. Also, archives must use forward slashes (`/`) so Linux unzippers don't flatten files or fail class autoloading.


### B. Internationalization (i18n) & Loco Translate Architecture
- Primary language: **English** (code, PHPDoc, UI default strings, documentation).
- Text Domain: `woo-get-data-for-ai`
- Domain Path: `/languages`
- Translation-ready for **Loco Translate** and standard WordPress polyglot tools.
- **100% French Translation Coverage**: Ships with complete master template (`languages/woo-get-data-for-ai.pot`), French PO translation (`languages/woo-get-data-for-ai-fr_FR.po`), and binary compiled MO file (`languages/woo-get-data-for-ai-fr_FR.mo`) covering 439+ UI, playbook, and API strings.
- **Automated CLI Sync Tool**: `php cli/sync-i18n.php` (or `npm run i18n` in `cli/`) scans all tokens across the plugin, regenerates `.pot`, merges French translations from `cli/translations-fr.php`, and compiles the `.mo` file natively without external binary dependencies. Mandatory before every release.
- **Zero Raw Strings Mandate**: All PHP strings are wrapped in gettext (`esc_html__()`, `esc_html_e()`, etc.) and JS strings are localized via `wp_localize_script()` in `Admin_Settings::enqueue_assets()`.

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

### D. Zero-Bloat, Anti-"Usine à Gaz" Architecture & High Performance (MANDATORY TOP PRIORITY)
As endpoints and modules accumulate over time, **preventing the plugin from becoming an "usine à gaz" (a bloated, slow, over-engineered machine) is a permanent, top-priority constraint for all development.**

- **Architectural Simplicity & Anti-Bloat Mandate**:
  - Keep code paths direct, clean, and idiomatic WordPress/PHP.
  - Refuse over-engineering, multi-layered abstractions, unnecessary wrappers, or complex design patterns where clean, native WordPress/PHP patterns work best.
  - Every endpoint must serve a concrete, high-value diagnostic need. Avoid adding endpoints "just in case" or for speculative features.
- **Ultra-Lean Runtime Footprint**:
  - **Near-Zero Idle Overhead**: The plugin lives on production e-commerce stores. When no REST request is active, the plugin does nothing: no heavy background crons, no polling loops, no unsolicited external HTTP requests, and no polluting `wp_options` with unnecessary autoloaded bloat.
  - **Blazing-Fast REST Response**: Endpoints must execute and return in milliseconds with minimal CPU utilization.
- **Strict Memory Safety & Query Optimization**:
  - **Log & File Streaming**: Always use reverse file pointers (`fseek`) for `debug.log`, `wc-logs/`, and error summaries. Never load multi-megabyte files into RAM.
  - **Bounded Database Queries**: Enforce sensible default pagination limits (`limit`, `offset`), select only required columns (`SELECT post_id, meta_key...` instead of `SELECT *`), disable `SAVEQUERIES`, and flush object cache during batch reads.
  - Never load unbounded collections of orders, products, or posts into PHP memory.
- **Defensive & Dependency-Free Modularity**:
  - Every controller under `includes/api/` is standalone and cleanly isolated.
  - Always verify prerequisites defensively (`class_exists()`, `is_plugin_active()`, or `table_exists`) before querying or running logic.
  - Zero heavy external Composer/vendor SDKs. The release ZIP remains minimal and fast to install.
- **Rate Limiting & Protection**:
  - Built-in request rate limiting using WordPress Transients API to prevent brute force or denial-of-service against the REST API.

### E. Mandatory Checklist for Adding a New Data Source / Inspection Module (CRITICAL FOR AI AGENTS & DEVELOPERS)

Whenever adding capabilities to inspect a new data source (e.g. ACF fields, WooCommerce orders/coupons, MetaSlider, SEO plugins, automation tables, custom post types):

The following **8-step synchronization protocol is strictly mandatory** to maintain system integrity across admin settings, the AI onboarding engine, documentation, procedural playbooks, and the local CLI client:

0. **The Anti-"Usine à Gaz" & Utility Gatekeeper (Preliminary Sanity Check — MANDATORY)**:
   Before writing code or designing a new endpoint, rigorously evaluate:
   - *Diagnostic Value*: Does this endpoint deliver essential diagnostic intelligence for AI coding assistants or developers?
   - *Non-Duplication*: Can this need be fulfilled by an existing endpoint with query parameters (e.g. `?type=`, `?scope=`)?
   - *Performance & Memory*: Is data extraction fast, indexed, and memory-safe with negligible server footprint?
   - *Anti-Bloat*: Does the implementation stay simple, clean, and maintainable without adding architectural cruft?
   *If the answer is no, challenge the feature and do NOT implement it.*

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
     - **The MECE 7 Master Pillars Principle**: Every new endpoint or diagnostic capability must be mapped into one of the **7 Strategic MECE Master Playbooks**:
       1. `seo_content_audit` (SEO, Contenus & Visibilité)
       2. `agency_performance_audit` (Performance Multi-Templates & Core Web Vitals)
       3. `database_system_hygiene` (Santé Système, BDD Bloat & Hygiène Background)
       4. `order_checkout_troubleshoot` (Dépannage Commandes, Passerelles, SMTP & Webhooks)
       5. `ecommerce_bi_analytics` (Analytics Ventes, Produits & CRO 360°)
       6. `shipping_logistics_audit` (Zones d'expédition, Méthodes & Flexible Shipping)
       7. `code_theme_integrations` (Architecture Code, Thème, Hooks, Snippets & Automations)
     - **STRICT ANTI-PROLIFERATION MANDATE**: Never create an 8th or ad-hoc micro-playbook. Micro-playbooks dilute the LLM's attention span, cause trigger collisions, and bloat the generated `SKILL.md` by thousands of tokens.
   - Verify that `GET /capabilities` (JSON mode) returns the updated playbook and that `GET /capabilities?format=skill` renders the updated markdown skill correctly.
   - Verify that the playbook correctly adapts when optional or required permissions are toggled off.

4. **AI Mega-Prompt Generator Integration (Tab 3)**:
   - In `includes/admin/views/tab-ai-prompt.php`, ensure the strategic instructions and quickstart command examples include the new capability or playbook reference.

5. **In-Plugin Documentation & Scope Table (Tab 5)**:
   - Update `includes/admin/views/tab-docs.php` in **Section 4: Inspectable Technical Data** (`.docs-scope-table-wrap`).
   - Add a row specifying the technical domain, endpoint paths, and a summary of data returned to the AI.

6. **Internationalization & Loco Translate Synchronization (`cli/sync-i18n.php`) (MANDATORY)**:
   - Ensure all user-facing strings in the controller, permissions catalog, views, and playbooks are strictly wrapped in gettext functions (`esc_html__()`, `esc_html_e()`, `esc_attr__()`, `_n()`, `_x()`) with domain `'woo-get-data-for-ai'`.
   - Run `php cli/sync-i18n.php` (or `npm run i18n` in `cli/`) to regenerate `woo-get-data-for-ai.pot`, merge translations into `woo-get-data-for-ai-fr_FR.po`, and compile binary `woo-get-data-for-ai-fr_FR.mo`.
   - Verify that translation coverage is **100%** and add any new French strings to `cli/translations-fr.php`.

7. **Local CLI Synchronization Client (`cli/sync.js`)**:
   - Add a dedicated command `pull:<source>` in `cli/sync.js` to dump the data locally into `./synced-site-data/<source>/`.
   - Integrate the new command into `pull:all`.
   - Update the usage help text in `cli/sync.js` and `README.md`.

8. **Repository Documentation & Release Protocol**:
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
- `[x] Code & Plugin Inspector` (`/code/plugins`, `/code/file`, `/code/checksums`, `/code/zip`)
- `[x] Elementor Architecture` (`/elementor/list`, `/elementor/forms`, `/elementor/kit`)
- `[x] Code Snippets (WPCode & Code Snippets Pro)` (`/snippets`, `/wpcode/snippets`)
- `[x] Error & WooCommerce Logs` (`/logs/sources`, `/logs/view`)
- `[x] FlowMattic Workflows` (`/flowmattic/export-all`, `/flowmattic/workflows`, `/flowmattic/workflow/{id}`)
- `[x] Independent Analytics (Visits & Conversion Rates)` (`/analytics/overview`, `/analytics/summary`, `/analytics/pages`, `/analytics/referrers`, `/analytics/campaigns`, `/analytics/devices`, `/analytics/geo`, `/analytics/conversions`)
- `[x] Custom Fields & Meta (ACF & Code)` (`/meta/fields`, `/meta/acf`, `/meta/post/{id}`)
- `[x] WooCommerce Store Data (Products, Orders, Settings, Shipping)` (`/woocommerce/summary`, `/woocommerce/products`, `/woocommerce/product/{id}`, `/woocommerce/orders`, `/woocommerce/order/{id}`, `/woocommerce/settings`, `/woocommerce/shipping`, `/woocommerce/analytics/sales`, `/woocommerce/analytics/top-performers`, `/woocommerce/analytics/stock`, `/woocommerce/webhooks`)
- `[x] Pages, Content & SEO` (`/content/pages`, `/content/page/{id}`, `/content/posts`, `/content/post/{id}`, `/content/seo-audit`)
- `[x] Site Performance & Plugin Profiler` (`/performance/profile`, `/performance/autoload`, `/performance/plugins-summary`, `/performance/templates-urls`)
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
| `GET /system` | GET | Server, WP/WC/PHP/MySQL versions, active plugins list & summary counts (`?plugins=active\|inactive\|all`, default: `active`), HPOS status, Action Scheduler queue & retention policy |
| `GET /system/database` | GET | In-depth database diagnostic: table sizes, top 15 largest tables, autoload footprint analysis with 800KB alert threshold and orphaned options from inactive plugins, transient counts, and object cache status |
| `GET /system/mail` | GET | SMTP & transactional email diagnostic: active provider (FluentSMTP, WP Mail SMTP, Post SMTP), credentials redaction, PHP `mail()` spam risk detection, and recent delivery failures |
| `GET /system/security` | GET | Security hardening audit: `DISALLOW_FILE_EDIT`, `DISALLOW_FILE_MODS`, `WP_DEBUG_DISPLAY`, XML-RPC exposure, SSL enforcement, DB prefix, detected security and caching plugins |
| `GET /theme/options` | GET | Decoded options for **Woodmart** (`xts-woodmart-options`), **Elessi** (`elessi_options` / Redux), and Customizer theme mods (sensitive keys redacted) |
| `GET /theme/overrides` | GET | WooCommerce template overrides in the active theme with version comparison to core WC |
| `GET /theme/child` | GET | Code and header info of the child theme's `functions.php` and `style.css` |
| `GET /code/plugins` | GET | File trees of active or all plugins and `wp-content/mu-plugins/` (`?status=active|inactive|all`, default: `active`) |
| `GET /code/file` | GET | Source code of a specific PHP/JS/CSS file (strictly sandboxed via `realpath()`) |
| `GET /code/checksums` | GET | Cryptographic file checksum map (MD5 / SHA256), modified timestamps, and byte sizes for instant local vs prod drift verification (`?path=plugins/my-plugin`, `?algo=md5\|sha256`) |
| `GET /code/zip` | GET | Clean, on-the-fly ZIP archive export of custom plugins or child themes without `.git`, logs, or sensitive files (`?path=plugins/my-plugin`, `?format=stream\|base64`, default: `stream`) |
| `GET /elementor/export-all` | GET | Bulk export of all Elementor pages, templates, kit & forms in 1 optimized request (`?status=all|publish|draft|active|inactive`, default: `all`) |
| `GET /elementor/list` | GET | Elementor pages, posts, and templates (`elementor_library`) (`?status=all|publish|draft|active|inactive`, default: `all`) |
| `GET /elementor/item/{id}` | GET | Full decoded `_elementor_data` JSON tree and page settings |
| `GET /elementor/forms` | GET | Inventory of all Elementor forms (field definitions, actions, webhook URLs) |
| `GET /elementor/kit` | GET | Global colors, system fonts, and design tokens from the active Elementor Kit |
| `GET /snippets` | GET | Unified listing of custom snippets across WPCode and Code Snippets (`?status=active\|inactive\|all`, default: `active`, `?source=all\|code-snippets\|wpcode`, `?type=all\|php\|css\|js\|html`) with global `active_count` and `inactive_count` |
| `GET /snippets/{id}` | GET | Full source code, execution location, priority, tags, and direct WordPress Admin edit link with collision resolution (`?source=all\|code-snippets\|wpcode`) |
| `GET /wpcode/snippets` | GET | Legacy alias: Listing of custom snippets across WPCode and Code Snippets (`?status=active\|inactive\|all`, default: `active`, `?source`, `?type`) |
| `GET /wpcode/snippet/{id}` | GET | Legacy alias: Full source code and configuration of a targeted snippet (`?source`) |
| `GET /logs/sources` | GET | Available log files (`debug.log`, `uploads/wc-logs/*.log`, custom logs) with sizes & dates |
| `GET /logs/view` | GET | Memory-safe tail extraction of the last $N$ lines with optional error filtering |
| `GET /logs/custom` | GET | Memory-safe tail inspection of specific log files in `wp-content/` with strict path sandboxing (`?file=nom-du-log`) |
| `GET /logs/errors-summary` | GET | Crash Watch: aggregated and deduplicated recent fatal PHP errors and exceptions from `debug.log` and `wc-logs` with component attribution (`?limit=15`) |
| `GET /crons` | GET | WP-Cron registered jobs, next execution timestamps (GMT & local), recurrence intervals, overdue tasks, and hook arguments |
| `GET /action-scheduler` | GET | Action Scheduler queue (in-progress, failed, pending), hook, group, attempts, arguments, retention policy in days, bloat alert, and error logs from `actionscheduler_logs` |
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
| `GET /woocommerce/summary` | GET | High-level store health, product counts by status/stock/type, order counts and hygiene analysis (cancellation ratio, stale unpaid orders > 1y), HPOS state, active payment gateways, and shipping zones |
| `GET /woocommerce/products` | GET | Paginated WooCommerce product catalog with SKU, prices, stock, categories, tags, attributes, and variations (`?status=publish\|draft\|all`, `?type=`, `?stock_status=`, `?category=`, `?search=`, `?per_page=20`, `?page=1`) |
| `GET /woocommerce/product/{id}` | GET | Detailed product inspection including variations breakdown, dimensions, images, unified SEO object, and sanitized postmeta custom fields |
| `GET /woocommerce/coupons` | GET | List and filter promotional discount coupons with status (`active`, `expired`, `exhausted`, `all`), discount types, usage counts, limits, held counts, and PII-masked email restrictions (`?status=`, `?type=`, `?search=`, `?email=`, `?per_page=20`, `?page=1`, `?orderby=date\|code\|usage_count\|modified`, `?order=DESC\|ASC`) |
| `GET /woocommerce/coupon/{id}` | GET | Deep inspection of a single coupon by numeric ID or code slug: discount rules, real-time availability (`is_valid_now`, `usage_left`), active held checkout sessions (`_coupon_held_keys`), and last 10 associated orders |
| `GET /woocommerce/orders` | GET | Recent orders with strict GDPR/PII anonymization (masked customer details, redacted emails/phones/addresses), item lines, coupon lines, applied coupon codes, totals, and gateways (`?status=processing\|completed\|failed\|all`, `?search=`, `?customer_id=`, `?coupon=`, `?per_page=10`) |
| `GET /woocommerce/order/{id}` | GET | Deep order diagnostics: item line metadata, shipping lines with decoded metadata (`shipping_lines[].meta_data` including Flexible Shipping `fs_costs` base & additional costs), fees, coupon lines, refunds, order notes (payment gateway responses), and sanitized metadata |
| `GET /woocommerce/settings` | GET | Store configuration: currency, tax settings, stock management, active payment gateways (secrets redacted), and shipping zones/methods with geo-locations, flat_rate table rate rules, and Flexible Shipping matrix rules |
| `GET /woocommerce/shipping` | GET | Dedicated logistics & shipping inspection: zones, geographic locations (postcodes, states, countries), native method parameters, flat_rate table rate rules (`flexible_shipping_table_rate`), Flexible Shipping & Flexible Shipping PRO matrix calculation rules (tiers, classes, conditions), and sanitized `raw_instance_settings` |
| `GET /woocommerce/analytics/sales` | GET | 100% native WooCommerce sales report: net sales, gross sales, orders count, AOV, refunds, daily trend, and growth percentage compared to previous period (`?range=last_30_days`, `?start_date=`, `?end_date=`) |
| `GET /woocommerce/analytics/top-performers` | GET | Top products by net revenue & volume sold, and top coupons with discount totals (`?limit=10`, `?range=last_30_days`) |
| `GET /woocommerce/analytics/stock` | GET | Stock financial valuation, low stock alerts, and dormant stock (0 sales in last 90 days) (`?low_stock_threshold=`) |
| `GET /woocommerce/webhooks` | GET | WooCommerce webhooks inventory, delivery URLs, topics, and failure counters (`failure_count >= 5`) |
| `GET /content/pages` | GET | Paginated WordPress pages list with hierarchy, slug, status, template PHP, editor type (Gutenberg/Classic/Elementor), special page flags, and quick SEO preview (`?status=publish\|draft\|all`, `?parent=`, `?search=`, `?per_page=20`, `?page=1`) |
| `GET /content/page/{id}` | GET | Deep page inspection: raw/rendered content, Gutenberg blocks summary, detected shortcodes, word count, parent/child hierarchy, and unified normalized SEO metadata |
| `GET /content/posts` | GET | Paginated blog posts list with categories, tags, author, editor type, and quick SEO preview (`?status=publish\|draft\|all`, `?category=`, `?tag=`, `?search=`, `?per_page=20`) |
| `GET /content/post/{id}` | GET | Deep post or custom post type inspection: raw/rendered content, blocks, taxonomies, sanitized postmeta, and full unified SEO object |
| `GET /content/seo-audit` | GET | Site-wide SEO audit report across pages, posts, WooCommerce products, and categories: missing meta descriptions, title issues, noindex warnings on published products/checkout, thin content, and category descriptions (`?include_posts=true\|false`, `?include_products=true\|false`, `?include_categories=true\|false`, `?limit=100`, `?limit_products=50`) |
| `GET /performance/templates-urls` | GET | Auto-discovers and resolves representative URLs for 5 key e-commerce page archetypes: Homepage (`/`), Shop (`/shop/`), Product Category, Single Product, and Cart/Checkout |
| `GET /performance/profile` | GET | Targeted on-demand URL profiler: attributes SQL queries and duration per plugin via stack backtraces, detects duplicate/slow queries (>50ms), measures TTFB, memory, enqueued JS/CSS assets, and 100% native Core Web Vitals signals (DOM size/depth, Elementor nodes %, CLS images missing dimensions, legacy PNG/JPEG images, external Google Fonts display=swap check, WP core bloat scripts, wc-cart-fragments, server compression) (`?path=/`, `?include_assets=true`, `?include_queries=true`, `?slow_query_threshold_ms=50`) |
| `GET /performance/autoload` | GET | Deep `wp_options` autoload bloat analysis: total size vs 800KB threshold, top heaviest options, and size distribution grouped by plugin prefix (`?limit=25`) |
| `GET /performance/plugins-summary` | GET | Consolidated resource footprint per plugin: active status, associated database tables count, database disk size, and table row counts (`?status=active\|all`) |

---

### 4.B Procedural AI Playbooks & Self-Updating Skills System (`includes/class-playbooks.php`)

To prevent AI prompt stagnation and trial-and-error querying across 25+ endpoints, the plugin features an intelligent **Playbooks Engine**:
- **Zero-Prompt Stagnation**: Instead of memorizing static endpoint lists, AI agents query `GET /capabilities?format=skill` to instantly generate an up-to-date `.agents/skills/wp-agent-bridge/SKILL.md` workspace skill.
- **Permission-Adaptive Workflows**: When an administrator disables a module in the Permissions matrix, dependent Playbooks and individual workflow steps are automatically excluded from the catalog so the AI never triggers `403 Forbidden` errors.
- **Built-in Procedural Playbooks (7 MECE Strategic Master Pillars)**:
  To eliminate LLM attention dilution and trigger collisions while providing comprehensive agency-grade diagnostics, the playbooks are strictly organized into 7 MECE (Mutually Exclusive, Collectively Exhaustive) master pillars:
  1. `seo_content_audit` (360° SEO, Content Hierarchy & Visibility Audit): Meta tags, critical noindex detection on pages/products, OpenGraph coverage, canonical audit, and Gutenberg content hierarchy (`/content/seo-audit`, `/content/pages`, `/content/page/{id}`).
  2. `agency_performance_audit` (Agency Multi-Template Performance & Core Web Vitals Audit): Strategic 5-template archetypes discovery (Home, Shop, Category, Product, Cart), on-demand profiling (SQL duration/queries per plugin, TTFB, memory), 100% native server-side Core Web Vitals (DOM size, Elementor nodes %, CLS missing dimensions, legacy image formats, Google Fonts display=swap, core bloat scripts, wc-cart-fragments, server compression), and active plugins database footprint (`/performance/templates-urls`, `/performance/profile`, `/performance/plugins-summary`).
  3. `database_system_hygiene` (System Health, Database Bloat & Background Hygiene Audit): Unified system infrastructure, memory limits, database size & top heavy tables, autoload memory bloat with orphaned options detection from inactive plugins, WooCommerce order status distribution & stale unpaid orders (> 1y), Action Scheduler queue backlog & retention policy with bloat alerts, overdue WP-Cron jobs, and Crash Watch fatal error summary (`/system`, `/system/database`, `/woocommerce/summary`, `/action-scheduler`, `/crons`, `/logs/errors-summary`).
  4. `order_checkout_troubleshoot` (Orders, Payment Gateways & Delivery Troubleshooting): Full e-commerce operational troubleshooting combining recent order failures, payment gateway error notes, coupon/fee inspections, gateway debug logs, active checkout snippets/hooks, transactional SMTP mail delivery diagnostics (provider detection, credentials redaction, PHP mail() spam risk), and WooCommerce webhook delivery status (`/woocommerce/orders`, `/woocommerce/order/{id}`, `/logs/view`, `/snippets`, `/system/mail`, `/woocommerce/webhooks`).
  5. `ecommerce_bi_analytics` (360° E-Commerce Sales, Traffic & Conversion Analytics): Complete commercial & CRO intelligence: native WooCommerce sales (gross/net, paid orders, AOV, refunds, % growth vs prior period), top performing products & coupons, stock valuation & dormant inventory, alongside traffic channels, UTM marketing campaigns, and device breakdowns (`/woocommerce/analytics/sales`, `/woocommerce/analytics/top-performers`, `/woocommerce/analytics/stock`, `/analytics/overview`, `/analytics/campaigns`).
  6. `shipping_logistics_audit` (Shipping Zones, Methods & Flexible Shipping Rules Audit): Comprehensive logistics & shipping rate calculations: WooCommerce shipping zones, geo-locations (postcodes, regions, countries), native methods (flat rate, free shipping threshold), Flexible Shipping PRO matrix calculation rules (weight/price tiers, shipping classes), and deep order shipping line metadata inspection (`/woocommerce/shipping`, `/woocommerce/settings`, `/woocommerce/order/{id}`).
  7. `code_theme_integrations` (Code Architecture, Theme Settings & Automations Map): Complete technical codebase audit: WooCommerce template version overrides, child theme files, directory checksum fingerprints for local vs remote drift detection, FlowMattic automation recipes, Elementor webhook forms, active custom snippets (WPCode & Code Snippets), and custom ACF/code meta fields (`/theme/overrides`, `/theme/child`, `/code/checksums`, `/flowmattic/workflows`, `/elementor/forms`, `/snippets`, `/meta/fields`).

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
- Commands: `pull:all`, `pull:capabilities`, `pull:skill`, `pull:system`, `pull:scheduler`, `pull:theme`, `pull:code`, `pull:checksums`, `pull:elementor`, `pull:snippets`, `pull:flowmattic`, `pull:analytics`, `pull:meta`, `pull:woocommerce`, `pull:content`, `pull:performance`, `pull:logs`.
- Optional status filtering: `--status=active|inactive|all`.
- Optional path targeting for checksums & code inspection: `--path=plugins/<plugin-slug>`.
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
- Optimized for v1.10.0: `pull:code` dumps plugins and mu-plugins code tree (`./synced-site-data/code/plugins.json` and `plugins.md`), and with `--path=<dir>` computes directory checksum fingerprints (`./synced-site-data/code/checksums.json` and `checksums.md`) for instant local vs remote drift detection.
- Optimized for v1.11.0: `pull:woocommerce` dumps dedicated shipping logistics (`./synced-site-data/woocommerce/shipping.json`) and enriches `summary.md` with zones, geo-locations, and Flexible Shipping matrix rules.
- Optimized for v1.12.0: `pull:performance` dumps autoload bloat (`autoload.json`) and active plugins DB footprint (`plugins-summary.json`) into `./synced-site-data/performance/` and generates an executive performance report (`performance-report.md`).
- Optimized for v1.13.0: `pull:performance` dumps multi-template URLs (`templates-urls.json`), homepage profile (`profile-home.json`), mobile PageSpeed insights (`pagespeed-mobile.json`), autoload bloat (`autoload.json`), and active plugins DB footprint (`plugins-summary.json`) into `./synced-site-data/performance/` and generates a comprehensive executive report (`performance-report.md`).
- Optimized for v1.14.0: `pull:woocommerce` extracts Table Rate rules on flat_rate and order shipping lines meta_data.
- Optimized for v1.15.0: `pull:performance` dumps multi-template URLs (`templates-urls.json`), homepage profile with 100% native Web Vitals (`profile-home.json`), autoload bloat (`autoload.json`), and active plugins DB footprint (`plugins-summary.json`) into `./synced-site-data/performance/` with zero external API dependencies.
- Optimized for v1.16.0: `pull:woocommerce` enriches `summary.md` with order volume hygiene analysis (cancellation ratio & stale abandoned orders > 1y); `pull:scheduler` enriches `action-scheduler-summary.md` with active retention policy and bloat alerts.
- Generates a cleanly structured local export under `./synced-site-data/`.

---

## 7. Version Changelog

### v1.22.0 (2026-09-08)
- **Harmonisation des Statuts Actifs/Inactifs & Optimisation Drastique des Tokens (`System_Controller`, `Wpcode_Controller`, `Permissions`, `Playbooks`, `sync.js`)** :
  - **Endpoint `GET /system` Révolutionné** :
    - Élimination définitive des biais cognitifs et faux positifs d'agents IA (ex: outils de débug inactifs pris pour des processus actifs dégradant le TTFB).
    - Filtrage par défaut sur les extensions actives (`?plugins=active` par défaut).
    - Nouveaux paramètres explicites : `?plugins=all` (toutes les extensions installées avec booléen `is_active`) et `?plugins=inactive` (extensions dormantes).
    - `plugins_count` reflète désormais fidèlement le nombre de plugins retournés dans le tableau (par défaut, uniquement les extensions actives).
    - Ajout du bloc racine `plugins_summary` avec compteurs exhaustifs : `total_installed`, `active_count`, `inactive_count`, et `must_use_count`.
  - **Endpoints `GET /snippets` & `GET /wpcode/snippets` Inversés par Défaut** :
    - Comportement par défaut réaligné sur `status=active` : ne retourne que les extraits réellement actifs et exécutés en production.
    - Économie massive de tokens (ex: évite d'ingérer 178 extraits inactifs/brouillons sur un total de 275 sur Conforama.re) et suppression des hallucinations d'IA sur du code mort.
    - Paramètres explicites préservés : `?status=all` pour auditer l'historique complet et `?status=inactive` pour inspecter les extraits dormants.
  - **Générateur de Compétences `SKILL.md` & Playbooks** :
    - Renforcement de la directive contractuelle n°2 (*ACTIVE VS INACTIVE CODE & PLUGIN INTEGRITY*) alertant formellement les agents sur les valeurs par défaut actives de `/system`, `/code/plugins` et `/snippets`.
  - **Synchronisation CLI & i18n** :
    - Prise en charge de `plugins_summary` dans `cli/sync.js` (`system-report.md`) et forçage de `?status=all` lors des sauvegardes complètes CLI.
    - Synchronisation i18n 100% avec mise à jour de `woo-get-data-for-ai.pot`, `woo-get-data-for-ai-fr_FR.po`, et recompilation binaire de `woo-get-data-for-ai-fr_FR.mo`.

### v1.21.1 (2026-09-08)
- **Correctif Critique sur l'Inspection des Commandes (`Woocommerce_Controller`)** :
  - **Correction Fatal Error `OrderRefund::get_order_number()`** : Forçage explicite du paramètre `'type' => 'shop_order'` dans `$query_args` pour `wc_get_orders()` dans `GET /woocommerce/orders`, excluant nativement les objets de remboursement (`shop_order_refund`).
  - **Sécurisation Défensive Multi-Couches** :
    - Filtrage strict dans la boucle `foreach ($results->orders as $order_obj)` avec `!($order_obj instanceof \WC_Order) || ($order_obj instanceof \WC_Order_Refund)`.
    - Sécurisation de l'appel `$order_obj->get_order_number()` via `method_exists()` avec fallback sur l'ID de commande.
    - Sécurisation de `$order_obj->get_coupon_codes()` via `method_exists()`.
    - Sécurisation symétrique dans `GET /woocommerce/order/{id}`, ainsi que dans les sessions en retenue (`held_sessions`) et commandes associées (`associated_orders`) de `GET /woocommerce/coupon/{id}`.

### v1.21.0 (2026-09-08)
- **Module Codes Promos (Coupons) & Amélioration de l'Inspection des Commandes (`Woocommerce_Controller`, `Permissions`, `Playbooks`, `sync.js`)** :
  - **Nouveaux Endpoints REST d'Inspection des Codes Promos** :
    - `GET /woocommerce/coupons` : Découverte et filtrage des codes promos de la boutique avec compteurs de résumé global (`total_coupons`, `active_coupons`, `expired_coupons`, `exhausted_coupons`), filtrage par statut (`active`, `expired`, `exhausted`, `all`), type de remise (`fixed_cart`, `percent`, `fixed_product`, `all`), recherche textuelle (code ou description), restrictions e-mail, pagination et tris (`date`, `code`, `usage_count`, `modified`). Masquage RGPD strict des adresses e-mails restreintes (`w***e@domain.com`).
    - `GET /woocommerce/coupon/{id}` : Inspection approfondie unitaire d'un code promo par ID numérique ou par slug/code textuel. Retourne l'ensemble des attributs du coupon (`WC_Coupon`), un diagnostic en temps réel de disponibilité (`is_valid_now`, `usage_left`), les sessions de checkout en cours de retenue (`held_sessions` via `_coupon_held_keys` et commandes en attente avec ID, date, statut et total), et les 10 dernières commandes associées ayant appliqué ce code (`associated_orders` avec statut, date, total, devise et client anonymisé RGPD).
  - **Amélioration de l'Inspection des Commandes (`GET /woocommerce/orders`)** :
    - **Injection des Codes Promos dans les Commandes** : Chaque objet commande expose désormais `coupon_lines` (détail des remises et taxes de remise par coupon) et `coupon_codes` (tableau des codes appliqués).
    - **Filtrage Direct par Code Promo (`?coupon=<code>`)** : Permet de requêter directement `GET /woocommerce/orders?coupon=sophie_5eur_2` pour retrouver instantanément toutes les commandes (en cours, payées ou en attente) rattachées à ce code.
    - **Recherche Améliorée par E-mail et Nom Client en Environnement HPOS & CPT** : Résolution fiable et indexée de la recherche `$query_args['s']` sur les tables personnalisées de commandes HPOS (`wp_wc_orders` et `wp_wc_order_addresses`) et fallback CPT pour rechercher par e-mail, nom de client, numéro de transaction ou ID de commande sans altération.
  - **Permissions, Capabilities & Playbooks** :
    - Enregistrement des nouvelles routes dans `get_module_definitions()` et `get_capabilities_catalog()`.
    - Enrichissement des Piliers 4 (`order_checkout_troubleshoot`) et 5 (`ecommerce_bi_analytics`) avec déclencheurs et directives de diagnostic coupons (`_coupon_held_keys`, quota restant).
  - **Client CLI Local (`cli/sync.js`)** :
    - `pullWooCommerce()` télécharge automatiquement `/woocommerce/coupons` dans `./synced-site-data/woocommerce/coupons.json`.
  - **Internationalisation & Loco Translate** : 442 chaînes uniques, couverture française maintenue à 100% dans `languages/`.

### v1.20.0 (2026-09-07)
- **Audit des Options de Performance & Checkout WooCommerce (`Woocommerce_Controller`, `System_Controller`, `Playbooks`)** :
  - **Diagnostic Natif 5 Leviers Clés dans `GET /woocommerce/summary` & `GET /system`** :
    - *High-Performance Order Storage (HPOS)* (`hpos`) : Détection de l'état actif et de la table autoritaire (`custom_orders_table`).
    - *Mise en Cache des Données HPOS* (`hpos_data_caching`) : Introspection via `FeaturesUtil::feature_is_enabled('hpos_datastore_caching')` et fallback d'option. Croisement intelligent avec la présence d'un cache objet persistant (`wp_using_ext_object_cache()`, Redis/Memcached) pour recommander l'activation afin de réduire drastiquement les requêtes SQL redondantes sur les commandes.
    - *E-mails Transactionnels Différés* (`deferred_transactional_emails`) : Détection en temps réel du filtre `apply_filters('woocommerce_defer_transactional_emails', false)` et des extensions spécialisées. Recommandation prioritaire pour éliminer le gel de 2 à 5 secondes sur la page de confirmation de commande dû à la latence SMTP synchrone.
    - *Limitation du Débit Checkout* (`checkout_rate_limiting`) : Détection de `FeaturesUtil::feature_is_enabled('rate_limit_checkout')` et de l'option correspondante. Recommandation pour protéger la passerelle et la boutique contre le carding et les attaques bots.
    - *Index de Recherche Plein Texte HPOS* (`hpos_full_text_search`) : Détection de `hpos_fts_indexes` (fonctionnalité expérimentale). Recommandation ciblée pour les boutiques comptant plus de 5 000 commandes avec recherche admin ralentie.
  - **Recommandations Actionnables Directes** : Tableau structuré `recommendations` dans `woocommerce.performance_features` avec niveau de priorité (`high`, `medium`, `low`), titre et justification technique.
  - **Synchronisation des Playbooks MECE (Piliers 3 & 4)** :
    - Pilier 3 (`database_system_hygiene`) : Étape 3 enrichie avec les signaux `woocommerce.performance_features.hpos` et `hpos_data_caching`.
    - Pilier 4 (`order_checkout_troubleshoot`) : Étape 5 enrichie pour vérifier les e-mails différés et le rate limiting checkout en cas de ralentissements ou d'échecs au checkout.
    - Générateur de Skill IA (`SKILL.md`) : Ajout de la Directive #5 guidant l'IA sur l'interprétation et les nuances de chaque option.
  - **Internationalisation & Loco Translate** : 439 chaînes uniques, couverture française maintenue à 100% dans `languages/`.

### v1.19.1 (2026-09-07)
- **Synchronisation Complète i18n & Compatibilité Loco Translate 100% (`languages/`, `Admin_Settings`, `admin.js`, `cli/sync-i18n.php`)** :
  - **Couverture de Traduction Française à 100% (429 chaînes)** :
    - Récupération et traduction intégrale de l'ensemble des 290+ chaînes manquantes ajoutées au fil des versions (onglets Onboarding, Playbooks, Mega-Prompt, Permissions, Documentation, Logs, et contrôleurs REST).
    - Mise à jour du template maître POT (`woo-get-data-for-ai.pot`, 64 Ko).
    - Synchronisation du catalogue de traductions PO (`woo-get-data-for-ai-fr_FR.po`, 107 Ko).
    - Compilation native du fichier binaire MO (`woo-get-data-for-ai-fr_FR.mo`, 86 Ko, 430 entrées) avec vérification d'intégrité par parseur binaire gettext.
  - **Localisation des Chaînes JavaScript (`admin.js`, `Admin_Settings`)** :
    - Injection des chaînes d'interface dynamiques (boutons "Show / Hide", alertes de copie, messages d'erreurs réseau, confirmations de réinitialisation) via `wp_localize_script()` dans l'objet global `agentBridgeData`.
    - Localisation des chaînes de statut dans les vues d'administration (`tab-logs.php`).
  - **Outil d'Automatisation CLI Autonome (`cli/sync-i18n.php`, `npm run i18n`)** :
    - Développement d'un outil PHP d'extraction de tokens (`token_get_all`) et de compilation binaire MO native (pack binaire `0x950412de`) sans dépendance vers GNU `msgfmt` ou `gettext`.
    - Dictionnaire de référence structuré dans `cli/translations-fr.php`.
    - Commande `npm run i18n` dans `cli/package.json`.
  - **Directive Anti-Régression Stricte (`AGENTS.md`, `PROJECT_CONTEXT.md`)** :
    - Établissement de la directive obligatoire : synchronisation i18n systématique (100% de couverture requise) avant toute nouvelle release GitHub.

### v1.19.0 (2026-09-07)
- **Support Complet et Unifié Code Snippets & WPCode (`Wpcode_Controller`, `Permissions`, `Playbooks`, `sync.js`)** :
  - **Routes Unifiées & Rétrocompatibilité** :
    - Nouvelles routes unifiées `GET /snippets` et `GET /snippets/{id}` avec conservation des routes existantes `GET /wpcode/snippets` et `GET /wpcode/snippet/{id}`.
    - Nouveaux paramètres de filtrage : `?source=all|code-snippets|wpcode` et `?type=all|php|css|js|html` aux côtés de `?status=all|active|inactive`.
  - **Extraction Fidèle et Détection Dynamique Code Snippets (`{$wpdb->prefix}snippets`)** :
    - Déduction dynamique du type de code (`code_type`) depuis `scope` : `-css` -> `css`, `-js` -> `js`, `content` -> `html`, sinon `php`.
    - Traduction lisible de `location` depuis `scope` : `global` -> `run-everywhere`, `admin` -> `admin-only`, `front-end` -> `front-end-only`, `single-use` -> `single-use`, ou conservation de la valeur brute (`site-head-js`, `site-css`, etc.).
    - Extraction réelle de la colonne `priority`, du champ `description`, et normalisation des `tags` (tableau nettoyé depuis tableau sérialisé, JSON ou chaîne séparée par des virgules).
    - Génération systématique du lien direct d'édition admin : `admin_url('admin.php?page=edit-snippet&id=' . $id)`.
  - **Extraction Enrichie WPCode (`wpcode` CPT)** :
    - Extraction de `description` (`_wpcode_snippet_description`), normalisation des tags (`wpcode_tags` / `wpcode_tag`), et génération du lien d'édition admin : `admin_url('admin.php?page=wpcode-snippet-manager&snippet_id=' . $id)`.
  - **Schéma JSON Uniforme & Résolution des Collisions** :
    - Schéma cohérent et complet partagé entre les collections et les détails unitaires : `source_plugin`, `id`, `title`, `status`, `is_active`, `code_type`, `location`, `priority`, `description`, `tags`, `admin_edit_url`, `modified_at`, `code`.
    - Résolution des collisions d'ID dans `get_snippet_by_id` avec paramètre optionnel `?source=wpcode|code-snippets` (recherche en cascade WPCode puis Code Snippets par défaut).
  - **Permissions, Playbooks & Mega-Prompt** :
    - Renommage du module en `Code Snippets (WPCode & Code Snippets Pro)`.
    - Mise à jour du Playbook 4 (Étape 4) et du Playbook 7 (Étape 6 : « Active Custom Snippets (WPCode & Code Snippets) ») pointant sur `/snippets`.
    - Mise à jour de la Directive #3 dans `generate_skill_markdown()` et le Mega-Prompt pour les liens d'édition admin WPCode et Code Snippets.
  - **Client CLI (`cli/sync.js`)** :
    - Prise en charge de l'extension `.html` pour le type `html`, interrogation transparente de `/snippets` avec fallback `/wpcode/snippets`, et enrichissement de l'en-tête de fichier (`Type`, `Location`, `Priority`, `Description`, `Tags`, `Admin URL`).

### v1.18.0 (2026-09-07)
- **Bannières d'Onboarding Premier Utilisateur & Connexion IA en 2 Clics (`Access_Logger`, `Admin_Settings`, `tab-general.php`)** :
  - **Détection d'État Zéro-Bloat de Première Connexion (`Access_Logger::has_connected()`)** :
    - Détection intelligente basée sur l'historique des requêtes REST authentifiées (HTTP 200).
    - Mise en cache persistante dans `wp_options` (`wp_agent_bridge_has_connected`) dès la première requête reçue d'un agent IA ou d'un outil client, garantissant un coût d'exécution de 0ms et 0 requête SQL sur les chargements ultérieurs de l'administration WordPress.
  - **Notification Globale d'Administration (`admin_notices`)** :
    - Affichage d'une bannière de bienvenue non-intrusive sur l'administration WordPress pour les administrateurs tant qu'aucune IA ne s'est connectée au site.
    - Rappel clair et rassurant de la sécurité 100% lecture seule (`100% Read-Only & Safe`) avec caviardage automatique des identifiants et des données personnelles clients (PII).
    - Exemple concret de prompt à forte valeur ajouté à tester immédiatement : *"Audit my site's frontend performance, identify slow plugins, and check database bloat to find quick optimization wins."* avec bouton de copie en 1 clic.
    - Bouton d'action principal redirigeant directement vers l'onglet "AI Mega-Prompt & Skill".
    - Masquage standard par l'icône de fermeture `(X)`, mémorisé par utilisateur via user meta (`wp_agent_bridge_onboarding_dismissed`) grâce à l'action AJAX `agent_bridge_dismiss_onboarding`.
    - Masquage automatique sur la page de réglages du plugin pour éviter toute redondance visuelle.
  - **Carte d'Onboarding Héroïque dans le Plugin (`tab-general.php`)** :
    - Bannière héroïque dédiée dans l'onglet Général guidant l'utilisateur en 2 étapes simples : récupération du prompt/skill et collage dans son assistant IA préféré (Antigravity, Claude, Cursor, ChatGPT).
    - Barre de réassurance technique (100% Read-Only, Auto-Redacted PII, Zéro impact au repos).
    - Bascule automatique dès la première connexion enregistrée vers une carte de confirmation verte valorisant la liaison active avec les agents IA et fournissant un lien direct vers les journaux d'accès (`tab=logs`).
  - **Internationalisation & Support Complet Loco Translate (`languages/`)** :
    - Textes rédigés en anglais par défaut avec text-domain `woo-get-data-for-ai`.
    - Mise à jour du fichier source `woo-get-data-for-ai.pot`.
    - Traduction française complète dans `woo-get-data-for-ai-fr_FR.po` et compilation du binaire `woo-get-data-for-ai-fr_FR.mo` pour une prise en charge native immédiate sous Loco Translate.

### v1.17.1 (2026-09-06)
- **Harmonisation du Mega-Prompt IA & de la Documentation Interne (`tab-ai-prompt.php`, `tab-docs.php`, `class-playbooks.php`)** :
  - **Alignement sur les 7 Grands Piliers MECE** : Mise à jour intégrale des étapes de diagnostic (Phase 1) et des commandes rapides cURL dans le Mega-Prompt affiché en onglet d'administration ("AI Mega-Prompt & Skill") pour référencer exactement les 7 Piliers Stratégiques avec leurs identifiants canoniques.
  - **Uniformisation Linguistique 100% Anglais** : Traduction intégrale en anglais de la Phase 4 ("Strict Active vs Inactive State Management") qui contenait encore des paragraphes en français, garantissant une cohérence textuelle absolue pour les contextes des LLMs anglophones et multilingues.
  - **Enrichissement de la Checklist "Live Freshness Check" (Phase 2)** : Intégration des points d'inspection modernes récemment ajoutés : profiler de performance frontend & Core Web Vitals natifs (`/performance/profile`), bloat et options orphelines BDD (`/system/database`), KPIs de ventes et valorisation stock (`/woocommerce/analytics/sales`, `/woocommerce/analytics/stock`), logistique & Flexible Shipping (`/woocommerce/shipping`), diagnostic SMTP (`/system/mail`), et détection de code drift / export ZIP (`/code/checksums`, `/code/zip`).
  - **Mise à Jour de l'Onglet Documentation (`tab-docs.php`)** : Harmonisation du compteur et du descriptif des playbooks (passage de 12 à 7 Piliers MECE).
  - **Traduction des Commentaires cURL dans le Générateur de Skill (`class-playbooks.php`)** : Passage de `# Pilier X` à `# Pillar X` pour une homogénéité totale en anglais dans `SKILL.md`.

### v1.17.0 (2026-09-06)
- **Consolidation Stratégique des Playbooks en 7 Grands Piliers MECE (`Playbooks`, `includes/class-playbooks.php`)** :
  - **Refonte Architecturale Anti-Dispersion** : Fusion et rationalisation des 12 playbooks historiques en 7 Piliers Stratégiques MECE (Mutually Exclusive, Collectively Exhaustive) afin d'éliminer les collisions sémantiques de triggers et de réduire la dilution cognitive des LLMs de ~45% de tokens dans le `SKILL.md` généré.
  - **Préservation Intégrale de Couverture (100%)** : Aucune étape, aucun endpoint et aucun signal diagnostic n'est perdu.
    - *Pilier 1* : `seo_content_audit` (SEO, Contenus & Hiérarchie Gutenberg).
    - *Pilier 2* : `agency_performance_audit` (Performance Frontend, TTFB & Core Web Vitals Natifs).
    - *Pilier 3* : `database_system_hygiene` (Santé Système, BDD Bloat & Hygiène Background - *Fusion Ex-P2 & Ex-P12*).
    - *Pilier 4* : `order_checkout_troubleshoot` (Dépannage Commandes, Passerelles, SMTP & Webhooks - *Fusion Ex-P3 & Ex-P8*).
    - *Pilier 5* : `ecommerce_bi_analytics` (Business Intelligence, Ventes 360° & CRO - *Fusion Ex-P4 & Ex-P7*).
    - *Pilier 6* : `shipping_logistics_audit` (Logistique, Expédition & Règles Flexible Shipping).
    - *Pilier 7* : `code_theme_integrations` (Architecture Code, Thème, Hooks, Snippets & Automations - *Fusion Ex-P5, Ex-P6 & Ex-P9*).
- **Gouvernance Architecturale & Règle Anti-Prolifération (`AGENTS.md`, `PROJECT_CONTEXT.md`)** :
  - Établissement de la directive stricte "The MECE Master Pillars Principle" : interdiction de créer des micro-playbooks ad-hoc. Toute nouvelle capacité ou tout nouvel endpoint doit obligatoirement s'intégrer dans l'un des 7 piliers existants.
- **Mise à Jour de la Documentation & des Exemples cURL Quickstart** :
  - Mise à jour des exemples cURL dans `generate_skill_markdown()`, de la table des capacités dans `README.md` et de la documentation de contexte.

### v1.16.0 (2026-09-06)
- **Détection Intelligente du Bloat BDD, Options Orphelines & Santé Commandes (`System_Controller`, `Performance_Controller`, `Woocommerce_Controller`, `Scheduler_Controller`)** :
  - **Détection des Options Autoload Orphelines (`GET /system/database` & `GET /performance/autoload`)** :
    - Analyse automatique et qualification de chaque option volumineuse via `analyze_orphaned_option()` : croisement des préfixes avec un dictionnaire d'extensions connues (`wpassetcleanup_`, `elementor_`, `wpcode_`, `woocommerce_`, `rank_math_`, `flw_`, `wp_rocket_`, etc.) et avec les plugins installés/actifs.
    - Exposition structurée de `is_orphaned_candidate` (booléen), `related_plugin_status` (`active`, `inactive`, `uninstalled`, `unknown`), `related_plugin_name` et `hint` de remédiation (snippet de désactivation autoload ou suppression).
  - **Analyse de Santé des Commandes & Commandes Abandonnées (`GET /woocommerce/summary`)** :
    - Calcul des ratios de statut (`cancelled_ratio_percent`, `failed_ratio_percent`) et déclenchement d'alerte (`alert_high_cancellations`) si le volume d'annulations dépasse 30% et 500 commandes.
    - Estimation chiffrée via requête indexée légère des commandes annulées non payées datant de plus d'un an (`cancelled_unpaid_older_than_1y_estimate`), avec prise en charge transparente du mode HPOS (`wc_orders`) et du mode classique CPT (`wp_posts`).
  - **Exposition de la Politique de Rétention Action Scheduler (`GET /action-scheduler` & `GET /system`)** :
    - Exposition directe de la durée de rétention active en jours (`retention_period_days`), du batch size de nettoyage (`cleanup_batch_size`), du statut par défaut (`is_default`), et d'une alerte d'encombrement (`alert_bloat`) en cas de rétention 30 jours par défaut avec plus de 25 000 actions complétées accumulées.
- **Nouveau Playbook Procédural n°12 (`database_bloat_hygiene_audit`)** :
  - Protocole d'audit complet en 4 étapes pour agents IA et agences : diagnostic BDD et options orphelines (`/system/database`), ratios et commandes stagnantes (`/woocommerce/summary`), files d'attente et rétention Action Scheduler (`/action-scheduler`), et crons bloqués (`/crons?status=overdue`).
  - Intégration et génération automatique dans le skill IA dynamique (`GET /capabilities?format=skill`).
- **Mise à Jour du Client CLI (`cli/sync.js`)** :
  - `pull:woocommerce` intègre l'analyse d'hygiène des commandes dans `summary.md`.
  - `pull:scheduler` intègre la politique de rétention et l'alerte d'encombrement dans `action-scheduler-summary.md`.

### v1.15.0 (2026-09-06)
- **Audit Web Vitals & Signaux Frontend 100% Natifs & Zéro Dépendance Externe (`Performance_Controller`)** :
  - **Suppression Complète de l'Endpoint Proxy Google PageSpeed (`GET /performance/pagespeed`)** :
    - Élimination intégrale du proxy Google PageSpeed Insights afin de supprimer les blocages de quota public Google (`429 Quota Exceeded`), supprimer l'obligation de configurer une clé d'API (`GOOGLE_PAGESPEED_API_KEY`) et éliminer 15-20s de latence réseau externe bloquante pour le serveur.
    - Conforme à la directive absolue **Zéro usine à gaz** : 100% PHP/WordPress natif, zéro dépendance externe, zéro clé d'API, temps de réponse sous les 100ms.
  - **Enrichissement des Audits Frontend Natifs dans `GET /performance/profile`** :
    - *Audit des formats d'images* (`image_formats`) : Décompte des images aux extensions historiques (`.png`, `.jpg`, `.jpeg`) vs formats modernes (`.webp`, `.avif`, `.svg`), extraction d'échantillons d'URLs et recommandation chiffrée de conversion WebP/AVIF (30% à 70% d'allègement réseau).
    - *Audit Google Fonts & FOIT* (`google_fonts`) : Détection des polices Google Fonts externes dans `<head>` et contrôle de la présence du paramètre `display=swap` pour éliminer le risque de Flash of Invisible Text sur mobile pénalisant le LCP.
    - *Détection des scripts superflus du coeur WP sur le Frontend* (`core_bloat`) : Détection de `wp-emoji`, `wp-embed`, `jquery-migrate`, et `dashicons` (visiteurs non connectés) avec snippets de désenqueuement WPCode prêts à l'emploi.
    - *Empreinte DOM Elementor* (`dom_health.elementor_nodes_count`) : Décompte précis des conteneurs et widgets Elementor avec calcul du pourcentage de l'arborescence HTML globale pour isoler le surpoids de constructeur de page.
- **Playbooks Procéduraux Synchronisés (`Playbooks`)** :
  - `agency_performance_audit` (Playbook 11) : Étape 3 mise à jour pour s'appuyer sur l'audit 100% natif Web Vitals de `/performance/profile` au lieu de l'API externe Google PageSpeed.
- **Client CLI Local (`cli/sync.js`)** :
  - `pullPerformance()` : Suppression de l'appel `/performance/pagespeed` et génération de `performance-report.md` enrichie des signaux natifs Core Web Vitals (formats d'images, Google Fonts swap, bloat du coeur WP).

### v1.14.0 (2026-09-06)
- **Support Flexible Shipping Table Rate sur Flat Rate & Métadonnées Commandes (`Woocommerce_Controller`)** :
  - **Extraction Flexible Shipping PRO Table Rate sur les méthodes `flat_rate`** :
    - Détection et extraction automatique des champs d'instance `fs_calculation_enabled` et `fs_method_rules` pour les méthodes de livraison Forfait (`flat_rate`), complétée par un fallback direct dans `wp_options` (`woocommerce_flat_rate_<instance_id>_settings`).
    - Exposition structurée dans `$data['flat_rate_settings']['fs_calculation_enabled']` et `$data['flat_rate_settings']['fs_method_rules']`.
    - Exposition de `$data['flexible_shipping_table_rate']` à la racine de la méthode contenant le flag `enabled`, `is_pro`, `rules_count`, et l'ensemble des règles décodées (`rules`).
    - Exposition de `$data['raw_instance_settings']` (options d'instance sanitaires avec exclusion des clés/mots de passe) pour garantir qu'aucune option tierce personnalisée ne soit perdue.
  - **Exposition des Métadonnées Lignes de Livraison Commandes (`GET /woocommerce/order/{id}`)** :
    - Ajout de `meta_data` sur chaque élément de `$shipping_lines` (`\WC_Order_Item_Shipping`).
    - Auto-décodage JSON transparent des valeurs sérialisées/JSON telles que `fs_costs` (`{"base":...,"additional":...}`) pour une analyse immédiate par l'IA sans étape de parsing supplémentaire.
  - **Amélioration du Helper `parse_flexible_shipping_rules()`** :
    - Méthode unifiée protégée pour parser et formater les règles Flexible Shipping qu'elles soient stockées en tableau ou en chaîne JSON.
- **Playbooks Procéduraux Synchronisés (`Playbooks`)** :
  - `shipping_logistics_audit` (Playbook 10) enrichi avec les règles Table Rate sur `flat_rate`, les options d'instance brutes, et l'inspection des métadonnées de livraison des commandes (`GET /woocommerce/order/{id}`).
  - `ecommerce_troubleshoot` (Playbook 3) mis à jour pour auditer les frais de livraison via `shipping_lines[].meta_data.fs_costs`.
- **Client CLI Local (`cli/sync.js`)** :
  - `pullWooCommerce()` enrichi pour détecter `m.flexible_shipping || m.flexible_shipping_table_rate` et afficher les règles de matrice Flexible Shipping PRO directement dans le rapport Markdown `summary.md`.

### v1.13.0 (2026-09-06)
- **Profilage Multi-Templates & Diagnostic Avancé Google PageSpeed / Core Web Vitals (`Performance_Controller`)** :
  - **Résolution Automatique Multi-Templates (`GET /performance/templates-urls`)** :
    - Découvre et résout en 1 appel les URLs représentatives des 5 archétypes e-commerce majeurs : Accueil (`/`), Boutique (`wc_get_page_id('shop')`), Catégorie de produit (`get_terms('product_cat')`), Fiche Produit (`wc_get_products()`), Panier (`wc_get_cart_url()`) et Tunnel de commande (`wc_get_checkout_url()`) avec fallbacks intelligents pour sites non-WooCommerce.
  - **Audits Serveur Natifs Type PageSpeed dans `GET /performance/profile` (100% PHP, 0ms latence externe)** :
    - *Taille et profondeur du DOM* : Décompte précis des éléments HTML du template (seuil alerte >800, critique >1400 noeuds).
    - *Images sans dimensions* : Détection des balises `<img>` dépourvues d'attributs `width`/`height` explicites (cause racine n°1 de CLS / Cumulative Layout Shift).
    - *Ressources bloquant le rendu* : Inventaire des CSS et JS insérés dans le `<head>` sans `defer` ni `async`.
    - *Détection `wc-cart-fragments.js`* : Alerte immédiate sur la présence du script d'actualisation de panier WooCommerce sur des pages hors panier/commande (requête POST AJAX systématique non cachable).
    - *Compression serveur* : Détection de l'activation de Gzip ou Brotli.
  - **Proxy Google PageSpeed Insights Officiel (`GET /performance/pagespeed`)** :
    - Interroge l'API publique Google PageSpeed Insights (`https://www.googleapis.com/pagespeedonline/v5/runPagespeed`) pour la stratégie spécifiée (`strategy=mobile|desktop`).
    - Extrait le score global Lighthouse (0-100), les Core Web Vitals de terrain (LCP, CLS, FCP, TBT, Speed Index) et les opportunités d'économies d'octets.
    - Mise en cache de 1 heure via Transient WordPress (`wpab_psi_*`) pour préserver les quotas et accélérer les audits répétés.
- **Enrichissement Majeur du Playbook Agence V2 (`agency_performance_audit`)** :
  - Restructuration en 6 phases complètes : Découverte des 5 templates, profilage comparatif multi-templates, audit PageSpeed & Core Web Vitals, fuites d'autoload & transients, santé Action Scheduler / Crons, et livrable exécutif.
  - Cadre de prescription avec calcul du *Business Impact Score* (temps de chargement gaspillé en ms, poids réseau éliminable, estimation des gains de conversion), matrice d'actions Quick Wins vs Structurel, et snippets WPCode prêts à coller (désenqueuement conditionnel de fragments, d'emojis, et d'assets de constructeurs).
- **Mise à Jour du Client CLI (`cli/sync.js`)** :
  - `pull:performance` télécharge désormais `templates-urls.json`, `profile-home.json` et `pagespeed-mobile.json` en plus de `autoload.json` et `plugins-summary.json`, et produit un rapport Markdown consolidé de niveau exécutif.

### v1.12.0 (2026-09-06)
- **Module Performance & Profiler de Plugins (`Performance_Controller`)** :
  - **Nouveau Contrôleur REST (`class-performance-controller.php`)** sous le namespace `agent-bridge/v1/performance/` (100% lecture seule, zéro impact visiteur).
  - `GET /performance/profile` : Profilage d'URL chirurgical à la demande (`path=/`, `/boutique/`, `/panier/`).
    - Mesure du TTFB réel et du pic de mémoire PHP (`memory_peak_mb`).
    - Attribution des requêtes SQL et de la durée d'exécution (en millisecondes) à chaque plugin / thème / coeur via l'analyse de la pile d'appel PHP (`$wpdb->queries` et backtraces).
    - Détection automatique des requêtes SQL dupliquées et de leur temps gaspillé.
    - Détection des requêtes lentes dépassant le seuil paramétrable (`slow_query_threshold_ms`, défaut: 50ms).
    - Inventaire de l'empreinte Frontend : nombre de scripts JS et de feuilles CSS enqueués par chaque plugin sur la page testée.
    - Détection transparente de l'extension **Query Monitor** si présente.
  - `GET /performance/autoload` : Audit approfondi de la table `wp_options` (`alloptions`) :
    - Calcul du volume total d'autoload contre le seuil recommandé par WordPress (800 Ko).
    - Classement du Top 25 des options les plus volumineuses.
    - Répartition et agrégation de la taille d'autoload par préfixe de composant (WooCommerce, Elementor, Rank Math, Yoast, Action Scheduler, Transients, etc.).
  - `GET /performance/plugins-summary` : Synthèse consolidée de l'empreinte de chaque plugin actif :
    - Nombre de tables en base de données, taille totale sur disque (données + index), et volume de lignes.
- **Nouveau Playbook Procédural n°11 (`agency_performance_audit`)** :
  - Protocole d'audit complet en 6 étapes pour agences et agents IA : diagnostic serveur, profilage de page à la demande, fuites d'autoload, empreinte BDD par plugin, files d'attente d'arrière-plan (Action Scheduler), et journal Crash Watch.
- **Enregistrement Permissions & Catalogue `/capabilities`** :
  - Nouveau module `'performance'` activé par défaut dans la matrice des permissions (`tab-permissions.php`).
  - Découverte dynamique complète et génération automatique dans `SKILL.md`.
- **Client CLI Local (`cli/sync.js`)** :
  - Nouvelle commande `pull:performance` générant `./synced-site-data/performance/performance-report.md`, `autoload.json` et `plugins-summary.json`. Intégrée dans `pull:all`.

### v1.11.0 (2026-09-06)
- **Logistique Avancée WooCommerce & Règles Flexible Shipping PRO (`Woocommerce_Controller`)** :
  - **Enrichissement des Zones de Livraison** : Extraction des emplacements géographiques (`locations` : codes postaux, états/régions, pays, continents) via `get_zone_locations()`.
  - **Options d'Instance Natives Enrichies** : Extraction des réglages pour `flat_rate` (`calculation_type`, `cost`), `free_shipping` (`requires`, `min_amount`, `ignore_discounts`), et `local_pickup` (`cost`, `tax_status`).
  - **Détection & Décodage Flexible Shipping / Flexible Shipping PRO (Octolize / WPDesk)** :
    - Détection de la version PRO via constantes/classes (`FLEXIBLE_SHIPPING_PRO_VERSION`, `WPDesk_Flexible_Shipping_Pro_Plugin`).
    - Décodage complet de la matrice de règles conditionnelles (`method_rules`) : tranches de poids, montants de panier, articles, classes de livraison, conditions complexes, coûts par commande, coûts additionnels, et actions spéciales (`none`, `stop`, `cancel`).
    - Extraction des seuils de gratuité (`method_free_shipping`), labels, visibilité et descriptions.
  - **Nouvel Endpoint Dédié `GET /woocommerce/shipping`** : Permet l'inspection logistique ciblée (`zones_count`, `zones`) sans recharger l'ensemble des réglages généraux de la boutique.
  - **Rétrocompatibilité Totale** : Conservation stricte des 5 champs existants (`instance_id`, `id`, `title`, `enabled`, `cost`) au sein de `GET /woocommerce/settings`.
- **Nouveau Playbook IA Dédié (`Playbooks`)** :
  - `shipping_logistics_audit` (Playbook 10) : Protocole d'audit logistique automatisé combinant `/woocommerce/shipping`, `/woocommerce/settings` et `/woocommerce/orders`.
- **Enrichissement Client CLI (`cli/sync.js`)** :
  - `pull:woocommerce` télécharge désormais `/woocommerce/shipping` dans `./synced-site-data/woocommerce/shipping.json` et génère un tableau détaillé des zones et méthodes dans `summary.md`.

### v1.10.0 (2026-09-06)
- **Module Code Avancé : Détection de Dérive (Code Drift) & Export ZIP à la Volée (`Code_Controller`)** :
  - `GET /code/checksums` : Calcul instantané des empreintes numériques (MD5 / SHA256), des tailles en octets et des dates de modification de tous les fichiers d'une extension ou d'un thème (`wp-content/plugins/`, `wp-content/themes/`). Permet à l'IA de vérifier la dérive de code locale vs serveur avant toute intervention locale en 1 seule requête au lieu de dizaines de requêtes individuelles.
  - `GET /code/zip` : Génération et streaming à la volée d'une archive ZIP compressée propre d'un plugin personnalisé ou du thème enfant (`format=stream` par défaut ou `format=base64`). Nettoyage automatique immédiat du fichier temporaire sur le serveur.
- **Garde-fous de Sécurité & Confinement Strict** :
  - Confinement de chemin impératif par `realpath()` à l'intérieur de `WP_PLUGIN_DIR`, `WPMU_PLUGIN_DIR` et `get_theme_root()`. Interdiction formelle de la racine `ABSPATH`, de `wp-config.php`, de `wp-content/uploads/` et des traversées de dossiers (`..`).
  - Exclusions automatiques : filtrage systématique de `.git`, `.svn`, `.env*`, `.DS_Store`, dumps `.sql`, archives `.zip`, `.tar`, `.gz`, fichiers de logs `.log`, `node_modules`, `vendor`, `cache`.
  - Protection DoS/OOM : limitation de sécurité à 1 000 fichiers maximum et 30 Mo maximum décompressés par répertoire inspecté.
- **Playbook IA Dédié & Découverte (`Playbooks` & `System_Controller`)** :
  - **Nouveau Playbook 9** : `code_sync_drift_audit` avec triggers d'intention explicites (`"comparer code local prod"`, `"verifier derive code"`, `"code drift"`, `"telecharger plugin"`, `"recuperer theme enfant"`, `"synchroniser extension"`, etc.).
  - Mise à jour des `discovery_instructions` dans `/capabilities` et intégration dans la génération automatique du skill d'agent (`SKILL.md`).
- **Client CLI Local (`cli/sync.js`)** :
  - Nouvelles commandes `pull:code` et `pull:checksums` avec argument `--path=<chemin_relatif>` pour générer localement l'arborescence et le rapport d'empreintes d'intégrité.

### v1.9.1 (2026-09-06)
- **Correction Critique Autoloader & Dézippage Linux (`Class "System_Controller" not found`)** :
  - **Résolution de l'incompatibilité de décompression Linux** : Remplacement impératif de PowerShell `Compress-Archive` (qui générait des séparateurs Windows `\` dans les archives ZIP) par l'outil natif universel `tar -a -cf` (garantissant des séparateurs POSIX `/`). Les serveurs Linux (conforama.re / Apache / Nginx / LiteSpeed) décompressent désormais l'arborescence complète des sous-dossiers (`includes/api/`) sans aplatissement de fichiers.
  - **Mécanisme d'Auto-Réparation (Self-Healing)** au démarrage du plugin : Détection automatique au boot (sur environnements non-Windows) des fichiers résiduels extraits avec des antislashs `\` dans leur nom de fichier, et réorganisation automatique dans leurs sous-dossiers réels.
  - **Autoloader Résilient & Fallbacks Multi-Niveaux** : Prise en charge des chemins alternatifs avec rétrocompatibilité antislash si un ancien dézippage aplati est présent sur le serveur. Préchargement explicite du contrôleur de base `Rest_Controller`.
  - **Isolation & Robustesse des Contrôleurs REST (`Plugin::register_rest_routes`)** : Instanciation conditionnelle sécurisée avec `class_exists()` et frontière d'erreur `try / catch (\Throwable $e)`. L'absence éventuelle d'un contrôleur n'entraîne plus d'erreur fatale bloquante pour WordPress et ne perturbe plus les autres endpoints REST du site (ex: `/wp-json/iawp/search` déclenché lors de la navigation visiteur).

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

