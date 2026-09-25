<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Redaction;

class Logs_Controller extends Rest_Controller {

    public function register_routes() {
        // GET /logs/sources
        register_rest_route(self::NAMESPACE, '/logs/sources', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_log_sources'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'logs');
            },
        ]);

        // GET /logs/view
        register_rest_route(self::NAMESPACE, '/logs/view', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'view_log_file'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'logs');
            },
        ]);

        // GET /logs/custom (Read specific custom log file in wp-content/ with memory safety)
        register_rest_route(self::NAMESPACE, '/logs/custom', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'view_custom_log'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'logs');
            },
            'args'                => [
                'file'   => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'lines'  => [
                    'default'           => 200,
                    'sanitize_callback' => 'absint',
                ],
                'filter' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /logs/errors-summary (Crash Watch: aggregated recent PHP fatal errors and exceptions)
        register_rest_route(self::NAMESPACE, '/logs/errors-summary', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_errors_summary'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'logs');
            },
            'args'                => [
                'limit' => [
                    'default'           => 15,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // GET /logs/emails (Search database email logs across WP Mail Logging, FluentSMTP, Post SMTP, WP Mail SMTP)
        register_rest_route(self::NAMESPACE, '/logs/emails', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_email_logs'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'logs');
            },
            'args'                => [
                'search' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'status' => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit'  => [
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
                'offset' => [
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // GET /logs/payment-logs (Payment gateway logs inspection and failed orders diagnosis)
        register_rest_route(self::NAMESPACE, '/logs/payment-logs', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_payment_logs'],
            'permission_callback' => function ($request) {
                return $this->check_payment_logs_access($request);
            },
            'args'                => [
                'gateway'  => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order_id' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'level'    => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'lines'    => [
                    'default'           => 100,
                    'sanitize_callback' => 'absint',
                ],
                'days'     => [
                    'default'           => null,
                    'sanitize_callback' => 'absint',
                ],
                'date'     => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'filter'   => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search'   => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /logs/payments (Shorter alias)
        register_rest_route(self::NAMESPACE, '/logs/payments', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_payment_logs'],
            'permission_callback' => function ($request) {
                return $this->check_payment_logs_access($request);
            },
        ]);
    }

    public function get_log_sources(\WP_REST_Request $request) {
        $sources = [];

        // 1. WP debug.log
        $debug_log_path = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log_path) && is_readable($debug_log_path)) {
            $sources[] = [
                'source_type' => 'wp_debug',
                'name'        => 'debug.log',
                'path'        => 'debug.log',
                'size_bytes'  => filesize($debug_log_path),
                'size_human'  => size_format(filesize($debug_log_path)),
                'modified_at' => date('c', filemtime($debug_log_path)),
            ];
        }

        // 2. Custom log files located directly in WP_CONTENT_DIR (e.g. komela-order-status-sync.log)
        if (is_dir(WP_CONTENT_DIR)) {
            $root_content_files = scandir(WP_CONTENT_DIR);
            if ($root_content_files) {
                foreach ($root_content_files as $rc_file) {
                    if ($rc_file === '.' || $rc_file === '..' || $rc_file === 'debug.log' || substr($rc_file, -4) !== '.log') {
                        continue;
                    }
                    $full_rc_file = WP_CONTENT_DIR . '/' . $rc_file;
                    if (is_file($full_rc_file) && is_readable($full_rc_file)) {
                        $sources[] = [
                            'source_type' => 'custom',
                            'name'        => $rc_file,
                            'path'        => $rc_file,
                            'size_bytes'  => filesize($full_rc_file),
                            'size_human'  => size_format(filesize($full_rc_file)),
                            'modified_at' => date('c', filemtime($full_rc_file)),
                        ];
                    }
                }
            }
        }

        // 3. WooCommerce Logs (uploads/wc-logs/)
        $wc_logs_dir = '';
        if (defined('WC_LOG_DIR')) {
            $wc_logs_dir = WC_LOG_DIR;
        } else {
            $upload_dir = wp_upload_dir();
            $wc_logs_dir = $upload_dir['basedir'] . '/wc-logs/';
        }

        if (is_dir($wc_logs_dir)) {
            $files = scandir($wc_logs_dir);
            if ($files) {
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..' || substr($file, -4) !== '.log') {
                        continue;
                    }
                    $full_file = $wc_logs_dir . $file;
                    $sources[] = [
                        'source_type' => 'woocommerce',
                        'name'        => $file,
                        'path'        => 'wc-logs/' . $file,
                        'size_bytes'  => filesize($full_file),
                        'size_human'  => size_format(filesize($full_file)),
                        'modified_at' => date('c', filemtime($full_file)),
                    ];
                }
            }
        }

        // Sort by last modified descending
        usort($sources, function ($a, $b) {
            return strcmp($b['modified_at'], $a['modified_at']);
        });

        return $this->response([
            'total_sources' => count($sources),
            'sources'       => $sources,
        ]);
    }

    public function view_log_file(\WP_REST_Request $request) {
        $source = $request->get_param('source');
        $max_lines = min(1000, max(10, (int) ($request->get_param('lines') ?: 200)));
        $filter = $request->get_param('filter');

        if (empty($source)) {
            return $this->error('missing_source', esc_html__('Parameter "source" is required.', 'woo-get-data-for-ai'), 400);
        }

        // Resolve file path safely
        $file_path = $this->resolve_log_path($source);
        if (!$file_path || !file_exists($file_path) || !is_readable($file_path)) {
            return $this->error('log_not_found', esc_html__('Log file not found or unreadable.', 'woo-get-data-for-ai'), 404);
        }

        // Tail reading via fseek (memory safe for files of any size)
        $lines = $this->tail_file($file_path, $max_lines * 2); // Read extra to account for filters

        // Apply filter if requested
        if (!empty($filter)) {
            $regex = '/' . preg_quote($filter, '/') . '/i';
            $lines = array_values(array_filter($lines, function ($line) use ($regex) {
                return preg_match($regex, $line);
            }));
        }

        // Limit to requested lines
        $lines = array_slice($lines, -$max_lines);

        // Redact all lines for sensitive data and PII
        $sanitized_lines = array_map(function ($line) {
            return Redaction::redact_string($line, true);
        }, $lines);

        return $this->response([
            'source'      => basename($file_path),
            'total_lines' => count($sanitized_lines),
            'filesize'    => size_format(filesize($file_path)),
            'modified_at' => date('c', filemtime($file_path)),
            'lines'       => $sanitized_lines,
        ]);
    }

    /**
     * View a custom log file located in wp-content/.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function view_custom_log(\WP_REST_Request $request) {
        $file = $request->get_param('file');
        if (empty($file)) {
            $file = $request->get_param('source');
        }

        if (empty($file)) {
            return $this->error('missing_file', esc_html__('Parameter "file" is required.', 'woo-get-data-for-ai'), 400);
        }

        $max_lines = min(1000, max(10, (int) ($request->get_param('lines') ?: 200)));
        $filter = $request->get_param('filter');

        // Resolve file path strictly within WP_CONTENT_DIR
        $file_path = $this->resolve_custom_log_path($file);
        if (!$file_path || !file_exists($file_path) || !is_readable($file_path)) {
            return $this->error('log_not_found', esc_html__('Log file not found, unreadable, or outside allowed directories.', 'woo-get-data-for-ai'), 404);
        }

        // Tail reading via fseek (memory safe)
        $lines = $this->tail_file($file_path, $max_lines * 2);

        // Apply filter if requested
        if (!empty($filter)) {
            $regex = '/' . preg_quote($filter, '/') . '/i';
            $lines = array_values(array_filter($lines, function ($line) use ($regex) {
                return preg_match($regex, $line);
            }));
        }

        // Limit to requested lines
        $lines = array_slice($lines, -$max_lines);

        // Redact all lines for sensitive data and PII
        $sanitized_lines = array_map(function ($line) {
            return Redaction::redact_string($line, true);
        }, $lines);

        $wp_content_real = realpath(WP_CONTENT_DIR);
        $relative_path = 'wp-content/' . ltrim(str_replace([$wp_content_real, '\\'], ['', '/'], $file_path), '/');

        return $this->response([
            'file'           => basename($file_path),
            'relative_path'  => $relative_path,
            'total_lines'    => count($sanitized_lines),
            'filesize'       => size_format(filesize($file_path)),
            'filesize_bytes' => filesize($file_path),
            'modified_at'    => date('c', filemtime($file_path)),
            'lines'          => $sanitized_lines,
        ]);
    }

    /**
     * Resolve and validate custom log path strictly within WP_CONTENT_DIR.
     *
     * @param string $file
     * @return string|false
     */
    protected function resolve_custom_log_path($file) {
        $clean_file = sanitize_text_field(wp_unslash($file));

        // Strip directory traversal attempts & null bytes
        if (strpos($clean_file, '..') !== false || strpos($clean_file, "\0") !== false) {
            return false;
        }

        // Strict extension whitelist: ONLY .log and .txt
        $ext = strtolower(pathinfo($clean_file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['log', 'txt'], true)) {
            return false;
        }

        // Block access to sensitive files
        $lower_base = strtolower(basename($clean_file));
        if (strpos($lower_base, 'wp-config') !== false || strpos($lower_base, '.env') !== false || strpos($lower_base, '.git') !== false) {
            return false;
        }

        $wp_content_real = realpath(WP_CONTENT_DIR);
        if (!$wp_content_real) {
            return false;
        }

        // 1. Direct path inside WP_CONTENT_DIR
        $candidate = WP_CONTENT_DIR . '/' . ltrim($clean_file, '/\\');
        $real = realpath($candidate);
        if ($real && file_exists($real) && strpos($real, $wp_content_real) === 0) {
            return $real;
        }

        // 2. Basename directly in WP_CONTENT_DIR
        $candidate_root = WP_CONTENT_DIR . '/' . basename($clean_file);
        $real_root = realpath($candidate_root);
        if ($real_root && file_exists($real_root) && strpos($real_root, $wp_content_real) === 0) {
            return $real_root;
        }

        // 3. Inside wc-logs directory
        $upload_dir = wp_upload_dir();
        $wc_logs_dir = defined('WC_LOG_DIR') ? WC_LOG_DIR : ($upload_dir['basedir'] . '/wc-logs/');
        $candidate_wc = $wc_logs_dir . basename($clean_file);
        $real_wc = realpath($candidate_wc);
        if ($real_wc && file_exists($real_wc) && strpos($real_wc, $wp_content_real) === 0) {
            return $real_wc;
        }

        return false;
    }

    /**
     * Resolve and validate log path strictly within WP_CONTENT_DIR or wc-logs.
     */
    protected function resolve_log_path($source) {
        $clean_source = sanitize_text_field(wp_unslash($source));

        // Strip directory traversal attempts
        if (strpos($clean_source, '..') !== false) {
            return false;
        }

        $upload_dir = wp_upload_dir();
        $wc_logs_dir = defined('WC_LOG_DIR') ? WC_LOG_DIR : ($upload_dir['basedir'] . '/wc-logs/');

        if (strpos($clean_source, 'wc-logs/') === 0 || strpos($clean_source, 'wc-logs\\') === 0) {
            $filename = basename($clean_source);
            return realpath($wc_logs_dir . $filename);
        }

        if ($clean_source === 'debug.log') {
            return realpath(WP_CONTENT_DIR . '/debug.log');
        }

        // Check inside wc-logs by simple filename
        $in_wc = realpath($wc_logs_dir . basename($clean_source));
        if ($in_wc && file_exists($in_wc)) {
            return $in_wc;
        }

        // Check custom log path fallback
        $custom_log = $this->resolve_custom_log_path($clean_source);
        if ($custom_log && file_exists($custom_log)) {
            return $custom_log;
        }

        return false;
    }

    /**
     * Memory-safe reverse file tail reader using fseek.
     *
     * @param string $filepath
     * @param int $lines
     * @return array
     */
    protected function tail_file($filepath, $lines = 200) {
        $handle = fopen($filepath, 'rb');
        if (!$handle) {
            return [];
        }

        $buffer_size = 4096;
        fseek($handle, 0, SEEK_END);
        $pos = ftell($handle);

        $output = '';
        $line_count = 0;

        while ($pos > 0 && $line_count <= $lines) {
            $seek = min($pos, $buffer_size);
            $pos -= $seek;
            fseek($handle, $pos, SEEK_SET);
            $chunk = fread($handle, $seek);
            $output = $chunk . $output;
            $line_count = substr_count($output, "\n");
        }

        fclose($handle);

        $lines_array = explode("\n", str_replace(["\r\n", "\r"], "\n", $output));
        $lines_array = array_filter($lines_array, function ($l) {
            return trim($l) !== '';
        });

        return array_values($lines_array);
    }

    /**
     * GET /logs/errors-summary
     * Crash Watch: scans recent log entries to aggregate and deduplicate recent fatal PHP errors and exceptions.
     */
    public function get_errors_summary(\WP_REST_Request $request) {
        $limit = min(50, max(1, (int) ($request->get_param('limit') ?: 15)));

        // 1. Discover target error log files
        $files_to_scan = [];

        // debug.log
        $debug_log = WP_CONTENT_DIR . '/debug.log';
        if (file_exists($debug_log) && is_readable($debug_log)) {
            $files_to_scan['debug.log'] = $debug_log;
        }

        // Recent WooCommerce fatal-errors-*.log
        $upload_dir = wp_upload_dir();
        $wc_logs_dir = defined('WC_LOG_DIR') ? WC_LOG_DIR : ($upload_dir['basedir'] . '/wc-logs/');
        if (is_dir($wc_logs_dir)) {
            $wc_files = glob($wc_logs_dir . 'fatal-errors-*.log');
            if (!empty($wc_files)) {
                // Sort by modification time descending
                usort($wc_files, function ($a, $b) {
                    return filemtime($b) <=> filemtime($a);
                });
                // Take top 2 most recent
                foreach (array_slice($wc_files, 0, 2) as $f) {
                    $files_to_scan['wc-logs/' . basename($f)] = $f;
                }
            }
        }

        $aggregated_errors = [];
        $total_error_lines_matched = 0;

        foreach ($files_to_scan as $label => $filepath) {
            $raw_lines = $this->tail_file($filepath, 400);

            foreach ($raw_lines as $line) {
                // Filter for fatal/critical error patterns
                if (!preg_match('/(Fatal error|Parse error|Uncaught\s+[a-zA-Z0-9_\\\\]+|Allowed memory size|Maximum execution time)/i', $line)) {
                    continue;
                }

                $total_error_lines_matched++;

                // Extract timestamp
                $timestamp = null;
                if (preg_match('/^\[([0-9A-Za-z\-:\s\+]+)\]/', $line, $time_match)) {
                    $timestamp = trim($time_match[1]);
                }

                // Extract error file & line
                $source_file = '';
                $source_line = null;
                if (preg_match('/(?:in\s+)([\S]+)(?:\s+on line\s+)(\d+)/i', $line, $file_match)) {
                    $source_file = $file_match[1];
                    $source_line = (int) $file_match[2];
                }

                // Identify origin component (plugin, theme, core)
                $component = 'core_or_other';
                $component_name = '';
                $norm_file = str_replace('\\', '/', $source_file);
                if (preg_match('/\/wp-content\/plugins\/([^\/]+)/', $norm_file, $p_match)) {
                    $component = 'plugin';
                    $component_name = $p_match[1];
                } elseif (preg_match('/\/wp-content\/themes\/([^\/]+)/', $norm_file, $t_match)) {
                    $component = 'theme';
                    $component_name = $t_match[1];
                } elseif (preg_match('/\/wp-content\/mu-plugins\/([^\/]+)/', $norm_file, $mu_match)) {
                    $component = 'mu-plugin';
                    $component_name = $mu_match[1];
                }

                // Clean and redact error message
                $clean_message = Redaction::redact_string(trim($line));

                // Make filepath relative
                $rel_file = $source_file;
                if (!empty($source_file) && defined('ABSPATH')) {
                    $rel_file = str_replace([ABSPATH, WP_CONTENT_DIR], ['', 'wp-content'], $source_file);
                    $rel_file = ltrim($rel_file, '/\\');
                }

                // Deduplication hash
                $error_hash = md5($component . ':' . $component_name . ':' . basename($rel_file) . ':' . $source_line . ':' . substr($clean_message, 0, 80));

                if (!isset($aggregated_errors[$error_hash])) {
                    $aggregated_errors[$error_hash] = [
                        'component_type' => $component,
                        'component_name' => $component_name ?: 'WordPress Core',
                        'file'           => $rel_file,
                        'line'           => $source_line,
                        'source_log'     => $label,
                        'occurrences'    => 1,
                        'last_seen'      => $timestamp ?: 'recent',
                        'first_seen'     => $timestamp ?: 'recent',
                        'raw_excerpt'    => $clean_message,
                    ];
                } else {
                    $aggregated_errors[$error_hash]['occurrences']++;
                    if ($timestamp) {
                        $aggregated_errors[$error_hash]['last_seen'] = $timestamp;
                    }
                }
            }
        }

        $unique_errors = array_values($aggregated_errors);

        // Sort by occurrences descending, then last seen
        usort($unique_errors, function ($a, $b) {
            return $b['occurrences'] <=> $a['occurrences'];
        });

        $top_errors = array_slice($unique_errors, 0, $limit);

        return $this->response([
            'status'             => empty($top_errors) ? 'clean' : 'issues_detected',
            'scanned_sources'    => array_keys($files_to_scan),
            'matched_lines'      => $total_error_lines_matched,
            'unique_issues_count'=> count($unique_errors),
            'reported_count'     => count($top_errors),
            'recent_crashes'     => $top_errors,
        ]);
    }

    /**
     * GET /logs/emails
     * Search database email logs across WP Mail Logging, FluentSMTP, Post SMTP, and WP Mail SMTP.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_email_logs(\WP_REST_Request $request) {
        global $wpdb;

        $search = trim((string) $request->get_param('search'));
        $status = strtolower(trim((string) $request->get_param('status'))) ?: 'all';
        $limit  = min(100, max(1, (int) $request->get_param('limit') ?: 20));
        $offset = max(0, (int) $request->get_param('offset') ?: 0);

        // Detect supported database logging tables
        $table_wpml    = $wpdb->prefix . 'wpml_mails';
        $table_fluent  = $wpdb->prefix . 'fluentmail_log';
        $table_postman = $wpdb->prefix . 'postman_logs';
        $table_wpms    = $wpdb->prefix . 'wpmailsmtp_emails_log';

        $detected_providers = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_wpml}'") === $table_wpml) {
            $detected_providers[] = 'wp_mail_logging';
        }
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_fluent}'") === $table_fluent) {
            $detected_providers[] = 'fluentsmtp';
        }
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_postman}'") === $table_postman) {
            $detected_providers[] = 'post_smtp';
        }
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_wpms}'") === $table_wpms) {
            $detected_providers[] = 'wp_mail_smtp';
        }

        if (empty($detected_providers)) {
            return $this->response([
                'active_log_provider' => null,
                'detected_providers'  => [],
                'total_matched'       => 0,
                'limit'               => $limit,
                'offset'              => $offset,
                'returned_count'      => 0,
                'notice'              => esc_html__('No supported database email logging plugin tables found (WP Mail Logging, FluentSMTP, Post SMTP, or WP Mail SMTP).', 'woo-get-data-for-ai'),
                'logs'                => [],
            ]);
        }

        $primary_provider = $detected_providers[0];
        $items = [];
        $total_matched = 0;

        if ($primary_provider === 'wp_mail_logging') {
            $where = ['1=1'];
            $params = [];

            if ($status === 'sent') {
                $where[] = "(error IS NULL OR error = '' OR error = '0')";
            } elseif ($status === 'failed') {
                $where[] = "(error IS NOT NULL AND error != '' AND error != '0')";
            }

            if (!empty($search)) {
                $where[] = "(receiver LIKE %s OR subject LIKE %s OR message LIKE %s)";
                $like = '%' . $wpdb->esc_like($search) . '%';
                $params[] = $like;
                $params[] = $like;
                $params[] = $like;
            }

            $where_sql = implode(' AND ', $where);

            // Total matched count
            $count_sql = "SELECT COUNT(*) FROM {$table_wpml} WHERE {$where_sql}";
            $total_matched = !empty($params) ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params)) : (int) $wpdb->get_var($count_sql);

            // Retrieve paginated rows
            $query_sql = "SELECT mail_id as id, timestamp, receiver, subject, error, attachments FROM {$table_wpml} WHERE {$where_sql} ORDER BY mail_id DESC LIMIT %d OFFSET %d";
            $query_params = $params;
            $query_params[] = $limit;
            $query_params[] = $offset;

            $rows = $wpdb->get_results($wpdb->prepare($query_sql, $query_params));
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $is_failed = (!empty($row->error) && $row->error !== '0');
                    $items[] = [
                        'id'              => (int) $row->id,
                        'source'          => 'WP Mail Logging',
                        'timestamp'       => $row->timestamp,
                        'recipient'       => Redaction::redact_email($row->receiver),
                        'subject'         => sanitize_text_field($row->subject),
                        'status'          => $is_failed ? 'failed' : 'sent',
                        'error'           => $is_failed ? sanitize_text_field(substr((string) $row->error, 0, 500)) : null,
                        'has_attachments' => !empty($row->attachments),
                    ];
                }
            }
        } elseif ($primary_provider === 'fluentsmtp') {
            $where = ['1=1'];
            $params = [];

            if ($status === 'sent') {
                $where[] = "status = 'sent'";
            } elseif ($status === 'failed') {
                $where[] = "status != 'sent'";
            }

            if (!empty($search)) {
                $where[] = "(`to` LIKE %s OR subject LIKE %s)";
                $like = '%' . $wpdb->esc_like($search) . '%';
                $params[] = $like;
                $params[] = $like;
            }

            $where_sql = implode(' AND ', $where);
            $count_sql = "SELECT COUNT(*) FROM {$table_fluent} WHERE {$where_sql}";
            $total_matched = !empty($params) ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params)) : (int) $wpdb->get_var($count_sql);

            $query_sql = "SELECT id, `to` as recipient, subject, status, response, created_at FROM {$table_fluent} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
            $query_params = $params;
            $query_params[] = $limit;
            $query_params[] = $offset;

            $rows = $wpdb->get_results($wpdb->prepare($query_sql, $query_params));
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $is_failed = ($row->status !== 'sent');
                    $items[] = [
                        'id'              => (int) $row->id,
                        'source'          => 'FluentSMTP',
                        'timestamp'       => $row->created_at,
                        'recipient'       => Redaction::redact_email($row->recipient),
                        'subject'         => sanitize_text_field($row->subject),
                        'status'          => $is_failed ? 'failed' : 'sent',
                        'error'           => $is_failed ? sanitize_text_field(substr((string) $row->response, 0, 500)) : null,
                        'has_attachments' => false,
                    ];
                }
            }
        } elseif ($primary_provider === 'post_smtp') {
            $where = ['1=1'];
            $params = [];

            if ($status === 'sent') {
                $where[] = "success = 1";
            } elseif ($status === 'failed') {
                $where[] = "success = 0";
            }

            if (!empty($search)) {
                $where[] = "(original_to LIKE %s OR original_subject LIKE %s)";
                $like = '%' . $wpdb->esc_like($search) . '%';
                $params[] = $like;
                $params[] = $like;
            }

            $where_sql = implode(' AND ', $where);
            $count_sql = "SELECT COUNT(*) FROM {$table_postman} WHERE {$where_sql}";
            $total_matched = !empty($params) ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params)) : (int) $wpdb->get_var($count_sql);

            $query_sql = "SELECT id, original_to, original_subject, success, solution, time FROM {$table_postman} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
            $query_params = $params;
            $query_params[] = $limit;
            $query_params[] = $offset;

            $rows = $wpdb->get_results($wpdb->prepare($query_sql, $query_params));
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $is_failed = empty($row->success);
                    $date_str = is_numeric($row->time) ? date('c', (int) $row->time) : $row->time;
                    $items[] = [
                        'id'              => (int) $row->id,
                        'source'          => 'Post SMTP',
                        'timestamp'       => $date_str,
                        'recipient'       => Redaction::redact_email($row->original_to),
                        'subject'         => sanitize_text_field($row->original_subject),
                        'status'          => $is_failed ? 'failed' : 'sent',
                        'error'           => $is_failed ? sanitize_text_field(substr((string) $row->solution, 0, 500)) : null,
                        'has_attachments' => false,
                    ];
                }
            }
        } elseif ($primary_provider === 'wp_mail_smtp') {
            $where = ['1=1'];
            $params = [];

            if ($status === 'sent') {
                $where[] = "status = 1";
            } elseif ($status === 'failed') {
                $where[] = "status = 2";
            }

            if (!empty($search)) {
                $where[] = "(to_address LIKE %s OR subject LIKE %s)";
                $like = '%' . $wpdb->esc_like($search) . '%';
                $params[] = $like;
                $params[] = $like;
            }

            $where_sql = implode(' AND ', $where);
            $count_sql = "SELECT COUNT(*) FROM {$table_wpms} WHERE {$where_sql}";
            $total_matched = !empty($params) ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params)) : (int) $wpdb->get_var($count_sql);

            $query_sql = "SELECT id, to_address, subject, status, error_text, date_sent FROM {$table_wpms} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d";
            $query_params = $params;
            $query_params[] = $limit;
            $query_params[] = $offset;

            $rows = $wpdb->get_results($wpdb->prepare($query_sql, $query_params));
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $is_failed = ((int) $row->status === 2);
                    $items[] = [
                        'id'              => (int) $row->id,
                        'source'          => 'WP Mail SMTP',
                        'timestamp'       => $row->date_sent,
                        'recipient'       => Redaction::redact_email($row->to_address),
                        'subject'         => sanitize_text_field($row->subject),
                        'status'          => $is_failed ? 'failed' : 'sent',
                        'error'           => $is_failed ? sanitize_text_field(substr((string) $row->error_text, 0, 500)) : null,
                        'has_attachments' => false,
                    ];
                }
            }
        }

        return $this->response([
            'active_log_provider' => $primary_provider,
            'detected_providers'  => $detected_providers,
            'total_matched'       => $total_matched,
            'limit'               => $limit,
            'offset'              => $offset,
            'returned_count'      => count($items),
            'filters'             => [
                'search' => $search,
                'status' => $status,
            ],
            'logs'                => $items,
        ]);
    }

    /**
     * Check access for payment logs endpoint (allowed if either woocommerce or logs permission is active).
     *
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    public function check_payment_logs_access(\WP_REST_Request $request) {
        $endpoint = $request->get_route();

        // 1. Verify Security (Bearer token, method, rate limit, IP)
        $security_check = \WPAgentBridge\Security::verify_request($request);
        if (is_wp_error($security_check)) {
            $status = $security_check->get_error_data()['status'] ?? 401;
            \WPAgentBridge\Access_Logger::log_request($endpoint, $status);
            return $security_check;
        }

        // 2. Allow if either woocommerce or logs permission is active
        if (!\WPAgentBridge\Permissions::is_module_enabled('woocommerce') && !\WPAgentBridge\Permissions::is_module_enabled('logs')) {
            \WPAgentBridge\Access_Logger::log_request($endpoint, 403);
            return new \WP_Error(
                'agent_bridge_module_disabled',
                esc_html__("Both 'woocommerce' and 'logs' modules are disabled by the site administrator.", 'woo-get-data-for-ai'),
                ['status' => 403]
            );
        }

        \WPAgentBridge\Access_Logger::log_request($endpoint, 200);
        return true;
    }

    /**
     * GET /logs/payment-logs
     * Inspect payment gateway logs (Stripe, Alma, PayPal) with automatic hash resolution and order filtering.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_payment_logs(\WP_REST_Request $request) {
        $data = \WPAgentBridge\Payment_Logs::get_logs($request);
        return $this->response($data);
    }
}
