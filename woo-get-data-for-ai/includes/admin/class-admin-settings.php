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
        add_action('admin_notices', [$this, 'render_onboarding_notice']);

        // AJAX handlers
        add_action('wp_ajax_agent_bridge_regenerate_token', [$this, 'ajax_regenerate_token']);
        add_action('wp_ajax_agent_bridge_clear_logs', [$this, 'ajax_clear_logs']);
        add_action('wp_ajax_agent_bridge_unlock_ip', [$this, 'ajax_unlock_ip']);
        add_action('wp_ajax_agent_bridge_reset_failures', [$this, 'ajax_reset_failures']);
        add_action('wp_ajax_agent_bridge_dismiss_onboarding', [$this, 'ajax_dismiss_onboarding']);

        // Action links on plugins list page
        add_filter('plugin_action_links_' . WOO_GET_DATA_AI_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);
    }

    /**
     * Add Settings link under plugin name in Plugins list.
     *
     * @param array $links
     * @return array
     */
    public function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url(admin_url('options-general.php?page=wp-agent-bridge')),
            esc_html__('Settings', 'woo-get-data-for-ai')
        );
        array_unshift($links, $settings_link);
        return $links;
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

    public function should_show_onboarding_notice() {
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Hide notice if an AI has already connected to the site
        if (Access_Logger::has_connected()) {
            return false;
        }

        // Hide notice if the administrator explicitly dismissed it
        if (get_user_meta(get_current_user_id(), 'wp_agent_bridge_onboarding_dismissed', true)) {
            return false;
        }

        return true;
    }

    public function enqueue_assets($hook) {
        $is_plugin_page = ($hook === 'settings_page_wp-agent-bridge');
        $show_notice    = $this->should_show_onboarding_notice();

        if (!$is_plugin_page && !$show_notice) {
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
            'confirmReset'  => esc_html__('Are you sure you want to reset all lockout counters and unblock all IP addresses?', 'woo-get-data-for-ai'),
            'copiedText'    => esc_html__('Copied to clipboard!', 'woo-get-data-for-ai'),
        ]);
    }

    /**
     * Render the first-time onboarding notice in WP Admin.
     */
    public function render_onboarding_notice() {
        if (!$this->should_show_onboarding_notice()) {
            return;
        }

        // Avoid showing global notice on the plugin's own dashboard page (which displays the hero card)
        $current_screen = get_current_screen();
        if ($current_screen && $current_screen->id === 'settings_page_wp-agent-bridge') {
            return;
        }

        $site_name = get_bloginfo('name');
        $setup_url = admin_url('options-general.php?page=wp-agent-bridge&tab=ai_prompt');
        $example_prompt = esc_attr__('Audit my site\'s frontend performance, identify slow plugins, and check database bloat to find quick optimization wins.', 'woo-get-data-for-ai');
        ?>
        <div class="notice notice-info is-dismissible agent-bridge-onboarding-notice" data-nonce="<?php echo esc_attr(wp_create_nonce('agent_bridge_admin_nonce')); ?>">
            <div class="agent-bridge-notice-content">
                <div class="notice-icon-col">
                    <span class="dashicons dashicons-rest-api notice-main-icon"></span>
                </div>
                <div class="notice-body-col">
                    <div class="notice-header-row">
                        <h3 class="notice-headline">
                            <?php
                            printf(
                                /* translators: %s: site name */
                                esc_html__('Connect your AI to %s in 2 clicks', 'woo-get-data-for-ai'),
                                '<strong>' . esc_html($site_name) . '</strong>'
                            );
                            ?>
                        </h3>
                        <span class="badge-read-only">
                            <span class="dashicons dashicons-shield"></span> 
                            <?php esc_html_e('100% Read-Only & Safe', 'woo-get-data-for-ai'); ?>
                        </span>
                    </div>
                    <p class="notice-description">
                        <?php esc_html_e('WP Agent Bridge is active and ready. Safely connect your AI assistant (Antigravity, Claude, Cursor, ChatGPT) to inspect and diagnose your site with zero risk to your data or orders.', 'woo-get-data-for-ai'); ?>
                    </p>
                    <div class="notice-prompt-box">
                        <span class="prompt-box-label">
                            <span class="dashicons dashicons-lightbulb"></span> 
                            <strong><?php esc_html_e('High-value prompt to try with your AI:', 'woo-get-data-for-ai'); ?></strong>
                        </span>
                        <div class="prompt-box-snippet">
                            <code>&ldquo;<?php echo esc_html($example_prompt); ?>&rdquo;</code>
                            <button type="button" class="button button-small btn-copy-prompt" data-prompt="<?php echo esc_attr($example_prompt); ?>">
                                <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy Prompt', 'woo-get-data-for-ai'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="notice-actions-row">
                        <a href="<?php echo esc_url($setup_url); ?>" class="button button-primary notice-btn-connect">
                            <span class="dashicons dashicons-admin-plugins"></span> <?php esc_html_e('Connect My AI in 2 Clicks', 'woo-get-data-for-ai'); ?>
                        </a>
                        <a href="<?php echo esc_url(admin_url('options-general.php?page=wp-agent-bridge&tab=documentation')); ?>" class="button button-secondary">
                            <?php esc_html_e('View Documentation', 'woo-get-data-for-ai'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    public function render_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        $tabs = [
            'general'       => esc_html__('General & Status', 'woo-get-data-for-ai'),
            'permissions'   => esc_html__('Permissions Matrix', 'woo-get-data-for-ai'),
            'ai_prompt'     => esc_html__('AI Mega-Prompt & Skill', 'woo-get-data-for-ai'),
            'logs'          => esc_html__('Connection Logs & Analytics', 'woo-get-data-for-ai'),
            'documentation' => esc_html__('Documentation & Guide', 'woo-get-data-for-ai'),
        ];

        ?>
        <div class="wrap agent-bridge-wrap">
            <h1 class="wp-heading-inline screen-reader-text"><?php esc_html_e('WP Agent Bridge', 'woo-get-data-for-ai'); ?></h1>
            <hr class="wp-header-end">

            <div class="agent-bridge-header">
                <div class="header-title-row">
                    <h2 class="header-title">
                        <span class="dashicons dashicons-rest-api"></span> 
                        <?php esc_html_e('WP Agent Bridge', 'woo-get-data-for-ai'); ?>
                        <span class="badge-version">v<?php echo esc_html(WOO_GET_DATA_AI_VERSION); ?></span>
                    </h2>
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
                    case 'documentation':
                        include WOO_GET_DATA_AI_PLUGIN_DIR . 'includes/admin/views/tab-docs.php';
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

        $new_token = Security::generate_token();
        update_option('wp_agent_bridge_token', $new_token, false);

        // Reset any failed attempts for current admin IP upon token generation
        Security::reset_failed_attempts(Security::get_client_ip());

        wp_send_json_success([
            'token'   => $new_token,
            'message' => esc_html__('Access token regenerated successfully.', 'woo-get-data-for-ai'),
        ]);
    }

    public function ajax_reset_failures() {
        check_ajax_referer('agent_bridge_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized.', 'woo-get-data-for-ai')]);
        }

        Security::reset_all_lockouts_and_failures();

        wp_send_json_success([
            'message' => esc_html__('All failed attempts and IP lockouts have been reset successfully.', 'woo-get-data-for-ai'),
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

    public function ajax_dismiss_onboarding() {
        check_ajax_referer('agent_bridge_admin_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => esc_html__('Unauthorized.', 'woo-get-data-for-ai')]);
        }

        update_user_meta(get_current_user_id(), 'wp_agent_bridge_onboarding_dismissed', 1);

        wp_send_json_success([
            'message' => esc_html__('Onboarding notice dismissed.', 'woo-get-data-for-ai'),
        ]);
    }
}
