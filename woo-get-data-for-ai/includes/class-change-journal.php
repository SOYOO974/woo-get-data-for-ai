<?php
namespace WPAgentBridge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Passive change journal used by /guardian/snapshot to explain anomalies.
 * Records plugin/theme/core updates, (de)activations, theme switches, payment gateway
 * settings changes (changed keys only, never values) and mu-plugins drift.
 * No notification, no e-mail, no external call.
 */
class Change_Journal {

    const OPTION         = 'wp_agent_bridge_change_journal';
    const MU_HASH_OPTION = 'wp_agent_bridge_mu_hashes';
    const MAIL_OPTION    = 'wp_agent_bridge_mail_failures';
    const CRON_HOOK      = 'wp_agent_bridge_mu_checksum';
    const MAX_ENTRIES    = 300;
    const MAX_MAIL       = 100;

    /**
     * Versions captured right before an upgrade overwrites the files.
     *
     * @var array<string, string>
     */
    private static $pre_versions = [];

    /**
     * Core version loaded at boot (the upgrade request still runs the old core).
     *
     * @var string
     */
    private static $boot_core_version = '';

    /**
     * Register passive hooks (each callback exits in O(1) when irrelevant).
     */
    public static function init() {
        self::$boot_core_version = isset($GLOBALS['wp_version']) ? (string) $GLOBALS['wp_version'] : '';

        add_filter('upgrader_pre_install', [__CLASS__, 'capture_pre_version'], 10, 2);
        add_action('upgrader_process_complete', [__CLASS__, 'on_upgrade'], 10, 2);
        add_action('activated_plugin', [__CLASS__, 'on_activated_plugin'], 10, 1);
        add_action('deactivated_plugin', [__CLASS__, 'on_deactivated_plugin'], 10, 1);
        add_action('switch_theme', [__CLASS__, 'on_switch_theme'], 10, 3);
        add_action('updated_option', [__CLASS__, 'on_updated_option'], 10, 3);
        add_action('wp_mail_failed', [__CLASS__, 'on_mail_failed'], 10, 1);
        add_action(self::CRON_HOOK, [__CLASS__, 'check_mu_plugins']);

        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    /**
     * Unschedule the daily mu-plugins checksum event.
     */
    public static function unschedule() {
        $ts = wp_next_scheduled(self::CRON_HOOK);
        if ($ts) {
            wp_unschedule_event($ts, self::CRON_HOOK);
        }
    }

    /**
     * Resolve who triggered the change.
     *
     * @param mixed $upgrader
     * @return int|string
     */
    private static function actor($upgrader = null) {
        if (defined('WP_CLI') && WP_CLI) {
            return 'wp-cli';
        }
        if (is_object($upgrader) && isset($upgrader->skin) && $upgrader->skin instanceof \Automatic_Upgrader_Skin) {
            return 'auto';
        }
        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return 'cron';
        }
        $user_id = get_current_user_id();
        return $user_id ? (int) $user_id : 'auto';
    }

    /**
     * Append one entry to the FIFO journal (non-autoloaded option).
     *
     * @param array $entry
     */
    public static function log(array $entry) {
        $journal = get_option(self::OPTION, []);
        if (!is_array($journal)) {
            $journal = [];
        }

        $journal[] = array_merge([
            'at'     => gmdate('Y-m-d\TH:i:s\Z'),
            'type'   => '',
            'object' => '',
            'from'   => null,
            'to'     => null,
            'actor'  => self::actor(),
        ], $entry);

        if (count($journal) > self::MAX_ENTRIES) {
            $journal = array_slice($journal, -self::MAX_ENTRIES);
        }

        update_option(self::OPTION, $journal, false);
    }

    /**
     * Journal entries recorded at or after a UTC timestamp.
     *
     * @param int $since_ts
     * @return array
     */
    public static function get_since($since_ts) {
        $journal = get_option(self::OPTION, []);
        if (!is_array($journal)) {
            return [];
        }

        return array_values(array_filter($journal, function ($e) use ($since_ts) {
            return is_array($e) && !empty($e['at']) && strtotime($e['at']) >= $since_ts;
        }));
    }

