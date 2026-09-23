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

        // GET /theme/custom-css (Aggregated custom CSS from Customizer, Woodmart, and Child Theme)
        register_rest_route(self::NAMESPACE, '/theme/custom-css', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_theme_custom_css'],
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

        // Retrieve Woodmart options if target allows
        $woodmart_opts = null;
        $woodmart_decoded = null;
        if (empty($target) || in_array($target, ['all', 'woodmart', 'js'], true)) {
            $woodmart_opts = get_option('xts-woodmart-options');
            if (empty($woodmart_opts)) {
                $woodmart_opts = get_option('woodmart_options');
            }
            if (!empty($woodmart_opts)) {
                $woodmart_decoded = is_string($woodmart_opts) ? json_decode($woodmart_opts, true) : (is_array($woodmart_opts) ? $woodmart_opts : null);
            }
        }

        // 1. Woodmart Options
        if (empty($target) || $target === 'all' || $target === 'woodmart') {
            if (!empty($woodmart_decoded)) {
                $response_data['woodmart'] = $woodmart_decoded;
            }
        }

        // 2. Woodmart Custom JS blocks (custom_js and js_ready)
        if (empty($target) || in_array($target, ['all', 'woodmart', 'js'], true)) {
            if (!empty($woodmart_decoded) && is_array($woodmart_decoded)) {
                $custom_js = isset($woodmart_decoded['custom_js']) ? (string) $woodmart_decoded['custom_js'] : '';
                $js_ready  = isset($woodmart_decoded['js_ready']) ? (string) $woodmart_decoded['js_ready'] : '';
                if ($custom_js !== '' || $js_ready !== '') {
                    $response_data['woodmart_custom_js'] = [
                        'custom_js' => $custom_js,
                        'js_ready'  => $js_ready,
                    ];
                }
            }
        }

        // 3. Elessi Options (Redux Framework / Customizer)
        if (empty($target) || $target === 'all' || $target === 'elessi') {
            $elessi_opts = get_option('elessi_options');
            if (!empty($elessi_opts)) {
                $response_data['elessi'] = is_string($elessi_opts) ? json_decode($elessi_opts, true) : $elessi_opts;
            }
        }

        // 4. General Theme Mods (Customizer)
        if (empty($target) || $target === 'all' || $target === 'mods' || $target === 'customizer') {
            $theme_mods = get_theme_mods();
            if (!empty($theme_mods) && is_array($theme_mods)) {
                if (function_exists('wp_get_custom_css')) {
                    $theme_mods['custom_css'] = (string) wp_get_custom_css();
                }
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

        if ($is_child) {
            if (file_exists($style_file) && is_readable($style_file)) {
                $data['style_css'] = [
                    'size_bytes'   => filesize($style_file),
                    'modified_at'  => date('c', filemtime($style_file)),
                    'content'      => file_get_contents($style_file),
                ];
            }
        } else {
            $data['style_css'] = null;
            $data['style_css_notice'] = 'parent_theme_not_streamed';
        }

        return $this->response($data);
    }

    public function get_theme_custom_css(\WP_REST_Request $request) {
        // 1. Customizer Custom CSS
        $custom_css_post = function_exists('wp_get_custom_css_post') ? wp_get_custom_css_post() : null;
        $customizer_content = function_exists('wp_get_custom_css') ? (string) wp_get_custom_css() : '';
        $customizer_post_id = null;
        $customizer_modified = null;

        if ($custom_css_post instanceof \WP_Post) {
            $customizer_post_id = (int) $custom_css_post->ID;
            if (!empty($custom_css_post->post_modified)) {
                $customizer_modified = mysql2date('c', $custom_css_post->post_modified, false);
            }
            if (empty($customizer_content) && !empty($custom_css_post->post_content)) {
                $customizer_content = (string) $custom_css_post->post_content;
            }
        } else {
            $theme_mod_id = get_theme_mod('custom_css_post_id');
            if (!empty($theme_mod_id) && is_numeric($theme_mod_id) && (int) $theme_mod_id > 0) {
                $customizer_post_id = (int) $theme_mod_id;
                $post = get_post($customizer_post_id);
                if ($post instanceof \WP_Post) {
                    $customizer_modified = mysql2date('c', $post->post_modified, false);
                    if (empty($customizer_content)) {
                        $customizer_content = (string) $post->post_content;
                    }
                }
            }
        }

        $customizer_data = [
            'post_id'     => $customizer_post_id,
            'modified_at' => $customizer_modified,
            'size_bytes'  => strlen($customizer_content),
            'content'     => $customizer_content,
        ];

        // 2. Woodmart Custom CSS (if Woodmart is active or options exist)
        $woodmart_opts = get_option('xts-woodmart-options');
        if (empty($woodmart_opts)) {
            $woodmart_opts = get_option('woodmart_options');
        }
        if (is_string($woodmart_opts)) {
            $woodmart_opts = json_decode($woodmart_opts, true);
        }

        $is_woodmart = (
            strtolower(wp_get_theme()->get_template()) === 'woodmart' ||
            strtolower(wp_get_theme()->get_stylesheet()) === 'woodmart' ||
            defined('WOODMART_THEME_DIR') ||
            (!empty($woodmart_opts) && is_array($woodmart_opts))
        );

        $woodmart_data = null;
        if ($is_woodmart && is_array($woodmart_opts)) {
            $woodmart_data = [
                'global'  => isset($woodmart_opts['custom_css']) ? (string) $woodmart_opts['custom_css'] : '',
                'desktop' => isset($woodmart_opts['css_desktop']) ? (string) $woodmart_opts['css_desktop'] : '',
                'tablet'  => isset($woodmart_opts['css_tablet']) ? (string) $woodmart_opts['css_tablet'] : '',
                'mobile'  => isset($woodmart_opts['css_mobile']) ? (string) $woodmart_opts['css_mobile'] : '',
            ];
        }

        // 3. Child Theme style.css (only if is_child_theme() is true)
        $is_child = is_child_theme();
        $child_style_content = null;

        if ($is_child) {
            $child_style_file = get_stylesheet_directory() . '/style.css';
            if (file_exists($child_style_file) && is_readable($child_style_file)) {
                $child_style_content = file_get_contents($child_style_file);
            }
        }

        $child_theme_data = [
            'active'    => $is_child,
            'style_css' => $child_style_content,
        ];

        return $this->response([
            'customizer'  => $customizer_data,
            'woodmart'    => $woodmart_data,
            'child_theme' => $child_theme_data,
        ]);
    }
}
