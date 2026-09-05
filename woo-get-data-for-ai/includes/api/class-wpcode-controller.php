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
        $status_filter = $request->get_param('status') ?: 'all';
        $snippets = [];

        // 1. Check WPCode Custom Post Type ('wpcode')
        $post_status = ($status_filter === 'active') ? ['publish'] : ['publish', 'draft'];
        $wpcode_posts = get_posts([
            'post_type'      => 'wpcode',
            'post_status'    => $post_status,
            'posts_per_page' => 200,
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

            $snippets[] = [
                'source_plugin' => 'WPCode',
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'status'        => ($post->post_status === 'publish') ? 'active' : 'inactive',
                'code_type'     => $code_type,
                'location'      => $location ?: 'site-wide',
                'priority'      => (int) ($priority ?: 10),
                'modified_at'   => get_the_modified_date('c', $post),
                'code'          => $code,
            ];
        }

        // 2. Check traditional "Code Snippets" plugin table if present
        global $wpdb;
        $table_snippets = $wpdb->prefix . 'snippets';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_snippets'") === $table_snippets) {
            $where = ($status_filter === 'active') ? 'WHERE active = 1' : '';
            $rows = $wpdb->get_results("SELECT id, name, code, active, modified FROM $table_snippets $where ORDER BY name ASC", ARRAY_A);
            if ($rows) {
                foreach ($rows as $row) {
                    $snippets[] = [
                        'source_plugin' => 'Code Snippets',
                        'id'            => (int) $row['id'],
                        'title'         => $row['name'],
                        'status'        => ((int) $row['active'] === 1) ? 'active' : 'inactive',
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
            'total'    => count($snippets),
            'snippets' => $snippets,
        ]);
    }

    public function get_snippet_by_id(\WP_REST_Request $request) {
        $id = (int) $request->get_param('id');
        $post = get_post($id);

        if ($post && $post->post_type === 'wpcode') {
            $code = get_post_meta($post->ID, '_wpcode_snippet_code', true) ?: get_post_meta($post->ID, 'wpcode_snippet_code', true) ?: $post->post_content;
            $code_type = get_post_meta($post->ID, '_wpcode_snippet_type', true) ?: get_post_meta($post->ID, 'wpcode_snippet_type', true) ?: 'php';

            return $this->response([
                'source'      => 'WPCode',
                'id'          => $post->ID,
                'title'       => $post->post_title,
                'status'      => ($post->post_status === 'publish') ? 'active' : 'inactive',
                'code_type'   => $code_type,
                'code'        => $code,
                'modified_at' => get_the_modified_date('c', $post),
            ]);
        }

        // Check Code Snippets table
        global $wpdb;
        $table_snippets = $wpdb->prefix . 'snippets';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_snippets'") === $table_snippets) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_snippets WHERE id = %d", $id), ARRAY_A);
            if ($row) {
                return $this->response([
                    'source'      => 'Code Snippets',
                    'id'          => (int) $row['id'],
                    'title'       => $row['name'],
                    'status'      => ((int) $row['active'] === 1) ? 'active' : 'inactive',
                    'code_type'   => 'php',
                    'code'        => $row['code'],
                    'modified_at' => $row['modified'],
                ]);
            }
        }

        return $this->error('snippet_not_found', esc_html__('Snippet not found.', 'woo-get-data-for-ai'), 404);
    }
}
