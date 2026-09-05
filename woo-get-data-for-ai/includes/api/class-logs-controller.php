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

        // 2. WooCommerce Logs (uploads/wc-logs/)
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
}
