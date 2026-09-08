<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Permissions;
use WPAgentBridge\Playbooks;
use WPAgentBridge\Redaction;

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
            'args'                => [
                'format' => [
                    'type'        => 'string',
                    'enum'        => ['json', 'skill', 'markdown'],
                    'default'     => 'json',
                    'description' => 'Response format: "json" for structured data or "skill"/"markdown" for ready-to-use Agent SKILL.md.',
                ],
            ],
        ]);

        // GET /system (Comprehensive server & environment status)
        register_rest_route(self::NAMESPACE, '/system', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_system'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'system');
            },
            'args'                => [
                'plugins' => [
                    'default'           => 'active',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /system/database (Database health, table sizes, autoload analysis, transients)
        register_rest_route(self::NAMESPACE, '/system/database', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_database_health'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'system');
            },
        ]);

        // GET /system/mail (SMTP diagnostic, mail transport provider, and recent delivery failures)
        register_rest_route(self::NAMESPACE, '/system/mail', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_mail_diagnostic'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'system');
            },
        ]);

        // GET /system/security (Hardening audit, file editing constants, XML-RPC, security & cache plugins)
        register_rest_route(self::NAMESPACE, '/system/security', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_security_audit'],
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
        $catalog   = Permissions::get_capabilities_catalog();
        $playbooks = Playbooks::get_active_playbooks();

        $site_info = [
            'name'           => get_bloginfo('name'),
            'site_url'       => site_url(),
            'home_url'       => home_url(),
            'rest_base'      => rest_url(self::NAMESPACE),
            'plugin_version' => WOO_GET_DATA_AI_VERSION,
        ];

        $format = strtolower(trim((string) $request->get_param('format')));

        // Direct ready-to-use Agent SKILL.md generator
        if ($format === 'skill' || $format === 'markdown') {
            $markdown = Playbooks::generate_skill_markdown($site_info, $catalog, $playbooks);
            $response = new \WP_REST_Response($markdown, 200);
            $response->set_headers([
                'Content-Type'        => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => 'inline; filename="SKILL.md"',
            ]);
            return $response;
        }

        return $this->response([
            'plugin' => [
                'name'      => 'WP Agent Bridge (Data for AI)',
                'version'   => WOO_GET_DATA_AI_VERSION,
                'read_only' => true,
                'docs_url'  => WOO_GET_DATA_AI_GITHUB_REPO,
            ],
            'site' => $site_info,
            'discovery_instructions' => [
                'purpose'        => 'Dynamic schema, capability, and procedural playbook discovery for AI development agents (Antigravity, Cursor, Claude).',
                'skill_download' => rest_url(self::NAMESPACE . '/capabilities?format=skill'),
                'workflow'       => 'On initial connection, bootstrap/update your local skill (.agents/skills/wp-agent-bridge/SKILL.md) via GET /capabilities?format=skill. The code module now supports individual inspection (/code/file), directory checksum fingerprinting (/code/checksums), and instant ZIP archive export (/code/zip). Re-query regularly to detect newly added data sources and playbooks.',
            ],
            'modules_count'   => count($catalog),
            'modules'         => $catalog,
            'playbooks_count' => count($playbooks),
            'playbooks'       => $playbooks,
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

        // Plugins filter: 'active' (default), 'inactive', 'all'
        $raw_plugins_filter = strtolower(trim((string) ($request->get_param('plugins') ?: $request->get_param('status') ?: 'active')));
        if (in_array($raw_plugins_filter, ['all', '*'], true)) {
            $plugins_filter = 'all';
        } elseif (in_array($raw_plugins_filter, ['inactive', 'disabled', '0'], true)) {
            $plugins_filter = 'inactive';
        } else {
            $plugins_filter = 'active';
        }

        // Plugins info (Active & Updates)
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins_option = (array) get_option('active_plugins', []);
        $update_plugins = get_site_transient('update_plugins');

        $active_count = 0;
        $inactive_count = 0;
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = in_array($plugin_file, $active_plugins_option, true) || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($plugin_file));
            if ($is_active) {
                $active_count++;
            } else {
                $inactive_count++;
            }
        }

        $plugins_list = [];
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = in_array($plugin_file, $active_plugins_option, true) || (function_exists('is_plugin_active_for_network') && is_plugin_active_for_network($plugin_file));

            if ($plugins_filter === 'active' && !$is_active) {
                continue;
            }
            if ($plugins_filter === 'inactive' && $is_active) {
                continue;
            }

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

                // Performance & Optimization features
                $features_util = class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil');
                $hpos_caching = false;
                $rate_limit = false;
                $hpos_fts = false;
                try {
                    if ($features_util && method_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil', 'feature_is_enabled')) {
                        $hpos_caching = \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled('hpos_datastore_caching');
                        $rate_limit = \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled('rate_limit_checkout');
                        $hpos_fts = \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled('hpos_fts_indexes');
                    }
                } catch (\Throwable $e) {
                    // Fallback to options below
                }

                $wc_info['performance_features'] = [
                    'hpos_data_caching'             => (bool) ($hpos_caching || get_option('woocommerce_hpos_datastore_caching_enabled', 'no') === 'yes'),
                    'deferred_transactional_emails' => (bool) apply_filters('woocommerce_defer_transactional_emails', false),
                    'checkout_rate_limiting'        => (bool) ($rate_limit || get_option('woocommerce_rate_limit_checkout_enabled', 'no') === 'yes' || get_option('woocommerce_feature_rate_limit_checkout_enabled', 'no') === 'yes'),
                    'hpos_full_text_search'         => (bool) ($hpos_fts || get_option('woocommerce_hpos_fts_indexes_enabled', 'no') === 'yes' || get_option('woocommerce_feature_hpos_fts_indexes_enabled', 'no') === 'yes'),
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

        // Action Scheduler status & retention policy (if present)
        $action_scheduler_info = null;
        if (class_exists('ActionScheduler') && class_exists('ActionScheduler_Store')) {
            try {
                $as_store = \ActionScheduler_Store::instance();
                $counts = [];
                if (method_exists($as_store, 'action_counts')) {
                    $counts = $as_store->action_counts();
                } else {
                    $counts = [
                        'pending'     => $as_store->get_status_count('pending'),
                        'in-progress' => $as_store->get_status_count('in-progress'),
                        'complete'    => $as_store->get_status_count('complete'),
                        'failed'      => $as_store->get_status_count('failed'),
                        'canceled'    => $as_store->get_status_count('canceled'),
                    ];
                }
                $retention = Scheduler_Controller::get_retention_policy($counts['complete'] ?? 0);
                $action_scheduler_info = array_merge($counts, [
                    'retention' => $retention,
                ]);
            } catch (\Throwable $e) {
                $action_scheduler_info = ['error' => $e->getMessage()];
            }
        }

        return $this->response([
            'system'           => $server_info,
            'wordpress'        => $wp_info,
            'theme'            => $theme_info,
            'plugins_summary'  => [
                'total_installed' => count($all_plugins),
                'active_count'    => $active_count,
                'inactive_count'  => $inactive_count,
                'must_use_count'  => count($mu_list),
            ],
            'plugins_count'    => count($plugins_list),
            'plugins'          => $plugins_list,
            'mu_plugins'       => $mu_list,
            'woocommerce'      => $wc_info,
            'action_scheduler' => $action_scheduler_info,
        ]);
    }

    /**
     * GET /system/database
     * In-depth database diagnostic: table sizes, top tables, autoload footprint, and transient health.
     */
    public function get_database_health(\WP_REST_Request $request) {
        global $wpdb;

        // 1. Table status & storage breakdown
        $table_status = $wpdb->get_results("SHOW TABLE STATUS", ARRAY_A);
        $total_data_bytes  = 0;
        $total_index_bytes = 0;
        $total_rows        = 0;
        $tables            = [];

        if (is_array($table_status)) {
            foreach ($table_status as $row) {
                $table_name   = $row['Name'] ?? '';
                $data_len     = (int) ($row['Data_length'] ?? 0);
                $index_len    = (int) ($row['Index_length'] ?? 0);
                $table_bytes  = $data_len + $index_len;
                $rows_count   = (int) ($row['Rows'] ?? 0);
                $engine       = $row['Engine'] ?? 'Unknown';

                $total_data_bytes  += $data_len;
                $total_index_bytes += $index_len;
                $total_rows        += $rows_count;

                $tables[] = [
                    'table'        => $table_name,
                    'engine'       => $engine,
                    'rows'         => $rows_count,
                    'data_bytes'   => $data_len,
                    'index_bytes'  => $index_len,
                    'total_bytes'  => $table_bytes,
                    'total_human'  => size_format($table_bytes),
                    'is_wp_prefix' => (strpos($table_name, $wpdb->prefix) === 0),
                ];
            }
        }

        // Sort tables by size descending
        usort($tables, function ($a, $b) {
            return $b['total_bytes'] <=> $a['total_bytes'];
        });

        $top_tables = array_slice($tables, 0, 15);
        $total_db_bytes = $total_data_bytes + $total_index_bytes;

        // 2. Autoload Footprint Analysis (Top performance bottleneck in WP)
        $autoload_query = $wpdb->get_results(
            "SELECT option_name, LENGTH(option_value) AS size_bytes 
             FROM {$wpdb->options} 
             WHERE autoload NOT IN ('no', 'off')
             ORDER BY size_bytes DESC",
            ARRAY_A
        );

        $total_autoload_bytes = 0;
        $total_autoload_count = 0;
        $top_autoload_options = [];

        if (is_array($autoload_query)) {
            $total_autoload_count = count($autoload_query);
            foreach ($autoload_query as $opt) {
                $bytes = (int) ($opt['size_bytes'] ?? 0);
                $total_autoload_bytes += $bytes;
            }

            // Top 10 largest autoloaded options with orphaned plugin analysis
            $top_slice = array_slice($autoload_query, 0, 10);
            foreach ($top_slice as $opt) {
                $opt_bytes = (int) ($opt['size_bytes'] ?? 0);
                $orphan_data = self::analyze_orphaned_option($opt['option_name']);
                $top_autoload_options[] = array_merge([
                    'option_name' => $opt['option_name'],
                    'size_bytes'  => $opt_bytes,
                    'size_human'  => size_format($opt_bytes),
                ], $orphan_data);
            }
        }

        // Autoload status & thresholds
        $autoload_status = 'healthy';
        $autoload_alert  = null;
        if ($total_autoload_bytes > 1572864) { // > 1.5 MB
            $autoload_status = 'critical';
            $autoload_alert  = sprintf(
                'CRITICAL: Total autoload size is %s across %d options. Autoload exceeding 1 MB significantly degrades TTFB on every single request. Consider disabling autoload for oversized options.',
                size_format($total_autoload_bytes),
                $total_autoload_count
            );
        } elseif ($total_autoload_bytes > 819200) { // > 800 KB
            $autoload_status = 'warning';
            $autoload_alert  = sprintf(
                'WARNING: Total autoload size is %s across %d options. Optimal threshold is under 800 KB.',
                size_format($total_autoload_bytes),
                $total_autoload_count
            );
        }

        // 3. Transients Health Check
        $now = time();
        $expired_transients_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value < %d",
                '_transient_timeout_%',
                $now
            )
        );

        $total_transients_count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                '_transient_%'
            )
        );

        // 4. Object Cache & Database Engine
        $ext_object_cache = function_exists('wp_using_ext_object_cache') ? wp_using_ext_object_cache() : false;

        return $this->response([
            'database' => [
                'server_version' => $wpdb->db_version(),
                'tables_count'   => count($tables),
                'total_rows'     => $total_rows,
                'data_size'      => size_format($total_data_bytes),
                'index_size'     => size_format($total_index_bytes),
                'total_size'     => size_format($total_db_bytes),
                'total_bytes'    => $total_db_bytes,
            ],
            'autoload_health' => [
                'status'               => $autoload_status,
                'total_size'           => size_format($total_autoload_bytes),
                'total_bytes'          => $total_autoload_bytes,
                'total_options_count'  => $total_autoload_count,
                'recommended_max_size' => '800 KB',
                'alert'                => $autoload_alert,
                'top_heavy_options'    => $top_autoload_options,
            ],
            'transients_health' => [
                'total_transients'   => $total_transients_count,
                'expired_transients' => $expired_transients_count,
                'alert'              => $expired_transients_count > 200 ? sprintf('Warning: %d expired transients detected in wp_options. Consider running a transient cleanup.', $expired_transients_count) : null,
            ],
            'caching' => [
                'external_object_cache' => $ext_object_cache,
                'recommendation'        => !$ext_object_cache ? 'External object cache (Redis/Memcached) is not active. Enabling an external object cache will relieve database pressure on high-traffic WooCommerce sites.' : 'External object cache is active.',
            ],
            'top_tables' => $top_tables,
        ]);
    }

    /**
     * GET /system/mail
     * SMTP and transactional email diagnostic report.
     * Detects transport provider (FluentSMTP, WP Mail SMTP, Post SMTP, Easy WP SMTP),
     * sanitizes credentials, and inspects recent delivery failures.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_mail_diagnostic(\WP_REST_Request $request) {
        global $wpdb;

        // 1. Detect Installed & Active Mail Plugins
        $detected_plugins = [];
        $active_provider  = 'php_mail'; // Default fallback
        $active_plugin    = 'WordPress Core (Default)';
        $config_details   = [];
        $recent_failures  = [];

        // Check FluentSMTP
        $has_fluentsmtp = defined('FLUENTMAIL') || class_exists('FluentMail\App\App');
        if ($has_fluentsmtp) {
            $detected_plugins[] = 'FluentSMTP';
            $fluent_settings = get_option('fluentmail-settings');
            if (!empty($fluent_settings) && is_array($fluent_settings)) {
                $active_plugin   = 'FluentSMTP';
                $connections     = $fluent_settings['connections'] ?? [];
                $active_conn_key = $fluent_settings['active_connection'] ?? key($connections);
                $active_conn     = $connections[$active_conn_key] ?? [];
                $active_provider = $active_conn['provider_name'] ?? ($active_conn['provider'] ?? 'fluentsmtp_custom');
                
                $config_details = [
                    'provider'        => $active_provider,
                    'sender_email'    => $active_conn['sender_email'] ?? get_option('admin_email'),
                    'sender_name'     => $active_conn['sender_name'] ?? get_bloginfo('name'),
                    'is_active'       => true,
                    'logging_enabled' => !empty($fluent_settings['misc']['log_emails']),
                ];

                // Query FluentSMTP failed log
                $f_table = $wpdb->prefix . 'fluentmail_log';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$f_table}'") === $f_table) {
                    $f_logs = $wpdb->get_results("
                        SELECT id, `to` as recipient, subject, status, response, created_at
                        FROM {$f_table}
                        WHERE status != 'sent'
                        ORDER BY id DESC
                        LIMIT 10
                    ", ARRAY_A);
                    if (!empty($f_logs)) {
                        foreach ($f_logs as $f_log) {
                            $recent_failures[] = [
                                'id'         => (int) $f_log['id'],
                                'recipient'  => Redaction::redact_string($f_log['recipient'] ?? '', true),
                                'subject'    => sanitize_text_field($f_log['subject'] ?? ''),
                                'status'     => $f_log['status'],
                                'error'      => sanitize_text_field(substr($f_log['response'] ?? '', 0, 200)),
                                'date'       => $f_log['created_at'],
                            ];
                        }
                    }
                }
            }
        }

        // Check WP Mail SMTP
        $has_wp_mail_smtp = defined('WPMS_PLUGIN_VER') || class_exists('WPMailSMTP\Core');
        if ($has_wp_mail_smtp) {
            $detected_plugins[] = 'WP Mail SMTP';
            if ($active_provider === 'php_mail') {
                $wpms_options = get_option('wp_mail_smtp');
                if (!empty($wpms_options) && is_array($wpms_options)) {
                    $active_plugin   = 'WP Mail SMTP';
                    $mailer          = $wpms_options['mail']['mailer'] ?? 'mail';
                    $active_provider = $mailer;

                    $config_details = [
                        'provider'       => $mailer,
                        'sender_email'   => $wpms_options['mail']['from_email'] ?? get_option('admin_email'),
                        'sender_name'    => $wpms_options['mail']['from_name'] ?? get_bloginfo('name'),
                        'is_active'      => $mailer !== 'mail',
                        'host'           => isset($wpms_options['smtp']['host']) ? $wpms_options['smtp']['host'] : null,
                        'port'           => isset($wpms_options['smtp']['port']) ? (int) $wpms_options['smtp']['port'] : null,
                        'encryption'     => isset($wpms_options['smtp']['encryption']) ? $wpms_options['smtp']['encryption'] : null,
                    ];
                }

                // Query WP Mail SMTP debug events or logs if available
                $wpms_debug_table = $wpdb->prefix . 'wpmailsmtp_debug_events';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$wpms_debug_table}'") === $wpms_debug_table) {
                    $wpms_logs = $wpdb->get_results("
                        SELECT id, event_type, content, created_at
                        FROM {$wpms_debug_table}
                        WHERE event_type IN (1, 2)
                        ORDER BY id DESC
                        LIMIT 10
                    ", ARRAY_A);
                    if (!empty($wpms_logs)) {
                        foreach ($wpms_logs as $w_log) {
                            $recent_failures[] = [
                                'id'         => (int) $w_log['id'],
                                'recipient'  => '',
                                'subject'    => '',
                                'status'     => 'failed',
                                'error'      => sanitize_text_field(substr($w_log['content'] ?? '', 0, 200)),
                                'date'       => $w_log['created_at'],
                            ];
                        }
                    }
                }
            }
        }

        // Check Post SMTP
        $has_post_smtp = defined('POST_SMTP_VER') || class_exists('Postman');
        if ($has_post_smtp) {
            $detected_plugins[] = 'Post SMTP';
            if ($active_provider === 'php_mail') {
                $active_plugin   = 'Post SMTP';
                $active_provider = 'post_smtp';
            }
        }

        // Check Easy WP SMTP
        $has_easy_wp_smtp = class_exists('EasyWPSmtp');
        if ($has_easy_wp_smtp) {
            $detected_plugins[] = 'Easy WP SMTP';
            if ($active_provider === 'php_mail') {
                $active_plugin   = 'Easy WP SMTP';
                $active_provider = 'easy_wp_smtp';
            }
        }

        // Default / PHP mail evaluation
        $is_using_php_mail = ($active_provider === 'php_mail' || $active_provider === 'mail');
        $health_status     = $is_using_php_mail ? 'warning' : (!empty($recent_failures) ? 'notice' : 'healthy');

        $alerts = [];
        if ($is_using_php_mail) {
            $alerts[] = 'CRITICAL: No authenticated SMTP provider is active. WordPress is using unauthenticated PHP mail(). Emails (order confirmations, customer receipts, password resets) have high spam score and will be rejected or quarantined by Gmail, Outlook, Yahoo.';
        }
        if (!empty($recent_failures)) {
            $alerts[] = sprintf('%d recent email delivery failure(s) recorded in mail log.', count($recent_failures));
        }

        return $this->response([
            'health'             => $health_status,
            'active_plugin'      => $active_plugin,
            'transport_provider' => $active_provider,
            'is_authenticated'   => !$is_using_php_mail,
            'detected_plugins'   => $detected_plugins,
            'configuration'      => $config_details,
            'recent_failures'    => $recent_failures,
            'alerts'             => $alerts,
        ]);
    }

    /**
     * GET /system/security
     * Security hardening audit, constants, XML-RPC, exposed versions, and security/caching plugins.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_security_audit(\WP_REST_Request $request) {
        global $wpdb;

        // 1. Core Hardening Constants
        $disallow_file_edit = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        $disallow_file_mods = defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS;
        $force_ssl_admin    = defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN;
        $wp_debug           = defined('WP_DEBUG') && WP_DEBUG;
        $wp_debug_display   = defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY;
        $wp_debug_log       = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        $script_debug       = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
        $is_ssl             = is_ssl();

        // 2. Database Prefix Security
        $table_prefix      = $wpdb->prefix;
        $is_default_prefix = ($table_prefix === 'wp_');

        // 3. XML-RPC & Version Exposure
        $xmlrpc_enabled    = apply_filters('xmlrpc_enabled', true);
        $generator_exposed = has_action('wp_head', 'wp_generator') !== false;

        // 4. Detected Security Plugins
        $security_plugins = [];
        if (defined('WORDFENCE_VERSION') || class_exists('wordfence')) $security_plugins[] = ['name' => 'Wordfence Security', 'active' => true];
        if (class_exists('ITSEC_Core')) $security_plugins[] = ['name' => 'Solid Security (iThemes)', 'active' => true];
        if (defined('SUCURISCAN_INIT')) $security_plugins[] = ['name' => 'Sucuri Security', 'active' => true];
        if (defined('SECUPRESS_VERSION')) $security_plugins[] = ['name' => 'SecuPress', 'active' => true];
        if (defined('AIO_WP_SECURITY_VERSION')) $security_plugins[] = ['name' => 'All In One WP Security & Firewall', 'active' => true];
        if (class_exists('WP_Defender\Controller')) $security_plugins[] = ['name' => 'Defender Security', 'active' => true];
        if (defined('CERBER_VERSION')) $security_plugins[] = ['name' => 'WP Cerber Security', 'active' => true];
        if (defined('MALCARE_VERSION')) $security_plugins[] = ['name' => 'MalCare Security', 'active' => true];

        // 5. Detected Caching & Performance Plugins
        $cache_plugins = [];
        if (defined('WP_ROCKET_VERSION')) $cache_plugins[] = ['name' => 'WP Rocket', 'version' => WP_ROCKET_VERSION];
        if (defined('LSCWP_V')) $cache_plugins[] = ['name' => 'LiteSpeed Cache', 'version' => LSCWP_V];
        if (defined('W3TC')) $cache_plugins[] = ['name' => 'W3 Total Cache', 'version' => true];
        if (defined('ADVANCEDCACHEPROBLEM') || function_exists('wp_cache_init')) $cache_plugins[] = ['name' => 'WP Super Cache / Page Cache', 'version' => true];
        if (defined('AUTOPTIMIZE_PLUGIN_VERSION')) $cache_plugins[] = ['name' => 'Autoptimize', 'version' => AUTOPTIMIZE_PLUGIN_VERSION];
        if (class_exists('RedisObjectCache') || defined('WP_REDIS_VERSION')) $cache_plugins[] = ['name' => 'Redis Object Cache', 'version' => true];
        if (defined('PERFMATTERS_VERSION')) $cache_plugins[] = ['name' => 'Perfmatters', 'version' => PERFMATTERS_VERSION];
        if (defined('FLYING_PRESS_VERSION')) $cache_plugins[] = ['name' => 'FlyingPress', 'version' => FLYING_PRESS_VERSION];

        // 6. Security Score & Actionable Recommendations
        $recommendations = [];
        if (!$disallow_file_edit) {
            $recommendations[] = [
                'severity' => 'medium',
                'issue'    => 'DISALLOW_FILE_EDIT is not enabled.',
                'solution' => 'Add define(\'DISALLOW_FILE_EDIT\', true); in wp-config.php to block admin dashboard theme/plugin code editing.',
            ];
        }
        if ($wp_debug_display) {
            $recommendations[] = [
                'severity' => 'high',
                'issue'    => 'WP_DEBUG_DISPLAY is enabled in production.',
                'solution' => 'Set define(\'WP_DEBUG_DISPLAY\', false); in wp-config.php to prevent sensitive PHP backtraces and database paths from leaking to visitors.',
            ];
        }
        if ($is_default_prefix) {
            $recommendations[] = [
                'severity' => 'low',
                'issue'    => 'Default database prefix "wp_" is used.',
                'solution' => 'Using standard "wp_" prefix makes automated SQL injection probes easier. Consider renaming prefix on next staging migration.',
            ];
        }
        if ($xmlrpc_enabled) {
            $recommendations[] = [
                'severity' => 'low',
                'issue'    => 'XML-RPC interface is active.',
                'solution' => 'If not using Jetpack or mobile WP app, disable XML-RPC via filter add_filter(\'xmlrpc_enabled\', \'__return_false\') or web server rules to avoid brute-force attacks.',
            ];
        }
        if (!$is_ssl) {
            $recommendations[] = [
                'severity' => 'critical',
                'issue'    => 'Site is not running over HTTPS (SSL).',
                'solution' => 'Enable SSL certificate and define(\'FORCE_SSL_ADMIN\', true); immediately.',
            ];
        }

        $has_critical_or_high = false;
        foreach ($recommendations as $rec) {
            if ($rec['severity'] === 'high' || $rec['severity'] === 'critical') {
                $has_critical_or_high = true;
                break;
            }
        }

        $security_health = empty($recommendations) ? 'hardened' : ($has_critical_or_high ? 'needs_attention' : 'moderate');

        return $this->response([
            'health'          => $security_health,
            'constants'       => [
                'disallow_file_edit' => $disallow_file_edit,
                'disallow_file_mods' => $disallow_file_mods,
                'force_ssl_admin'    => $force_ssl_admin,
                'wp_debug'           => $wp_debug,
                'wp_debug_display'   => $wp_debug_display,
                'wp_debug_log'       => $wp_debug_log,
                'script_debug'       => $script_debug,
                'is_ssl'             => $is_ssl,
            ],
            'database_hardening' => [
                'prefix'            => $table_prefix,
                'is_default_prefix' => $is_default_prefix,
            ],
            'attack_surface'     => [
                'xmlrpc_enabled'    => $xmlrpc_enabled,
                'generator_exposed' => $generator_exposed,
            ],
            'security_plugins'   => $security_plugins,
            'caching_plugins'    => $cache_plugins,
            'recommendations'    => $recommendations,
        ]);
    }
}
