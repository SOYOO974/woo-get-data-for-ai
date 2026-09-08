<?php
namespace WPAgentBridge;

if (!defined('ABSPATH')) {
    exit;
}

class Permissions {

    /**
     * Get module definitions with human-readable labels and descriptions.
     *
     * @return array
     */
    public static function get_module_definitions() {
        return [
            'system' => [
                'label'       => esc_html__('System & Environment', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting WordPress, PHP, MySQL versions, active plugins list, server limits, Action Scheduler crons, database autoload footprint, SMTP mail diagnostic, security hardening, and caching configuration.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/ping', '/capabilities', '/system', '/system/database', '/system/mail', '/system/security', '/system/caching'],
            ],
            'wc_overrides' => [
                'label'       => esc_html__('WooCommerce Diagnostic & Overrides', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows auditing template overrides in active themes, detecting outdated WooCommerce templates, and HPOS status.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/theme/overrides'],
            ],
            'theme' => [
                'label'       => esc_html__('Theme Settings & Child Theme', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows exporting Woodmart (xts-woodmart-options), Elessi (elessi_options) settings, and child theme functions.php/style.css.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/theme/options', '/theme/child'],
            ],
            'code' => [
                'label'       => esc_html__('Code & Plugin File Inspector', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows browsing active plugins/mu-plugins structures, reading sandboxed source code, generating directory checksums, and exporting plugin/theme ZIP archives.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/code/plugins', '/code/file', '/code/checksums', '/code/zip'],
            ],
            'elementor' => [
                'label'       => esc_html__('Elementor Architecture', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows reading Elementor page trees, global kit styles, and mapping form widgets with webhooks.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/elementor/export-all', '/elementor/list', '/elementor/item/{id}', '/elementor/forms', '/elementor/kit'],
            ],
            'wpcode' => [
                'label'       => esc_html__('Code Snippets (WPCode & Code Snippets Pro)', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows listing and inspecting custom PHP, JS, CSS, and HTML snippets stored in WPCode and Code Snippets (Free/Pro) plugins.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/snippets', '/snippets/{id}', '/wpcode/snippets', '/wpcode/snippet/{id}'],
            ],
            'logs' => [
                'label'       => esc_html__('Error & WooCommerce Logs', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows listing and tail-reading debug.log, uploads/wc-logs/*.log, and custom wp-content/ logs with memory protection.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/logs/sources', '/logs/view', '/logs/custom', '/logs/errors-summary'],
            ],
            'scheduler' => [
                'label'       => esc_html__('WP-Cron & Action Scheduler', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting registered WP-Cron schedules, intervals, overdue jobs, and Action Scheduler queues (in-progress, failed, pending tasks).', 'woo-get-data-for-ai'),
                'endpoints'   => ['/crons', '/action-scheduler'],
            ],
            'flowmattic' => [
                'label'       => esc_html__('FlowMattic Workflows', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows listing and exporting FlowMattic workflows, steps, triggers, and configurations in native JSON format.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/flowmattic/export-all', '/flowmattic/workflows', '/flowmattic/workflow/{id}'],
            ],
            'analytics' => [
                'label'       => esc_html__('Independent Analytics (Visits & Conversion Rates)', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting site visit statistics, traffic channels, UTM campaigns, device breakdowns, and WooCommerce conversion rates tracked by Independent Analytics.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/analytics/overview', '/analytics/summary', '/analytics/pages', '/analytics/referrers', '/analytics/campaigns', '/analytics/devices', '/analytics/geo', '/analytics/conversions'],
            ],
            'meta' => [
                'label'       => esc_html__('Custom Fields & Meta (ACF & Code)', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting custom meta fields registered in code (register_post_meta), ACF field groups and fields, and database postmeta.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/meta/fields', '/meta/acf', '/meta/post/{id}'],
            ],
            'woocommerce' => [
                'label'       => esc_html__('WooCommerce Store Data (Products, Orders, Settings, Shipping)', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting WooCommerce products, variations, recent orders (anonymized/PII-redacted), store summary, sales analytics, top performers, stock valuation, webhooks, shipping zones/methods and matrix rules, and e-commerce settings.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/woocommerce/summary', '/woocommerce/products', '/woocommerce/product/{id}', '/woocommerce/coupons', '/woocommerce/coupon/{id}', '/woocommerce/orders', '/woocommerce/order/{id}', '/woocommerce/settings', '/woocommerce/shipping', '/woocommerce/analytics/sales', '/woocommerce/analytics/top-performers', '/woocommerce/analytics/stock', '/woocommerce/webhooks'],
            ],
            'content' => [
                'label'       => esc_html__('Pages, Content & SEO', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting WordPress pages and posts hierarchy, rendered and raw Gutenberg block content, templates, and unified SEO metadata (Yoast, Rank Math, SEOPress, AIOSEO).', 'woo-get-data-for-ai'),
                'endpoints'   => ['/content/pages', '/content/page/{id}', '/content/posts', '/content/post/{id}', '/content/seo-audit'],
            ],
            'performance' => [
                'label'       => esc_html__('Site Performance & Plugin Profiler', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows profiling URL response times, attributing SQL queries and duration per plugin, detecting duplicate/slow queries, measuring frontend assets (JS/CSS) footprint per plugin, discovering 5 key template URLs, 100% native server-side Core Web Vitals checks, auditing autoloaded options bloat, and inspecting caching & WP Rocket settings.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/performance/templates-urls', '/performance/profile', '/performance/autoload', '/performance/plugins-summary', '/performance/caching'],
            ],
            'pmpro' => [
                'label'       => esc_html__('Paid Memberships Pro (PMPro)', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting Paid Memberships Pro levels, durations, recurring billing cycles, user membership records, status timeline, and associated PMPro orders.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/pmpro/levels', '/pmpro/members', '/pmpro/member/{user_id}'],
            ],
            'masterstudy' => [
                'label'       => esc_html__('MasterStudy LMS', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting MasterStudy LMS courses, pricing, lesson counts, course durations, user course enrollments, and PMPro subscription link synchronization.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/masterstudy/courses', '/masterstudy/user/{user_id}/courses'],
            ],
        ];
    }

    /**
     * Get stored permissions.
     *
     * @return array
     */
    public static function get_permissions() {
        $defaults = [
            'system'       => 1,
            'wc_overrides' => 1,
            'theme'        => 1,
            'code'         => 1,
            'elementor'    => 1,
            'wpcode'       => 1,
            'logs'         => 1,
            'scheduler'    => 1,
            'flowmattic'   => 1,
            'analytics'    => 1,
            'meta'         => 1,
            'woocommerce'  => 1,
            'content'      => 1,
            'performance'  => 1,
            'pmpro'        => 1,
            'masterstudy'  => 1,
        ];

        $saved = get_option('wp_agent_bridge_permissions', []);
        return wp_parse_args($saved, $defaults);
    }

    /**
     * Check if a specific module is enabled.
     *
     * @param string $module_key
     * @return bool
     */
    public static function is_module_enabled($module_key) {
        $permissions = self::get_permissions();
        return !empty($permissions[$module_key]);
    }

    /**
     * Verify module permission for REST endpoint.
     *
     * @param string $module_key
     * @return true|\WP_Error
     */
    public static function check_module_permission($module_key) {
        if (!self::is_module_enabled($module_key)) {
            $definitions = self::get_module_definitions();
            $module_name = isset($definitions[$module_key]['label']) ? $definitions[$module_key]['label'] : $module_key;

            return new \WP_Error(
                'agent_bridge_module_disabled',
                sprintf(
                    /* translators: %s: Module label */
                    esc_html__("The '%s' module is disabled by the site administrator.", 'woo-get-data-for-ai'),
                    $module_name
                ),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Get detailed capabilities catalog with endpoints, query params, and descriptions.
     *
     * @return array
     */
    public static function get_capabilities_catalog() {
        $permissions = self::get_permissions();

        return [
            [
                'id'          => 'system',
                'label'       => esc_html__('System & Environment', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspection of WP, PHP, MySQL versions, active plugins list, server limits, and Action Scheduler crons.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['system']),
                'endpoints'   => [
                    [
                        'path'        => '/ping',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Quick connectivity, server timestamp, and plugin version check.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/capabilities',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Self-describing API catalog, active modules, and dynamic discovery of inspectable data types.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/system',
                        'methods'     => ['GET'],
                        'params'      => ['plugins (active|inactive|all, default: active)'],
                        'description' => esc_html__('Comprehensive server, WordPress, theme, active plugins list & summary counts (or all installed plugins via ?plugins=all), WooCommerce environment, and Action Scheduler status.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/system/database',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Deep database diagnostic: table sizes, top 15 largest tables, autoload footprint with 800KB threshold, orphaned options analysis from inactive plugins, transient counts, and object cache status.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/system/mail',
                        'methods'     => ['GET'],
                        'description' => esc_html__('SMTP & transactional email diagnostic: active mail plugin (FluentSMTP, WP Mail SMTP, Post SMTP), provider, sanitization of secrets, and recent delivery failures.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/system/security',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Hardening and security audit: DISALLOW_FILE_EDIT, DISALLOW_FILE_MODS, XML-RPC exposure, SSL enforcement, DB prefix, detected security and caching plugins.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/system/caching',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Universal caching & optimization diagnostic: Object Cache (Redis/Memcached), Page Cache drop-in, and in-depth WP Rocket settings (RUCSS vs CPCSS, Delay JS, safelists, lazyload, mobile cache) with security redaction.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'wc_overrides',
                'label'       => esc_html__('WooCommerce Diagnostic & Overrides', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows auditing template overrides in active themes, detecting outdated WooCommerce templates, and HPOS status.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['wc_overrides']),
                'endpoints'   => [
                    [
                        'path'        => '/theme/overrides',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Audits WooCommerce template overrides in active theme with version comparison to core WC.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'theme',
                'label'       => esc_html__('Theme Settings & Child Theme', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows exporting Woodmart (xts-woodmart-options), Elessi (elessi_options) settings, and child theme functions.php/style.css.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['theme']),
                'endpoints'   => [
                    [
                        'path'        => '/theme/options',
                        'methods'     => ['GET'],
                        'params'      => ['target (all|woodmart|elessi|customizer)'],
                        'description' => esc_html__('Decoded theme settings and Customizer theme mods with sensitive keys redacted.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/theme/child',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Full source code and header info of the active child theme functions.php and style.css.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'code',
                'label'       => esc_html__('Code & Plugin File Inspector', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows browsing active plugins and mu-plugins file structures, reading specific files in sandbox, computing directory checksums, and exporting clean ZIP archives.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['code']),
                'endpoints'   => [
                    [
                        'path'        => '/code/plugins',
                        'methods'     => ['GET'],
                        'params'      => ['status (active|inactive|all, default: active)'],
                        'description' => esc_html__('Hierarchical directory and file trees of plugins and wp-content/mu-plugins/.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/code/file',
                        'methods'     => ['GET'],
                        'params'      => ['path (required, e.g. plugins/my-plugin/file.php)'],
                        'description' => esc_html__('Sandboxed source code reader for PHP, JS, and CSS files.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/code/checksums',
                        'methods'     => ['GET'],
                        'params'      => ['path (required, e.g. plugins/my-plugin, themes/woodmart-child)', 'algo (md5|sha256, default: md5)'],
                        'description' => esc_html__('Directory checksum fingerprint map of all code files with modified timestamps and byte sizes for instant local vs prod drift detection.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/code/zip',
                        'methods'     => ['GET'],
                        'params'      => ['path (required, e.g. plugins/my-plugin, themes/woodmart-child)', 'format (stream|base64, default: stream)'],
                        'description' => esc_html__('Generates and streams a clean on-the-fly ZIP archive of a target plugin or child theme directory (excluding .git, logs, and sensitive files).', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'elementor',
                'label'       => esc_html__('Elementor Architecture', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows reading Elementor page trees, global kit styles, and mapping form widgets with webhooks.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['elementor']),
                'endpoints'   => [
                    [
                        'path'        => '/elementor/export-all',
                        'methods'     => ['GET'],
                        'params'      => ['status (all|publish|draft|active|inactive, default: all, recommended: publish)', 'type (all|page|elementor_library|post)', 'page', 'per_page'],
                        'description' => esc_html__('Bulk export of all Elementor pages, templates, kit & forms in 1 optimized HTTP request.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/elementor/list',
                        'methods'     => ['GET'],
                        'params'      => ['status (all|publish|draft|active|inactive, default: all, recommended: publish)', 'type (any|page|post|elementor_library)'],
                        'description' => esc_html__('List all Elementor pages, posts, and saved library templates.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/elementor/item/{id}',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Full decoded _elementor_data JSON tree and page settings for a specific post/template.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/elementor/forms',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Inventory of all Elementor Pro form widgets, field configurations, webhooks, and recipient emails.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/elementor/kit',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Global design system: colors, system fonts, and design tokens from active Elementor Kit.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'wpcode',
                'label'       => esc_html__('Code Snippets (WPCode & Code Snippets Pro)', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows listing and inspecting custom PHP, JS, CSS, and HTML snippets stored in WPCode and Code Snippets (Free/Pro) plugins.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['wpcode']),
                'endpoints'   => [
                    [
                        'path'        => '/snippets',
                        'methods'     => ['GET'],
                        'params'      => ['status (active|inactive|all, default: active)', 'source (all|code-snippets|wpcode, default: all)', 'type (all|php|css|js|html, default: all)'],
                        'description' => esc_html__('List active snippets by default across WPCode and Code Snippets (use ?status=all for full history or ?status=inactive for dormant snippets) with source plugin, execution location, priority, tags, direct admin edit link, and full source code.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/snippets/{id}',
                        'methods'     => ['GET'],
                        'params'      => ['source (all|code-snippets|wpcode, default: all)'],
                        'description' => esc_html__('Full source code, execution location, priority, tags, and direct WordPress Admin edit link for a specific snippet with collision resolution.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/wpcode/snippets',
                        'methods'     => ['GET'],
                        'params'      => ['status (active|inactive|all, default: active)', 'source (all|code-snippets|wpcode, default: all)', 'type (all|php|css|js|html, default: all)'],
                        'description' => esc_html__('Legacy alias: List active snippets by default across WPCode and Code Snippets (?status=all for all).', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/wpcode/snippet/{id}',
                        'methods'     => ['GET'],
                        'params'      => ['source (all|code-snippets|wpcode, default: all)'],
                        'description' => esc_html__('Legacy alias: Full source code and settings for a specific snippet.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'logs',
                'label'       => esc_html__('Error & WooCommerce Logs', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows listing and tail-reading debug.log, uploads/wc-logs/*.log, and custom wp-content/ logs with memory protection.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['logs']),
                'endpoints'   => [
                    [
                        'path'        => '/logs/sources',
                        'methods'     => ['GET'],
                        'description' => esc_html__('List available log files (debug.log, uploads/wc-logs/*.log, custom logs) with sizes and timestamps.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/logs/view',
                        'methods'     => ['GET'],
                        'params'      => ['source (required)', 'lines (default: 100, max: 2000)', 'filter (optional substring filter)'],
                        'description' => esc_html__('Memory-safe reverse tail extraction of last N lines with optional error filtering.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/logs/custom',
                        'methods'     => ['GET'],
                        'params'      => ['file (required, e.g. komela-order-status-sync.log)', 'lines (default: 200, max: 1000)', 'filter (optional substring filter)'],
                        'description' => esc_html__('Memory-safe reverse tail extraction of specific log files in wp-content/ with path sandboxing.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/logs/errors-summary',
                        'methods'     => ['GET'],
                        'params'      => ['limit (default: 15, max: 50)'],
                        'description' => esc_html__('Crash Watch: aggregated and deduplicated recent fatal PHP errors and exceptions from debug.log and wc-logs.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'scheduler',
                'label'       => esc_html__('WP-Cron & Action Scheduler', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting registered WP-Cron schedules, intervals, overdue jobs, and Action Scheduler queues (in-progress, failed, pending tasks).', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['scheduler']),
                'endpoints'   => [
                    [
                        'path'        => '/crons',
                        'methods'     => ['GET'],
                        'params'      => ['status (all|overdue|future)', 'search (hook filter)', 'limit (default: 100, max: 500)'],
                        'description' => esc_html__('List registered WP-Cron jobs, execution timestamps, overdue detection, recurrence intervals, and hook arguments.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/action-scheduler',
                        'methods'     => ['GET'],
                        'params'      => ['status (default: in-progress,failed,pending)', 'hook', 'search', 'group', 'per_page (default: 50)', 'page (default: 1)'],
                        'description' => esc_html__('Inspect Action Scheduler queue with status summary, retention policy in days, scheduled dates, attempts, arguments, and failure log messages.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'flowmattic',
                'label'       => esc_html__('FlowMattic Workflows', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows listing and exporting FlowMattic workflows, steps, triggers, and configurations in native JSON format.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['flowmattic']),
                'endpoints'   => [
                    [
                        'path'        => '/flowmattic/export-all',
                        'methods'     => ['GET'],
                        'params'      => ['status (all|active|inactive, default: all, recommended: active)', 'page', 'per_page'],
                        'description' => esc_html__('Bulk export of all FlowMattic workflows in native importable JSON format in 1 request.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/flowmattic/workflows',
                        'methods'     => ['GET'],
                        'params'      => ['status (all|active|inactive, default: all, recommended: active)', 'search', 'limit', 'offset'],
                        'description' => esc_html__('List all FlowMattic workflows (ID, name, status, trigger, actions, tasks count).', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/flowmattic/workflow/{id}',
                        'methods'     => ['GET'],
                        'params'      => ['format (default: raw, export: native FlowMattic JSON)'],
                        'description' => esc_html__('Single FlowMattic workflow detail or native importable JSON backup.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'analytics',
                'label'       => esc_html__('Independent Analytics (Visits & Conversion Rates)', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting site visit statistics, traffic channels, UTM campaigns, device breakdowns, and WooCommerce conversion rates tracked by Independent Analytics.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['analytics']),
                'endpoints'   => [
                    [
                        'path'        => '/analytics/overview',
                        'methods'     => ['GET'],
                        'params'      => ['range (default: last_30_days)'],
                        'description' => esc_html__('Consolidated 360° traffic & conversion audit in 1 call (summary, top pages, referrers, campaigns, devices).', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/analytics/summary',
                        'methods'     => ['GET'],
                        'params'      => ['range (today|yesterday|last_7_days|last_30_days|this_month|last_month)'],
                        'description' => esc_html__('Traffic KPIs (visitors, views, bounce rate, duration) and WooCommerce conversion rate, net sales, AOV, % growth.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/analytics/pages',
                        'methods'     => ['GET'],
                        'params'      => ['range', 'limit (default: 50)', 'sort (views|visitors|orders|net_sales)'],
                        'description' => esc_html__('Performance & conversion rate per page / WooCommerce product.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/analytics/referrers',
                        'methods'     => ['GET'],
                        'params'      => ['range', 'limit (default: 50)'],
                        'description' => esc_html__('Traffic acquisition sources & referring domains with associated orders, sales, and conversion rates.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/analytics/campaigns',
                        'methods'     => ['GET'],
                        'params'      => ['range', 'limit (default: 50)'],
                        'description' => esc_html__('Marketing UTM campaigns ROI tracking (utm_source, utm_medium, utm_campaign, orders, net_sales).', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/analytics/devices',
                        'methods'     => ['GET'],
                        'params'      => ['range'],
                        'description' => esc_html__('Breakdown and conversion comparison across device types (Desktop vs Mobile vs Tablet), browsers, and OS.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/analytics/geo',
                        'methods'     => ['GET'],
                        'params'      => ['range', 'limit (default: 50)'],
                        'description' => esc_html__('Geographic distribution of visitors and orders by country and city.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/analytics/conversions',
                        'methods'     => ['GET'],
                        'params'      => ['range', 'limit (default: 50)'],
                        'description' => esc_html__('Recent order and conversion stream with attribution (landing page, country, device, browser, amount).', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'meta',
                'label'       => esc_html__('Custom Fields & Meta (ACF & Code)', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting custom meta fields registered in code (register_post_meta), ACF field groups and fields, and database postmeta.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['meta']),
                'endpoints'   => [
                    [
                        'path'        => '/meta/fields',
                        'methods'     => ['GET'],
                        'params'      => ['source (all|code|acf|db, default: all)', 'post_type (e.g. product, post)', 'object_type (all|post|term|user|comment)', 'search', 'include_db (true|false)', 'limit_db (default: 50)'],
                        'description' => esc_html__('Unified catalog of all declared custom meta fields (WordPress code register_post_meta and ACF plugin field groups/fields).', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/meta/acf',
                        'methods'     => ['GET'],
                        'params'      => ['status (all|active|inactive)', 'post_type'],
                        'description' => esc_html__('Deep ACF inspection: field groups, hierarchy, recursive subfields, location rules, and registered options pages.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/meta/post/{id}',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Inspect all metadata for a specific post/product/order (resolved ACF fields, code-registered meta, and full categorized raw postmeta).', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'woocommerce',
                'label'       => esc_html__('WooCommerce Store Data (Products, Orders, Settings)', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting WooCommerce products, variations, recent orders (anonymized/PII-redacted), store summary, and e-commerce settings.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['woocommerce']),
                'endpoints'   => [
                    [
                        'path'        => '/woocommerce/summary',
                        'methods'     => ['GET'],
                        'description' => esc_html__('High-level store health, product counts by status/stock/type, order counts and hygiene analysis (cancellation ratio, stale unpaid orders > 1y), HPOS state, active payment gateways, and shipping zones.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/woocommerce/products',
                        'methods'     => ['GET'],
                        'params'      => ['status (publish|draft|all, default: publish)', 'type (simple|variable|grouped|external|all, default: all)', 'stock_status (instock|outofstock|onbackorder|all, default: all)', 'category (slug)', 'search', 'per_page (default: 20, max: 100)', 'page', 'orderby (default: date)', 'order (DESC|ASC)'],
                        'description' => esc_html__('Paginated WooCommerce products catalog with SKU, prices, stock, categories, tags, and attributes.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/woocommerce/product/{id}',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Detailed product inspection including variations breakdown, dimensions, images, and sanitized postmeta custom fields.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/woocommerce/coupons',
                        'methods'     => ['GET'],
                        'params'      => ['status (active|expired|exhausted|all, default: all)', 'type (fixed_cart|percent|fixed_product|all, default: all)', 'search', 'email', 'per_page (default: 20, max: 100)', 'page', 'orderby (date|code|usage_count|modified, default: date)', 'order (DESC|ASC)'],
                        'description' => esc_html__('List and filter WooCommerce promotional coupons with status, expiration, usage counts, limits, held count, and PII-masked email restrictions.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/woocommerce/coupon/{id}',
                        'methods'     => ['GET'],
                        'params'      => ['id (numeric ID or coupon code slug, required)'],
                        'description' => esc_html__('Deep inspection of a single coupon: discount rules, real-time availability (is_valid_now, usage_left), active held checkout sessions (_coupon_held_keys), and last 10 associated orders.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/woocommerce/orders',
                        'methods'     => ['GET'],
                        'params'      => ['status (processing|completed|failed|all, default: all)', 'search (customer email, name, transaction ID, or order ID)', 'customer_id', 'coupon', 'per_page (default: 10, max: 50)', 'page', 'orderby (default: date)', 'order (DESC|ASC)'],
                        'description' => esc_html__('Recent orders with strict GDPR/PII anonymization (masked customer names, redacted emails/phones/addresses), item lines, coupon lines and applied coupon codes, totals, and gateways.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/woocommerce/order/{id}',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Deep order diagnostics: item line metadata, shipping lines with decoded metadata (e.g. Flexible Shipping fs_costs base & additional costs), fees, coupon lines, refunds, order notes (payment gateway responses), and sanitized metadata.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/woocommerce/settings',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Store configuration: currency, tax settings, stock management, active payment gateways (secrets redacted), and shipping zones/methods with locations and Flexible Shipping matrix rules.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/woocommerce/shipping',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Dedicated shipping & logistics inspection: zones, geographic locations (postcodes, regions, countries), native method parameters, flat_rate table rate rules, Flexible Shipping & Flexible Shipping PRO matrix calculation rules (weight/price tiers, shipping classes), and sanitized raw instance settings.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/woocommerce/analytics/sales',
                        'methods'     => ['GET'],
                        'params'      => ['range (today|yesterday|last_7_days|last_30_days|this_month|last_month|this_year|custom, default: last_30_days)', 'start_date (YYYY-MM-DD)', 'end_date (YYYY-MM-DD)'],
                        'description' => esc_html__('100% native WooCommerce sales report: net sales, gross sales, orders count, AOV, refunds, daily trend, and growth percentage compared to previous period.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/woocommerce/analytics/top-performers',
                        'methods'     => ['GET'],
                        'params'      => ['range (default: last_30_days)', 'limit (default: 10, max: 50)', 'start_date', 'end_date'],
                        'description' => esc_html__('Top selling products by net revenue and units sold, and top discount coupons with usage counts.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/woocommerce/analytics/stock',
                        'methods'     => ['GET'],
                        'params'      => ['low_stock_threshold (optional override)'],
                        'description' => esc_html__('Stock health and financial valuation: total managed units, total retail valuation, low stock alerts, and dormant stock (0 sales in last 90 days).', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/woocommerce/webhooks',
                        'methods'     => ['GET'],
                        'description' => esc_html__('WooCommerce Webhooks inventory: delivery URL, topic, status (active/paused/disabled), failure counts, and alerts for repeated delivery failures.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'content',
                'label'       => esc_html__('Pages, Content & SEO', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting WordPress pages and posts hierarchy, rendered and raw Gutenberg block content, templates, and unified SEO metadata (Yoast, Rank Math, SEOPress, AIOSEO).', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['content']),
                'endpoints'   => [
                    [
                        'path'        => '/content/pages',
                        'methods'     => ['GET'],
                        'params'      => ['status (publish|draft|all, default: publish)', 'parent', 'search', 'per_page (default: 20, max: 100)', 'page', 'orderby (menu_order|title|date|modified)', 'order (ASC|DESC)'],
                        'description' => esc_html__('Lists WordPress pages with hierarchy (parent/child), slug, status, template PHP, editor type (Gutenberg/Classic/Elementor), special page flags, and quick SEO preview.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/content/page/{id}',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Deep page inspection: raw/rendered content, Gutenberg blocks summary, detected shortcodes, word count, parent/child hierarchy, and unified SEO metadata.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/content/posts',
                        'methods'     => ['GET'],
                        'params'      => ['status (publish|draft|all, default: publish)', 'category', 'tag', 'search', 'per_page (default: 20, max: 100)', 'page', 'orderby (date|title|modified)', 'order (DESC|ASC)'],
                        'description' => esc_html__('Lists WordPress blog posts with categories, tags, author, editor type, and quick SEO preview.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/content/post/{id}',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Deep post or custom post type inspection: raw/rendered content, blocks, taxonomies, sanitized postmeta, and full unified SEO object.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/content/seo-audit',
                        'methods'     => ['GET'],
                        'params'      => ['include_posts (true|false, default: false)', 'include_products (true|false, default: false)', 'include_categories (true|false, default: false)', 'limit (default: 100, max: 300)', 'limit_products (default: 50, max: 200)'],
                        'description' => esc_html__('Site-wide SEO audit report across pages, posts, WooCommerce products, and categories: missing meta descriptions, title issues, noindex warnings on published products/checkout, thin content, and category descriptions.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'performance',
                'label'       => esc_html__('Site Performance & Plugin Profiler', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows on-demand URL benchmarking (TTFB, memory, SQL queries per plugin, slow/duplicate queries, frontend JS/CSS assets per plugin), template archetype discovery, 100% native server-side Core Web Vitals checks (DOM size, CLS image dimensions, legacy image formats, render-blocking resources, Google Fonts, WP core bloat scripts, cart fragments), autoload bloat audit, and plugin resource footprint.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['performance']),
                'endpoints'   => [
                    [
                        'path'        => '/performance/templates-urls',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Automatic discovery and resolution of 5 key representative template URLs: Homepage, Shop / Catalog, Product Category, Single Product, and Cart / Checkout.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/performance/profile',
                        'methods'     => ['GET'],
                        'params'      => ['path (default: /)', 'include_assets (true|false, default: true)', 'include_queries (true|false, default: true)', 'slow_query_threshold_ms (default: 50)'],
                        'description' => esc_html__('Targeted URL profiler: measures TTFB, peak memory, attributes SQL queries & duration to specific plugins via backtrace, flags duplicate and slow queries, measures frontend JS/CSS assets per plugin, and runs native Web Vitals checks (DOM size, CLS image dimensions, legacy format PNG/JPEG, render-blocking in <head>, external Google Fonts, core bloat scripts, and WooCommerce cart-fragments detection).', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/performance/autoload',
                        'methods'     => ['GET'],
                        'params'      => ['limit (default: 25, max: 100)'],
                        'description' => esc_html__('Audits wp_options autoload bloat: total size vs 800KB threshold, top heaviest options, and size distribution grouped by plugin prefix.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/performance/plugins-summary',
                        'methods'     => ['GET'],
                        'params'      => ['status (active|all, default: active)'],
                        'description' => esc_html__('Consolidated resource footprint per plugin: active status, associated database tables count, database disk size, and table row counts.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/performance/caching',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Universal caching & optimization diagnostic: Object Cache (Redis/Memcached), Page Cache drop-in, and in-depth WP Rocket settings (RUCSS vs CPCSS, Delay JS, safelists, lazyload, mobile cache) with security redaction.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'pmpro',
                'label'       => esc_html__('Paid Memberships Pro (PMPro)', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting Paid Memberships Pro levels, durations, recurring billing cycles, user membership records, status timeline, and associated PMPro orders.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['pmpro']),
                'endpoints'   => [
                    [
                        'path'        => '/pmpro/levels',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Lists all PMPro membership levels with duration rules (expiration_number/period, cycle_number/period), pricing, and active member counts with duration anomaly detection.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/pmpro/members',
                        'methods'     => ['GET'],
                        'params'      => ['status (active|all, default: active)', 'level_id', 'search', 'user_id', 'per_page (default: 20, max: 100)', 'page'],
                        'description' => esc_html__('Lists membership records from pmpro_memberships_users with startdate, enddate, status, masked PII, and computed expiration indicators.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/pmpro/member/{user_id}',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Deep member diagnostic: active level, membership history timeline, associated PMPro orders, and access anomaly flags (e.g. active status with past expiration date).', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'masterstudy',
                'label'       => esc_html__('MasterStudy LMS', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows inspecting MasterStudy LMS courses, pricing, lesson counts, course durations, user course enrollments, and PMPro subscription link synchronization.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['masterstudy']),
                'endpoints'   => [
                    [
                        'path'        => '/masterstudy/courses',
                        'methods'     => ['GET'],
                        'params'      => ['search', 'status (publish|draft|all, default: publish)', 'per_page (default: 20, max: 100)', 'page'],
                        'description' => esc_html__('Lists MasterStudy LMS courses with pricing configuration, linked WooCommerce product ID, course duration/expiration rules, total students, lessons count, and allowed PMPro membership levels.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/masterstudy/user/{user_id}/courses',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Detailed user enrollment audit: course progress, start/end dates, linked PMPro subscription record, and root cause diagnosis for premature access expirations or pointer desync.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
        ];
    }
}
