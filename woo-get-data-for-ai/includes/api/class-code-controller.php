<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

class Code_Controller extends Rest_Controller {

    /**
     * Allowed file extensions for single code inspection (/code/file).
     */
    const ALLOWED_EXTENSIONS = ['php', 'js', 'css', 'json', 'txt', 'md', 'xml', 'svg', 'html'];

    /**
     * Maximum files allowed in a single directory inspection/export to prevent DoS/OOM.
     */
    const MAX_FILES_LIMIT = 1000;

    /**
     * Maximum cumulative uncompressed directory size allowed (30 MB).
     */
    const MAX_SIZE_BYTES = 31457280; // 30 * 1024 * 1024

    public function register_routes() {
        // GET /code/plugins (File tree of plugins & mu-plugins)
        register_rest_route(self::NAMESPACE, '/code/plugins', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_plugins_code_tree'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'code');
            },
            'args'                => [
                'status' => [
                    'default'           => 'active',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /code/file (Sandboxed file reader)
        register_rest_route(self::NAMESPACE, '/code/file', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_file_content'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'code');
            },
            'args'                => [
                'path' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Relative path to file (e.g. plugins/my-plugin/my-plugin.php).',
                ],
            ],
        ]);

        // GET /code/checksums (Directory checksum map for local vs prod drift verification)
        register_rest_route(self::NAMESPACE, '/code/checksums', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_code_checksums'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'code');
            },
            'args'                => [
                'path' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Relative target directory (e.g. plugins/my-plugin, themes/woodmart-child).',
                ],
                'algo' => [
                    'default'           => 'md5',
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_key',
                    'description'       => 'Hash algorithm: "md5" (default) or "sha256".',
                ],
            ],
        ]);

        // GET /code/zip (On-the-fly clean ZIP archive generator and streamer)
        register_rest_route(self::NAMESPACE, '/code/zip', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_code_zip'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'code');
            },
            'args'                => [
                'path' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Target directory to compress (e.g. plugins/my-plugin, themes/woodmart-child).',
                ],
                'format' => [
                    'default'           => 'stream',
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_key',
                    'description'       => 'Delivery format: "stream" (binary attachment) or "base64" (JSON-encapsulated base64 string).',
                ],
            ],
        ]);
    }

    public function get_plugins_code_tree(\WP_REST_Request $request) {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $raw_status = strtolower(trim((string) ($request->get_param('status') ?: 'active')));
        if (in_array($raw_status, ['active', 'enabled', '1'], true)) {
            $status_filter = 'active';
        } elseif (in_array($raw_status, ['inactive', 'disabled', '0'], true)) {
            $status_filter = 'inactive';
        } else {
            $status_filter = 'all';
        }

        $all_plugins    = get_plugins();
        $active_plugins = (array) get_option('active_plugins', []);

        $active_count   = 0;
        $inactive_count = 0;
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            if (in_array($plugin_file, $active_plugins, true)) {
                $active_count++;
            } else {
                $inactive_count++;
            }
        }
        $total_plugins = count($all_plugins);

        $plugins = [];

        // 1. Standard Plugins
        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $is_active = in_array($plugin_file, $active_plugins, true);
            if ($status_filter === 'active' && !$is_active) {
                continue;
            }
            if ($status_filter === 'inactive' && $is_active) {
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

            $plugins[] = [
                'name'        => $plugin_data['Name'],
                'plugin_file' => $plugin_file,
                'version'     => $plugin_data['Version'],
                'status'      => $is_active ? 'active' : 'inactive',
                'is_active'   => $is_active,
                'files_count' => count($file_list),
                'files'       => $file_list,
            ];
        }

        // 2. Must-Use Plugins
        $mu_plugins_data = get_mu_plugins();
        $mu_plugins = [];
        foreach ($mu_plugins_data as $mu_file => $mu_data) {
            $full_path = WPMU_PLUGIN_DIR . '/' . $mu_file;
            $mu_plugins[] = [
                'name'       => $mu_data['Name'],
                'file'       => $mu_file,
                'version'    => $mu_data['Version'],
                'size_bytes' => file_exists($full_path) ? filesize($full_path) : 0,
            ];
        }

        return $this->response([
            'total'          => $total_plugins,
            'active_count'   => $active_count,
            'inactive_count' => $inactive_count,
            'filter'         => $status_filter,
            'count'          => count($plugins),
            'plugins'        => $plugins,
            'mu_plugins'     => $mu_plugins,
        ]);
    }

    public function get_file_content(\WP_REST_Request $request) {
        $raw_path = $request->get_param('path');
        $resolved = $this->resolve_sandboxed_path($raw_path, false);
        if (is_wp_error($resolved)) {
            return $resolved;
        }

        $real_path            = $resolved['real_path'];
        $real_path_normalized = $resolved['real_path_normalized'];

        // Check file extension
        $ext = strtolower(pathinfo($real_path_normalized, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
            return $this->error('forbidden_extension', esc_html__('Access denied: File extension not permitted.', 'woo-get-data-for-ai'), 403);
        }

        // Check forbidden files (e.g. wp-config, .env)
        $basename = basename($real_path_normalized);
        if ($this->is_excluded_item($basename, false)) {
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
     * Compute file checksums (MD5 or SHA256) for all files in a plugin or theme directory.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_code_checksums(\WP_REST_Request $request) {
        $raw_path = $request->get_param('path');
        $resolved = $this->resolve_sandboxed_path($raw_path, true);
        if (is_wp_error($resolved)) {
            return $resolved;
        }

        $algo = strtolower(trim((string) ($request->get_param('algo') ?: 'md5')));
        if (!in_array($algo, ['md5', 'sha256'], true)) {
            return $this->error('invalid_algorithm', esc_html__('Unsupported hashing algorithm. Choose "md5" or "sha256".', 'woo-get-data-for-ai'), 400);
        }

        $collection = $this->collect_directory_files($resolved['real_path']);
        if (is_wp_error($collection)) {
            return $collection;
        }

        $checksums = [];
        foreach ($collection['files'] as $item) {
            $hash = hash_file($algo, $item['full_path']);
            $checksums[$item['relative_path']] = [
                'size_bytes'  => $item['size'],
                'modified_at' => date('c', $item['modified_at']),
                'hash'        => $hash,
            ];
        }

        return $this->response([
            'status'           => 'ok',
            'target'           => $resolved['relative_target'],
            'base_path'        => $resolved['base_path'],
            'total_files'      => $collection['total_files'],
            'total_size_bytes' => $collection['total_size'],
            'algorithm'        => $algo,
            'checksums'        => $checksums,
        ]);
    }

    /**
     * Generate and stream a clean on-the-fly ZIP archive of a plugin or child theme directory.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error|void
     */
    public function get_code_zip(\WP_REST_Request $request) {
        if (!class_exists('ZipArchive')) {
            return $this->error('zip_extension_missing', esc_html__('PHP ZipArchive extension is not available on this server.', 'woo-get-data-for-ai'), 500);
        }

        $raw_path = $request->get_param('path');
        $resolved = $this->resolve_sandboxed_path($raw_path, true);
        if (is_wp_error($resolved)) {
            return $resolved;
        }

        $format = strtolower(trim((string) ($request->get_param('format') ?: 'stream')));
        if (!in_array($format, ['stream', 'base64'], true)) {
            return $this->error('invalid_format', esc_html__('Parameter "format" must be "stream" or "base64".', 'woo-get-data-for-ai'), 400);
        }

        $collection = $this->collect_directory_files($resolved['real_path']);
        if (is_wp_error($collection)) {
            return $collection;
        }

        if ($collection['total_files'] === 0) {
            return $this->error('empty_directory', esc_html__('Target directory has no eligible files to compress.', 'woo-get-data-for-ai'), 400);
        }

        // Create temporary zip archive
        $temp_dir = function_exists('get_temp_dir') ? get_temp_dir() : sys_get_temp_dir();
        $temp_zip = function_exists('wp_tempnam') ? wp_tempnam('wp_agent_bridge_zip_', $temp_dir) : tempnam($temp_dir, 'wp_agent_bridge_zip_');
        if (!$temp_zip) {
            $temp_zip = tempnam(sys_get_temp_dir(), 'wp_agent_bridge_') . '.zip';
        } else {
            // Rename to ensure .zip extension
            $zip_target = $temp_zip . '.zip';
            @rename($temp_zip, $zip_target);
            $temp_zip = $zip_target;
        }

        $zip = new \ZipArchive();
        $open_res = $zip->open($temp_zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($open_res !== true) {
            @unlink($temp_zip);
            return $this->error('zip_creation_failed', esc_html__('Failed to initialize ZIP archive on server.', 'woo-get-data-for-ai'), 500);
        }

        foreach ($collection['files'] as $item) {
            // Ensure forward slashes in archive
            $in_zip_path = str_replace('\\', '/', $item['relative_path']);
            $zip->addFile($item['full_path'], $in_zip_path);
        }

        $zip->close();

        $archive_slug = sanitize_file_name(basename($resolved['real_path']));
        $filename     = $archive_slug . '.zip';
        $zip_filesize = file_exists($temp_zip) ? filesize($temp_zip) : 0;

        if ($format === 'base64') {
            $binary_data = file_get_contents($temp_zip);
            @unlink($temp_zip);

            if ($binary_data === false) {
                return $this->error('zip_read_failed', esc_html__('Failed to read generated ZIP archive.', 'woo-get-data-for-ai'), 500);
            }

            return $this->response([
                'status'         => 'ok',
                'target'         => $resolved['relative_target'],
                'filename'       => $filename,
                'size_bytes'     => $zip_filesize,
                'content_base64' => base64_encode($binary_data),
            ]);
        }

        // Streaming binary mode
        if (headers_sent()) {
            @unlink($temp_zip);
            return $this->error('headers_already_sent', esc_html__('Headers already sent, cannot stream binary ZIP.', 'woo-get-data-for-ai'), 500);
        }

        // Clean any output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . $zip_filesize);
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        $handle = fopen($temp_zip, 'rb');
        if ($handle) {
            while (!feof($handle)) {
                echo fread($handle, 8192);
                flush();
            }
            fclose($handle);
        }

        @unlink($temp_zip);
        exit;
    }

    /**
     * Resolve and strictly validate that a path is confined within authorized roots.
     *
     * @param string $raw_path
     * @param bool   $must_be_dir
     * @return array|\WP_Error Array with path metadata or WP_Error.
     */
    protected function resolve_sandboxed_path($raw_path, $must_be_dir = false) {
        if (empty($raw_path)) {
            return $this->error('missing_path', esc_html__('Parameter "path" is required.', 'woo-get-data-for-ai'), 400);
        }

        // Prevent traversal tricks before normalization
        if (strpos($raw_path, '..') !== false) {
            return $this->error('forbidden_path', esc_html__('Access denied: Directory traversal detected.', 'woo-get-data-for-ai'), 403);
        }

        $normalized_input = wp_normalize_path(urldecode($raw_path));

        // If path is relative, resolve against WP_CONTENT_DIR
        if (!preg_match('#^(/|[a-zA-Z]:)#', $normalized_input)) {
            if (strpos($normalized_input, 'wp-content/') === 0) {
                $normalized_input = wp_normalize_path(WP_CONTENT_DIR . '/' . substr($normalized_input, strlen('wp-content/')));
            } else {
                $normalized_input = wp_normalize_path(WP_CONTENT_DIR . '/' . ltrim($normalized_input, '/'));
            }
        }

        $real_path = realpath($normalized_input);
        if (!$real_path || !file_exists($real_path)) {
            return $this->error('not_found', esc_html__('Requested path not found on server.', 'woo-get-data-for-ai'), 404);
        }

        if ($must_be_dir && !is_dir($real_path)) {
            return $this->error('not_a_directory', esc_html__('Requested path must be a directory.', 'woo-get-data-for-ai'), 400);
        }

        $real_path_normalized = wp_normalize_path($real_path);

        // Security check: Must reside strictly within allowed root directories
        $allowed_roots = [
            wp_normalize_path(WP_PLUGIN_DIR),
            wp_normalize_path(WPMU_PLUGIN_DIR),
            wp_normalize_path(get_theme_root()),
        ];

        $is_allowed = false;
        foreach ($allowed_roots as $allowed_root) {
            // Must be a sub-item inside the allowed root
            if (strpos($real_path_normalized, $allowed_root . '/') === 0) {
                $is_allowed = true;
                break;
            }
        }

        if (!$is_allowed) {
            return $this->error('forbidden_path', esc_html__('Access denied: Requested path is outside authorized directories (plugins, mu-plugins, themes).', 'woo-get-data-for-ai'), 403);
        }

        // Prohibit entire plugins/themes root folders directly
        foreach ($allowed_roots as $allowed_root) {
            if ($real_path_normalized === $allowed_root) {
                return $this->error('forbidden_path', esc_html__('Access denied: Cannot target the root plugins or themes directory.', 'woo-get-data-for-ai'), 403);
            }
        }

        // Strict prohibitions: ABSPATH, wp-config, uploads
        $abspath_norm = wp_normalize_path(ABSPATH);
        $uploads_dir  = function_exists('wp_upload_dir') ? wp_upload_dir() : [];
        $uploads_norm = !empty($uploads_dir['basedir']) ? wp_normalize_path($uploads_dir['basedir']) : wp_normalize_path(WP_CONTENT_DIR . '/uploads');

        if (strpos($real_path_normalized, $uploads_norm) === 0 || strpos($real_path_normalized, $abspath_norm . 'wp-config') === 0) {
            return $this->error('forbidden_path', esc_html__('Access denied: Sensitive or upload directory.', 'woo-get-data-for-ai'), 403);
        }

        // Calculate relative target and base_path for display
        $relative_target = ltrim(str_replace([wp_normalize_path(WP_CONTENT_DIR), wp_normalize_path(ABSPATH)], '', $real_path_normalized), '/');
        $base_path       = 'wp-content/' . ltrim(str_replace(wp_normalize_path(WP_CONTENT_DIR), '', $real_path_normalized), '/');

        return [
            'real_path'            => $real_path,
            'real_path_normalized' => $real_path_normalized,
            'relative_target'      => $relative_target,
            'base_path'            => $base_path,
        ];
    }

    /**
     * Check if a file or directory should be excluded from checksums and ZIP archives.
     *
     * @param string $filename
     * @param bool   $is_dir
     * @return bool
     */
    protected function is_excluded_item($filename, $is_dir = false) {
        $name_lower = strtolower($filename);

        if ($is_dir) {
            $excluded_dirs = ['.git', '.svn', 'node_modules', 'vendor', '.idea', '.vscode', 'cache', 'tests', '__pycache__'];
            return in_array($name_lower, $excluded_dirs, true);
        }

        // Excluded specific sensitive or OS-generated files
        if (
            strpos($name_lower, '.env') === 0 ||
            strpos($name_lower, 'wp-config') !== false ||
            in_array($name_lower, ['.ds_store', 'thumbs.db', '.gitignore', '.gitattributes'], true)
        ) {
            return true;
        }

        // Excluded file extensions (logs, dumps, archives, temp files)
        $ext = strtolower(pathinfo($name_lower, PATHINFO_EXTENSION));
        $excluded_exts = ['log', 'sql', 'zip', 'tar', 'gz', 'tgz', '7z', 'rar', 'bak', 'swp', 'tmp'];
        if (in_array($ext, $excluded_exts, true)) {
            return true;
        }

        return false;
    }

    /**
     * Recursively collect files within a directory with safety limits and exclusion filtering.
     *
     * @param string $dir_path
     * @return array|\WP_Error
     */
    protected function collect_directory_files($dir_path) {
        $files       = [];
        $total_size  = 0;
        $total_files = 0;

        $dir_iterator = new \RecursiveDirectoryIterator($dir_path, \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS);
        $filter_iterator = new \RecursiveCallbackFilterIterator($dir_iterator, function ($current, $key, $iterator) {
            $filename = $current->getFilename();
            if ($current->isDir()) {
                return !$this->is_excluded_item($filename, true);
            }
            return !$this->is_excluded_item($filename, false);
        });

        $iterator = new \RecursiveIteratorIterator($filter_iterator, \RecursiveIteratorIterator::LEAVES_ONLY);

        $base_len = strlen(rtrim(wp_normalize_path($dir_path), '/')) + 1;

        foreach ($iterator as $file_info) {
            if ($file_info->isFile()) {
                $total_files++;
                if ($total_files > self::MAX_FILES_LIMIT) {
                    return $this->error(
                        'too_many_files',
                        sprintf(esc_html__('Directory contains more than %d files. Safety limit exceeded.', 'woo-get-data-for-ai'), self::MAX_FILES_LIMIT),
                        413
                    );
                }

                $size = $file_info->getSize();
                $total_size += $size;
                if ($total_size > self::MAX_SIZE_BYTES) {
                    return $this->error(
                        'target_too_large',
                        sprintf(esc_html__('Directory total size exceeds %d MB. Safety limit exceeded.', 'woo-get-data-for-ai'), self::MAX_SIZE_BYTES / (1024 * 1024)),
                        413
                    );
                }

                $full_pathname = wp_normalize_path($file_info->getPathname());
                $rel_path      = substr($full_pathname, $base_len);

                $files[] = [
                    'relative_path' => $rel_path,
                    'full_path'     => $file_info->getPathname(),
                    'size'          => $size,
                    'modified_at'   => $file_info->getMTime(),
                ];
            }
        }

        return [
            'files'       => $files,
            'total_files' => $total_files,
            'total_size'  => $total_size,
        ];
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
                    if ($current->isDir()) {
                        return !$this->is_excluded_item($filename, true);
                    }
                    return !$this->is_excluded_item($filename, false);
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
