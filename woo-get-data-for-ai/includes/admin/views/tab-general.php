<?php
if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Security;

$active_token = Security::get_active_token();
$is_constant = Security::is_token_from_constant();
$rest_base_url = rest_url('agent-bridge/v1');
$ip_whitelist = get_option('wp_agent_bridge_ip_whitelist', '');
$log_retention = get_option('wp_agent_bridge_log_retention', 500);
$max_attempts = get_option('wp_agent_bridge_max_failed_attempts', 5);
$lockout_duration = get_option('wp_agent_bridge_lockout_duration', 30);
$locked_ips = Security::get_locked_ips();

if (isset($_POST['agent_bridge_save_general']) && check_admin_referer('agent_bridge_save_general_nonce')) {
    if (isset($_POST['wp_agent_bridge_ip_whitelist'])) {
        update_option('wp_agent_bridge_ip_whitelist', sanitize_textarea_field(wp_unslash($_POST['wp_agent_bridge_ip_whitelist'])));
    }
    if (isset($_POST['wp_agent_bridge_log_retention'])) {
        update_option('wp_agent_bridge_log_retention', absint($_POST['wp_agent_bridge_log_retention']));
    }
    if (isset($_POST['wp_agent_bridge_max_failed_attempts'])) {
        update_option('wp_agent_bridge_max_failed_attempts', max(3, absint($_POST['wp_agent_bridge_max_failed_attempts'])));
    }
    if (isset($_POST['wp_agent_bridge_lockout_duration'])) {
        update_option('wp_agent_bridge_lockout_duration', max(5, absint($_POST['wp_agent_bridge_lockout_duration'])));
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'woo-get-data-for-ai') . '</p></div>';
    $ip_whitelist = get_option('wp_agent_bridge_ip_whitelist', '');
    $log_retention = get_option('wp_agent_bridge_log_retention', 500);
    $max_attempts = get_option('wp_agent_bridge_max_failed_attempts', 5);
    $lockout_duration = get_option('wp_agent_bridge_lockout_duration', 30);
    $locked_ips = Security::get_locked_ips();
}
?>

<div class="agent-bridge-card">
    <h3><?php esc_html_e('API Connection & Authentication', 'woo-get-data-for-ai'); ?></h3>
    <p class="description">
        <?php esc_html_e('Use these credentials to connect your local development environment or AI assistants to this WordPress installation.', 'woo-get-data-for-ai'); ?>
    </p>

    <div class="setting-field-group">
        <label><strong><?php esc_html_e('REST API Base URL', 'woo-get-data-for-ai'); ?></strong></label>
        <div class="input-with-button">
            <input type="text" readonly value="<?php echo esc_url($rest_base_url); ?>" id="agent-bridge-base-url" class="regular-text code">
            <button type="button" class="button btn-copy" data-target="agent-bridge-base-url">
                <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy URL', 'woo-get-data-for-ai'); ?>
            </button>
        </div>
        <p class="description"><?php esc_html_e('All inspection endpoints are available under this root URL.', 'woo-get-data-for-ai'); ?></p>
    </div>

    <div class="setting-field-group">
        <label><strong><?php esc_html_e('Bearer Access Token', 'woo-get-data-for-ai'); ?></strong></label>
        <div class="input-with-button">
            <input type="password" readonly value="<?php echo esc_attr($active_token); ?>" id="agent-bridge-token-input" class="regular-text code token-input">
            <button type="button" class="button btn-toggle-visibility" data-target="agent-bridge-token-input">
                <span class="dashicons dashicons-visibility"></span> <?php esc_html_e('Show', 'woo-get-data-for-ai'); ?>
            </button>
            <button type="button" class="button btn-copy" data-target="agent-bridge-token-input">
                <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy Token', 'woo-get-data-for-ai'); ?>
            </button>
            <?php if (!$is_constant) : ?>
                <button type="button" class="button button-secondary btn-regenerate-token">
                    <span class="dashicons dashicons-update"></span> <?php esc_html_e('Regenerate Token', 'woo-get-data-for-ai'); ?>
                </button>
            <?php endif; ?>
        </div>

        <?php if ($is_constant) : ?>
            <p class="description notice-inline notice-info">
                <span class="dashicons dashicons-info"></span> 
                <?php esc_html_e('Token is defined securely in wp-config.php via WP_AGENT_BRIDGE_TOKEN constant.', 'woo-get-data-for-ai'); ?>
            </p>
        <?php else : ?>
            <p class="description">
                <?php esc_html_e('Cryptographically secure 64-character hexadecimal token (RFC 6750 compliant, shell-safe). Keep it private. Click Regenerate at any time to invalidate old credentials.', 'woo-get-data-for-ai'); ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Anti-Brute-Force & Lockout Monitor -->
