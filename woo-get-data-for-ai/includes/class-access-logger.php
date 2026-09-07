<?php
namespace WPAgentBridge;

if (!defined('ABSPATH')) {
    exit;
}

class Access_Logger {

    /**
     * Resolve country code and name based on IP.
     * Prioritizes Cloudflare headers for 0ms execution time.
     *
     * @param string $ip
     * @return array [code, name]
     */
    public static function resolve_country($ip) {
        // 1. Check Cloudflare Header
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            $code = strtoupper(sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_IPCOUNTRY'])));
            return [$code, self::country_code_to_name($code)];
        }

        // Localhost / Private IPs
        if (in_array($ip, ['127.0.0.1', '::1'], true) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['LOC', esc_html__('Local Network', 'woo-get-data-for-ai')];
        }

        // 2. Check Cached GeoIP transient
        $transient_key = 'agent_bridge_geo_' . md5($ip);
        $cached = get_transient($transient_key);
        if (false !== $cached && is_array($cached)) {
            return $cached;
        }

        // For non-Cloudflare, we return 'Unknown' immediately during the request to never lag the API,
        // and schedule an asynchronous lookup if desirable, or default to XX.
        $result = ['XX', esc_html__('Unknown', 'woo-get-data-for-ai')];
        set_transient($transient_key, $result, 30 * DAY_IN_SECONDS);
        return $result;
    }

    /**
     * Map 2-letter country code to readable name.
     *
     * @param string $code
     * @return string
     */
    public static function country_code_to_name($code) {
        $countries = [
            'FR' => 'France', 'RE' => 'La Réunion', 'US' => 'United States', 'CA' => 'Canada',
            'GB' => 'United Kingdom', 'DE' => 'Germany', 'ES' => 'Spain', 'IT' => 'Italy',
            'BE' => 'Belgium', 'CH' => 'Switzerland', 'NL' => 'Netherlands', 'AU' => 'Australia',
            'BR' => 'Brazil', 'IN' => 'India', 'JP' => 'Japan', 'SG' => 'Singapore',
            'MU' => 'Mauritius', 'MG' => 'Madagascar', 'MA' => 'Morocco', 'TN' => 'Tunisia',
            'SN' => 'Senegal', 'CI' => 'Ivory Coast', 'ZA' => 'South Africa', 'LOC' => 'Local'
        ];

        return isset($countries[$code]) ? $countries[$code] : $code;
    }

    /**
     * Determine if the plugin has registered at least one successful connection.
     * Caches result in wp_options for 0ms execution time on subsequent admin page loads.
     *
     * @return bool
     */
    public static function has_connected() {
        $connected = get_option('wp_agent_bridge_has_connected', null);
        if ($connected !== null) {
            return (bool) $connected;
        }

        // Fallback check against cumulative summary or existing logs on initial run
        $summary = get_option('wp_agent_bridge_log_summary', []);
        if (!empty($summary['total_requests']) && $summary['total_requests'] > 0) {
            update_option('wp_agent_bridge_has_connected', 1, false);
            return true;
        }

        global $wpdb;
        if (!isset($wpdb) || !is_object($wpdb)) {
            return false;
        }

        $table_name = $wpdb->prefix . 'agent_bridge_logs';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
        if ($table_exists === $table_name) {
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE http_status = 200 LIMIT 1");
            if ($count > 0) {
                update_option('wp_agent_bridge_has_connected', 1, false);
                return true;
            }
        }

        update_option('wp_agent_bridge_has_connected', 0, false);
        return false;
    }

    /**
     * Log a REST API request.
     *
     * @param string $endpoint
     * @param int $http_status
     * @return void
     */
    public static function log_request($endpoint, $http_status = 200) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'agent_bridge_logs';

