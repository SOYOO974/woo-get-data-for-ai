<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Change_Journal;
use WPAgentBridge\Payment_Logs;

/**
 * GET /guardian/snapshot
 * Single read-only, dated raw-figures snapshot for the nightly e-commerce sentinel.
 * The plugin never decides severity: every threshold lives on the caller side.
 */
class Guardian_Controller extends Rest_Controller {

    const CACHE_TTL      = 60;
    const LOG_SCAN_BYTES = 8388608; // 8 MB backward scan cap per log file
    const ALMA_MIN_TOTAL = 150;

    /**
     * Snapshot window start (UTC timestamp).
     *
     * @var int
     */
    private $since = 0;

    /**
     * Number of best sellers inspected for stock-outs.
     *
     * @var int
     */
    private $top = 10;

    /**
     * Registered gateways cache for this request.
     *
     * @var array|null
     */
    private $gateways = null;

    public function register_routes() {
        register_rest_route(self::NAMESPACE, '/guardian/snapshot', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_snapshot'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'guardian');
            },
            'args'                => [
                'since' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'top'   => [
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_snapshot(\WP_REST_Request $request) {
        $started = microtime(true);
        $now     = time();

        $since_param = (string) $request->get_param('since');
        $since_ts    = $since_param !== '' ? strtotime($since_param) : false;
        if ($since_ts === false) {
            $since_ts = $now - DAY_IN_SECONDS;
        }
        // Clamp to [now - 31 days, now] to keep every query bounded.
        $this->since = (int) min($now, max($now - 31 * DAY_IN_SECONDS, $since_ts));
        $this->top   = min(50, max(1, (int) ($request->get_param('top') ?: 10)));

        $cache_key = 'agent_bridge_guardian_' . md5($this->since . '|' . $this->top);
        $cached    = get_transient($cache_key);
        if (is_array($cached)) {
            $cached['meta']['cached'] = true;
            return $this->response($cached);
        }

        $sections = [
            'php'       => 'section_php',
            'payments'  => 'section_payments',
            'orders'    => 'section_orders',
            'alma'      => 'section_alma',
            'stock'     => 'section_stock',
            'scheduler' => 'section_scheduler',
            'mail'      => 'section_mail',
            'feeds'     => 'section_feeds',
            'changes'   => 'section_changes',
        ];

        $data    = [];
        $timings = [];
        foreach ($sections as $key => $method) {
            $t0 = microtime(true);
            try {
                $data[$key] = $this->$method();
            } catch (\Throwable $e) {
                $data[$key] = ['error' => Change_Journal::scrub(mb_substr($e->getMessage(), 0, 300))];
            }
            $timings[$key] = (int) round((microtime(true) - $t0) * 1000);
        }

        $payload = array_merge([
            'meta' => [
                'plugin_version' => WOO_GET_DATA_AI_VERSION,
                'wp_version'     => get_bloginfo('version'),
                'wc_version'     => defined('WC_VERSION') ? WC_VERSION : null,
                'php_version'    => PHP_VERSION,
                'hpos_enabled'   => $this->is_hpos_enabled(),
                'site_timezone'  => wp_timezone_string(),
                'since'          => $this->iso($this->since),
                'top'            => $this->top,
                'generated_at'   => $this->iso($now),
                'urls'           => $this->store_urls(),
                'duration_ms'    => (int) round((microtime(true) - $started) * 1000),
                'timings'        => $timings,
                'cached'         => false,
            ],
        ], $data);

        set_transient($cache_key, $payload, self::CACHE_TTL);

        return $this->response($payload);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * @param int|null $ts
     * @return string|null
     */
    private function iso($ts) {
        return $ts ? gmdate('Y-m-d\TH:i:s\Z', (int) $ts) : null;
    }

    /**
     * @return bool
     */
    private function is_wc() {
        return class_exists('WooCommerce') && function_exists('wc_get_orders');
    }

    /**
     * @return bool
     */
    private function is_hpos_enabled() {
        if (class_exists('\Automattic\WooCommerce\Utilities\OrderUtil') && method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
            return (bool) \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        return false;
    }

    /**
     * Localized storefront URLs so visitor probes never guess paths (e.g. /commande/).
     *
     * @return array{cart: ?string, checkout: ?string, shop: ?string}
     */
    private function store_urls() {
        return [
            'cart'     => function_exists('wc_get_cart_url') ? (string) wc_get_cart_url() : null,
            'checkout' => function_exists('wc_get_checkout_url') ? (string) wc_get_checkout_url() : null,
            'shop'     => function_exists('wc_get_page_permalink') ? (string) wc_get_page_permalink('shop') : null,
        ];
    }

    /**
     * Count orders via the WooCommerce data store (HPOS & CPT compatible).
     *
     * @param array $args
     * @return int
     */
    private function count_orders(array $args) {
        $result = wc_get_orders(array_merge([
            'type'     => 'shop_order',
            'limit'    => 1,
            'paginate' => true,
            'return'   => 'ids',
        ], $args));
        return is_object($result) && isset($result->total) ? (int) $result->total : 0;
    }

    /**
     * @return array<string, array{id: string, title: string, enabled: bool}>
     */
    private function gateways() {
        if ($this->gateways === null) {
            $this->gateways = class_exists(Payment_Logs::class) ? Payment_Logs::get_registered_gateways() : [];
        }
        return $this->gateways;
    }

    /**
     * @param string $table
     * @return bool
     */
    private function table_exists($table) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    /**
     * Turn an absolute path into a wp-content relative one.
     *
     * @param string $path
     * @return string
     */
    private function relative_path($path) {
        $path = str_replace('\\', '/', (string) $path);
        $content = str_replace('\\', '/', WP_CONTENT_DIR);
        $abs = str_replace('\\', '/', ABSPATH);
        $path = str_replace([$content, rtrim($abs, '/')], ['wp-content', ''], $path);
        return ltrim($path, '/');
    }

    // -------------------------------------------------------------------------
    // Sections
    // -------------------------------------------------------------------------

    /**
     * PHP fatals since `since`, debug.log size and display flags.
     *
     * @return array
     */
    private function section_php() {
        $debug_log = WP_CONTENT_DIR . '/debug.log';
        if (defined('WP_DEBUG_LOG') && is_string(WP_DEBUG_LOG) && WP_DEBUG_LOG !== '' && !in_array(strtolower(WP_DEBUG_LOG), ['1', 'true'], true)) {
            $debug_log = WP_DEBUG_LOG;
        } elseif (!file_exists($debug_log)) {
            $ini_log = (string) ini_get('error_log');
            if ($ini_log !== '' && @is_file($ini_log)) {
                $debug_log = $ini_log;
            }
        }

        $files = [];
        if (@is_file($debug_log) && is_readable($debug_log)) {
            $files[] = $debug_log;
        }

        $wc_dir = class_exists(Payment_Logs::class) ? Payment_Logs::get_wc_logs_dir() : '';
        if ($wc_dir && is_dir($wc_dir)) {
            foreach ((array) glob($wc_dir . 'fatal-errors-*.log') as $f) {
                if (is_readable($f) && filemtime($f) >= $this->since) {
                    $files[] = $f;
                }
            }
        }

        $fatals = [];
        $total  = 0;
        foreach ($files as $file) {
            $this->scan_backwards($file, function ($line, $ts) use (&$fatals, &$total) {
                if (preg_match('/(has no fatal errors|will not be checked for fatal errors|checking for fatal errors|no fatal errors)/i', $line)) {
                    return;
                }
                if (!preg_match('/(?:PHP\s+(?:Fatal|Parse|Core|Compile)\s+error|Uncaught\s+[A-Za-z0-9_\\\\]*(?:Error|Exception)\b|Allowed memory size of|Maximum execution time of)/i', $line)) {
                    return;
                }
                $total++;

                $src_file = '';
                $src_line = null;
                if (preg_match('/in\s+(\S+?)(?:\s+on line\s+|:)(\d+)(?:\s|$|\))/i', $line, $m)) {
                    $src_file = $this->relative_path($m[1]);
                    $src_line = (int) $m[2];
                }

                // Strip timestamp prefix and normalize volatile parts for a stable fingerprint.
                $message = preg_replace('/^(\[[^\]]+\]|\d{4}-\d{2}-\d{2}T\S+\s+[A-Z]+)\s*/', '', $line);
                $message = Change_Journal::scrub($message);
                $normalized = preg_replace('#(?:[A-Za-z]:)?[\\\\/][^\s:\'"()]+#', '<path>', $message);
                $normalized = preg_replace('/\b[0-9a-f]{8,}\b/i', '<hex>', $normalized);
                $normalized = preg_replace('/\d+/', '<n>', $normalized);
                $fp = sha1($src_file . ':' . $src_line . ':' . $normalized);

                if (!isset($fatals[$fp])) {
                    $fatals[$fp] = [
                        'fingerprint' => $fp,
                        'message'     => mb_substr(str_replace([WP_CONTENT_DIR, ABSPATH], ['wp-content', ''], $message), 0, 300),
                        'file'        => $src_file,
                        'line'        => $src_line,
                        'count'       => 0,
                        'first_seen'  => $this->iso($ts),
                        'last_seen'   => $this->iso($ts),
                    ];
                }
                $fatals[$fp]['count']++;
                // Backward scan: each new hit is older.
                $fatals[$fp]['first_seen'] = $this->iso($ts);
            });
        }

        usort($fatals, function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        $ini_display = strtolower((string) ini_get('display_errors'));

        return [
            'fatals_since'     => $total,
            'fatals_top'       => array_slice(array_values($fatals), 0, 5),
            'debug_log_bytes'  => (@is_file($debug_log) ? (int) filesize($debug_log) : null),
            'wp_debug_display' => (bool) (defined('WP_DEBUG') && WP_DEBUG && (!defined('WP_DEBUG_DISPLAY') || WP_DEBUG_DISPLAY)),
            'ini_display_errors' => in_array($ini_display, ['1', 'on', 'true', 'yes', 'stdout'], true),
        ];
    }

    /**
     * Read a log file from the end, calling $callback($line, $ts) for every
     * timestamped line newer than `since`. Stops at the first older line or the byte cap.
     *
     * @param string   $file
     * @param callable $callback
     */
    private function scan_backwards($file, callable $callback) {
        $handle = @fopen($file, 'rb');
        if (!$handle) {
            return;
        }

        fseek($handle, 0, SEEK_END);
        $pos     = ftell($handle);
        $scanned = 0;
        $carry   = '';

        while ($pos > 0 && $scanned < self::LOG_SCAN_BYTES) {
            $read = min($pos, 65536);
            $pos -= $read;
            $scanned += $read;
            fseek($handle, $pos, SEEK_SET);
            $chunk = fread($handle, $read);
            if ($chunk === false) {
                break;
            }

            $lines = explode("\n", str_replace("\r", '', $chunk . $carry));
            $carry = $pos > 0 ? array_shift($lines) : '';

            for ($i = count($lines) - 1; $i >= 0; $i--) {
                $line = trim($lines[$i]);
                if ($line === '') {
                    continue;
                }
                $ts = 0;
                if (preg_match('/^\[([^\]]+)\]/', $line, $m) || preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+\-]\d{2}:\d{2})?)/', $line, $m)) {
                    $ts = (int) strtotime($m[1]);
                }
                if ($ts <= 0) {
                    continue; // Stack trace / continuation lines carry no timestamp.
                }
                if ($ts < $this->since) {
                    fclose($handle);
                    return;
                }
                $callback($line, $ts);
            }
        }

        fclose($handle);
    }

    /**
     * Gateways, failed orders per gateway, payment log errors (declined vs technical).
     *
     * @return array
     */
    private function section_payments() {
        if (!$this->is_wc()) {
            return ['error' => 'woocommerce_not_active'];
        }

        $gateways = array_values($this->gateways());

        $failed = [];
        foreach ($gateways as $gw) {
            if (!$gw['enabled']) {
                continue;
            }
            $failed[$gw['id']] = $this->count_orders([
                'status'         => ['failed'],
                'payment_method' => $gw['id'],
                'date_created'   => '>=' . $this->since,
            ]);
        }

        $log_errors = [];
        $truncated  = false;
        if (class_exists(Payment_Logs::class)) {
            $files = Payment_Logs::resolve_target_files('all', '', 0, Payment_Logs::scan_wc_log_files(), gmdate('Y-m-d', $this->since));

            foreach ($files as $f) {
                if ((int) $f['mtime'] < $this->since) {
                    continue;
                }
                $slug = Payment_Logs::detect_gateway_from_handle($f['handle']);
                if (!isset($log_errors[$slug])) {
                    $log_errors[$slug] = ['declined' => 0, 'technical' => 0];
                }

                $res = Payment_Logs::search_log_file($f['path'], 500, '', 'all', '', $this->since);
                if (!empty($res['has_more'])) {
                    $truncated = true;
                }
                foreach ($res['entries'] as $entry) {
                    $text  = (string) ($entry['message'] ?? '') . ' ' . (is_string($entry['context'] ?? null) ? $entry['context'] : wp_json_encode($entry['context'] ?? ''));
                    $level = strtoupper((string) ($entry['level'] ?? ''));

                    if (preg_match('/(declin|refus|insufficient|insuffisant|do_not_honor|expired_card|incorrect_cvc|card_error|3d\s?s|3-d secure|authentication_required|authentication failed|scored_no|not_eligible)/i', $text)) {
                        $log_errors[$slug]['declined']++;
                    } elseif (in_array($level, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true)
                        || preg_match('/(\bHTTP\/?\s?[45]\d{2}\b|\bstatus(?:_code)?["\s:=]+[45]\d{2}\b|timed?\s?out|cURL error|signature|exception|fatal)/i', $text)) {
                        $log_errors[$slug]['technical']++;
                    }
                }
            }
        }

        return [
            'gateways'                 => array_map(function ($gw) {
                return ['id' => $gw['id'], 'enabled' => (bool) $gw['enabled'], 'title' => $gw['title']];
            }, $gateways),
            'failed_orders_since'      => $failed,
            'payment_log_errors_since' => $log_errors,
            'payment_logs_truncated'   => $truncated,
        ];
    }

    /**
     * Paid volume and stuck order counters.
     *
     * @return array
     */
    private function section_orders() {
        if (!$this->is_wc()) {
            return ['error' => 'woocommerce_not_active'];
        }

        $now = time();

        $last_paid = null;
        $recent    = wc_get_orders([
            'type'    => 'shop_order',
            'status'  => ['processing', 'completed'],
            'limit'   => 5,
            'orderby' => 'date',
            'order'   => 'DESC',
        ]);
        foreach ((array) $recent as $order) {
            $paid = $order->get_date_paid();
            if ($paid && ($last_paid === null || $paid->getTimestamp() > $last_paid)) {
                $last_paid = $paid->getTimestamp();
            }
        }

        $processing_args = [
            'status'       => ['processing'],
            'date_created' => ($now - 90 * DAY_IN_SECONDS) . '...' . ($now - 72 * HOUR_IN_SECONDS),
        ];
        $oldest = wc_get_orders(array_merge($processing_args, [
            'type'    => 'shop_order',
            'limit'   => 5,
            'orderby' => 'date',
            'order'   => 'ASC',
        ]));

        return [
            'paid_since'          => $this->count_orders([
                'status'    => ['processing', 'completed'],
                'date_paid' => '>=' . $this->since,
            ]),
            'last_paid_order_at'  => $this->iso($last_paid),
            'pending_over_24h'    => $this->count_orders([
                'status'       => ['pending'],
                'date_created' => ($now - 30 * DAY_IN_SECONDS) . '...' . ($now - DAY_IN_SECONDS),
            ]),
            'on_hold_over_48h'    => $this->count_orders([
                'status'       => ['on-hold'],
                'date_created' => ($now - 90 * DAY_IN_SECONDS) . '...' . ($now - 48 * HOUR_IN_SECONDS),
            ]),
            'processing_over_72h' => [
                'count'  => $this->count_orders($processing_args),
                'oldest' => array_map(function ($order) use ($now) {
                    $created = $order->get_date_created();
                    return [
                        'id'        => $order->get_id(),
                        'age_hours' => $created ? (int) floor(($now - $created->getTimestamp()) / HOUR_IN_SECONDS) : null,
                        'amount'    => round((float) $order->get_total(), 2),
                        'gateway'   => $order->get_payment_method(),
                    ];
                }, (array) $oldest),
            ],
            'windows_days'        => ['pending' => 30, 'on_hold' => 90, 'processing' => 90],
        ];
    }

    /**
     * Alma health: plugin & shield integrity, order statuses, unrecovered high tickets.
     *
     * @return array|null
     */
    private function section_alma() {
        if (!$this->is_wc()) {
            return null;
        }

        $alma_ids = [];
        foreach ($this->gateways() as $gw) {
            if ($gw['enabled'] && strpos($gw['id'], 'alma') === 0) {
                $alma_ids[] = $gw['id'];
            }
        }
        if (empty($alma_ids)) {
            return null;
        }

        // Alma plugin version (direct header read, no get_plugins() scan).
        $plugin_version = null;
        $alma_dir       = WP_PLUGIN_DIR . '/alma-gateway-for-woocommerce';
        if (is_dir($alma_dir)) {
            $candidates = array_merge([$alma_dir . '/alma-gateway-for-woocommerce.php'], (array) glob($alma_dir . '/*.php'));
            foreach (array_unique($candidates) as $file) {
                if (!is_readable($file)) {
                    continue;
                }
                $headers = get_file_data($file, ['Name' => 'Plugin Name', 'Version' => 'Version']);
                if (!empty($headers['Name'])) {
                    $plugin_version = $headers['Version'] ?: null;
                    break;
                }
            }
        }

        $shield_file = WPMU_PLUGIN_DIR . '/soyoo-alma-reunion-shield.php';
        $shield      = ['present' => false, 'md5' => null, 'version' => null];
        if (is_readable($shield_file)) {
            $headers = get_file_data($shield_file, ['Version' => 'Version']);
            $shield  = [
                'present' => true,
                'md5'     => md5_file($shield_file),
                'version' => $headers['Version'] ?: null,
            ];
        }

        $statuses = function_exists('wc_get_order_statuses') ? array_keys(wc_get_order_statuses()) : 'any';
        $by_status = [];
        $candidates = [];
        foreach ($alma_ids as $gid) {
            $orders = wc_get_orders([
                'type'           => 'shop_order',
                'status'         => $statuses,
                'payment_method' => $gid,
                'date_created'   => '>=' . $this->since,
                'limit'          => 500,
            ]);
            foreach ((array) $orders as $order) {
                $status = $order->get_status();
                $by_status[$status] = ($by_status[$status] ?? 0) + 1;
                if (in_array($status, ['pending', 'failed', 'cancelled'], true) && (float) $order->get_total() >= self::ALMA_MIN_TOTAL && count($candidates) < 50) {
                    $candidates[] = $order;
                }
            }
        }

        $now         = time();
        $salt        = wp_salt('auth');
        $unrecovered = [];
        $recovered   = [];
        foreach ($candidates as $order) {
            $email   = strtolower(trim((string) $order->get_billing_email()));
            $created = $order->get_date_created();
            $created_ts = $created ? $created->getTimestamp() : $now;
            $hash    = $email !== '' ? substr(hash_hmac('sha256', $email, $salt), 0, 16) : null;

            $is_recovered = false;
            if ($email !== '') {
                $paid_ids = wc_get_orders([
                    'type'          => 'shop_order',
                    'status'        => ['processing', 'completed'],
                    'billing_email' => $email,
                    'date_paid'     => $created_ts . '...' . ($created_ts + 48 * HOUR_IN_SECONDS),
                    'exclude'       => [$order->get_id()],
                    'limit'         => 1,
                    'return'        => 'ids',
                ]);
                $is_recovered = !empty($paid_ids);
            }

            if ($is_recovered) {
                $recovered[$hash] = true;
                continue;
            }

            $unrecovered[] = [
                'order_id'      => $order->get_id(),
                'amount'        => round((float) $order->get_total(), 2),
                'gateway'       => $order->get_payment_method(),
                'status'        => $order->get_status(),
                'plan'          => $this->first_meta($order, ['_alma_selected_plan', 'alma_selected_plan', '_alma_plan_key', '_alma_installments_count']),
                'alma_state'    => $this->first_meta($order, ['_alma_payment_state', '_alma_state', 'alma_state']),
                'customer_hash' => $hash,
                'minutes_ago'   => (int) floor(($now - $created_ts) / MINUTE_IN_SECONDS),
                'window_open'   => ($now - $created_ts) < 48 * HOUR_IN_SECONDS,
            ];
        }

        return [
            'gateways'                => $alma_ids,
            'plugin_version'          => $plugin_version,
            'shield'                  => $shield,
            'orders_since'            => $by_status,
            'high_ticket_threshold'   => self::ALMA_MIN_TOTAL,
            'unrecovered_high_ticket' => $unrecovered,
            'recovered_count'         => count($recovered),
        ];
    }

    /**
     * @param \WC_Order $order
     * @param string[]  $keys
     * @return string|null
     */
    private function first_meta($order, array $keys) {
        foreach ($keys as $key) {
            $value = $order->get_meta($key, true);
            if ($value !== '' && $value !== null && is_scalar($value)) {
                return mb_substr((string) $value, 0, 60);
            }
        }
        return null;
    }

    /**
     * Best sellers (30 days) out of stock or on backorder, published zero-price products.
     *
     * @return array
     */
    private function section_stock() {
        if (!$this->is_wc()) {
            return ['error' => 'woocommerce_not_active'];
        }

        global $wpdb;
        $lookup = $wpdb->prefix . 'wc_order_product_lookup';
        $stats  = $wpdb->prefix . 'wc_order_stats';
        $meta   = $wpdb->prefix . 'wc_product_meta_lookup';

        $issues = [];
        if ($this->table_exists($lookup) && $this->table_exists($stats)) {
            // Same aggregation as /woocommerce/analytics/top-performers (net revenue, paid statuses).
            $rows = $wpdb->get_results($wpdb->prepare("
                SELECT l.product_id, COALESCE(SUM(l.product_qty), 0) AS units, COALESCE(SUM(l.product_net_revenue), 0) AS revenue
                FROM {$lookup} l
                INNER JOIN {$stats} s ON l.order_id = s.order_id
                WHERE s.date_created >= %s
                AND s.status IN ('wc-completed', 'wc-processing', 'wc-on-hold')
                GROUP BY l.product_id
                ORDER BY revenue DESC
                LIMIT %d
            ", gmdate('Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS), $this->top), ARRAY_A);

            foreach ((array) $rows as $row) {
                $product = wc_get_product((int) $row['product_id']);
                if (!$product) {
                    continue;
                }
                $status = $product->get_stock_status();
                if (!in_array($status, ['outofstock', 'onbackorder'], true)) {
                    continue;
                }
                $issues[] = [
                    'id'                => $product->get_id(),
                    'name'              => $product->get_name(),
                    'units_sold_30d'    => (int) $row['units'],
                    'stock_status'      => $status,
                    'out_of_stock_since' => null, // Not tracked natively by WooCommerce.
                ];
            }
        }

        $zero_price = null;
        if ($this->table_exists($meta)) {
            $zero_price = (int) $wpdb->get_var("
                SELECT COUNT(*) FROM {$meta} m
                INNER JOIN {$wpdb->posts} p ON p.ID = m.product_id
                WHERE p.post_type = 'product' AND p.post_status = 'publish'
                AND COALESCE(m.max_price, 0) = 0
            ");
        }

        return [
            'top_sellers_unavailable'    => $issues,
            'published_zero_price_count' => $zero_price,
        ];
    }

    /**
     * Action Scheduler backlog/failures and WP-Cron lag.
     *
     * @return array
     */
    private function section_scheduler() {
        global $wpdb;
        $now   = time();
        $table = $wpdb->prefix . 'actionscheduler_actions';

        $past_due = null;
        $failed   = null;
        $top      = [];
        if ($this->table_exists($table)) {
            $past_due = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'pending' AND scheduled_date_gmt < %s",
                gmdate('Y-m-d H:i:s', $now - 30 * MINUTE_IN_SECONDS)
            ));

            $since_sql = gmdate('Y-m-d H:i:s', $this->since);
            $failed = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE status = 'failed' AND last_attempt_gmt >= %s",
                $since_sql
            ));

            if ($failed > 0) {
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT hook, COUNT(*) AS cnt FROM {$table} WHERE status = 'failed' AND last_attempt_gmt >= %s GROUP BY hook ORDER BY cnt DESC LIMIT 5",
                    $since_sql
                ), ARRAY_A);
                foreach ((array) $rows as $row) {
                    $top[] = ['hook' => $row['hook'], 'count' => (int) $row['cnt']];
                }
            }
        }

        $overdue = 0;
        $crons   = _get_cron_array();
        if (is_array($crons) && !empty($crons)) {
            $first = (int) min(array_keys($crons));
            if ($first < $now) {
                $overdue = (int) floor(($now - $first) / MINUTE_IN_SECONDS);
            }
        }

        return [
            'as_past_due_pending'         => $past_due,
            'as_failed_since'             => $failed,
            'as_failed_top_hooks'         => $top,
            'wp_cron_overdue_max_minutes' => $overdue,
            'disable_wp_cron'             => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
        ];
    }

