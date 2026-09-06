<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Permissions;
use WPAgentBridge\Redaction;

class Meta_Controller extends Rest_Controller {

    public function register_routes() {
        // GET /meta/fields (Unified inspector for code-defined & ACF meta fields)
        register_rest_route(self::NAMESPACE, '/meta/fields', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_meta_fields'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'meta');
            },
            'args'                => [
                'source'      => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'post_type'   => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'object_type' => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'search'      => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'include_db'  => [
                    'default'           => 'false',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit_db'    => [
                    'default'           => 50,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // GET /meta/acf (Deep ACF inspection: field groups, hierarchy, location rules, options pages)
        register_rest_route(self::NAMESPACE, '/meta/acf', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_acf_details'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'meta');
            },
            'args'                => [
                'status'    => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'post_type' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        // GET /meta/post/{id} (Inspect all declared and raw meta fields for a specific post/product/order)
        register_rest_route(self::NAMESPACE, '/meta/post/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_post_meta_details'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'meta');
            },
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                ],
            ],
        ]);
    }

    /**
     * GET /meta/fields
     * Retrieves all meta fields defined via code and ACF, with optional database discovery.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_meta_fields(\WP_REST_Request $request) {
        $source      = strtolower(trim((string) ($request->get_param('source') ?: 'all')));
        $post_type   = sanitize_key((string) $request->get_param('post_type'));
        $object_type = sanitize_key((string) ($request->get_param('object_type') ?: 'all'));
        $search      = trim((string) $request->get_param('search'));
        $include_db  = filter_var($request->get_param('include_db'), FILTER_VALIDATE_BOOLEAN) || $source === 'db';
        $limit_db    = min(max(absint($request->get_param('limit_db') ?: 50), 1), 200);

        $response_data = [
            'filters' => [
                'source'      => $source,
                'post_type'   => $post_type ?: null,
                'object_type' => $object_type,
                'search'      => $search ?: null,
                'include_db'  => $include_db,
            ],
            'acf_installed' => function_exists('acf_get_field_groups'),
        ];

        // 1. Core WP Code-Registered Meta
        $code_meta = [];
        if ($source === 'all' || $source === 'code') {
            $code_meta = $this->get_code_registered_meta($object_type, $post_type, $search);
            $response_data['code_registered_meta'] = [
                'count'  => count($code_meta),
                'fields' => $code_meta,
            ];
        }

        // 2. ACF Fields (from PHP code, JSON files, or DB)
        $acf_groups = [];
        if ($source === 'all' || $source === 'acf') {
            $acf_groups = $this->get_acf_field_groups_data($post_type, $search);
            $total_acf_fields = 0;
            foreach ($acf_groups as $grp) {
                $total_acf_fields += count($grp['fields'] ?? []);
            }

            $response_data['acf'] = [
                'status'        => function_exists('acf_get_field_groups') ? 'active' : 'inactive_or_json_only',
                'groups_count'  => count($acf_groups),
                'fields_count'  => $total_acf_fields,
                'field_groups'  => $acf_groups,
            ];
        }

        // 3. Database Discovered Meta Keys
        if ($include_db) {
            $db_meta = $this->get_db_discovered_meta($post_type, $limit_db, $search);
            $response_data['database_meta'] = [
                'total_keys' => count($db_meta),
                'meta_keys'  => $db_meta,
            ];
        }

        // Summary
        $response_data['summary'] = [
            'code_registered_fields_count' => count($code_meta),
            'acf_field_groups_count'       => count($acf_groups),
            'acf_fields_count'             => isset($total_acf_fields) ? $total_acf_fields : 0,
            'database_keys_count'          => isset($db_meta) ? count($db_meta) : 0,
        ];

        return $this->response($response_data);
    }

    /**
     * GET /meta/acf
     * Deep inspection of ACF field groups, hierarchy, and options pages.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_acf_details(\WP_REST_Request $request) {
        $status_filter = strtolower(trim((string) ($request->get_param('status') ?: 'all')));
        $post_type     = sanitize_key((string) $request->get_param('post_type'));

        $is_acf_active = function_exists('acf_get_field_groups');

        $acf_info = [
            'is_active'       => $is_acf_active,
            'version'         => defined('ACF_VERSION') ? ACF_VERSION : null,
            'is_pro'          => defined('ACF_PRO') && ACF_PRO,
            'save_json_path'  => function_exists('acf_get_setting') ? acf_get_setting('save_json') : null,
            'load_json_paths' => function_exists('acf_get_setting') ? (array) acf_get_setting('load_json') : [],
        ];

        $field_groups = $this->get_acf_field_groups_data($post_type, '', $status_filter);

        // Options Pages (if ACF Pro / registered)
        $options_pages = [];
        if (function_exists('acf_get_options_pages')) {
            $raw_pages = acf_get_options_pages();
            if (is_array($raw_pages)) {
                foreach ($raw_pages as $page_slug => $page_def) {
                    $options_pages[] = [
                        'menu_slug'   => $page_def['menu_slug'] ?? $page_slug,
                        'page_title'  => $page_def['page_title'] ?? '',
                        'menu_title'  => $page_def['menu_title'] ?? '',
                        'parent_slug' => $page_def['parent_slug'] ?? '',
                        'capability'  => $page_def['capability'] ?? 'edit_posts',
                        'post_id'     => $page_def['post_id'] ?? 'options',
                    ];
                }
            }
        }

        return $this->response([
            'environment'   => $acf_info,
            'groups_count'  => count($field_groups),
            'options_pages' => $options_pages,
            'field_groups'  => $field_groups,
        ]);
    }

    /**
     * GET /meta/post/{id}
     * Inspect all meta data for a single post/product/order.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_post_meta_details(\WP_REST_Request $request) {
        $post_id = (int) $request->get_param('id');
        $post = get_post($post_id);

        if (!$post) {
            return $this->error('post_not_found', sprintf(esc_html__('Post with ID %d does not exist.', 'woo-get-data-for-ai'), $post_id), 404);
        }

        $post_type = $post->post_type;

        // Post summary
        $post_info = [
            'id'            => $post->ID,
            'title'         => get_the_title($post),
            'slug'          => $post->post_name,
            'post_type'     => $post_type,
            'post_status'   => $post->post_status,
            'date'          => $post->post_date,
            'modified'      => $post->post_modified,
            'edit_link'     => get_edit_post_link($post_id, 'raw'),
        ];

        // 1. ACF Fields for this Post
        $acf_values = [];
        if (function_exists('acf_get_field_groups')) {
            $applicable_groups = acf_get_field_groups(['post_id' => $post_id]);
            if (empty($applicable_groups)) {
                // Fallback: match by post_type
                $applicable_groups = $this->get_acf_field_groups_data($post_type);
            }

            foreach ($applicable_groups as $grp) {
                $grp_key   = $grp['key'] ?? '';
                $grp_title = $grp['title'] ?? '';
                $fields    = function_exists('acf_get_fields') ? acf_get_fields($grp_key) : ($grp['fields'] ?? []);

                if (is_array($fields)) {
                    foreach ($fields as $field) {
                        $field_name = $field['name'] ?? '';
                        if (empty($field_name)) {
                            continue;
                        }

                        $formatted_val = function_exists('get_field') ? get_field($field_name, $post_id, true) : null;
                        $raw_val       = function_exists('get_field') ? get_field($field_name, $post_id, false) : get_post_meta($post_id, $field_name, true);

                        $acf_values[$field_name] = [
                            'field_key'   => $field['key'] ?? '',
                            'field_name'  => $field_name,
                            'label'       => $field['label'] ?? $field_name,
                            'type'        => $field['type'] ?? 'text',
                            'group_title' => $grp_title,
                            'group_key'   => $grp_key,
                            'value'       => $this->safe_format_value($formatted_val),
                            'raw_value'   => $this->safe_format_value($raw_val),
                        ];
                    }
                }
            }
        }

        // 2. Code-Registered Meta for this post type
        $registered_meta_keys = $this->get_code_registered_meta('post', $post_type);
        $code_meta_values = [];
        foreach ($registered_meta_keys as $meta_def) {
            $m_key = $meta_def['meta_key'];
            $is_single = !empty($meta_def['single']);
            $val = get_post_meta($post_id, $m_key, $is_single);
            $code_meta_values[$m_key] = [
                'meta_key'    => $m_key,
                'type'        => $meta_def['type'] ?? 'string',
                'description' => $meta_def['description'] ?? '',
                'single'      => $is_single,
                'value'       => $this->safe_format_value($val),
            ];
        }

        // 3. Raw Postmeta Dump
        $all_raw_meta = get_post_meta($post_id);
        $public_custom_fields = [];
        $hidden_system_fields = [];

        if (is_array($all_raw_meta)) {
            foreach ($all_raw_meta as $key => $vals) {
                // WordPress returns array of values for each key
                $val = count($vals) === 1 ? maybe_unserialize($vals[0]) : array_map('maybe_unserialize', $vals);
                $formatted = $this->safe_format_value($val);

                if (strpos($key, '_') === 0) {
                    $hidden_system_fields[$key] = $formatted;
                } else {
                    $public_custom_fields[$key] = $formatted;
                }
            }
        }

        return $this->response([
            'post'                  => $post_info,
            'acf_fields'            => [
                'count'  => count($acf_values),
                'fields' => $acf_values,
            ],
            'code_registered_meta'  => [
                'count'  => count($code_meta_values),
                'fields' => $code_meta_values,
            ],
            'raw_postmeta'          => [
                'public_fields_count' => count($public_custom_fields),
                'hidden_fields_count' => count($hidden_system_fields),
                'public_fields'       => $public_custom_fields,
                'hidden_fields'       => $hidden_system_fields,
            ],
        ]);
    }

    /**
     * Helper: Retrieve meta keys registered via WordPress code (register_meta / register_post_meta).
     *
     * @param string $object_type_filter
     * @param string $post_type_filter
     * @param string $search
     * @return array
     */
    private function get_code_registered_meta($object_type_filter = 'all', $post_type_filter = '', $search = '') {
        global $wp_meta_keys;

        $results = [];
        $target_object_types = [];

        if ($object_type_filter === 'all' || empty($object_type_filter)) {
            $target_object_types = ['post', 'term', 'user', 'comment'];
        } else {
            $target_object_types = [$object_type_filter];
        }

        foreach ($target_object_types as $obj_type) {
            $subtypes = ['']; // empty string means applies to all subtypes

            if ($obj_type === 'post') {
                if (!empty($post_type_filter)) {
                    $subtypes[] = $post_type_filter;
                } else {
                    $post_types = get_post_types([], 'names');
                    $subtypes = array_merge($subtypes, array_values($post_types));
                }
            } elseif ($obj_type === 'term') {
                $taxonomies = get_taxonomies([], 'names');
                $subtypes = array_merge($subtypes, array_values($taxonomies));
            }

            // Also check whatever subtypes exist in memory in $wp_meta_keys
            if (!empty($wp_meta_keys[$obj_type]) && is_array($wp_meta_keys[$obj_type])) {
                $subtypes = array_unique(array_merge($subtypes, array_keys($wp_meta_keys[$obj_type])));
            }

            foreach ($subtypes as $subtype) {
                if (!empty($post_type_filter) && $obj_type === 'post' && $subtype !== '' && $subtype !== $post_type_filter) {
                    continue;
                }

                $meta_keys_for_subtype = function_exists('get_registered_meta_keys')
                    ? get_registered_meta_keys($obj_type, $subtype)
                    : ($wp_meta_keys[$obj_type][$subtype] ?? []);

                if (!empty($meta_keys_for_subtype) && is_array($meta_keys_for_subtype)) {
                    foreach ($meta_keys_for_subtype as $meta_key => $args) {
                        if (!empty($search)) {
                            $match = stripos($meta_key, $search) !== false
                                || (isset($args['description']) && stripos($args['description'], $search) !== false);
                            if (!$match) {
                                continue;
                            }
                        }

                        $unique_key = $obj_type . ':' . ($subtype ?: 'any') . ':' . $meta_key;
                        if (isset($results[$unique_key])) {
                            continue;
                        }

                        $results[$unique_key] = [
                            'meta_key'                 => $meta_key,
                            'object_type'              => $obj_type,
                            'subtype'                  => $subtype ?: 'any',
                            'type'                     => $args['type'] ?? 'string',
                            'description'              => $args['description'] ?? '',
                            'single'                   => !empty($args['single']),
                            'default'                  => $args['default'] ?? null,
                            'show_in_rest'             => !empty($args['show_in_rest']),
                            'revisions_enabled'        => !empty($args['revisions_enabled']),
                            'has_sanitize_callback'    => !empty($args['sanitize_callback']),
                            'has_auth_callback'        => !empty($args['auth_callback']),
                            'source'                   => 'code (register_meta / register_post_meta)',
                        ];
                    }
                }
            }
        }

        return array_values($results);
    }

    /**
     * Helper: Retrieve ACF field groups and parse full field tree.
     * Supports both native ACF API (DB, PHP code, JSON) and fallback file scanning.
     *
     * @param string $post_type_filter
     * @param string $search
     * @param string $status_filter
     * @return array
     */
    private function get_acf_field_groups_data($post_type_filter = '', $search = '', $status_filter = 'all') {
        $groups_data = [];

        // 1. Native ACF functions available
        if (function_exists('acf_get_field_groups')) {
            $raw_groups = acf_get_field_groups();

            if (is_array($raw_groups)) {
                foreach ($raw_groups as $group) {
                    $is_active = !empty($group['active']);
                    if ($status_filter === 'active' && !$is_active) {
                        continue;
                    }
                    if ($status_filter === 'inactive' && $is_active) {
                        continue;
                    }

                    // Determine definition origin
                    $origin = 'database (ACF Admin UI)';
                    $local_flag = $group['local'] ?? false;
                    if ($local_flag === 'php') {
                        $origin = 'code (PHP: acf_add_local_field_group)';
                    } elseif ($local_flag === 'json') {
                        $origin = 'local_json (acf-json/)';
                    }

                    // Analyze location rules
                    $location_rules = $group['location'] ?? [];
                    $target_post_types = $this->extract_acf_target_post_types($location_rules);
                    $human_rules = $this->humanize_acf_location_rules($location_rules);

                    // If filtering by post_type, verify if this group matches
                    if (!empty($post_type_filter)) {
                        $matches_pt = in_array($post_type_filter, $target_post_types, true);
                        if (!$matches_pt && !empty($target_post_types)) {
                            // If it explicitly targets other post types, skip
                            continue;
                        }
                    }

                    // Extract fields
                    $raw_fields = function_exists('acf_get_fields') ? acf_get_fields($group) : ($group['fields'] ?? []);
                    $parsed_fields = $this->parse_acf_fields_recursive($raw_fields);

                    // Search filter
                    if (!empty($search)) {
                        $title_match = stripos($group['title'] ?? '', $search) !== false;
                        $key_match   = stripos($group['key'] ?? '', $search) !== false;
                        $field_match = $this->search_fields_recursive($parsed_fields, $search);

                        if (!$title_match && !$key_match && !$field_match) {
                            continue;
                        }
                    }

                    $groups_data[] = [
                        'key'                => $group['key'] ?? '',
                        'id'                 => $group['ID'] ?? null,
                        'title'              => $group['title'] ?? '',
                        'active'             => $is_active,
                        'source'             => $origin,
                        'menu_order'         => $group['menu_order'] ?? 0,
                        'position'           => $group['position'] ?? 'normal',
                        'style'              => $group['style'] ?? 'default',
                        'description'        => $group['description'] ?? '',
                        'target_post_types'  => $target_post_types,
                        'location_summary'   => $human_rules,
                        'location_raw'       => $location_rules,
                        'fields_count'       => count($parsed_fields),
                        'fields'             => $parsed_fields,
                    ];
                }
            }

            return $groups_data;
        }

        // 2. Fallback: Scan theme acf-json directory if ACF plugin is inactive
        $json_dirs = array_filter([
            get_stylesheet_directory() . '/acf-json',
            get_template_directory() . '/acf-json',
        ], 'is_dir');

        foreach ($json_dirs as $dir) {
            $files = glob($dir . '/*.json');
            if (is_array($files)) {
                foreach ($files as $file) {
                    $json_content = @file_get_contents($file);
                    if ($json_content) {
                        $data = json_decode($json_content, true);
                        if (is_array($data) && !empty($data['key'])) {
                            $target_post_types = $this->extract_acf_target_post_types($data['location'] ?? []);
                            if (!empty($post_type_filter) && !in_array($post_type_filter, $target_post_types, true)) {
                                continue;
                            }

                            $parsed_fields = $this->parse_acf_fields_recursive($data['fields'] ?? []);
                            $groups_data[] = [
                                'key'                => $data['key'],
                                'id'                 => null,
                                'title'              => $data['title'] ?? basename($file, '.json'),
                                'active'             => !empty($data['active']),
                                'source'             => 'local_json_fallback (' . basename($file) . ')',
                                'menu_order'         => $data['menu_order'] ?? 0,
                                'position'           => $data['position'] ?? 'normal',
                                'style'              => $data['style'] ?? 'default',
                                'description'        => $data['description'] ?? '',
                                'target_post_types'  => $target_post_types,
                                'location_summary'   => $this->humanize_acf_location_rules($data['location'] ?? []),
                                'location_raw'       => $data['location'] ?? [],
                                'fields_count'       => count($parsed_fields),
                                'fields'             => $parsed_fields,
                            ];
                        }
                    }
                }
            }
        }

        return $groups_data;
    }

    /**
     * Recursively parse ACF fields to extract clean schema for AI agents.
     *
     * @param array $fields
     * @return array
     */
    private function parse_acf_fields_recursive($fields) {
        $clean = [];
        if (!is_array($fields)) {
            return $clean;
        }

        foreach ($fields as $field) {
            if (!is_array($field) || empty($field['name'])) {
                continue;
            }

            $type = $field['type'] ?? 'text';
            $field_entry = [
                'key'           => $field['key'] ?? '',
                'name'          => $field['name'],
                'label'         => $field['label'] ?? $field['name'],
                'type'          => $type,
                'instructions'  => $field['instructions'] ?? '',
                'required'      => !empty($field['required']),
                'default_value' => $field['default_value'] ?? null,
                'placeholder'   => $field['placeholder'] ?? '',
                'return_format' => $field['return_format'] ?? '',
            ];

            // Choices for select, radio, checkbox, button_group
            if (isset($field['choices']) && is_array($field['choices'])) {
                $field_entry['choices'] = $field['choices'];
            }

            // Sub-fields for repeaters, groups, accordions
            if (!empty($field['sub_fields']) && is_array($field['sub_fields'])) {
                $field_entry['sub_fields'] = $this->parse_acf_fields_recursive($field['sub_fields']);
            }

            // Layouts for flexible content
            if (!empty($field['layouts']) && is_array($field['layouts'])) {
                $layouts = [];
                foreach ($field['layouts'] as $layout) {
                    $layouts[] = [
                        'key'        => $layout['key'] ?? '',
                        'name'       => $layout['name'] ?? '',
                        'label'      => $layout['label'] ?? '',
                        'sub_fields' => $this->parse_acf_fields_recursive($layout['sub_fields'] ?? []),
                    ];
                }
                $field_entry['layouts'] = $layouts;
            }

            $clean[] = $field_entry;
        }

        return $clean;
    }

    /**
     * Search recursive fields for keyword match.
     *
     * @param array $fields
     * @param string $search
     * @return bool
     */
    private function search_fields_recursive($fields, $search) {
        foreach ($fields as $field) {
            if (stripos($field['name'] ?? '', $search) !== false || stripos($field['label'] ?? '', $search) !== false) {
                return true;
            }
            if (!empty($field['sub_fields']) && $this->search_fields_recursive($field['sub_fields'], $search)) {
                return true;
            }
            if (!empty($field['layouts'])) {
                foreach ($field['layouts'] as $layout) {
                    if (stripos($layout['name'] ?? '', $search) !== false || $this->search_fields_recursive($layout['sub_fields'] ?? [], $search)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    /**
     * Extract targeted post types from ACF location rules.
     *
     * @param array $location_rules
     * @return array
     */
    private function extract_acf_target_post_types($location_rules) {
        $post_types = [];
        if (!is_array($location_rules)) {
            return $post_types;
        }

        foreach ($location_rules as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ($group as $rule) {
                if (isset($rule['param']) && $rule['param'] === 'post_type' && isset($rule['operator']) && $rule['operator'] === '==' && !empty($rule['value'])) {
                    $post_types[] = $rule['value'];
                }
            }
        }

        return array_values(array_unique($post_types));
    }

    /**
     * Convert ACF location rule arrays into human-readable string.
     *
     * @param array $location_rules
     * @return string
     */
    private function humanize_acf_location_rules($location_rules) {
        if (!is_array($location_rules) || empty($location_rules)) {
            return 'All screens';
        }

        $group_strings = [];
        foreach ($location_rules as $group) {
            if (!is_array($group)) {
                continue;
            }
            $rule_strings = [];
            foreach ($group as $rule) {
                $param = $rule['param'] ?? '';
                $op    = $rule['operator'] ?? '==';
                $val   = $rule['value'] ?? '';
                $rule_strings[] = "{$param} {$op} {$val}";
            }
            if (!empty($rule_strings)) {
                $group_strings[] = '(' . implode(' AND ', $rule_strings) . ')';
            }
        }

        return !empty($group_strings) ? implode(' OR ', $group_strings) : 'All screens';
    }

    /**
     * Discover distinct custom meta keys directly in database postmeta.
     *
     * @param string $post_type
     * @param int $limit
     * @param string $search
     * @return array
     */
    private function get_db_discovered_meta($post_type = '', $limit = 50, $search = '') {
        global $wpdb;

        $where_clauses = [
            "pm.meta_key NOT LIKE '_edit_%'",
            "pm.meta_key NOT LIKE '_enclose%'",
            "pm.meta_key NOT LIKE '_oembed%'",
            "pm.meta_key NOT LIKE '_transient_%'",
        ];

        $params = [];

        if (!empty($post_type)) {
            $where_clauses[] = "p.post_type = %s";
            $params[] = $post_type;
        } else {
            $where_clauses[] = "p.post_type NOT IN ('revision', 'auto-draft', 'attachment')";
        }

        if (!empty($search)) {
            $where_clauses[] = "pm.meta_key LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $where_sql = implode(' AND ', $where_clauses);
        $query = "SELECT p.post_type, pm.meta_key, COUNT(pm.meta_id) as occurrences
                  FROM {$wpdb->postmeta} pm
                  INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                  WHERE {$where_sql}
                  GROUP BY p.post_type, pm.meta_key
                  ORDER BY occurrences DESC
                  LIMIT %d";

        $params[] = $limit;
        $prepared = $wpdb->prepare($query, $params);
        $rows = $wpdb->get_results($prepared, ARRAY_A);

        $results = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $is_hidden = strpos($row['meta_key'], '_') === 0;
                $results[] = [
                    'post_type'   => $row['post_type'],
                    'meta_key'    => $row['meta_key'],
                    'occurrences' => (int) $row['occurrences'],
                    'is_hidden'   => $is_hidden,
                    'type_hint'   => $is_hidden ? 'system_or_plugin_hidden' : 'public_custom_field',
                ];
            }
        }

        return $results;
    }

    /**
     * Safely format and truncate large values for JSON response.
     *
     * @param mixed $value
     * @return mixed
     */
    private function safe_format_value($value) {
        if (is_string($value) && strlen($value) > 2000) {
            return substr($value, 0, 2000) . '... [TRUNCATED ' . strlen($value) . ' bytes]';
        }
        return $value;
    }
}
