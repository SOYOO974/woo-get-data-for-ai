<?php
if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Access_Logger;

$stats = Access_Logger::get_stats();
$recent_logs = Access_Logger::get_recent_logs(100);
?>

<div class="agent-bridge-stats-grid">
    <div class="stat-card">
        <span class="stat-icon dashicons dashicons-chart-area"></span>
        <div class="stat-content">
            <span class="stat-value"><?php echo number_format_i18n($stats['total_all_time']); ?></span>
            <span class="stat-label"><?php esc_html_e('Total API Requests (All-Time)', 'woo-get-data-for-ai'); ?></span>
        </div>
    </div>
    <div class="stat-card">
        <span class="stat-icon dashicons dashicons-clock"></span>
        <div class="stat-content">
            <span class="stat-value"><?php echo number_format_i18n($stats['req_24h']); ?></span>
            <span class="stat-label"><?php esc_html_e('Requests in Last 24 Hours', 'woo-get-data-for-ai'); ?></span>
        </div>
    </div>
    <div class="stat-card">
        <span class="stat-icon dashicons dashicons-calendar-alt"></span>
        <div class="stat-content">
            <span class="stat-value"><?php echo number_format_i18n($stats['req_7d']); ?></span>
            <span class="stat-label"><?php esc_html_e('Requests in Last 7 Days', 'woo-get-data-for-ai'); ?></span>
        </div>
    </div>
</div>

<div class="agent-bridge-grid-2col">
    <!-- Top Countries Widget -->
    <div class="agent-bridge-card">
        <h3><span class="dashicons dashicons-admin-site-alt3"></span> <?php esc_html_e('Requests by Country', 'woo-get-data-for-ai'); ?></h3>
        <?php if (!empty($stats['countries'])) : ?>
            <ul class="stats-list">
                <?php foreach ($stats['countries'] as $c) : ?>
                    <li>
                        <span class="country-pill">
                            <span class="country-code"><?php echo esc_html($c['country_code'] ?: 'XX'); ?></span>
                            <strong><?php echo esc_html($c['country_name'] ?: 'Unknown'); ?></strong>
                        </span>
                        <span class="badge-count"><?php echo number_format_i18n($c['cnt']); ?> <?php esc_html_e('reqs', 'woo-get-data-for-ai'); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p class="description"><?php esc_html_e('No country connection data recorded yet.', 'woo-get-data-for-ai'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Top IPs Widget -->
    <div class="agent-bridge-card">
        <h3><span class="dashicons dashicons-networking"></span> <?php esc_html_e('Top Client IPs', 'woo-get-data-for-ai'); ?></h3>
        <?php if (!empty($stats['top_ips'])) : ?>
            <ul class="stats-list">
                <?php foreach ($stats['top_ips'] as $ip) : ?>
                    <li>
                        <code><?php echo esc_html($ip['ip']); ?></code>
                        <span class="badge-count"><?php echo number_format_i18n($ip['cnt']); ?> <?php esc_html_e('reqs', 'woo-get-data-for-ai'); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p class="description"><?php esc_html_e('No client IP connection data recorded yet.', 'woo-get-data-for-ai'); ?></p>
        <?php endif; ?>
    </div>
</div>

<div class="agent-bridge-card">
    <div class="card-header-with-action">
        <div>
            <h3><?php esc_html_e('Recent API Connection Logs', 'woo-get-data-for-ai'); ?></h3>
            <p class="description">
                <?php esc_html_e('Detailed audit trail of the latest 100 API queries received.', 'woo-get-data-for-ai'); ?>
            </p>
        </div>
        <button type="button" class="button button-secondary btn-clear-logs">
            <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Clear Log History', 'woo-get-data-for-ai'); ?>
        </button>
    </div>

    <div class="table-responsive">
        <table class="wp-list-table widefat fixed striped table-logs">
            <thead>
                <tr>
                    <th style="width: 160px;"><?php esc_html_e('Date & Time', 'woo-get-data-for-ai'); ?></th>
                    <th style="width: 160px;"><?php esc_html_e('Client IP', 'woo-get-data-for-ai'); ?></th>
                    <th style="width: 140px;"><?php esc_html_e('Country', 'woo-get-data-for-ai'); ?></th>
                    <th><?php esc_html_e('Endpoint', 'woo-get-data-for-ai'); ?></th>
                    <th style="width: 90px; text-align: center;"><?php esc_html_e('Status', 'woo-get-data-for-ai'); ?></th>
                    <th style="width: 220px;"><?php esc_html_e('User-Agent', 'woo-get-data-for-ai'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recent_logs)) : ?>
                    <?php foreach ($recent_logs as $log) : ?>
                        <?php
                        $status_class = 'status-200';
                        if ($log['http_status'] >= 400 && $log['http_status'] < 500) {
                            $status_class = ($log['http_status'] === 403 || $log['http_status'] === 401) ? 'status-401' : 'status-400';
                        } elseif ($log['http_status'] >= 500) {
                            $status_class = 'status-500';
                        }
                        ?>
                        <tr>
                            <td><?php echo esc_html($log['created_at']); ?></td>
                            <td><code><?php echo esc_html($log['ip']); ?></code></td>
                            <td>
                                <span class="country-pill-sm">
                                    <span class="country-code"><?php echo esc_html($log['country_code'] ?: 'XX'); ?></span>
                                    <?php echo esc_html($log['country_name'] ?: 'Unknown'); ?>
                                </span>
                            </td>
                            <td><code><?php echo esc_html($log['endpoint']); ?></code></td>
                            <td style="text-align: center;">
                                <span class="http-badge <?php echo esc_attr($status_class); ?>">
                                    <?php echo esc_html($log['http_status']); ?>
                                </span>
                            </td>
                            <td><span class="ua-text" title="<?php echo esc_attr($log['user_agent']); ?>"><?php echo esc_html(substr($log['user_agent'], 0, 35)); ?>...</span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 25px;">
                            <?php esc_html_e('No connection logs recorded yet. Once your AI agent connects, entries will show up here in real time.', 'woo-get-data-for-ai'); ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
