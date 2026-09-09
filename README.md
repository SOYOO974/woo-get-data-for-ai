# WP Agent Bridge (Woo & WP Data for AI)

[![License: GPL-2.0](https://img.shields.io/badge/License-GPL%202.0-blue.svg)](http://www.gnu.org/licenses/gpl-2.0.txt)
[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-21759b.svg)](https://wordpress.org)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net)
[![GitHub Updates](https://img.shields.io/badge/Updates-PUC%20v5.6-success.svg)](https://github.com/SOYOO974/woo-get-data-for-ai)

**WP Agent Bridge** is an enterprise-grade, lightweight, and ultra-secure WordPress and WooCommerce inspection plugin. It exposes a protected, read-only REST API (`agent-bridge/v1/`) designed specifically for AI coding assistants (**Antigravity**, **Claude**, **Cursor**, **ChatGPT**) and developer tools.

It allows AI assistants to instantly inspect live site configurations, debug logs, Elementor trees, WPCode & Code Snippets, and theme settings (Woodmart, Elessi) safely without requiring full SSH, SFTP, or database access.

---

## 🌟 Key Capabilities

- **100% Read-Only Security**: All endpoints strictly enforce HTTP `GET` (`WP_REST_Server::READABLE`). No remote write or database modification primitives exist.
- **Zero-Bloat & Ultra-Lean Architecture**: Built with a strict anti-bloat philosophy ("anti-usine à gaz"). Zero idle background overhead, zero heavy background crons, memory-safe streaming queries, lean isolated controllers, and minimal server footprint on live production stores.
- **Zero-Secret Public Codebase**: Designed for public hosting on GitHub. Tokens are generated on-demand inside WordPress admin, never stored in plugin files.
- **Dynamic AI Discovery & Playbooks (`/capabilities`)**: Self-describing schema and procedural diagnostic Playbooks allowing AI assistants to discover available modules, active permissions, query parameters, and step-by-step audit recipes.
- **Auto-Updating Skill Generator (`?format=skill`)**: When queried with `?format=skill`, the API dynamically generates a complete, ready-to-save `SKILL.md` markdown file for Antigravity, Cursor, and Claude agents.
- **2-Click AI Onboarding & Smart First-Time Banners**: Prominent, non-intrusive onboarding banners in WP Admin and inside the plugin settings dashboard. Active until the first successful AI connection is recorded, reassuring users with 100% read-only safety, offering 1-click prompt copying, and suggesting high-value performance audit prompts.
- **Granular Permissions Matrix**: Toggle access to specific modules (System, Themes, Code, Elementor, Snippets, Logs, FlowMattic, Analytics) via checkboxes in the admin panel.
- **Deep WooCommerce Diagnostics**: Audits template overrides in child/parent themes, detects outdated templates, and inspects HPOS status, HPOS datastore caching, deferred transactional emails, checkout rate limiting, and full-text search indexes.
- **Theme Settings Export**: Deep inspection and decoding of **Woodmart** (`xts-woodmart-options`), **Elessi** (`elessi_options` / Redux), and child theme `functions.php` / `style.css`.
- **Sandboxed Code Inspector**: Safely inspects file trees of active plugins and `mu-plugins`, and reads specific PHP/JS/CSS files with strict `realpath` validation.
- **High-Performance Log Streaming**: Memory-safe reverse file tailing (`fseek`) for `debug.log` and `uploads/wc-logs/*.log` preventing PHP memory exhaustion on heavy production sites.
- **Automatic Data Redaction**: Real-time regex engine that masks Stripe secret keys (`sk_live_*`), API tokens, passwords, database credentials, and customer email addresses before output.
- **FlowMattic Automations**: Bulk and targeted export of all workflows, triggers, actions, and execution task stats in native importable JSON format.
- **Independent Analytics Intelligence**: Complete visibility over site traffic, unique visitors, pageviews, acquisition channels, UTM campaigns, device breakdowns, and WooCommerce **conversion rates**, net sales, and AOV.
- **Custom Fields & ACF Meta**: Complete discovery of meta fields registered in code (`register_post_meta`), Advanced Custom Fields (ACF) field groups, recursive subfields (repeaters, flexible content), location rules, options pages, and single post metadata inspection.
- **WooCommerce Store, Orders & Coupons Data**: Read-only access to products, stock status, promotional discount coupons (`/woocommerce/coupons`, `/woocommerce/coupon/{id}`) with real-time validity, held checkout sessions (`_coupon_held_keys`), and recent orders with **strict GDPR/PII anonymization** (masked names, redacted emails/phones/addresses), coupon filtering (`?coupon=`), injected coupon lines, and gateway error diagnostics via order notes.
- **Native WooCommerce Sales & Stock Intelligence**: 100% native commercial reporting without external tracking plugins: gross/net sales, paid orders, AOV, refunds, % growth vs prior period, top products and coupons, and stock valuation & dormant inventory.
- **SMTP & Transactional Email Diagnostics**: Provider detection (**FluentSMTP**, **WP Mail SMTP**, **Post SMTP**, **Easy WP SMTP**), credentials sanitization, recent delivery failures, and PHP `mail()` unauthenticated spam risk alert.
- **Security Hardening & Protection Audit**: Audit of constants (`DISALLOW_FILE_EDIT`, `WP_DEBUG_DISPLAY`), XML-RPC exposure, SSL enforcement, DB prefix, detected security and caching plugins.
- **Database Health, Autoload & Orphaned Options**: Audit of SQL table sizes, top heavy tables, transient accumulations, `wp_options` autoload footprint (> 800 KB threshold), and intelligent detection of orphaned autoloaded options left behind by inactive or uninstalled plugins.
- **Crash Watch Fatal Error Dashboard**: Targeted reverse-tail extraction of recent critical PHP fatal errors and exceptions with component attribution for instant bug diagnostics.
- **WordPress Pages, Content, Unified SEO & Redirections**: Complete inspection of WordPress pages hierarchy, raw and rendered Gutenberg block trees, detected shortcodes, templates, and **unified SEO metadata** normalized across **Rank Math**, **Yoast SEO**, **The SEO Framework (TSF)**, **SEOPress**, and **All in One SEO**, alongside global SEO plugin settings & optimization health audits (`/content/seo/settings`), 301/302/410 URL redirect rules, and 404 error log monitoring (**Redirection** by John Godley, **Rank Math Redirections**, **301 Redirects**) with site-wide audit capabilities across pages, blog posts, WooCommerce products, and categories.
- **Code Drift Fingerprinting & Instant ZIP Export**: Instant cryptographic checksums (`/code/checksums`) for local vs remote code drift detection, and on-the-fly clean ZIP archive downloads (`/code/zip`) with zero `.git` or log clutter.
- **Advanced WooCommerce Shipping & Flexible Shipping PRO**: Deep logistics extraction across zones, geographic locations (postcodes, regions, countries), native method options (`flat_rate`, `free_shipping`, `local_pickup`), and matrix calculation rules for **Flexible Shipping** and **Flexible Shipping PRO** (including Table Rate integration extending `flat_rate` methods and order shipping lines `fs_costs` metadata) (`/woocommerce/shipping`, `/woocommerce/order/{id}`).
- **Multi-Template Profiler & Plugin Performance Attribution**: Discovers 5 key e-commerce page archetypes (`/performance/templates-urls`), performs surgical on-demand profiling (`/performance/profile`) measuring TTFB, peak memory, attributing SQL queries and execution duration per plugin via stack backtraces, detecting duplicate/slow queries (>50ms), and auditing enqueued JS/CSS assets per plugin.
- **100% Native Server-Side Core Web Vitals & Frontend Diagnostics**: Zero external API dependencies, zero quota limits, and sub-100ms response time. Audits DOM size/depth and Elementor node footprint, missing image dimensions (CLS root cause), legacy image formats (.png/.jpg vs modern WebP/AVIF), external Google Fonts display=swap check, WordPress core frontend bloat scripts (emojis, embeds, migrate, dashicons), render-blocking resources, and WooCommerce cart fragments directly inside `/performance/profile`.
- **Autoload Bloat & Plugin Resource Footprint**: Deep audit of `wp_options` (`alloptions`) against the 800 KB threshold (`/performance/autoload`) grouped by plugin prefix, and consolidated database table size & row volume per active plugin (`/performance/plugins-summary`).
- **Paid Memberships Pro & MasterStudy LMS Diagnostics**: Dedicated read-only inspection for e-learning and membership sites: audits membership levels and recurring billing cycles (`/pmpro/levels`) with duration anomaly detection (e.g. 11 months vs 12 months), member records (`/pmpro/members`, `/pmpro/member/{user_id}`), MasterStudy LMS course catalog (`/masterstudy/courses`), and deep user course enrollment audits (`/masterstudy/user/{user_id}/courses`) cross-referencing PMPro subscription IDs to identify access expiration root causes or pointer desync.
- **7 MECE Master Procedural Playbooks**: Streamlined, agency-grade investigation workflows eliminating LLM prompt dilution and trigger collisions: SEO & Content, Agency Multi-Template Performance & Core Web Vitals, System Health & Database Bloat Hygiene, Orders & Gateway Troubleshooting, 360° E-Commerce Sales & Analytics, Shipping Logistics & Flexible Rules, and Code Architecture & Automations Map.
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
| `GET /system?plugins={active\|inactive\|all}` | Server limits (PHP, RAM, execution time), WP core info, active plugins list & summary breakdown (total, active, inactive, mu-plugins; default: `active`), HPOS state, Action Scheduler queue, and retention policy. |
| `GET /system/database` | In-depth database diagnostic: table sizes, top 15 largest tables, autoload footprint analysis with 800KB alert threshold, orphaned options analysis from inactive plugins, transient counts, and object cache status. |
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
| `GET /snippets?status={active\|inactive\|all}&source={all\|code-snippets\|wpcode}&type={all\|php\|css\|js\|html}` | Custom PHP, JS, CSS, and HTML snippets across WPCode and Code Snippets (Free & Pro) with location, priority, tags, direct admin edit URLs, and global active/inactive counts (default: `active`). |
| `GET /snippets/{id}?source={all\|code-snippets\|wpcode}` | Source code, execution location, priority, tags, and admin edit URL of a specific snippet with collision resolution. |
| `GET /wpcode/snippets` | Legacy alias: Listing of custom snippets across WPCode and Code Snippets (default: `active`). |
| `GET /wpcode/snippet/{id}` | Legacy alias: Source code and metadata of a specific snippet. |
| `GET /logs/sources` | Available log files (`debug.log`, `uploads/wc-logs/*.log`, custom `wp-content/` logs) with file sizes and dates. |
| `GET /logs/view?source={file}&lines=200` | Memory-safe tail extraction of the latest log lines. |
| `GET /logs/custom?file={filename}&lines=200` | Tail inspection of specific custom logs in `wp-content/` (e.g. `komela-order-status-sync.log`). |
| `GET /logs/errors-summary?limit=15` | Crash Watch: aggregated and deduplicated recent fatal PHP errors and exceptions from `debug.log` and `wc-logs` with component attribution. |
| `GET /crons` | WP-Cron registered jobs, next execution timestamps (GMT & local), recurrence intervals, overdue tasks, and hook arguments. |
| `GET /action-scheduler` | Action Scheduler queue (in-progress, failed, pending), hook, group, attempts, arguments, retention policy in days, bloat alert, and error logs from `actionscheduler_logs`. |
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
| `GET /woocommerce/summary` | High-level store health, product counts by status/stock/type, order counts and hygiene analysis (cancellation ratio, stale unpaid orders > 1y), HPOS state, performance features audit (HPOS datastore caching, deferred transactional emails, checkout rate limiting, HPOS full-text search indexes), active payment gateways, and shipping zones. |
| `GET /woocommerce/products?status={publish\|draft\|all}` | Paginated WooCommerce product catalog with SKU, prices, stock, categories, tags, attributes, and variations. |
| `GET /woocommerce/product/{id}` | Detailed product inspection including variations breakdown, dimensions, images, unified SEO object, and sanitized postmeta custom fields. |
| `GET /woocommerce/coupons?status={active|expired|exhausted|all}&type={all|fixed_cart|percent|fixed_product}` | List and filter promotional discount coupons with status (`active`, `expired`, `exhausted`, `all`), discount types, usage counts, limits, held counts, and PII-masked email restrictions (`?status=`, `?type=`, `?search=`, `?email=`, `?per_page=20`, `?page=1`, `?orderby=date|code|usage_count|modified`, `?order=DESC|ASC`). |
| `GET /woocommerce/coupon/{id}` | Deep inspection of a single coupon by numeric ID or code slug: discount rules, real-time availability (`is_valid_now`, `usage_left`), active held checkout sessions (`_coupon_held_keys`), and last 10 associated orders. |
| `GET /woocommerce/orders?status={status}` | Recent orders with strict GDPR/PII anonymization (masked customer details, redacted emails/phones/addresses), item lines, coupon lines, applied coupon codes (`coupon_codes`), totals, and gateways (`?status=processing|completed|failed|all`, `?search=`, `?customer_id=`, `?coupon=`, `?per_page=10`). |
| `GET /woocommerce/order/{id}` | Deep order diagnostics: item line metadata, shipping lines with decoded metadata (`shipping_lines[].meta_data` including Flexible Shipping `fs_costs` base & additional costs), fees, coupon lines, refunds, order notes (payment gateway responses), and sanitized metadata. |
| `GET /woocommerce/settings` | Store configuration: currency, tax settings, stock management, active payment gateways (secrets redacted), shipping zones/methods with locations, flat_rate table rate rules, and Flexible Shipping matrix rules. |
| `GET /woocommerce/shipping` | Dedicated logistics inspection: shipping zones, geo-locations (postcodes, regions, countries), native method parameters, flat_rate table rate rules (`flexible_shipping_table_rate`), Flexible Shipping & Flexible Shipping PRO matrix calculation rules (weight/price tiers, shipping classes), and sanitized `raw_instance_settings`. |
| `GET /woocommerce/analytics/sales?range={range}` | 100% native WooCommerce sales report: net sales, gross sales, orders count, AOV, refunds, daily trend, and growth percentage compared to previous period. |
| `GET /woocommerce/analytics/top-performers?limit=10` | Top products by net revenue & volume sold, and top coupons with discount totals. |
| `GET /woocommerce/analytics/stock` | Stock financial valuation, low stock alerts, and dormant stock (0 sales in last 90 days). |
| `GET /woocommerce/webhooks` | WooCommerce webhooks inventory, delivery URLs, topics, and failure counters (`failure_count >= 5`). |
| `GET /content/pages?status={status}` | Paginated WordPress pages list with hierarchy, slug, status, template PHP, editor type (Gutenberg/Classic/Elementor), special page flags, and quick SEO preview. |
| `GET /content/page/{id}` | Deep page inspection: raw/rendered content, Gutenberg blocks summary, detected shortcodes, word count, parent/child hierarchy, and unified normalized SEO metadata. |
| `GET /content/posts?status={status}` | Paginated blog posts list with categories, tags, author, editor type, and quick SEO preview. |
| `GET /content/post/{id}` | Deep post or custom post type inspection: raw/rendered content, blocks, taxonomies, sanitized postmeta, and full unified SEO object. |
| `GET /content/seo-audit` | Site-wide SEO audit report across pages, posts, WooCommerce products, and categories: missing meta descriptions, title issues, noindex warnings on published products/checkout, thin content, category descriptions, global settings audit, and redirection status summary (Rank Math, Yoast SEO, The SEO Framework, SEOPress, AIOSEO) (`?include_posts=true\|false`, `?include_products=true\|false`, `?include_categories=true\|false`). |
| `GET /content/seo/settings` | Audits global settings and configuration best practices of the active SEO plugin (Rank Math, Yoast SEO, The SEO Framework) and Redirection plugin: attachment redirects, category base stripping, tag/product_tag noindex, default OpenGraph image, schema entity & logo, active modules, and 404 log retention. |
| `GET /content/redirections` | List, filter, and paginate configured URL redirection rules (301, 302, 307, 410) across **Redirection** (John Godley), **Rank Math Redirections**, and **301 Redirects** (`?provider=`, `?status_code=`, `?search=`, `?per_page=50`, `?page=1`). |
| `GET /content/redirections/404` | Inspect recent 404 error logs recorded by Redirection plugin with request frequency, referrer, and GDPR/PII-masked IP (`?per_page=50`, `?page=1`, `?search=`). |
| `GET /performance/templates-urls` | Auto-discovers and resolves representative URLs for 5 key e-commerce page archetypes: Homepage (`/`), Shop (`/shop/`), Product Category, Single Product, and Cart/Checkout. |
| `GET /performance/profile?path=/` | Targeted on-demand URL profiler: attributes SQL queries and duration per plugin via stack backtraces, detects duplicate/slow queries (>50ms), measures TTFB, memory, enqueued assets, and 100% native Core Web Vitals signals (DOM size & Elementor %, CLS missing image dimensions, legacy PNG/JPEG images, Google Fonts swap, WP core bloat scripts, wc-cart-fragments). |
| `GET /performance/autoload` | Deep `wp_options` autoload bloat analysis: total size vs 800KB threshold, top heaviest options, and size distribution grouped by plugin prefix (`?limit=25`). |
| `GET /performance/plugins-summary` | Consolidated resource footprint per plugin: active status, associated database tables count, database disk size, and table row counts (`?status=active\|all`). |
| `GET /performance/caching` | Universal caching & optimization diagnostic: Object Cache (Redis/Memcached), Page Cache drop-in (`advanced-cache.php`), and in-depth WP Rocket settings (RUCSS vs CPCSS mode, CSS safelist, Delay JS exclusions & safe mode, lazyload, mobile caching) with strict security redaction. |
| `GET /system/caching` | Alias to `/performance/caching`: Universal caching & optimization diagnostic accessible via either `system` or `performance` module permissions. |
| `GET /pmpro/levels` | Paid Memberships Pro levels list with duration rules (`expiration_number`, `expiration_period`, `cycle_number`, `cycle_period`), pricing, active member counts, and duration anomaly detection. |
| `GET /pmpro/members?status={active\|all}` | Paginated membership records from `wp_pmpro_memberships_users` with startdate, enddate, status, masked PII, and computed expiration indicators (`is_expired`, `days_left`). |
| `GET /pmpro/member/{user_id}` | Deep member diagnostic: active level, membership history timeline, associated PMPro orders (redacted transaction IDs and notes), and access anomaly flags. |
| `GET /masterstudy/courses?status={publish\|draft\|all}` | MasterStudy LMS courses list with pricing configuration, linked WooCommerce product ID, course duration/expiration rules, total students, lessons count, and allowed PMPro membership levels. |
| `GET /masterstudy/user/{user_id}/courses` | Detailed user enrollment audit: course progress, start/end dates, linked PMPro subscription ID integrity, and root cause diagnosis for premature access expirations or pointer desync. |

---

## 🎯 Procedural AI Playbooks (7 MECE Master Investigation Pillars)

To eliminate prompt dilution and trigger collisions while guaranteeing exhaustive diagnostic depth, the plugin consolidates all investigation sequences into **7 MECE Strategic Master Pillars**:

1. **360° SEO, Content Hierarchy, Redirections & Visibility Audit** (`seo_content_audit`): Runs `/content/seo-audit`, audits global plugin settings best practices with `/content/seo/settings`, analyzes `/content/pages`, inspects `/content/page/{id}` for flagged URLs, Gutenberg block structures, and meta tags, and audits configured 301/302/410 redirect rules and 404 error logs with `/content/redirections` and `/content/redirections/404`.
2. **Agency Multi-Template Performance & Core Web Vitals Audit** (`agency_performance_audit`): Audits caching infrastructure with `/performance/caching` (Object Cache, Page Cache drop-in, WP Rocket RUCSS vs CPCSS, Delay JS, safelist), correlates `/performance/templates-urls`, conducts on-demand profiling with `/performance/profile` across 5 archetypes (attributing SQL duration and memory per plugin with 100% native Core Web Vitals & frontend signals), and analyzes active plugins database footprint with `/performance/plugins-summary`.
3. **System Health, Database Bloat & Background Hygiene Audit** (`database_system_hygiene`): Correlates `/system`, `/system/database` (table sizes, autoload memory, orphaned options detection), `/woocommerce/summary` (order cancellation ratio & stale unpaid orders > 1y), `/action-scheduler` (queue backlog & retention policy alerts), `/crons` (overdue jobs), and `/logs/errors-summary` (Crash Watch).
4. **Orders, Payment Gateways, PMPro & LMS Troubleshooting** (`order_checkout_troubleshoot`): Investigates order failures with `/woocommerce/orders`, `/woocommerce/order/{id}`, gateway debug logs `/logs/view`, active checkout snippets `/snippets`, SMTP delivery diagnostics `/system/mail`, WooCommerce webhooks `/woocommerce/webhooks`, Paid Memberships Pro member/level diagnostics `/pmpro/member/{user_id}`, and MasterStudy LMS user course enrollment & expiration root cause analysis `/masterstudy/user/{user_id}/courses`.
5. **360° E-Commerce Sales, Traffic & Conversion Analytics** (`ecommerce_bi_analytics`): Deep commercial intelligence combining native WooCommerce sales `/woocommerce/analytics/sales`, `/woocommerce/analytics/top-performers`, `/woocommerce/analytics/stock` alongside traffic acquisition `/analytics/overview`, and marketing campaigns `/analytics/campaigns`.
6. **Shipping Zones, Methods & Flexible Shipping Rules Audit** (`shipping_logistics_audit`): Comprehensive logistics audit inspecting shipping zones and geo-locations with `/woocommerce/shipping`, general shop options with `/woocommerce/settings`, and deep order shipping line metadata with `/woocommerce/order/{id}`.
7. **Code Architecture, Theme Settings & Automations Map** (`code_theme_integrations`): Audits WooCommerce template version drift `/theme/overrides`, child theme files `/theme/child`, cryptographic directory checksum fingerprints for local vs remote drift `/code/checksums`, FlowMattic workflows `/flowmattic/workflows`, Elementor forms `/elementor/forms`, custom snippets (WPCode & Code Snippets) `/snippets`, and custom ACF/code meta fields `/meta/fields`.

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
node sync.js pull:content      # Dumps WordPress pages, Gutenberg trees, SEO audit & plugin settings, and redirections to ./content/
node sync.js pull:performance  # Dumps multi-template URLs, homepage profile with native Web Vitals, autoload & plugins DB footprint
node sync.js pull:pmpro        # Dumps Paid Memberships Pro levels, duration rules, and active members to ./pmpro/
node sync.js pull:masterstudy  # Dumps MasterStudy LMS courses, pricing, and duration rules to ./masterstudy/
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

## 🌐 Internationalization (i18n) & Loco Translate

- Default language: **English** (code, PHPDoc, UI default strings).
- Fully compatible with **Loco Translate** and standard WordPress polyglot tools.
- **100% French Translations**: Full master template (`woo-get-data-for-ai.pot`), French PO translation (`woo-get-data-for-ai-fr_FR.po`), and binary compiled MO file (`woo-get-data-for-ai-fr_FR.mo`) included in `woo-get-data-for-ai/languages/` covering 497+ UI, playbook, and API strings.
- **Automated CLI Sync Tool**: Run `php cli/sync-i18n.php` (or `npm run i18n` in `cli/`) to automatically scan the codebase, regenerate the POT template, merge French translations, and recompile the binary MO file without external gettext dependencies.

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