<div class="agent-bridge-card">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 8px;">
        <h3 style="margin: 0;">
            <span class="dashicons dashicons-shield"></span> 
            <?php esc_html_e('Anti-Brute-Force Protection & Lockouts', 'woo-get-data-for-ai'); ?>
        </h3>
        <button type="button" class="button button-secondary btn-reset-failures">
            <span class="dashicons dashicons-image-rotate"></span> <?php esc_html_e('Reset All Failures & Unblock All IPs', 'woo-get-data-for-ai'); ?>
        </button>
    </div>
    <p class="description">
        <?php esc_html_e('Protects your site against unauthorized automated attempts to guess your Bearer token. Any IP that submits wrong tokens repeatedly is automatically locked out.', 'woo-get-data-for-ai'); ?>
    </p>

    <div class="lockout-status-box">
        <?php if (!empty($locked_ips)) : ?>
            <div class="notice-inline notice-warning" style="margin-bottom: 15px; width: 100%;">
                <span class="dashicons dashicons-warning"></span>
                <strong><?php echo sprintf(esc_html__('%d IP address(es) currently locked out.', 'woo-get-data-for-ai'), count($locked_ips)); ?></strong>
            </div>
            <table class="wp-list-table widefat fixed striped table-locked-ips">
                <thead>
                    <tr>
                        <th style="width: 180px;"><?php esc_html_e('Locked IP', 'woo-get-data-for-ai'); ?></th>
                        <th style="width: 180px;"><?php esc_html_e('Locked Since', 'woo-get-data-for-ai'); ?></th>
                        <th><?php esc_html_e('Remaining Lockout', 'woo-get-data-for-ai'); ?></th>
                        <th style="width: 120px; text-align: right;"><?php esc_html_e('Action', 'woo-get-data-for-ai'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($locked_ips as $lip => $ldata) : ?>
                        <tr id="locked-row-<?php echo esc_attr(md5($lip)); ?>">
                            <td><code><?php echo esc_html($lip); ?></code></td>
                            <td><?php echo esc_html($ldata['locked_at']); ?></td>
                            <td>
                                <span class="badge-lockout-time">
                                    <?php echo sprintf(esc_html__('%d minute(s) remaining', 'woo-get-data-for-ai'), $ldata['remaining_minutes']); ?>
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <button type="button" class="button button-small btn-unlock-ip" data-ip="<?php echo esc_attr($lip); ?>" data-row="locked-row-<?php echo esc_attr(md5($lip)); ?>">
                                    <span class="dashicons dashicons-unlock"></span> <?php esc_html_e('Unlock IP', 'woo-get-data-for-ai'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <div class="lockout-clean-state">
                <span class="dashicons dashicons-yes-alt" style="color: #2e7d32; font-size: 24px; vertical-align: middle;"></span>
                <span><?php esc_html_e('No IP addresses currently locked out. Your inspection API is secure.', 'woo-get-data-for-ai'); ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>

<form method="post" action="">
    <?php wp_nonce_field('agent_bridge_save_general_nonce'); ?>
    <div class="agent-bridge-card">
        <h3><?php esc_html_e('Security & Restrictions Settings', 'woo-get-data-for-ai'); ?></h3>

        <div class="setting-field-group">
            <label for="wp_agent_bridge_max_failed_attempts"><strong><?php esc_html_e('Max Failed Attempts Before Lockout', 'woo-get-data-for-ai'); ?></strong></label>
            <input type="number" name="wp_agent_bridge_max_failed_attempts" id="wp_agent_bridge_max_failed_attempts" value="<?php echo esc_attr($max_attempts); ?>" min="3" max="20" step="1" class="small-text">
            <p class="description">
                <?php esc_html_e('Number of invalid authentication attempts permitted from an IP address before it is temporarily blocked (default: 5 attempts).', 'woo-get-data-for-ai'); ?>
            </p>
        </div>

        <div class="setting-field-group">
            <label for="wp_agent_bridge_lockout_duration"><strong><?php esc_html_e('Lockout Duration (Minutes)', 'woo-get-data-for-ai'); ?></strong></label>
            <input type="number" name="wp_agent_bridge_lockout_duration" id="wp_agent_bridge_lockout_duration" value="<?php echo esc_attr($lockout_duration); ?>" min="5" max="1440" step="5" class="small-text">
            <p class="description">
                <?php esc_html_e('Duration in minutes for which an offending IP remains completely blocked from querying the API (default: 30 minutes).', 'woo-get-data-for-ai'); ?>
            </p>
        </div>

        <div class="setting-field-group">
            <label for="wp_agent_bridge_ip_whitelist"><strong><?php esc_html_e('IP Whitelist (Optional)', 'woo-get-data-for-ai'); ?></strong></label>
            <textarea name="wp_agent_bridge_ip_whitelist" id="wp_agent_bridge_ip_whitelist" rows="4" class="large-text code" placeholder="192.168.1.50, 203.0.113.19"><?php echo esc_textarea($ip_whitelist); ?></textarea>
            <p class="description">
                <?php esc_html_e('Comma or line-separated list of allowed IP addresses. Leave empty to allow any client presenting the valid Bearer token.', 'woo-get-data-for-ai'); ?>
            </p>
        </div>

        <div class="setting-field-group">
            <label for="wp_agent_bridge_log_retention"><strong><?php esc_html_e('Connection Log Retention (Max Entries)', 'woo-get-data-for-ai'); ?></strong></label>
            <input type="number" name="wp_agent_bridge_log_retention" id="wp_agent_bridge_log_retention" value="<?php echo esc_attr($log_retention); ?>" min="50" max="2000" step="50" class="small-text">
            <p class="description">
                <?php esc_html_e('Maximum number of recent connection logs kept in the database. Older entries are automatically summarized into cumulative statistics to save database storage.', 'woo-get-data-for-ai'); ?>
            </p>
        </div>

        <p class="submit">
            <input type="submit" name="agent_bridge_save_general" class="button button-primary" value="<?php esc_attr_e('Save Security Settings', 'woo-get-data-for-ai'); ?>">
        </p>
    </div>
</form>