    /**
     * Read the installed version of a plugin file or theme slug.
     *
     * @param string $kind plugin|theme
     * @param string $id
     * @return string|null
     */
    private static function read_version($kind, $id) {
        if ($kind === 'plugin') {
            $file = WP_PLUGIN_DIR . '/' . $id;
            if (!is_readable($file)) {
                return null;
            }
            $data = get_file_data($file, ['Version' => 'Version']);
            return !empty($data['Version']) ? (string) $data['Version'] : null;
        }

        $theme = wp_get_theme($id);
        return $theme->exists() ? (string) $theme->get('Version') : null;
    }

    /**
     * Capture the version on disk before the package overwrites it.
     *
     * @param mixed $response
     * @param array $hook_extra
     * @return mixed
     */
    public static function capture_pre_version($response, $hook_extra) {
        if (!empty($hook_extra['plugin']) && is_string($hook_extra['plugin'])) {
            self::$pre_versions['plugin:' . $hook_extra['plugin']] = self::read_version('plugin', $hook_extra['plugin']);
        } elseif (!empty($hook_extra['theme']) && is_string($hook_extra['theme'])) {
            self::$pre_versions['theme:' . $hook_extra['theme']] = self::read_version('theme', $hook_extra['theme']);
        }
        return $response;
    }

    /**
     * upgrader_process_complete: plugin, theme or core update.
     *
     * @param mixed $upgrader
     * @param array $hook_extra
     */
    public static function on_upgrade($upgrader, $hook_extra) {
        if (!is_array($hook_extra) || empty($hook_extra['type']) || ($hook_extra['action'] ?? '') !== 'update') {
            return;
        }

        $type  = $hook_extra['type'];
        $actor = self::actor($upgrader);

        if ($type === 'core') {
            $new = null;
            $version_file = ABSPATH . WPINC . '/version.php';
            if (is_readable($version_file) && preg_match('/\$wp_version\s*=\s*\'([^\']+)\'/', (string) file_get_contents($version_file), $m)) {
                $new = $m[1];
            }
            self::log(['type' => 'core_update', 'object' => 'wordpress', 'from' => self::$boot_core_version ?: null, 'to' => $new, 'actor' => $actor]);
            return;
        }

        if ($type === 'plugin' || $type === 'theme') {
            $items = [];
            if ($type === 'plugin') {
                $items = !empty($hook_extra['plugins']) ? (array) $hook_extra['plugins'] : (!empty($hook_extra['plugin']) ? [$hook_extra['plugin']] : []);
            } else {
                $items = !empty($hook_extra['themes']) ? (array) $hook_extra['themes'] : (!empty($hook_extra['theme']) ? [$hook_extra['theme']] : []);
            }

            foreach ($items as $item) {
                $item   = (string) $item;
                $object = $item;
                if ($type === 'plugin' && dirname($item) !== '.') {
                    $object = dirname($item);
                }
                self::log([
                    'type'   => $type . '_update',
                    'object' => $object,
                    'from'   => self::$pre_versions[$type . ':' . $item] ?? null,
                    'to'     => self::read_version($type, $item),
                    'actor'  => $actor,
                ]);
            }
        }
    }

    /**
     * @param string $plugin
     */
    public static function on_activated_plugin($plugin) {
        self::log(['type' => 'plugin_activated', 'object' => (string) $plugin, 'to' => self::read_version('plugin', (string) $plugin)]);
    }

    /**
     * @param string $plugin
     */
    public static function on_deactivated_plugin($plugin) {
        self::log(['type' => 'plugin_deactivated', 'object' => (string) $plugin, 'from' => self::read_version('plugin', (string) $plugin)]);
    }

    /**
     * @param string    $new_name
     * @param \WP_Theme $new_theme
     * @param \WP_Theme $old_theme
     */
    public static function on_switch_theme($new_name, $new_theme = null, $old_theme = null) {
        self::log([
            'type'   => 'theme_switched',
            'object' => 'theme',
            'from'   => ($old_theme instanceof \WP_Theme) ? $old_theme->get_stylesheet() : null,
            'to'     => ($new_theme instanceof \WP_Theme) ? $new_theme->get_stylesheet() : (string) $new_name,
        ]);
    }

