<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

class Scheduler_Controller extends Rest_Controller {

    /**
     * Register routes for Scheduler_Controller.
     */
    public function register_routes() {
        // GET /crons (WP-Cron registry, next run times, intervals, overdue jobs)
        register_rest_route(self::NAMESPACE, '/crons', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_crons'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'scheduler');
            },
            'args'                => [
                'status' => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit'  => [
                    'default'           => 100,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // GET /action-scheduler (Action Scheduler queue: in-progress, failed, pending)
        register_rest_route(self::NAMESPACE, '/action-scheduler', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_action_scheduler'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'scheduler');
            },
            'args'                => [
                'status'   => [
                    'default'           => 'in-progress,failed,pending',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'hook'     => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search'   => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'group'    => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'per_page' => [
                    'default'           => 50,
                    'sanitize_callback' => 'absint',
                ],
                'page'     => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * Get registered WP-Cron jobs.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_crons(\WP_REST_Request $request) {
        $status_filter = strtolower(sanitize_text_field($request->get_param('status') ?: 'all'));
        $search        = strtolower(sanitize_text_field($request->get_param('search') ?: ''));
        $limit         = min(500, max(1, (int) ($request->get_param('limit') ?: 100)));

        $cron_array = _get_cron_array();
        $schedules  = wp_get_schedules();
        $now        = time();

        $items         = [];
        $total_crons   = 0;
        $overdue_count = 0;

        if (is_array($cron_array)) {
            foreach ($cron_array as $timestamp => $hooks) {
                if (!is_array($hooks)) {
                    continue;
                }

                $timestamp    = (int) $timestamp;
                $is_overdue   = ($timestamp < $now);
                $diff_seconds = $timestamp - $now;

                foreach ($hooks as $hook_name => $events) {
                    if (!is_array($events)) {
                        continue;
                    }

                    foreach ($events as $event_key => $event) {
                        $total_crons++;

                        if ($is_overdue) {
                            $overdue_count++;
                        }

                        // Filter by search
                        if (!empty($search) && strpos(strtolower($hook_name), $search) === false) {
                            continue;
                        }

                        // Filter by status
                        if ($status_filter === 'overdue' && !$is_overdue) {
                            continue;
                        }
                        if ($status_filter === 'future' && $is_overdue) {
                            continue;
                        }

                        $schedule_slug = !empty($event['schedule']) ? $event['schedule'] : false;
                        $schedule_name = $schedule_slug && isset($schedules[$schedule_slug]['display'])
                            ? $schedules[$schedule_slug]['display']
                            : ($schedule_slug ? ucfirst(str_replace('_', ' ', $schedule_slug)) : 'Single event');

                        $interval = !empty($event['interval']) ? (int) $event['interval'] : null;
                        if (!$interval && $schedule_slug && isset($schedules[$schedule_slug]['interval'])) {
                            $interval = (int) $schedules[$schedule_slug]['interval'];
                        }

                        $human_diff = $diff_seconds >= 0
                            ? sprintf(esc_html__('in %s', 'woo-get-data-for-ai'), human_time_diff($now, $timestamp))
                            : sprintf(esc_html__('%s ago (OVERDUE)', 'woo-get-data-for-ai'), human_time_diff($timestamp, $now));

                        $items[] = [
                            'hook'               => $hook_name,
                            'schedule'           => $schedule_slug,
                            'schedule_name'      => $schedule_name,
                            'interval_seconds'   => $interval,
                            'interval_human'     => $interval ? human_time_diff(0, $interval) : null,
                            'next_run_timestamp' => $timestamp,
                            'next_run_gmt'       => gmdate('c', $timestamp),
                            'next_run_local'     => get_date_from_gmt(gmdate('Y-m-d H:i:s', $timestamp), 'c'),
                            'human_diff'         => $human_diff,
                            'is_overdue'         => $is_overdue,
                            'diff_seconds'       => $diff_seconds,
                            'args'               => !empty($event['args']) ? $event['args'] : [],
                            'event_key'          => $event_key,
                        ];
                    }
                }
            }
        }

        // Sort crons chronologically by next_run_timestamp ASC
        usort($items, function ($a, $b) {
            if ($a['next_run_timestamp'] === $b['next_run_timestamp']) {
                return strcmp($a['hook'], $b['hook']);
            }
            return ($a['next_run_timestamp'] < $b['next_run_timestamp']) ? -1 : 1;
        });

        $sliced_items = array_slice($items, 0, $limit);

        return $this->response([
            'cron_system' => [
                'cron_enabled'      => !(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON),
                'alternate_wp_cron' => defined('ALTERNATE_WP_CRON') && ALTERNATE_WP_CRON,
                'server_timestamp'  => $now,
                'server_time_gmt'   => gmdate('c', $now),
                'server_time_local' => current_time('c'),
                'timezone'          => wp_timezone_string(),
            ],
            'summary'     => [
                'total_registered' => $total_crons,
                'overdue_count'    => $overdue_count,
                'returned_count'   => count($sliced_items),
                'limit'            => $limit,
                'status_filter'    => $status_filter,
                'search'           => $search ?: null,
            ],
            'crons'       => $sliced_items,
        ]);
    }

    /**
     * Get Action Scheduler actions and queue status.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_action_scheduler(\WP_REST_Request $request) {
        global $wpdb;

        $actions_table = $wpdb->prefix . 'actionscheduler_actions';
        $groups_table  = $wpdb->prefix . 'actionscheduler_groups';
        $logs_table    = $wpdb->prefix . 'actionscheduler_logs';

        // Check if Action Scheduler tables exist
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $actions_table)) === $actions_table;
        if (!$table_exists) {
            return $this->response([
                'action_scheduler_installed' => false,
                'message' => esc_html__('Action Scheduler tables not found in database.', 'woo-get-data-for-ai'),
            ]);
        }

        // Summary counts per status
        $status_counts_raw = $wpdb->get_results("SELECT status, COUNT(*) as count FROM `{$actions_table}` GROUP BY status", ARRAY_A);
        $summary = [
            'pending'     => 0,
            'in-progress' => 0,
            'failed'      => 0,
            'complete'    => 0,
            'canceled'    => 0,
            'total'       => 0,
        ];
        if ($status_counts_raw) {
            foreach ($status_counts_raw as $row) {
                $st = $row['status'];
                $cnt = (int) $row['count'];
                $summary[$st] = $cnt;
                $summary['total'] += $cnt;
            }
        }

        // Parse query filters
        $status_param = sanitize_text_field($request->get_param('status') ?: 'in-progress,failed,pending');
        $hook_param   = sanitize_text_field($request->get_param('hook') ?: '');
        $search_param = sanitize_text_field($request->get_param('search') ?: '');
        $group_param  = sanitize_text_field($request->get_param('group') ?: '');
        $per_page     = min(100, max(1, (int) ($request->get_param('per_page') ?: 50)));
        $page         = max(1, (int) ($request->get_param('page') ?: 1));
        $offset       = ($page - 1) * $per_page;

        $where = ['1=1'];
        $query_params = [];

        // Status filter
        if ($status_param !== 'all') {
            $allowed_statuses = ['pending', 'in-progress', 'complete', 'failed', 'canceled'];
            $requested_statuses = array_filter(array_map('trim', explode(',', $status_param)));
            $valid_statuses = array_values(array_intersect($requested_statuses, $allowed_statuses));

            if (!empty($valid_statuses)) {
                $placeholders = implode(',', array_fill(0, count($valid_statuses), '%s'));
                $where[] = "a.status IN ($placeholders)";
                foreach ($valid_statuses as $st) {
                    $query_params[] = $st;
                }
            }
        }

        // Hook exact filter
        if (!empty($hook_param)) {
            $where[] = "a.hook = %s";
            $query_params[] = $hook_param;
        }

        // Hook substring search
        if (!empty($search_param)) {
            $where[] = "a.hook LIKE %s";
            $query_params[] = '%' . $wpdb->esc_like($search_param) . '%';
        }

        // Group filter
        if (!empty($group_param)) {
            $where[] = "g.slug = %s";
            $query_params[] = $group_param;
        }

        $where_sql = implode(' AND ', $where);

        // Count total matching
        $count_sql = "SELECT COUNT(*) FROM `{$actions_table}` a LEFT JOIN `{$groups_table}` g ON a.group_id = g.group_id WHERE {$where_sql}";
        $total_matching = !empty($query_params)
            ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $query_params))
            : (int) $wpdb->get_var($count_sql);

        // Query actions with priority to failed & in-progress
        $items_sql = "SELECT a.action_id, a.hook, a.status, a.scheduled_date_gmt, a.scheduled_date_local, 
                             a.args, a.schedule, a.group_id, a.attempts, a.last_attempt_gmt, 
                             a.last_attempt_local, a.claim_id, g.slug as group_slug 
                      FROM `{$actions_table}` a 
                      LEFT JOIN `{$groups_table}` g ON a.group_id = g.group_id 
                      WHERE {$where_sql} 
                      ORDER BY CASE 
                          WHEN a.status = 'failed' THEN 1 
                          WHEN a.status = 'in-progress' THEN 2 
                          WHEN a.status = 'pending' THEN 3 
                          ELSE 4 
                      END ASC, a.scheduled_date_gmt DESC 
                      LIMIT %d OFFSET %d";

        $paging_params = array_merge($query_params, [$per_page, $offset]);
        $actions_rows = $wpdb->get_results($wpdb->prepare($items_sql, $paging_params), ARRAY_A);

        // Fetch logs for returned actions (especially failed ones)
        $action_ids = !empty($actions_rows) ? array_column($actions_rows, 'action_id') : [];
        $logs_by_action = [];

        $has_logs_table = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $logs_table)) === $logs_table;
        if ($has_logs_table && !empty($action_ids)) {
            $id_placeholders = implode(',', array_fill(0, count($action_ids), '%d'));
            $logs_query = "SELECT action_id, message, log_date_gmt 
                           FROM `{$logs_table}` 
                           WHERE action_id IN ($id_placeholders) 
                           ORDER BY log_date_gmt DESC";
            $logs_raw = $wpdb->get_results($wpdb->prepare($logs_query, $action_ids), ARRAY_A);
            if ($logs_raw) {
                foreach ($logs_raw as $log_row) {
                    $act_id = (int) $log_row['action_id'];
                    if (!isset($logs_by_action[$act_id])) {
                        $logs_by_action[$act_id] = [];
                    }
                    if (count($logs_by_action[$act_id]) < 5) {
                        $logs_by_action[$act_id][] = [
                            'message'  => $log_row['message'],
                            'date_gmt' => $log_row['log_date_gmt'],
                        ];
                    }
                }
            }
        }

        // Format action items
        $actions = [];
        if ($actions_rows) {
            foreach ($actions_rows as $row) {
                $act_id = (int) $row['action_id'];

                // Decode args safely
                $args_raw = $row['args'];
                $args = null;
                if (!empty($args_raw)) {
                    $args = json_decode($args_raw, true);
                    if ($args === null) {
                        $args = maybe_unserialize($args_raw);
                    }
                }

                // Check schedule recurrence
                $schedule_raw = $row['schedule'];
                $recurrence = null;
                if (!empty($schedule_raw)) {
                    $schedule_obj = maybe_unserialize($schedule_raw);
                    if (is_object($schedule_obj) && method_exists($schedule_obj, 'get_recurrence')) {
                        $recurrence = $schedule_obj->get_recurrence();
                    }
                }

                $actions[] = [
                    'action_id'             => $act_id,
                    'hook'                  => $row['hook'],
                    'status'                => $row['status'],
                    'group'                 => $row['group_slug'] ?: null,
                    'scheduled_date_gmt'    => $row['scheduled_date_gmt'],
                    'scheduled_date_local'  => $row['scheduled_date_local'],
                    'last_attempt_gmt'      => $row['last_attempt_gmt'],
                    'last_attempt_local'    => $row['last_attempt_local'],
                    'attempts'              => (int) $row['attempts'],
                    'claim_id'              => (int) $row['claim_id'],
                    'recurrence'            => $recurrence,
                    'args'                  => $args,
                    'logs'                  => isset($logs_by_action[$act_id]) ? $logs_by_action[$act_id] : [],
                ];
            }
        }

        $total_pages = $per_page > 0 ? (int) ceil($total_matching / $per_page) : 1;

        return $this->response([
            'action_scheduler_installed' => true,
            'summary'                    => $summary,
            'retention'                  => self::get_retention_policy($summary['complete'] ?? 0),
            'total_matching'             => $total_matching,
            'page'                       => $page,
            'per_page'                   => $per_page,
            'total_pages'                => $total_pages,
            'actions'                    => $actions,
        ]);
    }

    /**
     * Retrieve Action Scheduler retention policy, batch size and bloat assessment.
     *
     * @param int $complete_count
     * @return array
     */
    public static function get_retention_policy($complete_count = 0) {
        $default_retention_seconds = 30 * (defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400);
        $retention_seconds = (int) apply_filters('action_scheduler_retention_period', $default_retention_seconds);
        $day_sec = defined('DAY_IN_SECONDS') ? DAY_IN_SECONDS : 86400;
        $retention_days    = round($retention_seconds / $day_sec);
        $cleanup_batch_size= (int) apply_filters('action_scheduler_cleanup_batch_size', 20);
        $is_default        = ($retention_seconds === $default_retention_seconds);
        $alert_bloat       = ($is_default && $complete_count > 25000);

        $recommendation = null;
        if ($alert_bloat) {
            $recommendation = sprintf(
                'More than %s completed actions stored with default %d-day retention period. Consider reducing retention to 7 days via action_scheduler_retention_period filter to significantly reduce database size.',
                function_exists('number_format_i18n') ? number_format_i18n($complete_count) : number_format($complete_count),
                $retention_days
            );
        } elseif ($is_default) {
            $recommendation = sprintf('Default %d-day retention period active.', $retention_days);
        } else {
            $recommendation = sprintf('Custom retention period of %d days active.', $retention_days);
        }

        return [
            'retention_period_days' => (int) $retention_days,
            'retention_seconds'     => $retention_seconds,
            'is_default'            => $is_default,
            'cleanup_batch_size'    => $cleanup_batch_size,
            'alert_bloat'           => $alert_bloat,
            'recommendation'        => $recommendation,
        ];
    }
}
