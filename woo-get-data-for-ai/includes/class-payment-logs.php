<?php
namespace WPAgentBridge;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Redaction;

class Payment_Logs {

    /**
     * Common payment gateway aliases and their corresponding WooCommerce log file handles.
     *
     * @var array<string, array{name: string, handles: string[], gateway_ids: string[]}>
     */
    protected static $known_gateways = [
        'stripe' => [
            'name'        => 'Stripe',
            'handles'     => ['woocommerce-gateway-stripe', 'eh_stripe_pay', 'eh_stripe_pay_live', 'eh_stripe_pay_oauth', 'eh_stripe_pay_test', 'eh_stripe_pay_dead', 'stripe'],
            'gateway_ids' => ['stripe', 'stripe_cc', 'stripe_sepa', 'stripe_ideal', 'stripe_giropay', 'stripe_bancontact', 'eh_stripe_pay'],
        ],
        'alma' => [
            'name'        => 'Alma',
            'handles'     => ['alma', 'php-client'],
            'gateway_ids' => ['alma', 'alma_pnx_gateway', 'alma_in_page', 'alma_pay_now', 'alma_pay_10x_12x'],
        ],
        'paypal' => [
            'name'        => 'PayPal',
            'handles'     => ['woocommerce-paypal-payments', 'paypal', 'ppcp-paypal', 'wc_gateway_paypal'],
            'gateway_ids' => ['ppcp-gateway', 'paypal', 'paypal_express'],
        ],
        'mollie' => [
            'name'        => 'Mollie',
            'handles'     => ['mollie-payments-for-woocommerce', 'mollie'],
            'gateway_ids' => ['mollie_wc_gateway_ideal', 'mollie_wc_gateway_creditcard', 'mollie'],
        ],
        'payplug' => [
            'name'        => 'PayPlug',
            'handles'     => ['payplug'],
            'gateway_ids' => ['payplug'],
        ],
        'monetico' => [
            'name'        => 'Monetico',
            'handles'     => ['monetico', 'cmcic'],
            'gateway_ids' => ['monetico'],
        ],
        'systempay' => [
            'name'        => 'Systempay / Cyberplus',
            'handles'     => ['systempay', 'cyberplus'],
            'gateway_ids' => ['systempay'],
        ],
        'klarna' => [
            'name'        => 'Klarna',
            'handles'     => ['klarna-payments', 'klarna-checkout', 'klarna'],
            'gateway_ids' => ['klarna_payments', 'kco'],
        ],
        'scalapay' => [
            'name'        => 'Scalapay',
            'handles'     => ['scalapay'],
            'gateway_ids' => ['scalapay'],
        ],
        'vivawallet' => [
            'name'        => 'Viva Wallet',
            'handles'     => ['vivawallet', 'viva-wallet'],
            'gateway_ids' => ['vivawallet'],
        ],
        'paygreen' => [
            'name'        => 'PayGreen',
            'handles'     => ['paygreen'],
            'gateway_ids' => ['paygreen'],
        ],
        'lyra' => [
            'name'        => 'Lyra Collect',
            'handles'     => ['lyra'],
            'gateway_ids' => ['lyra'],
        ],
    ];

    /**
     * Detect known gateway slug from log handle.
     *
     * @param string $handle
     * @return string
     */
    public static function detect_gateway_from_handle($handle) {
        $handle = strtolower($handle);
        foreach (self::$known_gateways as $slug => $info) {
            foreach ($info['handles'] as $h) {
                if ($handle === $h || strpos($handle, $h) === 0 || strpos($handle, $slug) !== false) {
                    return $slug;
                }
            }
        }
        return 'other';
    }

    /**
     * Get WooCommerce logs directory path.
     *
     * @return string
     */
    public static function get_wc_logs_dir() {
        if (defined('WC_LOG_DIR')) {
            return trailingslashit(WC_LOG_DIR);
        }

        if (function_exists('wp_upload_dir')) {
            $upload_dir = wp_upload_dir();
            return trailingslashit($upload_dir['basedir'] . '/wc-logs');
        }

        return '';
    }

