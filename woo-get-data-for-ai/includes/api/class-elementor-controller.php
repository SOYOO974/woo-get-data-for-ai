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

    public function get_elementor_list(\WP_REST_Request $request) {
        $post_type = $request->get_param('type') ?: 'any';

        $args = [
            'post_type'      => $post_type === 'any' ? ['page', 'post', 'elementor_library'] : sanitize_text_field($post_type),
            'posts_per_page' => 100,
            'post_status'    => ['publish', 'draft', 'private'],
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
            $items[] = [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'slug'          => $post->post_name,
                'post_type'     => $post->post_type,
                'template_type' => $template_type ?: 'standard',
                'status'        => $post->post_status,
                'modified_at'   => get_the_modified_date('c', $post),
                'url'           => get_permalink($post->ID),
            ];
        }

        return $this->response([
            'total' => $query->found_posts,
            'items' => $items,
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

        return $this->response([
            'id'            => $post->ID,
            'title'         => $post->post_title,
            'slug'          => $post->post_name,
            'post_type'     => $post->post_type,
            'template_type' => $template_type,
            'modified_at'   => get_the_modified_date('c', $post),
            'page_settings' => $page_settings ?: [],
            'elements'      => $parsed_data,
        ]);
    }

    public function get_export_all(\WP_REST_Request $request) {
        $page = max(1, (int) $request->get_param('page'));
        $per_page = min(100, max(1, (int) $request->get_param('per_page')));

        $post_type = $request->get_param('type');
        $allowed_types = ['page', 'elementor_library', 'post'];
        if (!empty($post_type) && in_array($post_type, $allowed_types, true)) {
            $types = [$post_type];
        } else {
            $types = ['page', 'elementor_library'];
        }

        $args = [
            'post_type'      => $types,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'post_status'    => ['publish', 'draft', 'private'],
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

            $items[] = [
                'id'            => $post->ID,
                'title'         => $post->post_title,
                'slug'          => $post->post_name,
                'post_type'     => $post->post_type,
                'template_type' => $template_type ?: 'standard',
                'status'        => $post->post_status,
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
            'total'       => (int) $query->found_posts,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) $query->max_num_pages,
            'count'       => count($items),
            'kit'         => $this->get_kit_data(),
            'forms'       => $this->get_forms_data(),
            'items'       => $items,
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
