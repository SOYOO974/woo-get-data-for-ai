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
            [
                'id'              => 'seo_content_audit',
                'title'           => esc_html__('360° SEO & Content Structure Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Comprehensive audit protocol to identify indexation blockers, missing meta descriptions, title length anomalies, and Gutenberg content structure.', 'woo-get-data-for-ai'),
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
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Site-wide SEO Audit Report', 'woo-get-data-for-ai'),
                        'endpoint'    => '/content/seo-audit',
                        'params'      => ['limit' => 100, 'include_posts' => 'false'],
                        'description' => esc_html__('Detects active SEO plugin (Yoast, Rank Math, SEOPress, AIOSEO), global visibility flag, critical noindex warnings, and pages with missing SEO titles or meta descriptions.', 'woo-get-data-for-ai'),
                        'key_signals' => ['critical_warnings', 'missing_descriptions', 'title_length_issues', 'og_image_coverage'],
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
                        'params'      => ['id' => '<targeted_page_id>'],
                        'description' => esc_html__('For pages flagged in step 1 or 2: inspects full Gutenberg block tree, shortcodes, heading structure, word count, and canonical URL.', 'woo-get-data-for-ai'),
                        'key_signals' => ['blocks_summary', 'headings_distribution', 'word_count', 'seo_metadata'],
                    ],
                ],
            ],
            [
                'id'              => 'tech_health_crons',
                'title'           => esc_html__('Technical Health, Crons & Background Tasks Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Audits server environment, PHP/MySQL versions, memory limits, database autoload bloat, security hardening, stalled Action Scheduler queues, overdue WP-Crons, and critical PHP errors.', 'woo-get-data-for-ai'),
                'required_modules'=> ['system'],
                'optional_modules'=> ['scheduler', 'logs', 'wc_overrides'],
                'intent_triggers' => [
                    'audit technique',
                    'santé du site',
                    'site lent',
                    'cron bloqué',
                    'action scheduler',
                    'erreur php',
                    'fatal error',
                    'crash wordpress',
                    'server limits',
                    'autoload bloat',
                    'sécurité wordpress',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Server Environment & Outdated Plugins', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system',
                        'params'      => [],
                        'description' => esc_html__('Inspects PHP version, memory limits (wp_memory_limit), MySQL version, HPOS status, active plugins list, and pending core/plugin updates.', 'woo-get-data-for-ai'),
                        'key_signals' => ['environment.php_version', 'environment.wp_memory_limit', 'plugins_with_updates', 'woocommerce.hpos_enabled'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Database Health & Autoload Footprint', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system/database',
                        'params'      => [],
                        'description' => esc_html__('Audits total database size, top 15 heaviest tables, autoload options size (alert if >800KB), and expired transients.', 'woo-get-data-for-ai'),
                        'key_signals' => ['autoload_health.total_size', 'autoload_health.status', 'autoload_health.top_heavy_options', 'transients_health.expired_transients'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Security Hardening & Protection Audit', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system/security',
                        'params'      => [],
                        'description' => esc_html__('Audits constants (DISALLOW_FILE_EDIT, WP_DEBUG_DISPLAY), XML-RPC exposure, SSL enforcement, and active caching/security plugins.', 'woo-get-data-for-ai'),
                        'key_signals' => ['health', 'constants.disallow_file_edit', 'constants.wp_debug_display', 'recommendations'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Action Scheduler Queue Diagnostics', 'woo-get-data-for-ai'),
                        'endpoint'    => '/action-scheduler',
                        'params'      => ['status' => 'in-progress,failed,pending', 'per_page' => 30],
                        'description' => esc_html__('Examines queued background jobs, recurring scheduled tasks (subscriptions, webhooks, inventory sync), and error logs of failed actions.', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.failed_count', 'summary.in_progress_count', 'actions[].log_messages'],
                    ],
                    [
                        'step'        => 5,
                        'action'      => esc_html__('WP-Cron Overdue Detection', 'woo-get-data-for-ai'),
                        'endpoint'    => '/crons',
                        'params'      => ['status' => 'overdue', 'limit' => 50],
                        'description' => esc_html__('Checks for overdue WP-Cron events that indicate a stalled or misconfigured cron runner.', 'woo-get-data-for-ai'),
                        'key_signals' => ['overdue_count', 'crons[].overdue_by_seconds'],
                    ],
                    [
                        'step'        => 6,
                        'action'      => esc_html__('Crash Watch Fatal Error Dashboard', 'woo-get-data-for-ai'),
                        'endpoint'    => '/logs/errors-summary',
                        'params'      => [],
                        'description' => esc_html__('Scans debug.log memory-safely and clusters recurring Fatal Errors by root cause file, line, and frequency.', 'woo-get-data-for-ai'),
                        'key_signals' => ['total_fatal_errors', 'grouped_errors[].file', 'grouped_errors[].occurrences', 'grouped_errors[].last_seen'],
                    ],
                ],
            ],
            [
                'id'              => 'ecommerce_troubleshoot',
                'title'           => esc_html__('Checkout, Failed Orders & Payment Gateway Diagnostics', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Investigates payment failures, abandoned checkouts, coupon glitches, missing confirmation emails, and failing webhooks with complete GDPR PII anonymization.', 'woo-get-data-for-ai'),
                'required_modules'=> ['woocommerce'],
                'optional_modules'=> ['system', 'logs', 'wpcode', 'wc_overrides'],
                'intent_triggers' => [
                    'commande échouée',
                    'problème paiement',
                    'bug checkout',
                    'panier bloqué',
                    'failed order',
                    'erreur stripe',
                    'erreur paypal',
                    'diagnostiquer commande',
                    'email commande non reçu',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Fetch Recent Failed & Processing Orders', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/orders',
                        'params'      => ['status' => 'failed', 'per_page' => 5],
                        'description' => esc_html__('Lists recent failed orders with GDPR-masked customer data, total amounts, and payment method used.', 'woo-get-data-for-ai'),
                        'key_signals' => ['orders[].id', 'orders[].payment_method', 'orders[].total'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Inspect Targeted Order Notes, Gateway Responses & Shipping Meta', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/order/{id}',
                        'params'      => ['id' => '<failed_order_id>'],
                        'description' => esc_html__('Deep inspection of order notes containing raw payment gateway decline reasons, refund logs, coupon lines, sanitized fees, and shipping line metadata (including Flexible Shipping fs_costs base & additional costs).', 'woo-get-data-for-ai'),
                        'key_signals' => ['order_notes (gateway response messages)', 'coupon_lines', 'fee_lines', 'shipping_lines[].meta_data', 'shipping_lines[].meta_data.fs_costs'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Check WooCommerce Gateway Logs', 'woo-get-data-for-ai'),
                        'endpoint'    => '/logs/view',
                        'params'      => ['filter' => 'wc-', 'lines' => 150],
                        'description' => esc_html__('Inspects WooCommerce upload logs for recent gateway webhook failures or API communication errors.', 'woo-get-data-for-ai'),
                        'key_signals' => ['log entries from stripe, paypal, or custom gateways'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Audit Active Code Snippets Touching Checkout', 'woo-get-data-for-ai'),
                        'endpoint'    => '/wpcode/snippets',
                        'params'      => ['status' => 'active'],
                        'description' => esc_html__('Scans active WPCode snippets to detect any custom PHP/JS altering woocommerce_checkout_* or woocommerce_payment_* hooks.', 'woo-get-data-for-ai'),
                        'key_signals' => ['snippets[].code', 'snippets[].location', 'snippets[].tags'],
                    ],
                    [
                        'step'        => 5,
                        'action'      => esc_html__('SMTP & Order Confirmation Email Diagnostics', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system/mail',
                        'params'      => [],
                        'description' => esc_html__('Checks if transactional emails are sent via authenticated SMTP or failing PHP mail, and inspects recent delivery errors.', 'woo-get-data-for-ai'),
                        'key_signals' => ['health', 'active_plugin', 'transport_provider', 'recent_failures'],
                    ],
                    [
                        'step'        => 6,
                        'action'      => esc_html__('WooCommerce Webhooks Delivery Health', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/webhooks',
                        'params'      => [],
                        'description' => esc_html__('Verifies whether order-triggered webhooks are disabled or accumulating delivery failures.', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.failing_count', 'webhooks[].is_failing', 'webhooks[].failure_count'],
                    ],
                ],
            ],
            [
                'id'              => 'store_analytics_roi',
                'title'           => esc_html__('Store Performance, Sales & Conversion Funnel Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Correlates web traffic, WooCommerce conversion rates, marketing campaign (UTM) ROI, and device behaviors without third-party tracking scripts.', 'woo-get-data-for-ai'),
                'required_modules'=> ['analytics'],
                'optional_modules'=> ['woocommerce'],
                'intent_triggers' => [
                    'rapport ventes',
                    'statistiques site',
                    'taux de conversion',
                    'roi campagnes',
                    'analyse trafic',
                    'store metrics',
                    'conversion funnel',
                    'panier moyen',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('360° Traffic & Conversion Executive Overview', 'woo-get-data-for-ai'),
                        'endpoint'    => '/analytics/overview',
                        'params'      => ['range' => 'last_30_days'],
                        'description' => esc_html__('Consolidated overview of total visitors, views, net sales, WooCommerce conversion rate, top pages, referrers, and devices in 1 call.', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.conversion_rate', 'summary.net_sales', 'summary.average_order_value', 'summary.growth_percent'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('High-Level Store Inventory & Order Status Health', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/summary',
                        'params'      => [],
                        'description' => esc_html__('Total products, stock health (out of stock, backorder), and distribution of orders by status (processing, on-hold, completed).', 'woo-get-data-for-ai'),
                        'key_signals' => ['stock_breakdown.outofstock', 'orders_by_status.processing', 'orders_by_status.failed'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Marketing Campaigns & Acquisition Channels ROI', 'woo-get-data-for-ai'),
                        'endpoint'    => '/analytics/campaigns',
                        'params'      => ['range' => 'last_30_days', 'limit' => 25],
                        'description' => esc_html__('Attributes visitors, orders, and generated revenue to specific UTM campaigns (source, medium, campaign name).', 'woo-get-data-for-ai'),
                        'key_signals' => ['campaigns[].orders', 'campaigns[].net_sales', 'campaigns[].conversion_rate'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Mobile vs Desktop Conversion Disparity', 'woo-get-data-for-ai'),
                        'endpoint'    => '/analytics/devices',
                        'params'      => ['range' => 'last_30_days'],
                        'description' => esc_html__('Compares conversion rates between Desktop, Mobile, and Tablet to pinpoint mobile checkout UX friction.', 'woo-get-data-for-ai'),
                        'key_signals' => ['device_types[].conversion_rate', 'device_types[].visitors'],
                    ],
                ],
            ],
            [
                'id'              => 'integration_automation_map',
                'title'           => esc_html__('Architecture, Workflows & Custom Fields Mapping', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Maps all site integrations: Elementor forms, active FlowMattic automation recipes, ACF custom field schemas, and custom PHP hooks.', 'woo-get-data-for-ai'),
                'required_modules'=> ['elementor'],
                'optional_modules'=> ['flowmattic', 'meta', 'wpcode'],
                'intent_triggers' => [
                    'cartographie technique',
                    'formulaires elementor',
                    'workflows flowmattic',
                    'champs personnalisés',
                    'acf schema',
                    'webhooks actifs',
                    'architecture site',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Elementor Forms & Webhook Inventory', 'woo-get-data-for-ai'),
                        'endpoint'    => '/elementor/forms',
                        'params'      => [],
                        'description' => esc_html__('Maps all form widgets, field identifiers, email recipients, and webhook destination URLs across the site.', 'woo-get-data-for-ai'),
                        'key_signals' => ['forms[].form_name', 'forms[].webhooks', 'forms[].fields'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Active FlowMattic Automation Workflows', 'woo-get-data-for-ai'),
                        'endpoint'    => '/flowmattic/workflows',
                        'params'      => ['status' => 'active'],
                        'description' => esc_html__('Lists all automated business workflows, triggers (WooCommerce order, form submission, webhook), and action chains.', 'woo-get-data-for-ai'),
                        'key_signals' => ['workflows[].workflow_title', 'workflows[].trigger_type', 'workflows[].actions_count'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Custom Fields & ACF Architecture', 'woo-get-data-for-ai'),
                        'endpoint'    => '/meta/fields',
                        'params'      => ['source' => 'all', 'include_db' => 'false'],
                        'description' => esc_html__('Discovers all metadata fields defined in code (register_post_meta) and in ACF field groups with type declarations.', 'woo-get-data-for-ai'),
                        'key_signals' => ['fields[].meta_key', 'fields[].source', 'fields[].type', 'fields[].post_types'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Custom Live Code Snippets', 'woo-get-data-for-ai'),
                        'endpoint'    => '/wpcode/snippets',
                        'params'      => ['status' => 'active'],
                        'description' => esc_html__('Audits custom code snippets executing in production, location hooks, and conditional logic.', 'woo-get-data-for-ai'),
                        'key_signals' => ['snippets[].title', 'snippets[].code_type', 'snippets[].location'],
                    ],
                ],
            ],
            [
                'id'              => 'theme_wc_compatibility',
                'title'           => esc_html__('Theme Settings & WooCommerce Template Overrides Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Audits child theme code, Woodmart/Elessi theme options, and detects outdated WooCommerce template overrides.', 'woo-get-data-for-ai'),
                'required_modules'=> ['wc_overrides'],
                'optional_modules'=> ['theme'],
                'intent_triggers' => [
                    'audit theme',
                    'template overrides',
                    'templates obsolètes',
                    'woodmart options',
                    'elessi options',
                    'child theme functions',
                    'incompatibilité woocommerce',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('WooCommerce Template Overrides Version Drift', 'woo-get-data-for-ai'),
                        'endpoint'    => '/theme/overrides',
                        'params'      => [],
                        'description' => esc_html__('Compares active theme template overrides against the installed WooCommerce core templates to pinpoint version mismatches.', 'woo-get-data-for-ai'),
                        'key_signals' => ['has_outdated', 'outdated_count', 'overrides[].file', 'overrides[].version', 'overrides[].core_version'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Child Theme Code & Custom CSS', 'woo-get-data-for-ai'),
                        'endpoint'    => '/theme/child',
                        'params'      => [],
                        'description' => esc_html__('Fetches active child theme functions.php and style.css source code and metadata.', 'woo-get-data-for-ai'),
                        'key_signals' => ['functions_php.code', 'style_css.code'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Theme Configuration & Options', 'woo-get-data-for-ai'),
                        'endpoint'    => '/theme/options',
                        'params'      => ['target' => 'all'],
                        'description' => esc_html__('Decoded theme options (Woodmart, Elessi/Redux, Customizer mods) with API keys and secrets redacted.', 'woo-get-data-for-ai'),
                        'key_signals' => ['theme_type', 'options'],
                    ],
                ],
            ],
            [
                'id'              => 'store_sales_stock_audit',
                'title'           => esc_html__('Native E-Commerce Sales, Top Performers & Stock Valuation Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('100% native WooCommerce sales reporting, revenue growth vs prior period, top products and coupons, and inventory valuation without external tracking.', 'woo-get-data-for-ai'),
                'required_modules'=> ['woocommerce'],
                'optional_modules'=> [],
                'intent_triggers' => [
                    'rapport ventes',
                    'chiffre d affaires',
                    'top ventes',
                    'meilleures ventes',
                    'valorisation stock',
                    'stock dormant',
                    'kpis ecommerce',
                    'panier moyen',
                    'croissance ventes',
                    'sales analytics',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Native WooCommerce Sales Performance', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/analytics/sales',
                        'params'      => ['range' => 'last_30_days'],
                        'description' => esc_html__('Net sales, gross sales, paid orders count, AOV, refunds, and growth percentages compared to previous 30 days period.', 'woo-get-data-for-ai'),
                        'key_signals' => ['kpis.net_sales', 'kpis.orders_count', 'kpis.average_order_value', 'growth_vs_previous.net_sales_growth_pct'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Top Performing Products & Coupons', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/analytics/top-performers',
                        'params'      => ['limit' => 10, 'range' => 'last_30_days'],
                        'description' => esc_html__('Identifies best-selling catalog items by net revenue and units sold, and most redeemed coupons with discount totals.', 'woo-get-data-for-ai'),
                        'key_signals' => ['top_products[].net_revenue', 'top_products[].units_sold', 'top_coupons[].total_discount'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Inventory Financial Valuation & Dormant Stock', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/analytics/stock',
                        'params'      => [],
                        'description' => esc_html__('Computes total stock valuation, urgent low stock alerts, and capital locked in dormant stock (0 sales in last 90 days).', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.total_inventory_value', 'summary.low_stock_count', 'summary.dormant_items_count', 'dormant_stock_90d[].locked_capital'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Catalog & Order Status Distribution', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/summary',
                        'params'      => [],
                        'description' => esc_html__('Global catalog breakdown by product type and order pipeline status (processing, on-hold, completed, failed).', 'woo-get-data-for-ai'),
                        'key_signals' => ['orders_by_status', 'stock_breakdown'],
                    ],
                ],
            ],
            [
                'id'              => 'email_webhook_diagnostics',
                'title'           => esc_html__('Transactional Emails & Webhooks Integrations Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Diagnoses order confirmation delivery failures (FluentSMTP, WP Mail SMTP, PHP mail) and automated ERP/CRM webhook integration dropouts.', 'woo-get-data-for-ai'),
                'required_modules'=> ['system'],
                'optional_modules'=> ['woocommerce', 'scheduler', 'logs'],
                'intent_triggers' => [
                    'problème email',
                    'email non reçu',
                    'diagnostic smtp',
                    'échec webhook',
                    'webhook woocommerce',
                    'synchronisation erp',
                    'mail delivery failed',
                    'fluent smtp',
                    'wp mail smtp',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('SMTP Provider & Delivery Failures Diagnostic', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system/mail',
                        'params'      => [],
                        'description' => esc_html__('Detects active mail transport provider (FluentSMTP, WP Mail SMTP, Post SMTP), checks for spam-risky unauthenticated PHP mail, and extracts recent delivery errors.', 'woo-get-data-for-ai'),
                        'key_signals' => ['health', 'transport_provider', 'is_authenticated', 'recent_failures', 'alerts'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('WooCommerce Webhooks Inventory & Failure Count', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/webhooks',
                        'params'      => [],
                        'description' => esc_html__('Audits WooCommerce webhook statuses and flags repeated distribution failures (failure count >= 5 or disabled).', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.failing_count', 'summary.health', 'webhooks[].delivery_url', 'webhooks[].failure_count'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Action Scheduler Delivery Tasks Queue', 'woo-get-data-for-ai'),
                        'endpoint'    => '/action-scheduler',
                        'params'      => ['status' => 'in-progress,failed,pending', 'per_page' => 30],
                        'description' => esc_html__('Inspects background delivery tasks for webhooks, email queues, and third-party synchronization jobs.', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.failed_count', 'actions[].log_messages'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Mail & Webhook Error Logs Inspection', 'woo-get-data-for-ai'),
                        'endpoint'    => '/logs/view',
                        'params'      => ['filter' => 'mail', 'lines' => 150],
                        'description' => esc_html__('Streams error logs to isolate exact SMTP error codes, network timeouts, or remote HTTP webhook 4xx/5xx rejection responses.', 'woo-get-data-for-ai'),
                        'key_signals' => ['lines', 'matched_errors'],
                    ],
                ],
            ],
            [
                'id'              => 'code_sync_drift_audit',
                'title'           => esc_html__('Code Drift Verification & Extension Synchronization', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Instant drift detection between local workspace and production site via directory checksum fingerprints, and 1-call clean ZIP archive export of plugins and child themes.', 'woo-get-data-for-ai'),
                'required_modules'=> ['code'],
                'optional_modules'=> ['theme'],
                'intent_triggers' => [
                    'comparer code local prod',
                    'verifier derive code',
                    'code drift',
                    'telecharger plugin',
                    'recuperer theme enfant',
                    'synchroniser extension',
                    'audit checksums',
                    'exporter code plugin',
                    'synchroniser plugin custom',
                    'sauvegarde plugin',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Fingerprint Directory Checksums (Local vs Remote Drift)', 'woo-get-data-for-ai'),
                        'endpoint'    => '/code/checksums',
                        'params'      => ['path' => 'plugins/<plugin_slug>', 'algo' => 'md5'],
                        'description' => esc_html__('Generates a hash map of all files with modified dates and byte sizes. Compare against local workspace files to detect altered, added, or missing files before any editing.', 'woo-get-data-for-ai'),
                        'key_signals' => ['checksums.<filename>.hash', 'checksums.<filename>.size_bytes', 'total_files', 'total_size_bytes'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Export Complete Plugin or Child Theme ZIP Archive', 'woo-get-data-for-ai'),
                        'endpoint'    => '/code/zip',
                        'params'      => ['path' => 'plugins/<plugin_slug>', 'format' => 'stream'],
                        'description' => esc_html__('If drift is detected or local files are missing, downloads a complete, clean ZIP archive without .git, logs, or sensitive files directly in 1 single call.', 'woo-get-data-for-ai'),
                        'key_signals' => ['archive_name', 'size_bytes', 'stream_binary'],
                    ],
                ],
            ],
            [
                'id'              => 'shipping_logistics_audit',
                'title'           => esc_html__('Shipping Zones, Methods & Flexible Shipping Rules Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Comprehensive logistics audit: inspects WooCommerce shipping zones, geo-locations (postcodes, regions, countries), native methods (flat rate, free shipping threshold), and advanced Flexible Shipping PRO matrix calculation rules (weight/price tiers, shipping classes).', 'woo-get-data-for-ai'),
                'required_modules'=> ['woocommerce'],
                'optional_modules'=> ['system'],
                'intent_triggers' => [
                    'audit livraison',
                    'frais de port',
                    'shipping rules',
                    'flexible shipping',
                    'zones de livraison',
                    'tarifs livraison',
                    'conditions port gratuit',
                    'modes de livraison',
                    'frais dexpédition',
                    'table rate shipping',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Dedicated Shipping Zones & Matrix Rules Inspection', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/shipping',
                        'params'      => [],
                        'description' => esc_html__('Inspects all configured shipping zones, geographic location filters (postcodes, countries, states), native method parameters, and Flexible Shipping matrix rules (conditions, costs, weight/price tiers, special actions, and flat_rate table rate extensions).', 'woo-get-data-for-ai'),
                        'key_signals' => ['zones[].zone_name', 'zones[].locations', 'zones[].shipping_methods[].id', 'zones[].shipping_methods[].flexible_shipping.rules', 'zones[].shipping_methods[].flexible_shipping_table_rate.rules', 'zones[].shipping_methods[].raw_instance_settings'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Store Currency & Tax on Shipping Configuration', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/settings',
                        'params'      => [],
                        'description' => esc_html__('Verifies tax calculation on shipping (tax_based_on, shipping_tax_class), default shipping country, and currency formatting.', 'woo-get-data-for-ai'),
                        'key_signals' => ['general.currency', 'general.ship_to_countries', 'tax.shipping_tax_class', 'tax.calc_taxes'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Recent Orders Shipping Method Verification', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/orders',
                        'params'      => ['per_page' => 10],
                        'description' => esc_html__('Reviews recent orders to observe which shipping methods and shipping fees were applied in practice.', 'woo-get-data-for-ai'),
                        'key_signals' => ['orders[].shipping_total', 'orders[].shipping_lines'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Order Shipping Line Meta & Granular Cost Breakdown Inspection', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/order/{id}',
                        'params'      => ['id' => '<order_id>'],
                        'description' => esc_html__('Deep inspection of shipping lines meta_data to audit granular fee calculations (e.g. Flexible Shipping PRO fs_costs base & additional costs, package item weights).', 'woo-get-data-for-ai'),
                        'key_signals' => ['shipping_lines[].meta_data', 'shipping_lines[].meta_data.fs_costs.base', 'shipping_lines[].meta_data.fs_costs.additional'],
                    ],
                ],
            ],
            [
                'id'              => 'agency_performance_audit',
                'title'           => esc_html__('Agency Performance, Multi-Template TTFB & Native Web Vitals Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Comprehensive diagnostic and prescription protocol for agencies and AI agents: audits 5 strategic e-commerce page archetypes (Home, Shop, Category, Product, Cart), attributes SQL queries and enqueued assets per plugin, inspects 100% native Core Web Vitals signals (DOM size, CLS missing dimensions, legacy image formats, Google Fonts display=swap, core script bloat), detects autoload leaks, and formulates quantified Quick Wins with ready-to-use WPCode snippets.', 'woo-get-data-for-ai'),
                'required_modules'=> ['performance'],
                'optional_modules'=> ['system', 'scheduler', 'logs'],
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
                    'consommation ressources plugins',
                    'page speed woocommerce',
                    'core web vitals',
                    'pagespeed',
                    'quick wins performance',
                    'audit boutique lente',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Strategic Multi-Template URLs Discovery', 'woo-get-data-for-ai'),
                        'endpoint'    => '/performance/templates-urls',
                        'params'      => [],
                        'description' => esc_html__('Discovers and resolves representative URLs for 5 key e-commerce page archetypes: Homepage (/), Shop (/shop/), Product Category, Single Product, and Cart/Checkout.', 'woo-get-data-for-ai'),
                        'key_signals' => ['templates.home.url', 'templates.shop.url', 'templates.category.url', 'templates.product.url', 'templates.cart.url'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Multi-Template Benchmarking & Plugin SQL Attribution', 'woo-get-data-for-ai'),
                        'endpoint'    => '/performance/profile',
                        'params'      => ['path' => '<template_url>', 'include_assets' => true, 'include_queries' => true, 'slow_query_threshold_ms' => 50],
                        'description' => esc_html__('Profiles each key template archetype: attributes SQL queries and duration per plugin via stack backtraces, detects duplicate/slow queries (>50ms), and measures TTFB and peak memory.', 'woo-get-data-for-ai'),
                        'key_signals' => ['profile.ttfb_ms', 'profile.memory_peak_mb', 'profile.sql.total_queries', 'profile.sql.by_component', 'profile.sql.duplicate_queries', 'profile.sql.slow_queries'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Native Web Vitals & Frontend Performance Signals Audit', 'woo-get-data-for-ai'),
                        'endpoint'    => '/performance/profile',
                        'params'      => ['path' => '<template_url>', 'include_assets' => true, 'include_queries' => false],
                        'description' => esc_html__('Performs 100% native server-side Core Web Vitals & frontend diagnostics on the rendered HTML: DOM size & Elementor bloat, images missing dimensions (CLS), legacy PNG/JPEG images (WebP/AVIF recommendation), render-blocking resources, Google Fonts swap status, core script bloat (emojis, embeds, migrate, dashicons), and WooCommerce cart fragments.', 'woo-get-data-for-ai'),
                        'key_signals' => ['pagespeed_audits.dom_health', 'pagespeed_audits.cls_image_dimensions', 'pagespeed_audits.image_formats', 'pagespeed_audits.render_blocking_in_head', 'pagespeed_audits.google_fonts', 'pagespeed_audits.core_bloat', 'pagespeed_audits.woocommerce_cart_fragments'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Autoload Bloat & Early-Boot Options Audit', 'woo-get-data-for-ai'),
                        'endpoint'    => '/performance/autoload',
                        'params'      => ['limit' => 25],
                        'description' => esc_html__('Audits total wp_options autoload footprint against the 800 KB threshold, lists top 25 heaviest individual options, groups autoload by plugin prefix, and detects expired transients.', 'woo-get-data-for-ai'),
                        'key_signals' => ['status', 'total_size_kb', 'alert', 'top_heavy_options', 'by_component'],
                    ],
                    [
                        'step'        => 5,
                        'action'      => esc_html__('Background Cron & Action Scheduler Queue Health', 'woo-get-data-for-ai'),
                        'endpoint'    => '/action-scheduler',
                        'params'      => ['status' => 'in-progress,failed,pending', 'per_page' => 30],
                        'description' => esc_html__('Detects stalled recurring background jobs, failed synchronization queues, or repetitive webhook loops that monopolize server CPU and database locks.', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.failed_count', 'summary.in_progress_count', 'actions[].log_messages'],
                    ],
                    [
                        'step'        => 6,
                        'action'      => esc_html__('Fatal Error & Warning Disk I/O Scan', 'woo-get-data-for-ai'),
                        'endpoint'    => '/logs/errors-summary',
                        'params'      => ['limit' => 15],
                        'description' => esc_html__('Verifies whether recurrent PHP warnings, deprecations, or fatal errors are continuously writing to debug.log and choking disk I/O.', 'woo-get-data-for-ai'),
                        'key_signals' => ['total_fatal_errors', 'grouped_errors[].file', 'grouped_errors[].occurrences'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get active playbooks filtered dynamically by the site's enabled module permissions.
     *
     * If a playbook's mandatory required modules are not all enabled, the playbook is excluded.
     * If optional modules are disabled, the playbook remains included but notes are provided.
     *
     * @return array
     */
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
        if (strpos($endpoint, '/wpcode') === 0) return 'wpcode';
        if (strpos($endpoint, '/logs') === 0) return 'logs';
        if (strpos($endpoint, '/crons') === 0 || strpos($endpoint, '/action-scheduler') === 0) return 'scheduler';
        if (strpos($endpoint, '/flowmattic') === 0) return 'flowmattic';
        if (strpos($endpoint, '/analytics') === 0) return 'analytics';
        if (strpos($endpoint, '/meta') === 0) return 'meta';
        if (strpos($endpoint, '/performance') === 0) return 'performance';

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
        $md .= "2. **ACTIVE VS INACTIVE CODE INTEGRITY**:\n";
        $md .= "   - When diagnosing issues, ALWAYS query live active elements first (`?status=active` or `?status=publish`).\n";
        $md .= "   - Never mistake inactive snippets or drafts for live production code.\n";
        $md .= "3. **WPCODE DIRECT ADMIN LINKS RULE**:\n";
        $md .= "   - Whenever you recommend or analyze a WPCode snippet, ALWAYS provide the user with the direct WordPress Admin edit link:\n";
        $md .= "     `{$site_url}/wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=<ID>`\n";
        $md .= "4. **DYNAMIC FRESHNESS & REFRESH PROTOCOL**:\n";
        $md .= "   - Re-query `GET {$rest_base}/capabilities?format=skill` regularly to detect new inspection capabilities after plugin updates.\n\n";

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
                $md .= "- `GET {$rest_base}" . $ep['path'] . "`" . $params_note . ": " . esc_html($ep['description']) . "\n";
            }
            $md .= "\n";
        }

        $md .= "---\n\n";

        $md .= "## ⚡ QUICK START cURL EXAMPLES\n\n";
        $md .= "```bash\n";
        $md .= "# Check connection & server time\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' {$rest_base}/ping\n\n";
        $md .= "# Refresh full dynamic skill & active playbooks\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/capabilities?format=skill' > .agents/skills/wp-agent-bridge/SKILL.md\n\n";
        $md .= "# Run Playbook 1: Site-wide SEO Audit\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/content/seo-audit?limit=100'\n\n";
        $md .= "# Run Playbook 2: Server & Failed Background Actions\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/action-scheduler?status=failed,in-progress'\n\n";
        $md .= "# Run Playbook 3: Recent Failed Orders (PII Redacted)\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/woocommerce/orders?status=failed&per_page=5'\n\n";
        $md .= "# Run Playbook 8: Verify Plugin Code Drift via Checksums Fingerprint\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/code/checksums?path=plugins/my-plugin'\n\n";
        $md .= "# Run Playbook 8: Download Clean Plugin or Child Theme ZIP Archive\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/code/zip?path=plugins/my-plugin' -o my-plugin.zip\n\n";
        $md .= "# Run Playbook 11: Agency Multi-Template Performance & Native Web Vitals Audit\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/performance/templates-urls'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/performance/profile?path=/&include_assets=true&include_queries=true'\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/performance/autoload?limit=25'\n";
        $md .= "```\n";

        return $md;
    }
}
