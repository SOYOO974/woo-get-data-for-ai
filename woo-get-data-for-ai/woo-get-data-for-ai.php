<?php
/**
 * Plugin Name:       WP Agent Bridge (Data for AI)
 * Plugin URI:        https://github.com/SOYOO974/woo-get-data-for-ai
 * Description:       Enterprise-grade, read-only inspection API for WordPress & WooCommerce. Securely exposes system state, logs, Elementor trees, WPCode snippets, and theme options to AI agents (Antigravity, Claude, Cursor).
 * Version:           1.10.0
 * Author:            SOYOO
 * Author URI:        https://github.com/SOYOO974
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woo-get-data-for-ai
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define Plugin Constants
define('WOO_GET_DATA_AI_VERSION', '1.10.0');

define('WOO_GET_DATA_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_GET_DATA_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO_GET_DATA_AI_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WOO_GET_DATA_AI_GITHUB_REPO', 'https://github.com/SOYOO974/woo-get-data-for-ai/');

// Self-healing: On Linux/UNIX environments, repair any files inadvertently extracted with literal Windows backslashes
if (DIRECTORY_SEPARATOR === '/' && !file_exists(WOO_GET_DATA_AI_PLUGIN_DIR . 'includes/api/class-system-controller.php')) {
    $heal_paths = function ($dir, &$heal_paths) {
        if (!is_dir($dir)) {
            return;
        }
        $items = @scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full_path = $dir . '/' . $item;
            if (strpos($item, '\\') !== false) {
                $target_relative = str_replace('\\', '/', $item);
                $target_path = $dir . '/' . $target_relative;
                $target_dir = dirname($target_path);
                if (!is_dir($target_dir)) {
                    @mkdir($target_dir, 0755, true);
                }
                @rename($full_path, $target_path);
            } elseif (is_dir($full_path)) {
                $heal_paths($full_path, $heal_paths);
            }
        }
    };
    $heal_paths(WOO_GET_DATA_AI_PLUGIN_DIR, $heal_paths);
}

// Initialize Plugin Update Checker (PUC v5.6)
if (file_exists(WOO_GET_DATA_AI_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php')) {
    require_once WOO_GET_DATA_AI_PLUGIN_DIR . 'plugin-update-checker/plugin-update-checker.php';
    if (class_exists('YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
        $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            WOO_GET_DATA_AI_GITHUB_REPO,
            __FILE__,
            'woo-get-data-for-ai'
        );
        $updateChecker->setBranch('main');
        if (method_exists($updateChecker->getVcsApi(), 'enableReleaseAssets')) {
            $updateChecker->getVcsApi()->enableReleaseAssets();
        }
    }
}

// Autoloader for Plugin Classes (Namespace WPAgentBridge\)
spl_autoload_register(function ($class) {
    $prefix = 'WPAgentBridge\\';
    $base_dir = WOO_GET_DATA_AI_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $parts = explode('\\', $relative_class);
    $class_name = array_pop($parts);
    $sub_path = '';

    if (!empty($parts)) {
        $sub_path = strtolower(implode('/', $parts)) . '/';
    }

    $formatted_class_name = 'class-' . strtolower(str_replace('_', '-', $class_name)) . '.php';
    $file = $base_dir . $sub_path . $formatted_class_name;

    if (file_exists($file)) {
        require_once $file;
        return;
    }

    // Fallback 1: Literal backslash filename inside includes/ (Linux artifact from Windows-created ZIP)
    $backslash_sub = !empty($parts) ? strtolower(implode('\\', $parts)) . '\\' : '';
    $fallback_file = $base_dir . $backslash_sub . $formatted_class_name;
    if (file_exists($fallback_file)) {
        require_once $fallback_file;
        return;
    }

    // Fallback 2: Root-level flattened backslash filename
    $root_fallback = WOO_GET_DATA_AI_PLUGIN_DIR . 'includes\\' . $backslash_sub . $formatted_class_name;
    if (file_exists($root_fallback)) {
        require_once $root_fallback;
        return;
    }
});

// Ensure base REST controller is preloaded if available
if (file_exists(WOO_GET_DATA_AI_PLUGIN_DIR . 'includes/api/class-rest-controller.php')) {
    require_once WOO_GET_DATA_AI_PLUGIN_DIR . 'includes/api/class-rest-controller.php';
}

// Activation & Deactivation Hooks
register_activation_hook(__FILE__, function () {
    \WPAgentBridge\Activator::activate();
});

register_deactivation_hook(__FILE__, function () {
    \WPAgentBridge\Deactivator::deactivate();
});

// Run the Plugin
function woo_get_data_ai_init() {
    \WPAgentBridge\Plugin::instance()->run();
}
add_action('plugins_loaded', 'woo_get_data_ai_init');
