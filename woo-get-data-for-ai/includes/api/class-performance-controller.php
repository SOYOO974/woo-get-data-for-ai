<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Permissions;
use WPAgentBridge\Redaction;

class Performance_Controller extends Rest_Controller {

    /**
     * Transient prefix for single-request profiling tokens.
     */
    const PROFILING_TOKEN_PREFIX = 'wpab_prof_';

    /**
     * Register REST API routes for performance profiling.
     */
    public function register_routes() {
        // GET /performance/profile (On-demand URL profiler: SQL per plugin, assets per plugin, TTFB, memory)
        register_rest_route(self::NAMESPACE, '/performance/profile', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_page_profile'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'performance');
            },
            'args'                => [
                'path' => [
                    'type'              => 'string',
                    'default'           => '/',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Relative URL path to benchmark (e.g. "/", "/boutique/", "/panier/").',
                ],
                'include_assets' => [
                    'type'              => 'boolean',
                    'default'           => true,
                    'description'       => 'Whether to inspect and attribute frontend JS/CSS assets per plugin.',
                ],
                'include_queries' => [
                    'type'              => 'boolean',
                    'default'           => true,
                    'description'       => 'Whether to attribute SQL queries and duration per plugin.',
                ],
                'slow_query_threshold_ms' => [
                    'type'              => 'integer',
                    'default'           => 50,
                    'sanitize_callback' => 'absint',
                    'description'       => 'Threshold in milliseconds to flag slow database queries.',
                ],
            ],
        ]);

        // GET /performance/autoload (Deep analysis of wp_options autoload bloat grouped by plugin prefix)
        register_rest_route(self::NAMESPACE, '/performance/autoload', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_autoload_analysis'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'performance');
            },
            'args'                => [
                'limit' => [
                    'type'              => 'integer',
                    'default'           => 25,
                    'sanitize_callback' => 'absint',
                    'description'       => 'Number of heaviest individual autoload options to return.',
                ],
            ],
        ]);

        // GET /performance/plugins-summary (Consolidated resource footprint per plugin: DB tables, autoload, crons)
        register_rest_route(self::NAMESPACE, '/performance/plugins-summary', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_plugins_summary'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'performance');
            },
            'args'                => [
                'status' => [
                    'type'              => 'string',
                    'default'           => 'active',
                    'enum'              => ['active', 'all'],
                    'description'       => 'Filter by plugin activation status.',
                ],
            ],
        ]);
    }

    /**
     * Intercept and profile a single synthetic loopback request when a valid token is provided.
     * ZERO idle overhead: only executes if a signed header or query param is present.
     */
    public static function init_profiling_catcher() {
        $token = '';
        if (isset($_SERVER['HTTP_X_WP_AGENT_BRIDGE_PROFILE'])) {
            $token = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_WP_AGENT_BRIDGE_PROFILE']));
        } elseif (isset($_GET['wpab_profile_token'])) {
            $token = sanitize_text_field(wp_unslash($_GET['wpab_profile_token']));
        }

        if (empty($token) || strpos($token, self::PROFILING_TOKEN_PREFIX) !== 0) {
            return;
        }

        // Validate the transient token
        $stored = get_transient($token);
        if (!$stored || !is_array($stored) || empty($stored['status'])) {
            return;
        }

        // Prevent caching on this request
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        // Enable SQL queries logging for this single request
        global $wpdb;
        $wpdb->save_queries = true;

        $start_time = microtime(true);

        // Register shutdown hook to capture metrics and persist them to transient
        add_action('shutdown', function () use ($token, $start_time, $stored) {
            self::capture_and_store_profile($token, $start_time, $stored);
        }, 99999);
    }

    /**
     * Capture runtime metrics at shutdown and store them in the temporary transient.
     *
     * @param string $token
     * @param float  $start_time
     * @param array  $stored_config
     */
    private static function capture_and_store_profile($token, $start_time, $stored_config) {
        global $wpdb, $template;

        $total_duration_ms = round((microtime(true) - $start_time) * 1000, 2);
        $peak_memory_mb    = round(memory_get_peak_usage(true) / (1024 * 1024), 2);

        $slow_threshold_ms = isset($stored_config['slow_query_threshold_ms']) ? absint($stored_config['slow_query_threshold_ms']) : 50;
        $include_queries   = !empty($stored_config['include_queries']);
        $include_assets    = !empty($stored_config['include_assets']);

        $sql_data = [
            'total_queries'     => 0,
            'total_time_ms'     => 0.0,
            'duplicate_queries' => 0,
            'by_component'      => [],
            'slow_queries'      => [],
        ];

        // 1. Analyze SQL queries
        if ($include_queries && !empty($wpdb->queries) && is_array($wpdb->queries)) {
            $sql_data['total_queries'] = count($wpdb->queries);
            $total_sql_seconds = 0.0;
            $seen_queries = [];

            foreach ($wpdb->queries as $q) {
                $query_sql     = isset($q[0]) ? (string) $q[0] : '';
                $query_seconds = isset($q[1]) ? (float) $q[1] : 0.0;
                $caller        = isset($q[2]) ? (string) $q[2] : '';

                $total_sql_seconds += $query_seconds;
                $query_ms = round($query_seconds * 1000, 3);

                // Identify component (plugin, theme, core)
                $component = self::classify_caller($caller);

                if (!isset($sql_data['by_component'][$component])) {
                    $sql_data['by_component'][$component] = [
                        'queries' => 0,
                        'time_ms' => 0.0,
                    ];
                }
                $sql_data['by_component'][$component]['queries']++;
                $sql_data['by_component'][$component]['time_ms'] += $query_ms;

                // Detect duplicate queries
                $normalized = strtolower(trim(preg_replace('/\s+/', ' ', $query_sql)));
                $query_hash = md5($normalized);
                if (isset($seen_queries[$query_hash])) {
                    $seen_queries[$query_hash]['count']++;
                    $seen_queries[$query_hash]['wasted_time_ms'] += $query_ms;
                } else {
                    $seen_queries[$query_hash] = [
                        'sql'            => strlen($query_sql) > 160 ? substr($query_sql, 0, 160) . '...' : $query_sql,
                        'component'      => $component,
                        'count'          => 1,
                        'wasted_time_ms' => 0.0,
                    ];
                }

                // Detect slow queries
                if ($query_ms >= $slow_threshold_ms) {
                    $sql_data['slow_queries'][] = [
                        'sql'       => strlen($query_sql) > 250 ? substr($query_sql, 0, 250) . '...' : $query_sql,
                        'time_ms'   => $query_ms,
                        'component' => $component,
                        'caller'    => strlen($caller) > 120 ? substr($caller, 0, 120) . '...' : $caller,
                    ];
                }
            }

            $sql_data['total_time_ms'] = round($total_sql_seconds * 1000, 2);

            // Filter duplicate queries (> 1 occurrence)
            $duplicates_list = [];
            foreach ($seen_queries as $sq) {
                if ($sq['count'] > 1) {
                    $duplicates_list[] = [
                        'sql'            => $sq['sql'],
                        'component'      => $sq['component'],
                        'count'          => $sq['count'],
                        'wasted_time_ms' => round($sq['wasted_time_ms'], 2),
                    ];
                }
            }
            $sql_data['duplicate_queries'] = count($duplicates_list);
            $sql_data['top_duplicates']    = array_slice($duplicates_list, 0, 10);

            // Calculate percentage time for each component and round
            foreach ($sql_data['by_component'] as $comp => &$cdata) {
                $cdata['time_ms'] = round($cdata['time_ms'], 2);
                $cdata['pct_of_sql_time'] = $sql_data['total_time_ms'] > 0
                    ? round(($cdata['time_ms'] / $sql_data['total_time_ms']) * 100, 1)
                    : 0;
            }
            unset($cdata);

            // Sort components by highest SQL time
            uasort($sql_data['by_component'], function ($a, $b) {
                return $b['time_ms'] <=> $a['time_ms'];
            });
        }

        // 2. Analyze Enqueued Frontend Assets (Scripts & Styles)
        $assets_data = [
            'total_scripts'   => 0,
            'total_styles'    => 0,
            'total_assets'    => 0,
            'by_component'    => [],
        ];

        if ($include_assets) {
            $wp_scripts = wp_scripts();
            $wp_styles  = wp_styles();

            $classify_asset_src = function ($src) {
                if (empty($src)) {
                    return 'inline/core';
                }
                if (preg_match('#/wp-content/plugins/([a-zA-Z0-9_\-]+)/#', $src, $m)) {
                    return 'plugin:' . $m[1];
                }
                if (preg_match('#/wp-content/themes/([a-zA-Z0-9_\-]+)/#', $src, $m)) {
                    return 'theme:' . $m[1];
                }
                if (preg_match('#/wp-content/mu-plugins/([a-zA-Z0-9_\-]+)#', $src, $m)) {
                    return 'mu-plugin:' . $m[1];
                }
                if (preg_match('#^https?://#i', $src) && strpos($src, site_url()) === false) {
                    return 'external';
                }
                return 'core';
            };

            // Scripts
            if ($wp_scripts instanceof \WP_Scripts && !empty($wp_scripts->queue)) {
                foreach ($wp_scripts->queue as $handle) {
                    $assets_data['total_scripts']++;
                    $src = isset($wp_scripts->registered[$handle]->src) ? $wp_scripts->registered[$handle]->src : '';
                    $comp = $classify_asset_src($src);

                    if (!isset($assets_data['by_component'][$comp])) {
                        $assets_data['by_component'][$comp] = ['scripts' => 0, 'styles' => 0, 'total' => 0];
                    }
                    $assets_data['by_component'][$comp]['scripts']++;
                    $assets_data['by_component'][$comp]['total']++;
                }
            }

            // Styles
            if ($wp_styles instanceof \WP_Styles && !empty($wp_styles->queue)) {
                foreach ($wp_styles->queue as $handle) {
                    $assets_data['total_styles']++;
                    $src = isset($wp_styles->registered[$handle]->src) ? $wp_styles->registered[$handle]->src : '';
                    $comp = $classify_asset_src($src);

                    if (!isset($assets_data['by_component'][$comp])) {
                        $assets_data['by_component'][$comp] = ['scripts' => 0, 'styles' => 0, 'total' => 0];
                    }
                    $assets_data['by_component'][$comp]['styles']++;
                    $assets_data['by_component'][$comp]['total']++;
                }
            }

            $assets_data['total_assets'] = $assets_data['total_scripts'] + $assets_data['total_styles'];

            // Sort components by highest total assets count
            uasort($assets_data['by_component'], function ($a, $b) {
                return $b['total'] <=> $a['total'];
            });
        }

        // 3. Template and Query Monitor info
        $template_name = !empty($template) ? basename($template) : 'unknown';
        $query_monitor = class_exists('QueryMonitor') || defined('QM_VERSION');

        // 4. Save results back into transient
        set_transient($token, [
            'status'         => 'completed',
            'captured_at'    => current_time('c'),
            'path'           => $stored_config['path'] ?? '/',
            'ttfb_ms'        => $total_duration_ms,
            'memory_peak_mb' => $peak_memory_mb,
            'template'       => $template_name,
            'query_monitor'  => $query_monitor,
            'sql'            => $sql_data,
            'assets'         => $assets_data,
        ], 60);
    }

    /**
     * Classify backtrace caller string into plugin, theme, or core.
     *
     * @param string $caller
     * @return string
     */
    private static function classify_caller($caller) {
        if (empty($caller)) {
            return 'core';
        }

        // 1. Direct plugin path
        if (preg_match('#wp-content[\\/]plugins[\\/]([a-zA-Z0-9_\-]+)[\\/]#i', $caller, $matches)) {
            return 'plugin:' . $matches[1];
        }

        // 2. Direct theme path
        if (preg_match('#wp-content[\\/]themes[\\/]([a-zA-Z0-9_\-]+)[\\/]#i', $caller, $matches)) {
            return 'theme:' . $matches[1];
        }

        // 3. Mu-plugins path
        if (preg_match('#wp-content[\\/]mu-plugins[\\/]([a-zA-Z0-9_\-]+)#i', $caller, $matches)) {
            return 'mu-plugin:' . $matches[1];
        }

        // 4. Common plugin class or namespace signatures
        $signatures = [
            'Automattic\WooCommerce' => 'plugin:woocommerce',
            'WC_'                    => 'plugin:woocommerce',
            'wc_'                    => 'plugin:woocommerce',
            'Elementor\\'            => 'plugin:elementor',
            'RankMath\\'             => 'plugin:seo-by-rank-math',
            'WPSEO_'                 => 'plugin:wordpress-seo',
            'Yoast\\'                => 'plugin:wordpress-seo',
            'ActionScheduler'        => 'plugin:action-scheduler',
            'WPAgentBridge\\'        => 'plugin:wp-agent-bridge',
            'woodmart_'              => 'theme:woodmart',
            'XTS\\'                  => 'theme:woodmart',
            'FLW_'                   => 'plugin:flowmattic',
            'FlowMattic'             => 'plugin:flowmattic',
            'WPCF7'                  => 'plugin:contact-form-7',
            'WP_Rocket'              => 'plugin:wp-rocket',
        ];

        foreach ($signatures as $sig => $comp) {
            if (stripos($caller, $sig) !== false) {
                return $comp;
            }
        }

        return 'core';
    }

    /**
     * GET /performance/profile
     *
     * Benchmark a target URL, attributing SQL queries, TTFB, memory, and assets per plugin.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_page_profile(\WP_REST_Request $request) {
        $path              = trim((string) $request->get_param('path'));
        $include_assets    = (bool) $request->get_param('include_assets');
        $include_queries   = (bool) $request->get_param('include_queries');
        $slow_threshold_ms = absint($request->get_param('slow_query_threshold_ms'));

        if (empty($path) || $path[0] !== '/') {
            $path = '/' . $path;
        }

        // Generate temporary single-use token (valid for 60 seconds)
        $token = self::PROFILING_TOKEN_PREFIX . wp_generate_password(24, false);

        set_transient($token, [
            'status'                  => 'pending',
            'path'                    => $path,
            'include_assets'          => $include_assets,
            'include_queries'         => $include_queries,
            'slow_query_threshold_ms' => $slow_threshold_ms,
            'created_at'              => microtime(true),
        ], 60);

        // Build target loopback URL
        $target_url = home_url($path);
        $loopback_url = add_query_arg([
            'wpab_profile_token' => $token,
            'nocache'            => wp_generate_password(6, false),
        ], $target_url);

        $request_start = microtime(true);

        // Perform loopback HTTP GET request
        $response = wp_remote_get($loopback_url, [
            'timeout'     => 25,
            'redirection' => 5,
            'sslverify'   => false, // Avoid SSL failures in local/dev/self-signed setups
            'headers'     => [
                'X-WP-Agent-Bridge-Profile' => $token,
                'Cache-Control'             => 'no-cache, no-store, must-revalidate',
                'Pragma'                    => 'no-cache',
                'User-Agent'                => 'WP-Agent-Bridge-Profiler/' . WOO_GET_DATA_AI_VERSION,
            ],
        ]);

        $http_elapsed_ms = round((microtime(true) - $request_start) * 1000, 2);

        // Read profiling result from transient
        $profile_result = get_transient($token);
        delete_transient($token);

        $http_code = !is_wp_error($response) ? wp_remote_retrieve_response_code($response) : 0;

        if ($profile_result && is_array($profile_result) && isset($profile_result['status']) && $profile_result['status'] === 'completed') {
            return $this->response([
                'status'            => 'success',
                'url'               => $target_url,
                'path'              => $path,
                'http_status'       => $http_code,
                'http_roundtrip_ms' => $http_elapsed_ms,
                'profile'           => $profile_result,
            ]);
        }

        // If loopback failed (e.g. host cURL loopback restriction)
        $error_message = is_wp_error($response)
            ? $response->get_error_message()
            : sprintf('Loopback request returned HTTP %d but profiler hook did not capture data.', $http_code);

        return $this->response([
            'status'            => 'loopback_failed',
            'url'               => $target_url,
            'path'              => $path,
            'http_status'       => $http_code,
            'http_roundtrip_ms' => $http_elapsed_ms,
            'error'             => $error_message,
            'notice'            => 'The web server could not perform an internal loopback HTTP request to itself. Autoload and database performance endpoints (/performance/autoload and /performance/plugins-summary) remain fully functional.',
        ], 200);
    }

    /**
     * GET /performance/autoload
     *
     * Audit wp_options autoload bloat grouped by plugin prefix.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_autoload_analysis(\WP_REST_Request $request) {
        global $wpdb;

        $limit = absint($request->get_param('limit'));
        if ($limit < 5 || $limit > 100) {
            $limit = 25;
        }

        // 1. Overall autoload statistics
        $overall = $wpdb->get_row(
            "SELECT COUNT(*) AS total_count, COALESCE(SUM(LENGTH(option_value)), 0) AS total_bytes 
             FROM {$wpdb->options} 
             WHERE autoload IN ('yes', 'on', '1')"
        );

        $total_count = $overall ? (int) $overall->total_count : 0;
        $total_bytes = $overall ? (int) $overall->total_bytes : 0;
        $total_kb    = round($total_bytes / 1024, 2);

        // Status assessment according to WP Core recommendation (ideal < 800 KB)
        $status = 'good';
        $alert  = null;
        if ($total_kb > 1500) {
            $status = 'critical';
            $alert  = sprintf('Critical autoload size: %.2f KB. Exceeds recommended 800 KB threshold by %.1fx, degrading TTFB on every request.', $total_kb, $total_kb / 800);
        } elseif ($total_kb > 800) {
            $status = 'warning';
            $alert  = sprintf('High autoload size: %.2f KB. Slightly exceeds recommended 800 KB threshold.', $total_kb);
        }

        // 2. Fetch top heaviest autoloaded options
        $heavy_options = $wpdb->get_results($wpdb->prepare(
            "SELECT option_name, LENGTH(option_value) AS size_bytes 
             FROM {$wpdb->options} 
             WHERE autoload IN ('yes', 'on', '1') 
             ORDER BY size_bytes DESC 
             LIMIT %d",
            $limit
        ));

        $top_options = [];
        $by_component = [];

        if ($heavy_options) {
            foreach ($heavy_options as $row) {
                $comp = self::classify_option_name($row->option_name);
                $opt_bytes = (int) $row->size_bytes;
                $opt_kb    = round($opt_bytes / 1024, 2);

                $top_options[] = [
                    'option_name' => $row->option_name,
                    'size_bytes'  => $opt_bytes,
                    'size_kb'     => $opt_kb,
                    'component'   => $comp,
                ];

                if (!isset($by_component[$comp])) {
                    $by_component[$comp] = [
                        'count'       => 0,
                        'total_bytes' => 0,
                        'total_kb'    => 0.0,
                    ];
                }
                $by_component[$comp]['count']++;
                $by_component[$comp]['total_bytes'] += $opt_bytes;
                $by_component[$comp]['total_kb']    += $opt_kb;
            }

            foreach ($by_component as &$cdata) {
                $cdata['total_kb'] = round($cdata['total_kb'], 2);
            }
            unset($cdata);

            uasort($by_component, function ($a, $b) {
                return $b['total_bytes'] <=> $a['total_bytes'];
            });
        }

        return $this->response([
            'status'         => $status,
            'alert'          => $alert,
            'total_options'  => $total_count,
            'total_size_bytes' => $total_bytes,
            'total_size_kb'  => $total_kb,
            'recommended_max_kb' => 800,
            'top_heavy_options'  => $top_options,
            'by_component'   => $by_component,
            'recommendations'=> [
                'threshold'  => 'Keep total autoloaded options below 800 KB to avoid slow TTFB on all pages.',
                'transients' => 'Autoloaded transients (_transient_* or _site_transient_*) should be cleaned up or switched to standard transients.',
            ],
        ]);
    }

    /**
     * GET /performance/plugins-summary
     *
     * Consolidated resource footprint per plugin: DB tables, autoloaded options, and background tasks.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_plugins_summary(\WP_REST_Request $request) {
        global $wpdb;

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins     = get_plugins();
        $active_plugins  = (array) get_option('active_plugins', []);
        $filter_status   = $request->get_param('status') ?: 'active';

        // 1. Get all DB tables with byte sizes
        $db_name = DB_NAME;
        $raw_tables = $wpdb->get_results($wpdb->prepare(
            "SELECT table_name, data_length + index_length AS total_bytes, table_rows 
             FROM information_schema.tables 
             WHERE table_schema = %s",
            $db_name
        ));

        $tables_map = [];
        if ($raw_tables) {
            foreach ($raw_tables as $tbl) {
                $tables_map[$tbl->table_name] = [
                    'bytes' => (int) $tbl->total_bytes,
                    'rows'  => (int) $tbl->table_rows,
                ];
            }
        }

        // 2. Scan active plugins
        $summary = [];
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = in_array($plugin_file, $active_plugins, true);
            if ($filter_status === 'active' && !$is_active) {
                continue;
            }

            $slug = dirname($plugin_file);
            if ($slug === '.' || empty($slug)) {
                $slug = basename($plugin_file, '.php');
            }

            // Find associated database tables
            $matched_tables = [];
            $plugin_db_bytes = 0;
            $plugin_db_rows  = 0;

            foreach ($tables_map as $tname => $tinfo) {
                $clean_tname = strtolower($tname);
                $is_match = false;

                // Match by slug or known plugin patterns
                if (strpos($clean_tname, $wpdb->prefix . str_replace('-', '_', $slug)) !== false) {
                    $is_match = true;
                } elseif ($slug === 'woocommerce' && (strpos($clean_tname, $wpdb->prefix . 'wc_') !== false || strpos($clean_tname, $wpdb->prefix . 'woocommerce_') !== false)) {
                    $is_match = true;
                } elseif ($slug === 'action-scheduler' && strpos($clean_tname, $wpdb->prefix . 'actionscheduler_') !== false) {
                    $is_match = true;
                }

                if ($is_match) {
                    $matched_tables[] = $tname;
                    $plugin_db_bytes += $tinfo['bytes'];
                    $plugin_db_rows  += $tinfo['rows'];
                }
            }

            $summary[] = [
                'name'         => $plugin_data['Name'],
                'slug'         => $slug,
                'version'      => $plugin_data['Version'],
                'is_active'    => $is_active,
                'tables_count' => count($matched_tables),
                'db_size_kb'   => round($plugin_db_bytes / 1024, 2),
                'db_rows'      => $plugin_db_rows,
                'matched_tables'=> $matched_tables,
            ];
        }

        // Sort by DB footprint descending
        usort($summary, function ($a, $b) {
            return $b['db_size_kb'] <=> $a['db_size_kb'];
        });

        return $this->response([
            'plugins_count'   => count($summary),
            'filter_status'   => $filter_status,
            'plugins'         => $summary,
        ]);
    }

    /**
     * Infer component from option name.
     *
     * @param string $option_name
     * @return string
     */
    private static function classify_option_name($option_name) {
        if (strpos($option_name, '_transient_') === 0 || strpos($option_name, '_site_transient_') === 0) {
            return 'transient';
        }
        if (strpos($option_name, 'woocommerce_') === 0 || strpos($option_name, 'wc_') === 0) {
            return 'woocommerce';
        }
        if (strpos($option_name, 'elementor_') === 0) {
            return 'elementor';
        }
        if (strpos($option_name, 'rank_math_') === 0) {
            return 'rank-math';
        }
        if (strpos($option_name, 'wpseo_') === 0) {
            return 'yoast-seo';
        }
        if (strpos($option_name, 'action_scheduler_') === 0 || strpos($option_name, 'actionscheduler_') === 0) {
            return 'action-scheduler';
        }
        if (strpos($option_name, 'wp_mail_smtp') === 0) {
            return 'wp-mail-smtp';
        }
        if (strpos($option_name, 'woodmart_') === 0 || strpos($option_name, 'xts_') === 0) {
            return 'theme:woodmart';
        }
        if (strpos($option_name, 'flw_') === 0) {
            return 'flowmattic';
        }

        // Fallback: extract prefix before first underscore
        $parts = explode('_', $option_name);
        if (!empty($parts[0]) && strlen($parts[0]) >= 3) {
            return 'prefix:' . $parts[0];
        }

        return 'core/other';
    }
}
