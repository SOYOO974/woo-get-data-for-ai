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

        // GET /system/mail/subscriber (MailPoet subscriber status, bounce diagnostic, and segment membership)
        register_rest_route(self::NAMESPACE, '/system/mail/subscriber', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_mail_subscriber'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'system');
            },
            'args'                => [
                'email' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => function ($param) {
                        return is_email($param);
                    },
                ],
            ],
        ]);

        // GET /system/security (Hardening audit, file editing constants, XML-RPC, security & cache plugins)
        register_rest_route(self::NAMESPACE, '/system/security', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_security_audit'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'system');
            },
        ]);

        // GET /system/search (Global read-only search across wp_posts, sanitized wp_options, and WPCode / Code Snippets)
        register_rest_route(self::NAMESPACE, '/system/search', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'search_system'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'system');
            },
            'args'                => [
                'query'     => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'q'         => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'target'    => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit'     => [
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
                'post_type' => [
                    'default'           => 'any',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
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

        $runtime_constants = self::get_runtime_performance_constants();

        return $this->response([
            'system'            => $server_info,
            'wordpress'         => $wp_info,
            'runtime_constants' => $runtime_constants,
            'theme'             => $theme_info,
            'plugins_summary'   => [
                'total_installed' => count($all_plugins),
                'active_count'    => $active_count,
                'inactive_count'  => $inactive_count,
                'must_use_count'  => count($mu_list),
            ],
            'plugins_count'     => count($plugins_list),
            'plugins'           => $plugins_list,
            'mu_plugins'        => $mu_list,
            'woocommerce'       => $wc_info,
            'action_scheduler'  => $action_scheduler_info,
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
                                'recipient'  => Redaction::redact_email($f_log['recipient'] ?? ''),
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

        // Check MailPoet & MailPoet Sending Service (MSS)
        $has_mailpoet = defined('MAILPOET_VERSION') || class_exists('\MailPoet\Settings\SettingsController') || class_exists('\MailPoet\Config\ServicesChecker');
        $mailpoet_settings_table = $wpdb->prefix . 'mailpoet_settings';
        $has_mailpoet_table = ($wpdb->get_var("SHOW TABLES LIKE '{$mailpoet_settings_table}'") === $mailpoet_settings_table);

        $mailpoet_config = null;
        if ($has_mailpoet || $has_mailpoet_table) {
            $detected_plugins[] = 'MailPoet';

            $mp_send_transactional = false;
            $mp_mta = [];
            $mp_mta_group = '';
            $mp_api_key_state = [];
            $mp_has_api_key = false;
            $mp_sender = [];

            if (class_exists('\MailPoet\Settings\SettingsController')) {
                try {
                    $mp_settings = \MailPoet\Settings\SettingsController::getInstance();
                    $mp_send_transactional = (bool) $mp_settings->get('send_transactional_emails', false);
                    $mp_mta = $mp_settings->get('mta', []) ?: [];
                    $mp_mta_group = $mp_settings->get('mta_group', '') ?: '';
                    $mp_api_key_state = $mp_settings->get('mta.mailpoet_api_key_state', []) ?: [];
                    $mp_has_api_key = !empty($mp_settings->get('mta.mailpoet_api_key'));
                    $mp_sender = $mp_settings->get('sender', []) ?: [];
                } catch (\Throwable $e) {
                    // fallback to database queries below
                }
            }

            if (empty($mp_mta) && $has_mailpoet_table) {
                $mp_rows = $wpdb->get_results("SELECT name, value FROM {$mailpoet_settings_table} WHERE name IN ('mta', 'mta_group', 'send_transactional_emails', 'sender')", OBJECT_K);
                if (!empty($mp_rows)) {
                    if (isset($mp_rows['send_transactional_emails'])) {
                        $val = maybe_unserialize($mp_rows['send_transactional_emails']->value);
                        $mp_send_transactional = ($val === true || $val === '1' || $val === 1);
                    }
                    if (isset($mp_rows['mta'])) {
                        $mp_mta = maybe_unserialize($mp_rows['mta']->value) ?: [];
                    }
                    if (isset($mp_rows['mta_group'])) {
                        $mp_mta_group = maybe_unserialize($mp_rows['mta_group']->value) ?: '';
                    }
                    if (isset($mp_rows['sender'])) {
                        $mp_sender = maybe_unserialize($mp_rows['sender']->value) ?: [];
                    }
                }
            }

            $mp_method = $mp_mta['method'] ?? ($mp_mta_group ?: 'unknown');
            $is_mss = ($mp_method === 'MailPoet');
            $key_state = $mp_api_key_state['state'] ?? ($mp_mta['mailpoet_api_key_state']['state'] ?? ($mp_has_api_key ? 'specified' : 'none'));
            $is_mss_valid = ($key_state === 'valid' || $key_state === 'expiring');

            if ($is_mss && class_exists('\MailPoet\Config\ServicesChecker')) {
                try {
                    $services_checker = new \MailPoet\Config\ServicesChecker();
                    $sc_valid = $services_checker->isMailPoetAPIKeyValid(false);
                    if ($sc_valid !== null) {
                        $is_mss_valid = (bool) $sc_valid;
                    }
                } catch (\Throwable $e) {
                    // keep current evaluation
                }
            }

            $mailpoet_config = [
                'provider'                  => $is_mss ? 'mailpoet_sending_service' : 'mailpoet_' . strtolower($mp_method),
                'send_transactional_emails' => $mp_send_transactional,
                'mta_group'                 => $mp_mta_group,
                'mta_method'                => $mp_method,
                'is_mss_active'             => $is_mss,
                'mss_key_specified'         => $mp_has_api_key,
                'mss_key_state'             => $key_state,
                'mss_key_valid'             => $is_mss_valid,
                'sender_email'              => !empty($mp_sender['address']) ? $mp_sender['address'] : get_option('admin_email'),
                'sender_name'               => !empty($mp_sender['name']) ? $mp_sender['name'] : get_bloginfo('name'),
            ];

            // If MailPoet has taken over transactional emails, or if no other dedicated SMTP was active
            if ($mp_send_transactional || $active_provider === 'php_mail') {
                if ($mp_send_transactional) {
                    $active_plugin = 'MailPoet';
                    $active_provider = $is_mss ? 'mailpoet_sending_service' : 'mailpoet_' . strtolower($mp_method);
                    $config_details = $mailpoet_config;
                } else {
                    $config_details['mailpoet'] = $mailpoet_config;
                }
            } else {
                $config_details['mailpoet'] = $mailpoet_config;
            }
        }

        // Authentication & Health evaluation
        $is_using_php_mail = ($active_provider === 'php_mail' || $active_provider === 'mail' || $active_provider === 'mailpoet_phpmail');
        $is_mss_auth       = ($active_provider === 'mailpoet_sending_service' && !empty($mailpoet_config['mss_key_valid']));
        $is_authenticated  = (!$is_using_php_mail && $active_provider !== 'php_mail') || $is_mss_auth;
        $health_status     = !$is_authenticated ? 'warning' : (!empty($recent_failures) ? 'notice' : 'healthy');

        $alerts = [];
        if (!$is_authenticated) {
            $alerts[] = esc_html__('CRITICAL: No authenticated SMTP provider is active. WordPress is using unauthenticated PHP mail(). Emails (order confirmations, customer receipts, password resets) have high spam score and will be rejected or quarantined by Gmail, Outlook, Yahoo.', 'woo-get-data-for-ai');
        } elseif ($active_provider === 'mailpoet_sending_service' && empty($mailpoet_config['mss_key_valid'])) {
            $alerts[] = sprintf(
                /* translators: %s: MSS key status */
                esc_html__('CRITICAL: MailPoet Sending Service is configured for transactional emails, but the API key state is invalid or unapproved (%s). Delivery of emails may be paused.', 'woo-get-data-for-ai'),
                esc_html($mailpoet_config['mss_key_state'])
            );
        }

        if (!empty($recent_failures)) {
            $alerts[] = sprintf(
                /* translators: %d: Failures count */
                esc_html__('%d recent email delivery failure(s) recorded in mail log.', 'woo-get-data-for-ai'),
                count($recent_failures)
            );
        }

        return $this->response([
            'health'             => $health_status,
            'active_plugin'      => $active_plugin,
            'transport_provider' => $active_provider,
            'is_authenticated'   => $is_authenticated,
            'detected_plugins'   => $detected_plugins,
            'configuration'      => $config_details,
            'recent_failures'    => $recent_failures,
            'alerts'             => $alerts,
        ]);
    }

    /**
     * GET /system/mail/subscriber
     * MailPoet subscriber diagnostic: checks subscription status, bounces, and segment memberships.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_mail_subscriber(\WP_REST_Request $request) {
        global $wpdb;

        $email = sanitize_email((string) $request->get_param('email'));
        if (empty($email) || !is_email($email)) {
            return new \WP_Error(
                'agent_bridge_invalid_email',
                esc_html__('A valid email parameter is required for subscriber diagnostic.', 'woo-get-data-for-ai'),
                ['status' => 400]
            );
        }

        $subscribers_table = $wpdb->prefix . 'mailpoet_subscribers';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$subscribers_table}'") !== $subscribers_table) {
            return $this->response([
                'mailpoet_active' => false,
                'found'           => false,
                'email'           => Redaction::redact_email($email),
                'message'         => esc_html__('MailPoet subscribers table does not exist or MailPoet is not installed.', 'woo-get-data-for-ai'),
            ]);
        }

        $subscriber = $wpdb->get_row($wpdb->prepare("
            SELECT id, wp_user_id, first_name, last_name, email, status, source, count_confirmations,
                   confirmed_at, confirmed_ip, created_at, updated_at, deleted_at, last_subscribed_at, last_engagement_at
            FROM {$subscribers_table}
            WHERE email = %s
            LIMIT 1
        ", $email));

        if (!$subscriber) {
            return $this->response([
                'mailpoet_active' => true,
                'found'           => false,
                'email'           => Redaction::redact_email($email),
                'status'          => 'not_found',
                'message'         => esc_html__('No MailPoet subscriber record found for this email address.', 'woo-get-data-for-ai'),
                'alerts'          => [],
            ]);
        }

        // Retrieve lists and segments
        $lists = [];
        $sub_segments_table = $wpdb->prefix . 'mailpoet_subscriber_segment';
        $segments_table     = $wpdb->prefix . 'mailpoet_segments';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$sub_segments_table}'") === $sub_segments_table &&
            $wpdb->get_var("SHOW TABLES LIKE '{$segments_table}'") === $segments_table) {

            $seg_rows = $wpdb->get_results($wpdb->prepare("
                SELECT s.id, s.name, s.type, ss.status as subscription_status, ss.created_at, ss.updated_at
                FROM {$sub_segments_table} ss
                JOIN {$segments_table} s ON s.id = ss.segment_id
                WHERE ss.subscriber_id = %d
                ORDER BY s.name ASC
            ", (int) $subscriber->id), ARRAY_A);

            if (!empty($seg_rows)) {
                foreach ($seg_rows as $row) {
                    $lists[] = [
                        'id'                  => (int) $row['id'],
                        'name'                => sanitize_text_field($row['name']),
                        'type'                => sanitize_text_field($row['type']),
                        'subscription_status' => sanitize_text_field($row['subscription_status']),
                        'created_at'          => $row['created_at'],
                        'updated_at'          => $row['updated_at'],
                    ];
                }
            }
        }

        // Status analysis & diagnostic alerts
        $alerts = [];
        $is_bounced = ($subscriber->status === 'bounced');

        if ($is_bounced) {
            $alerts[] = esc_html__('CRITICAL BOUNCE ALERT: Address is marked as "bounced" in MailPoet. The MailPoet Sending Service (MSS) silently drops/blocks all transactional emails (order confirmations, status updates, password resets) sent to bounced addresses to protect sender reputation. The bounce status must be cleared or reset in MailPoet > Subscribers.', 'woo-get-data-for-ai');
        } elseif ($subscriber->status === 'unsubscribed') {
            $alerts[] = esc_html__('NOTICE: Address is marked as "unsubscribed". Marketing campaigns will not be sent. Transactional emails will still dispatch only if send_transactional_emails is enabled in MailPoet.', 'woo-get-data-for-ai');
        } elseif ($subscriber->status === 'unconfirmed') {
            $alerts[] = esc_html__('NOTICE: Address is unconfirmed (pending double opt-in confirmation).', 'woo-get-data-for-ai');
        } elseif ($subscriber->status === 'inactive') {
            $alerts[] = esc_html__('NOTICE: Subscriber marked inactive due to prolonged engagement dormancy.', 'woo-get-data-for-ai');
        }

        return $this->response([
            'mailpoet_active'     => true,
            'found'               => true,
            'subscriber_id'       => (int) $subscriber->id,
            'wp_user_id'          => !empty($subscriber->wp_user_id) ? (int) $subscriber->wp_user_id : null,
            'email'               => Redaction::redact_email($subscriber->email),
            'first_name'          => Redaction::redact_string($subscriber->first_name ?? ''),
            'last_name'           => Redaction::redact_string($subscriber->last_name ?? ''),
            'status'              => $subscriber->status,
            'is_bounced'          => $is_bounced,
            'source'              => $subscriber->source,
            'count_confirmations' => (int) $subscriber->count_confirmations,
            'confirmed_at'        => $subscriber->confirmed_at,
            'last_subscribed_at'  => $subscriber->last_subscribed_at,
            'last_engagement_at'  => $subscriber->last_engagement_at,
            'created_at'          => $subscriber->created_at,
            'updated_at'          => $subscriber->updated_at,
            'lists'               => $lists,
            'alerts'              => $alerts,
        ]);
    }

    /**
     * Get runtime performance constants and configuration flags defined in wp-config.php.
     *
     * @return array
     */
    public static function get_runtime_performance_constants() {
        $savequeries         = defined('SAVEQUERIES') && SAVEQUERIES;
        $script_debug        = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG;
        $wp_debug            = defined('WP_DEBUG') && WP_DEBUG;
        $wp_debug_display    = defined('WP_DEBUG_DISPLAY') && WP_DEBUG_DISPLAY;
        $wp_debug_log        = defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
        $wp_cache            = defined('WP_CACHE') && WP_CACHE;
        $disable_wp_cron     = defined('DISABLE_WP_CRON') && DISABLE_WP_CRON;
        $alternate_wp_cron   = defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON;
        $disallow_file_edit  = defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT;
        $disallow_file_mods  = defined('DISALLOW_FILE_MODS') && DISALLOW_FILE_MODS;
        $concatenate_scripts = defined('CONCATENATE_SCRIPTS') ? (bool) CONCATENATE_SCRIPTS : null;
        $compress_scripts    = defined('COMPRESS_SCRIPTS') ? (bool) COMPRESS_SCRIPTS : null;
        $compress_css        = defined('COMPRESS_CSS') ? (bool) COMPRESS_CSS : null;

        // Post revisions
        $post_revisions_raw     = defined('WP_POST_REVISIONS') ? WP_POST_REVISIONS : null;
        $is_revisions_unlimited = ($post_revisions_raw === null || $post_revisions_raw === true || $post_revisions_raw === -1);
        $revisions_cap          = $is_revisions_unlimited ? 'unlimited' : (int) $post_revisions_raw;

        // Memory limit
        $wp_memory_limit     = defined('WP_MEMORY_LIMIT') ? WP_MEMORY_LIMIT : '40M';
        $wp_max_memory_limit = defined('WP_MAX_MEMORY_LIMIT') ? WP_MAX_MEMORY_LIMIT : '256M';

        // Environment
        $environment_type = function_exists('wp_get_environment_type') ? wp_get_environment_type() : (defined('WP_ENVIRONMENT_TYPE') ? WP_ENVIRONMENT_TYPE : 'production');

        // Build actionable performance alerts
        $alerts = [];

        if ($savequeries) {
            $alerts[] = [
                'constant' => 'SAVEQUERIES',
                'severity' => 'critical',
                'issue'    => esc_html__('SAVEQUERIES is active in wp-config.php.', 'woo-get-data-for-ai'),
                'message'  => esc_html__('WordPress is recording every SQL query in memory with execution backtraces. On production stores, this severely degrades TTFB and can cause PHP memory exhaustion.', 'woo-get-data-for-ai'),
                'solution' => esc_html__('Set define(\'SAVEQUERIES\', false); or remove it in wp-config.php.', 'woo-get-data-for-ai'),
            ];
        }

        if ($script_debug) {
            $alerts[] = [
                'constant' => 'SCRIPT_DEBUG',
                'severity' => 'warning',
                'issue'    => esc_html__('SCRIPT_DEBUG is active in wp-config.php.', 'woo-get-data-for-ai'),
                'message'  => esc_html__('WordPress loads unminified core CSS and JavaScript files, inflating frontend asset payload and slowing down page rendering.', 'woo-get-data-for-ai'),
                'solution' => esc_html__('Set define(\'SCRIPT_DEBUG\', false); in production wp-config.php.', 'woo-get-data-for-ai'),
            ];
        }

        if ($wp_debug_display) {
            $alerts[] = [
                'constant' => 'WP_DEBUG_DISPLAY',
                'severity' => 'high',
                'issue'    => esc_html__('WP_DEBUG_DISPLAY is enabled in production.', 'woo-get-data-for-ai'),
                'message'  => esc_html__('PHP errors, database paths, and sensitive stack traces may be displayed to visitors.', 'woo-get-data-for-ai'),
                'solution' => esc_html__('Set define(\'WP_DEBUG_DISPLAY\', false); in wp-config.php.', 'woo-get-data-for-ai'),
            ];
        }

        if ($is_revisions_unlimited) {
            $alerts[] = [
                'constant' => 'WP_POST_REVISIONS',
                'severity' => 'info',
                'issue'    => esc_html__('WP_POST_REVISIONS is not strictly capped.', 'woo-get-data-for-ai'),
                'message'  => esc_html__('Every post and product revision is stored indefinitely, bloating wp_posts and slowing database queries over time.', 'woo-get-data-for-ai'),
                'solution' => esc_html__('Add define(\'WP_POST_REVISIONS\', 5); in wp-config.php to limit revisions to 5 per post/product.', 'woo-get-data-for-ai'),
            ];
        }

        if (class_exists('WooCommerce')) {
            $mem_bytes = self::parse_memory_bytes($wp_memory_limit);
            if ($mem_bytes > 0 && $mem_bytes < 256 * 1024 * 1024) {
                $alerts[] = [
                    'constant' => 'WP_MEMORY_LIMIT',
                    'severity' => 'medium',
                    'issue'    => sprintf(esc_html__('WP_MEMORY_LIMIT (%s) is below 256M.', 'woo-get-data-for-ai'), $wp_memory_limit),
                    'message'  => esc_html__('WooCommerce recommends a minimum of 256M for WordPress memory limit to prevent memory exhaustion during checkout, stock imports, or image processing.', 'woo-get-data-for-ai'),
                    'solution' => esc_html__('Add define(\'WP_MEMORY_LIMIT\', \'256M\'); in wp-config.php.', 'woo-get-data-for-ai'),
                ];
            }
        }

        return [
            'savequeries'         => $savequeries,
            'script_debug'        => $script_debug,
            'wp_debug'            => $wp_debug,
            'wp_debug_display'    => $wp_debug_display,
            'wp_debug_log'        => $wp_debug_log,
            'wp_cache'            => $wp_cache,
            'wp_post_revisions'   => $revisions_cap,
            'revisions_capped'    => !$is_revisions_unlimited,
            'wp_memory_limit'     => $wp_memory_limit,
            'wp_max_memory_limit' => $wp_max_memory_limit,
            'disable_wp_cron'     => $disable_wp_cron,
            'alternate_wp_cron'   => $alternate_wp_cron,
            'disallow_file_edit'  => $disallow_file_edit,
            'disallow_file_mods'  => $disallow_file_mods,
            'environment_type'    => $environment_type,
            'concatenate_scripts' => $concatenate_scripts,
            'compress_scripts'    => $compress_scripts,
            'compress_css'        => $compress_css,
            'has_critical_alerts' => $savequeries,
            'alerts_count'        => count($alerts),
            'alerts'              => $alerts,
        ];
    }

    /**
     * Convert memory string (e.g. '256M', '1G', '64m') to bytes defensively.
     *
     * @param string $val
     * @return int
     */
    public static function parse_memory_bytes($val) {
        if (function_exists('wp_convert_hr_to_bytes')) {
            return wp_convert_hr_to_bytes($val);
        }
        $val = trim((string) $val);
        if (empty($val)) {
            return 0;
        }
        $last = strtolower(substr($val, -1));
        $num  = (int) $val;
        switch ($last) {
            case 'g':
                $num *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $num *= 1024 * 1024;
                break;
            case 'k':
                $num *= 1024;
                break;
        }
        return $num;
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
        $savequeries        = defined('SAVEQUERIES') && SAVEQUERIES;
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
        if ($savequeries) {
            $recommendations[] = [
                'severity' => 'critical',
                'issue'    => 'SAVEQUERIES is active in production.',
                'solution' => 'Set define(\'SAVEQUERIES\', false); or remove it in wp-config.php. Logging every SQL query in memory degrades TTFB and risks PHP memory exhaustion.',
            ];
        }
        if ($script_debug) {
            $recommendations[] = [
                'severity' => 'medium',
                'issue'    => 'SCRIPT_DEBUG is active in production.',
                'solution' => 'Set define(\'SCRIPT_DEBUG\', false); in wp-config.php to ensure minified core JavaScript and CSS are served.',
            ];
        }
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
                'savequeries'        => $savequeries,
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

    /**
     * GET /system/search
     * Global read-only search across wp_posts, sanitized wp_options, and WPCode / Code Snippets.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function search_system(\WP_REST_Request $request) {
        $raw_query = $request->get_param('query') ?: $request->get_param('q');
        $query     = trim((string) $raw_query);

        if (mb_strlen($query) < 2) {
            return $this->error(
                'query_too_short',
                esc_html__('Search query must be at least 2 characters long.', 'woo-get-data-for-ai'),
                400
            );
        }

        $target        = strtolower(trim((string) ($request->get_param('target') ?: 'all')));
        $limit         = min(max(1, (int) ($request->get_param('limit') ?: 20)), 50);
        $raw_post_type = $request->get_param('post_type') ?: 'any';

        global $wpdb;
        $like = '%' . $wpdb->esc_like($query) . '%';

        // Snippet extractor helper (returns ±80 chars around match without HTML tags)
        $extract_snippet = function ($text, $term, $radius = 80) {
            if (empty($text) || !is_string($text)) {
                return '';
            }
            $clean = wp_strip_all_tags($text);
            $pos   = mb_stripos($clean, $term);
            if ($pos === false) {
                return mb_substr($clean, 0, $radius * 2);
            }
            $start   = max(0, $pos - $radius);
            $length  = ($pos - $start) + mb_strlen($term) + $radius;
            $snippet = mb_substr($clean, $start, $length);
            $prefix  = ($start > 0) ? '...' : '';
            $suffix  = (mb_strlen($clean) > ($start + $length)) ? '...' : '';
            return $prefix . trim(preg_replace('/\s+/', ' ', $snippet)) . $suffix;
        };

        $posts_matches    = [];
        $options_matches  = [];
        $snippets_matches = [];

        // 1. Search in wp_posts
        if ($target === 'all' || $target === 'posts') {
            $post_type_sql = "";
            if ($raw_post_type === 'any') {
                $post_type_sql = "AND post_type NOT IN ('revision')";
            } elseif (strpos($raw_post_type, ',') !== false) {
                $types = array_filter(array_map('sanitize_key', explode(',', $raw_post_type)));
                if (!empty($types)) {
                    $types_in      = "'" . implode("','", array_map('esc_sql', $types)) . "'";
                    $post_type_sql = "AND post_type IN ($types_in)";
                }
            } else {
                $single_type   = sanitize_key($raw_post_type);
                $post_type_sql = $wpdb->prepare("AND post_type = %s", $single_type);
            }

            $posts_sql = $wpdb->prepare(
                "SELECT ID, post_title, post_name, post_type, post_status, post_date, post_modified, post_content, post_excerpt
                 FROM {$wpdb->posts}
                 WHERE (post_title LIKE %s OR post_content LIKE %s OR post_name LIKE %s OR post_excerpt LIKE %s)
                 {$post_type_sql}
                 ORDER BY post_modified DESC
                 LIMIT %d",
                $like, $like, $like, $like, $limit
            );

            $posts_rows = $wpdb->get_results($posts_sql);
            if ($posts_rows) {
                foreach ($posts_rows as $row) {
                    $in_title   = (mb_stripos($row->post_title, $query) !== false);
                    $in_slug    = (mb_stripos($row->post_name, $query) !== false);
                    $in_excerpt = (mb_stripos($row->post_excerpt, $query) !== false);
                    $in_content = (mb_stripos($row->post_content, $query) !== false);

                    $snippet_text = $in_content ? $row->post_content : ($in_excerpt ? $row->post_excerpt : $row->post_title);
                    $snippet      = $extract_snippet($snippet_text, $query, 80);

                    $posts_matches[] = [
                        'id'          => (int) $row->ID,
                        'title'       => html_entity_decode($row->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        'slug'        => $row->post_name,
                        'post_type'   => $row->post_type,
                        'post_status' => $row->post_status,
                        'modified'    => $row->post_modified,
                        'url'         => get_permalink($row->ID),
                        'match_in'    => array_values(array_filter([
                            $in_title ? 'title' : null,
                            $in_slug ? 'slug' : null,
                            $in_excerpt ? 'excerpt' : null,
                            $in_content ? 'content' : null,
                        ])),
                        'snippet'     => $snippet,
                    ];
                }
            }
        }

        // 2. Search in wp_options (Strictly sanitized & sensitive keys redacted)
        if ($target === 'all' || $target === 'options') {
            $options_sql = $wpdb->prepare(
                "SELECT option_name, option_value, autoload
                 FROM {$wpdb->options}
                 WHERE (option_name LIKE %s OR option_value LIKE %s)
                 AND option_name NOT LIKE '_transient_%'
                 AND option_name NOT LIKE '_site_transient_%'
                 ORDER BY option_name ASC
                 LIMIT %d",
                $like, $like, $limit * 3
            );
            $options_rows = $wpdb->get_results($options_sql);

            if ($options_rows) {
                foreach ($options_rows as $row) {
                    if (count($options_matches) >= $limit) {
                        break;
                    }

                    $opt_name     = $row->option_name;
                    $is_sensitive = \WPAgentBridge\Redaction::is_sensitive_key($opt_name);

                    $in_name = (mb_stripos($opt_name, $query) !== false);
                    $in_val  = false;
                    $snippet = '';

                    if ($is_sensitive) {
                        $snippet = '[REDACTED_SENSITIVE_OPTION]';
                    } else {
                        $raw_val = $row->option_value;
                        $in_val  = (mb_stripos($raw_val, $query) !== false);

                        $decoded = maybe_unserialize($raw_val);
                        if (is_array($decoded) || is_object($decoded)) {
                            $cleaned   = \WPAgentBridge\Redaction::redact_array((array) $decoded);
                            $as_string = wp_json_encode($cleaned);
                        } else {
                            $as_string = \WPAgentBridge\Redaction::redact_string($raw_val);
                        }

                        $snippet = $extract_snippet($as_string, $query, 80);
                    }

                    $options_matches[] = [
                        'option_name'  => $opt_name,
                        'autoload'     => $row->autoload,
                        'is_sensitive' => $is_sensitive,
                        'match_in'     => array_values(array_filter([
                            $in_name ? 'option_name' : null,
                            $in_val ? 'option_value' : null,
                        ])),
                        'snippet'      => $snippet,
                    ];
                }
            }
        }

        // 3. Search in WPCode and Code Snippets
        if ($target === 'all' || $target === 'snippets') {
            // A. WPCode Custom Post Type
            $wpcode_posts = get_posts([
                'post_type'      => 'wpcode',
                'post_status'    => ['publish', 'draft'],
                'posts_per_page' => 100,
            ]);

            $seen_wpcode_ids = [];
            foreach ($wpcode_posts as $p) {
                if (isset($seen_wpcode_ids[$p->ID])) {
                    continue;
                }
                $seen_wpcode_ids[$p->ID] = true;

                $code = get_post_meta($p->ID, '_wpcode_code', true);
                if (empty($code)) {
                    $code = $p->post_content;
                }
                $notes        = get_post_meta($p->ID, '_wpcode_note', true) ?: '';
                $snippet_type = get_post_meta($p->ID, '_wpcode_snippet_type', true) ?: 'php';

                $in_title = (mb_stripos($p->post_title, $query) !== false);
                $in_code  = (mb_stripos($code, $query) !== false);
                $in_notes = (mb_stripos($notes, $query) !== false);

                if ($in_title || $in_code || $in_notes) {
                    $match_text           = $in_code ? $code : ($in_notes ? $notes : $p->post_title);
                    $snippet_code_excerpt = $extract_snippet($match_text, $query, 80);

                    $snippets_matches[] = [
                        'id'             => $p->ID,
                        'name'           => html_entity_decode($p->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                        'source'         => 'wpcode',
                        'active'         => ($p->post_status === 'publish'),
                        'type'           => $snippet_type,
                        'match_in'       => array_values(array_filter([
                            $in_title ? 'title' : null,
                            $in_code ? 'code' : null,
                            $in_notes ? 'notes' : null,
                        ])),
                        'snippet'        => $snippet_code_excerpt,
                        'admin_edit_url' => admin_url('admin.php?page=wpcode-snippet-manager&snippet_id=' . $p->ID),
                    ];
                }

                if (count($snippets_matches) >= $limit) {
                    break;
                }
            }

            // B. Code Snippets table
            $table_snippets = $wpdb->prefix . 'snippets';
            $has_cs_table   = ($wpdb->get_var("SHOW TABLES LIKE '$table_snippets'") === $table_snippets);

            if ($has_cs_table && count($snippets_matches) < $limit) {
                $cs_sql = $wpdb->prepare(
                    "SELECT id, name, description, code, tags, scope, priority, active
                     FROM `{$table_snippets}`
                     WHERE (name LIKE %s OR description LIKE %s OR code LIKE %s OR tags LIKE %s)
                     ORDER BY name ASC
                     LIMIT %d",
                    $like, $like, $like, $like, ($limit - count($snippets_matches))
                );
                $cs_rows = $wpdb->get_results($cs_sql);

                if ($cs_rows) {
                    foreach ($cs_rows as $row) {
                        $in_name = (mb_stripos($row->name, $query) !== false);
                        $in_desc = (mb_stripos($row->description, $query) !== false);
                        $in_code = (mb_stripos($row->code, $query) !== false);
                        $in_tags = (mb_stripos($row->tags, $query) !== false);

                        $match_text           = $in_code ? $row->code : ($in_desc ? $row->description : $row->name);
                        $snippet_code_excerpt = $extract_snippet($match_text, $query, 80);

                        $snippets_matches[] = [
                            'id'             => (int) $row->id,
                            'name'           => html_entity_decode($row->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                            'source'         => 'code-snippets',
                            'active'         => ((int) $row->active === 1),
                            'type'           => $row->scope ?? 'global',
                            'match_in'       => array_values(array_filter([
                                $in_name ? 'name' : null,
                                $in_desc ? 'description' : null,
                                $in_code ? 'code' : null,
                                $in_tags ? 'tags' : null,
                            ])),
                            'snippet'        => $snippet_code_excerpt,
                            'admin_edit_url' => admin_url('admin.php?page=edit-snippet&id=' . $row->id),
                        ];
                    }
                }
            }
        }

        return $this->response([
            'query'   => $query,
            'target'  => $target,
            'counts'  => [
                'posts'    => count($posts_matches),
                'options'  => count($options_matches),
                'snippets' => count($snippets_matches),
                'total'    => count($posts_matches) + count($options_matches) + count($snippets_matches),
            ],
            'posts'    => $posts_matches,
            'options'  => $options_matches,
            'snippets' => $snippets_matches,
        ]);
    }
}
