<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

class Wpcode_Controller extends Rest_Controller {

    public function register_routes() {
        // Collection routes: /snippets (universal) & /wpcode/snippets (backward compatibility)
        $collection_routes = [
            '/snippets',
            '/wpcode/snippets',
        ];

        foreach ($collection_routes as $route) {
            register_rest_route(self::NAMESPACE, $route, [
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
                    'source' => [
                        'default'           => 'all',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'type'   => [
                        'default'           => 'all',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]);
        }

        // Single snippet routes: /snippets/{id} & /wpcode/snippet/{id}
        $single_routes = [
            '/snippets/(?P<id>\d+)',
            '/wpcode/snippet/(?P<id>\d+)',
        ];

        foreach ($single_routes as $route) {
            register_rest_route(self::NAMESPACE, $route, [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_snippet_by_id'],
                'permission_callback' => function ($request) {
                    return $this->check_access($request, 'wpcode');
                },
                'args'                => [
                    'source' => [
                        'default'           => 'all',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]);
        }
    }

    /**
     * Get all snippets across WPCode and Code Snippets with filtering.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_snippets(\WP_REST_Request $request) {
        $raw_status = strtolower(trim((string) ($request->get_param('status') ?: 'all')));
        if (in_array($raw_status, ['active', 'publish', 'enabled', '1'], true)) {
            $status_filter = 'active';
        } elseif (in_array($raw_status, ['inactive', 'draft', 'disabled', '0'], true)) {
            $status_filter = 'inactive';
        } else {
            $status_filter = 'all';
        }

        $raw_source = strtolower(trim((string) ($request->get_param('source') ?: 'all')));
        if (in_array($raw_source, ['code-snippets', 'codesnippets', 'cs'], true)) {
            $source_filter = 'code-snippets';
        } elseif ($raw_source === 'wpcode') {
            $source_filter = 'wpcode';
        } else {
            $source_filter = 'all';
        }

        $raw_type = strtolower(trim((string) ($request->get_param('type') ?: 'all')));
        if (in_array($raw_type, ['js', 'javascript'], true)) {
            $type_filter = 'js';
        } elseif (in_array($raw_type, ['html', 'text', 'content'], true)) {
            $type_filter = 'html';
        } elseif (in_array($raw_type, ['css', 'php'], true)) {
            $type_filter = $raw_type;
        } else {
            $type_filter = 'all';
        }

        global $wpdb;
        $table_snippets = $wpdb->prefix . 'snippets';
        $has_cs_table   = ($wpdb->get_var("SHOW TABLES LIKE '$table_snippets'") === $table_snippets);

        // 1. Calculate active and inactive counts for both managers
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

        if ($source_filter === 'wpcode') {
            $active_count   = $cpt_active;
            $inactive_count = $cpt_inactive;
        } elseif ($source_filter === 'code-snippets') {
            $active_count   = $cs_active;
            $inactive_count = $cs_inactive;
        } else {
            $active_count   = $cpt_active + $cs_active;
            $inactive_count = $cpt_inactive + $cs_inactive;
        }
        $total_count = $active_count + $inactive_count;

        $snippets = [];

        // 2. Fetch WPCode Custom Post Type ('wpcode') matching filter
        if ($source_filter === 'all' || $source_filter === 'wpcode') {
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
                $snippets[] = $this->format_wpcode_post($post);
            }
        }

        // 3. Fetch Code Snippets plugin table if present
        if ($has_cs_table && ($source_filter === 'all' || $source_filter === 'code-snippets')) {
            $where = '';
            if ($status_filter === 'active') {
                $where = 'WHERE active = 1';
            } elseif ($status_filter === 'inactive') {
                $where = 'WHERE active = 0';
            }

            $rows = $wpdb->get_results("SELECT id, name, description, code, tags, scope, priority, active, modified FROM `$table_snippets` $where ORDER BY name ASC", ARRAY_A);
            if ($rows) {
                foreach ($rows as $row) {
                    $snippets[] = $this->format_code_snippets_row($row);
                }
            }
        }

        // 4. Apply type filter if requested
        if ($type_filter !== 'all') {
            $snippets = array_values(array_filter($snippets, function ($snip) use ($type_filter) {
                return $snip['code_type'] === $type_filter;
            }));
        }

        return $this->response([
            'total'          => $total_count,
            'active_count'   => $active_count,
            'inactive_count' => $inactive_count,
            'filter'         => $status_filter,
            'source'         => $source_filter,
            'type'           => $type_filter,
            'count'          => count($snippets),
            'snippets'       => $snippets,
        ]);
    }

    /**
     * Get a single snippet by ID across WPCode and Code Snippets with collision resolution.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_snippet_by_id(\WP_REST_Request $request) {
        $id = (int) $request->get_param('id');
        $raw_source = strtolower(trim((string) ($request->get_param('source') ?: 'all')));
        if (in_array($raw_source, ['code-snippets', 'codesnippets', 'cs'], true)) {
            $source_filter = 'code-snippets';
        } elseif ($raw_source === 'wpcode') {
            $source_filter = 'wpcode';
        } else {
            $source_filter = 'all';
        }

        // 1. Search in WPCode CPT if requested or default
        if ($source_filter === 'all' || $source_filter === 'wpcode') {
            $post = get_post($id);
            if ($post && $post->post_type === 'wpcode') {
                return $this->response($this->format_wpcode_post($post));
            }
        }

        // 2. Search in Code Snippets table if requested or default
        if ($source_filter === 'all' || $source_filter === 'code-snippets') {
            global $wpdb;
            $table_snippets = $wpdb->prefix . 'snippets';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_snippets'") === $table_snippets) {
                $row = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, name, description, code, tags, scope, priority, active, modified FROM `$table_snippets` WHERE id = %d",
                    $id
                ), ARRAY_A);

                if ($row) {
                    return $this->response($this->format_code_snippets_row($row));
                }
            }
        }

        return $this->error('snippet_not_found', esc_html__('Snippet not found.', 'woo-get-data-for-ai'), 404);
    }

    /**
     * Format a WPCode post into a unified snippet structure.
     *
     * @param \WP_Post $post
     * @return array
     */
    private function format_wpcode_post($post) {
        $raw_type = get_post_meta($post->ID, '_wpcode_snippet_type', true);
        if (empty($raw_type)) {
            $raw_type = get_post_meta($post->ID, 'wpcode_snippet_type', true) ?: 'php';
        }
        $raw_type = strtolower(trim($raw_type));
        if ($raw_type === 'javascript') {
            $code_type = 'js';
        } elseif ($raw_type === 'text') {
            $code_type = 'html';
        } else {
            $code_type = $raw_type ?: 'php';
        }

        $code = get_post_meta($post->ID, '_wpcode_snippet_code', true);
        if (empty($code)) {
            $code = get_post_meta($post->ID, 'wpcode_snippet_code', true) ?: $post->post_content;
        }

        $location = get_post_meta($post->ID, '_wpcode_snippet_location', true) ?: (get_post_meta($post->ID, 'wpcode_snippet_location', true) ?: 'site-wide');
        $priority = (int) (get_post_meta($post->ID, '_wpcode_snippet_priority', true) ?: (get_post_meta($post->ID, 'wpcode_snippet_priority', true) ?: 10));
        $description = (string) (get_post_meta($post->ID, '_wpcode_snippet_description', true) ?: (get_post_meta($post->ID, 'wpcode_snippet_description', true) ?: ''));

        // Retrieve tags (from taxonomies or post meta)
        $tags = [];
        if (taxonomy_exists('wpcode_tags')) {
            $term_tags = wp_get_post_terms($post->ID, 'wpcode_tags', ['fields' => 'names']);
            if (!is_wp_error($term_tags) && is_array($term_tags)) {
                $tags = $term_tags;
            }
        } elseif (taxonomy_exists('wpcode_tag')) {
            $term_tags = wp_get_post_terms($post->ID, 'wpcode_tag', ['fields' => 'names']);
            if (!is_wp_error($term_tags) && is_array($term_tags)) {
                $tags = $term_tags;
            }
        }
        if (empty($tags)) {
            $meta_tags = get_post_meta($post->ID, '_wpcode_snippet_tags', true);
            if (is_array($meta_tags)) {
                $tags = $meta_tags;
            } elseif (is_string($meta_tags) && !empty($meta_tags)) {
                $tags = array_map('trim', explode(',', $meta_tags));
            }
        }
        $tags = array_values(array_filter(array_map('strval', (array) $tags), function ($t) {
            return trim($t) !== '';
        }));

        $is_active = ($post->post_status === 'publish');

        return [
            'source_plugin'  => 'WPCode',
            'source'         => 'WPCode',
            'id'             => (int) $post->ID,
            'title'          => $post->post_title,
            'status'         => $is_active ? 'active' : 'inactive',
            'is_active'      => $is_active,
            'code_type'      => $code_type,
            'location'       => $location,
            'priority'       => $priority,
            'description'    => $description,
            'tags'           => $tags,
            'admin_edit_url' => admin_url('admin.php?page=wpcode-snippet-manager&snippet_id=' . (int) $post->ID),
            'modified_at'    => get_the_modified_date('c', $post),
            'code'           => $code,
        ];
    }

    /**
     * Format a Code Snippets DB row into a unified snippet structure.
     *
     * @param array $row
     * @return array
     */
    private function format_code_snippets_row($row) {
        $scope = (string) ($row['scope'] ?? 'global');
        if (substr($scope, -4) === '-css') {
            $code_type = 'css';
        } elseif (substr($scope, -3) === '-js') {
            $code_type = 'js';
        } elseif (substr($scope, -7) === 'content') {
            $code_type = 'html';
        } else {
            $code_type = 'php';
        }

        switch ($scope) {
            case 'global':
                $location = 'run-everywhere';
                break;
            case 'admin':
                $location = 'admin-only';
                break;
            case 'front-end':
                $location = 'front-end-only';
                break;
            case 'single-use':
                $location = 'single-use';
                break;
            default:
                $location = $scope;
                break;
        }

        $priority    = (int) ($row['priority'] ?? 10);
        $description = (string) ($row['description'] ?? '');

        // Extract and normalize tags
        $raw_tags = $row['tags'] ?? '';
        $tags     = [];
        if (!empty($raw_tags)) {
            if (is_serialized($raw_tags)) {
                $unserialized = @maybe_unserialize($raw_tags);
                if (is_array($unserialized)) {
                    $tags = $unserialized;
                }
            } elseif (is_string($raw_tags) && (strpos($raw_tags, '[') === 0 || strpos($raw_tags, '{') === 0)) {
                $decoded = json_decode($raw_tags, true);
                if (is_array($decoded)) {
                    $tags = $decoded;
                }
            }
            if (empty($tags) && is_string($raw_tags)) {
                $parts = explode(',', $raw_tags);
                $tags  = array_map('trim', $parts);
            }
        }
        $tags = array_values(array_filter(array_map('strval', (array) $tags), function ($t) {
            return trim($t) !== '';
        }));

        $is_active = ((int) ($row['active'] ?? 0) === 1);

        return [
            'source_plugin'  => 'Code Snippets',
            'source'         => 'Code Snippets',
            'id'             => (int) $row['id'],
            'title'          => (string) ($row['name'] ?? ''),
            'status'         => $is_active ? 'active' : 'inactive',
            'is_active'      => $is_active,
            'code_type'      => $code_type,
            'location'       => $location,
            'priority'       => $priority,
            'description'    => $description,
            'tags'           => $tags,
            'admin_edit_url' => admin_url('admin.php?page=edit-snippet&id=' . (int) $row['id']),
            'modified_at'    => (string) ($row['modified'] ?? ''),
            'code'           => (string) ($row['code'] ?? ''),
        ];
    }
}
