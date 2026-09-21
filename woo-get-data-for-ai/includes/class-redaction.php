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
                '/[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+/',
                function ($matches) {
                    return self::redact_email($matches[0]);
                },
                $text
            );
        }

        return $text;
    }

    /**
     * Intelligently redact email address to preserve the first 2 characters
     * and the last 2 characters of the local part (e.g. doriane.hoareau@hotmail.fr -> do***au@hotmail.fr).
     * Preserves GDPR compliance while allowing diagnosis of account typos and mismatches.
     *
     * @param string $email
     * @return string
     */
    public static function redact_email($email) {
        if (!is_string($email) || empty(trim($email))) {
            return '';
        }

        $email = trim($email);

        // Handle comma or semicolon separated lists of emails
        if (strpos($email, ',') !== false || strpos($email, ';') !== false) {
            $delimiter = strpos($email, ',') !== false ? ',' : ';';
            $parts = explode($delimiter, $email);
            $redacted_parts = array_map([self::class, 'redact_email'], array_map('trim', $parts));
            return implode(', ', $redacted_parts);
        }

        // Handle "Name <email@domain.com>" format
        if (preg_match('/^(.*?)<([^>]+)>$/', $email, $matches)) {
            $name = trim($matches[1]);
            $inner_email = trim($matches[2]);
            $redacted_inner = self::redact_single_email($inner_email);
            return $name ? "$name <$redacted_inner>" : "<$redacted_inner>";
        }

        return self::redact_single_email($email);
    }

    /**
     * Redact a single raw email address.
     *
     * @param string $email
     * @return string
     */
    protected static function redact_single_email($email) {
        if (strpos($email, '@') === false) {
            return self::redact_string($email, false);
        }

        $parts = explode('@', $email, 2);
        $local = $parts[0];
        $domain = $parts[1];

        $len = function_exists('mb_strlen') ? mb_strlen($local, 'UTF-8') : strlen($local);

        if ($len >= 5) {
            // Keep first 2 and last 2 characters (e.g. doriane.hoareau -> do***au, dodokisss -> do***ss)
            $first = function_exists('mb_substr') ? mb_substr($local, 0, 2, 'UTF-8') : substr($local, 0, 2);
            $last  = function_exists('mb_substr') ? mb_substr($local, -2, null, 'UTF-8') : substr($local, -2);
            $masked_local = $first . '***' . $last;
        } elseif ($len === 4 || $len === 3) {
            $first = function_exists('mb_substr') ? mb_substr($local, 0, 1, 'UTF-8') : substr($local, 0, 1);
            $last  = function_exists('mb_substr') ? mb_substr($local, -1, null, 'UTF-8') : substr($local, -1);
            $masked_local = $first . '***' . $last;
        } elseif ($len === 2) {
            $first = function_exists('mb_substr') ? mb_substr($local, 0, 1, 'UTF-8') : substr($local, 0, 1);
            $masked_local = $first . '***';
        } else {
            $masked_local = '***';
        }

        return $masked_local . '@' . $domain;
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

    /**
     * Anonymize/mask IP address for GDPR compliance.
     *
     * @param string $ip
     * @return string
     */
    public static function mask_ip($ip) {
        if (!is_string($ip) || empty(trim($ip))) {
            return '';
        }

        $ip = trim($ip);

        if (function_exists('wp_privacy_anonymize_ip')) {
            return wp_privacy_anonymize_ip($ip);
        }

        // Native IPv4 fallback (mask last octet)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            $parts[3] = '0';
            return implode('.', $parts);
        }

        // Native IPv6 fallback (mask last 80 bits)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            if ($packed !== false) {
                $mask = str_repeat("\xFF", 6) . str_repeat("\x00", 10);
                return inet_ntop($packed & $mask);
            }
        }

        return preg_replace('/\d+$/', '0', $ip);
    }

    /**
     * Alias for redact_data when handling arrays.
     *
     * @param array $data
     * @return array
     */
    public static function redact_array(array $data) {
        return (array) self::redact_data($data);
    }

    /**
     * Alias for redact_data to sanitize output payloads.
     *
     * @param mixed $data
     * @return mixed
     */
    public static function sanitize_output($data) {
        return self::redact_data($data);
    }
}
