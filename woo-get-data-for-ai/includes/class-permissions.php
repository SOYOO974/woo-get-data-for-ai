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
                'description' => esc_html__('Allows inspection of WP, PHP, MySQL versions, active plugins list, server limits, and Action Scheduler crons.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/ping', '/capabilities', '/system'],
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
                'description' => esc_html__('Allows browsing active plugins and mu-plugins file structures and reading specific PHP/JS/CSS files in sandbox.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/code/plugins', '/code/file'],
            ],
            'elementor' => [
                'label'       => esc_html__('Elementor Architecture', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows reading Elementor page trees, global kit styles, and mapping form widgets with webhooks.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/elementor/export-all', '/elementor/list', '/elementor/item/{id}', '/elementor/forms', '/elementor/kit'],
            ],
            'wpcode' => [
                'label'       => esc_html__('WPCode Snippets', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows listing and inspecting custom PHP, JS, and CSS snippets stored in WPCode plugin.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/wpcode/snippets', '/wpcode/snippet/{id}'],
            ],
            'logs' => [
                'label'       => esc_html__('Error & WooCommerce Logs', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows listing and tail-reading debug.log and uploads/wc-logs/*.log with memory protection.', 'woo-get-data-for-ai'),
                'endpoints'   => ['/logs/sources', '/logs/view'],
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
            'flowmattic'   => 1,
            'analytics'    => 1,
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
                        'description' => esc_html__('Comprehensive server, WordPress, theme, active plugins & updates, WooCommerce environment, and Action Scheduler status.', 'woo-get-data-for-ai'),
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
                'description' => esc_html__('Allows browsing active plugins and mu-plugins file structures and reading specific PHP/JS/CSS files in sandbox.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['code']),
                'endpoints'   => [
                    [
                        'path'        => '/code/plugins',
                        'methods'     => ['GET'],
                        'params'      => ['status (active|all)'],
                        'description' => esc_html__('Hierarchical directory and file trees of active plugins and wp-content/mu-plugins/.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/code/file',
                        'methods'     => ['GET'],
                        'params'      => ['path (required, e.g. plugins/my-plugin/file.php)'],
                        'description' => esc_html__('Sandboxed source code reader for PHP, JS, and CSS files.', 'woo-get-data-for-ai'),
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
                        'description' => esc_html__('Bulk export of all Elementor pages, templates, kit & forms in 1 optimized HTTP request.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/elementor/list',
                        'methods'     => ['GET'],
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
                'label'       => esc_html__('WPCode Snippets', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows listing and inspecting custom PHP, JS, and CSS snippets stored in WPCode plugin.', 'woo-get-data-for-ai'),
                'enabled'     => !empty($permissions['wpcode']),
                'endpoints'   => [
                    [
                        'path'        => '/wpcode/snippets',
                        'methods'     => ['GET'],
                        'params'      => ['status (all|active|inactive)'],
                        'description' => esc_html__('List all WPCode snippets with full source code, hook targets, type, and execution state.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/wpcode/snippet/{id}',
                        'methods'     => ['GET'],
                        'description' => esc_html__('Full source code, settings, and direct WordPress Admin edit link for a specific snippet.', 'woo-get-data-for-ai'),
                    ],
                ],
            ],
            [
                'id'          => 'logs',
                'label'       => esc_html__('Error & WooCommerce Logs', 'woo-get-data-for-ai'),
                'description' => esc_html__('Allows listing and tail-reading debug.log and uploads/wc-logs/*.log with memory protection.', 'woo-get-data-for-ai'),
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
                        'description' => esc_html__('Bulk export of all FlowMattic workflows in native importable JSON format in 1 request.', 'woo-get-data-for-ai'),
                    ],
                    [
                        'path'        => '/flowmattic/workflows',
                        'methods'     => ['GET'],
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
        ];
    }
}
