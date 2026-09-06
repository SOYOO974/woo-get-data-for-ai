<?php
/**
 * Plugin Name:       WP Agent Bridge (Data for AI)
 * Plugin URI:        https://github.com/SOYOO974/woo-get-data-for-ai
 * Description:       Enterprise-grade, read-only inspection API for WordPress & WooCommerce. Securely exposes system state, logs, Elementor trees, WPCode snippets, and theme options to AI agents (Antigravity, Claude, Cursor).
 * Version:           1.7.0
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
define('WOO_GET_DATA_AI_VERSION', '1.7.0');

define('WOO_GET_DATA_AI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_GET_DATA_AI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO_GET_DATA_AI_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('WOO_GET_DATA_AI_GITHUB_REPO', 'https://github.com/SOYOO974/woo-get-data-for-ai/');

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
    }
});

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
