<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

class Code_Controller extends Rest_Controller {

    /**
     * Allowed file extensions for code inspection.
     */
    const ALLOWED_EXTENSIONS = ['php', 'js', 'css', 'json', 'txt', 'md', 'xml', 'svg', 'html'];

    public function register_routes() {
        // GET /code/plugins (File tree of plugins & mu-plugins)
        register_rest_route(self::NAMESPACE, '/code/plugins', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_plugins_code_tree'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'code');
            },
        ]);

        // GET /code/file (Sandboxed file reader)
        register_rest_route(self::NAMESPACE, '/code/file', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_file_content'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'code');
            },
        ]);
    }

    public function get_plugins_code_tree(\WP_REST_Request $request) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $status = $request->get_param('status') ?: 'active';
        $all_plugins = get_plugins();
        $active_plugins = (array) get_option('active_plugins', []);

        $results = [
            'plugins'    => [],
            'mu_plugins' => [],
        ];

        // 1. Standard Plugins
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = in_array($plugin_file, $active_plugins, true);
            if ($status === 'active' && !$is_active) {
                continue;
            }

            $plugin_dir_name = dirname($plugin_file);
            $full_plugin_dir = WP_PLUGIN_DIR . '/' . $plugin_dir_name;

            $file_list = [];
            if ($plugin_dir_name !== '.' && is_dir($full_plugin_dir)) {
                $file_list = $this->scan_directory_files($full_plugin_dir);
            } else {
                $file_list[] = [
                    'path'       => $plugin_file,
                    'size_bytes' => file_exists(WP_PLUGIN_DIR . '/' . $plugin_file) ? filesize(WP_PLUGIN_DIR . '/' . $plugin_file) : 0,
                ];
            }

            $results['plugins'][] = [
                'name'        => $plugin_data['Name'],
                'plugin_file' => $plugin_file,
                'version'     => $plugin_data['Version'],
                'is_active'   => $is_active,
                'files_count' => count($file_list),
                'files'       => $file_list,
            ];
        }

        // 2. Must-Use Plugins
        $mu_plugins = get_mu_plugins();
        foreach ($mu_plugins as $mu_file => $mu_data) {
            $full_path = WPMU_PLUGIN_DIR . '/' . $mu_file;
            $results['mu_plugins'][] = [
                'name'       => $mu_data['Name'],
                'file'       => $mu_file,
                'version'    => $mu_data['Version'],
                'size_bytes' => file_exists($full_path) ? filesize($full_path) : 0,
            ];
        }

        return $this->response($results);
    }

    public function get_file_content(\WP_REST_Request $request) {
        $raw_path = $request->get_param('path');
        if (empty($raw_path)) {
            return $this->error('missing_path', esc_html__('Parameter "path" is required.', 'woo-get-data-for-ai'), 400);
        }

        // Sanitize and resolve full path
        $normalized_input = wp_normalize_path(urldecode($raw_path));

        // If path is relative to WP_CONTENT_DIR, prepend it
        if (!preg_match('#^(/|[a-zA-Z]:)#', $normalized_input)) {
            $normalized_input = wp_normalize_path(WP_CONTENT_DIR . '/' . ltrim($normalized_input, '/'));
        }

        $real_path = realpath($normalized_input);
        if (!$real_path || !file_exists($real_path)) {
            return $this->error('file_not_found', esc_html__('File not found.', 'woo-get-data-for-ai'), 404);
        }

        $real_path_normalized = wp_normalize_path($real_path);

        // Security check: Must reside within allowed directories
        $allowed_roots = [
            wp_normalize_path(WP_PLUGIN_DIR),
            wp_normalize_path(WPMU_PLUGIN_DIR),
            wp_normalize_path(get_theme_root()),
        ];

        $is_allowed = false;
        foreach ($allowed_roots as $allowed_root) {
            if (strpos($real_path_normalized, $allowed_root) === 0) {
                $is_allowed = true;
                break;
            }
        }

        if (!$is_allowed) {
            return $this->error('forbidden_path', esc_html__('Access denied: Requested path is outside authorized directories.', 'woo-get-data-for-ai'), 403);
        }

        // Check file extension
        $ext = strtolower(pathinfo($real_path_normalized, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return $this->error('forbidden_extension', esc_html__('Access denied: File extension not permitted.', 'woo-get-data-for-ai'), 403);
        }

        // Check forbidden files (e.g. wp-config, .env)
        $basename = strtolower(basename($real_path_normalized));
        if (strpos($basename, 'wp-config') !== false || strpos($basename, '.env') !== false || strpos($basename, '.git') !== false) {
            return $this->error('forbidden_file', esc_html__('Access denied: Protected file.', 'woo-get-data-for-ai'), 403);
        }

        // Enforce maximum file read size (2MB) to prevent memory exhaustion
        $filesize = filesize($real_path);
        if ($filesize > 2 * 1024 * 1024) {
            return $this->error('file_too_large', esc_html__('File exceeds maximum readable size of 2MB.', 'woo-get-data-for-ai'), 413);
        }

        $content = file_get_contents($real_path);

        return $this->response([
            'path'        => str_replace(wp_normalize_path(ABSPATH), '', $real_path_normalized),
            'filename'    => basename($real_path),
            'extension'   => $ext,
            'size_bytes'  => $filesize,
            'modified_at' => date('c', filemtime($real_path)),
            'content'     => $content,
        ]);
    }

    /**
     * Scan directory recursively for code files, skipping heavy vendor/node_modules directories.
     */
    protected function scan_directory_files($dir) {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
                function ($current, $key, $iterator) {
                    $filename = $current->getFilename();
                    // Skip heavy or private directories
                    if ($current->isDir() && in_array($filename, ['vendor', 'node_modules', '.git', '.svn', 'tests'], true)) {
                        return false;
                    }
                    return true;
                }
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                    $files[] = [
                        'path'       => str_replace(wp_normalize_path(WP_PLUGIN_DIR) . '/', '', wp_normalize_path($file->getPathname())),
                        'size_bytes' => $file->getSize(),
                    ];
                }
            }
        }

        return $files;
    }
}
