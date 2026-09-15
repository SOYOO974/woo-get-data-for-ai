<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Redaction;

/**
 * Class Tracking_Controller
 *
 * REST API controller exposing diagnostic intelligence and compliance audits for:
 * 1. Meta Ads Hybrid Tracking (Browser Pixel + Conversions API CAPI v21.0)
 * 2. Google Ads Server-Side Tracking (REST API Conversion Uploads)
 * 3. Cookie Banners & GDPR / Google Consent Mode v2 Compliance
 *
 * All endpoints are strictly 100% Read-Only (HTTP GET).
 */
class Tracking_Controller extends Rest_Controller {

    /**
     * Register all Tracking routes under agent-bridge/v1/tracking/
     */
    public function register_routes() {
        // GET /tracking/audit (Consolidated 360° audit of tracking & consent health)
        register_rest_route(self::NAMESPACE, '/tracking/audit', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_audit'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'tracking');
            },
        ]);

        // GET /tracking/orders (Recent orders with CAPI & GAds tracking status & identifiers)
        register_rest_route(self::NAMESPACE, '/tracking/orders', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_orders'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'tracking');
            },
            'args'                => [
                'limit'           => [
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
                'status'          => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'tracking_filter' => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /tracking/logs (Unified tracking logs from database and WC logger)
        register_rest_route(self::NAMESPACE, '/tracking/logs', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_logs'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'tracking');
            },
            'args'                => [
                'provider' => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit'    => [
                    'default'           => 30,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * GET /tracking/audit
     *
     * Master 360° audit of server-side tracking and consent configuration.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_audit(\WP_REST_Request $request) {
        $meta_audit   = $this->audit_meta_tracking();
        $gads_audit   = $this->audit_google_ads_tracking();
        $consent_audit = $this->audit_gdpr_consent($meta_audit, $gads_audit);

        // Aggregate all alerts across components
        $alerts = array_merge(
            $meta_audit['alerts'],
            $gads_audit['alerts'],
            $consent_audit['alerts']
        );

        // Determine global health status
        $status = 'healthy';
        $critical_count = 0;
        $warning_count  = 0;

        foreach ($alerts as $alert) {
            if ($alert['level'] === 'critical') {
                $critical_count++;
                $status = 'critical';
            } elseif ($alert['level'] === 'warning') {
                $warning_count++;
                if ($status !== 'critical') {
                    $status = 'warning';
                }
            }
        }

        // Clean internal alert arrays from component outputs to avoid redundancy
        unset($meta_audit['alerts'], $gads_audit['alerts'], $consent_audit['alerts']);

        // Build actionable recommendations
        $recommendations = $this->generate_recommendations($alerts);

        $response_data = [
            'summary'             => [
                'status'           => $status,
                'total_alerts'     => count($alerts),
                'critical_alerts'  => $critical_count,
                'warning_alerts'   => $warning_count,
                'active_providers' => [
                    'meta_tracking_active'       => $meta_audit['plugin']['is_active'],
                    'google_ads_tracking_active' => $gads_audit['plugin']['is_active'],
                    'cmp_detected'               => $consent_audit['cmp_detected']['provider'],
                ],
                'alerts'           => $alerts,
            ],
            'meta_tracking'       => $meta_audit,
            'google_ads_tracking' => $gads_audit,
            'gdpr_cookie_banner'  => $consent_audit,
            'recommendations'     => $recommendations,
        ];

        return $this->response($response_data);
    }

    /**
     * GET /tracking/orders
     *
     * Inspect recent orders with server-side tracking metadata and consent flags.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_orders(\WP_REST_Request $request) {
        if (!class_exists('WooCommerce')) {
            return $this->error('woocommerce_not_active', esc_html__('WooCommerce is not active on this site.', 'woo-get-data-for-ai'), 400);
        }

        $limit           = min(50, max(1, (int) $request->get_param('limit')));
        $status_filter   = sanitize_text_field($request->get_param('status') ?: 'all');
        $tracking_filter = sanitize_text_field($request->get_param('tracking_filter') ?: 'all');

        $query_args = [
            'limit'   => 50, // Fetch up to 50 to allow client filtering
            'orderby' => 'date',
            'order'   => 'DESC',
            'type'    => 'shop_order',
        ];

        if ($status_filter !== 'all') {
            $query_args['status'] = array_map('trim', explode(',', $status_filter));
        }

        $wc_orders = function_exists('wc_get_orders') ? wc_get_orders($query_args) : [];
        $orders_data = [];

        foreach ($wc_orders as $order) {
            if (!($order instanceof \WC_Order) || ($order instanceof \WC_Order_Refund)) {
                continue;
            }

            $order_id = $order->get_id();

            // Meta tracking metadata
            $meta_status  = $order->get_meta('_wfbt_capi_status') ?: '';
            $meta_sent_at = $order->get_meta('_wfbt_capi_sent_at') ?: '';
            $meta_error   = $order->get_meta('_wfbt_capi_error') ?: '';
            $meta_consent = $order->get_meta('_wfbt_consent') ?: '';
            $has_fbp      = !empty($order->get_meta('_wfbt_fbp'));
            $has_fbc      = !empty($order->get_meta('_wfbt_fbc'));

            // Google Ads tracking metadata
            $gads_status  = $order->get_meta('_gads_api_status') ?: '';
            $gads_sent    = $order->get_meta('_gads_api_sent') ? true : false;
            $gads_error   = $order->get_meta('_gads_api_error') ?: '';
            $gads_consent = $order->get_meta('_gads_consent') ?: '';
            $has_gclid    = !empty($order->get_meta('_gads_gclid'));
            $has_wbraid   = !empty($order->get_meta('_gads_wbraid'));
            $has_gbraid   = !empty($order->get_meta('_gads_gbraid'));

            // Check if there is an Action Scheduler job pending for Meta CAPI
            $as_pending = false;
            if (function_exists('as_next_scheduled_action')) {
                $as_pending = (false !== as_next_scheduled_action('wfbt_send_capi_event', ['order_id' => $order_id], 'wfbt_capi'));
            }

            // Apply tracking filter
            if ($tracking_filter === 'failed') {
                $is_failed = ($meta_status === 'Failed' || ($gads_status !== '' && $gads_status !== 'Succès'));
                if (!$is_failed) {
                    continue;
                }
            } elseif ($tracking_filter === 'meta_failed' && $meta_status !== 'Failed') {
                continue;
            } elseif ($tracking_filter === 'gads_failed' && ($gads_status === '' || $gads_status === 'Succès')) {
                continue;
            } elseif ($tracking_filter === 'missing_consent' && $meta_consent !== 'denied' && $gads_consent !== 'denied') {
                continue;
            } elseif ($tracking_filter === 'has_identifiers' && !($has_gclid || $has_wbraid || $has_gbraid || $has_fbp || $has_fbc)) {
                continue;
            }

            $order_number = method_exists($order, 'get_order_number') ? $order->get_order_number() : (string) $order_id;

            $orders_data[] = [
                'order_id'       => $order_id,
                'order_number'   => $order_number,
                'status'         => $order->get_status(),
                'date_created'   => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : null,
                'total'          => number_format((float) $order->get_total(), 2, '.', ''),
                'currency'       => $order->get_currency(),
                'payment_method' => $order->get_payment_method(),
                'meta_capi'      => [
                    'status'             => $meta_status ?: ($as_pending ? 'Pending (Action Scheduler)' : 'Untracked'),
                    'sent_at'            => $meta_sent_at ?: null,
                    'error'              => $meta_error ?: null,
                    'consent'            => $meta_consent ?: 'not_recorded',
                    'has_fbp'            => $has_fbp,
                    'has_fbc'            => $has_fbc,
                    'as_action_pending'  => $as_pending,
                ],
                'google_ads'     => [
                    'status'             => $gads_status ?: ($gads_sent ? 'Succès' : 'Untracked'),
                    'is_sent'            => $gads_sent,
                    'error'              => $gads_error ?: null,
                    'consent'            => $gads_consent ?: 'not_recorded',
                    'has_gclid'          => $has_gclid,
                    'has_wbraid'         => $has_wbraid,
                    'has_gbraid'         => $has_gbraid,
                ],
            ];

            if (count($orders_data) >= $limit) {
                break;
            }
        }

        return $this->response([
            'total_inspected' => count($orders_data),
            'limit'           => $limit,
            'filters'         => [
                'status'          => $status_filter,
                'tracking_filter' => $tracking_filter,
            ],
            'orders'          => $orders_data,
        ]);
    }

    /**
     * GET /tracking/logs
     *
     * Unified tracking logs from Google Ads database table and Meta WC logger.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_logs(\WP_REST_Request $request) {
        $provider = sanitize_text_field($request->get_param('provider') ?: 'all');
        $limit    = min(100, max(1, (int) $request->get_param('limit')));

        $google_logs = [];
        $meta_logs   = [];

        // 1. Google Ads DB Logs
        if (in_array($provider, ['all', 'google_ads'], true)) {
            $google_logs = $this->fetch_google_ads_db_logs($limit);
        }

        // 2. Meta CAPI WC File Logs
        if (in_array($provider, ['all', 'meta'], true)) {
            $meta_logs = $this->fetch_meta_wc_file_logs($limit);
        }

        return $this->response([
            'provider'    => $provider,
            'google_ads'  => [
                'count' => count($google_logs),
                'logs'  => $google_logs,
            ],
            'meta_capi'   => [
                'count' => count($meta_logs),
                'logs'  => $meta_logs,
            ],
        ]);
    }

    // =========================================================================
    // AUDIT COMPONENT: META ADS (PIXEL + CAPI)
    // =========================================================================

    /**
     * Audit Meta Ads Hybrid Tracking (Pixel + CAPI).
     *
     * @return array
     */
    private function audit_meta_tracking() {
        $alerts = [];

        $plugin_file = 'woo-fb-tracking-server-side/woo-fb-tracking-server-side.php';
        $is_active   = is_plugin_active($plugin_file) || defined('WFBT_VERSION') || class_exists('\WFBT\Core');
        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin_file);
        $version     = defined('WFBT_VERSION') ? WFBT_VERSION : null;

        if (!$version && $is_installed && function_exists('get_file_data')) {
            $plugin_data = get_file_data(WP_PLUGIN_DIR . '/' . $plugin_file, ['Version' => 'Version']);
            $version = $plugin_data['Version'] ?? null;
        }

        // Read stored options
        $pixel_id          = get_option('wfbt_pixel_id', '');
        $access_token      = get_option('wfbt_access_token', '');
        $test_code         = get_option('wfbt_test_code', '');
        $enable_pixel      = get_option('wfbt_enable_pixel', 'yes') === 'yes';
        $enable_debug_bar  = get_option('wfbt_enable_debug_bar', 'no') === 'yes';
        $respect_consent   = get_option('wfbt_respect_consent', 'yes') === 'yes';
        $consent_action    = get_option('wfbt_consent_action', 'anonymize');
        $concord_cookie    = get_option('wfbt_concord_cookie_name', 'concord_consent');
        $trigger_statuses  = get_option('wfbt_trigger_statuses', ['processing', 'completed']);
        $enable_alerts     = get_option('wfbt_enable_alerts', 'no') === 'yes';
        $alert_email       = get_option('wfbt_alert_email', get_option('admin_email'));

        // Validation & Alerts
        if (!$is_active) {
            $alerts[] = [
                'level'          => 'info',
                'code'           => 'meta_plugin_inactive',
                'message'        => esc_html__('Meta Tracking Server-Side plugin (woo-fb-tracking-server-side) is not active.', 'woo-get-data-for-ai'),
                'recommendation' => esc_html__('Activate woo-fb-tracking-server-side to enable hybrid Meta Pixel + Conversions API (CAPI) tracking.', 'woo-get-data-for-ai'),
            ];
        } else {
            // Check Pixel ID
            $pixel_valid = !empty($pixel_id) && ctype_digit($pixel_id) && strlen($pixel_id) >= 10;
            if (empty($pixel_id)) {
                $alerts[] = [
                    'level'          => 'critical',
                    'code'           => 'meta_pixel_id_missing',
                    'message'        => esc_html__('Meta Pixel ID is missing in configuration.', 'woo-get-data-for-ai'),
                    'recommendation' => esc_html__('Enter your 15-16 digit Meta Dataset / Pixel ID in Woo FB Tracking settings.', 'woo-get-data-for-ai'),
                ];
            } elseif (!$pixel_valid) {
                $alerts[] = [
                    'level'          => 'warning',
                    'code'           => 'meta_pixel_id_invalid_format',
                    'message'        => sprintf(esc_html__('Meta Pixel ID format appears invalid (%s). Expected 10-16 numeric digits.', 'woo-get-data-for-ai'), esc_html($pixel_id)),
                    'recommendation' => esc_html__('Verify the Pixel ID in Meta Events Manager.', 'woo-get-data-for-ai'),
                ];
            }

            // Check Access Token
            if (empty($access_token)) {
                $alerts[] = [
                    'level'          => 'critical',
                    'code'           => 'meta_access_token_missing',
                    'message'        => esc_html__('Meta CAPI Access Token is missing.', 'woo-get-data-for-ai'),
                    'recommendation' => esc_html__('Generate a System User token with ads_management permissions in Meta Events Manager and paste it in plugin settings.', 'woo-get-data-for-ai'),
                ];
            } elseif (strlen($access_token) < 30) {
                $alerts[] = [
                    'level'          => 'warning',
                    'code'           => 'meta_access_token_too_short',
                    'message'        => esc_html__('Meta Access Token appears abnormally short or malformed.', 'woo-get-data-for-ai'),
                    'recommendation' => esc_html__('Check the token in Meta Business Manager.', 'woo-get-data-for-ai'),
                ];
            }

            // Check Test Event Code in Production
            if (!empty($test_code)) {
                $alerts[] = [
                    'level'          => 'warning',
                    'code'           => 'meta_test_code_active',
                    'message'        => sprintf(esc_html__('Meta Test Event Code is active in production: "%s".', 'woo-get-data-for-ai'), esc_html($test_code)),
                    'recommendation' => esc_html__('Clear the Test Event Code field in plugin settings once testing is finished to avoid treating live purchases as test events.', 'woo-get-data-for-ai'),
                ];
            }

            // Check Trigger Statuses
            if (empty($trigger_statuses) || !is_array($trigger_statuses)) {
                $alerts[] = [
                    'level'          => 'critical',
                    'code'           => 'meta_trigger_statuses_empty',
                    'message'        => esc_html__('No order trigger statuses configured for Meta CAPI.', 'woo-get-data-for-ai'),
                    'recommendation' => esc_html__('Select at least "processing" and "completed" in Woo FB Tracking settings.', 'woo-get-data-for-ai'),
                ];
            }

            // Check Consent Setting
            if (!$respect_consent) {
                $alerts[] = [
                    'level'          => 'warning',
                    'code'           => 'meta_consent_not_respected',
                    'message'        => esc_html__('Meta CAPI is configured to bypass customer cookie consent.', 'woo-get-data-for-ai'),
                    'recommendation' => esc_html__('Enable "Respect GDPR Consent" to comply with European privacy regulations (ePrivacy / RGPD).', 'woo-get-data-for-ai'),
                ];
            }
        }

        // Action Scheduler Queue Health
        $as_health = $this->get_meta_action_scheduler_health();
        if ($as_health['failed_last_7d'] > 0) {
            $alerts[] = [
                'level'          => 'critical',
                'code'           => 'meta_capi_as_actions_failed',
                'message'        => sprintf(esc_html__('%d Meta CAPI Action Scheduler jobs failed in the last 7 days.', 'woo-get-data-for-ai'), $as_health['failed_last_7d']),
                'recommendation' => esc_html__('Inspect Action Scheduler failures under WooCommerce > Status > Action Scheduler (group: wfbt_capi).', 'woo-get-data-for-ai'),
            ];
        }
        if ($as_health['pending'] > 20) {
            $alerts[] = [
                'level'          => 'warning',
                'code'           => 'meta_capi_as_backlog',
                'message'        => sprintf(esc_html__('Backlog of %d pending Meta CAPI actions in Action Scheduler.', 'woo-get-data-for-ai'), $as_health['pending']),
                'recommendation' => esc_html__('Verify WP-Cron execution to ensure background jobs are processing smoothly.', 'woo-get-data-for-ai'),
            ];
        }

        // Recent Orders Transmission Sample
        $orders_summary = $this->get_meta_orders_summary();
        if ($orders_summary['failed_count'] > 0) {
            $alerts[] = [
                'level'          => 'warning',
                'code'           => 'meta_recent_orders_failed',
                'message'        => sprintf(esc_html__('%d of last %d orders failed Meta CAPI transmission.', 'woo-get-data-for-ai'), $orders_summary['failed_count'], $orders_summary['total_inspected']),
                'recommendation' => esc_html__('Check recent error reasons in the recent_failed_samples array.', 'woo-get-data-for-ai'),
            ];
        }

        // Mask Access Token safely (Never expose secrets)
        $token_preview = 'missing';
        if (!empty($access_token)) {
            $len = strlen($access_token);
            $token_preview = substr($access_token, 0, 6) . '...' . substr($access_token, -4) . " (Length: {$len})";
        }

        return [
            'plugin'                => [
                'is_active'    => $is_active,
                'is_installed' => $is_installed,
                'version'      => $version,
                'plugin_file'  => $plugin_file,
            ],
            'configuration'         => [
                'pixel_id'               => !empty($pixel_id) ? $pixel_id : null,
                'pixel_id_valid_format'  => !empty($pixel_id) && ctype_digit($pixel_id) && strlen($pixel_id) >= 10,
                'access_token_status'    => !empty($access_token) ? 'configured' : 'missing',
                'access_token_preview'   => $token_preview,
                'enable_browser_pixel'   => $enable_pixel,
                'enable_debug_bar'       => $enable_debug_bar,
                'test_event_code'        => !empty($test_code) ? $test_code : null,
                'has_active_test_code'   => !empty($test_code),
                'respect_consent'        => $respect_consent,
                'consent_action'         => $consent_action,
                'concord_cookie_name'    => $concord_cookie,
                'trigger_statuses'       => (array) $trigger_statuses,
                'enable_alerts'          => $enable_alerts,
                'alert_email'            => !empty($alert_email) ? Redaction::redact_string($alert_email, true) : null,
            ],
            'action_scheduler'      => $as_health,
            'recent_orders_summary' => $orders_summary,
            'alerts'                => $alerts,
        ];
    }

    /**
     * Inspect Action Scheduler for Meta CAPI jobs.
     *
     * @return array
     */
    private function get_meta_action_scheduler_health() {
        global $wpdb;

        $table = $wpdb->prefix . 'actionscheduler_actions';
        $group_table = $wpdb->prefix . 'actionscheduler_groups';

        $health = [
            'pending'         => 0,
            'in_progress'     => 0,
            'failed_last_7d'  => 0,
            'complete_last_7d'=> 0,
        ];

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return $health;
        }

        $seven_days_ago = gmdate('Y-m-d H:i:s', time() - (7 * 86400));

        // Group-based counts
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT a.status, COUNT(*) as cnt 
             FROM {$table} a 
             LEFT JOIN {$group_table} g ON a.group_id = g.group_id 
             WHERE (g.slug = 'wfbt_capi' OR a.hook = 'wfbt_send_capi_event')
               AND (a.status IN ('pending', 'in-progress') OR a.scheduled_date_gmt >= %s)
             GROUP BY a.status",
            $seven_days_ago
        ), ARRAY_A);

        if (is_array($results)) {
            foreach ($results as $row) {
                $status = $row['status'] ?? '';
                $count  = (int) ($row['cnt'] ?? 0);
                if ($status === 'pending') {
                    $health['pending'] = $count;
                } elseif ($status === 'in-progress') {
                    $health['in_progress'] = $count;
                } elseif ($status === 'failed') {
                    $health['failed_last_7d'] = $count;
                } elseif ($status === 'complete') {
                    $health['complete_last_7d'] = $count;
                }
            }
        }

        return $health;
    }

    /**
     * Inspect recent orders for Meta CAPI transmission status.
     *
     * @return array
     */
    private function get_meta_orders_summary() {
        $summary = [
            'total_inspected'       => 0,
            'success_count'         => 0,
            'failed_count'          => 0,
            'ignored_consent_count' => 0,
            'untracked_count'       => 0,
            'success_rate_pct'      => null,
            'recent_failed_samples' => [],
        ];

        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            return $summary;
        }

        $orders = wc_get_orders([
            'limit'   => 50,
            'orderby' => 'date',
            'order'   => 'DESC',
            'type'    => 'shop_order',
        ]);

        $summary['total_inspected'] = count($orders);

        foreach ($orders as $order) {
            if (!($order instanceof \WC_Order)) {
                continue;
            }

            $capi_status = $order->get_meta('_wfbt_capi_status');
            $capi_error  = $order->get_meta('_wfbt_capi_error');

            if ($capi_status === 'Success') {
                $summary['success_count']++;
            } elseif ($capi_status === 'Failed') {
                $summary['failed_count']++;
                if (count($summary['recent_failed_samples']) < 5) {
                    $summary['recent_failed_samples'][] = [
                        'order_id'     => $order->get_id(),
                        'order_number' => method_exists($order, 'get_order_number') ? $order->get_order_number() : (string) $order->get_id(),
                        'error'        => $capi_error ?: 'Unknown error',
                        'date'         => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : null,
                    ];
                }
            } elseif (strpos((string) $capi_status, 'Ignored') !== false) {
                $summary['ignored_consent_count']++;
            } else {
                $summary['untracked_count']++;
            }
        }

        $attempted = $summary['success_count'] + $summary['failed_count'];
        if ($attempted > 0) {
            $summary['success_rate_pct'] = round(($summary['success_count'] / $attempted) * 100, 1);
        }

        return $summary;
    }

    // =========================================================================
    // AUDIT COMPONENT: GOOGLE ADS SERVER-SIDE TRACKING
    // =========================================================================

    /**
     * Audit Google Ads Server-Side Tracking.
     *
     * @return array
     */
    private function audit_google_ads_tracking() {
        $alerts = [];

        $plugin_file = 'woo-gads-tracking-server-side/woo-gads-server-side.php';
        $is_active   = is_plugin_active($plugin_file) || defined('WOO_GADS_VERSION') || class_exists('Woo_Gads');
        $is_installed = file_exists(WP_PLUGIN_DIR . '/' . $plugin_file);
        $version     = defined('WOO_GADS_VERSION') ? WOO_GADS_VERSION : null;

        if (!$version && $is_installed && function_exists('get_file_data')) {
            $plugin_data = get_file_data(WP_PLUGIN_DIR . '/' . $plugin_file, ['Version' => 'Version']);
            $version = $plugin_data['Version'] ?? null;
        }

        // Read stored options
        $settings = get_option('woo_gads_settings', []);
        if (!is_array($settings)) {
            $settings = [];
        }

        $dev_token        = $settings['developer_token'] ?? '';
        $merchant_id      = $settings['merchant_id'] ?? '';
        $manager_id       = $settings['manager_id'] ?? '';
        $conv_action_id   = $settings['conversion_action_id'] ?? '';
        $client_id        = $settings['client_id'] ?? '';
        $client_secret    = $settings['client_secret'] ?? '';
        $refresh_token    = $settings['refresh_token'] ?? '';
        $builtin_banner   = !empty($settings['enable_builtin_banner']);
        $cookie_name      = $builtin_banner ? 'woo_gads_consent' : ($settings['consent_cookie_name'] ?? 'concord_consent');
        $order_statuses   = $settings['order_statuses'] ?? ['processing', 'completed'];
        $enable_alerts    = !empty($settings['enable_email_alerts']);
        $alert_email      = $settings['alert_email'] ?? get_option('admin_email');

        // Validation & Alerts
        if (!$is_active) {
            $alerts[] = [
                'level'          => 'info',
                'code'           => 'gads_plugin_inactive',
                'message'        => esc_html__('Google Ads Server-Side Tracking plugin (woo-gads-tracking-server-side) is not active.', 'woo-get-data-for-ai'),
                'recommendation' => esc_html__('Activate woo-gads-tracking-server-side to enable server-side Google Ads conversion uploads.', 'woo-get-data-for-ai'),
            ];
        } else {
            // Check Developer Token
            if (empty($dev_token)) {
                $alerts[] = [
                    'level'          => 'critical',
                    'code'           => 'gads_dev_token_missing',
                    'message'        => esc_html__('Google Ads Developer Token is missing.', 'woo-get-data-for-ai'),
                    'recommendation' => esc_html__('Enter your Google Ads Developer Token from the Google Ads API Center.', 'woo-get-data-for-ai'),
                ];
            }

            // Check Merchant ID (Customer ID)
            $clean_merchant_id = preg_replace('/[^0-9]/', '', (string) $merchant_id);
            if (empty($merchant_id)) {
                $alerts[] = [
                    'level'          => 'critical',
                    'code'           => 'gads_merchant_id_missing',
                    'message'        => esc_html__('Google Ads Merchant Customer ID is missing.', 'woo-get-data-for-ai'),
                    'recommendation' => esc_html__('Enter your 10-digit Google Ads account ID (without dashes).', 'woo-get-data-for-ai'),
                ];
            } elseif (strlen($clean_merchant_id) !== 10) {
                $alerts[] = [
                    'level'          => 'warning',
                    'code'           => 'gads_merchant_id_invalid_length',
                    'message'        => sprintf(esc_html__('Google Ads Customer ID (%s) does not have exactly 10 digits.', 'woo-get-data-for-ai'), esc_html($merchant_id)),
                    'recommendation' => esc_html__('Verify your Google Ads account ID.', 'woo-get-data-for-ai'),
                ];
            }

            // Check Conversion Action ID
            if (empty($conv_action_id)) {
                $alerts[] = [
                    'level'          => 'critical',
                    'code'           => 'gads_conversion_action_id_missing',
                    'message'        => esc_html__('Google Ads Conversion Action ID is missing.', 'woo-get-data-for-ai'),
                    'recommendation' => esc_html__('Enter the numeric Conversion Action ID from Google Ads > Conversions.', 'woo-get-data-for-ai'),
                ];
            }

            // Check OAuth Credentials & Refresh Token
            if (empty($client_id) || empty($client_secret)) {
                $alerts[] = [
                    'level'          => 'critical',
                    'code'           => 'gads_oauth_credentials_missing',
                    'message'        => esc_html__('Google Cloud OAuth Client ID or Client Secret is missing.', 'woo-get-data-for-ai'),
                    'recommendation' => esc_html__('Create OAuth credentials in Google Cloud Console and paste them in plugin settings.', 'woo-get-data-for-ai'),
                ];
            }

            if (empty($refresh_token)) {
                $alerts[] = [
                    'level'          => 'critical',
                    'code'           => 'gads_refresh_token_missing',
                    'message'        => esc_html__('Google Ads OAuth is not connected (Refresh Token missing).', 'woo-get-data-for-ai'),
                    'recommendation' => esc_html__('Click "Se connecter avec Google" in the Google Ads plugin settings to authorize API transmission.', 'woo-get-data-for-ai'),
                ];
            }

            // Check Trigger Statuses
            if (empty($order_statuses) || !is_array($order_statuses)) {
                $alerts[] = [
                    'level'          => 'critical',
                    'code'           => 'gads_trigger_statuses_empty',
                    'message'        => esc_html__('No order trigger statuses configured for Google Ads conversions.', 'woo-get-data-for-ai'),
                    'recommendation' => esc_html__('Select at least "processing" and "completed" in Google Ads Server-Side settings.', 'woo-get-data-for-ai'),
                ];
            }
        }

        // Database Logs Health
        $db_logs_health = $this->get_google_ads_db_logs_health();
        if ($db_logs_health['error_count'] > 0) {
            $alerts[] = [
                'level'          => 'warning',
                'code'           => 'gads_recent_api_errors',
                'message'        => sprintf(esc_html__('%d Google Ads API conversion uploads resulted in errors in recent logs.', 'woo-get-data-for-ai'), $db_logs_health['error_count']),
                'recommendation' => esc_html__('Review recent error details in google_ads_tracking.database_logs.recent_errors.', 'woo-get-data-for-ai'),
            ];
        }

        // Orders Metadata & Click Identifiers
        $orders_summary = $this->get_google_ads_orders_summary();

        // Safely format token previews
        $refresh_preview = 'missing';
        if (!empty($refresh_token)) {
            $len = strlen($refresh_token);
            $refresh_preview = substr($refresh_token, 0, 6) . '...' . substr($refresh_token, -4) . " (Length: {$len})";
        }

        return [
            'plugin'                => [
                'is_active'    => $is_active,
                'is_installed' => $is_installed,
                'version'      => $version,
                'plugin_file'  => $plugin_file,
            ],
            'configuration'         => [
                'developer_token_status' => !empty($dev_token) ? 'configured' : 'missing',
                'merchant_id'            => !empty($merchant_id) ? $clean_merchant_id : null,
                'merchant_id_valid'      => strlen($clean_merchant_id) === 10,
                'manager_id'             => !empty($manager_id) ? preg_replace('/[^0-9]/', '', (string) $manager_id) : null,
                'conversion_action_id'   => !empty($conv_action_id) ? $conv_action_id : null,
                'client_id_status'       => !empty($client_id) ? 'configured' : 'missing',
                'client_secret_status'   => !empty($client_secret) ? 'configured' : 'missing',
                'refresh_token_status'   => !empty($refresh_token) ? 'configured' : 'missing',
                'refresh_token_preview'  => $refresh_preview,
                'is_oauth_authenticated' => !empty($refresh_token),
                'enable_builtin_banner'  => $builtin_banner,
                'consent_cookie_name'    => $cookie_name,
                'order_statuses'         => (array) $order_statuses,
                'enable_email_alerts'    => $enable_alerts,
                'alert_email'            => !empty($alert_email) ? Redaction::redact_string($alert_email, true) : null,
            ],
            'database_logs'         => $db_logs_health,
            'recent_orders_summary' => $orders_summary,
            'alerts'                => $alerts,
        ];
    }

    /**
     * Inspect Google Ads custom database log table (wp_woo_gads_logs).
     *
     * @return array
     */
    private function get_google_ads_db_logs_health() {
        global $wpdb;

        $table = $wpdb->prefix . 'woo_gads_logs';
        $health = [
            'table_exists'       => false,
            'total_logs'         => 0,
            'success_count'      => 0,
            'error_count'        => 0,
            'latest_entry_time'  => null,
            'recent_errors'      => [],
        ];

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return $health;
        }

        $health['table_exists'] = true;

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $health['total_logs'] = $total;

        if ($total > 0) {
            $success = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE http_status IN (200, 201) AND (error = '' OR error IS NULL)");
            $errors  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE http_status NOT IN (200, 201) OR (error != '' AND error IS NOT NULL)");
            $latest  = $wpdb->get_var("SELECT time FROM {$table} ORDER BY id DESC LIMIT 1");

            $health['success_count']     = $success;
            $health['error_count']       = $errors;
            $health['latest_entry_time'] = $latest;

            if ($errors > 0) {
                $err_rows = $wpdb->get_results(
                    "SELECT id, time, order_id, http_status, error 
                     FROM {$table} 
                     WHERE http_status NOT IN (200, 201) OR (error != '' AND error IS NOT NULL)
                     ORDER BY id DESC LIMIT 5",
                    ARRAY_A
                );
                $health['recent_errors'] = $err_rows ?: [];
            }
        }

        return $health;
    }

    /**
     * Inspect recent orders for Google Ads tracking metadata and click IDs.
     *
     * @return array
     */
    private function get_google_ads_orders_summary() {
        $summary = [
            'total_inspected'   => 0,
            'success_count'     => 0,
            'error_count'       => 0,
            'untracked_count'   => 0,
            'identifiers_stats' => [
                'orders_with_gclid'  => 0,
                'orders_with_wbraid' => 0,
                'orders_with_gbraid' => 0,
            ],
            'consent_stats'     => [
                'granted'      => 0,
                'denied'       => 0,
                'not_recorded' => 0,
            ],
        ];

        if (!class_exists('WooCommerce') || !function_exists('wc_get_orders')) {
            return $summary;
        }

        $orders = wc_get_orders([
            'limit'   => 50,
            'orderby' => 'date',
            'order'   => 'DESC',
            'type'    => 'shop_order',
        ]);

        $summary['total_inspected'] = count($orders);

        foreach ($orders as $order) {
            if (!($order instanceof \WC_Order)) {
                continue;
            }

            $gads_status  = $order->get_meta('_gads_api_status');
            $gads_sent    = $order->get_meta('_gads_api_sent');
            $gads_consent = $order->get_meta('_gads_consent');

            // Status count
            if ($gads_status === 'Succès' || $gads_sent) {
                $summary['success_count']++;
            } elseif (!empty($gads_status) && $gads_status !== 'Succès') {
                $summary['error_count']++;
            } else {
                $summary['untracked_count']++;
            }

            // Click Identifiers
            if (!empty($order->get_meta('_gads_gclid'))) {
                $summary['identifiers_stats']['orders_with_gclid']++;
            }
            if (!empty($order->get_meta('_gads_wbraid'))) {
                $summary['identifiers_stats']['orders_with_wbraid']++;
            }
            if (!empty($order->get_meta('_gads_gbraid'))) {
                $summary['identifiers_stats']['orders_with_gbraid']++;
            }

            // Consent stats
            if ($gads_consent === 'granted') {
                $summary['consent_stats']['granted']++;
            } elseif ($gads_consent === 'denied') {
                $summary['consent_stats']['denied']++;
            } else {
                $summary['consent_stats']['not_recorded']++;
            }
        }

        return $summary;
    }

    // =========================================================================
    // AUDIT COMPONENT: GDPR COOKIE BANNER & CONSENT MODE V2
    // =========================================================================

    /**
     * Audit GDPR Cookie Banners and Google Consent Mode v2.
     *
     * @param array $meta_audit
     * @param array $gads_audit
     * @return array
     */
    private function audit_gdpr_consent($meta_audit, $gads_audit) {
        $alerts = [];

        // 1. Detect active CMP / Cookie Banner
        $cmp = $this->detect_cookie_management_provider($gads_audit);

        // 2. Privacy Policy Page
        $privacy_url  = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
        $privacy_id   = (int) get_option('wp_page_for_privacy_policy', 0);
        $privacy_post = $privacy_id ? get_post($privacy_id) : null;
        $privacy_status = ($privacy_post && $privacy_post->post_status === 'publish') ? 'publish' : ($privacy_id ? 'draft' : 'missing');

        if (empty($privacy_url) || $privacy_status !== 'publish') {
            $alerts[] = [
                'level'          => 'warning',
                'code'           => 'privacy_policy_missing',
                'message'        => esc_html__('No published Privacy Policy page configured in WordPress (Settings > Privacy).', 'woo-get-data-for-ai'),
                'recommendation' => esc_html__('Assign and publish a legal Privacy Policy page to satisfy RGPD requirements.', 'woo-get-data-for-ai'),
            ];
        }

        // 3. Google Consent Mode v2 Signals
        $consent_mode_v2 = [
            'is_supported'      => false,
            'source'            => 'none',
            'required_signals'  => [
                'ad_storage'            => 'ad_storage',
                'analytics_storage'     => 'analytics_storage',
                'ad_user_data'          => 'ad_user_data',
                'ad_personalization'    => 'ad_personalization',
            ],
            'default_state'     => 'unknown',
        ];

        if ($cmp['provider'] === 'native_woo_gads') {
            $consent_mode_v2['is_supported']  = true;
            $consent_mode_v2['source']        = 'woo-gads-server-side native banner';
            $consent_mode_v2['default_state'] = 'denied';
        } elseif ($cmp['provider'] === 'complianz') {
            $consent_mode_v2['is_supported']  = true;
            $consent_mode_v2['source']        = 'Complianz GDPR';
            $consent_mode_v2['default_state'] = 'denied';
        } elseif ($cmp['provider'] === 'cookiebot') {
            $consent_mode_v2['is_supported']  = true;
            $consent_mode_v2['source']        = 'Cookiebot';
            $consent_mode_v2['default_state'] = 'denied';
        }

        // Alert if tracking is active but no CMP was detected
        $tracking_active = $meta_audit['plugin']['is_active'] || $gads_audit['plugin']['is_active'];
        if ($tracking_active && $cmp['provider'] === 'none') {
            $alerts[] = [
                'level'          => 'critical',
                'code'           => 'no_cookie_banner_detected',
                'message'        => esc_html__('No active cookie banner or CMP detected while server-side tracking is active.', 'woo-get-data-for-ai'),
                'recommendation' => esc_html__('Activate the built-in banner in Woo Google Ads settings or install a certified CMP (Complianz, Axeptio, Concord) to prevent RGPD non-compliance.', 'woo-get-data-for-ai'),
            ];
        }

        // 4. Inter-Plugin Cookie Name Coherence Check (Critical Point)
        $meta_cookie_target = $meta_audit['configuration']['concord_cookie_name'] ?? 'concord_consent';
        $gads_cookie_target = $gads_audit['configuration']['consent_cookie_name'] ?? 'concord_consent';
        $is_builtin_gads    = !empty($gads_audit['configuration']['enable_builtin_banner']);

        $coherence = [
            'is_cookie_coherent'   => true,
            'meta_cookie_watched'  => $meta_cookie_target,
            'gads_cookie_watched'  => $gads_cookie_target,
            'banner_cookie_name'   => $cmp['cookie_name'],
            'message'              => esc_html__('Cookie names match between tracking plugins and banner.', 'woo-get-data-for-ai'),
        ];

        // If Google Ads native banner is active, it sets 'woo_gads_consent'
        if ($is_builtin_gads && $meta_audit['plugin']['is_active']) {
            if ($meta_cookie_target !== 'woo_gads_consent') {
                $coherence['is_cookie_coherent'] = false;
                $coherence['message'] = sprintf(
                    esc_html__('Cookie Mismatch! Google Ads native banner sets "woo_gads_consent", but Meta CAPI is watching "%s". Meta CAPI will never detect user consent!', 'woo-get-data-for-ai'),
                    esc_html($meta_cookie_target)
                );

                $alerts[] = [
                    'level'          => 'critical',
                    'code'           => 'tracking_cookie_name_mismatch',
                    'message'        => $coherence['message'],
                    'recommendation' => esc_html__('Set "Concord / Consent Cookie Name" to "woo_gads_consent" in Woo FB Tracking settings to align with the native banner.', 'woo-get-data-for-ai'),
                ];
            }
        } elseif ($cmp['cookie_name'] && $meta_audit['plugin']['is_active'] && $meta_cookie_target !== $cmp['cookie_name']) {
            $coherence['is_cookie_coherent'] = false;
            $coherence['message'] = sprintf(
                esc_html__('Potential Cookie Mismatch: CMP uses "%s", but Meta CAPI is watching "%s".', 'woo-get-data-for-ai'),
                esc_html($cmp['cookie_name']),
                esc_html($meta_cookie_target)
            );

            $alerts[] = [
                'level'          => 'warning',
                'code'           => 'cmp_cookie_name_mismatch',
                'message'        => $coherence['message'],
                'recommendation' => sprintf(esc_html__('Verify that Meta CAPI cookie name is configured to "%s".', 'woo-get-data-for-ai'), esc_html($cmp['cookie_name'])),
            ];
        }

        return [
            'cmp_detected'           => $cmp,
            'privacy_policy'         => [
                'page_id' => $privacy_id ?: null,
                'url'     => $privacy_url ?: null,
                'status'  => $privacy_status,
            ],
            'google_consent_mode_v2' => $consent_mode_v2,
            'coherence_check'        => $coherence,
            'alerts'                 => $alerts,
        ];
    }

    /**
     * Detect installed and active Cookie Management Providers (CMP).
     *
     * @param array $gads_audit
     * @return array
     */
    private function detect_cookie_management_provider($gads_audit) {
        // 1. Native Woo Gads built-in banner
        if (!empty($gads_audit['configuration']['enable_builtin_banner'])) {
            return [
                'provider'    => 'native_woo_gads',
                'name'        => 'Woo Google Ads Built-in Banner (Consent Mode v2)',
                'is_active'   => true,
                'cookie_name' => 'woo_gads_consent',
                'details'     => [
                    'lightweight'        => true,
                    'consent_mode_v2'    => true,
                    'manages_cookies'    => true,
                ],
            ];
        }

        // 2. Complianz
        if (class_exists('COMPLIANZ') || defined('cmplz_version')) {
            return [
                'provider'    => 'complianz',
                'name'        => 'Complianz GDPR/CCPA',
                'is_active'   => true,
                'cookie_name' => 'complianz_consent_status',
                'details'     => [
                    'version'            => defined('cmplz_version') ? cmplz_version : null,
                    'consent_mode_v2'    => function_exists('cmplz_get_value') ? (bool) cmplz_get_value('use_categories') : true,
                ],
            ];
        }

        // 3. Axeptio
        if (class_exists('Axeptio') || defined('AXEPTIO_PLUGIN_DIR')) {
            return [
                'provider'    => 'axeptio',
                'name'        => 'Axeptio for WordPress',
                'is_active'   => true,
                'cookie_name' => 'axeptio_cookies',
                'details'     => [
                    'client_id' => get_option('axeptio_client_id', ''),
                ],
            ];
        }

        // 4. Cookiebot
        if (class_exists('Cookiebot_WP') || defined('COOKIEBOT_PLUGIN_DIR')) {
            return [
                'provider'    => 'cookiebot',
                'name'        => 'Cookiebot CMP',
                'is_active'   => true,
                'cookie_name' => 'CookieConsent',
                'details'     => [],
            ];
        }

        // 5. Tarteaucitron
        if (is_plugin_active('tarteaucitronjs/tarteaucitron.php')) {
            return [
                'provider'    => 'tarteaucitron',
                'name'        => 'tarteaucitron.js',
                'is_active'   => true,
                'cookie_name' => 'tarteaucitron',
                'details'     => [],
            ];
        }

        // 6. Cookie Notice (dFactory / Hu-manity)
        if (class_exists('Cookie_Notice')) {
            return [
                'provider'    => 'cookie_notice',
                'name'        => 'Cookie Notice & Compliance',
                'is_active'   => true,
                'cookie_name' => 'cookie_notice_accepted',
                'details'     => [],
            ];
        }

        // 7. Generic WP Consent API
        if (function_exists('wp_has_consent')) {
            return [
                'provider'    => 'wp_consent_api',
                'name'        => 'WP Consent API Compatible CMP',
                'is_active'   => true,
                'cookie_name' => null,
                'details'     => [],
            ];
        }

        return [
            'provider'    => 'none',
            'name'        => 'None detected',
            'is_active'   => false,
            'cookie_name' => null,
            'details'     => [],
        ];
    }

    // =========================================================================
    // LOGS HELPERS: DATABASE & WC LOGGER
    // =========================================================================

    /**
     * Fetch Google Ads raw database logs.
     *
     * @param int $limit
     * @return array
     */
    private function fetch_google_ads_db_logs($limit = 30) {
        global $wpdb;
        $table = $wpdb->prefix . 'woo_gads_logs';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, time, order_id, http_status, payload, response, error 
             FROM {$table} 
             ORDER BY id DESC 
             LIMIT %d",
            $limit
        ), ARRAY_A);

        if (!is_array($rows)) {
            return [];
        }

        $logs = [];
        foreach ($rows as $row) {
            $payload  = maybe_unserialize($row['payload']);
            $response = maybe_unserialize($row['response']);

            // Parse json if stored as json string
            if (is_string($payload)) {
                $decoded = json_decode($payload, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $payload = $decoded;
                }
            }
            if (is_string($response)) {
                $decoded = json_decode($response, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $response = $decoded;
                }
            }

            $logs[] = [
                'id'          => (int) $row['id'],
                'time'        => $row['time'],
                'order_id'    => (int) $row['order_id'],
                'http_status' => (int) $row['http_status'],
                'error'       => $row['error'] ?: null,
                'payload'     => Redaction::redact_data($payload),
                'response'    => Redaction::redact_data($response),
            ];
        }

        return $logs;
    }

    /**
     * Fetch Meta CAPI WC log entries safely with memory protection.
     *
     * @param int $limit
     * @return array
     */
    private function fetch_meta_wc_file_logs($limit = 30) {
        $log_dir = defined('WC_LOG_DIR') ? WC_LOG_DIR : WP_CONTENT_DIR . '/uploads/wc-logs/';
        if (!is_dir($log_dir)) {
            return [];
        }

        // Find wfbt-server-side-*.log files
        $files = glob($log_dir . 'wfbt-server-side-*.log');
        if (empty($files)) {
            return [];
        }

        // Pick the latest log file
        usort($files, function ($a, $b) {
            return filemtime($b) - filemtime($a);
        });

        $latest_file = $files[0];
        if (!is_readable($latest_file)) {
            return [];
        }

        // Tail the file defensively
        $lines = $this->tail_file($latest_file, $limit);

        $parsed = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Redact customer emails or tokens from log line
            $safe_line = Redaction::redact_string($line, true);

            $parsed[] = [
                'file'    => basename($latest_file),
                'message' => $safe_line,
                'is_error'=> (stripos($line, 'error') !== false || stripos($line, 'failed') !== false || stripos($line, '400') !== false),
            ];
        }

        return $parsed;
    }

    /**
     * Tail lines from a file with fseek memory protection.
     *
     * @param string $filepath
     * @param int $lines
     * @return array
     */
    private function tail_file($filepath, $lines = 30) {
        $f = @fopen($filepath, 'rb');
        if (!$f) {
            return [];
        }

        $buffer_size = 4096;
        $output      = '';
        $line_count  = 0;

        fseek($f, 0, SEEK_END);
        $pos = ftell($f);

        while ($pos > 0 && $line_count <= $lines) {
            $read_size = min($pos, $buffer_size);
            $pos -= $read_size;
            fseek($f, $pos);
            $chunk = fread($f, $read_size);
            $output = $chunk . $output;
            $line_count = substr_count($output, "\n");
        }
        fclose($f);

        $all_lines = explode("\n", $output);
        return array_slice($all_lines, -$lines);
    }

    /**
     * Generate prioritized actionable recommendations based on alerts.
     *
     * @param array $alerts
     * @return array
     */
    private function generate_recommendations($alerts) {
        $recommendations = [];

        foreach ($alerts as $alert) {
            if (!empty($alert['recommendation'])) {
                $priority = ($alert['level'] === 'critical') ? 'high' : (($alert['level'] === 'warning') ? 'medium' : 'low');
                $recommendations[] = [
                    'priority'       => $priority,
                    'code'           => $alert['code'],
                    'recommendation' => $alert['recommendation'],
                ];
            }
        }

        // Sort by priority high -> medium -> low
        usort($recommendations, function ($a, $b) {
            $weights = ['high' => 3, 'medium' => 2, 'low' => 1];
            return ($weights[$b['priority']] ?? 0) - ($weights[$a['priority']] ?? 0);
        });

        return $recommendations;
    }
}
