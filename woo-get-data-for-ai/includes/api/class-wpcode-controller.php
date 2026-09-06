<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

class Wpcode_Controller extends Rest_Controller {

    public function register_routes() {
        // GET /wpcode/snippets
        register_rest_route(self::NAMESPACE, '/wpcode/snippets', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_snippets'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'wpcode');
            },
            'args'                => [
                'status' => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /wpcode/snippet/{id}
        register_rest_route(self::NAMESPACE, '/wpcode/snippet/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_snippet_by_id'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'wpcode');
            },
        ]);
    }

    public function get_snippets(\WP_REST_Request $request) {
        $raw_status = strtolower(trim((string) ($request->get_param('status') ?: 'all')));
        if (in_array($raw_status, ['active', 'publish', 'enabled', '1'], true)) {
            $status_filter = 'active';
        } elseif (in_array($raw_status, ['inactive', 'draft', 'disabled', '0'], true)) {
            $status_filter = 'inactive';
        } else {
            $status_filter = 'all';
        }

        global $wpdb;
        $table_snippets = $wpdb->prefix . 'snippets';
        $has_cs_table   = ($wpdb->get_var("SHOW TABLES LIKE '$table_snippets'") === $table_snippets);

        // 1. Calculate global counts across both WPCode and traditional Code Snippets
        $wpcode_counts = wp_count_posts('wpcode');
        $cpt_active    = isset($wpcode_counts->publish) ? (int) $wpcode_counts->publish : 0;
        $cpt_inactive  = isset($wpcode_counts->draft) ? (int) $wpcode_counts->draft : 0;

        $cs_active   = 0;
        $cs_inactive = 0;
        if ($has_cs_table) {
            $cs_stats = $wpdb->get_row("SELECT SUM(CASE WHEN active = 1 THEN 1 ELSE 0 END) as active_cnt, SUM(CASE WHEN active = 0 THEN 1 ELSE 0 END) as inactive_cnt FROM `$table_snippets`", ARRAY_A);
            if ($cs_stats) {
                $cs_active   = (int) ($cs_stats['active_cnt'] ?? 0);
                $cs_inactive = (int) ($cs_stats['inactive_cnt'] ?? 0);
            }
        }

        $global_active   = $cpt_active + $cs_active;
        $global_inactive = $cpt_inactive + $cs_inactive;
        $global_total    = $global_active + $global_inactive;

        $snippets = [];

        // 2. Fetch WPCode Custom Post Type ('wpcode') matching filter
        if ($status_filter === 'active') {
            $post_status = ['publish'];
        } elseif ($status_filter === 'inactive') {
            $post_status = ['draft'];
        } else {
            $post_status = ['publish', 'draft'];
        }

        $wpcode_posts = get_posts([
            'post_type'      => 'wpcode',
            'post_status'    => $post_status,
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        foreach ($wpcode_posts as $post) {
            $code_type = get_post_meta($post->ID, '_wpcode_snippet_type', true);
            if (empty($code_type)) {
                $code_type = get_post_meta($post->ID, 'wpcode_snippet_type', true) ?: 'php';
            }

            $code = get_post_meta($post->ID, '_wpcode_snippet_code', true);
            if (empty($code)) {
                $code = get_post_meta($post->ID, 'wpcode_snippet_code', true) ?: $post->post_content;
            }

            $location = get_post_meta($post->ID, '_wpcode_snippet_location', true) ?: get_post_meta($post->ID, 'wpcode_snippet_location', true);
            $priority = get_post_meta($post->ID, '_wpcode_snippet_priority', true) ?: get_post_meta($post->ID, 'wpcode_snippet_priority', true);

            $is_active = ($post->post_status === 'publish');

            $snippets[] = [
                'source_plugin' => 'WPCode',
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'status'        => $is_active ? 'active' : 'inactive',
                'is_active'     => $is_active,
                'code_type'     => $code_type,
                'location'      => $location ?: 'site-wide',
                'priority'      => (int) ($priority ?: 10),
                'modified_at'   => get_the_modified_date('c', $post),
                'code'          => $code,
            ];
        }

        // 3. Check traditional "Code Snippets" plugin table if present
        if ($has_cs_table) {
            $where = '';
            if ($status_filter === 'active') {
                $where = 'WHERE active = 1';
            } elseif ($status_filter === 'inactive') {
                $where = 'WHERE active = 0';
            }

            $rows = $wpdb->get_results("SELECT id, name, code, active, modified FROM `$table_snippets` $where ORDER BY name ASC", ARRAY_A);
            if ($rows) {
                foreach ($rows as $row) {
                    $is_active = ((int) $row['active'] === 1);
                    $snippets[] = [
                        'source_plugin' => 'Code Snippets',
                        'id'            => (int) $row['id'],
                        'title'         => $row['name'],
                        'status'        => $is_active ? 'active' : 'inactive',
                        'is_active'     => $is_active,
                        'code_type'     => 'php',
                        'location'      => 'run-everywhere',
                        'priority'      => 10,
                        'modified_at'   => $row['modified'],
                        'code'          => $row['code'],
                    ];
                }
            }
        }

        return $this->response([
            'total'          => $global_total,
            'active_count'   => $global_active,
            'inactive_count' => $global_inactive,
            'filter'         => $status_filter,
            'count'          => count($snippets),
            'snippets'       => $snippets,
        ]);
    }

    public function get_snippet_by_id(\WP_REST_Request $request) {
        $id = (int) $request->get_param('id');
        $post = get_post($id);

        if ($post && $post->post_type === 'wpcode') {
            $code = get_post_meta($post->ID, '_wpcode_snippet_code', true) ?: get_post_meta($post->ID, 'wpcode_snippet_code', true) ?: $post->post_content;
            $code_type = get_post_meta($post->ID, '_wpcode_snippet_type', true) ?: get_post_meta($post->ID, 'wpcode_snippet_type', true) ?: 'php';
            $is_active = ($post->post_status === 'publish');

            return $this->response([
                'source'      => 'WPCode',
                'id'          => $post->ID,
                'title'       => $post->post_title,
                'status'      => $is_active ? 'active' : 'inactive',
                'is_active'   => $is_active,
                'code_type'   => $code_type,
                'code'        => $code,
                'modified_at' => get_the_modified_date('c', $post),
            ]);
        }

        // Check Code Snippets table
        global $wpdb;
        $table_snippets = $wpdb->prefix . 'snippets';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_snippets'") === $table_snippets) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `$table_snippets` WHERE id = %d", $id), ARRAY_A);
            if ($row) {
                $is_active = ((int) $row['active'] === 1);
                return $this->response([
                    'source'      => 'Code Snippets',
                    'id'          => (int) $row['id'],
                    'title'       => $row['name'],
                    'status'      => $is_active ? 'active' : 'inactive',
                    'is_active'   => $is_active,
                    'code_type'   => 'php',
                    'code'        => $row['code'],
                    'modified_at' => $row['modified'],
                ]);
            }
        }

        return $this->error('snippet_not_found', esc_html__('Snippet not found.', 'woo-get-data-for-ai'), 404);
    }
}
