<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

class Theme_Controller extends Rest_Controller {

    public function register_routes() {
        // GET /theme/options
        register_rest_route(self::NAMESPACE, '/theme/options', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_theme_options'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'theme');
            },
        ]);

        // GET /theme/overrides (WooCommerce template overrides)
        register_rest_route(self::NAMESPACE, '/theme/overrides', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_theme_overrides'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'wc_overrides');
            },
        ]);

        // GET /theme/child (Child theme functions.php & style.css)
        register_rest_route(self::NAMESPACE, '/theme/child', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_child_theme_code'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'theme');
            },
        ]);
    }

    public function get_theme_options(\WP_REST_Request $request) {
        $target = $request->get_param('target');
        $stylesheet = get_stylesheet();
        $response_data = [
            'active_theme' => wp_get_theme()->get('Name'),
            'stylesheet'   => $stylesheet,
        ];

        // 1. Woodmart Options
        if (empty($target) || $target === 'all' || $target === 'woodmart') {
            $woodmart_opts = get_option('xts-woodmart-options');
            if (empty($woodmart_opts)) {
                $woodmart_opts = get_option('woodmart_options');
            }
            if (!empty($woodmart_opts)) {
                $response_data['woodmart'] = is_string($woodmart_opts) ? json_decode($woodmart_opts, true) : $woodmart_opts;
            }
        }

        // 2. Elessi Options (Redux Framework / Customizer)
        if (empty($target) || $target === 'all' || $target === 'elessi') {
            $elessi_opts = get_option('elessi_options');
            if (!empty($elessi_opts)) {
                $response_data['elessi'] = is_string($elessi_opts) ? json_decode($elessi_opts, true) : $elessi_opts;
            }
        }

        // 3. General Theme Mods
        if (empty($target) || $target === 'all' || $target === 'mods') {
            $theme_mods = get_theme_mods();
            if (!empty($theme_mods)) {
                $response_data['theme_mods'] = $theme_mods;
            }
        }

        return $this->response($response_data);
    }

    public function get_theme_overrides(\WP_REST_Request $request) {
        if (!class_exists('WooCommerce')) {
            return $this->error(
                'woocommerce_not_active',
                esc_html__('WooCommerce is not active on this site.', 'woo-get-data-for-ai'),
                400
            );
        }

        $template_path = apply_filters('woocommerce_template_path', 'woocommerce/');
        $theme_dir = get_stylesheet_directory() . '/' . $template_path;
        $parent_dir = get_template_directory() . '/' . $template_path;

        $overrides = [];

        // Scan theme overrides
        $scan_dirs = [$theme_dir];
        if (is_child_theme() && $theme_dir !== $parent_dir) {
            $scan_dirs[] = $parent_dir;
        }

        foreach ($scan_dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $relative_path = str_replace([get_stylesheet_directory() . '/', get_template_directory() . '/'], '', $file->getPathname());
                    $file_content = file_get_contents($file->getPathname(), false, null, 0, 2048);

                    // Extract version from template header
                    $version = null;
                    if (preg_match('/@version\s+([0-9\.]+)/i', $file_content, $matches)) {
                        $version = $matches[1];
                    }

                    // Check core WC template version
                    $core_file = WC()->plugin_path() . '/templates/' . str_replace($template_path, '', $relative_path);
                    $core_version = null;
                    $is_outdated = false;

                    if (file_exists($core_file)) {
                        $core_content = file_get_contents($core_file, false, null, 0, 2048);
                        if (preg_match('/@version\s+([0-9\.]+)/i', $core_content, $core_matches)) {
                            $core_version = $core_matches[1];
                            if ($version && version_compare($version, $core_version, '<')) {
                                $is_outdated = true;
                            }
                        }
                    }

                    $overrides[] = [
                        'file'          => $relative_path,
                        'theme_version' => $version,
                        'core_version'  => $core_version,
                        'is_outdated'   => $is_outdated,
                        'modified_at'   => date('c', $file->getMTime()),
                    ];
                }
            }
        }

        return $this->response([
            'total_overrides' => count($overrides),
            'has_outdated'    => in_array(true, array_column($overrides, 'is_outdated'), true),
            'overrides'       => $overrides,
        ]);
    }

    public function get_child_theme_code(\WP_REST_Request $request) {
        $stylesheet_dir = get_stylesheet_directory();
        $is_child = is_child_theme();

        $functions_file = $stylesheet_dir . '/functions.php';
        $style_file = $stylesheet_dir . '/style.css';

        $data = [
            'is_child_theme'   => $is_child,
            'theme_name'       => wp_get_theme()->get('Name'),
            'stylesheet_path'  => $stylesheet_dir,
            'functions_php'    => null,
            'style_css'        => null,
        ];

        if (file_exists($functions_file) && is_readable($functions_file)) {
            $data['functions_php'] = [
                'size_bytes'   => filesize($functions_file),
                'modified_at'  => date('c', filemtime($functions_file)),
                'content'      => file_get_contents($functions_file),
            ];
        }

        if (file_exists($style_file) && is_readable($style_file)) {
            $data['style_css'] = [
                'size_bytes'   => filesize($style_file),
                'modified_at'  => date('c', filemtime($style_file)),
                'content'      => file_get_contents($style_file),
            ];
        }

        return $this->response($data);
    }
}
