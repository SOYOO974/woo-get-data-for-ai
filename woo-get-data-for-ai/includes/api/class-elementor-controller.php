<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

class Elementor_Controller extends Rest_Controller {

    public function register_routes() {
        // GET /elementor/export-all (Bulk export of all pages, templates, kit & forms)
        register_rest_route(self::NAMESPACE, '/elementor/export-all', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_export_all'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'elementor');
            },
            'args'                => [
                'status'   => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'type'     => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'page'     => ['default' => 1, 'sanitize_callback' => 'absint'],
                'per_page' => ['default' => 50, 'sanitize_callback' => 'absint'],
            ],
        ]);

        // GET /elementor/list
        register_rest_route(self::NAMESPACE, '/elementor/list', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_elementor_list'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'elementor');
            },
            'args'                => [
                'status' => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'type'   => [
                    'default'           => 'any',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /elementor/item/{id}
        register_rest_route(self::NAMESPACE, '/elementor/item/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_elementor_item'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'elementor');
            },
        ]);

        // GET /elementor/forms
        register_rest_route(self::NAMESPACE, '/elementor/forms', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_elementor_forms'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'elementor');
            },
        ]);

        // GET /elementor/kit
        register_rest_route(self::NAMESPACE, '/elementor/kit', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_elementor_kit'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'elementor');
            },
        ]);
    }

    /**
     * Normalize status parameter for Elementor posts ('all', 'publish', 'draft', 'private').
     * Supports aliases 'active' -> 'publish' and 'inactive' -> 'draft'.
     *
     * @param string $status_param
     * @return string
     */
    protected function normalize_elementor_status($status_param) {
        $clean = strtolower(trim((string) $status_param));
        if (in_array($clean, ['publish', 'published', 'active', 'enabled', '1'], true)) {
            return 'publish';
        }
        if (in_array($clean, ['draft', 'inactive', 'disabled', '0'], true)) {
            return 'draft';
        }
        if ($clean === 'private') {
            return 'private';
        }
        return 'all';
    }

    /**
     * Get aggregate counts of Elementor posts by status.
     *
     * @param array $post_types
     * @return array
     */
    protected function get_elementor_status_counts(array $post_types) {
        global $wpdb;
        if (empty($post_types)) {
            $post_types = ['page', 'elementor_library', 'post'];
        }

        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $sql = $wpdb->prepare(
            "SELECT p.post_status, COUNT(*) as cnt
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON (p.ID = pm.post_id AND pm.meta_key = '_elementor_edit_mode' AND pm.meta_value = 'builder')
             WHERE p.post_type IN ($placeholders)
               AND p.post_status IN ('publish', 'draft', 'private')
             GROUP BY p.post_status",
            ...$post_types
        );

        $results = $wpdb->get_results($sql, ARRAY_A);
        $counts = [
            'publish' => 0,
            'draft'   => 0,
            'private' => 0,
        ];

        if ($results) {
            foreach ($results as $r) {
                $status = $r['post_status'];
                if (isset($counts[$status])) {
                    $counts[$status] = (int) $r['cnt'];
                }
            }
        }

        return $counts;
    }

    public function get_elementor_list(\WP_REST_Request $request) {
        $post_type     = $request->get_param('type') ?: 'any';
        $status_filter = $this->normalize_elementor_status($request->get_param('status') ?: 'all');

        $types = ($post_type === 'any') ? ['page', 'post', 'elementor_library'] : [sanitize_text_field($post_type)];
        $post_statuses = ($status_filter === 'all') ? ['publish', 'draft', 'private'] : [$status_filter];

        // Aggregate counts across requested post types
        $status_counts   = $this->get_elementor_status_counts($types);
        $published_count = $status_counts['publish'];
        $draft_count     = $status_counts['draft'];
        $private_count   = $status_counts['private'];
        $total_global    = $published_count + $draft_count + $private_count;
        $active_count    = $published_count;
        $inactive_count  = $draft_count + $private_count;

        $args = [
            'post_type'      => $types,
            'posts_per_page' => 100,
            'post_status'    => $post_statuses,
            'meta_query'     => [
                [
                    'key'     => '_elementor_edit_mode',
                    'value'   => 'builder',
                    'compare' => '=',
                ],
            ],
            'no_found_rows'  => false,
        ];

        $query = new \WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            $template_type = get_post_meta($post->ID, '_elementor_template_type', true);
            $is_published  = ($post->post_status === 'publish');

            $items[] = [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'slug'          => $post->post_name,
                'post_type'     => $post->post_type,
                'template_type' => $template_type ?: 'standard',
                'status'        => $post->post_status,
                'is_published'  => $is_published,
                'is_active'     => $is_published,
                'modified_at'   => get_the_modified_date('c', $post),
                'url'           => get_permalink($post->ID),
            ];
        }

        return $this->response([
            'total'           => $total_global,
            'matched_count'   => (int) $query->found_posts,
            'published_count' => $published_count,
            'draft_count'     => $draft_count,
            'private_count'   => $private_count,
            'active_count'    => $active_count,
            'inactive_count'  => $inactive_count,
            'filter'          => $status_filter,
            'count'           => count($items),
            'items'           => $items,
        ]);
    }

    public function get_elementor_item(\WP_REST_Request $request) {
        $id = (int) $request->get_param('id');
        $post = get_post($id);

        if (!$post) {
            return $this->error('item_not_found', esc_html__('Post or template not found.', 'woo-get-data-for-ai'), 404);
        }

        $raw_data = get_post_meta($id, '_elementor_data', true);
        $page_settings = get_post_meta($id, '_elementor_page_settings', true);
        $template_type = get_post_meta($id, '_elementor_template_type', true);

        $parsed_data = [];
        if (!empty($raw_data)) {
            $parsed_data = is_string($raw_data) ? json_decode($raw_data, true) : $raw_data;
        }

        $is_published = ($post->post_status === 'publish');

        return $this->response([
            'id'            => $post->ID,
            'title'         => $post->post_title,
            'slug'          => $post->post_name,
            'post_type'     => $post->post_type,
            'template_type' => $template_type,
            'status'        => $post->post_status,
            'is_published'  => $is_published,
            'is_active'     => $is_published,
            'modified_at'   => get_the_modified_date('c', $post),
            'page_settings' => $page_settings ?: [],
            'elements'      => $parsed_data,
        ]);
    }

    public function get_export_all(\WP_REST_Request $request) {
        $page          = max(1, (int) $request->get_param('page'));
        $per_page      = min(100, max(1, (int) $request->get_param('per_page')));
        $status_filter = $this->normalize_elementor_status($request->get_param('status') ?: 'all');

        $post_type = $request->get_param('type');
        $allowed_types = ['page', 'elementor_library', 'post'];
        if (!empty($post_type) && in_array($post_type, $allowed_types, true)) {
            $types = [$post_type];
        } else {
            $types = ['page', 'elementor_library'];
        }

        $post_statuses = ($status_filter === 'all') ? ['publish', 'draft', 'private'] : [$status_filter];

        // Aggregate counts across requested post types
        $status_counts   = $this->get_elementor_status_counts($types);
        $published_count = $status_counts['publish'];
        $draft_count     = $status_counts['draft'];
        $private_count   = $status_counts['private'];
        $total_global    = $published_count + $draft_count + $private_count;
        $active_count    = $published_count;
        $inactive_count  = $draft_count + $private_count;

        $args = [
            'post_type'      => $types,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => $post_statuses,
            'meta_query'     => [
                [
                    'key'     => '_elementor_edit_mode',
                    'value'   => 'builder',
                    'compare' => '=',
                ],
            ],
            'no_found_rows'  => false,
        ];

        $query = new \WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            $raw_data = get_post_meta($post->ID, '_elementor_data', true);
            $page_settings = get_post_meta($post->ID, '_elementor_page_settings', true);
            $template_type = get_post_meta($post->ID, '_elementor_template_type', true);

            $parsed_data = [];
            if (!empty($raw_data)) {
                $parsed_data = is_string($raw_data) ? json_decode($raw_data, true) : $raw_data;
            }

            $is_published = ($post->post_status === 'publish');

            $items[] = [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'slug'          => $post->post_name,
                'post_type'     => $post->post_type,
                'template_type' => $template_type ?: 'standard',
                'status'        => $post->post_status,
                'is_published'  => $is_published,
                'is_active'     => $is_published,
                'modified_at'   => get_the_modified_date('c', $post),
                'url'           => get_permalink($post->ID),
                'page_settings' => is_array($page_settings) ? $page_settings : [],
                'elements'      => is_array($parsed_data) ? $parsed_data : [],
            ];

            // Free runtime cache memory at each iteration
            if (function_exists('wp_cache_flush_runtime')) {
                wp_cache_flush_runtime();
            }
        }

        return $this->response([
            'total'           => $total_global,
            'matched_count'   => (int) $query->found_posts,
            'published_count' => $published_count,
            'draft_count'     => $draft_count,
            'private_count'   => $private_count,
            'active_count'    => $active_count,
            'inactive_count'  => $inactive_count,
            'filter'          => $status_filter,
            'page'            => $page,
            'per_page'        => $per_page,
            'total_pages'     => (int) $query->max_num_pages,
            'count'           => count($items),
            'kit'             => $this->get_kit_data(),
            'forms'           => $this->get_forms_data(),
            'items'           => $items,
        ]);
    }

    public function get_elementor_forms(\WP_REST_Request $request) {
        return $this->response($this->get_forms_data());
    }

    public function get_forms_data() {
        global $wpdb;

        // Search posts containing form widgets in _elementor_data
        $form_posts = $wpdb->get_results(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} 
             WHERE meta_key = '_elementor_data' AND meta_value LIKE '%\"widgetType\":\"form\"%'",
            ARRAY_A
        );

        $forms = [];

        if (!empty($form_posts)) {
            foreach ($form_posts as $row) {
                $post_id = (int) $row['post_id'];
                $post = get_post($post_id);
                if (!$post) {
                    continue;
                }

                $data = json_decode($row['meta_value'], true);
                if (!is_array($data)) {
                    continue;
                }

                $extracted_forms = [];
                $this->extract_forms_recursive($data, $extracted_forms);

                foreach ($extracted_forms as $form_widget) {
                    $settings = $form_widget['settings'] ?? [];
                    $fields = [];

                    if (!empty($settings['form_fields']) && is_array($settings['form_fields'])) {
                        foreach ($settings['form_fields'] as $field) {
                            $fields[] = [
                                'id'       => $field['_id'] ?? '',
                                'type'     => $field['field_type'] ?? 'text',
                                'label'    => $field['field_label'] ?? '',
                                'required' => !empty($field['required']),
                            ];
                        }
                    }

                    $forms[] = [
                        'post_id'         => $post_id,
                        'post_title'      => $post->post_title,
                        'post_url'        => get_permalink($post_id),
                        'widget_id'       => $form_widget['id'] ?? '',
                        'form_name'       => $settings['form_name'] ?? 'Unnamed Form',
                        'submit_actions'  => $settings['submit_actions'] ?? [],
                        'webhook_url'     => $settings['webhooks_url'] ?? null,
                        'email_to'        => $settings['email_to'] ?? null,
                        'redirect_url'    => $settings['redirect_to'] ?? null,
                        'fields_count'    => count($fields),
                        'fields'          => $fields,
                    ];
                }
            }
        }

        return [
            'total_forms' => count($forms),
            'forms'       => $forms,
        ];
    }

    public function get_elementor_kit(\WP_REST_Request $request) {
        $kit_data = $this->get_kit_data();
        if (!$kit_data) {
            return $this->error('no_active_kit', esc_html__('No active Elementor Kit found.', 'woo-get-data-for-ai'), 404);
        }

        return $this->response($kit_data);
    }

    public function get_kit_data() {
        $kit_id = get_option('elementor_active_kit');
        if (!$kit_id) {
            return null;
        }

        $kit_settings = get_post_meta($kit_id, '_elementor_page_settings', true);
        if (!is_array($kit_settings)) {
            $kit_settings = [];
        }

        return [
            'kit_id'             => (int) $kit_id,
            'system_colors'      => $kit_settings['system_colors'] ?? [],
            'custom_colors'      => $kit_settings['custom_colors'] ?? [],
            'system_typography'  => $kit_settings['system_typography'] ?? [],
            'custom_typography'  => $kit_settings['custom_typography'] ?? [],
            'container_width'    => $kit_settings['container_width'] ?? null,
            'viewport_md'        => $kit_settings['viewport_md'] ?? null,
            'viewport_lg'        => $kit_settings['viewport_lg'] ?? null,
        ];
    }

    protected function extract_forms_recursive($elements, &$found_forms) {
        if (!is_array($elements)) {
            return;
        }

        foreach ($elements as $el) {
            if (isset($el['widgetType']) && $el['widgetType'] === 'form') {
                $found_forms[] = $el;
            }

            if (!empty($el['elements']) && is_array($el['elements'])) {
                $this->extract_forms_recursive($el['elements'], $found_forms);
            }
        }
    }
}
