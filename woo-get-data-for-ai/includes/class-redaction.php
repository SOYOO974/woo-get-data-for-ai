<?php
namespace WPAgentBridge;

if (!defined('ABSPATH')) {
    exit;
}

class Redaction {

    /**
     * Regex patterns for sensitive values.
     *
     * @var array
     */
    protected static $patterns = [
        // Stripe live keys
        '/(sk_live_[0-9a-zA-Z]{24,})/i'                       => '[REDACTED_STRIPE_SECRET]',
        '/(rk_live_[0-9a-zA-Z]{24,})/i'                       => '[REDACTED_STRIPE_RESTRICTED]',
        // Bearer tokens
        '/(Bearer\s+)[A-Za-z0-9_\-\.~+\/=]{20,}/i'            => '$1[REDACTED_BEARER_TOKEN]',
        // Basic auth strings
        '/(Basic\s+)[A-Za-z0-9+\/=]{20,}/i'                   => '$1[REDACTED_BASIC_AUTH]',
        // WP Config DB Password in errors
        '/(define\s*\(\s*[\'"]DB_PASSWORD[\'"]\s*,\s*[\'"])[^\'"]+([\'"]\s*\))/i' => '$1[REDACTED_DB_PASSWORD]$2',
        // WP Salts
        '/(define\s*\(\s*[\'"][A-Z_]*SALT[\'"]\s*,\s*[\'"])[^\'"]+([\'"]\s*\))/i' => '$1[REDACTED_SALT]$2',
        '/(define\s*\(\s*[\'"][A-Z_]*KEY[\'"]\s*,\s*[\'"])[^\'"]+([\'"]\s*\))/i'  => '$1[REDACTED_AUTH_KEY]$2',
        // Common webhook secrets
        '/(whsec_[0-9a-zA-Z]{24,})/i'                         => '[REDACTED_WEBHOOK_SECRET]',
    ];

    /**
     * Sensitive key names to automatically redact in arrays/options.
     *
     * @var array
     */
    protected static $sensitive_key_fragments = [
        'secret',
        'password',
        'passwd',
        'api_key',
        'apikey',
        'access_token',
        'private_key',
        'auth_token',
        'jwt',
        'consumer_secret',
        'consumer_key',
        'consumer_email',
        'webhook_secret',
        'license_key',
        'smtp_pass',
        'db_password',
    ];

    /**
     * Whitelist for keys that match fragments but are not sensitive.
     *
     * @var array
     */
    protected static $safe_keys = [
        'primary_key',
        'foreign_key',
        'keyboard_shortcuts',
        'key_metrics',
    ];

    /**
     * Check if a key name is considered sensitive.
     *
     * @param string $key
     * @return bool
     */
    public static function is_sensitive_key($key) {
        $lower_key = strtolower((string) $key);

        if (in_array($lower_key, self::$safe_keys, true)) {
            return false;
        }

        foreach (self::$sensitive_key_fragments as $fragment) {
            if (strpos($lower_key, $fragment) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Redact sensitive text (logs, strings).
     *
     * @param string $text
     * @param bool $redact_emails
     * @return string
     */
    public static function redact_string($text, $redact_emails = true) {
        if (!is_string($text) || empty($text)) {
            return $text;
        }

        // Apply predefined regex patterns
        foreach (self::$patterns as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        // Mask emails if requested (PII protection)
        if ($redact_emails) {
            $text = preg_replace_callback(
                '/[a-zA-Z0-9_.+-]+@([a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+)/',
                function ($matches) {
                    return '[REDACTED_EMAIL@' . $matches[1] . ']';
                },
                $text
            );
        }

        return $text;
    }

    /**
     * Recursively redact an array (e.g. theme options, settings).
     *
     * @param mixed $data
     * @return mixed
     */
    public static function redact_data($data) {
        if (is_array($data)) {
            $cleaned = [];
            foreach ($data as $k => $v) {
                if (self::is_sensitive_key($k)) {
                    $cleaned[$k] = is_string($v) && strlen($v) > 0 ? '[REDACTED_SECRET]' : $v;
                } else {
                    $cleaned[$k] = self::redact_data($v);
                }
            }
            return $cleaned;
        } elseif (is_object($data)) {
            $cleaned = clone $data;
            foreach (get_object_vars($data) as $k => $v) {
                if (self::is_sensitive_key($k)) {
                    $cleaned->$k = '[REDACTED_SECRET]';
                } else {
                    $cleaned->$k = self::redact_data($v);
                }
            }
            return $cleaned;
        } elseif (is_string($data)) {
            return self::redact_string($data, false);
        }

        return $data;
    }
}
