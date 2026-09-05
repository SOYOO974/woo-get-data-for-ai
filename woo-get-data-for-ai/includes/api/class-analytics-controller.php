<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Analytics_Controller
 *
 * REST API controller exposing audience, acquisition, and conversion intelligence
 * from the Independent Analytics plugin (Free and Pro).
 *
 * All endpoints are 100% Read-Only (GET requests).
 */
class Analytics_Controller extends Rest_Controller {

    /**
     * Register all Analytics routes under agent-bridge/v1/analytics/
     */
    public function register_routes() {
        // GET /analytics/overview (Consolidated 360° audit in 1 request)
        register_rest_route(self::NAMESPACE, '/analytics/overview', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_overview'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'analytics');
            },
        ]);

        // GET /analytics/summary (High-level KPIs, conversion rate, growth comparison)
        register_rest_route(self::NAMESPACE, '/analytics/summary', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_summary'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'analytics');
            },
        ]);

        // GET /analytics/pages (Page & Product performance, bounce rates, conversion rates)
        register_rest_route(self::NAMESPACE, '/analytics/pages', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_pages'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'analytics');
            },
        ]);

        // GET /analytics/referrers (Traffic sources, domains, and conversion rates)
        register_rest_route(self::NAMESPACE, '/analytics/referrers', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_referrers'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'analytics');
            },
        ]);

        // GET /analytics/campaigns (UTM campaigns ROI & conversion)
        register_rest_route(self::NAMESPACE, '/analytics/campaigns', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_campaigns'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'analytics');
            },
        ]);

        // GET /analytics/devices (Device types, browsers, OS & conversion comparison)
        register_rest_route(self::NAMESPACE, '/analytics/devices', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_devices'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'analytics');
            },
        ]);

        // GET /analytics/geo (Countries & Cities breakdown)
        register_rest_route(self::NAMESPACE, '/analytics/geo', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_geo'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'analytics');
            },
        ]);

        // GET /analytics/conversions (Recent conversions log: orders & form submissions)
        register_rest_route(self::NAMESPACE, '/analytics/conversions', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_conversions'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'analytics');
            },
        ]);
    }

    /* =========================================================================
     * INSTALLATION & ENVIRONMENT CHECKS
     * ========================================================================= */

    /**
     * Check if Independent Analytics is installed and database tables exist.
     *
     * @return bool
     */
    protected function is_ia_installed() {
        global $wpdb;
        $views_table = $wpdb->prefix . 'independent_analytics_views';
        return ($wpdb->get_var("SHOW TABLES LIKE '$views_table'") === $views_table);
    }

    /**
     * Check if the orders table exists in Independent Analytics.
     *
     * @return bool
     */
    protected function has_ia_orders_table() {
        global $wpdb;
        $orders_table = $wpdb->prefix . 'independent_analytics_orders';
        return ($wpdb->get_var("SHOW TABLES LIKE '$orders_table'") === $orders_table);
    }

    /**
     * Check if the form submissions table exists.
     *
     * @return bool
     */
    protected function has_ia_forms_table() {
        global $wpdb;
        $forms_table = $wpdb->prefix . 'independent_analytics_form_submissions';
        return ($wpdb->get_var("SHOW TABLES LIKE '$forms_table'") === $forms_table);
    }

    /**
     * Get Independent Analytics version and edition info.
     *
     * @return array
     */
    protected function get_ia_meta() {
        $version = defined('IAWP_VERSION') ? IAWP_VERSION : (get_option('iawp_db_version') ? 'v' . get_option('iawp_db_version') : 'unknown');
        $is_pro = function_exists('\IAWPSCOPED\iawp_is_pro') ? \IAWPSCOPED\iawp_is_pro() : (defined('IAWP_PRO') || is_dir(WP_PLUGIN_DIR . '/independent-analytics-pro'));
        $db_version = get_option('iawp_db_version', '0');

        return [
            'installed'         => $this->is_ia_installed(),
            'version'           => $version,
            'is_pro'            => (bool) $is_pro,
            'db_version'        => (int) $db_version,
            'has_orders_table'  => $this->has_ia_orders_table(),
            'currency'          => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
            'currency_symbol'   => function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol()) : '$',
        ];
    }

    /**
     * Return a standardized response when Independent Analytics is not installed.
     *
     * @return \WP_REST_Response
     */
    protected function not_installed_response() {
        return $this->response([
            'success'   => false,
            'installed' => false,
            'message'   => esc_html__('Independent Analytics is not installed or its database tables were not found on this site.', 'woo-get-data-for-ai'),
            'tip'       => esc_html__('Install and activate Independent Analytics or Independent Analytics Pro to enable audience and conversion tracking.', 'woo-get-data-for-ai'),
        ], 200);
    }

    /* =========================================================================
     * DATE RANGE PARSER & TIMEZONE CALCULATOR
     * ========================================================================= */

    /**
     * Parse date range from request parameters and calculate UTC query boundaries.
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    protected function parse_date_range(\WP_REST_Request $request) {
        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $now = new \DateTime('now', $tz);

        $range = sanitize_text_field($request->get_param('range') ?: 'last_30_days');
        $start_param = sanitize_text_field($request->get_param('start') ?: '');
        $end_param = sanitize_text_field($request->get_param('end') ?: '');

        $label = 'Last 30 Days';

        if (!empty($start_param) && !empty($end_param)) {
            try {
                $start_local = new \DateTime($start_param . ' 00:00:00', $tz);
                $end_local = new \DateTime($end_param . ' 23:59:59', $tz);
                $range = 'custom';
                $label = $start_local->format('Y-m-d') . ' - ' . $end_local->format('Y-m-d');
            } catch (\Throwable $e) {
                // Fallback to last 30 days
                $start_local = (clone $now)->modify('-29 days')->setTime(0, 0, 0);
                $end_local = (clone $now)->setTime(23, 59, 59);
            }
        } else {
            switch ($range) {
                case 'today':
                    $start_local = (clone $now)->setTime(0, 0, 0);
                    $end_local = (clone $now)->setTime(23, 59, 59);
                    $label = 'Today';
                    break;

                case 'yesterday':
                    $start_local = (clone $now)->modify('-1 day')->setTime(0, 0, 0);
                    $end_local = (clone $now)->modify('-1 day')->setTime(23, 59, 59);
                    $label = 'Yesterday';
                    break;

                case 'last_7_days':
                    $start_local = (clone $now)->modify('-6 days')->setTime(0, 0, 0);
                    $end_local = (clone $now)->setTime(23, 59, 59);
                    $label = 'Last 7 Days';
                    break;

                case 'this_week':
                    $start_local = (clone $now)->modify('this week')->setTime(0, 0, 0);
                    $end_local = (clone $now)->setTime(23, 59, 59);
                    $label = 'This Week';
                    break;

                case 'this_month':
                    $start_local = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
                    $end_local = (clone $now)->setTime(23, 59, 59);
                    $label = 'This Month';
                    break;

                case 'last_month':
                    $start_local = (clone $now)->modify('first day of last month')->setTime(0, 0, 0);
                    $end_local = (clone $now)->modify('last day of last month')->setTime(23, 59, 59);
                    $label = 'Last Month';
                    break;

                case 'last_90_days':
                    $start_local = (clone $now)->modify('-89 days')->setTime(0, 0, 0);
                    $end_local = (clone $now)->setTime(23, 59, 59);
                    $label = 'Last 90 Days';
                    break;

                case 'this_year':
                    $start_local = (clone $now)->modify('first day of january this year')->setTime(0, 0, 0);
                    $end_local = (clone $now)->setTime(23, 59, 59);
                    $label = 'This Year';
                    break;

                case 'last_year':
                    $start_local = (clone $now)->modify('first day of january last year')->setTime(0, 0, 0);
                    $end_local = (clone $now)->modify('last day of december last year')->setTime(23, 59, 59);
                    $label = 'Last Year';
                    break;

                case 'all_time':
                    $start_local = new \DateTime('2000-01-01 00:00:00', $tz);
                    $end_local = (clone $now)->setTime(23, 59, 59);
                    $label = 'All Time';
                    break;

                case 'last_30_days':
                default:
                    $range = 'last_30_days';
                    $start_local = (clone $now)->modify('-29 days')->setTime(0, 0, 0);
                    $end_local = (clone $now)->setTime(23, 59, 59);
                    $label = 'Last 30 Days';
                    break;
            }
        }

        // Convert site local time to UTC for SQL comparison
        $utc_tz = new \DateTimeZone('UTC');
        $start_utc = (clone $start_local)->setTimezone($utc_tz)->format('Y-m-d H:i:s');
        $end_utc = (clone $end_local)->setTimezone($utc_tz)->format('Y-m-d H:i:s');

        // Calculate previous period of equal duration
        $duration_seconds = max(1, $end_local->getTimestamp() - $start_local->getTimestamp() + 1);
        $prev_end_local = (clone $start_local)->modify('-1 second');
        $prev_start_local = (clone $prev_end_local)->modify('-' . ($duration_seconds - 1) . ' seconds');

        $prev_start_utc = (clone $prev_start_local)->setTimezone($utc_tz)->format('Y-m-d H:i:s');
        $prev_end_utc = (clone $prev_end_local)->setTimezone($utc_tz)->format('Y-m-d H:i:s');

        return [
            'range'            => $range,
            'label'            => $label,
            'start_local'      => $start_local->format('Y-m-d H:i:s'),
            'end_local'        => $end_local->format('Y-m-d H:i:s'),
            'start_utc'        => $start_utc,
            'end_utc'          => $end_utc,
            'duration_days'    => round($duration_seconds / 86400, 1),
            'previous_period'  => [
                'start_local'  => $prev_start_local->format('Y-m-d H:i:s'),
                'end_local'    => $prev_end_local->format('Y-m-d H:i:s'),
                'start_utc'    => $prev_start_utc,
                'end_utc'      => $prev_end_utc,
            ],
        ];
    }

    /**
     * Compute safe percentage growth.
     *
     * @param float|int $current
     * @param float|int $previous
     * @return float
     */
    protected function calculate_growth($current, $previous) {
        if ($previous <= 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }

    /* =========================================================================
     * CORE SUMMARY QUERY BUILDER
     * ========================================================================= */

    /**
     * Query core traffic and ecommerce statistics for a given UTC date range.
     *
     * @param string $start_utc
     * @param string $end_utc
     * @return array
     */
    protected function query_summary_stats($start_utc, $end_utc) {
        global $wpdb;

        $sessions_table = $wpdb->prefix . 'independent_analytics_sessions';
        $views_table    = $wpdb->prefix . 'independent_analytics_views';
        $orders_table   = $wpdb->prefix . 'independent_analytics_orders';
        $has_orders     = $this->has_ia_orders_table();

        // 1. Session and Traffic aggregation
        // Sessions created within the range
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql_traffic = $wpdb->prepare(
            "SELECT 
                COUNT(DISTINCT s.session_id) AS total_sessions,
                COUNT(DISTINCT s.visitor_id) AS total_visitors,
                IFNULL(SUM(s.total_views), 0) AS total_views,
                COUNT(DISTINCT IF(s.final_view_id IS NULL, s.session_id, NULL)) AS bounces,
                IFNULL(AVG(TIMESTAMPDIFF(SECOND, s.created_at, s.ended_at)), 0) AS avg_duration_seconds
             FROM `$sessions_table` s
             WHERE s.created_at BETWEEN %s AND %s",
            $start_utc,
            $end_utc
        );

        $traffic = $wpdb->get_row($sql_traffic, ARRAY_A) ?: [];

        $sessions = (int) ($traffic['total_sessions'] ?? 0);
        $visitors = (int) ($traffic['total_visitors'] ?? 0);
        $views    = (int) ($traffic['total_views'] ?? 0);
        $bounces  = (int) ($traffic['bounces'] ?? 0);
        $duration = (float) ($traffic['avg_duration_seconds'] ?? 0);

        $bounce_rate = $sessions > 0 ? round(($bounces / $sessions) * 100, 2) : 0.0;
        $views_per_session = $sessions > 0 ? round($views / $sessions, 2) : 0.0;

        // 2. Orders & E-commerce metrics
        $orders_count     = 0;
        $gross_sales      = 0.0;
        $refunds_count    = 0;
        $refunded_amount  = 0.0;
        $net_sales        = 0.0;
        $conversion_rate  = 0.0;
        $earnings_per_vis = 0.0;
        $aov              = 0.0;

        if ($has_orders) {
            // Attribute orders created within the date range and marked as included in analytics
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $sql_orders = $wpdb->prepare(
                "SELECT 
                    COUNT(o.order_id) AS total_orders,
                    IFNULL(SUM(o.total), 0) AS gross_sales_cents,
                    IFNULL(SUM(o.total_refunds), 0) AS total_refunds,
                    IFNULL(SUM(o.total_refunded), 0) AS refunded_amount_cents
                 FROM `$orders_table` o
                 WHERE o.is_included_in_analytics = 1
                   AND o.created_at BETWEEN %s AND %s",
                $start_utc,
                $end_utc
            );

            $order_stats = $wpdb->get_row($sql_orders, ARRAY_A) ?: [];

            $orders_count    = (int) ($order_stats['total_orders'] ?? 0);
            $gross_sales     = round(((int) ($order_stats['gross_sales_cents'] ?? 0)) / 100, 2);
            $refunds_count   = (int) ($order_stats['total_refunds'] ?? 0);
            $refunded_amount = round(((int) ($order_stats['refunded_amount_cents'] ?? 0)) / 100, 2);
            $net_sales       = round($gross_sales - $refunded_amount, 2);

            if ($visitors > 0) {
                $conversion_rate  = round(($orders_count / $visitors) * 100, 2);
                $earnings_per_vis = round($net_sales / $visitors, 2);
            }
            if ($orders_count > 0) {
                $aov = round($gross_sales / $orders_count, 2);
            }
        }

        // 3. Form submissions (if Pro forms table exists)
        $form_submissions = 0;
        $form_conv_rate   = 0.0;
        if ($this->has_ia_forms_table()) {
            $forms_table = $wpdb->prefix . 'independent_analytics_form_submissions';
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $form_submissions = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM `$forms_table` WHERE created_at BETWEEN %s AND %s", $start_utc, $end_utc)
            );
            if ($visitors > 0) {
                $form_conv_rate = round(($form_submissions / $visitors) * 100, 2);
            }
        }

        // Format duration into readable MM:SS
        $duration_m = floor($duration / 60);
        $duration_s = round($duration % 60);
        $duration_formatted = sprintf('%dm %02ds', $duration_m, $duration_s);

        return [
            'traffic' => [
                'visitors'                 => $visitors,
                'views'                    => $views,
                'sessions'                 => $sessions,
                'views_per_session'        => $views_per_session,
                'bounce_rate_percent'      => $bounce_rate,
                'bounces'                  => $bounces,
                'avg_session_duration_sec' => round($duration, 1),
                'avg_session_duration_fmt' => $duration_formatted,
            ],
            'ecommerce' => [
                'orders'                   => $orders_count,
                'gross_sales'              => $gross_sales,
                'refunds'                  => $refunds_count,
                'refunded_amount'          => $refunded_amount,
                'net_sales'                => $net_sales,
                'conversion_rate_percent'  => $conversion_rate,
                'earnings_per_visitor'     => $earnings_per_vis,
                'average_order_value'      => $aov,
            ],
            'forms' => [
                'submissions'              => $form_submissions,
                'conversion_rate_percent'  => $form_conv_rate,
            ],
        ];
    }

    /* =========================================================================
     * ENDPOINT HANDLERS
     * ========================================================================= */

    /**
     * GET /analytics/summary
     * Returns high-level KPIs, conversion rates, and previous period growth comparisons.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_summary(\WP_REST_Request $request) {
        if (!$this->is_ia_installed()) {
            return $this->not_installed_response();
        }

        $date_range = $this->parse_date_range($request);
        $current    = $this->query_summary_stats($date_range['start_utc'], $date_range['end_utc']);
        $previous   = $this->query_summary_stats($date_range['previous_period']['start_utc'], $date_range['previous_period']['end_utc']);

        // Calculate period-over-period growth rates
        $growth = [
            'visitors_growth_percent'        => $this->calculate_growth($current['traffic']['visitors'], $previous['traffic']['visitors']),
            'views_growth_percent'           => $this->calculate_growth($current['traffic']['views'], $previous['traffic']['views']),
            'sessions_growth_percent'        => $this->calculate_growth($current['traffic']['sessions'], $previous['traffic']['sessions']),
            'orders_growth_percent'          => $this->calculate_growth($current['ecommerce']['orders'], $previous['ecommerce']['orders']),
            'net_sales_growth_percent'       => $this->calculate_growth($current['ecommerce']['net_sales'], $previous['ecommerce']['net_sales']),
            'conversion_rate_growth_percent' => $this->calculate_growth($current['ecommerce']['conversion_rate_percent'], $previous['ecommerce']['conversion_rate_percent']),
        ];

        return $this->response([
            'success'     => true,
            'meta'        => $this->get_ia_meta(),
            'period'      => $date_range,
            'summary'     => $current,
            'growth'      => $growth,
            'previous'    => $previous,
        ]);
    }

    /**
     * GET /analytics/pages
     * Returns page and product performance with conversion rate per resource.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_pages(\WP_REST_Request $request) {
        if (!$this->is_ia_installed()) {
            return $this->not_installed_response();
        }

        global $wpdb;
        $date_range = $this->parse_date_range($request);

        $limit  = min(100, max(1, (int) ($request->get_param('limit') ?: 25)));
        $page   = max(1, (int) ($request->get_param('page') ?: 1));
        $offset = ($page - 1) * $limit;

        $type_filter = sanitize_text_field($request->get_param('type') ?: '');
        $search      = sanitize_text_field($request->get_param('search') ?: '');
        $sort_param  = sanitize_text_field($request->get_param('sort') ?: 'views');
        $order_param = strtoupper(sanitize_text_field($request->get_param('order') ?: 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $views_table     = $wpdb->prefix . 'independent_analytics_views';
        $sessions_table  = $wpdb->prefix . 'independent_analytics_sessions';
        $resources_table = $wpdb->prefix . 'independent_analytics_resources';
        $orders_table    = $wpdb->prefix . 'independent_analytics_orders';
        $has_orders      = $this->has_ia_orders_table();

        $where_clauses = ["v.viewed_at BETWEEN %s AND %s"];
        $params        = [$date_range['start_utc'], $date_range['end_utc']];

        if (!empty($type_filter)) {
            $where_clauses[] = "r.cached_type = %s";
            $params[]        = $type_filter;
        }

        if (!empty($search)) {
            $where_clauses[] = "(r.cached_title LIKE %s OR r.cached_url LIKE %s)";
            $params[]        = '%' . $wpdb->esc_like($search) . '%';
            $params[]        = '%' . $wpdb->esc_like($search) . '%';
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Map safe sort columns
        $valid_sorts = [
            'views'           => 'views',
            'visitors'        => 'visitors',
            'orders'          => 'wc_orders',
            'net_sales'       => 'wc_net_sales',
            'conversion_rate' => 'wc_conversion_rate',
            'title'           => 'r.cached_title',
        ];
        $sort_column = $valid_sorts[$sort_param] ?? 'views';

        // Orders subquery join (attributed to initial view or view)
        $orders_join = "";
        $orders_select = ", 0 AS wc_orders, 0 AS wc_gross_sales, 0 AS wc_net_sales, 0.0 AS wc_conversion_rate";

        if ($has_orders) {
            $orders_join = "
                LEFT JOIN (
                    SELECT 
                        o.view_id,
                        COUNT(o.order_id) AS orders_count,
                        SUM(o.total) AS gross_cents,
                        SUM(o.total - o.total_refunded) AS net_cents
                    FROM `$orders_table` o
                    WHERE o.is_included_in_analytics = 1
                      AND o.created_at BETWEEN '{$date_range['start_utc']}' AND '{$date_range['end_utc']}'
                    GROUP BY o.view_id
                ) ord ON v.id = ord.view_id
            ";
            $orders_select = "
                , IFNULL(SUM(ord.orders_count), 0) AS wc_orders,
                ROUND(IFNULL(SUM(ord.gross_cents), 0) / 100, 2) AS wc_gross_sales,
                ROUND(IFNULL(SUM(ord.net_cents), 0) / 100, 2) AS wc_net_sales,
                IF(COUNT(DISTINCT s.visitor_id) > 0, ROUND((IFNULL(SUM(ord.orders_count), 0) / COUNT(DISTINCT s.visitor_id)) * 100, 2), 0.0) AS wc_conversion_rate
            ";
        }

        // Main SQL Query
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT 
                r.id AS resource_id,
                r.singular_id,
                r.cached_title AS title,
                r.cached_url AS url,
                r.cached_type AS page_type,
                r.cached_category AS category,
                r.cached_author AS author,
                COUNT(DISTINCT v.id) AS views,
                COUNT(DISTINCT s.visitor_id) AS visitors,
                COUNT(DISTINCT s.session_id) AS sessions,
                IFNULL(AVG(TIMESTAMPDIFF(SECOND, v.viewed_at, v.next_viewed_at)), 0) AS avg_view_duration_seconds
                $orders_select
             FROM `$views_table` v
             JOIN `$resources_table` r ON v.resource_id = r.id
             JOIN `$sessions_table` s ON v.session_id = s.session_id
             $orders_join
             WHERE $where_sql
             GROUP BY r.id
             ORDER BY $sort_column $order_param
             LIMIT %d OFFSET %d",
            array_merge($params, [$limit, $offset])
        );

        $results = $wpdb->get_results($sql, ARRAY_A) ?: [];

        // Count total pages matching
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $count_sql = $wpdb->prepare(
            "SELECT COUNT(DISTINCT r.id)
             FROM `$views_table` v
             JOIN `$resources_table` r ON v.resource_id = r.id
             JOIN `$sessions_table` s ON v.session_id = s.session_id
             WHERE $where_sql",
            $params
        );
        $total_items = (int) $wpdb->get_var($count_sql);

        // Format items
        $items = array_map(function ($row) {
            $views    = (int) $row['views'];
            $visitors = (int) $row['visitors'];
            $sessions = (int) $row['sessions'];
            $orders   = (int) $row['wc_orders'];
            $sales    = (float) $row['wc_net_sales'];
            $duration = (float) $row['avg_view_duration_seconds'];

            return [
                'resource_id'              => (int) $row['resource_id'],
                'singular_id'              => (int) $row['singular_id'],
                'title'                    => $row['title'],
                'url'                      => $row['url'],
                'page_type'                => $row['page_type'],
                'category'                 => $row['category'],
                'author'                   => $row['author'],
                'views'                    => $views,
                'visitors'                 => $visitors,
                'sessions'                 => $sessions,
                'avg_view_duration_sec'    => round($duration, 1),
                'orders'                   => $orders,
                'net_sales'                => $sales,
                'conversion_rate_percent'  => $visitors > 0 ? round(($orders / $visitors) * 100, 2) : 0.0,
            ];
        }, $results);

        return $this->response([
            'success'     => true,
            'meta'        => $this->get_ia_meta(),
            'period'      => $date_range,
            'pagination'  => [
                'page'        => $page,
                'limit'       => $limit,
                'total_items' => $total_items,
                'total_pages' => ceil($total_items / $limit),
            ],
            'pages'       => $items,
        ]);
    }

    /**
     * GET /analytics/referrers
     * Returns traffic acquisition channels and referring domains with conversion rates.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_referrers(\WP_REST_Request $request) {
        if (!$this->is_ia_installed()) {
            return $this->not_installed_response();
        }

        global $wpdb;
        $date_range = $this->parse_date_range($request);
        $limit      = min(100, max(1, (int) ($request->get_param('limit') ?: 25)));

        $sessions_table   = $wpdb->prefix . 'independent_analytics_sessions';
        $referrers_table  = $wpdb->prefix . 'independent_analytics_referrers';
        $orders_table     = $wpdb->prefix . 'independent_analytics_orders';
        $has_orders       = $this->has_ia_orders_table();

        $orders_select = ", 0 AS orders_count, 0 AS net_sales_cents";
        $orders_join   = "";

        if ($has_orders) {
            $orders_join = "
                LEFT JOIN (
                    SELECT 
                        s_inner.referrer_id,
                        COUNT(o.order_id) AS orders_count,
                        SUM(o.total - o.total_refunded) AS net_cents
                    FROM `$orders_table` o
                    JOIN `$sessions_table` s_inner ON o.initial_view_id = s_inner.initial_view_id
                    WHERE o.is_included_in_analytics = 1
                      AND o.created_at BETWEEN '{$date_range['start_utc']}' AND '{$date_range['end_utc']}'
                    GROUP BY s_inner.referrer_id
                ) ord ON ref.id = ord.referrer_id
            ";
            $orders_select = ", IFNULL(ord.orders_count, 0) AS orders_count, IFNULL(ord.net_cents, 0) AS net_sales_cents";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT 
                IFNULL(ref.domain, 'Direct / None') AS domain,
                ref.id AS referrer_id,
                COUNT(DISTINCT s.visitor_id) AS visitors,
                COUNT(DISTINCT s.session_id) AS sessions,
                IFNULL(SUM(s.total_views), 0) AS views,
                COUNT(DISTINCT IF(s.final_view_id IS NULL, s.session_id, NULL)) AS bounces
                $orders_select
             FROM `$sessions_table` s
             LEFT JOIN `$referrers_table` ref ON s.referrer_id = ref.id
             $orders_join
             WHERE s.created_at BETWEEN %s AND %s
             GROUP BY domain
             ORDER BY visitors DESC
             LIMIT %d",
            $date_range['start_utc'],
            $date_range['end_utc'],
            $limit
        );

        $results = $wpdb->get_results($sql, ARRAY_A) ?: [];

        $items = array_map(function ($row) {
            $visitors = (int) $row['visitors'];
            $sessions = (int) $row['sessions'];
            $views    = (int) $row['views'];
            $bounces  = (int) $row['bounces'];
            $orders   = (int) $row['orders_count'];
            $net_sales = round(((int) $row['net_sales_cents']) / 100, 2);

            return [
                'domain'                  => $row['domain'],
                'visitors'                => $visitors,
                'sessions'                => $sessions,
                'views'                   => $views,
                'bounce_rate_percent'     => $sessions > 0 ? round(($bounces / $sessions) * 100, 2) : 0.0,
                'orders'                  => $orders,
                'net_sales'               => $net_sales,
                'conversion_rate_percent' => $visitors > 0 ? round(($orders / $visitors) * 100, 2) : 0.0,
            ];
        }, $results);

        return $this->response([
            'success'   => true,
            'meta'      => $this->get_ia_meta(),
            'period'    => $date_range,
            'referrers' => $items,
        ]);
    }

    /**
     * GET /analytics/campaigns
     * Returns UTM marketing campaigns tracking (source, medium, campaign) and ROI.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_campaigns(\WP_REST_Request $request) {
        if (!$this->is_ia_installed()) {
            return $this->not_installed_response();
        }

        global $wpdb;
        $date_range = $this->parse_date_range($request);
        $limit      = min(100, max(1, (int) ($request->get_param('limit') ?: 25)));

        $sessions_table  = $wpdb->prefix . 'independent_analytics_sessions';
        $campaigns_table = $wpdb->prefix . 'independent_analytics_campaigns';
        $orders_table    = $wpdb->prefix . 'independent_analytics_orders';
        $has_orders      = $this->has_ia_orders_table();

        $orders_select = ", 0 AS orders_count, 0 AS net_sales_cents";
        $orders_join   = "";

        if ($has_orders) {
            $orders_join = "
                LEFT JOIN (
                    SELECT 
                        s_inner.campaign_id,
                        COUNT(o.order_id) AS orders_count,
                        SUM(o.total - o.total_refunded) AS net_cents
                    FROM `$orders_table` o
                    JOIN `$sessions_table` s_inner ON o.initial_view_id = s_inner.initial_view_id
                    WHERE o.is_included_in_analytics = 1
                      AND o.created_at BETWEEN '{$date_range['start_utc']}' AND '{$date_range['end_utc']}'
                    GROUP BY s_inner.campaign_id
                ) ord ON c.campaign_id = ord.campaign_id
            ";
            $orders_select = ", IFNULL(ord.orders_count, 0) AS orders_count, IFNULL(ord.net_cents, 0) AS net_sales_cents";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT 
                c.utm_campaign,
                c.utm_source,
                c.utm_medium,
                c.utm_term,
                c.utm_content,
                COUNT(DISTINCT s.visitor_id) AS visitors,
                COUNT(DISTINCT s.session_id) AS sessions,
                IFNULL(SUM(s.total_views), 0) AS views,
                COUNT(DISTINCT IF(s.final_view_id IS NULL, s.session_id, NULL)) AS bounces
                $orders_select
             FROM `$sessions_table` s
             JOIN `$campaigns_table` c ON s.campaign_id = c.campaign_id
             $orders_join
             WHERE s.created_at BETWEEN %s AND %s
             GROUP BY c.campaign_id
             ORDER BY visitors DESC
             LIMIT %d",
            $date_range['start_utc'],
            $date_range['end_utc'],
            $limit
        );

        $results = $wpdb->get_results($sql, ARRAY_A) ?: [];

        $items = array_map(function ($row) {
            $visitors  = (int) $row['visitors'];
            $sessions  = (int) $row['sessions'];
            $orders    = (int) $row['orders_count'];
            $net_sales = round(((int) $row['net_sales_cents']) / 100, 2);

            return [
                'campaign'                => $row['utm_campaign'],
                'source'                  => $row['utm_source'],
                'medium'                  => $row['utm_medium'],
                'term'                    => $row['utm_term'],
                'content'                 => $row['utm_content'],
                'visitors'                => $visitors,
                'sessions'                => $sessions,
                'views'                   => (int) $row['views'],
                'orders'                  => $orders,
                'net_sales'               => $net_sales,
                'conversion_rate_percent' => $visitors > 0 ? round(($orders / $visitors) * 100, 2) : 0.0,
            ];
        }, $results);

        return $this->response([
            'success'   => true,
            'meta'      => $this->get_ia_meta(),
            'period'    => $date_range,
            'campaigns' => $items,
        ]);
    }

    /**
     * GET /analytics/devices
     * Returns breakdown by Device Type (Mobile, Desktop, Tablet), Browser, and Operating System.
     * Crucial for detecting UX/mobile conversion bottlenecks.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_devices(\WP_REST_Request $request) {
        if (!$this->is_ia_installed()) {
            return $this->not_installed_response();
        }

        global $wpdb;
        $date_range = $this->parse_date_range($request);

        $sessions_table  = $wpdb->prefix . 'independent_analytics_sessions';
        $types_table     = $wpdb->prefix . 'independent_analytics_device_types';
        $browsers_table  = $wpdb->prefix . 'independent_analytics_device_browsers';
        $oss_table       = $wpdb->prefix . 'independent_analytics_device_oss';
        $orders_table    = $wpdb->prefix . 'independent_analytics_orders';
        $has_orders      = $this->has_ia_orders_table();

        // 1. Device Types (Desktop, Mobile, Tablet)
        $orders_join = "";
        $orders_select = ", 0 AS orders_count, 0 AS net_sales_cents";

        if ($has_orders) {
            $orders_join = "
                LEFT JOIN (
                    SELECT 
                        s_inner.device_type_id,
                        COUNT(o.order_id) AS orders_count,
                        SUM(o.total - o.total_refunded) AS net_cents
                    FROM `$orders_table` o
                    JOIN `$sessions_table` s_inner ON o.initial_view_id = s_inner.initial_view_id
                    WHERE o.is_included_in_analytics = 1
                      AND o.created_at BETWEEN '{$date_range['start_utc']}' AND '{$date_range['end_utc']}'
                    GROUP BY s_inner.device_type_id
                ) ord ON dt.device_type_id = ord.device_type_id
            ";
            $orders_select = ", IFNULL(ord.orders_count, 0) AS orders_count, IFNULL(ord.net_cents, 0) AS net_sales_cents";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql_types = $wpdb->prepare(
            "SELECT 
                IFNULL(dt.device_type, 'Unknown') AS device_type,
                COUNT(DISTINCT s.visitor_id) AS visitors,
                COUNT(DISTINCT s.session_id) AS sessions,
                IFNULL(SUM(s.total_views), 0) AS views,
                COUNT(DISTINCT IF(s.final_view_id IS NULL, s.session_id, NULL)) AS bounces
                $orders_select
             FROM `$sessions_table` s
             LEFT JOIN `$types_table` dt ON s.device_type_id = dt.device_type_id
             $orders_join
             WHERE s.created_at BETWEEN %s AND %s
             GROUP BY device_type
             ORDER BY visitors DESC",
            $date_range['start_utc'],
            $date_range['end_utc']
        );

        $device_types = array_map(function ($row) {
            $visitors  = (int) $row['visitors'];
            $sessions  = (int) $row['sessions'];
            $bounces   = (int) $row['bounces'];
            $orders    = (int) $row['orders_count'];
            $net_sales = round(((int) $row['net_sales_cents']) / 100, 2);

            return [
                'type'                    => ucfirst($row['device_type']),
                'visitors'                => $visitors,
                'sessions'                => $sessions,
                'views'                   => (int) $row['views'],
                'bounce_rate_percent'     => $sessions > 0 ? round(($bounces / $sessions) * 100, 2) : 0.0,
                'orders'                  => $orders,
                'net_sales'               => $net_sales,
                'conversion_rate_percent' => $visitors > 0 ? round(($orders / $visitors) * 100, 2) : 0.0,
            ];
        }, $wpdb->get_results($sql_types, ARRAY_A) ?: []);

        // 2. Browsers (Chrome, Safari, Firefox, Edge, etc.)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql_browsers = $wpdb->prepare(
            "SELECT 
                IFNULL(db.device_browser, 'Unknown') AS browser,
                COUNT(DISTINCT s.visitor_id) AS visitors,
                COUNT(DISTINCT s.session_id) AS sessions
             FROM `$sessions_table` s
             LEFT JOIN `$browsers_table` db ON s.device_browser_id = db.device_browser_id
             WHERE s.created_at BETWEEN %s AND %s
             GROUP BY browser
             ORDER BY visitors DESC
             LIMIT 10",
            $date_range['start_utc'],
            $date_range['end_utc']
        );

        $browsers = array_map(function ($row) {
            return [
                'browser'  => $row['browser'],
                'visitors' => (int) $row['visitors'],
                'sessions' => (int) $row['sessions'],
            ];
        }, $wpdb->get_results($sql_browsers, ARRAY_A) ?: []);

        // 3. Operating Systems (Windows, iOS, Android, macOS, Linux)
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql_oss = $wpdb->prepare(
            "SELECT 
                IFNULL(d_os.device_os, 'Unknown') AS os,
                COUNT(DISTINCT s.visitor_id) AS visitors,
                COUNT(DISTINCT s.session_id) AS sessions
             FROM `$sessions_table` s
             LEFT JOIN `$oss_table` d_os ON s.device_os_id = d_os.device_os_id
             WHERE s.created_at BETWEEN %s AND %s
             GROUP BY os
             ORDER BY visitors DESC
             LIMIT 10",
            $date_range['start_utc'],
            $date_range['end_utc']
        );

        $operating_systems = array_map(function ($row) {
            return [
                'os'       => $row['os'],
                'visitors' => (int) $row['visitors'],
                'sessions' => (int) $row['sessions'],
            ];
        }, $wpdb->get_results($sql_oss, ARRAY_A) ?: []);

        return $this->response([
            'success'           => true,
            'meta'              => $this->get_ia_meta(),
            'period'            => $date_range,
            'device_types'      => $device_types,
            'browsers'          => $browsers,
            'operating_systems' => $operating_systems,
        ]);
    }

    /**
     * GET /analytics/geo
     * Returns geographic distribution (Countries & Cities).
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_geo(\WP_REST_Request $request) {
        if (!$this->is_ia_installed()) {
            return $this->not_installed_response();
        }

        global $wpdb;
        $date_range = $this->parse_date_range($request);
        $limit      = min(100, max(1, (int) ($request->get_param('limit') ?: 25)));

        $sessions_table  = $wpdb->prefix . 'independent_analytics_sessions';
        $countries_table = $wpdb->prefix . 'independent_analytics_countries';
        $cities_table    = $wpdb->prefix . 'independent_analytics_cities';
        $orders_table    = $wpdb->prefix . 'independent_analytics_orders';
        $has_orders      = $this->has_ia_orders_table();

        $orders_join = "";
        $orders_select = ", 0 AS orders_count, 0 AS net_sales_cents";

        if ($has_orders) {
            $orders_join = "
                LEFT JOIN (
                    SELECT 
                        s_inner.country_id,
                        COUNT(o.order_id) AS orders_count,
                        SUM(o.total - o.total_refunded) AS net_cents
                    FROM `$orders_table` o
                    JOIN `$sessions_table` s_inner ON o.initial_view_id = s_inner.initial_view_id
                    WHERE o.is_included_in_analytics = 1
                      AND o.created_at BETWEEN '{$date_range['start_utc']}' AND '{$date_range['end_utc']}'
                    GROUP BY s_inner.country_id
                ) ord ON c.country_id = ord.country_id
            ";
            $orders_select = ", IFNULL(ord.orders_count, 0) AS orders_count, IFNULL(ord.net_cents, 0) AS net_sales_cents";
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT 
                IFNULL(c.country, 'Unknown') AS country,
                c.country_code,
                COUNT(DISTINCT s.visitor_id) AS visitors,
                COUNT(DISTINCT s.session_id) AS sessions,
                IFNULL(SUM(s.total_views), 0) AS views
                $orders_select
             FROM `$sessions_table` s
             LEFT JOIN `$countries_table` c ON s.country_id = c.country_id
             $orders_join
             WHERE s.created_at BETWEEN %s AND %s
             GROUP BY country
             ORDER BY visitors DESC
             LIMIT %d",
            $date_range['start_utc'],
            $date_range['end_utc'],
            $limit
        );

        $countries = array_map(function ($row) {
            $visitors  = (int) $row['visitors'];
            $orders    = (int) $row['orders_count'];
            $net_sales = round(((int) $row['net_sales_cents']) / 100, 2);

            return [
                'country'                 => $row['country'],
                'country_code'            => $row['country_code'],
                'visitors'                => $visitors,
                'sessions'                => (int) $row['sessions'],
                'views'                   => (int) $row['views'],
                'orders'                  => $orders,
                'net_sales'               => $net_sales,
                'conversion_rate_percent' => $visitors > 0 ? round(($orders / $visitors) * 100, 2) : 0.0,
            ];
        }, $wpdb->get_results($sql, ARRAY_A) ?: []);

        return $this->response([
            'success'   => true,
            'meta'      => $this->get_ia_meta(),
            'period'    => $date_range,
            'countries' => $countries,
        ]);
    }

    /**
     * GET /analytics/conversions
     * Returns real-time/recent conversion stream (Orders & Form Submissions) with attribution.
     * Guaranteed zero PII (no customer IPs, emails, or phone numbers).
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_conversions(\WP_REST_Request $request) {
        if (!$this->is_ia_installed()) {
            return $this->not_installed_response();
        }

        global $wpdb;
        $limit = min(100, max(1, (int) ($request->get_param('limit') ?: 25)));

        $orders_table    = $wpdb->prefix . 'independent_analytics_orders';
        $views_table     = $wpdb->prefix . 'independent_analytics_views';
        $sessions_table  = $wpdb->prefix . 'independent_analytics_sessions';
        $resources_table = $wpdb->prefix . 'independent_analytics_resources';
        $countries_table = $wpdb->prefix . 'independent_analytics_countries';
        $types_table     = $wpdb->prefix . 'independent_analytics_device_types';
        $browsers_table  = $wpdb->prefix . 'independent_analytics_device_browsers';
        $has_orders      = $this->has_ia_orders_table();

        if (!$has_orders) {
            return $this->response([
                'success'     => true,
                'meta'        => $this->get_ia_meta(),
                'conversions' => [],
                'message'     => esc_html__('No ecommerce orders table active in Independent Analytics.', 'woo-get-data-for-ai'),
            ]);
        }

        // Query latest orders with technical and geographic attribution
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $sql = $wpdb->prepare(
            "SELECT 
                o.order_id AS ia_order_id,
                o.woocommerce_order_id AS wc_order_id,
                o.woocommerce_order_status AS status,
                o.total AS total_cents,
                o.total_refunded AS refunded_cents,
                o.is_discounted,
                o.created_at AS order_time,
                r.cached_title AS landing_page,
                r.cached_url AS landing_url,
                cnt.country,
                cnt.country_code,
                dt.device_type,
                db.device_browser AS browser
             FROM `$orders_table` o
             LEFT JOIN `$views_table` v ON o.initial_view_id = v.id
             LEFT JOIN `$resources_table` r ON v.resource_id = r.id
             LEFT JOIN `$sessions_table` s ON v.session_id = s.session_id
             LEFT JOIN `$countries_table` cnt ON s.country_id = cnt.country_id
             LEFT JOIN `$types_table` dt ON s.device_type_id = dt.device_type_id
             LEFT JOIN `$browsers_table` db ON s.device_browser_id = db.device_browser_id
             WHERE o.is_included_in_analytics = 1
             ORDER BY o.created_at DESC
             LIMIT %d",
            $limit
        );

        $results = $wpdb->get_results($sql, ARRAY_A) ?: [];

        $conversions = array_map(function ($row) {
            $total    = round(((int) $row['total_cents']) / 100, 2);
            $refunded = round(((int) $row['refunded_cents']) / 100, 2);

            return [
                'conversion_type' => 'order',
                'order_id'        => (int) $row['wc_order_id'],
                'status'          => $row['status'],
                'amount'          => $total,
                'refunded'        => $refunded,
                'net_amount'      => round($total - $refunded, 2),
                'is_discounted'   => (bool) $row['is_discounted'],
                'timestamp'       => $row['order_time'],
                'landing_page'    => $row['landing_page'],
                'landing_url'     => $row['landing_url'],
                'country'         => $row['country'] ?: 'Unknown',
                'country_code'    => $row['country_code'] ?: '',
                'device_type'     => ucfirst($row['device_type'] ?: 'Unknown'),
                'browser'         => $row['browser'] ?: 'Unknown',
            ];
        }, $results);

        return $this->response([
            'success'     => true,
            'meta'        => $this->get_ia_meta(),
            'total_shown' => count($conversions),
            'conversions' => $conversions,
        ]);
    }

    /**
     * GET /analytics/overview
     * Consolidated 360° audit in 1 single HTTP request.
     * Includes Summary KPIs + Top 10 Pages + Top 10 Referrers + Top 10 Campaigns + Device Breakdown.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_overview(\WP_REST_Request $request) {
        if (!$this->is_ia_installed()) {
            return $this->not_installed_response();
        }

        $date_range = $this->parse_date_range($request);

        // 1. Summary & Growth
        $current  = $this->query_summary_stats($date_range['start_utc'], $date_range['end_utc']);
        $previous = $this->query_summary_stats($date_range['previous_period']['start_utc'], $date_range['previous_period']['end_utc']);

        $growth = [
            'visitors_growth_percent'        => $this->calculate_growth($current['traffic']['visitors'], $previous['traffic']['visitors']),
            'views_growth_percent'           => $this->calculate_growth($current['traffic']['views'], $previous['traffic']['views']),
            'sessions_growth_percent'        => $this->calculate_growth($current['traffic']['sessions'], $previous['traffic']['sessions']),
            'orders_growth_percent'          => $this->calculate_growth($current['ecommerce']['orders'], $previous['ecommerce']['orders']),
            'net_sales_growth_percent'       => $this->calculate_growth($current['ecommerce']['net_sales'], $previous['ecommerce']['net_sales']),
            'conversion_rate_growth_percent' => $this->calculate_growth($current['ecommerce']['conversion_rate_percent'], $previous['ecommerce']['conversion_rate_percent']),
        ];

        // 2. Fetch top slices using internal requests
        $req_top = clone $request;
        $req_top->set_param('limit', 10);

        $pages_res     = $this->get_pages($req_top)->get_data();
        $referrers_res = $this->get_referrers($req_top)->get_data();
        $campaigns_res = $this->get_campaigns($req_top)->get_data();
        $devices_res   = $this->get_devices($req_top)->get_data();

        return $this->response([
            'success'       => true,
            'meta'          => $this->get_ia_meta(),
            'period'        => $date_range,
            'summary'       => $current,
            'growth'        => $growth,
            'top_pages'     => $pages_res['pages'] ?? [],
            'top_referrers' => $referrers_res['referrers'] ?? [],
            'top_campaigns' => $campaigns_res['campaigns'] ?? [],
            'device_types'  => $devices_res['device_types'] ?? [],
        ]);
    }
}
