<?php
namespace WPAgentBridge;

if (!defined('ABSPATH')) {
    exit;
}

class Activator {

    /**
     * Run activation tasks: create database table and set default settings.
     */
    public static function activate() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'agent_bridge_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ip varchar(45) NOT NULL,
            country_code varchar(10) NOT NULL DEFAULT '',
            country_name varchar(100) NOT NULL DEFAULT '',
            endpoint varchar(255) NOT NULL,
            http_status smallint(5) NOT NULL DEFAULT 200,
            user_agent text NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY created_at (created_at),
            KEY ip (ip)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Generate default 64-char token if not set or non-compliant
        $existing_token = get_option('wp_agent_bridge_token');
        if (empty($existing_token) || !Security::is_valid_token_format($existing_token)) {
            $new_token = Security::generate_token();
            update_option('wp_agent_bridge_token', $new_token, false);
        }

        // Set default permissions matrix (all enabled by default)
        if (false === get_option('wp_agent_bridge_permissions')) {
            $default_permissions = [
                'system'       => 1,
                'wc_overrides' => 1,
                'theme'        => 1,
                'code'         => 1,
                'elementor'    => 1,
                'wpcode'       => 1,
                'logs'         => 1,
            ];
            update_option('wp_agent_bridge_permissions', $default_permissions, false);
        }

        // Set default retention
        if (false === get_option('wp_agent_bridge_log_retention')) {
            update_option('wp_agent_bridge_log_retention', 500, false);
        }
    }
}
