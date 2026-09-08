<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Redaction;

class Pmpro_Controller extends Rest_Controller {

    /**
     * Register REST API routes for Paid Memberships Pro.
     */
    public function register_routes() {
        // GET /pmpro/levels (Lists all membership levels with duration rules and active counts)
        register_rest_route(self::NAMESPACE, '/pmpro/levels', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_levels'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'pmpro');
            },
        ]);

        // GET /pmpro/members (Lists membership records from pmpro_memberships_users)
        register_rest_route(self::NAMESPACE, '/pmpro/members', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_members'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'pmpro');
            },
            'args'                => [
                'status'   => [
                    'default'           => 'active',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'level_id' => [
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ],
                'search'   => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'user_id'  => [
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
                'page'     => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // GET /pmpro/member/{user_id} (Full membership and order diagnosis for a specific member)
        register_rest_route(self::NAMESPACE, '/pmpro/member/(?P<user_id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_member_diagnostic'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'pmpro');
            },
            'args'                => [
                'user_id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);
    }

    /**
     * Check if Paid Memberships Pro is active or its tables exist.
     *
     * @return bool
     */
    protected function is_pmpro_active() {
        global $wpdb;
        $table_levels = $wpdb->prefix . 'pmpro_membership_levels';
        $table_users  = $wpdb->prefix . 'pmpro_memberships_users';

        if (function_exists('pmpro_getAllLevels') || class_exists('PMPro_Member')) {
            return true;
        }

        // Defensive database table check
        return $wpdb->get_var("SHOW TABLES LIKE '{$table_levels}'") === $table_levels
            && $wpdb->get_var("SHOW TABLES LIKE '{$table_users}'") === $table_users;
    }

    /**
     * Mask username for privacy/GDPR compliance.
     *
     * @param string $login
     * @return string
     */
    protected static function mask_login($login) {
        if (empty($login) || !is_string($login)) {
            return '';
        }
        $len = mb_strlen($login);
        if ($len <= 2) {
            return mb_substr($login, 0, 1) . '***';
        }
        return mb_substr($login, 0, 1) . '***' . mb_substr($login, -1);
    }

    /**
     * Mask display name for privacy/GDPR compliance.
     *
     * @param string $name
     * @return string
     */
    protected static function mask_name($name) {
        if (empty($name) || !is_string($name)) {
            return '';
        }
        $trimmed = trim($name);
        if (mb_strlen($trimmed) <= 1) {
            return $trimmed . '***';
        }
        return mb_substr($trimmed, 0, 1) . '***';
    }

    /**
     * Mask email address for GDPR/PII protection.
     *
     * @param string $email
     * @return string
     */
    protected static function mask_email($email) {
        if (empty($email) || !is_string($email)) {
            return '';
        }
        $email = trim($email);
        $parts = explode('@', $email, 2);
        if (count($parts) !== 2) {
            return '***';
        }
        $name   = $parts[0];
        $domain = $parts[1];
        $len    = mb_strlen($name);
        if ($len <= 1) {
            $masked_name = $name . '***';
        } elseif ($len === 2) {
            $masked_name = mb_substr($name, 0, 1) . '***';
        } else {
            $masked_name = mb_substr($name, 0, 1) . '***' . mb_substr($name, -1);
        }
        return $masked_name . '@' . $domain;
    }

    /**
     * GET /pmpro/levels
     * Lists all membership levels configured in PMPro.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_levels(\WP_REST_Request $request) {
        if (!$this->is_pmpro_active()) {
            return $this->error('pmpro_not_active', esc_html__('Paid Memberships Pro is not installed or active on this site.', 'woo-get-data-for-ai'), 404);
        }

        global $wpdb;

        $levels_table = $wpdb->prefix . 'pmpro_membership_levels';
        $users_table  = $wpdb->prefix . 'pmpro_memberships_users';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$levels_table}'") !== $levels_table) {
            return $this->error('pmpro_table_missing', esc_html__('PMPro membership levels table not found.', 'woo-get-data-for-ai'), 404);
        }

        // Fetch all levels
        $raw_levels = $wpdb->get_results("
            SELECT 
                id,
                name,
                description,
                confirmation,
                initial_payment,
                billing_amount,
                cycle_number,
                cycle_period,
                billing_limit,
                trial_amount,
                trial_limit,
                allow_signups,
                expiration_number,
                expiration_period
            FROM {$levels_table}
            ORDER BY id ASC
        ", ARRAY_A);

        // Compute active members counts in single batch query (zero N+1)
        $active_counts_map = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '{$users_table}'") === $users_table) {
            $counts_rows = $wpdb->get_results("
                SELECT membership_id, COUNT(*) as cnt
                FROM {$users_table}
                WHERE status = 'active'
                GROUP BY membership_id
            ", ARRAY_A);
            foreach ($counts_rows as $c_row) {
                $active_counts_map[(int) $c_row['membership_id']] = (int) $c_row['cnt'];
            }
        }

        $levels = [];
        foreach ($raw_levels as $l) {
            $level_id          = (int) $l['id'];
            $exp_num           = (int) $l['expiration_number'];
            $exp_period        = (string) $l['expiration_period'];
            $cycle_num         = (int) $l['cycle_number'];
            $cycle_period      = (string) $l['cycle_period'];
            $active_count      = $active_counts_map[$level_id] ?? 0;

            // Anomaly detection for duration (e.g. 11 months vs 12 months for annual memberships)
            $anomaly_flags = [];
            $name_lower = strtolower($l['name']);
            if ((strpos($name_lower, 'an') !== false || strpos($name_lower, 'year') !== false) && $exp_num === 11 && strtolower($exp_period) === 'month') {
                $anomaly_flags[] = esc_html__('Potential duration anomaly: Level appears to be annual, but expiration_number is set to 11 Months instead of 12.', 'woo-get-data-for-ai');
            }

            // Duration human summary
            $duration_summary = esc_html__('Never expires', 'woo-get-data-for-ai');
            if ($exp_num > 0) {
                $duration_summary = sprintf(
                    /* translators: 1: Number of units, 2: Period name (Day/Month/Year) */
                    esc_html__('%1$d %2$s(s)', 'woo-get-data-for-ai'),
                    $exp_num,
                    $exp_period
                );
            }

            // Recurring human summary
            $recurring_summary = esc_html__('One-time payment', 'woo-get-data-for-ai');
            if ($cycle_num > 0) {
                $recurring_summary = sprintf(
                    /* translators: 1: Billing amount, 2: Cycle number, 3: Cycle period */
                    esc_html__('%1$s every %2$d %3$s(s)', 'woo-get-data-for-ai'),
                    number_format((float) $l['billing_amount'], 2, '.', ''),
                    $cycle_num,
                    $cycle_period
                );
            }

            $levels[] = [
                'id'                   => $level_id,
                'name'                 => $l['name'],
                'description'          => wp_strip_all_tags($l['description']),
                'initial_payment'      => number_format((float) $l['initial_payment'], 2, '.', ''),
                'billing_amount'       => number_format((float) $l['billing_amount'], 2, '.', ''),
                'cycle_number'         => $cycle_num,
                'cycle_period'         => $cycle_period,
                'billing_limit'        => (int) $l['billing_limit'],
                'trial_amount'         => number_format((float) $l['trial_amount'], 2, '.', ''),
                'trial_limit'          => (int) $l['trial_limit'],
                'allow_signups'        => (bool) $l['allow_signups'],
                'expiration_number'    => $exp_num,
                'expiration_period'    => $exp_period,
                'has_expiration'       => ($exp_num > 0),
                'duration_summary'     => $duration_summary,
                'is_recurring'         => ($cycle_num > 0),
                'recurring_summary'    => $recurring_summary,
                'active_members_count' => $active_count,
                'anomaly_flags'        => $anomaly_flags,
            ];
        }

        return $this->response([
            'total_levels' => count($levels),
            'levels'       => $levels,
        ]);
    }

    /**
     * GET /pmpro/members
     * Lists membership rows from wp_pmpro_memberships_users with filters and PII masking.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_members(\WP_REST_Request $request) {
        if (!$this->is_pmpro_active()) {
            return $this->error('pmpro_not_active', esc_html__('Paid Memberships Pro is not installed or active on this site.', 'woo-get-data-for-ai'), 404);
        }

        global $wpdb;

        $users_table  = $wpdb->prefix . 'pmpro_memberships_users';
        $levels_table = $wpdb->prefix . 'pmpro_membership_levels';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$users_table}'") !== $users_table) {
            return $this->error('pmpro_table_missing', esc_html__('PMPro memberships users table not found.', 'woo-get-data-for-ai'), 404);
        }

        $status_param = sanitize_text_field($request->get_param('status') ?: 'active');
        $level_id     = (int) $request->get_param('level_id');
        $user_id      = (int) $request->get_param('user_id');
        $search       = sanitize_text_field($request->get_param('search') ?: '');
        $per_page     = min(100, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $page         = max(1, (int) ($request->get_param('page') ?: 1));
        $offset       = ($page - 1) * $per_page;

        $where_clauses = ['1=1'];
        $query_params  = [];

        // Status filter
        if ($status_param !== 'all') {
            $statuses = array_map('trim', explode(',', $status_param));
            $placeholders = implode(',', array_fill(0, count($statuses), '%s'));
            $where_clauses[] = "mu.status IN ({$placeholders})";
            foreach ($statuses as $st) {
                $query_params[] = $st;
            }
        }

        // Level ID filter
        if ($level_id > 0) {
            $where_clauses[] = 'mu.membership_id = %d';
            $query_params[]  = $level_id;
        }

        // Specific User ID filter
        if ($user_id > 0) {
            $where_clauses[] = 'mu.user_id = %d';
            $query_params[]  = $user_id;
        }

        // Search in login, email, display_name
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_clauses[] = '(u.user_login LIKE %s OR u.user_email LIKE %s OR u.display_name LIKE %s)';
            $query_params[]  = $search_like;
            $query_params[]  = $search_like;
            $query_params[]  = $search_like;
        }

        $where_sql = implode(' AND ', $where_clauses);

        // Count total matching records
        $count_sql = "
            SELECT COUNT(*) 
            FROM {$users_table} mu
            LEFT JOIN {$wpdb->users} u ON mu.user_id = u.ID
            WHERE {$where_sql}
        ";
        $total = (int) (!empty($query_params) ? $wpdb->get_var($wpdb->prepare($count_sql, $query_params)) : $wpdb->get_var($count_sql));
        $total_pages = $total > 0 ? (int) ceil($total / $per_page) : 0;

        // Fetch records
        $query_sql = "
            SELECT 
                mu.id,
                mu.user_id,
                mu.membership_id,
                mu.initial_payment,
                mu.billing_amount,
                mu.cycle_number,
                mu.cycle_period,
                mu.status,
                mu.startdate,
                mu.enddate,
                mu.modified,
                ml.name as level_name,
                u.user_login,
                u.user_email,
                u.display_name
            FROM {$users_table} mu
            LEFT JOIN {$levels_table} ml ON mu.membership_id = ml.id
            LEFT JOIN {$wpdb->users} u ON mu.user_id = u.ID
            WHERE {$where_sql}
            ORDER BY mu.id DESC
            LIMIT %d OFFSET %d
        ";
        $paginated_params   = array_merge($query_params, [$per_page, $offset]);
        $rows               = $wpdb->get_results($wpdb->prepare($query_sql, $paginated_params), ARRAY_A);

        $now = current_time('timestamp');
        $members = [];

        foreach ($rows as $row) {
            $enddate_ts = !empty($row['enddate']) && $row['enddate'] !== '0000-00-00 00:00:00' ? strtotime($row['enddate']) : null;
            $is_expired = false;
            $days_left  = null;

            if ($enddate_ts !== null) {
                $is_expired = ($enddate_ts < $now);
                $days_left  = (int) round(($enddate_ts - $now) / DAY_IN_SECONDS);
            }

            $members[] = [
                'id'              => (int) $row['id'],
                'user_id'         => (int) $row['user_id'],
                'user_login'      => self::mask_login($row['user_login'] ?? ''),
                'user_email'      => self::mask_email($row['user_email'] ?? ''),
                'display_name'    => self::mask_name($row['display_name'] ?? ''),
                'membership_id'   => (int) $row['membership_id'],
                'level_name'      => $row['level_name'] ?? sprintf(esc_html__('Level #%d', 'woo-get-data-for-ai'), (int) $row['membership_id']),
                'status'          => $row['status'],
                'startdate'       => $row['startdate'],
                'enddate'         => (!empty($row['enddate']) && $row['enddate'] !== '0000-00-00 00:00:00') ? $row['enddate'] : null,
                'modified'        => $row['modified'],
                'is_expired'      => $is_expired,
                'days_left'       => $days_left,
                'initial_payment' => number_format((float) $row['initial_payment'], 2, '.', ''),
                'billing_amount'  => number_format((float) $row['billing_amount'], 2, '.', ''),
                'cycle_number'    => (int) $row['cycle_number'],
                'cycle_period'    => $row['cycle_period'],
            ];
        }

        return $this->response([
            'total'       => $total,
            'total_pages' => $total_pages,
            'page'        => $page,
            'per_page'    => $per_page,
            'filters'     => [
                'status'   => $status_param,
                'level_id' => $level_id,
                'user_id'  => $user_id,
                'search'   => $search,
            ],
            'count'       => count($members),
            'members'     => $members,
        ]);
    }

    /**
     * GET /pmpro/member/{user_id}
     * Comprehensive diagnostic of a specific member's active access, history, orders, and duration anomalies.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_member_diagnostic(\WP_REST_Request $request) {
        if (!$this->is_pmpro_active()) {
            return $this->error('pmpro_not_active', esc_html__('Paid Memberships Pro is not installed or active on this site.', 'woo-get-data-for-ai'), 404);
        }

        global $wpdb;

        $user_id = (int) $request->get_param('user_id');
        $user    = get_userdata($user_id);

        if (!$user) {
            return $this->error('user_not_found', esc_html__('WordPress user not found.', 'woo-get-data-for-ai'), 404);
        }

        $users_table  = $wpdb->prefix . 'pmpro_memberships_users';
        $levels_table = $wpdb->prefix . 'pmpro_membership_levels';
        $orders_table = $wpdb->prefix . 'pmpro_membership_orders';

        $now = current_time('timestamp');

        // 1. All membership history records for this user
        $history_rows = $wpdb->get_results($wpdb->prepare("
            SELECT 
                mu.id,
                mu.membership_id,
                mu.initial_payment,
                mu.billing_amount,
                mu.cycle_number,
                mu.cycle_period,
                mu.status,
                mu.startdate,
                mu.enddate,
                mu.modified,
                ml.name as level_name,
                ml.expiration_number,
                ml.expiration_period
            FROM {$users_table} mu
            LEFT JOIN {$levels_table} ml ON mu.membership_id = ml.id
            WHERE mu.user_id = %d
            ORDER BY mu.id DESC
        ", $user_id), ARRAY_A);

        $active_memberships = [];
        $membership_history = [];
        $anomalies          = [];

        foreach ($history_rows as $row) {
            $enddate_ts = !empty($row['enddate']) && $row['enddate'] !== '0000-00-00 00:00:00' ? strtotime($row['enddate']) : null;
            $is_expired = false;
            $days_left  = null;

            if ($enddate_ts !== null) {
                $is_expired = ($enddate_ts < $now);
                $days_left  = (int) round(($enddate_ts - $now) / DAY_IN_SECONDS);
            }

            $item = [
                'id'                   => (int) $row['id'],
                'membership_id'        => (int) $row['membership_id'],
                'level_name'           => $row['level_name'] ?? sprintf(esc_html__('Level #%d', 'woo-get-data-for-ai'), (int) $row['membership_id']),
                'status'               => $row['status'],
                'startdate'            => $row['startdate'],
                'enddate'              => (!empty($row['enddate']) && $row['enddate'] !== '0000-00-00 00:00:00') ? $row['enddate'] : null,
                'modified'             => $row['modified'],
                'is_expired'           => $is_expired,
                'days_left'            => $days_left,
                'initial_payment'      => number_format((float) $row['initial_payment'], 2, '.', ''),
                'billing_amount'       => number_format((float) $row['billing_amount'], 2, '.', ''),
                'cycle_number'         => (int) $row['cycle_number'],
                'cycle_period'         => $row['cycle_period'],
                'expiration_number'    => (int) ($row['expiration_number'] ?? 0),
                'expiration_period'    => $row['expiration_period'] ?? '',
            ];

            if ($row['status'] === 'active') {
                $active_memberships[] = $item;

                // Anomaly check on active record
                if ($is_expired) {
                    $anomalies[] = sprintf(
                        /* translators: 1: Level name, 2: Row ID, 3: End date */
                        esc_html__('Anomaly detected: Membership "%1$s" (Row #%2$d) is flagged as status="active" in database, but its enddate (%3$s) has already passed.', 'woo-get-data-for-ai'),
                        $item['level_name'],
                        $item['id'],
                        $item['enddate']
                    );
                }
            }

            $membership_history[] = $item;
        }

        // 2. Recent PMPro orders for this user
        $orders = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '{$orders_table}'") === $orders_table) {
            $order_rows = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    mo.id,
                    mo.code,
                    mo.membership_id,
                    mo.subtotal,
                    mo.tax,
                    mo.total,
                    mo.status,
                    mo.gateway,
                    mo.gateway_environment,
                    mo.payment_transaction_id,
                    mo.subscription_transaction_id,
                    mo.timestamp,
                    mo.notes,
                    ml.name as level_name
                FROM {$orders_table} mo
                LEFT JOIN {$levels_table} ml ON mo.membership_id = ml.id
                WHERE mo.user_id = %d
                ORDER BY mo.id DESC
                LIMIT 20
            ", $user_id), ARRAY_A);

            foreach ($order_rows as $ord) {
                $orders[] = [
                    'id'                          => (int) $ord['id'],
                    'code'                        => $ord['code'],
                    'membership_id'               => (int) $ord['membership_id'],
                    'level_name'                  => $ord['level_name'] ?? sprintf(esc_html__('Level #%d', 'woo-get-data-for-ai'), (int) $ord['membership_id']),
                    'total'                       => number_format((float) $ord['total'], 2, '.', ''),
                    'subtotal'                    => number_format((float) $ord['subtotal'], 2, '.', ''),
                    'tax'                         => number_format((float) $ord['tax'], 2, '.', ''),
                    'status'                      => $ord['status'],
                    'gateway'                     => $ord['gateway'],
                    'gateway_environment'         => $ord['gateway_environment'],
                    'payment_transaction_id'      => Redaction::redact_string($ord['payment_transaction_id']),
                    'subscription_transaction_id' => Redaction::redact_string($ord['subscription_transaction_id']),
                    'timestamp'                   => $ord['timestamp'],
                    'notes'                       => Redaction::redact_string($ord['notes']),
                ];
            }
        }

        // Access status diagnostic
        $has_active = !empty($active_memberships);
        $access_state = 'no_membership';
        if ($has_active) {
            $access_state = 'active';
            foreach ($active_memberships as $am) {
                if ($am['is_expired']) {
                    $access_state = 'expired';
                }
            }
        } elseif (!empty($membership_history)) {
            $latest = $membership_history[0];
            $access_state = $latest['status'];
        }

        return $this->response([
            'user' => [
                'id'              => $user_id,
                'user_login'      => self::mask_login($user->user_login),
                'user_email'      => self::mask_email($user->user_email),
                'display_name'    => self::mask_name($user->display_name),
                'user_registered' => $user->user_registered,
                'roles'           => (array) $user->roles,
            ],
            'diagnostics' => [
                'has_active_membership' => $has_active,
                'access_state'          => $access_state,
                'active_levels_count'   => count($active_memberships),
                'anomalies_detected'    => count($anomalies),
                'anomalies'             => $anomalies,
            ],
            'active_memberships'  => $active_memberships,
            'membership_history'  => $membership_history,
            'orders'              => $orders,
        ]);
    }
}
