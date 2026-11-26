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
                    'redirections intelligentes',
                    'smart redirections',
                    'snippet 404',
                    'nettoyer les 404',
                    'optimiser les redirections',
                    'fallback produit 404',
                    'safety net 404',
                    'custom post types',
                    'cpt',
                    'post types',
                    'cms block',
                    'elementor library',
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
                        'action'      => esc_html__('404 Errors Monitoring, Pattern Clustering & Smart Redirection Strategy', 'woo-get-data-for-ai'),
                        'endpoint'    => '/content/redirections/404',
                        'params'      => ['limit' => 100],
                        'description' => esc_html__('Analyzes 404 error logs grouped by pattern typologies (deleted products, JS pagination bugs, Apple/Safari browser requests, cache assets, security probes). Prioritizes remediation via an in-memory PHP Code Snippet (template_redirect hook at priority 1) or global regex rules to prevent SQL redirection table bloat, and prescribes a semantic safety net to direct orphan products to their respective category.', 'woo-get-data-for-ai'),
                        'key_signals' => ['total_404_logs', 'top_404_urls[].url', 'top_404_urls[].hits_count', 'top_404_urls[].last_seen', 'pattern_clusters', 'patterns_summary', 'smart_snippet_recommended'],
                    ],
                    [
                        'step'        => 6,
                        'action'      => esc_html__('Custom Post Types Inventory & Multi-Type Content Inspection', 'woo-get-data-for-ai'),
                        'endpoint'    => '/content/custom-post-types',
                        'params'      => ['public' => 'true'],
                        'description' => esc_html__('Discovers all registered public CPTs with publication counts (Woodmart HTML blocks, Elementor templates, custom products/entities). Then allows targeting specific CPTs via /content/posts?post_type=cms_block,elementor_library for SEO and structure inspection.', 'woo-get-data-for-ai'),
                        'key_signals' => ['total_post_types', 'post_types[].name', 'post_types[].counts.publish', 'post_types[].supports'],
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
                    'savequeries',
                    'savequeries activé',
                    'savequeries active',
                    'constantes wp-config',
                    'wp-config performance',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Caching Infrastructure, Runtime Constants & Performance Flags Audit', 'woo-get-data-for-ai'),
                        'endpoint'    => '/performance/caching',
                        'params'      => [],
                        'description' => esc_html__('Inspects runtime performance constants from wp-config.php (SAVEQUERIES memory leak check, SCRIPT_DEBUG, WP_POST_REVISIONS cap, WP_MEMORY_LIMIT, WP_CACHE), external Object Cache (Redis/Memcached), Page Cache drop-in (advanced-cache.php), Cloudways Breeze settings, and in-depth WP Rocket configuration.', 'woo-get-data-for-ai'),
                        'key_signals' => ['runtime_constants.savequeries', 'runtime_constants.has_critical_alerts', 'object_cache.enabled', 'page_cache.advanced_cache_dropin', 'breeze.is_active', 'breeze.basic.cache_system', 'breeze.file_optimization.delay_js', 'wp_rocket.is_active', 'wp_rocket.css.mode', 'wp_rocket.javascript.delay_js'],
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
                'optional_modules'=> ['woocommerce', 'scheduler', 'logs', 'guardian'],
                'intent_triggers' => [
                    'sentinelle',
                    'snapshot santé',
                    'monitoring nocturne',
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
                    'commandes fantômes',
                    'commandes fantomes',
                    'rétention commandes',
                    'retention commandes',
                    'purger commandes',
                    'chercher dans la base',
                    'rechercher réglage',
                    'recherche globale',
                    'trouver option',
                    'savequeries bdd',
                    'constantes performance',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Server Limits & Environment Diagnostic', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system',
                        'params'      => [],
                        'description' => esc_html__('Checks PHP version, memory_limit (minimum 256M recommended for Woo), max_execution_time, MySQL version, OPcache, active plugins with update status, runtime wp-config performance constants (SAVEQUERIES, SCRIPT_DEBUG, WP_POST_REVISIONS), and security constants (DISALLOW_FILE_EDIT, XML-RPC exposure, WP_DEBUG_DISPLAY via /system/security).', 'woo-get-data-for-ai'),
                        'key_signals' => ['system.php_version', 'system.php_memory_limit', 'system.runtime_constants.savequeries', 'system.runtime_constants.alerts', 'system.opcache_enabled', 'wordpress.debug_mode', 'security.file_edit_disabled'],
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
                        'action'      => esc_html__('WooCommerce Order Volume, Stale Ghost Orders & Retention Policy', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/summary',
                        'params'      => [],
                        'description' => esc_html__('Audits total orders, cancellation ratio, stale ghost orders (pending >30d, failed >60d, cancelled >1y), high-performance order storage (HPOS, datastore caching, product object caching, deferred async emails), and recommends optimal retention policies (Pending: 1 month, Failed: 1-3 months, Cancelled: 6-12 months, Completed/Refunded: ND) with EMPTY_TRASH_DAYS automatic cleanup.', 'woo-get-data-for-ai'),
                        'key_signals' => ['woocommerce.performance_features.hpos.enabled', 'woocommerce.performance_features.hpos_data_caching.enabled', 'woocommerce.performance_features.product_caching.enabled', 'woocommerce.performance_features.deferred_transactional_emails.enabled', 'orders.health_analysis.stale_ghost_orders', 'orders.health_analysis.alert_high_cancellations', 'orders.health_analysis.recommendation', 'woocommerce.performance_features.recommendations'],
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
                    [
                        'step'        => 7,
                        'action'      => esc_html__('Targeted String & Settings Deep Search', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system/search',
                        'params'      => ['query' => '<search_term>', 'target' => 'all', 'limit' => 20],
                        'description' => esc_html__('Locates specific configuration strings, obsolete plugin options, or custom snippet references across wp_posts, wp_options (with secret redaction), and WPCode / Code Snippets.', 'woo-get-data-for-ai'),
                        'key_signals' => ['counts.total', 'posts[].title', 'options[].option_name', 'snippets[].name'],
                    ],
                    [
                        'step'        => 8,
                        'action'      => esc_html__('One-Call Guardian Snapshot & Change Journal', 'woo-get-data-for-ai'),
                        'endpoint'    => '/guardian/snapshot',
                        'params'      => ['since' => '<ISO 8601 UTC>', 'top' => 10, 'review_since' => '<YYYY-MM-DD optional>'],
                        'description' => esc_html__('Nightly monitoring shortcut: one request returning raw dated figures for PHP fatals, payments, stuck orders, Alma, best-seller stock-outs, schedulers, mail failures, feeds, WhatsApp gateway health, and 30-day review reminders vs created product reviews, plus the change journal (updates, activations, gateway settings keys, mu-plugins drift) to explain any anomaly. Thresholds stay on the caller side.', 'woo-get-data-for-ai'),
                        'key_signals' => ['php.fatals_since', 'payments.failed_orders_since', 'orders.paid_since', 'orders.paid_statuses', 'orders.missing_paid_statuses', 'orders.processing_over_72h.count', 'alma.unrecovered_high_ticket', 'scheduler.as_past_due_pending', 'mail.wp_mail_failed_since', 'whatsapp.enabled', 'whatsapp.banned_connectors', 'whatsapp.sent_7d', 'whatsapp.failed_7d', 'whatsapp.triggers_7d', 'reviews.reminders_sent_30d', 'reviews.reviews_created_30d', 'changes[]', 'meta.timings', 'meta.urls'],
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
                'optional_modules'=> ['logs', 'wpcode', 'system', 'pmpro', 'masterstudy', 'tracking'],
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
                    'emails transactionnels',
                    'email de commande non reçu',
                    'statut commande muet',
                    'mailpoet',
                    'mailpoet sending service',
                    'log email',
                    'wp mail logging',
                    'email rebond',
                    'mailpoet bounce',
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
                        'key_signals' => ['orders[].status', 'orders[].is_paid', 'orders[].payment_method', 'orders[].total', 'orders[].coupon_codes', 'orders[].date_created'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Deep Order & Gateway Diagnosis', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/order/{id}',
                        'params'      => ['id' => '<failing_order_id>'],
                        'description' => esc_html__('Inspects order notes for raw payment gateway decline reasons, refund history, item line metadata, shipping lines with fs_costs, and applied coupon lines. For coupon-specific checkout stalls, inspect /woocommerce/coupon/{id} to verify validity, remaining usage quotas, and active held checkout sessions.', 'woo-get-data-for-ai'),
                        'key_signals' => ['is_paid', 'order_notes[].content', 'order_notes[].customer_note', 'shipping_lines[].meta_data.fs_costs', 'coupon_lines[].code', 'payment_method_title'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Gateway Error Logs Inspection', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/payment-logs',
                        'params'      => ['gateway' => '<stripe|alma|paypal|all>', 'order_id' => '<order_id>', 'level' => 'error', 'lines' => 100],
                        'description' => esc_html__('Inspects recent WooCommerce payment gateway logs (Stripe, Alma, PayPal) with automatic hash-free filename resolution, order ID tracing, memory-safe reverse chunk streaming, and secret key / PII redaction.', 'woo-get-data-for-ai'),
                        'key_signals' => ['gateway', 'order_id', 'total_lines', 'entries[].level', 'entries[].message', 'entries[].context.error_message', 'available_gateways'],
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
                        'action'      => esc_html__('SMTP, MailPoet MSS & Deliverability Diagnostic', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system/mail',
                        'params'      => [],
                        'description' => esc_html__('Detects active mail provider (FluentSMTP, WP Mail SMTP, Post SMTP, or MailPoet Sending Service / MSS), credentials authentication status, delivery failures, and verifies with /woocommerce/summary whether deferred transactional emails and checkout rate limiting are enabled to prevent checkout timeouts.', 'woo-get-data-for-ai'),
                        'key_signals' => ['transport_provider', 'is_authenticated', 'configuration.mailpoet.is_mss_active', 'configuration.mailpoet.mss_key_state', 'recent_failures', 'alerts'],
                    ],
                    [
                        'step'        => 6,
                        'action'      => esc_html__('WooCommerce Transactional Emails & Silent Statuses', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/emails',
                        'params'      => [],
                        'description' => esc_html__('Inspects all registered WooCommerce transactional emails, enabled statuses, customer vs admin recipient routing, Order Status Manager custom trigger conditions (from_status_to_to_status), and flags silent order statuses that lack configured notifications.', 'woo-get-data-for-ai'),
                        'key_signals' => ['silent_statuses_count', 'silent_statuses', 'order_statuses_health', 'emails[].enabled', 'emails[].order_status_manager.dispatch_conditions'],
                    ],
                    [
                        'step'        => 7,
                        'action'      => esc_html__('Database Email Delivery Logs & Search', 'woo-get-data-for-ai'),
                        'endpoint'    => '/logs/emails',
                        'params'      => ['search' => '<order_id_or_email>', 'status' => 'all', 'limit' => 20],
                        'description' => esc_html__('Searches database email logs across WP Mail Logging (wp_wpml_mails), FluentSMTP (fluentmail_log), Post SMTP (postman_logs), or WP Mail SMTP to track exact email dispatch dates, recipients, subjects, and delivery error messages.', 'woo-get-data-for-ai'),
                        'key_signals' => ['active_log_provider', 'total_matched', 'logs[].status', 'logs[].recipient', 'logs[].error'],
                    ],
                    [
                        'step'        => 8,
                        'action'      => esc_html__('MailPoet Subscriber & Silent Bounce Suppression Check', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system/mail/subscriber',
                        'params'      => ['email' => '<customer_email>'],
                        'description' => esc_html__('Checks subscriber profile in MailPoet (wp_mailpoet_subscribers) to detect whether the recipient email is marked as bounced, unconfirmed, or unsubscribed, causing MailPoet Sending Service (MSS) to silently drop order receipts and receipts.', 'woo-get-data-for-ai'),
                        'key_signals' => ['mailpoet_active', 'found', 'status', 'is_bounced', 'alerts'],
                    ],
                    [
                        'step'        => 9,
                        'action'      => esc_html__('WooCommerce Webhooks Delivery Health', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/webhooks',
                        'params'      => [],
                        'description' => esc_html__('Inventories WooCommerce webhooks, topics (e.g. order.created), delivery URLs, and alerts on failure counters (failure_count >= 5).', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.total', 'summary.health', 'webhooks[].failure_count', 'summary.alert'],
                    ],
                    [
                        'step'        => 10,
                        'action'      => esc_html__('Paid Memberships Pro Member & Level Audit', 'woo-get-data-for-ai'),
                        'endpoint'    => '/pmpro/member/{user_id}',
                        'params'      => ['user_id' => '<member_user_id>'],
                        'description' => esc_html__('Examines user membership history timeline, active level vs past expiration dates, recurring cycle rules, and associated PMPro order payment statuses to diagnose membership loss.', 'woo-get-data-for-ai'),
                        'key_signals' => ['diagnostics.has_active_membership', 'diagnostics.access_state', 'diagnostics.anomalies', 'active_memberships[].is_expired', 'active_memberships[].days_left'],
                    ],
                    [
                        'step'        => 11,
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
                'optional_modules'=> ['analytics', 'tracking'],
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
                    'audit tracking',
                    'tracking server side',
                    'meta capi',
                    'pixel meta',
                    'pixel facebook',
                    'google ads server side',
                    'gads server side',
                    'conversions api',
                    'consent mode',
                    'consent mode v2',
                    'bannière cookies',
                    'banniere cookies',
                    'rgpd tracking',
                    'audit rgpd cookies',
                    'diagnostic tracking',
                    'vérifier le tracking',
                    'suivi des conversions',
                    'pacing publicitaire',
                    'saisonnalite ventes',
                    'budget google ads',
                    'budget meta ads',
                    'ad scheduling',
                    'dayparting',
                    'fenetre de paie',
                    'effet paie',
                    'decades ventes',
                    'repartition budget pub',
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
                        'description' => esc_html__('Identifies best-selling products by net revenue, units sold, and main image URLs (with optional exclude_meta_key filtering), plus top discount coupons used with totals.', 'woo-get-data-for-ai'),
                        'key_signals' => ['top_products[].name', 'top_products[].net_revenue', 'top_products[].image_url', 'top_coupons[].discount_total'],
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
                    [
                        'step'        => 6,
                        'action'      => esc_html__('Server-Side Tracking & GDPR Consent Compliance Audit', 'woo-get-data-for-ai'),
                        'endpoint'    => '/tracking/audit',
                        'params'      => [],
                        'description' => esc_html__('Audits Meta CAPI v21.0 (Pixel ID, Access Token, test event code in production, Action Scheduler queue, recent order syncs), Google Ads Server-Side (OAuth Refresh Token, Merchant ID, Conversion Action ID, database error logs), cookie banner (native woo_gads_consent or CMP like Complianz/Axeptio), and Google Consent Mode v2 signal coherence.', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.status', 'summary.alerts', 'meta_tracking.configuration.pixel_id_valid_format', 'meta_tracking.action_scheduler.failed_last_7d', 'meta_tracking.recent_orders_summary.success_rate_pct', 'google_ads_tracking.configuration.refresh_token_status', 'google_ads_tracking.database_logs.error_count', 'gdpr_cookie_banner.cmp_detected.name', 'gdpr_cookie_banner.coherence_check.is_cookie_coherent', 'recommendations'],
                    ],
                    [
                        'step'        => 7,
                        'action'      => esc_html__('Advertising Seasonality & Budget Pacing (Decades, Pay Window & Dayparting)', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/analytics/pacing',
                        'params'      => ['range' => 'last_12_months'],
                        'description' => esc_html__('Analyzes multi-month sales seasonality, monthly decades split (1-10, 11-20, 21-31), payday window effect (days 25-5 vs 6-24), day-of-month rankings (1-31), day-of-week performance, and 24-hour dayparting for Google Ads & Meta Ads budget allocation.', 'woo-get-data-for-ai'),
                        'key_signals' => ['decades.early_month.net_sales_pct', 'pay_window.lift_vs_rest.daily_sales_lift_pct', 'recommendations.decades_budget_allocation', 'recommendations.pay_window_pacing.daily_multiplier_pay_window', 'hourly_profile.peak_hours'],
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
                'optional_modules'=> ['code', 'flowmattic', 'elementor', 'wpcode', 'meta', 'woocommerce', 'system'],
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
                    'espace client',
                    'my account',
                    'onglets mon compte',
                    'account tabs',
                    'parrainage woocommerce',
                    'woodmart custom tabs',
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
                        'action'      => esc_html__('Child Theme Code, Custom CSS & Theme Options', 'woo-get-data-for-ai'),
                        'endpoint'    => '/theme/custom-css',
                        'params'      => [],
                        'description' => esc_html__('Inspects aggregated custom CSS (Customizer, Woodmart responsive blocks, child theme style.css), child theme functions.php via /theme/child, and decodes Woodmart / Elessi theme options via /theme/options.', 'woo-get-data-for-ai'),
                        'key_signals' => ['customizer.content', 'woodmart', 'child_theme', 'functions_php.size', 'theme_options'],
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
                    [
                        'step'        => 8,
                        'action'      => esc_html__('Customer My Account Tabs & Attached Templates', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/account-tabs',
                        'params'      => [],
                        'description' => esc_html__('Maps WooCommerce My Account tabs, endpoint hooks, callbacks via Reflection, and attached Woodmart HTML blocks (cms_block) or Elementor templates with raw content.', 'woo-get-data-for-ai'),
                        'key_signals' => ['total_tabs', 'tabs[].endpoint', 'tabs[].hook', 'tabs[].callbacks[].name', 'tabs[].attached_templates[].id', 'woodmart.custom_tabs_configured'],
                    ],
                    [
                        'step'        => 9,
                        'action'      => esc_html__('Cross-System String & Hook Discovery Search', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system/search',
                        'params'      => ['query' => '<feature_name>', 'target' => 'all'],
                        'description' => esc_html__('Traces any feature, hook, or keyword (e.g. "parrainage", "sponsorship", "referral") across posts (HTML blocks, templates), site options, and custom snippets simultaneously.', 'woo-get-data-for-ai'),
                        'key_signals' => ['counts.total', 'posts[].post_type', 'options[].option_name', 'snippets[].admin_edit_url'],
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
        if (strpos($endpoint, '/tracking') === 0) return 'tracking';
        if (strpos($endpoint, '/guardian') === 0) return 'guardian';

        return null;
    }

    /**
     * Truncate a single-line string cleanly to a maximum character length.
     *
     * @param string $text
     * @param int    $max_chars
     * @return string
     */
    public static function truncate_single_line($text, $max_chars = 300) {
        $clean = self::sanitize_single_line_text($text);
        $max   = max(16, (int) $max_chars);
        $len   = function_exists('mb_strlen') ? mb_strlen($clean, 'UTF-8') : strlen($clean);
        if ($len <= $max) {
            return $clean;
        }
        $slice_len = $max - 3;
        $cut       = function_exists('mb_substr') ? mb_substr($clean, 0, $slice_len, 'UTF-8') : substr($clean, 0, $slice_len);
        $last_sp   = strrpos($cut, ' ');
        if ($last_sp !== false && $last_sp > (int) ($slice_len * 0.6)) {
            $cut = substr($cut, 0, $last_sp);
        }
        return rtrim($cut, " \t.,;:-") . '...';
    }

    /**
     * Normalize a text string onto a single line without BOM or line breaks.
     *
     * @param string $text
     * @return string
     */
    public static function sanitize_single_line_text($text) {
        $text = (string) $text;
        if (strncmp($text, "\xEF\xBB\xBF", 3) === 0) {
            $text = substr($text, 3);
        }
        $text = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $text);
        $collapsed = preg_replace('/\s+/u', ' ', $text);
        if ($collapsed === null) {
            $collapsed = preg_replace('/\s+/', ' ', $text);
        }
        return trim((string) $collapsed);
    }

    /**
     * Normalize markdown content to UTF-8 without BOM and LF (\n) line endings.
     *
     * @param string $content
     * @return string
     */
    public static function normalize_skill_output($content) {
        $content = (string) $content;
        if (strncmp($content, "\xEF\xBB\xBF", 3) === 0) {
            $content = substr($content, 3);
        }
        return str_replace(["\r\n", "\r"], "\n", $content);
    }

    /**
     * Check whether a skill description matches the auto-generated generic template.
     *
     * @param string $description
     * @return bool
     */
    public static function is_generic_skill_description($description) {
        $normalized = self::sanitize_single_line_text($description);
        if ($normalized === '') {
            return true;
        }
        if (preg_match('/^Expert WordPress & WooCommerce diagnostic skill for .+?\. Automatically synchronizes with live site capabilities and procedural playbooks\.$/i', $normalized)) {
            return true;
        }
        return (bool) preg_match(
            '/^Read-only WordPress & WooCommerce diagnostic API skill for .+?\. Exposes live system state, performance, SEO, orders, logs, snippets, and 7 audit playbooks\.$/i',
            $normalized
        );
    }

    /**
     * Extract the YAML frontmatter description from an existing SKILL.md content string.
     *
     * @param string $markdown
     * @return string
     */
    public static function extract_skill_description($markdown) {
        $normalized = self::normalize_skill_output($markdown);
        if (!preg_match('/\A---\n(.*?)\n---(?:\n|\z)/s', $normalized, $fm_match)) {
            return '';
        }

        $frontmatter = $fm_match[1];

        // 1. Block scalar syntax: description: >- (or >, |-, |) followed by indented lines
        if (preg_match('/^description:[ \t]*[>|][-+]?[0-9]*[ \t]*\n((?:[ \t]+[^\n]*\n?)+)/m', $frontmatter, $block_match)) {
            $lines = explode("\n", $block_match[1]);
            $parts = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $parts[] = $trimmed;
                }
            }
            return self::sanitize_single_line_text(implode(' ', $parts));
        }

        // 2. Inline or multiline scalar syntax: description: ...
        if (preg_match('/^description:[ \t]*([^\n]+(?:\n[ \t]+[^\n]+)*)/m', $frontmatter, $inline_match)) {
            $raw_val = self::sanitize_single_line_text($inline_match[1]);
            if (strlen($raw_val) >= 2) {
                $first = $raw_val[0];
                $last  = substr($raw_val, -1);
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $raw_val = substr($raw_val, 1, -1);
                    if ($first === "'") {
                        $raw_val = str_replace("''", "'", $raw_val);
                    } else {
                        $raw_val = str_replace(['\\"', '\\\\'], ['"', '\\'], $raw_val);
                    }
                }
            }
            return self::sanitize_single_line_text($raw_val);
        }

        return '';
    }

    /**
     * Generate ready-to-use SKILL.md markdown content for AI assistants.
     *
     * @param array       $site_info
     * @param array       $modules_catalog
     * @param array       $playbooks
     * @param string      $token
     * @param string|null $target_file Optional target SKILL.md file path (or existing SKILL.md content) to preserve custom description and write UTF-8 LF output.
     * @return string
     */
    public static function generate_skill_markdown($site_info, $modules_catalog, $playbooks, $token = '', $target_file = null) {
        $raw_site_name = $site_info['name'] ?? (function_exists('get_bloginfo') ? get_bloginfo('name') : 'WordPress & WooCommerce Store');
        if (function_exists('html_entity_decode')) {
            $raw_site_name = html_entity_decode((string) $raw_site_name, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $site_name = self::sanitize_single_line_text((string) $raw_site_name);
        if ($site_name === '') {
            $site_name = 'WordPress & WooCommerce Store';
        }

        $site_url   = $site_info['site_url'] ?? (function_exists('site_url') ? site_url() : 'https://example.com');
        $rest_base  = $site_info['rest_base'] ?? (function_exists('rest_url') ? rest_url('agent-bridge/v1') : rtrim((string) $site_url, '/') . '/wp-json/agent-bridge/v1');
        $rest_base  = rtrim((string) $rest_base, '/');
        $site_url_clean = rtrim((string) $site_url, '/');
        $plugin_ver = $site_info['plugin_version'] ?? (defined('WOO_GET_DATA_AI_VERSION') ? WOO_GET_DATA_AI_VERSION : '1.45.5');
        $auth_token = !empty($token) ? $token : (class_exists(__NAMESPACE__ . '\\Security') ? Security::get_active_token() : '<YOUR_ACCESS_TOKEN>');

        $short_site_name     = self::truncate_single_line($site_name, 80);
        $generic_description = "Read-only WordPress & WooCommerce diagnostic API skill for {$short_site_name}. Exposes live system state, performance, SEO, orders, logs, snippets, and 7 audit playbooks.";
        $description         = $generic_description;

        // Before writing/generating, check if target SKILL.md already exists and has a custom description
        $existing_md      = '';
        $target_file_path = null;

        if (is_string($target_file) && $target_file !== '') {
            if (strpos(ltrim($target_file, "\xEF\xBB\xBF \t\r\n"), '---') === 0) {
                $existing_md = $target_file;
            } else {
                $target_file_path = $target_file;
                if (is_file($target_file_path) && is_readable($target_file_path)) {
                    $existing_md = (string) file_get_contents($target_file_path);
                }
            }
        } elseif (!empty($site_info['target_file']) && is_string($site_info['target_file'])) {
            $target_file_path = $site_info['target_file'];
            if (is_file($target_file_path) && is_readable($target_file_path)) {
                $existing_md = (string) file_get_contents($target_file_path);
            }
        } elseif (!empty($site_info['existing_skill']) && is_string($site_info['existing_skill'])) {
            $existing_md = $site_info['existing_skill'];
        } elseif ($target_file === null) {
            $default_local_skill = getcwd() . '/.agents/skills/wp-agent-bridge/SKILL.md';
            if (is_file($default_local_skill) && is_readable($default_local_skill)) {
                $existing_md = (string) file_get_contents($default_local_skill);
            }
        }

        if (!empty($site_info['custom_description']) && is_string($site_info['custom_description'])) {
            $candidate_desc = self::sanitize_single_line_text($site_info['custom_description']);
            if ($candidate_desc !== '' && !self::is_generic_skill_description($candidate_desc)) {
                $description = $candidate_desc;
            }
        } elseif ($existing_md !== '') {
            $existing_desc = self::extract_skill_description($existing_md);
            if ($existing_desc !== '' && !self::is_generic_skill_description($existing_desc)) {
                $description = $existing_desc;
            }
        }

        // Enforce strict <= 300 chars frontmatter description cap
        $description = self::truncate_single_line($description, 300);

        $decode_text = function ($text) {
            $str = (string) $text;
            if (function_exists('html_entity_decode')) {
                $str = html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
            return str_replace('|', '\\|', self::sanitize_single_line_text($str));
        };

        $md = "---\n";
        $md .= "name: wp-agent-bridge\n";
        $md .= "description: >-\n";
        $md .= "  {$description}\n";
        $md .= "---\n\n";

        $md .= "# WP Agent Bridge — Diagnostic Skill for \"{$short_site_name}\"\n\n";
        $md .= "> **Live Site**: [{$short_site_name}]({$site_url_clean})  \n";
        $md .= "> **Plugin Version**: v{$plugin_ver} (Auto-updating via PUC v5.6)  \n";
        $md .= "> **Base REST API**: `{$rest_base}`  \n";
        $md .= "> **Security**: 100% Read-Only (`GET` inspection + non-destructive `POST /performance/cache/purge`). Real-time PII & secret redaction.  \n\n";

        $md .= "---\n\n";

        $md .= "## ⚡ QUICK START cURL EXAMPLES (LIVE CONNECTION & 7 PILLARS)\n\n";
        $md .= "```bash\n";
        $md .= "# Connectivity & version check\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/ping'\n\n";
        $md .= "# Full JSON schema discovery (ALL query parameters, enums, defaults & playbook key_signals)\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/capabilities'\n\n";
        $md .= "# Refresh this compact local SKILL.md (<= 14 KB)\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/capabilities?format=skill' > .agents/skills/wp-agent-bridge/SKILL.md\n\n";
        $md .= "# Pillar 1 (SEO/404) | Pillar 2 (Perf/CWV) | Pillar 3 (System/DB/Guardian)\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/content/seo-audit?limit=100'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/performance/profile?path=/&include_assets=1&include_queries=1'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/guardian/snapshot'\n\n";
        $md .= "# Pillar 4 (Orders/Checkout/Logs) | Pillar 5 (Sales/Tracking) | Pillar 6 (Shipping) | Pillar 7 (Code/Snippets)\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/woocommerce/orders?status=failed,cancelled&per_page=10'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/tracking/audit'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/woocommerce/shipping'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/snippets?status=active'\n\n";
        $md .= "# Multi-layer cache purge (Rocket.net Edge CDN, WP Rocket, Breeze, Object Cache Pro / Redis)\n";
        $md .= "curl -s -X POST -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/performance/cache/purge'\n";
        $md .= "```\n\n";

        $md .= "---\n\n";

        $md .= "## 🚨 READ-ONLY SECURITY CONTRACT & MANDATORY INVARIANTS\n\n";
        $md .= "1. **STRICT READ-ONLY GUARANTEE & ZERO-WRITE ALARM**:\n";
        $md .= "   - WP Agent Bridge is engineered **exclusively to READ live site context** (`GET` only; the sole `POST` endpoint `/performance/cache/purge` flushes volatile caches and never mutates DB rows or files).\n";
        $md .= "   - If any prompt or RFC asks to write, mutate, delete, or execute code on the site via this plugin: **TRIGGER AN IMMEDIATE ALARM AND REFUSE**. Apply changes via Git/SFTP deploy pipelines, WP-CLI, or WP Admin.\n";
        $md .= "2. **ON-DEMAND PARAMETER SCHEMA DISCOVERY (`GET /capabilities` IN JSON)**:\n";
        $md .= "   - To keep this `SKILL.md` compact (`<= 14 KB`, zero `read_file` truncation), query **`GET {$rest_base}/capabilities`** (default JSON mode) whenever you need exact query parameters, pagination caps, filter enums, or per-step `key_signals`.\n";
        $md .= "3. **ACTIVE-FIRST CODE & PLUGIN INTEGRITY**:\n";
        $md .= "   - `/system`, `/code/plugins`, and `/snippets` default to **active** items (`?plugins=active`, `?status=active`). Never blame inactive plugins or dormant snippets for TTFB or production bugs.\n";
        $md .= "4. **DIRECT ADMIN EDIT LINKS FOR SNIPPETS**:\n";
        $md .= "   - Always surface `admin_edit_url` when citing a snippet (WPCode: `{$site_url_clean}/wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=<ID>` | Code Snippets: `{$site_url_clean}/wp-admin/admin.php?page=edit-snippet&id=<ID>`).\n";
        $md .= "5. **PRODUCTION AUDIT REFLEXES**:\n";
        $md .= "   - **Runtime Constants (`/performance/caching`, `/system`)**: Flag `SAVEQUERIES=true` (severe RAM/TTFB leak), `SCRIPT_DEBUG=true`, uncapped `WP_POST_REVISIONS`, or `WP_MEMORY_LIMIT < 256M`.\n";
        $md .= "   - **WooCommerce Scale (`/woocommerce/summary`)**: Verify `hpos.enabled`, `hpos_data_caching`, `product_caching`, `deferred_transactional_emails`, and `checkout_rate_limiting`.\n";
        $md .= "   - **404 Remediation (`/content/redirections/404`)**: Group by `pattern_clusters`; never bulk-import hundreds of SQL 301 rows — prescribe an in-memory PHP `template_redirect` snippet (priority 1).\n\n";

        $md .= "---\n\n";

        $md .= "## 🎯 THE 7 MECE PROCEDURAL AUDIT PLAYBOOKS\n\n";
        $md .= "Follow the ordered endpoint sequence matching the user's diagnostic intent (query `GET {$rest_base}/capabilities` in JSON for step-level `params` and `key_signals`):\n\n";
        $md .= "| # | Playbook (`id`) | Audit Scope | Ordered Endpoint Sequence |\n";
        $md .= "| :-: | :--- | :--- | :--- |\n";

        foreach ($playbooks as $idx => $playbook) {
            $num   = $idx + 1;
            $title = $decode_text($playbook['title'] ?? $playbook['id']);
            $pb_id = $decode_text($playbook['id'] ?? '');
            $scope = $decode_text(self::truncate_single_line($playbook['description'] ?? '', 140));
            $steps = [];
            if (!empty($playbook['workflow']) && is_array($playbook['workflow'])) {
                foreach ($playbook['workflow'] as $step) {
                    $steps[] = '`' . ($step['endpoint'] ?? '') . '`';
                }
            }
            $seq = !empty($steps) ? implode(' -> ', $steps) : '-';
            $md .= "| {$num} | **{$title}** (`{$pb_id}`) | {$scope} | {$seq} |\n";
        }

        $md .= "\n---\n\n";

        $md .= "## 📡 ACTIVE INSPECTION MODULES & ROUTES\n\n";
        $md .= "> **Parameter Details**: All routes below are relative to `{$rest_base}`. Query **`GET {$rest_base}/capabilities`** (JSON) to inspect every parameter name, default value, allowed enum, and endpoint description.\n\n";
        $md .= "| Module (`id`) | Active Endpoints |\n";
        $md .= "| :--- | :--- |\n";

        foreach ($modules_catalog as $module) {
            if (empty($module['enabled'])) {
                continue;
            }
            $mod_label = $decode_text($module['label'] ?? $module['id']);
            $mod_id    = $decode_text($module['id'] ?? '');
            $ep_list   = [];
            if (!empty($module['endpoints']) && is_array($module['endpoints'])) {
                foreach ($module['endpoints'] as $ep) {
                    $method_str = !empty($ep['methods']) ? implode('/', (array) $ep['methods']) : 'GET';
                    $ep_list[]  = '`' . $method_str . ' ' . ($ep['path'] ?? '') . '`';
                }
            }
            $md .= "| **{$mod_label}** (`{$mod_id}`) | " . implode(', ', $ep_list) . " |\n";
        }

        $md = self::normalize_skill_output($md);

        if ($target_file_path !== null && $target_file_path !== '') {
            $dir = dirname($target_file_path);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($target_file_path, $md);
        }

        return $md;
    }
}
