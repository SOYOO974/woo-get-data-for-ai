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

        // GET /performance/templates-urls (Automatic discovery of key representative template URLs for multi-page audits)
        register_rest_route(self::NAMESPACE, '/performance/templates-urls', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_templates_urls'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'performance');
            },
        ]);

        // GET /performance/pagespeed (Official Google PageSpeed Insights & Core Web Vitals proxy with 1-hour cache)
        register_rest_route(self::NAMESPACE, '/performance/pagespeed', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_pagespeed'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'performance');
            },
            'args'                => [
                'url' => [
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'esc_url_raw',
                    'description'       => 'Target URL to test with Google PageSpeed Insights (default: homepage).',
                ],
                'strategy' => [
                    'type'              => 'string',
                    'default'           => 'mobile',
                    'enum'              => ['mobile', 'desktop'],
                    'description'       => 'Analysis strategy: "mobile" (default) or "desktop".',
                ],
                'key' => [
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Optional Google API key for higher rate limits.',
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
            $html_body = !is_wp_error($response) ? wp_remote_retrieve_body($response) : '';
            $profile_result['pagespeed_audits'] = self::analyze_html_pagespeed_signals($html_body, $response);

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

    /**
     * Analyze HTML response for native Google PageSpeed & Core Web Vitals signals.
     *
     * @param string $html
     * @param array|\WP_Error $response
     * @return array
     */
    private static function analyze_html_pagespeed_signals($html, $response) {
        if (empty($html) || !is_string($html)) {
            return [
                'html_size_kb'             => 0,
                'compression'              => ['is_enabled' => false, 'encoding' => 'none'],
                'dom_health'               => ['total_nodes' => 0, 'status' => 'unknown', 'alert' => null],
                'cls_image_dimensions'     => ['total_images' => 0, 'missing_dimensions_count' => 0, 'status' => 'good', 'sample_missing' => []],
                'render_blocking_in_head'  => ['stylesheets_count' => 0, 'scripts_count' => 0, 'total_blocking' => 0],
                'woocommerce_cart_fragments' => ['is_active' => false],
            ];
        }

        $html_bytes = strlen($html);
        $html_kb    = round($html_bytes / 1024, 2);

        // 1. DOM Size & Depth
        preg_match_all('/<([a-z0-9]+)\b/i', $html, $tag_matches);
        $dom_nodes_count = count($tag_matches[0] ?? []);
        $dom_status = 'good';
        $dom_alert  = null;
        if ($dom_nodes_count > 1400) {
            $dom_status = 'critical';
            $dom_alert  = sprintf('Excessive DOM size (%d nodes > 1400). Severely impacts mobile rendering, layout computation, and RAM.', $dom_nodes_count);
        } elseif ($dom_nodes_count > 800) {
            $dom_status = 'warning';
            $dom_alert  = sprintf('High DOM size (%d nodes > 800). Exceeds Lighthouse recommended threshold.', $dom_nodes_count);
        }

        // 2. Images Missing Explicit Dimensions (CLS Root Cause)
        preg_match_all('/<img\b([^>]*)>/i', $html, $img_matches);
        $missing_dims_count = 0;
        $sample_missing_images = [];

        if (!empty($img_matches[1])) {
            foreach ($img_matches[1] as $attrs) {
                $has_width  = preg_match('/\bwidth\s*=\s*["\']?[0-9]+/i', $attrs);
                $has_height = preg_match('/\bheight\s*=\s*["\']?[0-9]+/i', $attrs);

                if (!$has_width || !$has_height) {
                    $missing_dims_count++;
                    if (count($sample_missing_images) < 5) {
                        if (preg_match('/\bsrc\s*=\s*["\']([^"\']+)["\']/i', $attrs, $src_match)) {
                            $sample_missing_images[] = basename($src_match[1]);
                        }
                    }
                }
            }
        }

        // 3. Render-Blocking Resources in <head>
        $css_blocking_count = 0;
        $js_blocking_count  = 0;
        if (preg_match('/<head\b[^>]*>(.*?)<\/head>/is', $html, $head_match)) {
            $head_content = $head_match[1];

            // Stylesheets
            preg_match_all('/<link\b[^>]*rel=["\']stylesheet["\'][^>]*>/i', $head_content, $css_matches);
            $css_blocking_count = count($css_matches[0] ?? []);

            // Scripts without defer/async
            preg_match_all('/<script\b([^>]*)>/i', $head_content, $script_matches);
            if (!empty($script_matches[1])) {
                foreach ($script_matches[1] as $s_attrs) {
                    if (preg_match('/\bsrc\s*=/i', $s_attrs)) {
                        if (!preg_match('/\b(defer|async)\b/i', $s_attrs)) {
                            $js_blocking_count++;
                        }
                    }
                }
            }
        }

        // 4. WooCommerce Cart Fragments Detection
        $cart_fragments_detected = (stripos($html, 'wc-cart-fragments') !== false || stripos($html, 'cart-fragments') !== false);

        // 5. Compression Check
        $encoding = wp_remote_retrieve_header($response, 'content-encoding');
        $is_compressed = in_array(strtolower((string) $encoding), ['gzip', 'br', 'deflate'], true);

        return [
            'html_size_kb'             => $html_kb,
            'compression'              => [
                'is_enabled' => $is_compressed,
                'encoding'   => $encoding ?: 'none',
            ],
            'dom_health'               => [
                'total_nodes'    => $dom_nodes_count,
                'status'         => $dom_status,
                'alert'          => $dom_alert,
                'recommendation' => $dom_nodes_count > 800 ? 'Excessive DOM size (>800 nodes). Simplify Elementor nested sections or reduce widgets on this template.' : 'DOM node count is optimal.',
            ],
            'cls_image_dimensions'     => [
                'total_images'             => count($img_matches[0] ?? []),
                'missing_dimensions_count' => $missing_dims_count,
                'status'                   => $missing_dims_count === 0 ? 'good' : 'warning',
                'sample_missing'           => $sample_missing_images,
                'recommendation'           => $missing_dims_count > 0 ? sprintf('%d image(s) lack explicit width and height attributes, causing layout shifts (CLS).', $missing_dims_count) : 'All images have explicit dimensions.',
            ],
            'render_blocking_in_head'  => [
                'stylesheets_count' => $css_blocking_count,
                'scripts_count'     => $js_blocking_count,
                'total_blocking'    => $css_blocking_count + $js_blocking_count,
                'recommendation'    => ($js_blocking_count > 0) ? sprintf('%d script(s) in <head> block first contentful paint (FCP). Add defer or async attributes.', $js_blocking_count) : 'No blocking JavaScript found in <head>.',
            ],
            'woocommerce_cart_fragments' => [
                'is_active'      => $cart_fragments_detected,
                'recommendation' => $cart_fragments_detected ? 'Cart fragments script is active. Dequeue it on non-cart/non-checkout pages via WPCode to stop redundant uncached AJAX calls.' : 'Cart fragments script is not loaded on this page.',
            ],
        ];
    }

    /**
     * GET /performance/templates-urls
     *
     * Discovers and resolves representative template URLs for multi-page performance audits.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_templates_urls(\WP_REST_Request $request) {
        $templates = [];

        // 1. Home
        $templates['home'] = [
            'name'        => 'Homepage',
            'type'        => 'front_page',
            'url'         => home_url('/'),
            'path'        => '/',
            'description' => 'Main landing page. Tests slider impact, hero banner assets, and page builder overhead.',
        ];

        // 2. Shop / Catalog
        if (class_exists('WooCommerce')) {
            $shop_page_id = wc_get_page_id('shop');
            if ($shop_page_id > 0) {
                $shop_url = get_permalink($shop_page_id);
                $templates['shop'] = [
                    'name'        => 'Shop / Catalog',
                    'type'        => 'woocommerce_shop',
                    'url'         => $shop_url,
                    'path'        => wp_make_link_relative($shop_url),
                    'description' => 'Product archive loop. Tests product grid SQL queries, pagination, and filter plugins.',
                ];
            }

            // 3. Product Category
            $terms = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => true,
                'number'     => 1,
                'orderby'    => 'count',
                'order'      => 'DESC',
            ]);
            if (!empty($terms) && !is_wp_error($terms) && isset($terms[0])) {
                $cat_term = $terms[0];
                $cat_url  = get_term_link($cat_term);
                if (!is_wp_error($cat_url)) {
                    $templates['category'] = [
                        'name'        => sprintf('Category: %s (%d products)', $cat_term->name, $cat_term->count),
                        'type'        => 'woocommerce_category',
                        'term_id'     => $cat_term->term_id,
                        'url'         => $cat_url,
                        'path'        => wp_make_link_relative($cat_url),
                        'description' => 'Category archive. Tests taxonomy queries, layered navigation filters, and faceted search.',
                    ];
                }
            }

            // 4. Single Product
            $products = wc_get_products([
                'status'  => 'publish',
                'limit'   => 1,
                'orderby' => 'date',
                'order'   => 'DESC',
                'return'  => 'objects',
            ]);
            if (!empty($products) && isset($products[0])) {
                $product  = $products[0];
                $prod_url = $product->get_permalink();
                $templates['product'] = [
                    'name'        => sprintf('Product: %s (%s)', $product->get_name(), $product->get_type()),
                    'type'        => 'woocommerce_product',
                    'product_id'  => $product->get_id(),
                    'product_type'=> $product->get_type(),
                    'url'         => $prod_url,
                    'path'        => wp_make_link_relative($prod_url),
                    'description' => 'Single product page. Tests variation queries, gallery assets, cross-sells, and add-to-cart hooks.',
                ];
            }

            // 5. Cart
            $cart_url = wc_get_cart_url();
            if (!empty($cart_url)) {
                $templates['cart'] = [
                    'name'        => 'Cart',
                    'type'        => 'woocommerce_cart',
                    'url'         => $cart_url,
                    'path'        => wp_make_link_relative($cart_url),
                    'description' => 'Shopping cart. Non-cacheable dynamic template testing session management and shipping calculators.',
                ];
            }

            // 6. Checkout
            $checkout_url = wc_get_checkout_url();
            if (!empty($checkout_url)) {
                $templates['checkout'] = [
                    'name'        => 'Checkout',
                    'type'        => 'woocommerce_checkout',
                    'url'         => $checkout_url,
                    'path'        => wp_make_link_relative($checkout_url),
                    'description' => 'Checkout page. Conversion-critical tunnel testing payment gateways and order review AJAX.',
                ];
            }
        } else {
            // Fallback for non-WooCommerce sites
            $posts_page_id = get_option('page_for_posts');
            if ($posts_page_id > 0) {
                $blog_url = get_permalink($posts_page_id);
                $templates['blog'] = [
                    'name' => 'Blog Archive',
                    'type' => 'blog_archive',
                    'url'  => $blog_url,
                    'path' => wp_make_link_relative($blog_url),
                ];
            }

            $latest_posts = get_posts(['numberposts' => 1, 'post_status' => 'publish']);
            if (!empty($latest_posts) && isset($latest_posts[0])) {
                $post = $latest_posts[0];
                $post_url = get_permalink($post->ID);
                $templates['post'] = [
                    'name' => sprintf('Post: %s', $post->post_title),
                    'type' => 'single_post',
                    'url'  => $post_url,
                    'path' => wp_make_link_relative($post_url),
                ];
            }
        }

        return $this->response([
            'woocommerce_active' => class_exists('WooCommerce'),
            'templates_count'    => count($templates),
            'templates'          => $templates,
        ]);
    }

    /**
     * GET /performance/pagespeed
     *
     * Official Google PageSpeed Insights & Core Web Vitals proxy with 1-hour cache.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_pagespeed(\WP_REST_Request $request) {
        $url = trim((string) $request->get_param('url'));
        $strategy = strtolower(trim((string) $request->get_param('strategy')));

        if (empty($url)) {
            $url = home_url('/');
        }

        if (!in_array($strategy, ['mobile', 'desktop'], true)) {
            $strategy = 'mobile';
        }

        // Check transient cache (1 hour)
        $cache_key = 'wpab_psi_' . md5($url . '_' . $strategy);
        $cached = get_transient($cache_key);
        if ($cached && is_array($cached)) {
            $cached['cached'] = true;
            return $this->response($cached);
        }

        // Build Google API request
        $api_endpoint = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
        $params = [
            'url'      => $url,
            'strategy' => $strategy,
            'category' => 'performance',
        ];

        // Check for custom API key if defined
        if (defined('GOOGLE_PAGESPEED_API_KEY') && GOOGLE_PAGESPEED_API_KEY) {
            $params['key'] = GOOGLE_PAGESPEED_API_KEY;
        } elseif ($request->get_param('key')) {
            $params['key'] = sanitize_text_field(wp_unslash($request->get_param('key')));
        }

        $request_url = add_query_arg($params, $api_endpoint);

        $response = wp_remote_get($request_url, [
            'timeout'    => 45,
            'user-agent' => 'WP-Agent-Bridge/' . WOO_GET_DATA_AI_VERSION,
        ]);

        if (is_wp_error($response)) {
            return $this->response([
                'status'  => 'error',
                'message' => 'Failed to reach Google PageSpeed Insights API: ' . $response->get_error_message(),
                'notice'  => 'Google PageSpeed Insights requires the site URL to be publicly accessible from Google servers (cannot test local/intranet domains). Use native audits from /performance/profile for local testing.',
                'url'     => $url,
            ], 200);
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body_raw    = wp_remote_retrieve_body($response);
        $json        = json_decode($body_raw, true);

        if ($status_code !== 200 || empty($json['lighthouseResult'])) {
            $error_detail = $json['error']['message'] ?? 'Unknown Google API error (HTTP ' . $status_code . ')';
            return $this->response([
                'status'      => 'error',
                'http_status' => $status_code,
                'message'     => $error_detail,
                'notice'      => 'Google PageSpeed Insights requires the target URL to be publicly accessible without authentication. For local or staging environments, use /performance/profile.',
                'url'         => $url,
            ], 200);
        }

        $lh = $json['lighthouseResult'];
        $audits = $lh['audits'] ?? [];

        // Extract Core Web Vitals & Key Lighthouse Metrics
        $performance_score = isset($lh['categories']['performance']['score'])
            ? round($lh['categories']['performance']['score'] * 100)
            : null;

        $metrics = [
            'fcp' => [
                'title'    => 'First Contentful Paint (FCP)',
                'value_ms' => isset($audits['first-contentful-paint']['numericValue']) ? round($audits['first-contentful-paint']['numericValue']) : null,
                'display'  => $audits['first-contentful-paint']['displayValue'] ?? null,
                'score'    => $audits['first-contentful-paint']['score'] ?? null,
            ],
            'lcp' => [
                'title'    => 'Largest Contentful Paint (LCP)',
                'value_ms' => isset($audits['largest-contentful-paint']['numericValue']) ? round($audits['largest-contentful-paint']['numericValue']) : null,
                'display'  => $audits['largest-contentful-paint']['displayValue'] ?? null,
                'score'    => $audits['largest-contentful-paint']['score'] ?? null,
            ],
            'cls' => [
                'title'    => 'Cumulative Layout Shift (CLS)',
                'value'    => isset($audits['cumulative-layout-shift']['numericValue']) ? round($audits['cumulative-layout-shift']['numericValue'], 3) : null,
                'display'  => $audits['cumulative-layout-shift']['displayValue'] ?? null,
                'score'    => $audits['cumulative-layout-shift']['score'] ?? null,
            ],
            'tbt' => [
                'title'    => 'Total Blocking Time (TBT)',
                'value_ms' => isset($audits['total-blocking-time']['numericValue']) ? round($audits['total-blocking-time']['numericValue']) : null,
                'display'  => $audits['total-blocking-time']['displayValue'] ?? null,
                'score'    => $audits['total-blocking-time']['score'] ?? null,
            ],
            'speed_index' => [
                'title'    => 'Speed Index (SI)',
                'value_ms' => isset($audits['speed-index']['numericValue']) ? round($audits['speed-index']['numericValue']) : null,
                'display'  => $audits['speed-index']['displayValue'] ?? null,
                'score'    => $audits['speed-index']['score'] ?? null,
            ],
        ];

        // Extract Top Optimization Opportunities
        $opportunities = [];
        $opp_keys = [
            'render-blocking-resources',
            'unused-javascript',
            'unused-css-rules',
            'modern-image-formats',
            'uses-optimized-images',
            'unminified-javascript',
            'unminified-css',
            'efficient-animated-content',
            'duplicated-javascript',
            'server-response-time',
        ];

        foreach ($opp_keys as $key) {
            if (!empty($audits[$key]) && isset($audits[$key]['score']) && $audits[$key]['score'] < 0.9) {
                $opportunities[] = [
                    'id'           => $key,
                    'title'        => $audits[$key]['title'] ?? $key,
                    'display'      => $audits[$key]['displayValue'] ?? null,
                    'score'        => $audits[$key]['score'],
                    'wasted_ms'    => isset($audits[$key]['details']['overallSavingsMs']) ? round($audits[$key]['details']['overallSavingsMs']) : null,
                    'wasted_bytes' => isset($audits[$key]['details']['overallSavingsBytes']) ? round($audits[$key]['details']['overallSavingsBytes']) : null,
                    'description'  => wp_strip_all_tags($audits[$key]['description'] ?? ''),
                ];
            }
        }

        // Sort opportunities by highest wasted time or bytes
        usort($opportunities, function ($a, $b) {
            return ($b['wasted_ms'] ?? 0) <=> ($a['wasted_ms'] ?? 0);
        });

        $result = [
            'status'            => 'success',
            'source'            => 'google_pagespeed_insights_api',
            'tested_url'        => $url,
            'strategy'          => $strategy,
            'performance_score' => $performance_score,
            'lighthouse_version'=> $lh['lighthouseVersion'] ?? null,
            'fetched_at'        => current_time('c'),
            'core_web_vitals'   => $metrics,
            'opportunities'     => $opportunities,
            'cached'            => false,
        ];

        // Cache for 1 hour
        set_transient($cache_key, $result, HOUR_IN_SECONDS);

        return $this->response($result);
    }
}
