<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Permissions;

class System_Controller extends Rest_Controller {

    public function register_routes() {
        // GET /ping (Connectivity test, does not require module permission)
        register_rest_route(self::NAMESPACE, '/ping', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_ping'],
            'permission_callback' => function ($request) {
                return $this->check_access($request);
            },
        ]);

        // GET /capabilities (Dynamic discovery & self-describing API catalog for AI agents)
        register_rest_route(self::NAMESPACE, '/capabilities', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_capabilities'],
            'permission_callback' => function ($request) {
                return $this->check_access($request);
            },
        ]);

        // GET /system (Comprehensive server & environment status)
        register_rest_route(self::NAMESPACE, '/system', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_system'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'system');
            },
        ]);
    }

    public function get_ping(\WP_REST_Request $request) {
        return $this->response([
            'status'     => 'ok',
            'site_name'  => get_bloginfo('name'),
            'site_url'   => site_url(),
            'home_url'   => home_url(),
            'timestamp'  => current_time('c'),
            'timezone'   => wp_timezone_string(),
            'version'    => WOO_GET_DATA_AI_VERSION,
        ]);
    }

    public function get_capabilities(\WP_REST_Request $request) {
        $catalog = Permissions::get_capabilities_catalog();

        return $this->response([
            'plugin' => [
                'name'      => 'WP Agent Bridge (Data for AI)',
                'version'   => WOO_GET_DATA_AI_VERSION,
                'read_only' => true,
                'docs_url'  => WOO_GET_DATA_AI_GITHUB_REPO,
            ],
            'site' => [
                'name'      => get_bloginfo('name'),
                'site_url'  => site_url(),
                'home_url'  => home_url(),
                'rest_base' => rest_url(self::NAMESPACE),
            ],
            'discovery_instructions' => [
                'purpose'  => 'Dynamic schema and capability discovery for AI development agents (Antigravity, Cursor, Claude).',
                'workflow' => 'On initial connection, bootstrap/update your local skill (.agents/skills/wp-agent-bridge/SKILL.md). Periodically re-query this endpoint to discover new modules and endpoints after plugin updates.',
            ],
            'modules_count' => count($catalog),
            'modules'       => $catalog,
        ]);
    }

    public function get_system(\WP_REST_Request $request) {
        global $wpdb;

        // PHP & Server info
        $server_info = [
            'php_version'          => PHP_VERSION,
            'php_sapi'             => PHP_SAPI,
            'web_server'           => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'Unknown',
            'mysql_version'        => $wpdb->db_version(),
            'memory_limit'         => ini_get('memory_limit'),
            'wp_memory_limit'      => WP_MEMORY_LIMIT,
            'wp_max_memory_limit'  => WP_MAX_MEMORY_LIMIT,
            'max_execution_time'   => ini_get('max_execution_time'),
            'upload_max_filesize'  => ini_get('upload_max_filesize'),
            'post_max_size'        => ini_get('post_max_size'),
            'curl_enabled'         => function_exists('curl_version'),
            'openssl_enabled'      => extension_loaded('openssl'),
            'mbstring_enabled'     => extension_loaded('mbstring'),
            'imagick_enabled'      => extension_loaded('imagick'),
            'gd_enabled'           => extension_loaded('gd'),
            'opcache_enabled'      => extension_loaded('Zend OPcache') && ini_get('opcache.enable'),
        ];

        // WordPress Core info
        $wp_info = [
            'version'       => get_bloginfo('version'),
            'site_url'      => site_url(),
            'home_url'      => home_url(),
            'is_multisite'  => is_multisite(),
            'debug_mode'    => defined('WP_DEBUG') && WP_DEBUG,
            'debug_log'     => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'script_debug'  => defined('SCRIPT_DEBUG') && SCRIPT_DEBUG,
            'language'      => get_locale(),
            'timezone'      => wp_timezone_string(),
            'permalink'     => get_option('permalink_structure'),
        ];

        // Active Theme info
        $theme = wp_get_theme();
        $theme_info = [
            'name'          => $theme->get('Name'),
            'version'       => $theme->get('Version'),
            'author'        => $theme->get('Author'),
            'stylesheet'    => $theme->get_stylesheet(),
            'template'      => $theme->get_template(),
            'is_child'      => is_child_theme(),
            'parent_theme'  => is_child_theme() && $theme->parent() ? $theme->parent()->get('Name') : null,
        ];

        // Plugins info (Active & Updates)
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins_option = (array) get_option('active_plugins', []);
        $update_plugins = get_site_transient('update_plugins');

        $plugins_list = [];
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = in_array($plugin_file, $active_plugins_option, true);
            $has_update = isset($update_plugins->response[$plugin_file]);

            $plugins_list[] = [
                'name'             => $plugin_data['Name'],
                'plugin_file'      => $plugin_file,
                'version'          => $plugin_data['Version'],
                'is_active'        => $is_active,
                'update_available' => $has_update,
                'new_version'      => $has_update ? $update_plugins->response[$plugin_file]->new_version : null,
                'author'           => wp_strip_all_tags($plugin_data['Author']),
            ];
        }

        // Must-Use Plugins
        $mu_plugins = get_mu_plugins();
        $mu_list = [];
        foreach ($mu_plugins as $mu_file => $mu_data) {
            $mu_list[] = [
                'name'    => $mu_data['Name'],
                'file'    => $mu_file,
                'version' => $mu_data['Version'],
            ];
        }

        // WooCommerce Environment (if active)
        $wc_info = null;
        if (class_exists('WooCommerce')) {
            $wc_info = [
                'version'          => WC()->version,
                'currency'         => get_woocommerce_currency(),
                'currency_symbol'  => get_woocommerce_currency_symbol(),
                'prices_include_tax' => wc_prices_include_tax(),
            ];

            // HPOS (High-Performance Order Storage) status
            if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')) {
                $is_syncing = false;
                try {
                    if (class_exists('\Automattic\WooCommerce\Database\Migrations\CustomOrderTable\DataSynchronizer') && function_exists('wc_get_container')) {
                        $sync = wc_get_container()->get(\Automattic\WooCommerce\Database\Migrations\CustomOrderTable\DataSynchronizer::class);
                        $is_syncing = $sync ? (bool) $sync->is_sync_in_progress() : false;
                    }
                } catch (\Throwable $e) {
                    $is_syncing = false;
                }
                $wc_info['hpos'] = [
                    'enabled'              => method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')
                        ? \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
                        : false,
                    'sync_in_progress'     => $is_syncing,
                    'authoritative_source' => get_option('woocommerce_custom_orders_table_enabled', 'no') === 'yes' ? 'custom_orders_table' : 'posts_table',
                ];
            }

            // Payment Gateways (masked credentials)
            if (WC()->payment_gateways()) {
                $gateways = WC()->payment_gateways()->payment_gateways();
                $gateway_summary = [];
                foreach ($gateways as $id => $gateway) {
                    $gateway_summary[] = [
                        'id'          => $id,
                        'title'       => $gateway->get_title(),
                        'enabled'     => $gateway->enabled === 'yes',
                        'method_title'=> $gateway->get_method_title(),
                    ];
                }
                $wc_info['payment_gateways'] = $gateway_summary;
            }
        }

        // Action Scheduler status (if present)
        $action_scheduler_info = null;
        if (class_exists('ActionScheduler') && class_exists('ActionScheduler_Store')) {
            try {
                $as_store = \ActionScheduler_Store::instance();
                if (method_exists($as_store, 'action_counts')) {
                    $action_scheduler_info = $as_store->action_counts();
                } else {
                    $action_scheduler_info = [
                        'pending'     => $as_store->get_status_count('pending'),
                        'in-progress' => $as_store->get_status_count('in-progress'),
                        'complete'    => $as_store->get_status_count('complete'),
                        'failed'      => $as_store->get_status_count('failed'),
                        'canceled'    => $as_store->get_status_count('canceled'),
                    ];
                }
            } catch (\Throwable $e) {
                $action_scheduler_info = ['error' => $e->getMessage()];
            }
        }

        return $this->response([
            'system'           => $server_info,
            'wordpress'        => $wp_info,
            'theme'            => $theme_info,
            'plugins_count'    => count($plugins_list),
            'plugins'          => $plugins_list,
            'mu_plugins'       => $mu_list,
            'woocommerce'      => $wc_info,
            'action_scheduler' => $action_scheduler_info,
        ]);
    }
}