    /**
     * Discover registered payment gateways in WooCommerce.
     *
     * @return array<string, array{id: string, title: string, enabled: bool}>
     */
    public static function get_registered_gateways() {
        $gateways = [];

        if (function_exists('WC') && WC() && isset(WC()->payment_gateways)) {
            $wc_gateways = WC()->payment_gateways->payment_gateways();
            if (is_array($wc_gateways)) {
                foreach ($wc_gateways as $id => $gateway) {
                    $title = is_callable([$gateway, 'get_title']) ? $gateway->get_title() : $id;
                    $enabled = !empty($gateway->enabled) && in_array($gateway->enabled, ['yes', '1', true, 1], true);
                    $gateways[$id] = [
                        'id'      => (string) $id,
                        'title'   => html_entity_decode(strip_tags((string) $title), ENT_QUOTES, 'UTF-8'),
                        'enabled' => $enabled,
                    ];
                }
            }
        }

        return $gateways;
    }

    /**
     * Scan WC logs directory and catalogue all files.
     *
     * @return array<int, array{file: string, path: string, handle: string, date: string, hash: string, size_bytes: int, size_human: string, modified_at: string, mtime: int}>
     */
    public static function scan_wc_log_files() {
        $wc_logs_dir = self::get_wc_logs_dir();
        if (empty($wc_logs_dir) || !is_dir($wc_logs_dir)) {
            return [];
        }

        $files = scandir($wc_logs_dir);
        if (!$files) {
            return [];
        }

        $catalog = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || substr($file, -4) !== '.log') {
                continue;
            }

            $full_path = $wc_logs_dir . $file;
            if (!is_file($full_path) || !is_readable($full_path)) {
                continue;
            }

            // Standard WooCommerce log filename format: <handle>-<YYYY-MM-DD>-<hash>.log
            // Or non-standard: <handle>-<YYYY-MM-DD>.log or <handle>.log
            $handle = '';
            $date = '';
            $hash = '';

            if (preg_match('/^([a-zA-Z0-9_\-]+)-(\d{4}-\d{2}-\d{2})(?:-([a-f0-9]{32}|[a-f0-9]{20,}))\.log$/i', $file, $matches)) {
                $handle = $matches[1];
                $date   = $matches[2];
                $hash   = $matches[3] ?? '';
            } elseif (preg_match('/^([a-zA-Z0-9_\-]+)-(\d{4}-\d{2}-\d{2})\.log$/i', $file, $matches)) {
                $handle = $matches[1];
                $date   = $matches[2];
            } else {
                $handle = preg_replace('/\.log$/i', '', $file);
                $date   = date('Y-m-d', filemtime($full_path));
            }

            $size = filesize($full_path);
            $mtime = filemtime($full_path);

