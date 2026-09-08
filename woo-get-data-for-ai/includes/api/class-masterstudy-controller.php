<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Redaction;

class Masterstudy_Controller extends Rest_Controller {

    /**
     * Register REST API routes for MasterStudy LMS.
     */
    public function register_routes() {
        // GET /masterstudy/courses (Lists all courses with pricing, duration, and PMPro membership access rules)
        register_rest_route(self::NAMESPACE, '/masterstudy/courses', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_courses'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'masterstudy');
            },
            'args'                => [
                'search'   => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'status'   => [
                    'default'           => 'publish',
                    'sanitize_callback' => 'sanitize_text_field',
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

        // GET /masterstudy/user/{user_id}/courses (Audit user enrollments, PMPro links, and expiration root cause)
        register_rest_route(self::NAMESPACE, '/masterstudy/user/(?P<user_id>\d+)/courses', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_user_courses'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'masterstudy');
            },
        ]);
    }

    /**
     * Check if MasterStudy LMS is installed and available defensively.
     *
     * @return bool
     */
    private function is_masterstudy_available(): bool {
        global $wpdb;

        if (post_type_exists('stm-courses') || class_exists('STM_LMS_Course') || class_exists('MasterStudy\Lms\Plugin')) {
            return true;
        }

        $table = $wpdb->prefix . 'stm_lms_user_courses';
        $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));

        return ($found === $table);
    }

    /**
     * Mask customer email for GDPR / privacy protection.
     *
     * @param string $email
     * @return string
     */
    private static function mask_email(string $email): string {
        if (empty($email) || !is_email($email)) {
            return '';
        }

        $parts = explode('@', $email, 2);
        $name  = $parts[0];
        $domain = $parts[1] ?? '';

        $len = strlen($name);
        if ($len <= 2) {
            $masked_name = substr($name, 0, 1) . '***';
        } else {
            $masked_name = substr($name, 0, 1) . str_repeat('*', min($len - 2, 4)) . substr($name, -1);
        }

        return $masked_name . '@' . $domain;
    }

    /**
     * Mask customer login username.
     *
     * @param string $login
     * @return string
     */
    private static function mask_login(string $login): string {
        if (empty($login)) {
            return '';
        }

        $len = strlen($login);
        if ($len <= 2) {
            return substr($login, 0, 1) . '***';
        }

        return substr($login, 0, 1) . str_repeat('*', min($len - 2, 4)) . substr($login, -1);
    }

    /**
     * Mask customer display name.
     *
     * @param string $name
     * @return string
     */
    private static function mask_name(string $name): string {
        if (empty($name)) {
            return '';
        }

        $words = explode(' ', trim($name));
        $masked_words = [];
        foreach ($words as $word) {
            $len = strlen($word);
            if ($len <= 2) {
                $masked_words[] = substr($word, 0, 1) . '*';
            } else {
                $masked_words[] = substr($word, 0, 1) . str_repeat('*', min($len - 2, 3)) . substr($word, -1);
            }
        }

        return implode(' ', $masked_words);
    }

    /**
     * GET /masterstudy/courses
     *
     * Lists MasterStudy LMS courses with pricing configuration, linked WooCommerce product,
     * course duration/expiration rules, and allowed PMPro membership levels.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_courses(\WP_REST_Request $request) {
        global $wpdb;

        if (!$this->is_masterstudy_available()) {
            return $this->response([
                'available' => false,
                'message'   => esc_html__('MasterStudy LMS is not detected on this site (stm-courses post type or stm_lms_user_courses table missing).', 'woo-get-data-for-ai'),
                'courses'   => [],
                'total'     => 0,
            ]);
        }

        $search   = sanitize_text_field($request->get_param('search') ?? '');
        $status   = sanitize_text_field($request->get_param('status') ?? 'publish');
        $per_page = max(1, min(100, (int) ($request->get_param('per_page') ?? 20)));
        $page     = max(1, (int) ($request->get_param('page') ?? 1));

        $allowed_statuses = ['publish', 'draft', 'pending', 'future', 'private', 'any'];
        $query_status = in_array($status, $allowed_statuses, true) ? $status : 'publish';

        $query_args = [
            'post_type'              => 'stm-courses',
            'post_status'            => $query_status,
            'posts_per_page'         => $per_page,
            'paged'                  => $page,
            'orderby'                => 'ID',
            'order'                  => 'DESC',
            'update_post_meta_cache' => true,
            'update_post_term_cache' => false,
            'no_found_rows'          => false,
        ];

        if (!empty($search)) {
            $query_args['s'] = $search;
        }

        $query = new \WP_Query($query_args);
        $posts = $query->posts;
        $total = (int) $query->found_posts;
        $total_pages = (int) $query->max_num_pages;

        if (empty($posts)) {
            return $this->response([
                'available'   => true,
                'courses'     => [],
                'pagination'  => [
                    'total'        => $total,
                    'per_page'     => $per_page,
                    'current_page' => $page,
                    'total_pages'  => $total_pages,
                ],
            ]);
        }

        $course_ids = wp_list_pluck($posts, 'ID');
        $course_ids_in = implode(',', array_map('intval', $course_ids));

        // 1. Resolve PMPro membership levels allowed for each course
        $pmpro_pages_table = $wpdb->prefix . 'pmpro_memberships_pages';
        $pmpro_levels_table = $wpdb->prefix . 'pmpro_membership_levels';
        $has_pmpro_pages = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($pmpro_pages_table))) === $pmpro_pages_table);
        $has_pmpro_levels = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($pmpro_levels_table))) === $pmpro_levels_table);

        $course_levels_map = [];
        if ($has_pmpro_pages && $has_pmpro_levels && !empty($course_ids_in)) {
            $level_rows = $wpdb->get_results("
                SELECT mp.page_id, mp.membership_id, ml.name as level_name
                FROM {$pmpro_pages_table} mp
                LEFT JOIN {$pmpro_levels_table} ml ON mp.membership_id = ml.id
                WHERE mp.page_id IN ({$course_ids_in})
            ", ARRAY_A);

            foreach ($level_rows as $lr) {
                $pid = (int) $lr['page_id'];
                if (!isset($course_levels_map[$pid])) {
                    $course_levels_map[$pid] = [];
                }
                $course_levels_map[$pid][] = [
                    'id'   => (int) $lr['membership_id'],
                    'name' => $lr['level_name'] ?? sprintf(esc_html__('Level #%d', 'woo-get-data-for-ai'), (int) $lr['membership_id']),
                ];
            }
        }

        // 2. Resolve total enrolled students count per course
        $user_courses_table = $wpdb->prefix . 'stm_lms_user_courses';
        $has_user_courses_table = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($user_courses_table))) === $user_courses_table);
        $students_map = [];
        if ($has_user_courses_table && !empty($course_ids_in)) {
            $student_counts = $wpdb->get_results("
                SELECT course_id, COUNT(DISTINCT user_id) as student_count
                FROM {$user_courses_table}
                WHERE course_id IN ({$course_ids_in})
                GROUP BY course_id
            ", ARRAY_A);

            foreach ($student_counts as $sc) {
                $students_map[(int) $sc['course_id']] = (int) $sc['student_count'];
            }
        }

        // 3. Resolve lessons count per course
        $curriculum_sections_table = $wpdb->prefix . 'stm_lms_curriculum_sections';
        $curriculum_materials_table = $wpdb->prefix . 'stm_lms_curriculum_materials';
        $has_materials_table = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($curriculum_materials_table))) === $curriculum_materials_table);
        $has_sections_table = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($curriculum_sections_table))) === $curriculum_sections_table);

        $lessons_map = [];
        if ($has_materials_table && $has_sections_table && !empty($course_ids_in)) {
            $lesson_counts = $wpdb->get_results("
                SELECT s.course_id, COUNT(m.id) as lesson_count
                FROM {$curriculum_materials_table} m
                INNER JOIN {$curriculum_sections_table} s ON m.section_id = s.id
                WHERE s.course_id IN ({$course_ids_in})
                  AND m.post_type = 'stm-lessons'
                GROUP BY s.course_id
            ", ARRAY_A);

            foreach ($lesson_counts as $lc) {
                $lessons_map[(int) $lc['course_id']] = (int) $lc['lesson_count'];
            }
        }

        $courses = [];
        foreach ($posts as $post) {
            $cid = (int) $post->ID;

            $pricing_mode     = get_post_meta($cid, 'pricing_mode', true) ?: 'free';
            $price            = get_post_meta($cid, 'price', true);
            $sale_price       = get_post_meta($cid, 'sale_price', true);
            $product_id       = (int) get_post_meta($cid, 'stm_lms_product_id', true);
            $not_membership   = (bool) get_post_meta($cid, 'not_membership', true);
            $expiration_course= (bool) get_post_meta($cid, 'expiration_course', true);
            $end_time_days    = (int) get_post_meta($cid, 'end_time', true);

            // Fallback for lessons count if table not present
            $lessons_count = $lessons_map[$cid] ?? 0;
            if ($lessons_count === 0) {
                $curriculum = get_post_meta($cid, 'curriculum', true);
                if (!empty($curriculum) && is_string($curriculum)) {
                    $curriculum_ids = array_filter(array_map('trim', explode(',', $curriculum)));
                    $lessons_count = count($curriculum_ids);
                }
            }

            $allowed_levels = $course_levels_map[$cid] ?? [];

            $courses[] = [
                'id'                        => $cid,
                'title'                     => $post->post_title,
                'slug'                      => $post->post_name,
                'status'                    => $post->post_status,
                'pricing_mode'              => $pricing_mode,
                'price'                     => !empty($price) ? (float) $price : 0.0,
                'sale_price'                => !empty($sale_price) ? (float) $sale_price : null,
                'stm_lms_product_id'        => $product_id > 0 ? $product_id : null,
                'not_membership'            => $not_membership,
                'membership_access_enabled' => !$not_membership && !empty($allowed_levels),
                'membership_levels_allowed' => $allowed_levels,
                'expiration_course'         => $expiration_course,
                'expiration_days'           => $end_time_days > 0 ? $end_time_days : null,
                'total_students'            => $students_map[$cid] ?? 0,
                'lessons_count'             => $lessons_count,
            ];
        }

        return $this->response([
            'available'  => true,
            'courses'    => $courses,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $per_page,
                'current_page' => $page,
                'total_pages'  => $total_pages,
            ],
        ]);
    }

    /**
     * GET /masterstudy/user/{user_id}/courses
     *
     * Detailed audit of a user's course enrollments in MasterStudy LMS, cross-checking
     * subscription_id against Paid Memberships Pro records to identify access expiration
     * root causes (course duration expired, PMPro membership expired, subscription row pointer desync).
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_user_courses(\WP_REST_Request $request) {
        global $wpdb;

        if (!$this->is_masterstudy_available()) {
            return $this->error('lms_not_installed', esc_html__('MasterStudy LMS is not detected on this site.', 'woo-get-data-for-ai'), 404);
        }

        $user_id = (int) $request->get_param('user_id');
        $user = get_userdata($user_id);

        if (!$user) {
            return $this->error('user_not_found', sprintf(esc_html__('User #%d does not exist.', 'woo-get-data-for-ai'), $user_id), 404);
        }

        $user_courses_table = $wpdb->prefix . 'stm_lms_user_courses';
        $table_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($user_courses_table))) === $user_courses_table);

        if (!$table_exists) {
            return $this->error('table_not_found', sprintf(esc_html__('Table %s does not exist.', 'woo-get-data-for-ai'), $user_courses_table), 500);
        }

        // Query user courses
        $enrollment_rows = $wpdb->get_results($wpdb->prepare("
            SELECT
                user_course_id,
                user_id,
                course_id,
                current_lesson_id,
                progress_percent,
                status,
                subscription_id,
                bundle_id,
                enterprise_id,
                for_points,
                start_time,
                end_time
            FROM {$user_courses_table}
            WHERE user_id = %d
            ORDER BY user_course_id DESC
        ", $user_id), ARRAY_A);

        // Fetch PMPro status and active memberships if PMPro exists
        $pmpro_users_table = $wpdb->prefix . 'pmpro_memberships_users';
        $pmpro_levels_table = $wpdb->prefix . 'pmpro_membership_levels';
        $pmpro_pages_table = $wpdb->prefix . 'pmpro_memberships_pages';

        $has_pmpro_users = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($pmpro_users_table))) === $pmpro_users_table);
        $has_pmpro_levels = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($pmpro_levels_table))) === $pmpro_levels_table);
        $has_pmpro_pages = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($pmpro_pages_table))) === $pmpro_pages_table);

        // Map all PMPro records for this user by `id` (row id in pmpro_memberships_users)
        $pmpro_records_by_id = [];
        $active_pmpro_memberships = [];
        $now_ts = current_time('timestamp', true);

        if ($has_pmpro_users && $has_pmpro_levels) {
            $pmpro_all = $wpdb->get_results($wpdb->prepare("
                SELECT
                    mu.id as row_id,
                    mu.membership_id,
                    mu.status,
                    mu.startdate,
                    mu.enddate,
                    ml.name as level_name
                FROM {$pmpro_users_table} mu
                LEFT JOIN {$pmpro_levels_table} ml ON mu.membership_id = ml.id
                WHERE mu.user_id = %d
                ORDER BY mu.id DESC
            ", $user_id), ARRAY_A);

            foreach ($pmpro_all as $prow) {
                $rid = (int) $prow['row_id'];
                $is_active_status = ($prow['status'] === 'active');
                $is_expired_date = (!empty($prow['enddate']) && $prow['enddate'] !== '0000-00-00 00:00:00' && strtotime($prow['enddate']) < $now_ts);

                $formatted_prow = [
                    'row_id'          => $rid,
                    'membership_id'   => (int) $prow['membership_id'],
                    'level_name'      => $prow['level_name'] ?? sprintf(esc_html__('Level #%d', 'woo-get-data-for-ai'), (int) $prow['membership_id']),
                    'status'          => $prow['status'],
                    'startdate'       => $prow['startdate'],
                    'enddate'         => $prow['enddate'] ?: null,
                    'is_expired_date' => $is_expired_date,
                ];

                $pmpro_records_by_id[$rid] = $formatted_prow;

                if ($is_active_status && !$is_expired_date) {
                    $active_pmpro_memberships[] = $formatted_prow;
                }
            }
        }

        $courses_data = [];
        $anomalies = [];
        $expired_count = 0;
        $active_count = 0;

        foreach ($enrollment_rows as $row) {
            $cid = (int) $row['course_id'];
            $course_post = get_post($cid);
            $course_title = $course_post ? $course_post->post_title : sprintf(esc_html__('Course #%d (Deleted)', 'woo-get-data-for-ai'), $cid);
            $course_status = $course_post ? $course_post->post_status : 'trash';

            // Course settings
            $not_membership   = (bool) get_post_meta($cid, 'not_membership', true);
            $expiration_course= (bool) get_post_meta($cid, 'expiration_course', true);
            $end_time_days    = (int) get_post_meta($cid, 'end_time', true);

            $start_time_ts = (int) $row['start_time'];
            $end_time_ts   = (int) $row['end_time'];
            $sub_id        = (int) $row['subscription_id'];

            // Course-level duration expiration check
            $course_expired_by_duration = false;
            $course_expiration_ts = null;
            if ($expiration_course && $end_time_days > 0 && $start_time_ts > 0) {
                $course_expiration_ts = $start_time_ts + ($end_time_days * DAY_IN_SECONDS);
                if ($now_ts > $course_expiration_ts) {
                    $course_expired_by_duration = true;
                }
            }

            // Linked PMPro record check
            $linked_pmpro = null;
            $pmpro_expired = false;
            $pmpro_inactive = false;
            $pointer_mismatch = false;

            if ($sub_id > 0) {
                if (isset($pmpro_records_by_id[$sub_id])) {
                    $linked_pmpro = $pmpro_records_by_id[$sub_id];
                    if ($linked_pmpro['status'] !== 'active') {
                        $pmpro_inactive = true;
                    }
                    if ($linked_pmpro['is_expired_date'] || $linked_pmpro['status'] === 'expired') {
                        $pmpro_expired = true;
                    }

                    // Check if pointer is outdated: e.g. status is changed/cancelled but user has another active membership
                    if ($linked_pmpro['status'] !== 'active' && !empty($active_pmpro_memberships)) {
                        $pointer_mismatch = true;
                    }
                } else {
                    // Subscription ID points to a non-existent PMPro row
                    $pmpro_inactive = true;
                    $pointer_mismatch = true;
                    $linked_pmpro = [
                        'row_id'          => $sub_id,
                        'membership_id'   => null,
                        'level_name'      => sprintf(esc_html__('Unknown PMPro Record #%d', 'woo-get-data-for-ai'), $sub_id),
                        'status'          => 'not_found',
                        'startdate'       => null,
                        'enddate'         => null,
                        'is_expired_date' => true,
                    ];
                }
            }

            // Allowed PMPro levels for this course
            $allowed_levels_for_course = [];
            if ($has_pmpro_pages && $has_pmpro_levels) {
                $allowed_levels_for_course = $wpdb->get_col($wpdb->prepare("
                    SELECT membership_id FROM {$pmpro_pages_table} WHERE page_id = %d
                ", $cid));
                $allowed_levels_for_course = array_map('intval', $allowed_levels_for_course);
            }

            // Determine if user has valid access right now
            // Follow MasterStudy core logic:
            // If enrolled via subscription ($sub_id > 0), access depends on membership status & course duration
            $has_access = true;
            $expiration_reasons = [];

            if ($course_expired_by_duration) {
                $has_access = false;
                $expiration_reasons[] = sprintf(
                    esc_html__('Course duration limit reached (%d days from enrollment on %s).', 'woo-get-data-for-ai'),
                    $end_time_days,
                    gmdate('Y-m-d', $start_time_ts)
                );
            }

            if ($sub_id > 0) {
                if ($not_membership) {
                    $has_access = false;
                    $expiration_reasons[] = esc_html__('Course is marked with not_membership=true (membership does not grant access).', 'woo-get-data-for-ai');
                }

                if ($pmpro_expired) {
                    $has_access = false;
                    $expiration_reasons[] = sprintf(
                        esc_html__('Linked PMPro membership #%d has expired (status: %s, enddate: %s).', 'woo-get-data-for-ai'),
                        $sub_id,
                        $linked_pmpro['status'] ?? 'expired',
                        $linked_pmpro['enddate'] ?? 'N/A'
                    );
                } elseif ($pmpro_inactive) {
                    $has_access = false;
                    $expiration_reasons[] = sprintf(
                        esc_html__('Linked PMPro membership #%d is inactive (status: %s).', 'woo-get-data-for-ai'),
                        $sub_id,
                        $linked_pmpro['status'] ?? 'inactive'
                    );
                }

                if ($pointer_mismatch && !empty($active_pmpro_memberships)) {
                    $first_active = $active_pmpro_memberships[0];
                    $anomalies[] = [
                        'type'            => 'subscription_pointer_mismatch',
                        'course_id'       => $cid,
                        'course_title'    => $course_title,
                        'subscription_id' => $sub_id,
                        'current_status'  => $linked_pmpro['status'] ?? 'unknown',
                        'active_pmpro_id' => $first_active['row_id'],
                        'active_level'    => $first_active['level_name'],
                        'description'     => sprintf(
                            esc_html__('Course #%d references PMPro membership row #%d (%s), but the user currently has active PMPro membership row #%d (%s). MasterStudy LMS subscription pointer needs resync.', 'woo-get-data-for-ai'),
                            $cid,
                            $sub_id,
                            $linked_pmpro['status'] ?? 'unknown',
                            $first_active['row_id'],
                            $first_active['level_name']
                        ),
                    ];
                }
            }

            // Expiration timestamp calculation
            $days_left = null;
            if ($course_expiration_ts) {
                $diff_sec = $course_expiration_ts - $now_ts;
                $days_left = (int) round($diff_sec / DAY_IN_SECONDS);
            }

            if (!$has_access) {
                $expired_count++;
            } else {
                $active_count++;
            }

            $courses_data[] = [
                'user_course_id'      => (int) $row['user_course_id'],
                'course_id'           => $cid,
                'course_title'        => $course_title,
                'course_status'       => $course_status,
                'enrollment_status'   => $row['status'],
                'progress_percent'    => (int) $row['progress_percent'],
                'current_lesson_id'   => (int) $row['current_lesson_id'],
                'start_time'          => $start_time_ts > 0 ? $start_time_ts : null,
                'start_date'          => $start_time_ts > 0 ? gmdate('Y-m-d H:i:s', $start_time_ts) : null,
                'end_time'            => $end_time_ts > 0 ? $end_time_ts : null,
                'end_date'            => $end_time_ts > 0 ? gmdate('Y-m-d H:i:s', $end_time_ts) : null,
                'subscription_id'     => $sub_id > 0 ? $sub_id : null,
                'bundle_id'           => (int) $row['bundle_id'] > 0 ? (int) $row['bundle_id'] : null,
                'for_points'          => (bool) $row['for_points'],
                'course_rules'        => [
                    'not_membership'            => $not_membership,
                    'expiration_course'         => $expiration_course,
                    'expiration_days'           => $end_time_days > 0 ? $end_time_days : null,
                    'computed_expiration_date'  => $course_expiration_ts ? gmdate('Y-m-d H:i:s', $course_expiration_ts) : null,
                    'days_left'                 => $days_left,
                    'membership_levels_allowed' => $allowed_levels_for_course,
                ],
                'linked_pmpro'        => $linked_pmpro,
                'access_diagnosis'    => [
                    'has_access'          => $has_access,
                    'is_expired'          => !$has_access,
                    'pointer_mismatch'    => $pointer_mismatch,
                    'expiration_reasons'  => $expiration_reasons,
                ],
            ];
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
                'total_enrolled_courses'    => count($enrollment_rows),
                'active_access_courses'     => $active_count,
                'expired_courses'           => $expired_count,
                'active_pmpro_memberships'  => count($active_pmpro_memberships),
                'anomalies_detected'        => count($anomalies),
                'anomalies'                 => $anomalies,
            ],
            'active_pmpro_memberships' => $active_pmpro_memberships,
            'courses'                  => $courses_data,
        ]);
    }
}
