<?php
namespace WPAgentBridge;

if (!defined('ABSPATH')) {
    exit;
}

class Deactivator {

    /**
     * Run deactivation tasks.
     * Note: We keep the token, permissions, and logs intact to avoid data loss on accidental deactivation.
     */
    public static function deactivate() {
        // Delete temporary rate limit transients
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_agent_bridge_%' OR option_name LIKE '_transient_timeout_agent_bridge_%'");
    }
}