        $ip = Security::get_client_ip();
        list($country_code, $country_name) = self::resolve_country($ip);
        $user_agent = !empty($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        $user_agent = substr($user_agent, 0, 255);

        $wpdb->insert(
            $table_name,
            [
                'ip'           => $ip,
                'country_code' => $country_code,
                'country_name' => $country_name,
                'endpoint'     => sanitize_text_field($endpoint),
                'http_status'  => (int) $http_status,
                'user_agent'   => $user_agent,
                'created_at'   => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        // Mark as connected on successful 200 OK request
        if ((int) $http_status === 200 && !get_option('wp_agent_bridge_has_connected', 0)) {
            update_option('wp_agent_bridge_has_connected', 1, false);
        }

        // Auto-cleaning trigger (1 in 20 requests)
        if (wp_rand(1, 20) === 1) {
            self::prune_old_logs();
        }
    }

    /**
     * Prune logs exceeding retention and save cumulative summaries.
     */
    public static function prune_old_logs() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'agent_bridge_logs';
        $retention = (int) get_option('wp_agent_bridge_log_retention', 500);

        $total_rows = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($total_rows > $retention) {
            $excess = $total_rows - $retention;

            // Fetch summary stats of the rows about to be deleted
            $old_rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT country_code, country_name, http_status FROM $table_name ORDER BY id ASC LIMIT %d",
                    $excess
                ),
                ARRAY_A
            );

            // Update cumulative historical summary in options
            $summary = get_option('wp_agent_bridge_log_summary', [
                'total_requests' => 0,
                'by_country'     => [],
                'by_status'      => [],
            ]);

            $summary['total_requests'] += count($old_rows);
            foreach ($old_rows as $row) {
                $c = !empty($row['country_code']) ? $row['country_code'] : 'XX';
                $s = !empty($row['http_status']) ? $row['http_status'] : 200;

                $summary['by_country'][$c] = isset($summary['by_country'][$c]) ? $summary['by_country'][$c] + 1 : 1;
                $summary['by_status'][$s]  = isset($summary['by_status'][$s]) ? $summary['by_status'][$s] + 1 : 1;
            }

            update_option('wp_agent_bridge_log_summary', $summary, false);

            // Delete excess rows
            $max_id_to_delete = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM $table_name ORDER BY id ASC LIMIT 1 OFFSET %d", $excess - 1)
            );

            if ($max_id_to_delete > 0) {
                $wpdb->query($wpdb->prepare("DELETE FROM $table_name WHERE id <= %d", $max_id_to_delete));
            }
        }
    }

    /**
     * Retrieve recent logs for admin display.
     *
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function get_recent_logs($limit = 50, $offset = 0) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'agent_bridge_logs';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY id DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            ),
            ARRAY_A
        );
    }

    /**
     * Get aggregate statistics for dashboard summary.
     *
     * @return array
     */
    public static function get_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'agent_bridge_logs';

        $total_active = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $summary = get_option('wp_agent_bridge_log_summary', ['total_requests' => 0, 'by_country' => []]);
        $total_all_time = $total_active + (isset($summary['total_requests']) ? (int) $summary['total_requests'] : 0);

        // Requests in last 24h
        $req_24h = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );

        // Requests in last 7 days
        $req_7d = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_name WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
        );

        // Top Countries
        $countries = $wpdb->get_results(
            "SELECT country_code, country_name, COUNT(*) as cnt FROM $table_name GROUP BY country_code, country_name ORDER BY cnt DESC LIMIT 5",
            ARRAY_A
        );

        // Top IPs
        $top_ips = $wpdb->get_results(
            "SELECT ip, country_code, COUNT(*) as cnt FROM $table_name GROUP BY ip, country_code ORDER BY cnt DESC LIMIT 5",
            ARRAY_A
        );

        return [
            'total_all_time' => $total_all_time,
            'req_24h'        => $req_24h,
            'req_7d'         => $req_7d,
            'countries'      => $countries,
            'top_ips'        => $top_ips,
        ];
    }

    /**
     * Purge all log history.
     */
    public static function purge_all() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'agent_bridge_logs';
        $wpdb->query("TRUNCATE TABLE $table_name");
        delete_option('wp_agent_bridge_log_summary');
    }
}
