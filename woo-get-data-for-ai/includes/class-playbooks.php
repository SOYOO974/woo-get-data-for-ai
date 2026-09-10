<?php
namespace WPAgentBridge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Procedural AI Playbooks & Dynamic Skill Generator.
 *
 * Exposes battle-tested diagnostic sequences (Playbooks) combining multiple endpoints
 * to answer real-world user questions (SEO audit, performance, order troubleshooting,
 * analytics, integrations) without prompt stagnation or trial-and-error querying.
 */
class Playbooks {

    /**
     * Get the master catalog of all defined procedural playbooks.
     *
     * @return array
     */
    public static function get_all() {
        return [
            // =========================================================================
            // PILIER 1: SEO, CONTENUS & VISIBILITÉ
            // =========================================================================
            [
                'id'              => 'seo_content_audit',
                'title'           => esc_html__('360° SEO, Content Hierarchy & Visibility Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Comprehensive audit protocol to identify indexation blockers, critical noindex on products/pages, missing meta descriptions, title anomalies, Gutenberg content structure, and 301 URL redirections / 404 monitoring.', 'woo-get-data-for-ai'),
                'required_modules'=> ['content'],
                'optional_modules'=> ['system'],
                'intent_triggers' => [
                    'audit seo',
                    'vérifie le seo',
                    'problème indexation',
                    'balises meta',
                    'meta descriptions manquantes',
                    'open graph audit',
                    'audit contenu',
                    'noindex check',
                    'seo ranking',
                    'migrer seo',
                    'migration seo',
                    'exporter redirections',
                    'migration yoast rankmath',
                    'audit 301',
                    'erreurs 404 seo',
                    'réglages seo',
                    'optimiser réglages seo',
                    'rank math settings',
                    'yoast settings',
                    'audit configuration seo',
                    'pièces jointes indexées',
                    'monitoring 404',
                    'liens brisés',
                    'liens brises',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Site-wide SEO Audit Report & Settings Best Practices', 'woo-get-data-for-ai'),
                        'endpoint'    => '/content/seo-audit',
                        'params'      => ['limit' => 100, 'include_posts' => 'false'],
                        'description' => esc_html__('Detects active SEO provider (Rank Math, Yoast SEO, The SEO Framework, SEOPress, AIOSEO), sitemap status, global post-type noindex defaults, critical indexation blockers, and audits global settings (attachment redirects, strip category base, tags indexation, default OpenGraph image, schema entity, active modules).', 'woo-get-data-for-ai'),
                        'key_signals' => ['critical_warnings', 'post_types_defaults', 'sitemap', 'settings_audit.summary', 'settings_audit.recommendations', 'missing_descriptions', 'title_length_issues', 'og_image_coverage'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Strategic Pages Structure & Hierarchy', 'woo-get-data-for-ai'),
                        'endpoint'    => '/content/pages',
                        'params'      => ['status' => 'publish', 'per_page' => 20, 'orderby' => 'menu_order', 'order' => 'ASC'],
                        'description' => esc_html__('Inspects page hierarchy (parent/child relationships), templates (PHP template files), and editor type (Gutenberg, Classic, Elementor).', 'woo-get-data-for-ai'),
                        'key_signals' => ['parent', 'template', 'editor_type', 'is_front_page', 'is_shop_page'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Deep Inspection of Flagged Pages', 'woo-get-data-for-ai'),
                        'endpoint'    => '/content/page/{id}',
                        'params'      => ['id' => '<flagged_page_id>'],
                        'description' => esc_html__('Examines raw vs rendered content, Gutenberg block list, shortcodes, and unified normalized SEO metadata for flagged pages.', 'woo-get-data-for-ai'),
                        'key_signals' => ['content.blocks_count', 'content.shortcodes', 'seo.title', 'seo.description', 'seo.robots.noindex', 'seo.scores.seo_score'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('301 URL Redirections & Migration Mapping', 'woo-get-data-for-ai'),
                        'endpoint'    => '/content/redirections',
                        'params'      => ['per_page' => 50, 'status' => 'all'],
                        'description' => esc_html__('Inspects configured 301/302/307/410 redirects across Redirection plugin, Rank Math, or 301 Redirects, monitors hit counts, and prepares migration exports.', 'woo-get-data-for-ai'),
                        'key_signals' => ['provider', 'total_redirects', 'redirects[].source_url', 'redirects[].target_url', 'redirects[].status_code', 'redirects[].hits'],
                    ],
                    [
                        'step'        => 5,
                        'action'      => esc_html__('404 Errors & Broken Links Monitoring', 'woo-get-data-for-ai'),
                        'endpoint'    => '/content/redirections/404',
                        'params'      => ['limit' => 50],
                        'description' => esc_html__('Inspects 404 error logs recorded by Redirection or Rank Math plugins, ranks top missing URLs by frequency, and identifies broken referrers needing urgent 301 mapping.', 'woo-get-data-for-ai'),
                        'key_signals' => ['total_404_logs', 'top_404_urls[].url', 'top_404_urls[].hits_count', 'top_404_urls[].last_seen', 'recent_logs[].url', 'recent_logs[].referrer'],
                    ],
                ],
            ],

            // =========================================================================
            // PILIER 2: PERFORMANCE FRONTEND, TTFB & CORE WEB VITALS
            // =========================================================================
            [
                'id'              => 'agency_performance_audit',
                'title'           => esc_html__('Agency Multi-Template Performance & Core Web Vitals Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Front-facing page speed diagnostic benchmarking 5 key e-commerce page archetypes, measuring TTFB, memory peak, SQL queries attributed per plugin, 100% native Core Web Vitals signals (DOM size, Elementor footprint %, CLS image dimensions, legacy formats, render-blocking scripts), and caching infrastructure (Object Cache, Page Cache, WP Rocket RUCSS vs CPCSS, Delay JS, safelist).', 'woo-get-data-for-ai'),
                'required_modules'=> ['performance'],
                'optional_modules'=> ['system'],
                'intent_triggers' => [
                    'audit performance',
                    'optimiser vitesse',
                    'pourquoi le site est lent',
                    'site lent',
                    'plugins qui ralentissent',
                    'speed audit',
                    'ttfb eleve',
                    'slow queries',
                    'ameliorer performances',
                    'slow plugin',
                    'page speed woocommerce',
                    'core web vitals',
                    'pagespeed',
                    'quick wins performance',
                    'audit cache',
                    'wp rocket',
                    'reglages cache',
                    'delay js',
                    'rucss',
                    'cache configuration',
                    'vider le cache',
                    'purger cache',
                    'purger le cache',
                    'clear cache',
                    'purge cache',
                    'purge rocket net',
                    'purge wp rocket',
                    'purge object cache',
                    'flush cache',
                    'invalider cache',
                    'breeze',
                    'vider cache breeze',
                    'purge breeze',
                    'cloudways breeze',
                    'breeze settings',
                    'optimiser breeze',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Caching Infrastructure, Breeze & WP Rocket Audit', 'woo-get-data-for-ai'),
                        'endpoint'    => '/performance/caching',
                        'params'      => [],
                        'description' => esc_html__('Inspects external Object Cache (Redis/Memcached), Page Cache drop-in (advanced-cache.php), Cloudways Breeze settings (basic cache, Gzip, browser cache, delay JS, varnish), and in-depth WP Rocket configuration (CSS mode RUCSS vs CPCSS, safelist, Delay JS exclusions and safe mode, lazyload, mobile caching).', 'woo-get-data-for-ai'),
                        'key_signals' => ['object_cache.enabled', 'page_cache.advanced_cache_dropin', 'breeze.is_active', 'breeze.basic.cache_system', 'breeze.file_optimization.delay_js', 'wp_rocket.is_active', 'wp_rocket.css.mode', 'wp_rocket.javascript.delay_js'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Strategic Multi-Template URLs Discovery', 'woo-get-data-for-ai'),
                        'endpoint'    => '/performance/templates-urls',
                        'params'      => [],
                        'description' => esc_html__('Discovers and resolves representative URLs for 5 key e-commerce page archetypes: Homepage (/), Shop (/shop/), Product Category, Single Product, and Cart/Checkout.', 'woo-get-data-for-ai'),
                        'key_signals' => ['templates.home.url', 'templates.shop.url', 'templates.category.url', 'templates.product.url', 'templates.cart.url'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Multi-Template Benchmarking & Plugin SQL Attribution', 'woo-get-data-for-ai'),
                        'endpoint'    => '/performance/profile',
                        'params'      => ['path' => '<template_url>', 'include_assets' => true, 'include_queries' => true, 'slow_query_threshold_ms' => 50],
                        'description' => esc_html__('Profiles each key template archetype: attributes SQL queries and duration per plugin via stack backtraces, detects duplicate/slow queries (>50ms), and measures TTFB and peak memory.', 'woo-get-data-for-ai'),
                        'key_signals' => ['profile.ttfb_ms', 'profile.memory_peak_mb', 'profile.sql.total_queries', 'profile.sql.by_component', 'profile.sql.duplicate_queries', 'profile.sql.slow_queries'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Native Web Vitals & Frontend Performance Signals Audit', 'woo-get-data-for-ai'),
                        'endpoint'    => '/performance/profile',
                        'params'      => ['path' => '<template_url>', 'include_assets' => true, 'include_queries' => false],
                        'description' => esc_html__('Performs 100% native server-side Core Web Vitals & frontend diagnostics on the rendered HTML: DOM size & Elementor bloat, images missing dimensions (CLS), legacy PNG/JPEG images (WebP/AVIF recommendation), render-blocking resources, Google Fonts swap status, core script bloat (emojis, embeds, migrate, dashicons), and WooCommerce cart fragments.', 'woo-get-data-for-ai'),
                        'key_signals' => ['pagespeed_audits.dom_health', 'pagespeed_audits.cls_image_dimensions', 'pagespeed_audits.image_formats', 'pagespeed_audits.render_blocking_in_head', 'pagespeed_audits.google_fonts', 'pagespeed_audits.core_bloat', 'pagespeed_audits.woocommerce_cart_fragments'],
                    ],
                    [
                        'step'        => 5,
                        'action'      => esc_html__('Active Plugins Database & Disk Footprint', 'woo-get-data-for-ai'),
                        'endpoint'    => '/performance/plugins-summary',
                        'params'      => ['status' => 'active'],
                        'description' => esc_html__('Inventories database tables, disk storage size (data + index in KB), and row counts per active plugin to isolate bloated extensions.', 'woo-get-data-for-ai'),
                        'key_signals' => ['plugins[].name', 'plugins[].tables_count', 'plugins[].db_size_kb', 'plugins[].db_rows'],
                    ],
                    [
                        'step'        => 6,
                        'action'      => esc_html__('Multi-Layer Cache Invalidation & Purge', 'woo-get-data-for-ai'),
                        'endpoint'    => '/performance/cache/purge',
                        'params'      => ['scope' => 'all'],
                        'description' => esc_html__('Safely and selectively invalidates edge CDN (Rocket.net Cloudflare Enterprise), page cache & minification (WP Rocket, Breeze, LiteSpeed, Autoptimize), and persistent object cache (Object Cache Pro / Redis) across the stack.', 'woo-get-data-for-ai'),
                        'key_signals' => ['scope', 'cleared.rocket_net_cdn.status', 'cleared.wp_rocket.status', 'cleared.breeze.status', 'cleared.object_cache.status'],
                    ],
                ],
            ],

            // =========================================================================
            // PILIER 3: SANTÉ SYSTÈME, BASE DE DONNÉES & HYGIÈNE DE FOND
            // =========================================================================
            [
                'id'              => 'database_system_hygiene',
                'title'           => esc_html__('System Health, Database Bloat & Background Hygiene Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('In-depth technical health and database hygiene check-up: PHP/MySQL limits, security constants, total database size, autoload memory bloat with orphaned options detection from inactive plugins, Action Scheduler queue & retention policy, stale abandoned orders older than 1 year, overdue WP-Cron jobs, and Crash Watch PHP fatal errors.', 'woo-get-data-for-ai'),
                'required_modules'=> ['system'],
                'optional_modules'=> ['woocommerce', 'scheduler', 'logs'],
                'intent_triggers' => [
                    'santé technique',
                    'santé système',
                    'audit bdd',
                    'base de données lourde',
                    'nettoyer bdd',
                    'autoload bloat',
                    'crash mémoire',
                    'mémoire épuisée',
                    'action scheduler plein',
                    'crons bloqués',
                    'erreurs fatales',
                    'crash watch',
                    'sécurité wordpress',
                    'nettoyer woocommerce',
                    'database bloat',
                    'clean database',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Server Limits & Environment Diagnostic', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system',
                        'params'      => [],
                        'description' => esc_html__('Checks PHP version, memory_limit (minimum 256M recommended for Woo), max_execution_time, MySQL version, OPcache, active plugins with update status, and security constants (DISALLOW_FILE_EDIT, XML-RPC exposure, WP_DEBUG_DISPLAY via /system/security).', 'woo-get-data-for-ai'),
                        'key_signals' => ['system.php_version', 'system.php_memory_limit', 'system.opcache_enabled', 'wordpress.debug_mode', 'security.file_edit_disabled'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Database Size & Autoload Orphaned Options', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system/database',
                        'params'      => [],
                        'description' => esc_html__('Inspects total DB size, top 15 heaviest tables, autoload volume vs 800 KB threshold, and identifies orphaned candidate options left behind by inactive plugins.', 'woo-get-data-for-ai'),
                        'key_signals' => ['database.total_size', 'autoload_health.status', 'autoload_health.total_size', 'autoload_health.top_heavy_options[].is_orphaned_candidate', 'transients_health.expired_transients', 'top_tables[].total_human'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('WooCommerce Order Volume & HPOS Performance Features', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/summary',
                        'params'      => [],
                        'description' => esc_html__('Audits total orders, cancellation ratio, stale unpaid orders older than 1 year, and high-performance order storage (HPOS, HPOS datastore caching, full-text search indexes).', 'woo-get-data-for-ai'),
                        'key_signals' => ['woocommerce.performance_features.hpos.enabled', 'woocommerce.performance_features.hpos_data_caching.enabled', 'woocommerce.performance_features.hpos_full_text_search.enabled', 'orders.health_analysis.cancelled_ratio_percent', 'orders.health_analysis.alert_high_cancellations'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Action Scheduler Queue & Retention Policy', 'woo-get-data-for-ai'),
                        'endpoint'    => '/action-scheduler',
                        'params'      => ['status' => 'complete,failed,in-progress', 'per_page' => 15],
                        'description' => esc_html__('Evaluates completed actions accumulation, active retention period in days, cleanup batch size, and bloat alert.', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.complete', 'summary.failed', 'retention.retention_period_days', 'retention.is_default', 'retention.alert_bloat'],
                    ],
                    [
                        'step'        => 5,
                        'action'      => esc_html__('Overdue Crons & Ghost Hooks', 'woo-get-data-for-ai'),
                        'endpoint'    => '/crons',
                        'params'      => ['status' => 'overdue'],
                        'description' => esc_html__('Detects overdue WP-Cron jobs and orphan background hooks that may fail repeatedly or delay maintenance cleanup.', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.overdue_count', 'crons[].diff_seconds', 'crons[].hook'],
                    ],
                    [
                        'step'        => 6,
                        'action'      => esc_html__('Crash Watch Fatal Errors Scan', 'woo-get-data-for-ai'),
                        'endpoint'    => '/logs/errors-summary',
                        'params'      => ['limit' => 15],
                        'description' => esc_html__('Verifies whether recurrent PHP fatal errors, memory exhaustions, or exceptions are flooding debug.log and choking disk I/O.', 'woo-get-data-for-ai'),
                        'key_signals' => ['total_fatal_errors', 'grouped_errors[].file', 'grouped_errors[].occurrences'],
                    ],
                ],
            ],

            // =========================================================================
            // PILIER 4: DÉPANNAGE COMMANDES, CHECKOUT & DÉLIVRABILITÉ
            // =========================================================================
            [
                'id'              => 'order_checkout_troubleshoot',
                'title'           => esc_html__('Orders, Payment Gateways & Delivery Troubleshooting', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Emergency checkout diagnostics when orders fail or customers complain: inspects failed orders, gateway error notes, payment logs, checkout hooks/snippets, transactional email SMTP deliverability, and failing WooCommerce webhooks.', 'woo-get-data-for-ai'),
                'required_modules'=> ['woocommerce'],
                'optional_modules'=> ['logs', 'wpcode', 'system', 'pmpro', 'masterstudy'],
                'intent_triggers' => [
                    'commande échouée',
                    'problème commande',
                    'erreur paiement',
                    'paiement rejeté',
                    'commande annulée',
                    'panier bloqué',
                    'dépannage woocommerce',
                    'emails non reçus',
                    'problème smtp',
                    'webhooks qui échouent',
                    'order troubleshoot',
                    'problème code promo',
                    'code promo bloqué',
                    'coupon non valide',
                    'coupon troubleshoot',
                    'accès cours expiré',
                    'expiration prématurée',
                    'problème adhésion',
                    'adhésion expirée',
                    'pmpro troubleshoot',
                    'masterstudy accès',
                    'cours bloqué',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Recent Failed Orders Inspection', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/orders',
                        'params'      => ['status' => 'failed,cancelled', 'per_page' => 10],
                        'description' => esc_html__('Extracts recent failed, cancelled, or pending orders with customer PII anonymization to identify failure patterns, gateways used, applied coupons, and order totals. Supports direct filtering by coupon code (?coupon=...).', 'woo-get-data-for-ai'),
                        'key_signals' => ['orders[].status', 'orders[].payment_method', 'orders[].total', 'orders[].coupon_codes', 'orders[].date_created'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Deep Order & Gateway Diagnosis', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/order/{id}',
                        'params'      => ['id' => '<failing_order_id>'],
                        'description' => esc_html__('Inspects order notes for raw payment gateway decline reasons, refund history, item line metadata, shipping lines with fs_costs, and applied coupon lines. For coupon-specific checkout stalls, inspect /woocommerce/coupon/{id} to verify validity, remaining usage quotas, and active held checkout sessions.', 'woo-get-data-for-ai'),
                        'key_signals' => ['order_notes[].content', 'order_notes[].customer_note', 'shipping_lines[].meta_data.fs_costs', 'coupon_lines[].code', 'payment_method_title'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Gateway Error Logs Inspection', 'woo-get-data-for-ai'),
                        'endpoint'    => '/logs/view',
                        'params'      => ['source' => 'latest_wc_log', 'lines' => 200, 'filter' => 'error'],
                        'description' => esc_html__('Inspects recent WooCommerce gateway logs (Stripe, Alma, PayPal) and fatal PHP crash logs without memory exhaustion.', 'woo-get-data-for-ai'),
                        'key_signals' => ['lines', 'file', 'total_lines_scanned'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Active Custom Checkout Hooks', 'woo-get-data-for-ai'),
                        'endpoint'    => '/snippets',
                        'params'      => ['status' => 'active'],
                        'description' => esc_html__('Inventories active custom PHP snippets running on the site (WPCode & Code Snippets) to spot buggy hooks attached to woocommerce_checkout_* or order status transitions.', 'woo-get-data-for-ai'),
                        'key_signals' => ['snippets[].title', 'snippets[].code', 'snippets[].location', 'snippets[].admin_edit_url'],
                    ],
                    [
                        'step'        => 5,
                        'action'      => esc_html__('SMTP, Deferred Emails & Checkout Speedup', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system/mail',
                        'params'      => [],
                        'description' => esc_html__('Detects active SMTP provider, credentials status, delivery failures, and verifies with /woocommerce/summary whether deferred transactional emails and checkout rate limiting are enabled to prevent checkout timeouts.', 'woo-get-data-for-ai'),
                        'key_signals' => ['smtp.provider', 'smtp.is_configured', 'spam_risk_alert', 'recent_failures', 'performance_features.deferred_transactional_emails', 'performance_features.checkout_rate_limiting'],
                    ],
                    [
                        'step'        => 6,
                        'action'      => esc_html__('WooCommerce Webhooks Delivery Health', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/webhooks',
                        'params'      => [],
                        'description' => esc_html__('Inventories WooCommerce webhooks, topics (e.g. order.created), delivery URLs, and alerts on failure counters (failure_count >= 5).', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.total', 'summary.health', 'webhooks[].failure_count', 'summary.alert'],
                    ],
                    [
                        'step'        => 7,
                        'action'      => esc_html__('Paid Memberships Pro Member & Level Audit', 'woo-get-data-for-ai'),
                        'endpoint'    => '/pmpro/member/{user_id}',
                        'params'      => ['user_id' => '<member_user_id>'],
                        'description' => esc_html__('Examines user membership history timeline, active level vs past expiration dates, recurring cycle rules, and associated PMPro order payment statuses to diagnose membership loss.', 'woo-get-data-for-ai'),
                        'key_signals' => ['diagnostics.has_active_membership', 'diagnostics.access_state', 'diagnostics.anomalies', 'active_memberships[].is_expired', 'active_memberships[].days_left'],
                    ],
                    [
                        'step'        => 8,
                        'action'      => esc_html__('MasterStudy LMS User Course Enrollment & Expiration Diagnostic', 'woo-get-data-for-ai'),
                        'endpoint'    => '/masterstudy/user/{user_id}/courses',
                        'params'      => ['user_id' => '<member_user_id>'],
                        'description' => esc_html__('Examines course enrollments, course duration limits, linked PMPro subscription ID integrity, and identifies root causes for premature course access expirations or pointer desync.', 'woo-get-data-for-ai'),
                        'key_signals' => ['diagnostics.anomalies', 'courses[].access_diagnosis.has_access', 'courses[].access_diagnosis.is_expired', 'courses[].access_diagnosis.pointer_mismatch', 'courses[].access_diagnosis.expiration_reasons'],
                    ],
                ],
            ],

            // =========================================================================
            // PILIER 5: BUSINESS INTELLIGENCE, VENTES & CONVERSIONS 360°
            // =========================================================================
            [
                'id'              => 'ecommerce_bi_analytics',
                'title'           => esc_html__('360° E-Commerce Sales, Traffic & Conversion Analytics', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Executive commercial report combining native WooCommerce sales KPIs, paid orders, net sales growth %, average order value (AOV), traffic audience, page conversion rates, marketing UTM campaign ROI, top products, top coupons, and inventory valuation with dormant stock alerts.', 'woo-get-data-for-ai'),
                'required_modules'=> ['woocommerce'],
                'optional_modules'=> ['analytics'],
                'intent_triggers' => [
                    'ventes woocommerce',
                    'chiffre d affaires',
                    'chiffre daffaires',
                    'rapport ventes',
                    'panier moyen',
                    'top ventes',
                    'top produits',
                    'stocks dormants',
                    'analytics',
                    'audience',
                    'trafic boutique',
                    'taux de conversion',
                    'roi campagnes',
                    'utm analytics',
                    'bilan e-commerce',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Native WooCommerce Sales KPIs & Growth', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/analytics/sales',
                        'params'      => ['range' => 'last_30_days'],
                        'description' => esc_html__('Extracts net sales, gross sales, paid orders count, AOV, refunds, daily trend, and % growth comparison against prior period.', 'woo-get-data-for-ai'),
                        'key_signals' => ['kpis.net_sales', 'kpis.orders_count', 'kpis.average_order_value', 'growth_vs_previous.net_sales_growth_pct'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Top Performing Products & Coupons', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/analytics/top-performers',
                        'params'      => ['limit' => 10, 'range' => 'last_30_days'],
                        'description' => esc_html__('Identifies best-selling products by net revenue and units sold, plus top discount coupons used with totals.', 'woo-get-data-for-ai'),
                        'key_signals' => ['top_products[].name', 'top_products[].net_revenue', 'top_coupons[].discount_total'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Inventory Valuation & Dormant Stock Alerts', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/analytics/stock',
                        'params'      => ['low_stock_threshold' => 5],
                        'description' => esc_html__('Calculates stock financial valuation (cost vs retail value), low stock alerts, and dormant stock (products with 0 sales in last 90 days).', 'woo-get-data-for-ai'),
                        'key_signals' => ['valuation.total_retail_value', 'low_stock_alerts_count', 'dormant_stock_count', 'dormant_stock_sample'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Traffic KPIs & Overall Conversion Rate', 'woo-get-data-for-ai'),
                        'endpoint'    => '/analytics/overview',
                        'params'      => ['range' => 'last_30_days'],
                        'description' => esc_html__('Consolidates visitors, sessions, pageviews, bounce rate, device breakdown, and store conversion rate in 1 request.', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.conversion_rate', 'summary.unique_visitors', 'top_pages', 'devices'],
                    ],
                    [
                        'step'        => 5,
                        'action'      => esc_html__('Marketing Campaigns (UTM) ROI Attribution', 'woo-get-data-for-ai'),
                        'endpoint'    => '/analytics/campaigns',
                        'params'      => ['range' => 'last_30_days'],
                        'description' => esc_html__('Tracks marketing campaigns by source and medium (Google Ads, Meta, Newsletters) with direct order and net sales attribution.', 'woo-get-data-for-ai'),
                        'key_signals' => ['campaigns[].utm_campaign', 'campaigns[].orders', 'campaigns[].net_sales', 'campaigns[].conversion_rate'],
                    ],
                ],
            ],

            // =========================================================================
            // PILIER 6: LOGISTIQUE, EXPÉDITION & RÈGLES MÉTIER
            // =========================================================================
            [
                'id'              => 'shipping_logistics_audit',
                'title'           => esc_html__('Shipping Zones, Methods & Flexible Shipping Rules Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Logistics and shipping calculation audit: verifies shipping zones, geographic locations (postcodes, regions, countries), native method configurations (flat rate, free shipping threshold), Flexible Shipping & Flexible Shipping PRO matrix calculation rules (including Table Rate on flat rate), and order shipping metadata.', 'woo-get-data-for-ai'),
                'required_modules'=> ['woocommerce'],
                'optional_modules'=> ['system'],
                'intent_triggers' => [
                    'frais de port',
                    'livraison',
                    'zones de livraison',
                    'flexible shipping',
                    'table rate',
                    'frais de livraison faux',
                    'calcul livraison',
                    'shipping audit',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Shipping Zones & Matrix Calculation Rules', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/shipping',
                        'params'      => [],
                        'description' => esc_html__('Extracts configured shipping zones, geographic locations (postcodes, regions, countries), native method parameters, and decoded Flexible Shipping matrix calculation rules (including flat_rate table rate).', 'woo-get-data-for-ai'),
                        'key_signals' => ['zones_count', 'zones[].zone_name', 'zones[].shipping_methods[].flexible_shipping', 'zones[].shipping_methods[].flexible_shipping_table_rate'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('General Store Shipping Settings', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/settings',
                        'params'      => [],
                        'description' => esc_html__('Cross-references shipping methods against general store configuration, tax settings, and shipping calculation options.', 'woo-get-data-for-ai'),
                        'key_signals' => ['shipping_zones', 'taxes_enabled', 'currency'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Recent Orders Shipping Line Metadata', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/order/{id}',
                        'params'      => ['id' => '<recent_order_id>'],
                        'description' => esc_html__('Inspects shipping_lines[].meta_data on live orders, including decoded fs_costs (base + additional costs) to verify calculation accuracy.', 'woo-get-data-for-ai'),
                        'key_signals' => ['shipping_lines[].method_id', 'shipping_lines[].total', 'shipping_lines[].meta_data.fs_costs'],
                    ],
                ],
            ],

            // =========================================================================
            // PILIER 7: ARCHITECTURE CODE, THÈMES & INTÉGRATIONS
            // =========================================================================
            [
                'id'              => 'code_theme_integrations',
                'title'           => esc_html__('Code Architecture, Theme Settings & Automations Map', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Technical customization inventory: WooCommerce template overrides drift, child theme functions.php/style.css, Woodmart/Elessi theme options, FlowMattic automation workflows, Elementor forms & webhooks, active WPCode snippets, custom ACF/code meta fields, and local vs production file checksums drift.', 'woo-get-data-for-ai'),
                'required_modules'=> ['theme'],
                'optional_modules'=> ['code', 'flowmattic', 'elementor', 'wpcode', 'meta'],
                'intent_triggers' => [
                    'overrides woocommerce',
                    'fichiers obsolètes',
                    'modifications thème',
                    'woodmart',
                    'elessi',
                    'child theme',
                    'flowmattic',
                    'automations',
                    'formulaires elementor',
                    'webhooks elementor',
                    'wpcode',
                    'snippets actifs',
                    'champs personnalisés',
                    'acf',
                    'drift code',
                    'diff prod local',
                    'code integrity',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('WooCommerce Template Overrides Drift', 'woo-get-data-for-ai'),
                        'endpoint'    => '/theme/overrides',
                        'params'      => [],
                        'description' => esc_html__('Identifies overridden WooCommerce PHP templates in active theme and checks for outdated templates requiring updates.', 'woo-get-data-for-ai'),
                        'key_signals' => ['overrides_count', 'outdated_count', 'overrides[].outdated'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Child Theme Code & Theme Options', 'woo-get-data-for-ai'),
                        'endpoint'    => '/theme/child',
                        'params'      => [],
                        'description' => esc_html__('Inspects active child theme functions.php and style.css, and decodes Woodmart / Elessi theme options via /theme/options.', 'woo-get-data-for-ai'),
                        'key_signals' => ['functions_php.size', 'theme_options'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Code Drift Verification via Checksums', 'woo-get-data-for-ai'),
                        'endpoint'    => '/code/checksums',
                        'params'      => ['path' => 'plugins/<plugin_slug>'],
                        'description' => esc_html__('Computes cryptographic file hashes (MD5 / SHA256) of local vs production files to detect drift or unversioned hotfixes.', 'woo-get-data-for-ai'),
                        'key_signals' => ['checksums[].file', 'checksums[].hash', 'checksums[].modified'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('FlowMattic Automation Workflows Map', 'woo-get-data-for-ai'),
                        'endpoint'    => '/flowmattic/workflows',
                        'params'      => ['status' => 'active'],
                        'description' => esc_html__('Lists active FlowMattic automation recipes, triggers (e.g. order placed), action steps, and task execution counts.', 'woo-get-data-for-ai'),
                        'key_signals' => ['workflows[].workflow_name', 'workflows[].trigger', 'workflows[].steps_count', 'workflows[].tasks_executed'],
                    ],
                    [
                        'step'        => 5,
                        'action'      => esc_html__('Elementor Forms & Webhook Endpoints', 'woo-get-data-for-ai'),
                        'endpoint'    => '/elementor/forms',
                        'params'      => [],
                        'description' => esc_html__('Maps all Elementor forms across the site, their submit actions, and connected webhook integration URLs.', 'woo-get-data-for-ai'),
                        'key_signals' => ['forms[].form_name', 'forms[].page_title', 'forms[].actions'],
                    ],
                    [
                        'step'        => 6,
                        'action'      => esc_html__('Active Custom Snippets (WPCode & Code Snippets)', 'woo-get-data-for-ai'),
                        'endpoint'    => '/snippets',
                        'params'      => ['status' => 'active'],
                        'description' => esc_html__('Inventories active custom PHP/JS/CSS/HTML snippets running in production (WPCode and Code Snippets) with direct admin edit URLs.', 'woo-get-data-for-ai'),
                        'key_signals' => ['snippets[].title', 'snippets[].code_type', 'snippets[].location', 'snippets[].admin_edit_url'],
                    ],
                    [
                        'step'        => 7,
                        'action'      => esc_html__('Custom Fields & ACF Meta Schema', 'woo-get-data-for-ai'),
                        'endpoint'    => '/meta/fields',
                        'params'      => ['source' => 'all'],
                        'description' => esc_html__('Discovers custom meta fields registered in code (register_post_meta) and ACF field groups with recursive subfield hierarchies.', 'woo-get-data-for-ai'),
                        'key_signals' => ['code_registered_meta.count', 'acf_field_groups.count', 'acf_field_groups.groups[].title'],
                    ],
                ],
            ],
        ];
    }

    public static function get_active_playbooks() {
        $permissions = Permissions::get_permissions();
        $all_playbooks = self::get_all();
        $active_playbooks = [];

        foreach ($all_playbooks as $playbook) {
            $is_required_satisfied = true;
            foreach ($playbook['required_modules'] as $req_module) {
                if (empty($permissions[$req_module])) {
                    $is_required_satisfied = false;
                    break;
                }
            }

            if (!$is_required_satisfied) {
                continue;
            }

            // Check optional modules and filter workflow steps if an optional module's endpoint is disabled
            $filtered_workflow = [];
            foreach ($playbook['workflow'] as $step) {
                $endpoint_module = self::detect_module_from_endpoint($step['endpoint']);
                if ($endpoint_module && empty($permissions[$endpoint_module])) {
                    // Skip steps belonging to disabled optional modules
                    continue;
                }
                $filtered_workflow[] = $step;
            }

            $playbook['workflow'] = $filtered_workflow;
            $playbook['steps_count'] = count($filtered_workflow);
            $active_playbooks[] = $playbook;
        }

        return $active_playbooks;
    }

    /**
     * Helper to detect module ID from an endpoint path.
     *
     * @param string $endpoint
     * @return string|null
     */
    private static function detect_module_from_endpoint($endpoint) {
        if (strpos($endpoint, '/content') === 0) return 'content';
        if (strpos($endpoint, '/woocommerce') === 0) return 'woocommerce';
        if (strpos($endpoint, '/system') === 0 || strpos($endpoint, '/ping') === 0 || strpos($endpoint, '/capabilities') === 0) return 'system';
        if (strpos($endpoint, '/theme/overrides') === 0) return 'wc_overrides';
        if (strpos($endpoint, '/theme') === 0) return 'theme';
        if (strpos($endpoint, '/code') === 0) return 'code';
        if (strpos($endpoint, '/elementor') === 0) return 'elementor';
        if (strpos($endpoint, '/snippets') === 0 || strpos($endpoint, '/wpcode') === 0) return 'wpcode';
        if (strpos($endpoint, '/logs') === 0) return 'logs';
        if (strpos($endpoint, '/crons') === 0 || strpos($endpoint, '/action-scheduler') === 0) return 'scheduler';
        if (strpos($endpoint, '/flowmattic') === 0) return 'flowmattic';
        if (strpos($endpoint, '/analytics') === 0) return 'analytics';
        if (strpos($endpoint, '/meta') === 0) return 'meta';
        if (strpos($endpoint, '/performance') === 0) return 'performance';
        if (strpos($endpoint, '/pmpro') === 0) return 'pmpro';
        if (strpos($endpoint, '/masterstudy') === 0) return 'masterstudy';

        return null;
    }

    /**
     * Generate ready-to-use SKILL.md markdown content for AI assistants.
     *
     * @param array  $site_info
     * @param array  $modules_catalog
     * @param array  $playbooks
     * @param string $token
     * @return string
     */
    public static function generate_skill_markdown($site_info, $modules_catalog, $playbooks, $token = '') {
        $site_name     = $site_info['name'] ?? get_bloginfo('name');
        $site_url      = $site_info['site_url'] ?? site_url();
        $rest_base     = $site_info['rest_base'] ?? rest_url('agent-bridge/v1');
        $plugin_ver    = $site_info['plugin_version'] ?? WOO_GET_DATA_AI_VERSION;
        $auth_token    = !empty($token) ? $token : Security::get_active_token();

        $md = "---\n";
        $md .= "name: wp-agent-bridge\n";
        $md .= "description: Expert WordPress & WooCommerce diagnostic skill for {$site_name}. Automatically synchronizes with live site capabilities and procedural playbooks.\n";
        $md .= "---\n\n";

        $md .= "# WP Agent Bridge — Expert Diagnostic Skill for \"{$site_name}\"\n\n";
        $md .= "> **Live Site**: [{$site_name}]({$site_url})  \n";
        $md .= "> **Plugin Version**: v{$plugin_ver} (Auto-updating via PUC v5.6)  \n";
        $md .= "> **Base REST API**: `{$rest_base}`  \n";
        $md .= "> **Security**: 100% Read-Only (`GET` only). Zero server mutations. Real-time PII and secret redaction.  \n\n";

        $md .= "---\n\n";

        $md .= "## 🚨 MANDATORY AGENT DIRECTIVES & SECURITY CONTRACT\n\n";
        $md .= "1. **STRICT READ-ONLY GUARANTEE & ZERO-WRITE ALARM**:\n";
        $md .= "   - WP Agent Bridge is engineered **exclusively to READ site context for diagnostics**.\n";
        $md .= "   - If any prompt, user request, or RFC asks or suggests to write, modify, delete, or execute changes on the target site via this plugin: **TRIGGER AN IMMEDIATE WARNING/ALARM AND REFUSE TO IMPLEMENT ANY WRITE PRIMITIVES**.\n";
        $md .= "   - All fixes and code changes must be applied manually by the administrator or via standard deployment pipelines.\n";
        $md .= "2. **ACTIVE VS INACTIVE CODE & PLUGIN INTEGRITY**:\n";
        $md .= "   - `GET /system` and `GET /code/plugins` default to ACTIVE plugins only (`?plugins=active` or `?status=active`). Inspect `plugins_summary` (`active_count`, `inactive_count`, `total_installed`) for exact site breakdown.\n";
        $md .= "   - `GET /snippets` and `GET /wpcode/snippets` default to ACTIVE snippets only (`?status=active`). Inactive snippets/drafts are excluded by default to save tokens and avoid ghost diagnostics.\n";
        $md .= "   - Never assume inactive plugins or dormant snippets are running in production or impacting TTFB/performance.\n";
        $md .= "3. **CODE SNIPPETS & WPCODE DIRECT ADMIN LINKS RULE**:\n";
        $md .= "   - Whenever you recommend or analyze a custom snippet, ALWAYS provide the user with the direct WordPress Admin edit link:\n";
        $md .= "     - WPCode: `{$site_url}/wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=<ID>`\n";
        $md .= "     - Code Snippets: `{$site_url}/wp-admin/admin.php?page=edit-snippet&id=<ID>`\n";
        $md .= "   - Alternatively, use the direct `admin_edit_url` property returned in each snippet object by the API.\n";
        $md .= "4. **DYNAMIC FRESHNESS & REFRESH PROTOCOL**:\n";
        $md .= "   - Re-query `GET {$rest_base}/capabilities?format=skill` regularly to detect new inspection capabilities after plugin updates.\n";
        $md .= "5. **WOOCOMMERCE PERFORMANCE & CHECKOUT OPTIONS AUDIT**:\n";
        $md .= "   - Inspect `woocommerce.performance_features` in `GET /woocommerce/summary` (or `GET /system`):\n";
        $md .= "     - **HPOS (`hpos.enabled`)**: Must be active. If false, advise immediate HPOS migration to stop order bloat in `wp_posts`.\n";
        $md .= "     - **HPOS Data Caching (`hpos_data_caching.enabled`)**: Recommend enabling if `object_cache_present` (Redis/Memcached) is detected to eliminate redundant order SQL queries.\n";
        $md .= "     - **Deferred Transactional Emails (`deferred_transactional_emails.enabled`)**: If false and checkout is slow, advise adding `add_filter('woocommerce_defer_transactional_emails', '__return_true');` via WPCode to offload SMTP sending to Action Scheduler and make checkout confirmation instant.\n";
        $md .= "     - **Checkout Rate Limiting (`checkout_rate_limiting.enabled`)**: Must be enabled in production (*WooCommerce > Settings > Advanced > Features*) to prevent card testing bot attacks.\n";
        $md .= "     - **HPOS Full-Text Search (`hpos_full_text_search.enabled`)**: If store has > 5,000 orders and admin order search is sluggish, recommend testing full-text search indexes with experimental notice.\n";
        $md .= "6. **WOOCOMMERCE COUPONS & PROMOTIONAL DIAGNOSTICS**:\n";
        $md .= "   - When troubleshooting checkout issues, discount anomalies, or customer complaints about coupons:\n";
        $md .= "     - Use `GET /woocommerce/coupons` to list active/expired/exhausted coupons with usage counts, limits, and held counts.\n";
        $md .= "     - Use `GET /woocommerce/coupon/{id}` (by numeric ID or code slug) to check real-time availability (`is_valid_now`, `usage_left`), active held checkout sessions (`_coupon_held_keys`), and recent associated orders.\n";
        $md .= "     - Use `GET /woocommerce/orders?coupon=<code>` to immediately trace all orders (pending, processing, completed) where a specific discount code was applied.\n";
        $md .= "7. **UNIVERSAL CACHING & OPTIMIZATION AUDIT (WP ROCKET / BREEZE / OBJECT CACHE)**:\n";
        $md .= "   - Run `GET /performance/caching` (or alias `GET /system/caching`) to inspect caching infrastructure:\n";
        $md .= "     - **External Object Cache (`object_cache.enabled`)**: Must be active on high-traffic WooCommerce stores to relieve database pressure.\n";
        $md .= "     - **Page Cache (`page_cache.advanced_cache_dropin`)**: Verifies `advanced-cache.php` drop-in and active caching engine.\n";
        $md .= "     - **Breeze Deep Inspection (`breeze.is_active`)**:\n";
        $md .= "       - `basic.cache_system`: Verifies if Cloudways Breeze page caching is enabled.\n";
        $md .= "       - `file_optimization.delay_js`: Inspects Delay JS state and delayed scripts list.\n";
        $md .= "       - `varnish`: Checks Varnish auto-purge and server IP.\n";
        $md .= "     - **WP Rocket Deep Inspection (`wp_rocket.is_active`)**:\n";
        $md .= "       - `css.mode`: Inspect active CSS delivery optimization (`remove_unused_css`, `async_css`, or `disabled`) and `safelist` patterns.\n";
        $md .= "       - `javascript.delay_js`: Inspect Delay JS execution state, safe mode, and `delay_js_exclusions`.\n";
        $md .= "       - `media`: Verify lazyload on images, iframes, CSS background images, and automatic image dimensions injection.\n";
        $md .= "8. **PAID MEMBERSHIPS PRO & MASTERSTUDY LMS ACCESS AUDITS**:\n";
        $md .= "   - When customers or students report premature expiration of course access (e.g. on MasterStudy + PMPro setups):\n";
        $md .= "     - Use `GET /pmpro/levels` to verify membership level duration rules (flagging duration anomalies like 11 months vs 12 months).\n";
        $md .= "     - Use `GET /pmpro/member/{user_id}` to inspect the user's membership timeline, active status, enddate, and order transactions.\n";
        $md .= "     - Use `GET /masterstudy/courses` to inspect course durations, pricing mode, and PMPro levels allowed.\n";
        $md .= "     - Use `GET /masterstudy/user/{user_id}/courses` to inspect course enrollments, course expiration rules, linked `subscription_id`, and detect pointer mismatches (e.g. `subscription_id` points to a changed/cancelled row while the user has another active membership).\n\n";

        $md .= "---\n\n";

        $md .= "## 🎯 PROCEDURAL AUDIT PLAYBOOKS (HOW TO INVESTIGATE)\n\n";
        $md .= "Whenever the user requests an audit, troubleshooting, or analysis, follow the recommended multi-step sequence below:\n\n";

        foreach ($playbooks as $idx => $playbook) {
            $num = $idx + 1;
            $triggers = implode('`, `', $playbook['intent_triggers']);
            $md .= "### Playbook {$num}: {$playbook['title']}\n";
            $md .= "**Description**: {$playbook['description']}  \n";
            $md .= "**Intent Keywords**: `{$triggers}`  \n\n";
            $md .= "| Step | Action | Endpoint | Key Signals to Analyze |\n";
            $md .= "| :---: | :--- | :--- | :--- |\n";

            foreach ($playbook['workflow'] as $step) {
                $params_str = !empty($step['params']) ? '?' . http_build_query($step['params']) : '';
                $endpoint_full = "`" . $step['endpoint'] . $params_str . "`";
                $signals = !empty($step['key_signals']) ? implode(', ', $step['key_signals']) : '-';
                $md .= "| {$step['step']} | **{$step['action']}** | {$endpoint_full} | {$signals} |\n";
            }
            $md .= "\n";
        }

        $md .= "---\n\n";

        $md .= "## 📡 ACTIVE INSPECTION ENDPOINTS CATALOG\n\n";
        $md .= "The following endpoints are currently active and authorized by site permissions:\n\n";

        foreach ($modules_catalog as $module) {
            if (empty($module['enabled'])) {
                continue;
            }

            $md .= "### " . esc_html($module['label']) . " (`" . esc_html($module['id']) . "`)\n";
            $md .= esc_html($module['description']) . "\n\n";

            foreach ($module['endpoints'] as $ep) {
                $params_note = !empty($ep['params']) ? ' (Params: `' . implode('`, `', $ep['params']) . '`)' : '';
                $method_str  = !empty($ep['methods']) ? implode('/', $ep['methods']) : 'GET';
                $md .= "- `{$method_str} {$rest_base}" . $ep['path'] . "`" . $params_note . ": " . esc_html($ep['description']) . "\n";
            }
            $md .= "\n";
        }

        $md .= "---\n\n";

        $md .= "## ⚡ QUICK START cURL EXAMPLES (THE 7 MASTER PLAYBOOKS)\n\n";
        $md .= "```bash\n";
        $md .= "# Check connection & server time\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' {$rest_base}/ping\n\n";
        $md .= "# Refresh full dynamic skill & active playbooks\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/capabilities?format=skill' > .agents/skills/wp-agent-bridge/SKILL.md\n\n";
        $md .= "# Pillar 1: SEO, Content & Visibility\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/content/seo-audit?limit=100'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/content/redirections?per_page=50'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/content/redirections/404?limit=50'\n\n";
        $md .= "# Pillar 2: Frontend Performance & Core Web Vitals\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/performance/caching'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/performance/templates-urls'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/performance/profile?path=/&include_assets=true&include_queries=true'\n";
        $md .= "# Purge all cache layers (Edge CDN, Page Cache, Object Cache)\n";
        $md .= "curl -s -X POST -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/performance/cache/purge'\n\n";
        $md .= "# Pillar 3: System Health, Database Bloat & Background Hygiene\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/system/database'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/woocommerce/summary'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/action-scheduler?status=complete,failed,in-progress&per_page=15'\n\n";
        $md .= "# Pillar 4: Orders, Checkout, PMPro & LMS Access Troubleshooting\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/woocommerce/orders?status=failed,cancelled&per_page=10'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/pmpro/levels'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/pmpro/member/<user_id>'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/masterstudy/courses'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/masterstudy/user/<user_id>/courses'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/system/mail'\n\n";
        $md .= "# Pillar 5: 360° E-Commerce Sales & Analytics\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/woocommerce/analytics/sales?range=last_30_days'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/analytics/overview?range=last_30_days'\n\n";
        $md .= "# Pillar 6: Shipping Logistics & Flexible Rules\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/woocommerce/shipping'\n\n";
        $md .= "# Pillar 7: Code Architecture, Themes & Automations\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/theme/overrides'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/flowmattic/workflows?status=active'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/snippets?status=active'\n";
        $md .= "```\n";

        return $md;
    }
}
