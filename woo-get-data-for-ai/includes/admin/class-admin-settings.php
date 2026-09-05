<?php
namespace WPAgentBridge\Admin;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Security;
use WPAgentBridge\Permissions;
use WPAgentBridge\Access_Logger;

class Admin_Settings {

    public function init() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        // AJAX handlers
        add_action('wp_ajax_agent_bridge_regenerate_token', [$this, 'ajax_regenerate_token']);
        add_action('wp_ajax_agent_bridge_clear_logs', [$this, 'ajax_clear_logs']);
        add_action('wp_ajax_agent_bridge_unlock_ip', [$this, 'ajax_unlock_ip']);
    }

    public function add_menu_page() {
        add_options_page(
            esc_html__('WP Agent Bridge Settings', 'woo-get-data-for-ai'),
            esc_html__('Agent Bridge', 'woo-get-data-for-ai'),
            'manage_options',
            'wp-agent-bridge',
            [$this, 'render_page']
        );
    }

    public function register_settings() {
        // Register permissions setting
        register_setting('agent_bridge_settings_group', 'wp_agent_bridge_permissions', [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_permissions'],
            'default'           => [],
        ]);

        // Register IP whitelist setting
        register_setting('agent_bridge_settings_group', 'wp_agent_bridge_ip_whitelist', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_textarea_field',
            'default'           => '',
        ]);

        // Register log retention setting
        register_setting('agent_bridge_settings_group', 'wp_agent_bridge_log_retention', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 500,
        ]);

        // Register Anti-Brute-Force settings
        register_setting('agent_bridge_settings_group', 'wp_agent_bridge_max_failed_attempts', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 5,
        ]);

        register_setting('agent_bridge_settings_group', 'wp_agent_bridge_lockout_duration', [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ]);
    }

    public function sanitize_permissions($input) {
        $clean = [];
        $valid_modules = array_keys(Permissions::get_module_definitions());
        if (is_array($input)) {
            foreach ($valid_modules as $mod) {
                $clean[$mod] = !empty($input[$mod]) ? 1 : 0;
            }
        }
        return $clean;
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'settings_page_wp-agent-bridge') {
            return;
        }

        wp_enqueue_style(
            'agent-bridge-admin-css',
            WOO_GET_DATA_AI_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WOO_GET_DATA_AI_VERSION
        );

        wp_enqueue_script(
            'agent-bridge-admin-js',
            WOO_GET_DATA_AI_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            WOO_GET_DATA_AI_VERSION,
            true
        );

        wp_localize_script('agent-bridge-admin-js', 'agentBridgeData', [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce('agent_bridge_admin_nonce'),
            'confirmRegen'  => esc_html__('Are you sure you want to regenerate the access token? Existing AI agents and CLI clients will immediately lose access until updated.', 'woo-get-data-for-ai'),
            'confirmClear'  => esc_html__('Are you sure you want to clear all connection logs?', 'woo-get-data-for-ai'),
            'confirmUnlock' => esc_html__('Are you sure you want to unlock this IP address immediately?', 'woo-get-data-for-ai'),
            'copiedText'    => esc_html__('Copied to clipboard!', 'woo-get-data-for-ai'),
        ]);
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        $tabs = [
            'general'     => esc_html__('General & Status', 'woo-get-data-for-ai'),
            'permissions' => esc_html__('Permissions Matrix', 'woo-get-data-for-ai'),
            'ai_prompt'   => esc_html__('AI Mega-Prompt & Skill', 'woo-get-data-for-ai'),
            'logs'        => esc_html__('Connection Logs & Analytics', 'woo-get-data-for-ai'),
        ];

        ?>
        <div class="wrap agent-bridge-wrap">
            <div class="agent-bridge-header">
                <div class="header-title-row">
                    <h1>
                        <span class="dashicons dashicons-rest-api"></span> 
                        <?php esc_html_e('WP Agent Bridge', 'woo-get-data-for-ai'); ?>
                        <span class="badge-version">v<?php echo esc_html(WOO_GET_DATA_AI_VERSION); ?></span>
                    </h1>
                    <div class="header-status-badge <?php echo Security::get_active_token() ? 'status-active' : 'status-warning'; ?>">
                        <span class="status-dot"></span>
                        <?php echo Security::get_active_token() ? esc_html__('Active & Protected', 'woo-get-data-for-ai') : esc_html__('Token Missing', 'woo-get-data-for-ai'); ?>
                    </div>
                </div>
                <p class="header-desc">
                    <?php esc_html_e('Secure, read-only inspection API for WordPress & WooCommerce to connect AI Agents (Antigravity, Claude, Cursor) and developer tools.', 'woo-get-data-for-ai'); ?>
                </p>
            </div>

            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $tab_id => $tab_title) : ?>
                    <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-agent-bridge', 'tab' => $tab_id], admin_url('options-general.php'))); ?>" 
                       class="nav-tab <?php echo $active_tab === $tab_id ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($tab_title); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="agent-bridge-tab-content">
                <?php
                switch ($active_tab) {
                    case 'permissions':
                        include WOO_GET_DATA_AI_PLUGIN_DIR . 'includes/admin/views/tab-permissions.php';
                        break;
                    case 'ai_prompt':
                        include WOO_GET_DATA_AI_PLUGIN_DIR . 'includes/admin/views/tab-ai-prompt.php';
                        break;
                    case 'logs':
                        include WOO_GET_DATA_AI_PLUGIN_DIR . 'includes/admin/views/tab-logs.php';
                        break;
                    case 'general':
                    default:
                        include WOO_GET_DATA_AI_PLUGIN_DIR . 'includes/admin/views/tab-general.php';
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    public function ajax_regenerate_token() {
        check_ajax_referer('agent_bridge_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized.', 'woo-get-data-for-ai')]);
        }

        if (Security::is_token_from_constant()) {
            wp_send_json_error([
                'message' => esc_html__('Token is set in wp-config.php via WP_AGENT_BRIDGE_TOKEN constant and cannot be regenerated from admin.', 'woo-get-data-for-ai')
            ]);
        }

        $new_token = wp_generate_password(64, true, true);
        update_option('wp_agent_bridge_token', $new_token, false);

        wp_send_json_success([
            'token'   => $new_token,
            'message' => esc_html__('Access token regenerated successfully.', 'woo-get-data-for-ai'),
        ]);
    }

    public function ajax_clear_logs() {
        check_ajax_referer('agent_bridge_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized.', 'woo-get-data-for-ai')]);
        }

        Access_Logger::purge_all();

        wp_send_json_success([
            'message' => esc_html__('All access logs have been cleared.', 'woo-get-data-for-ai'),
        ]);
    }

    public function ajax_unlock_ip() {
        check_ajax_referer('agent_bridge_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized.', 'woo-get-data-for-ai')]);
        }

        $ip = isset($_POST['ip']) ? sanitize_text_field(wp_unslash($_POST['ip'])) : '';
        if (empty($ip)) {
            wp_send_json_error(['message' => esc_html__('Missing IP parameter.', 'woo-get-data-for-ai')]);
        }

        Security::unlock_ip($ip);

        wp_send_json_success([
            'message' => sprintf(
                /* translators: %s: IP address */
                esc_html__('IP address %s has been unlocked.', 'woo-get-data-for-ai'),
                $ip
            ),
        ]);
    }
}
