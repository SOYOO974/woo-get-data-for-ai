<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Security;
use WPAgentBridge\Permissions;
use WPAgentBridge\Redaction;
use WPAgentBridge\Access_Logger;

abstract class Rest_Controller {

    /**
     * REST API Namespace.
     */
    const NAMESPACE = 'agent-bridge/v1';

    /**
     * Register routes for this controller.
     */
    abstract public function register_routes();

    /**
     * Check authentication, permissions and log request.
     *
     * @param \WP_REST_Request $request
     * @param string $module_key
     * @return true|\WP_Error
     */
    public function check_access(\WP_REST_Request $request, $module_key = '') {
        $endpoint = $request->get_route();

        // 1. Verify Security (Bearer token, method, rate limit, IP)
        $security_check = Security::verify_request($request);
        if (is_wp_error($security_check)) {
            $status = $security_check->get_error_data()['status'] ?? 401;
            Access_Logger::log_request($endpoint, $status);
            return $security_check;
        }

        // 2. Verify Module Permission (if endpoint is part of a module)
        if (!empty($module_key)) {
            $perm_check = Permissions::check_module_permission($module_key);
            if (is_wp_error($perm_check)) {
                Access_Logger::log_request($endpoint, 403);
                return $perm_check;
            }
        }

        // Log successful access
        Access_Logger::log_request($endpoint, 200);

        return true;
    }

    /**
     * Return a sanitized and redacted JSON response.
     *
     * @param mixed $data
     * @param int $status
     * @return \WP_REST_Response
     */
    protected function response($data, $status = 200) {
        // Redact any sensitive tokens, passwords, or customer PII before sending
        $sanitized_data = Redaction::redact_data($data);

        return new \WP_REST_Response($sanitized_data, $status);
    }

    /**
     * Return a standardized WP_Error response.
     *
     * @param string $code
     * @param string $message
     * @param int $status
     * @return \WP_Error
     */
    protected function error($code, $message, $status = 400) {
        return new \WP_Error($code, $message, ['status' => $status]);
    }

    /**
     * Known prefixes for WordPress plugins and their representative slugs/names.
     *
     * @var array<string, array{slug: string, name: string}>
     */
    private static $known_plugin_prefixes = [
        'wpassetcleanup_' => ['slug' => 'wp-asset-clean-up', 'name' => 'WP Asset CleanUp'],
        'wpacu_'          => ['slug' => 'wp-asset-clean-up', 'name' => 'WP Asset CleanUp'],
        'elementor_'      => ['slug' => 'elementor', 'name' => 'Elementor'],
        '_elementor_'     => ['slug' => 'elementor', 'name' => 'Elementor'],
        'woocommerce_'    => ['slug' => 'woocommerce', 'name' => 'WooCommerce'],
        'wc_'             => ['slug' => 'woocommerce', 'name' => 'WooCommerce'],
        'wpcode_'         => ['slug' => 'insert-headers-and-footers', 'name' => 'WPCode'],
        'rank_math_'      => ['slug' => 'seo-by-rank-math', 'name' => 'Rank Math SEO'],
        'wpseo_'          => ['slug' => 'wordpress-seo', 'name' => 'Yoast SEO'],
        'wpforms_'        => ['slug' => 'wpforms-lite', 'name' => 'WPForms'],
        'flw_'            => ['slug' => 'flowmattic', 'name' => 'FlowMattic'],
        'flowmattic_'     => ['slug' => 'flowmattic', 'name' => 'FlowMattic'],
        'fluentmail_'     => ['slug' => 'fluent-smtp', 'name' => 'FluentSMTP'],
        'wp_mail_smtp'    => ['slug' => 'wp-mail-smtp', 'name' => 'WP Mail SMTP'],
        'postman_'        => ['slug' => 'post-smtp', 'name' => 'Post SMTP'],
        'updraft_'        => ['slug' => 'updraftplus', 'name' => 'UpdraftPlus'],
        'wordfence_'      => ['slug' => 'wordfence', 'name' => 'Wordfence Security'],
        'itsec_'          => ['slug' => 'better-wp-security', 'name' => 'Solid Security (iThemes)'],
        'litespeed_'      => ['slug' => 'litespeed-cache', 'name' => 'LiteSpeed Cache'],
        'w3tc_'           => ['slug' => 'w3-total-cache', 'name' => 'W3 Total Cache'],
        'wp_rocket_'      => ['slug' => 'wp-rocket', 'name' => 'WP Rocket'],
        'autoptimize_'    => ['slug' => 'autoptimize', 'name' => 'Autoptimize'],
        'aioseo_'         => ['slug' => 'all-in-one-seo-pack', 'name' => 'All in One SEO'],
        'shortpixel_'     => ['slug' => 'shortpixel-image-optimiser', 'name' => 'ShortPixel'],
        'smush_'          => ['slug' => 'wp-smushit', 'name' => 'Smush'],
        'imagify_'        => ['slug' => 'imagify', 'name' => 'Imagify'],
        'complianz_'      => ['slug' => 'complianz-gdpr', 'name' => 'Complianz'],
        'cookie_notice_'  => ['slug' => 'cookie-notice', 'name' => 'Cookie Notice'],
        'polylang_'       => ['slug' => 'polylang', 'name' => 'Polylang'],
        'icl_'            => ['slug' => 'sitepress-multilingual-cms', 'name' => 'WPML'],
        'acf_'            => ['slug' => 'advanced-custom-fields', 'name' => 'ACF'],
        'fs_'             => ['slug' => 'flexible-shipping', 'name' => 'Flexible Shipping'],
    ];

