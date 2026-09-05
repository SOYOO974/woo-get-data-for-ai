<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

class Flowmattic_Controller extends Rest_Controller {

    public function register_routes() {
        // GET /flowmattic/export-all (Bulk export in 1 optimized request)
        register_rest_route(self::NAMESPACE, '/flowmattic/export-all', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_export_all'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'flowmattic');
            },
        ]);

        // GET /flowmattic/workflows (List summaries, status, triggers)
        register_rest_route(self::NAMESPACE, '/flowmattic/workflows', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_workflows'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'flowmattic');
            },
        ]);

        // GET /flowmattic/workflow/{id} (Targeted workflow detail or export format)
        register_rest_route(self::NAMESPACE, '/flowmattic/workflow/(?P<id>[a-zA-Z0-9_-]+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_workflow_by_id'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'flowmattic');
            },
        ]);
    }

    /**
     * Check if the FlowMattic workflows table exists.
     *
     * @return bool
     */
    protected function is_flowmattic_installed() {
        global $wpdb;
        $table = $wpdb->prefix . 'flowmattic_workflows';
        return ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table);
    }

    /**
     * Batch count executed tasks for a list of workflow IDs.
     *
     * @param array $workflow_ids
     * @return array
     */
    protected function get_tasks_counts_batch(array $workflow_ids) {
        global $wpdb;
        $table = $wpdb->prefix . 'flowmattic_tasks';
        if (empty($workflow_ids) || $wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($workflow_ids), '%s'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $sql = $wpdb->prepare(
            "SELECT workflow_id, COUNT(*) as cnt FROM `$table` WHERE workflow_id IN ($placeholders) GROUP BY workflow_id",
            ...$workflow_ids
        );

        $results = $wpdb->get_results($sql, ARRAY_A);
        $counts = [];
        if ($results) {
            foreach ($results as $r) {
                $counts[$r['workflow_id']] = (int) $r['cnt'];
            }
        }
        return $counts;
    }

    /**
     * Single task count for a workflow ID.
     *
     * @param string $workflow_id
     * @return int
     */
    protected function count_tasks_for_workflow($workflow_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'flowmattic_tasks';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            return 0;
        }

        return (int) $wpdb->get_var(
            $wpdb->prepare("SELECT COUNT(*) FROM `$table` WHERE workflow_id = %s", $workflow_id)
        );
    }

    /**
     * Clean workflow steps by unsetting capturedData and stripslashing values,
     * mirroring FlowMattic's native flowmattic_export_workflow() behavior.
     *
     * @param mixed $raw_steps
     * @return array
     */
    protected function clean_workflow_steps($raw_steps) {
        $decoded = is_array($raw_steps) ? $raw_steps : json_decode((string) $raw_steps, true);
        if (!is_array($decoded)) {
            return [];
        }

        // Handle v2 React Builder envelope: { steps: [...], edges: [...] }
        if (isset($decoded['steps']) && is_array($decoded['steps'])) {
            foreach ($decoded['steps'] as $k => $step) {
                if (is_array($step)) {
                    unset($step['capturedData']);
                    foreach ($step as $data_key => $data_value) {
                        if (!is_array($data_value) && is_string($data_value)) {
                            $step[$data_key] = stripslashes($data_value);
                        }
                    }
                    $decoded['steps'][$k] = $step;
                }
            }
        } else {
            // Legacy flat steps format
            foreach ($decoded as $k => $step) {
                if (is_array($step)) {
                    unset($step['capturedData']);
                    foreach ($step as $data_key => $data_value) {
                        if (!is_array($data_value) && is_string($data_value)) {
                            $step[$data_key] = stripslashes($data_value);
                        }
                    }
                    $decoded[$k] = $step;
                }
            }
        }

        return $decoded;
    }

    /**
     * Parse workflow summary metadata for listing.
     *
     * @param array|object $row
     * @param array|null   $tasks_cache
     * @return array
     */
    protected function parse_workflow_summary($row, $tasks_cache = null) {
        $row = (array) $row;
        $workflow_id = (string) ($row['workflow_id'] ?? '');
        $raw_name = (string) ($row['workflow_name'] ?? '');
        $name = rawurldecode($raw_name);

        $settings = !empty($row['workflow_settings']) ? json_decode($row['workflow_settings'], true) : [];
        if (!is_array($settings)) {
            $settings = [];
        }

        $status = $settings['status'] ?? 'off';
        $created_time = $settings['time'] ?? null;

        $decoded_steps = !empty($row['workflow_steps']) ? json_decode($row['workflow_steps'], true) : [];
        if (is_array($decoded_steps) && isset($decoded_steps['steps']) && is_array($decoded_steps['steps'])) {
            $steps = $decoded_steps['steps'];
        } elseif (is_array($decoded_steps)) {
            $steps = $decoded_steps;
        } else {
            $steps = [];
        }

        $trigger = '';
        $trigger_event = '';
        $actions_count = 0;
        $valid_steps = 0;

        foreach ($steps as $step) {
            if (!is_array($step)) {
                continue;
            }
            $type = $step['type'] ?? '';
            if ($type === 'placeholder') {
                continue;
            }
            $valid_steps++;
            if (empty($trigger) && $type === 'trigger') {
                $trigger = $step['application'] ?? ($step['action'] ?? 'unknown');
                $trigger_event = $step['action'] ?? ($step['event'] ?? '');
                continue;
            }
            $actions_count++;
        }

        $task_count = 0;
        if (is_array($tasks_cache) && isset($tasks_cache[$workflow_id])) {
            $task_count = (int) $tasks_cache[$workflow_id];
        } else {
            $task_count = $this->count_tasks_for_workflow($workflow_id);
        }

        return [
            'workflow_id'   => $workflow_id,
            'name'          => $name,
            'status'        => $status,
            'is_active'     => ($status === 'on'),
            'trigger'       => $trigger ?: 'unknown',
            'trigger_event' => $trigger_event,
            'actions_count' => $actions_count,
            'steps_count'   => $valid_steps,
            'task_count'    => $task_count,
            'created_time'  => $created_time,
        ];
    }

    /**
     * Normalize human-readable status parameter ('active'/'inactive'/'on'/'off').
     *
     * @param string $status_param
     * @return string 'all', 'on', or 'off'
     */
    protected function normalize_status_filter($status_param) {
        $clean = strtolower(trim((string) $status_param));
        if (in_array($clean, ['on', 'active', 'enabled', '1'], true)) {
            return 'on';
        }
        if (in_array($clean, ['off', 'inactive', 'disabled', 'draft', '0'], true)) {
            return 'off';
        }
        return 'all';
    }

    /**
     * GET /flowmattic/workflows
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_workflows(\WP_REST_Request $request) {
        if (!$this->is_flowmattic_installed()) {
            return $this->response([
                'flowmattic_installed' => false,
                'total'                => 0,
                'workflows'            => [],
                'message'              => esc_html__('FlowMattic is not active or workflows database table was not found.', 'woo-get-data-for-ai'),
            ]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'flowmattic_workflows';

        $status_filter = $this->normalize_status_filter($request->get_param('status') ?: 'all');
        $search_query  = sanitize_text_field($request->get_param('search') ?: '');
        $limit         = min(max(1, (int) ($request->get_param('limit') ?: 100)), 500);
        $offset        = max(0, (int) ($request->get_param('offset') ?: 0));

        // Fetch rows
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results("SELECT id, workflow_id, workflow_name, workflow_steps, workflow_settings FROM `$table` ORDER BY id DESC", ARRAY_A);

        if (empty($rows)) {
            return $this->response([
                'flowmattic_installed' => true,
                'total'                => 0,
                'active_count'         => 0,
                'inactive_count'       => 0,
                'workflows'            => [],
            ]);
        }

        // Collect all IDs for batch task count query
        $all_ids = array_column($rows, 'workflow_id');
        $tasks_counts = $this->get_tasks_counts_batch($all_ids);

        $active_count   = 0;
        $inactive_count = 0;
        $filtered = [];

        foreach ($rows as $row) {
            $summary = $this->parse_workflow_summary($row, $tasks_counts);

            if ($summary['is_active']) {
                $active_count++;
            } else {
                $inactive_count++;
            }

            // Filter by status if requested ('on' = active, 'off' = inactive)
            if ($status_filter !== 'all' && $summary['status'] !== $status_filter) {
                continue;
            }

            // Filter by search query if requested
            if (!empty($search_query)) {
                $haystack = strtolower($summary['workflow_id'] . ' ' . $summary['name'] . ' ' . $summary['trigger']);
                if (strpos($haystack, strtolower($search_query)) === false) {
                    continue;
                }
            }

            $filtered[] = $summary;
        }

        $total_matched = count($filtered);
        $paginated = array_slice($filtered, $offset, $limit);

        return $this->response([
            'flowmattic_installed' => true,
            'total'                => $total_matched,
            'active_count'         => $active_count,
            'inactive_count'       => $inactive_count,
            'workflows'            => $paginated,
        ]);
    }

    /**
     * GET /flowmattic/workflow/{id}
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_workflow_by_id(\WP_REST_Request $request) {
        $workflow_id = sanitize_text_field($request->get_param('id'));
        $format      = sanitize_text_field($request->get_param('format') ?: 'default');

        if (!$this->is_flowmattic_installed()) {
            return $this->error('flowmattic_not_installed', esc_html__('FlowMattic is not active or workflows table was not found.', 'woo-get-data-for-ai'), 404);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'flowmattic_workflows';

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `$table` WHERE workflow_id = %s", $workflow_id),
            ARRAY_A
        );

        if (!$row) {
            return $this->error('workflow_not_found', sprintf(esc_html__("Workflow '%s' was not found.", 'woo-get-data-for-ai'), $workflow_id), 404);
        }

        $summary = $this->parse_workflow_summary($row);
        $cleaned_steps = $this->clean_workflow_steps($row['workflow_steps'] ?? '');

        $settings = !empty($row['workflow_settings']) ? json_decode($row['workflow_settings'], true) : [];
        if (!is_array($settings)) {
            $settings = [];
        }

        // Build exact FlowMattic native export payload
        $export_payload = [
            'workflow_name'     => $row['workflow_name'] ?? '',
            'workflow_steps'    => $cleaned_steps,
            'workflow_settings' => $settings,
        ];

        // Direct export format (ready for flowmattic-workflow-{id}.json)
        if ($format === 'export' || $format === 'raw') {
            return $this->response($export_payload);
        }

        // Standard rich response envelope
        return $this->response([
            'workflow_id'   => $summary['workflow_id'],
            'workflow_name' => $summary['name'],
            'status'        => $summary['status'],
            'is_active'     => $summary['is_active'],
            'trigger'       => $summary['trigger'],
            'trigger_event' => $summary['trigger_event'],
            'actions_count' => $summary['actions_count'],
            'steps_count'   => $summary['steps_count'],
            'task_count'    => $summary['task_count'],
            'created_time'  => $summary['created_time'],
            'export_data'   => $export_payload,
        ]);
    }

    /**
     * GET /flowmattic/export-all
     *
     * High-performance bulk export of FlowMattic workflows in 1 optimized request.
     * Supports filtering by status (?status=on / ?status=active or ?status=off / ?status=inactive).
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_export_all(\WP_REST_Request $request) {
        if (!$this->is_flowmattic_installed()) {
            return $this->response([
                'flowmattic_installed' => false,
                'total'                => 0,
                'active_count'         => 0,
                'inactive_count'       => 0,
                'count'                => 0,
                'workflows'            => [],
                'message'              => esc_html__('FlowMattic is not active or workflows table was not found.', 'woo-get-data-for-ai'),
            ]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'flowmattic_workflows';

        $status_filter = $this->normalize_status_filter($request->get_param('status') ?: 'all');
        $per_page      = min(max(1, (int) ($request->get_param('per_page') ?: 50)), 100);
        $page          = max(1, (int) ($request->get_param('page') ?: 1));
        $offset        = ($page - 1) * $per_page;

        // Fetch all rows to accurately compute status metrics and support in-memory status filtering
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results("SELECT * FROM `$table` ORDER BY id DESC", ARRAY_A);

        if (empty($rows)) {
            return $this->response([
                'flowmattic_installed' => true,
                'total'                => 0,
                'active_count'         => 0,
                'inactive_count'       => 0,
                'count'                => 0,
                'page'                 => $page,
                'per_page'             => $per_page,
                'total_pages'          => 0,
                'items'                => [],
            ]);
        }

        // Batch task counts
        $workflow_ids = array_column($rows, 'workflow_id');
        $tasks_counts = $this->get_tasks_counts_batch($workflow_ids);

        $active_count   = 0;
        $inactive_count = 0;
        $matched_rows   = [];

        foreach ($rows as $row) {
            $summary = $this->parse_workflow_summary($row, $tasks_counts);

            if ($summary['is_active']) {
                $active_count++;
            } else {
                $inactive_count++;
            }

            // Filter by status if requested ('on' = active, 'off' = inactive)
            if ($status_filter !== 'all' && $summary['status'] !== $status_filter) {
                continue;
            }

            $matched_rows[] = [
                'row'     => $row,
                'summary' => $summary,
            ];
        }

        $total_matched = count($matched_rows);
        $paginated_slice = array_slice($matched_rows, $offset, $per_page);

        $items = [];
        foreach ($paginated_slice as $entry) {
            $row     = $entry['row'];
            $summary = $entry['summary'];

            $cleaned_steps = $this->clean_workflow_steps($row['workflow_steps'] ?? '');
            $settings = !empty($row['workflow_settings']) ? json_decode($row['workflow_settings'], true) : [];
            if (!is_array($settings)) {
                $settings = [];
            }

            $items[] = [
                'workflow_id'   => $summary['workflow_id'],
                'workflow_name' => $summary['name'],
                'status'        => $summary['status'],
                'is_active'     => $summary['is_active'],
                'trigger'       => $summary['trigger'],
                'trigger_event' => $summary['trigger_event'],
                'actions_count' => $summary['actions_count'],
                'steps_count'   => $summary['steps_count'],
                'task_count'    => $summary['task_count'],
                'created_time'  => $summary['created_time'],
                'export_data'   => [
                    'workflow_name'     => $row['workflow_name'] ?? '',
                    'workflow_steps'    => $cleaned_steps,
                    'workflow_settings' => $settings,
                ],
            ];

            // Free runtime cache per row to maintain low memory footprint
            if (function_exists('wp_cache_flush_runtime')) {
                wp_cache_flush_runtime();
            }
        }

        return $this->response([
            'flowmattic_installed' => true,
            'total'                => $total_matched,
            'active_count'         => $active_count,
            'inactive_count'       => $inactive_count,
            'count'                => count($items),
            'page'                 => $page,
            'per_page'             => $per_page,
            'total_pages'          => (int) ceil($total_matched / $per_page),
            'items'                => $items,
        ]);
    }
}