    /**
     * Payment gateway settings: log changed keys only, never values (may hold secrets).
     *
     * @param string $option
     * @param mixed  $old_value
     * @param mixed  $value
     */
    public static function on_updated_option($option, $old_value, $value) {
        // Fast exit: this action fires on every update_option() call.
        if (strncmp($option, 'woocommerce_', 12) !== 0 || substr($option, -9) !== '_settings') {
            return;
        }

        $gateway_id = substr($option, 12, -9);
        if ($gateway_id === '' || !self::is_payment_gateway($gateway_id)) {
            return;
        }

        $old_value = is_array($old_value) ? $old_value : [];
        $value     = is_array($value) ? $value : [];
        $changed   = [];
        foreach (array_unique(array_merge(array_keys($old_value), array_keys($value))) as $key) {
            if (!array_key_exists($key, $old_value) || !array_key_exists($key, $value) || $old_value[$key] !== $value[$key]) {
                $changed[] = (string) $key;
            }
        }

        if (empty($changed)) {
            return;
        }

        self::log(['type' => 'gateway_settings', 'object' => $gateway_id, 'keys' => array_slice($changed, 0, 50)]);
    }

    /**
     * @param string $gateway_id
     * @return bool
     */
    private static function is_payment_gateway($gateway_id) {
        if (function_exists('WC') && WC() && isset(WC()->payment_gateways) && is_object(WC()->payment_gateways)) {
            $gateways = WC()->payment_gateways->payment_gateways();
            if (is_array($gateways) && !empty($gateways)) {
                return isset($gateways[$gateway_id]);
            }
        }
        // Fallback heuristic when gateways are not loaded in this request.
        return (bool) preg_match('/(stripe|alma|paypal|ppcp|mollie|payplug|monetico|systempay|klarna|scalapay|viva|paygreen|lyra|bacs|cheque|cod)/i', $gateway_id);
    }

    /**
     * Daily cron: compare md5 of wp-content/mu-plugins/*.php against the stored baseline.
     */
    public static function check_mu_plugins() {
        if (!defined('WPMU_PLUGIN_DIR') || !is_dir(WPMU_PLUGIN_DIR)) {
            return;
        }

        $current = [];
        foreach ((array) glob(WPMU_PLUGIN_DIR . '/*.php') as $file) {
            if (is_file($file) && is_readable($file)) {
                $current[basename($file)] = md5_file($file);
            }
        }

        $previous = get_option(self::MU_HASH_OPTION, null);
        update_option(self::MU_HASH_OPTION, $current, false);

        // First run only stores the baseline.
        if (!is_array($previous)) {
            return;
        }

        foreach ($current as $name => $hash) {
            if (!isset($previous[$name])) {
                self::log(['type' => 'mu_plugin_added', 'object' => $name, 'to' => $hash, 'actor' => 'cron']);
            } elseif ($previous[$name] !== $hash) {
                self::log(['type' => 'mu_plugin_modified', 'object' => $name, 'from' => $previous[$name], 'to' => $hash, 'actor' => 'cron']);
            }
        }
        foreach ($previous as $name => $hash) {
            if (!isset($current[$name])) {
                self::log(['type' => 'mu_plugin_removed', 'object' => $name, 'from' => $hash, 'actor' => 'cron']);
            }
        }
    }

    /**
     * wp_mail_failed: store date, truncated subject and error, never the recipient.
     *
     * @param \WP_Error $error
     */
    public static function on_mail_failed($error) {
        if (!is_wp_error($error)) {
            return;
        }

        $data    = $error->get_error_data();
        $subject = is_array($data) && isset($data['subject']) ? (string) $data['subject'] : '';

        $log = get_option(self::MAIL_OPTION, []);
        if (!is_array($log)) {
            $log = [];
        }

        $log[] = [
            'at'      => gmdate('Y-m-d\TH:i:s\Z'),
            'subject' => self::scrub(mb_substr(wp_strip_all_tags($subject), 0, 80)),
            'error'   => self::scrub(mb_substr(wp_strip_all_tags($error->get_error_message()), 0, 200)),
        ];

        if (count($log) > self::MAX_MAIL) {
            $log = array_slice($log, -self::MAX_MAIL);
        }

        update_option(self::MAIL_OPTION, $log, false);
    }

    /**
     * Remove e-mail addresses and phone numbers from free text.
     *
     * @param string $text
     * @return string
     */
    public static function scrub($text) {
        $text = preg_replace('/[a-zA-Z0-9_.+\-]+@[a-zA-Z0-9\-]+\.[a-zA-Z0-9\-.]+/', '[email]', (string) $text);
        $text = preg_replace('/\+\d[\d\s.\-]{7,}\d/', '[phone]', $text);
        return preg_replace('/\b0[1-9](?:[\s.\-]?\d{2}){4}\b/', '[phone]', $text);
    }
}