    /**
     * Cross-reference an option name with active/installed plugins to detect orphaned candidates.
     *
     * @param string $option_name
     * @return array
     */
    public static function analyze_orphaned_option($option_name) {
        if (empty($option_name) || !is_string($option_name)) {
            return [
                'is_orphaned_candidate' => false,
                'related_plugin_status' => 'unknown',
                'related_plugin_name'   => null,
                'hint'                  => null,
            ];
        }

        if (strpos($option_name, '_transient_') === 0 || strpos($option_name, '_site_transient_') === 0) {
            return [
                'is_orphaned_candidate' => false,
                'related_plugin_status' => 'transient',
                'related_plugin_name'   => null,
                'hint'                  => null,
            ];
        }

        static $active_plugins_cache = null;
        static $all_plugins_cache = null;

        if ($active_plugins_cache === null) {
            $active_plugins_cache = (array) get_option('active_plugins', []);
            if (is_multisite()) {
                $sitewide = (array) get_site_option('active_sitewide_plugins', []);
                $active_plugins_cache = array_merge($active_plugins_cache, array_keys($sitewide));
            }
        }

        if ($all_plugins_cache === null) {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            $all_plugins_cache = function_exists('get_plugins') ? get_plugins() : [];
        }

        $check_slug = function($target_slug) use ($active_plugins_cache, $all_plugins_cache) {
            $is_active = false;
            $is_installed = false;
            $plugin_name = null;

            foreach ($active_plugins_cache as $file) {
                if (strpos($file, $target_slug . '/') === 0 || strpos($file, $target_slug . '.') === 0) {
                    $is_active = true;
                    $is_installed = true;
                    break;
                }
            }

            foreach ($all_plugins_cache as $file => $data) {
                if (strpos($file, $target_slug . '/') === 0 || strpos($file, $target_slug . '.') === 0) {
                    $is_installed = true;
                    if (empty($plugin_name) && !empty($data['Name'])) {
                        $plugin_name = $data['Name'];
                    }
                    break;
                }
            }

            return [$is_active, $is_installed, $plugin_name];
        };

        foreach (self::$known_plugin_prefixes as $prefix => $meta) {
            if (strpos($option_name, $prefix) === 0) {
                list($is_active, $is_installed, $detected_name) = $check_slug($meta['slug']);
                $name = $detected_name ?: $meta['name'];

                if ($is_active) {
                    return [
                        'is_orphaned_candidate' => false,
                        'related_plugin_status' => 'active',
                        'related_plugin_name'   => $name,
                        'hint'                  => null,
                    ];
                }

                $status = $is_installed ? 'inactive' : 'uninstalled';
                $hint = sprintf(
                    'The associated plugin "%s" appears to be %s. If no longer needed, this option can safely be set to autoload="no" or removed.',
                    $name,
                    $status
                );

                return [
                    'is_orphaned_candidate' => true,
                    'related_plugin_status' => $status,
                    'related_plugin_name'   => $name,
                    'hint'                  => $hint,
                ];
            }
        }

        $parts = explode('_', $option_name);
        if (count($parts) >= 2 && strlen($parts[0]) >= 3) {
            $candidate_prefix = str_replace('_', '-', $parts[0]);
            foreach ($all_plugins_cache as $file => $data) {
                $plugin_slug = dirname($file);
                if ($plugin_slug !== '.' && (strpos($plugin_slug, $candidate_prefix) === 0 || strpos($plugin_slug, $parts[0]) === 0)) {
                    $is_active = in_array($file, $active_plugins_cache, true);
                    $name = !empty($data['Name']) ? $data['Name'] : $plugin_slug;

                    if ($is_active) {
                        return [
                            'is_orphaned_candidate' => false,
                            'related_plugin_status' => 'active',
                            'related_plugin_name'   => $name,
                            'hint'                  => null,
                        ];
                    }

                    return [
                        'is_orphaned_candidate' => true,
                        'related_plugin_status' => 'inactive',
                        'related_plugin_name'   => $name,
                        'hint'                  => sprintf(
                            'The associated plugin "%s" appears to be inactive. Consider setting autoload="no" or deleting this option.',
                            $name
                        ),
                    ];
                }
            }
        }

        return [
            'is_orphaned_candidate' => false,
            'related_plugin_status' => 'unknown',
            'related_plugin_name'   => null,
            'hint'                  => null,
        ];
    }
}
