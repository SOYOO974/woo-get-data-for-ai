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
                'endpoints'   => ['/ping', '/system'],
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
                'endpoints'   => ['/elementor/list', '/elementor/item/{id}', '/elementor/forms', '/elementor/kit'],
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
}