    /**
     * wp_mail_failed events journaled since `since`.
     *
     * @return array
     */
    private function section_mail() {
        $log = get_option(Change_Journal::MAIL_OPTION, []);
        $log = is_array($log) ? $log : [];

        $since  = $this->since;
        $recent = array_values(array_filter($log, function ($e) use ($since) {
            return is_array($e) && !empty($e['at']) && strtotime($e['at']) >= $since;
        }));

        return [
            'wp_mail_failed_since' => count($recent),
            'recent'               => array_slice(array_reverse($recent), 0, 5),
        ];
    }

    /**
     * Meta catalog feed metadata and Meta CAPI delivery counters (no XML parsing).
     *
     * @return array
     */
    private function section_feeds() {
        $result = ['meta_catalog' => null, 'meta_capi' => null];

        if (class_exists('\SOYOO\MetaCatalog\Feed_Generator')) {
            $status   = get_option('woo_meta_catalog_feed_status', []);
            $status   = is_array($status) ? $status : [];
            $upload   = wp_upload_dir();
            $file     = trailingslashit($upload['basedir']) . 'feeds/meta-catalog.xml';
            $exists   = is_file($file);

            $result['meta_catalog'] = [[
                'url'        => !empty($status['canonical_url']) ? (string) $status['canonical_url'] : trailingslashit($upload['baseurl']) . 'feeds/meta-catalog.xml',
                'mtime'      => $exists ? $this->iso(filemtime($file)) : null,
                'size_bytes' => $exists ? (int) filesize($file) : null,
                'item_count' => isset($status['total_items']) && ($status['status'] ?? '') === 'completed' ? (int) $status['total_items'] : null,
                'status'     => isset($status['status']) ? (string) $status['status'] : null,
            ]];
        }

        if (defined('WFBT_VERSION') || class_exists('\WFBT\Core')) {
            global $wpdb;
            $table = $wpdb->prefix . 'actionscheduler_actions';
            if ($this->table_exists($table)) {
                $since_sql = gmdate('Y-m-d H:i:s', $this->since);
                $rows = $wpdb->get_results($wpdb->prepare(
                    "SELECT status, COUNT(*) AS cnt FROM {$table} WHERE hook = 'wfbt_send_capi_event' AND status IN ('complete', 'failed') AND last_attempt_gmt >= %s GROUP BY status",
                    $since_sql
                ), ARRAY_A);
                $counts = ['complete' => 0, 'failed' => 0];
                foreach ((array) $rows as $row) {
                    $counts[$row['status']] = (int) $row['cnt'];
                }
                $result['meta_capi'] = [
                    'capi_events_sent_since' => $counts['complete'],
                    'capi_errors_since'      => $counts['failed'],
                    'source'                 => 'action_scheduler:wfbt_send_capi_event',
                ];
            } else {
                $result['meta_capi'] = ['capi_events_sent_since' => null, 'capi_errors_since' => null];
            }
        }

        return $result;
    }

    /**
     * Change journal entries since `since`.
     *
     * @return array
     */
    private function section_changes() {
        return Change_Journal::get_since($this->since);
    }
}
