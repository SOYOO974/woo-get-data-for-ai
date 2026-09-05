<?php
namespace WPAgentBridge;

if (!defined('ABSPATH')) {
    exit;
}

class Security {

    /**
     * Get the active token.
     * Prioritizes WP_AGENT_BRIDGE_TOKEN constant if defined, otherwise uses the DB option.
     *
     * @return string
     */
    public static function get_active_token() {
        if (defined('WP_AGENT_BRIDGE_TOKEN') && !empty(WP_AGENT_BRIDGE_TOKEN)) {
            return (string) WP_AGENT_BRIDGE_TOKEN;
        }

        return (string) get_option('wp_agent_bridge_token', '');
    }

    /**
     * Check whether the active token comes from wp-config.php constant.
     *
     * @return bool
     */
    public static function is_token_from_constant() {
        return defined('WP_AGENT_BRIDGE_TOKEN') && !empty(WP_AGENT_BRIDGE_TOKEN);
    }

    /**
     * Detect client IP address reliably, taking into account Cloudflare and reverse proxies.
     *
     * @return string
     */
    public static function get_client_ip() {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            return sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
        }

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', sanitize_text_field(wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR'])));
            return trim($ips[0]);
        }

        if (!empty($_SERVER['REMOTE_ADDR'])) {
            return sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
        }

        return '127.0.0.1';
    }

    /**
     * Check if a client IP is currently locked out due to failed attempts.
     *
     * @param string $ip
     * @return array|false Returns lockout info array or false if not locked.
     */
    public static function is_ip_locked($ip) {
        $ip_hash = md5($ip);
        $lock_key = 'agent_bridge_lock_' . $ip_hash;
        $expires_at = get_transient($lock_key);

        if (false !== $expires_at) {
            $remaining_seconds = max(1, (int) $expires_at - time());
            $remaining_minutes = max(1, (int) ceil($remaining_seconds / 60));

            return [
                'locked'            => true,
                'remaining_seconds' => $remaining_seconds,
                'remaining_minutes' => $remaining_minutes,
            ];
        }

        return false;
    }

    /**
     * Record a failed authentication attempt for an IP.
     *
     * @param string $ip
     * @return bool True if the IP was just locked out, false otherwise.
     */
    public static function record_failed_attempt($ip) {
        $ip_hash = md5($ip);
        $fail_key = 'agent_bridge_fail_' . $ip_hash;
        $max_attempts = max(3, (int) get_option('wp_agent_bridge_max_failed_attempts', 5));
        $lockout_duration_mins = max(5, (int) get_option('wp_agent_bridge_lockout_duration', 30));

        $attempts = (int) get_transient($fail_key);
        $attempts++;

        if ($attempts >= $max_attempts) {
            // Lock out IP
            $lockout_seconds = $lockout_duration_mins * 60;
            $expires_at = time() + $lockout_seconds;

            set_transient('agent_bridge_lock_' . $ip_hash, $expires_at, $lockout_seconds);
            delete_transient($fail_key);

            // Save to locked IPs registry for admin visibility
            $locked_ips = (array) get_option('wp_agent_bridge_locked_ips', []);
            $locked_ips[$ip] = [
                'ip'           => $ip,
                'locked_at'    => current_time('mysql'),
                'expires_at'   => date('Y-m-d H:i:s', $expires_at),
                'attempts'     => $attempts,
            ];
            update_option('wp_agent_bridge_locked_ips', $locked_ips, false);

            return true;
        }

        // Store attempt count with 15 minutes window
        set_transient($fail_key, $attempts, 15 * 60);

        return false;
    }

    /**
     * Get remaining attempts before lockout.
     *
     * @param string $ip
     * @return int
     */
    public static function get_remaining_attempts($ip) {
        $ip_hash = md5($ip);
        $max_attempts = max(3, (int) get_option('wp_agent_bridge_max_failed_attempts', 5));
        $attempts = (int) get_transient('agent_bridge_fail_' . $ip_hash);

        return max(0, $max_attempts - $attempts);
    }

    /**
     * Reset failed attempts upon successful authentication.
     *
     * @param string $ip
     */
    public static function reset_failed_attempts($ip) {
        $ip_hash = md5($ip);
        delete_transient('agent_bridge_fail_' . $ip_hash);
    }

    /**
     * Unlock a locked-out IP manually from admin.
     *
     * @param string $ip
     * @return bool
     */
    public static function unlock_ip($ip) {
        $ip_hash = md5($ip);
        delete_transient('agent_bridge_lock_' . $ip_hash);
        delete_transient('agent_bridge_fail_' . $ip_hash);

        $locked_ips = (array) get_option('wp_agent_bridge_locked_ips', []);
        if (isset($locked_ips[$ip])) {
            unset($locked_ips[$ip]);
            update_option('wp_agent_bridge_locked_ips', $locked_ips, false);
            return true;
        }

        return false;
    }

    /**
     * Retrieve active locked IPs with cleanup of expired ones.
     *
     * @return array
     */
    public static function get_locked_ips() {
        $locked_ips = (array) get_option('wp_agent_bridge_locked_ips', []);
        $active_locks = [];
        $has_changes = false;

        foreach ($locked_ips as $ip => $data) {
            $lock_info = self::is_ip_locked($ip);
            if ($lock_info && !empty($lock_info['locked'])) {
                $data['remaining_minutes'] = $lock_info['remaining_minutes'];
                $active_locks[$ip] = $data;
            } else {
                $has_changes = true; // expired
            }
        }

        if ($has_changes) {
            update_option('wp_agent_bridge_locked_ips', $active_locks, false);
        }

        return $active_locks;
    }

    /**
     * Verify incoming REST request:
     * 1. Method must be GET.
     * 2. Anti-Brute-Force check: IP must not be locked out.
     * 3. IP Whitelist check.
     * 4. Bearer Token must match.
     * 5. Rate limit check.
     *
     * @param \WP_REST_Request $request
     * @return true|\WP_Error
     */
    public static function verify_request(\WP_REST_Request $request) {
        $client_ip = self::get_client_ip();

        // 1. Enforce 100% Read-Only: Only GET allowed
        if ($request->get_method() !== 'GET') {
            return new \WP_Error(
                'rest_forbidden_method',
                esc_html__('Only GET (Read-Only) requests are permitted by WP Agent Bridge.', 'woo-get-data-for-ai'),
                ['status' => 405]
            );
        }

        // 2. Anti-Brute-Force Lockout check
        $lock_info = self::is_ip_locked($client_ip);
        if ($lock_info && !empty($lock_info['locked'])) {
            return new \WP_Error(
                'agent_bridge_ip_locked',
                sprintf(
                    /* translators: %d: remaining minutes */
                    esc_html__('Too many failed authentication attempts. Your IP has been temporarily locked out for %d more minute(s).', 'woo-get-data-for-ai'),
                    $lock_info['remaining_minutes']
                ),
                ['status' => 429]
            );
        }

        // 3. IP Whitelist Check (if configured)
        $whitelist = get_option('wp_agent_bridge_ip_whitelist', '');
        if (!empty($whitelist)) {
            $allowed_ips = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $whitelist)));
            if (!empty($allowed_ips) && !in_array($client_ip, $allowed_ips, true)) {
                return new \WP_Error(
                    'agent_bridge_ip_forbidden',
                    esc_html__('Access denied: Client IP is not in the authorized whitelist.', 'woo-get-data-for-ai'),
                    ['status' => 403]
                );
            }
        }

        $active_token = self::get_active_token();
        if (empty($active_token)) {
            return new \WP_Error(
                'agent_bridge_no_token',
                esc_html__('No Agent Bridge token configured on this server.', 'woo-get-data-for-ai'),
                ['status' => 401]
            );
        }

        // 4. Extract Authorization header
        $auth_header = '';
        if ($request->get_header('authorization')) {
            $auth_header = $request->get_header('authorization');
        } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth_header = sanitize_text_field(wp_unslash($_SERVER['HTTP_AUTHORIZATION']));
        } elseif (!empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $auth_header = sanitize_text_field(wp_unslash($_SERVER['REDIRECT_HTTP_AUTHORIZATION']));
        } elseif (function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            if (isset($headers['Authorization'])) {
                $auth_header = $headers['Authorization'];
            } elseif (isset($headers['authorization'])) {
                $auth_header = $headers['authorization'];
            }
        }

        if (empty($auth_header) || !preg_match('/Bearer\s+(\S+)/i', $auth_header, $matches)) {
            self::record_failed_attempt($client_ip);
            return new \WP_Error(
                'agent_bridge_unauthorized',
                esc_html__('Missing or invalid Authorization Bearer header.', 'woo-get-data-for-ai'),
                ['status' => 401]
            );
        }

        $provided_token = $matches[1];

        // 5. Timing-attack safe comparison
        if (!hash_equals($active_token, $provided_token)) {
            $just_locked = self::record_failed_attempt($client_ip);
            if ($just_locked) {
                $duration = (int) get_option('wp_agent_bridge_lockout_duration', 30);
                return new \WP_Error(
                    'agent_bridge_ip_locked',
                    sprintf(
                        /* translators: %d: lockout duration in minutes */
                        esc_html__('Too many failed authentication attempts. Your IP has been locked out for %d minutes.', 'woo-get-data-for-ai'),
                        $duration
                    ),
                    ['status' => 429]
                );
            }

            $remaining = self::get_remaining_attempts($client_ip);
            return new \WP_Error(
                'agent_bridge_invalid_token',
                sprintf(
                    /* translators: %d: remaining attempts count */
                    esc_html__('Invalid authentication token. %d attempt(s) remaining before temporary lockout.', 'woo-get-data-for-ai'),
                    $remaining
                ),
                ['status' => 401]
            );
        }

        // On successful authentication, reset fail counter
        self::reset_failed_attempts($client_ip);

        // 6. Rate Limiting: Max 120 requests/minute per client IP
        $rate_key = 'agent_bridge_rate_' . md5($client_ip);
        $req_count = (int) get_transient($rate_key);

        if ($req_count > 120) {
            return new \WP_Error(
                'agent_bridge_rate_limited',
                esc_html__('Too many requests. Please throttle your queries (limit: 120/min).', 'woo-get-data-for-ai'),
                ['status' => 429]
            );
        }

        set_transient($rate_key, $req_count + 1, 60);

        return true;
    }
}