            $catalog[] = [
                'file'        => $file,
                'path'        => $full_path,
                'handle'      => strtolower($handle),
                'date'        => $date,
                'hash'        => $hash,
                'size_bytes'  => $size,
                'size_human'  => function_exists('size_format') ? size_format($size) : ($size . ' B'),
                'modified_at' => date('c', $mtime),
                'mtime'       => $mtime,
            ];
        }

        // Sort by modification time descending
        usort($catalog, function ($a, $b) {
            return $b['mtime'] <=> $a['mtime'];
        });

        return $catalog;
    }

    /**
     * Map available payment gateways and their corresponding logs.
     *
     * @param array $all_files
     * @return array
     */
    public static function build_available_gateways(array $all_files) {
        $registered = self::get_registered_gateways();
        $gateways_map = [];

        // 1. Initialize known gateways
        foreach (self::$known_gateways as $slug => $info) {
            $gateways_map[$slug] = [
                'id'              => $slug,
                'name'            => $info['name'],
                'title'           => $info['name'],
                'enabled'         => false,
                'handles'         => $info['handles'],
                'has_logs'        => false,
                'log_files_count' => 0,
                'latest_log_file' => null,
                'latest_log_date' => null,
                'latest_modified' => null,
            ];

            // Match against registered WC gateways
            foreach ($info['gateway_ids'] as $gid) {
                if (isset($registered[$gid])) {
                    $gateways_map[$slug]['title']   = $registered[$gid]['title'];
                    $gateways_map[$slug]['enabled'] = $registered[$gid]['enabled'];
                    break;
                }
            }
        }

        // 2. Add any other installed WC payment gateways
        foreach ($registered as $gid => $gdata) {
            // Check if already covered
            $covered = false;
            foreach ($gateways_map as $slug => $data) {
                if (isset(self::$known_gateways[$slug]) && in_array($gid, self::$known_gateways[$slug]['gateway_ids'], true)) {
                    $covered = true;
                    break;
                }
            }

            if (!$covered && !isset($gateways_map[$gid])) {
                $gateways_map[$gid] = [
                    'id'              => $gid,
                    'name'            => $gdata['title'],
                    'title'           => $gdata['title'],
                    'enabled'         => $gdata['enabled'],
                    'handles'         => [$gid],
                    'has_logs'        => false,
                    'log_files_count' => 0,
                    'latest_log_file' => null,
                    'latest_log_date' => null,
                    'latest_modified' => null,
                ];
            }
        }

        // 3. Associate files with gateways
        foreach ($all_files as $file_info) {
            $handle = $file_info['handle'];

            foreach ($gateways_map as $slug => &$gw) {
                $matches_handle = false;
                foreach ($gw['handles'] as $h) {
                    if ($handle === $h || strpos($handle, $h) === 0 || strpos($handle, $slug) !== false) {
                        $matches_handle = true;
                        break;
                    }
                }

                if ($matches_handle) {
                    $gw['has_logs'] = true;
                    $gw['log_files_count']++;
                    if (empty($gw['latest_log_file']) || $file_info['mtime'] > (int) strtotime($gw['latest_modified'] ?? '0')) {
                        $gw['latest_log_file'] = $file_info['file'];
                        $gw['latest_log_date'] = $file_info['date'];
                        $gw['latest_modified'] = $file_info['modified_at'];
                    }
                }
            }
            unset($gw);
        }

        // Return array of gateways that either have logs or are enabled, or known
        return array_values($gateways_map);
    }

    /**
     * Resolve target log files matching gateway slug and date criteria.
     *
     * @param string $gateway_slug
     * @param string $date_filter
     * @param int $days
     * @param array $all_files
     * @param string $since_date
     * @param string $until_date
     * @return array
     */
    public static function resolve_target_files($gateway_slug, $date_filter, $days, array $all_files, $since_date = '', $until_date = '') {
        $slug = strtolower(trim($gateway_slug ?: 'all'));
        $target_handles = [];

        if ($slug !== 'all') {
            // Check known gateway mapping
            if (isset(self::$known_gateways[$slug])) {
                $target_handles = self::$known_gateways[$slug]['handles'];
            } else {
                // Check if slug matches an alias key
                foreach (self::$known_gateways as $k => $info) {
                    if (strpos($k, $slug) !== false || strpos($slug, $k) !== false || in_array($slug, $info['gateway_ids'], true)) {
                        $target_handles = array_merge($target_handles, $info['handles']);
                    }
                }
                if (empty($target_handles)) {
                    $target_handles[] = $slug;
                }
            }
        }

        // Filter files matching gateway
        $matched_files = [];
        foreach ($all_files as $f) {
            $handle = $f['handle'];

            if ($slug === 'all') {
                // In 'all' mode: match any known gateway handle or common payment keywords
                $is_payment_file = false;
                foreach (self::$known_gateways as $kg) {
                    foreach ($kg['handles'] as $kh) {
                        if ($handle === $kh || strpos($handle, $kh) === 0 || strpos($handle, $kh) !== false) {
                            $is_payment_file = true;
                            break 2;
                        }
                    }
                }
                if (!$is_payment_file) {
                    if (preg_match('/(stripe|alma|paypal|mollie|payplug|monetico|systempay|klarna|scalapay|checkout|payment)/i', $handle)) {
                        $is_payment_file = true;
                    }
                }

                if ($is_payment_file) {
                    $matched_files[] = $f;
                }
            } else {
                // Specific gateway requested
                $matches = false;
                foreach ($target_handles as $th) {
                    if ($handle === $th || strpos($handle, $th) === 0 || strpos($handle, $th) !== false || strpos($handle, $slug) !== false) {
                        $matches = true;
                        break;
                    }
                }
                if ($matches) {
                    $matched_files[] = $f;
                }
            }
        }

        // If specific since/until date range requested
        if (!empty($since_date) || !empty($until_date)) {
            $matched_files = array_values(array_filter($matched_files, function ($f) use ($since_date, $until_date) {
                $f_date = $f['date'];
                if (empty($f_date)) {
                    return true;
                }
                if (!empty($since_date) && $f_date < $since_date) {
                    return false;
                }
                if (!empty($until_date) && $f_date > $until_date) {
                    return false;
                }
                return true;
            }));

            // Sort matched files descending by date then mtime
            usort($matched_files, function ($a, $b) {
                if ($a['date'] !== $b['date']) {
                    return strcmp($b['date'], $a['date']);
                }
                return $b['mtime'] <=> $a['mtime'];
            });

            return $matched_files;
        }

        // If specific date requested
        if (!empty($date_filter)) {
            $target_date = $date_filter;
            if ($target_date === 'today') {
                $target_date = date('Y-m-d');
            } elseif ($target_date === 'yesterday') {
                $target_date = date('Y-m-d', strtotime('-1 day'));
            }

            $matched_files = array_values(array_filter($matched_files, function ($f) use ($target_date) {
                return $f['date'] === $target_date;
            }));

            usort($matched_files, function ($a, $b) {
                return $b['mtime'] <=> $a['mtime'];
            });

            return $matched_files;
        }

        // If days limit applied: collect files from the most recent N distinct dates
        if ($days > 0 && !empty($matched_files)) {
            // Find distinct dates present in matched files, sorted descending
            $dates = [];
            foreach ($matched_files as $f) {
                if (!empty($f['date']) && !in_array($f['date'], $dates, true)) {
                    $dates[] = $f['date'];
                }
            }
            rsort($dates);

            $allowed_dates = array_slice($dates, 0, $days);
            $matched_files = array_values(array_filter($matched_files, function ($f) use ($allowed_dates) {
                return in_array($f['date'], $allowed_dates, true);
            }));
        }

        // Sort descending by date then mtime
        usort($matched_files, function ($a, $b) {
            if ($a['date'] !== $b['date']) {
                return strcmp($b['date'], $a['date']);
            }
            return $b['mtime'] <=> $a['mtime'];
        });

        return $matched_files;
    }

    /**
     * Memory-safe reverse file tail search using fseek with chunk streaming.
     *
     * @param string $filepath
     * @param int $max_lines
     * @param string $order_id
     * @param string $level
     * @param string $search_text
     * @param int $since_ts
     * @param int $until_ts
     * @param bool $count_only
     * @return array{entries: array, total_matches: int, total_errors: int, total_warnings: int, latest_error: ?string, has_more: bool}
     */
    public static function search_log_file(
        $filepath,
        $max_lines = 100,
        $order_id = '',
        $level = 'all',
        $search_text = '',
        $since_ts = 0,
        $until_ts = 0,
        $count_only = false
    ) {
        $handle = @fopen($filepath, 'rb');
        if (!$handle) {
            return [
                'entries'        => [],
                'total_matches'  => 0,
                'total_errors'   => 0,
                'total_warnings' => 0,
                'latest_error'   => null,
                'has_more'       => false,
            ];
        }

        $buffer_size = 32768; // 32 KB chunk
        fseek($handle, 0, SEEK_END);
        $pos = ftell($handle);

        $matched_entries = [];
        $total_matches   = 0;
        $total_errors    = 0;
        $total_warnings  = 0;
        $latest_error    = null;
        $has_more        = false;
        $carry_over      = '';
        $max_bytes_to_scan = 10 * 1024 * 1024; // 10MB safety cap per file
        $bytes_scanned   = 0;

        // Precompile filter regexes
        $order_regex = !empty($order_id) ? '/(?:\b|\D)' . preg_quote((string) $order_id, '/') . '(?:\b|\D)/' : null;
        $search_regex = !empty($search_text) ? '/' . preg_quote($search_text, '/') . '/i' : null;

        $level_regex = null;
        if (!empty($level) && $level !== 'all') {
            $l = strtolower($level);
            if ($l === 'error') {
                $level_regex = '/\s+(ERROR|CRITICAL|EMERGENCY|ALERT)\s+/i';
            } elseif ($l === 'warning') {
                $level_regex = '/\s+(WARNING)\s+/i';
            } elseif ($l === 'info') {
                $level_regex = '/\s+(INFO|NOTICE)\s+/i';
            } elseif ($l === 'debug') {
                $level_regex = '/\s+(DEBUG)\s+/i';
            } else {
                $level_regex = '/\s+(' . preg_quote(strtoupper($l), '/') . ')\s+/i';
            }
        }

        $stop_early = false;

        while ($pos > 0 && $bytes_scanned < $max_bytes_to_scan && !$stop_early) {
            if (!$count_only && count($matched_entries) >= $max_lines) {
                $has_more = true;
                break;
            }

            $read_size = min($pos, $buffer_size);
            $pos -= $read_size;
            $bytes_scanned += $read_size;

            fseek($handle, $pos, SEEK_SET);
            $chunk = fread($handle, $read_size);
            if ($chunk === false) {
                break;
            }

            $chunk .= $carry_over;
            $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $chunk));

            // The first element in $lines might be an incomplete line (unless pos == 0)
            if ($pos > 0) {
                $carry_over = array_shift($lines);
            } else {
                $carry_over = '';
            }

            // Process lines from bottom (newest) to top
            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = trim($lines[$i]);
                if ($line === '') {
                    continue;
                }

                // 1. Extract timestamp if present
                $line_ts = 0;
                if (preg_match('/^(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}(?:\+\d{2}:\d{2}|Z)?)/', $line, $ts_match)) {
                    $line_ts = strtotime($ts_match[1]);
                }

                // 2. Temporal filtering
                if ($until_ts > 0 && $line_ts > 0 && $line_ts > $until_ts) {
                    continue; // Skip lines after until_ts
                }

                if ($since_ts > 0 && $line_ts > 0 && $line_ts < $since_ts) {
                    // If line timestamp is older than since_ts by > 300s, stop reading file backwards
                    if ($line_ts < ($since_ts - 300)) {
                        $stop_early = true;
                        break;
                    }
                    continue;
                }

                // 3. Track error and warning counts unconditionally in this time window
                $is_error = preg_match('/\s+(ERROR|CRITICAL|EMERGENCY|ALERT)\s+/i', $line);
                $is_warning = preg_match('/\s+(WARNING)\s+/i', $line);

                if ($is_error) {
                    $total_errors++;
                    if ($latest_error === null) {
                        $latest_error = $line_ts > 0 ? date('c', $line_ts) : 'detected';
                    }
                } elseif ($is_warning) {
                    $total_warnings++;
                }

                // 4. Check active filters
                if ($level_regex && !preg_match($level_regex, $line)) {
                    continue;
                }
                if ($order_regex && !preg_match($order_regex, $line)) {
                    continue;
                }
                if ($search_regex && !preg_match($search_regex, $line)) {
                    continue;
                }

                $total_matches++;

                if (!$count_only) {
                    if (count($matched_entries) < $max_lines) {
                        $redacted_line = Redaction::redact_string($line, true);
                        $matched_entries[] = self::parse_log_line($redacted_line, basename($filepath));
                    } else {
                        $has_more = true;
                    }
                }
            }
        }

        // Process leftover line at beginning of file if pos == 0
        if ($pos === 0 && !empty(trim($carry_over)) && !$stop_early) {
            $line = trim($carry_over);
            $line_ts = 0;
            if (preg_match('/^(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}(?:\+\d{2}:\d{2}|Z)?)/', $line, $ts_match)) {
                $line_ts = strtotime($ts_match[1]);
            }

            $pass_time = true;
            if ($until_ts > 0 && $line_ts > 0 && $line_ts > $until_ts) {
                $pass_time = false;
            }
            if ($since_ts > 0 && $line_ts > 0 && $line_ts < $since_ts) {
                $pass_time = false;
            }

            if ($pass_time) {
                $is_error = preg_match('/\s+(ERROR|CRITICAL|EMERGENCY|ALERT)\s+/i', $line);
                $is_warning = preg_match('/\s+(WARNING)\s+/i', $line);

                if ($is_error) {
                    $total_errors++;
                    if ($latest_error === null) {
                        $latest_error = $line_ts > 0 ? date('c', $line_ts) : 'detected';
                    }
                } elseif ($is_warning) {
                    $total_warnings++;
                }

                $pass = true;
                if ($level_regex && !preg_match($level_regex, $line)) {
                    $pass = false;
                }
                if ($pass && $order_regex && !preg_match($order_regex, $line)) {
                    $pass = false;
                }
                if ($pass && $search_regex && !preg_match($search_regex, $line)) {
                    $pass = false;
                }

                if ($pass) {
                    $total_matches++;
                    if (!$count_only) {
                        if (count($matched_entries) < $max_lines) {
                            $redacted_line = Redaction::redact_string($line, true);
                            $matched_entries[] = self::parse_log_line($redacted_line, basename($filepath));
                        } else {
                            $has_more = true;
                        }
                    }
                }
            }
        }

        fclose($handle);

        return [
            'entries'        => $matched_entries,
            'total_matches'  => $total_matches,
            'total_errors'   => $total_errors,
            'total_warnings' => $total_warnings,
            'latest_error'   => $latest_error,
            'has_more'       => $has_more,
        ];
    }

    /**
     * Parse raw log line into structured components.
     *
     * @param string $line
     * @param string $filename
     * @return array{timestamp: string, level: string, message: string, context: mixed, source_file: string, raw: string}
     */
    public static function parse_log_line($line, $filename = '') {
        $entry = [
            'timestamp'   => '',
            'level'       => '',
            'message'     => '',
            'context'     => null,
            'source_file' => $filename,
            'raw'         => $line,
        ];

        // Standard WC Logger format: 2026-09-23T16:41:59+00:00 LEVEL Message [CONTEXT: {...}]
        if (preg_match('/^(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}(?:\+\d{2}:\d{2}|Z)?)\s+([A-Z]+)\s+(.*)$/s', $line, $matches)) {
            $entry['timestamp'] = $matches[1];
            $entry['level']     = $matches[2];
            $rest               = $matches[3];

            if (strpos($rest, ' CONTEXT: ') !== false) {
                $parts = explode(' CONTEXT: ', $rest, 2);
                $entry['message'] = trim($parts[0]);
                $context_raw = trim($parts[1]);
                $decoded = json_decode($context_raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $entry['context'] = Redaction::redact_data($decoded);
                } else {
                    $entry['context'] = $context_raw;
                }
            } else {
                $entry['message'] = trim($rest);
            }
        } else {
            $entry['message'] = $line;
        }

        return $entry;
    }

    /**
     * Handle REST request and return unified payment logs response.
     *
     * @param \WP_REST_Request $request
     * @return array
     */
    public static function get_logs(\WP_REST_Request $request) {
        $gateway_param = $request->get_param('gateway');
        $gateway       = !empty($gateway_param) ? sanitize_text_field($gateway_param) : 'all';
        $order_id      = $request->get_param('order_id') ? sanitize_text_field((string) $request->get_param('order_id')) : '';
        $level         = sanitize_text_field($request->get_param('level') ?: 'all');
        $lines_param   = (int) ($request->get_param('lines') ?: 100);
        $max_lines     = min(1000, max(1, $lines_param));

        // count_only or summary flag
        $count_only_param = $request->get_param('count_only') ?? $request->get_param('summary');
        $count_only = $count_only_param !== null && in_array(strtolower((string) $count_only_param), ['1', 'true', 'yes'], true);

        // Date range parsing: since / date_from
        $since_input = $request->get_param('since') ?: $request->get_param('date_from');
        $since_ts = 0;
        $since_date = '';
        if (!empty($since_input)) {
            if (is_numeric($since_input)) {
                $since_ts = (int) $since_input;
            } else {
                $parsed = strtotime($since_input);
                if ($parsed !== false) {
                    $since_ts = $parsed;
                }
            }
            if ($since_ts > 0) {
                $since_date = date('Y-m-d', $since_ts);
            }
        }

        // Date range parsing: until / date_to
        $until_input = $request->get_param('until') ?: $request->get_param('date_to');
        $until_ts = 0;
        $until_date = '';
        if (!empty($until_input)) {
            if (is_numeric($until_input)) {
                $until_ts = (int) $until_input;
            } else {
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($until_input))) {
                    $parsed = strtotime(trim($until_input) . ' 23:59:59');
                } else {
                    $parsed = strtotime($until_input);
                }
                if ($parsed !== false) {
                    $until_ts = $parsed;
                }
            }
            if ($until_ts > 0) {
                $until_date = date('Y-m-d', $until_ts);
            }
        }

        // Default days: 5 if order_id is provided, 1 otherwise
        $default_days  = !empty($order_id) ? 5 : 1;
        $days_param    = $request->get_param('days');
        $days          = $days_param !== null ? min(30, max(1, (int) $days_param)) : $default_days;

        $date_filter   = $request->get_param('date') ? sanitize_text_field($request->get_param('date')) : '';
        $search_filter = $request->get_param('search') ? sanitize_text_field($request->get_param('search')) : '';
        if (empty($search_filter) && $request->get_param('filter')) {
            $search_filter = sanitize_text_field($request->get_param('filter'));
        }

        // 1. Scan all WC log files
        $all_files = self::scan_wc_log_files();

        // 2. Discover available gateways metadata
        $available_gateways = self::build_available_gateways($all_files);

        // 3. Resolve target log files
        $target_files = self::resolve_target_files($gateway, $date_filter, $days, $all_files, $since_date, $until_date);

        // 4. Stream search across target files
        $all_entries           = [];
        $matched_files_summary = [];
        $total_errors_accum    = 0;
        $total_warnings_accum  = 0;
        $total_matches_accum   = 0;
        $latest_error_global   = null;
        $has_more_global       = false;
        $by_gateway_stats      = [];

        foreach ($target_files as $f_info) {
            $gw_slug = self::detect_gateway_from_handle($f_info['handle']);
            if (!isset($by_gateway_stats[$gw_slug])) {
                $by_gateway_stats[$gw_slug] = [
                    'gateway'      => $gw_slug,
                    'errors'       => 0,
                    'warnings'     => 0,
                    'matches'      => 0,
                    'latest_error' => null,
                ];
            }

            $matched_files_summary[] = [
                'file'        => $f_info['file'],
                'handle'      => $f_info['handle'],
                'gateway'     => $gw_slug,
                'date'        => $f_info['date'],
                'size_human'  => $f_info['size_human'],
                'size_bytes'  => $f_info['size_bytes'],
                'modified_at' => $f_info['modified_at'],
            ];

            $remaining_slots = max(0, $max_lines - count($all_entries));
            $file_res = self::search_log_file(
                $f_info['path'],
                $remaining_slots,
                $order_id,
                $level,
                $search_filter,
                $since_ts,
                $until_ts,
                $count_only
            );

            $total_errors_accum   += $file_res['total_errors'];
            $total_warnings_accum += $file_res['total_warnings'];
            $total_matches_accum  += $file_res['total_matches'];

            $by_gateway_stats[$gw_slug]['errors']   += $file_res['total_errors'];
            $by_gateway_stats[$gw_slug]['warnings'] += $file_res['total_warnings'];
            $by_gateway_stats[$gw_slug]['matches']  += $file_res['total_matches'];

            if (!empty($file_res['latest_error'])) {
                if ($latest_error_global === null || strtotime($file_res['latest_error']) > strtotime($latest_error_global)) {
                    $latest_error_global = $file_res['latest_error'];
                }
                if ($by_gateway_stats[$gw_slug]['latest_error'] === null || strtotime($file_res['latest_error']) > strtotime($by_gateway_stats[$gw_slug]['latest_error'])) {
                    $by_gateway_stats[$gw_slug]['latest_error'] = $file_res['latest_error'];
                }
            }

            if ($file_res['has_more']) {
                $has_more_global = true;
            }

            if (!$count_only) {
                foreach ($file_res['entries'] as $entry) {
                    $all_entries[] = $entry;
                }
                if (count($all_entries) >= $max_lines) {
                    $has_more_global = true;
                    // In regular mode, stop reading additional files once capacity is reached
                    break;
                }
            }
        }

        // Return lightweight summary if count_only
        if ($count_only) {
            return [
                'success'                => true,
                'count_only'             => true,
                'gateway'                => $gateway,
                'level'                  => $level,
                'since'                  => $since_ts > 0 ? date('c', $since_ts) : null,
                'until'                  => $until_ts > 0 ? date('c', $until_ts) : null,
                'days_scanned'           => $days,
                'total_errors'           => $total_errors_accum,
                'total_warnings'         => $total_warnings_accum,
                'total_matches'          => $total_matches_accum,
                'latest_error_timestamp' => $latest_error_global,
                'by_gateway'             => $by_gateway_stats,
                'scanned_files_count'    => count($target_files),
                'available_gateways'     => $available_gateways,
            ];
        }

        // 5. Sort aggregated entries by timestamp descending (newest first)
        usort($all_entries, function ($a, $b) {
            $ta = !empty($a['timestamp']) ? strtotime($a['timestamp']) : 0;
            $tb = !empty($b['timestamp']) ? strtotime($b['timestamp']) : 0;
            return $tb <=> $ta;
        });

        // Limit to max lines
        if (count($all_entries) > $max_lines) {
            $all_entries = array_slice($all_entries, 0, $max_lines);
            $has_more_global = true;
        }

        $raw_lines = array_map(function ($e) {
            return $e['raw'];
        }, $all_entries);

        return [
            'success'                => true,
            'count_only'             => false,
            'gateway'                => $gateway,
            'order_id'               => !empty($order_id) ? $order_id : null,
            'level'                  => $level,
            'since'                  => $since_ts > 0 ? date('c', $since_ts) : null,
            'until'                  => $until_ts > 0 ? date('c', $until_ts) : null,
            'days_scanned'           => $days,
            'date_filter'            => !empty($date_filter) ? $date_filter : null,
            'total_lines'            => count($all_entries),
            'has_more'               => $has_more_global,
            'summary'                => [
                'total_errors'   => $total_errors_accum,
                'total_warnings' => $total_warnings_accum,
                'total_matches'  => $total_matches_accum,
                'latest_error'   => $latest_error_global,
                'by_gateway'     => $by_gateway_stats,
            ],
            'scanned_files_count'    => count($target_files),
            'matched_files'          => $matched_files_summary,
            'entries'                => $all_entries,
            'lines'                  => $raw_lines,
            'available_gateways'     => $available_gateways,
        ];
    }
}
