<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Redaction;

class Woocommerce_Controller extends Rest_Controller {

    /**
     * Register routes for Woocommerce_Controller.
     */
    public function register_routes() {
        // GET /woocommerce/summary (Overall store health, products and orders counts, HPOS status)
        register_rest_route(self::NAMESPACE, '/woocommerce/summary', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_summary'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'woocommerce');
            },
        ]);

        // GET /woocommerce/products (Paginated products list with status/type/stock filters)
        register_rest_route(self::NAMESPACE, '/woocommerce/products', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_products'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'woocommerce');
            },
            'args'                => [
                'status'       => [
                    'default'           => 'publish',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'type'         => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'stock_status' => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'category'     => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search'       => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'per_page'     => [
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
                'page'         => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'orderby'      => [
                    'default'           => 'date',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order'        => [
                    'default'           => 'DESC',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /woocommerce/product/{id} (Detailed single product with attributes, variations, and metadata)
        register_rest_route(self::NAMESPACE, '/woocommerce/product/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_product'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'woocommerce');
            },
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        // GET /woocommerce/orders (Recent orders with strict PII anonymization)
        register_rest_route(self::NAMESPACE, '/woocommerce/orders', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_orders'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'woocommerce');
            },
            'args'                => [
                'status'      => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search'      => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'customer_id' => [
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                ],
                'per_page'    => [
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                ],
                'page'        => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'orderby'     => [
                    'default'           => 'date',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order'       => [
                    'default'           => 'DESC',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        // GET /woocommerce/order/{id} (Deep order diagnostics with notes, refunds, and sanitized metadata)
        register_rest_route(self::NAMESPACE, '/woocommerce/order/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_order'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'woocommerce');
            },
            'args'                => [
                'id' => [
                    'required'          => true,
                    'validate_callback' => function ($param) {
                        return is_numeric($param);
                    },
                ],
            ],
        ]);

        // GET /woocommerce/settings (Store settings, active gateways, and shipping zones)
        register_rest_route(self::NAMESPACE, '/woocommerce/settings', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_settings'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'woocommerce');
            },
        ]);

        // GET /woocommerce/shipping (Complete logistics, shipping zones, methods and Flexible Shipping matrix rules)
        register_rest_route(self::NAMESPACE, '/woocommerce/shipping', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_shipping_details'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'woocommerce');
            },
        ]);

        // GET /woocommerce/analytics/sales (Native WooCommerce sales performance, revenue, orders, AOV, comparison vs prior period)
        register_rest_route(self::NAMESPACE, '/woocommerce/analytics/sales', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_sales_analytics'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'woocommerce');
            },
            'args'                => [
                'range'      => [
                    'default'           => 'last_30_days',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Time window: today, yesterday, last_7_days, last_30_days, this_month, last_month, this_year, custom',
                ],
                'start_date' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'Start date for custom range (YYYY-MM-DD)',
                ],
                'end_date'   => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                    'description'       => 'End date for custom range (YYYY-MM-DD)',
                ],
            ],
        ]);

        // GET /woocommerce/analytics/top-performers (Top products by revenue & volume, top coupons)
        register_rest_route(self::NAMESPACE, '/woocommerce/analytics/top-performers', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_top_performers'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'woocommerce');
            },
            'args'                => [
                'range'      => [
                    'default'           => 'last_30_days',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'start_date' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'end_date'   => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit'      => [
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                    'description'       => 'Max items to return (1-50)',
                ],
            ],
        ]);

        // GET /woocommerce/analytics/stock (Stock financial valuation, low stock alerts, dormant inventory)
        register_rest_route(self::NAMESPACE, '/woocommerce/analytics/stock', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_stock_analytics'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'woocommerce');
            },
            'args'                => [
                'low_stock_threshold' => [
                    'default'           => 0,
                    'sanitize_callback' => 'absint',
                    'description'       => 'Optional low stock threshold override (defaults to WooCommerce setting)',
                ],
            ],
        ]);

        // GET /woocommerce/webhooks (Active and failing webhooks inventory)
        register_rest_route(self::NAMESPACE, '/woocommerce/webhooks', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_webhooks'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'woocommerce');
            },
        ]);
    }

    /**
     * Verify WooCommerce is active on the site.
     *
     * @return bool
     */
    protected function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Anonymize customer names for GDPR/PII protection (e.g. "John" -> "J***").
     *
     * @param string $name
     * @return string
     */
    protected static function mask_name($name) {
        if (empty($name) || !is_string($name)) {
            return '';
        }
        $trimmed = trim($name);
        if (mb_strlen($trimmed) <= 1) {
            return $trimmed . '***';
        }
        return mb_substr($trimmed, 0, 1) . '***';
    }

    /**
     * Anonymize customer billing/shipping details for GDPR compliance.
     *
     * @param \WC_Order $order
     * @return array
     */
    protected function format_anonymized_customer(\WC_Order $order) {
        $first_name = $order->get_billing_first_name();
        $last_name  = $order->get_billing_last_name();
        $masked_name = trim(self::mask_name($first_name) . ' ' . self::mask_name($last_name));

        $email = $order->get_billing_email();
        $redacted_email = !empty($email) ? Redaction::redact_string($email, true) : '';

        return [
            'id'               => $order->get_customer_id(),
            'role'             => $order->get_customer_id() > 0 ? 'registered' : 'guest',
            'name'             => !empty($masked_name) ? $masked_name : 'Customer',
            'email'            => $redacted_email,
            'billing_country'  => $order->get_billing_country(),
            'billing_city'     => $order->get_billing_city(),
            'billing_postcode' => $order->get_billing_postcode(),
            'billing_address'  => !empty($order->get_billing_address_1()) ? '[REDACTED_ADDRESS]' : '',
            'billing_phone'    => !empty($order->get_billing_phone()) ? '[REDACTED_PHONE]' : '',
            'shipping_country' => $order->get_shipping_country(),
            'shipping_city'    => $order->get_shipping_city(),
            'shipping_postcode'=> $order->get_shipping_postcode(),
        ];
    }

    /**
     * GET /woocommerce/summary
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_summary(\WP_REST_Request $request) {
        if (!$this->is_woocommerce_active()) {
            return $this->error('woocommerce_not_active', esc_html__('WooCommerce is not active on this site.', 'woo-get-data-for-ai'), 400);
        }

        global $wpdb;

        // 1. HPOS State
        $hpos_enabled = class_exists('\Automattic\WooCommerce\Utilities\OrderUtil')
            && method_exists('\Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();

        $authoritative_source = get_option('woocommerce_custom_orders_table_enabled', 'no') === 'yes'
            ? 'custom_orders_table'
            : 'posts_table';

        // 2. Product Counts by Status
        $post_counts = wp_count_posts('product');
        $product_statuses = [
            'publish' => (int) ($post_counts->publish ?? 0),
            'draft'   => (int) ($post_counts->draft ?? 0),
            'pending' => (int) ($post_counts->pending ?? 0),
            'private' => (int) ($post_counts->private ?? 0),
            'trash'   => (int) ($post_counts->trash ?? 0),
        ];
        $total_products = array_sum($product_statuses);

        // Product Counts by Stock Status
        $stock_counts = [
            'instock'    => 0,
            'outofstock' => 0,
            'onbackorder'=> 0,
        ];
        $stock_results = $wpdb->get_results(
            "SELECT meta_value, COUNT(post_id) as count 
             FROM {$wpdb->postmeta} 
             WHERE meta_key = '_stock_status' 
               AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish')
             GROUP BY meta_value"
        );
        if (!empty($stock_results)) {
            foreach ($stock_results as $row) {
                if (isset($stock_counts[$row->meta_value])) {
                    $stock_counts[$row->meta_value] = (int) $row->count;
                }
            }
        }

        // Product Counts by Type
        $product_types = [];
        $registered_types = function_exists('wc_get_product_types') ? wc_get_product_types() : ['simple' => 'Simple', 'variable' => 'Variable'];
        $type_terms = get_terms([
            'taxonomy'   => 'product_type',
            'hide_empty' => false,
        ]);
        if (!is_wp_error($type_terms) && is_array($type_terms)) {
            foreach ($type_terms as $term) {
                $product_types[$term->slug] = [
                    'label' => $registered_types[$term->slug] ?? ucfirst($term->name),
                    'count' => (int) $term->count,
                ];
            }
        }

        // 3. Order Counts by Status
        $order_statuses = [];
        $registered_order_statuses = function_exists('wc_get_order_statuses') ? wc_get_order_statuses() : [];
        $total_orders = 0;
        foreach ($registered_order_statuses as $status_key => $status_label) {
            $raw_status = str_replace('wc-', '', $status_key);
            $count = function_exists('wc_orders_count') ? wc_orders_count($raw_status) : 0;
            $order_statuses[$raw_status] = [
                'label' => $status_label,
                'count' => (int) $count,
            ];
            $total_orders += (int) $count;
        }

        // 4. Payment Gateways summary
        $gateways_summary = [
            'total_installed' => 0,
            'active_count'    => 0,
            'active_gateways' => [],
        ];
        if (function_exists('WC') && WC()->payment_gateways()) {
            $gateways = WC()->payment_gateways()->payment_gateways();
            $gateways_summary['total_installed'] = count($gateways);
            foreach ($gateways as $gateway) {
                if ('yes' === $gateway->enabled) {
                    $gateways_summary['active_count']++;
                    $gateways_summary['active_gateways'][] = [
                        'id'    => $gateway->id,
                        'title' => $gateway->get_title(),
                    ];
                }
            }
        }

        // 5. Shipping Zones summary
        $zones_count = 0;
        if (class_exists('\WC_Shipping_Zones')) {
            $zones = \WC_Shipping_Zones::get_zones();
            $zones_count = count($zones);
        }

        // 6. Registered Customers count
        $customer_counts = count_users();
        $total_customers = $customer_counts['avail_roles']['customer'] ?? 0;

        return $this->response([
            'woocommerce' => [
                'version'              => defined('WC_VERSION') ? WC_VERSION : 'unknown',
                'currency'             => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'USD',
                'currency_symbol'      => function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol()) : '$',
                'hpos_enabled'         => $hpos_enabled,
                'authoritative_source' => $authoritative_source,
                'taxes_enabled'        => function_exists('wc_tax_enabled') ? wc_tax_enabled() : false,
                'prices_include_tax'   => function_exists('wc_prices_include_tax') ? wc_prices_include_tax() : false,
            ],
            'products' => [
                'total'        => $total_products,
                'by_status'    => $product_statuses,
                'by_stock'     => $stock_counts,
                'by_type'      => $product_types,
            ],
            'orders' => [
                'total'     => $total_orders,
                'by_status' => $order_statuses,
            ],
            'payment_gateways' => $gateways_summary,
            'shipping_zones'   => [
                'configured_zones' => $zones_count,
            ],
            'customers' => [
                'total_registered' => (int) $total_customers,
            ],
        ]);
    }

    /**
     * GET /woocommerce/products
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_products(\WP_REST_Request $request) {
        if (!$this->is_woocommerce_active()) {
            return $this->error('woocommerce_not_active', esc_html__('WooCommerce is not active on this site.', 'woo-get-data-for-ai'), 400);
        }

        $status_filter = sanitize_text_field($request->get_param('status') ?: 'publish');
        $type_filter   = sanitize_text_field($request->get_param('type') ?: 'all');
        $stock_filter  = sanitize_text_field($request->get_param('stock_status') ?: 'all');
        $category      = sanitize_text_field($request->get_param('category') ?: '');
        $search        = sanitize_text_field($request->get_param('search') ?: '');
        $per_page      = min(100, max(1, (int) ($request->get_param('per_page') ?: 20)));
        $page          = max(1, (int) ($request->get_param('page') ?: 1));
        $orderby       = sanitize_text_field($request->get_param('orderby') ?: 'date');
        $order         = strtoupper(sanitize_text_field($request->get_param('order') ?: 'DESC'));

        $query_args = [
            'paginate' => true,
            'limit'    => $per_page,
            'page'     => $page,
            'orderby'  => $orderby,
            'order'    => in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC',
        ];

        if ($status_filter !== 'all') {
            $query_args['status'] = $status_filter;
        }

        if ($type_filter !== 'all') {
            $query_args['type'] = $type_filter;
        }

        if ($stock_filter !== 'all') {
            $query_args['stock_status'] = $stock_filter;
        }

        if (!empty($category)) {
            $query_args['category'] = [$category];
        }

        if (!empty($search)) {
            $query_args['s'] = $search;
        }

        $results = wc_get_products($query_args);

        $products = [];
        $total    = 0;
        $max_num_pages = 0;

        if (is_object($results) && isset($results->products)) {
            $total         = (int) $results->total;
            $max_num_pages = (int) $results->max_num_pages;

            foreach ($results->products as $product) {
                /** @var \WC_Product $product */
                $categories = [];
                $cat_terms = get_the_terms($product->get_id(), 'product_cat');
                if (!is_wp_error($cat_terms) && is_array($cat_terms)) {
                    foreach ($cat_terms as $term) {
                        $categories[] = [
                            'id'   => $term->term_id,
                            'name' => $term->name,
                            'slug' => $term->slug,
                        ];
                    }
                }

                $tags = [];
                $tag_terms = get_the_terms($product->get_id(), 'product_tag');
                if (!is_wp_error($tag_terms) && is_array($tag_terms)) {
                    foreach ($tag_terms as $term) {
                        $tags[] = $term->name;
                    }
                }

                $attributes = [];
                foreach ($product->get_attributes() as $attr_name => $attribute) {
                    if (is_object($attribute) && method_exists($attribute, 'get_name')) {
                        $attributes[] = [
                            'name'         => $attribute->get_name(),
                            'is_variation' => $attribute->get_variation(),
                            'options'      => $attribute->is_taxonomy() ? wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names']) : $attribute->get_options(),
                        ];
                    }
                }

                $item = [
                    'id'               => $product->get_id(),
                    'name'             => $product->get_name(),
                    'slug'             => $product->get_slug(),
                    'permalink'        => $product->get_permalink(),
                    'type'             => $product->get_type(),
                    'status'           => $product->get_status(),
                    'is_published'     => $product->get_status() === 'publish',
                    'sku'              => $product->get_sku(),
                    'price'            => $product->get_price(),
                    'regular_price'    => $product->get_regular_price(),
                    'sale_price'       => $product->get_sale_price(),
                    'on_sale'          => $product->is_on_sale(),
                    'stock_status'     => $product->get_stock_status(),
                    'stock_quantity'   => $product->get_stock_quantity(),
                    'manage_stock'     => $product->get_manage_stock(),
                    'categories'       => $categories,
                    'tags'             => $tags,
                    'attributes'       => $attributes,
                    'tax_status'       => $product->get_tax_status(),
                    'tax_class'        => $product->get_tax_class(),
                    'shipping_class'   => $product->get_shipping_class(),
                    'total_sales'      => (int) $product->get_total_sales(),
                    'date_created'     => $product->get_date_created() ? $product->get_date_created()->date('Y-m-d H:i:s') : null,
                    'date_modified'    => $product->get_date_modified() ? $product->get_date_modified()->date('Y-m-d H:i:s') : null,
                ];

                if ($product->is_type('variable')) {
                    /** @var \WC_Product_Variable $product */
                    $children = $product->get_children();
                    $item['variations_count'] = count($children);
                    $item['variation_ids']   = $children;
                }

                $products[] = $item;
            }
        }

        return $this->response([
            'total'       => $total,
            'total_pages' => $max_num_pages,
            'page'        => $page,
            'per_page'    => $per_page,
            'filters'     => [
                'status'       => $status_filter,
                'type'         => $type_filter,
                'stock_status' => $stock_filter,
                'category'     => $category,
                'search'       => $search,
            ],
            'count'       => count($products),
            'products'    => $products,
        ]);
    }

    /**
     * GET /woocommerce/product/{id}
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_product(\WP_REST_Request $request) {
        if (!$this->is_woocommerce_active()) {
            return $this->error('woocommerce_not_active', esc_html__('WooCommerce is not active on this site.', 'woo-get-data-for-ai'), 400);
        }

        $id = (int) $request->get_param('id');
        $product = wc_get_product($id);

        if (!$product) {
            return $this->error('product_not_found', esc_html__('Product not found.', 'woo-get-data-for-ai'), 404);
        }

        // Categories & Tags
        $categories = [];
        $cat_terms = get_the_terms($product->get_id(), 'product_cat');
        if (!is_wp_error($cat_terms) && is_array($cat_terms)) {
            foreach ($cat_terms as $term) {
                $categories[] = [
                    'id'   => $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                ];
            }
        }

        $tags = [];
        $tag_terms = get_the_terms($product->get_id(), 'product_tag');
        if (!is_wp_error($tag_terms) && is_array($tag_terms)) {
            foreach ($tag_terms as $term) {
                $tags[] = $term->name;
            }
        }

        // Images
        $images = [];
        $main_img_id = $product->get_image_id();
        if ($main_img_id) {
            $images[] = [
                'id'       => (int) $main_img_id,
                'src'      => wp_get_attachment_url($main_img_id),
                'is_cover' => true,
            ];
        }
        foreach ($product->get_gallery_image_ids() as $gallery_id) {
            $images[] = [
                'id'       => (int) $gallery_id,
                'src'      => wp_get_attachment_url($gallery_id),
                'is_cover' => false,
            ];
        }

        // Attributes
        $attributes = [];
        foreach ($product->get_attributes() as $attr_name => $attribute) {
            if (is_object($attribute) && method_exists($attribute, 'get_name')) {
                $attributes[] = [
                    'name'         => $attribute->get_name(),
                    'is_variation' => $attribute->get_variation(),
                    'is_visible'   => $attribute->get_visible(),
                    'options'      => $attribute->is_taxonomy() ? wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names']) : $attribute->get_options(),
                ];
            }
        }

        // Variations details if variable product
        $variations = [];
        if ($product->is_type('variable')) {
            /** @var \WC_Product_Variable $product */
            $children_ids = $product->get_children();
            foreach ($children_ids as $var_id) {
                $variation_obj = wc_get_product($var_id);
                if ($variation_obj) {
                    $variations[] = [
                        'id'             => $variation_obj->get_id(),
                        'sku'            => $variation_obj->get_sku(),
                        'price'          => $variation_obj->get_price(),
                        'regular_price'  => $variation_obj->get_regular_price(),
                        'sale_price'     => $variation_obj->get_sale_price(),
                        'stock_status'   => $variation_obj->get_stock_status(),
                        'stock_quantity' => $variation_obj->get_stock_quantity(),
                        'attributes'     => $variation_obj->get_attributes(),
                    ];
                }
            }
        }

        // Postmeta (crucial for custom plugins, ACF fields, ERP IDs)
        $raw_meta = get_post_meta($product->get_id());
        $metadata = [];
        if (is_array($raw_meta)) {
            foreach ($raw_meta as $key => $values) {
                $val = count($values) === 1 ? maybe_unserialize($values[0]) : array_map('maybe_unserialize', $values);
                $metadata[$key] = $val;
            }
        }

        // Unified SEO data extraction if Content_Controller is available
        $seo_data = null;
        if (class_exists('WPAgentBridge\Api\Content_Controller')) {
            try {
                $seo_data = Content_Controller::get_post_seo_data($product->get_id());
            } catch (\Throwable $e) {
                $seo_data = null;
            }
        }

        return $this->response([
            'id'                => $product->get_id(),
            'name'              => $product->get_name(),
            'slug'              => $product->get_slug(),
            'permalink'         => $product->get_permalink(),
            'type'              => $product->get_type(),
            'status'            => $product->get_status(),
            'is_published'      => $product->get_status() === 'publish',
            'sku'               => $product->get_sku(),
            'price'             => $product->get_price(),
            'regular_price'     => $product->get_regular_price(),
            'sale_price'        => $product->get_sale_price(),
            'on_sale'           => $product->is_on_sale(),
            'stock_status'      => $product->get_stock_status(),
            'stock_quantity'    => $product->get_stock_quantity(),
            'manage_stock'      => $product->get_manage_stock(),
            'short_description' => $product->get_short_description(),
            'description'       => $product->get_description(),
            'dimensions'        => [
                'length' => $product->get_length(),
                'width'  => $product->get_width(),
                'height' => $product->get_height(),
                'weight' => $product->get_weight(),
            ],
            'categories'        => $categories,
            'tags'              => $tags,
            'images'            => $images,
            'attributes'        => $attributes,
            'variations'        => $variations,
            'tax_status'        => $product->get_tax_status(),
            'tax_class'         => $product->get_tax_class(),
            'shipping_class'    => $product->get_shipping_class(),
            'upsell_ids'        => $product->get_upsell_ids(),
            'cross_sell_ids'    => $product->get_cross_sell_ids(),
            'total_sales'       => (int) $product->get_total_sales(),
            'date_created'      => $product->get_date_created() ? $product->get_date_created()->date('Y-m-d H:i:s') : null,
            'date_modified'     => $product->get_date_modified() ? $product->get_date_modified()->date('Y-m-d H:i:s') : null,
            'seo'               => $seo_data,
            'metadata'          => $metadata,
        ]);
    }

    /**
     * GET /woocommerce/orders
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_orders(\WP_REST_Request $request) {
        if (!$this->is_woocommerce_active()) {
            return $this->error('woocommerce_not_active', esc_html__('WooCommerce is not active on this site.', 'woo-get-data-for-ai'), 400);
        }

        $status_filter = sanitize_text_field($request->get_param('status') ?: 'all');
        $search        = sanitize_text_field($request->get_param('search') ?: '');
        $customer_id   = (int) $request->get_param('customer_id');
        $per_page      = min(50, max(1, (int) ($request->get_param('per_page') ?: 10)));
        $page          = max(1, (int) ($request->get_param('page') ?: 1));
        $orderby       = sanitize_text_field($request->get_param('orderby') ?: 'date');
        $order         = strtoupper(sanitize_text_field($request->get_param('order') ?: 'DESC'));

        $query_args = [
            'paginate' => true,
            'limit'    => $per_page,
            'page'     => $page,
            'orderby'  => $orderby,
            'order'    => in_array($order, ['ASC', 'DESC'], true) ? $order : 'DESC',
        ];

        if ($status_filter !== 'all') {
            $statuses = array_map('trim', explode(',', $status_filter));
            $query_args['status'] = $statuses;
        }

        if ($customer_id > 0) {
            $query_args['customer_id'] = $customer_id;
        }

        if (!empty($search)) {
            $query_args['s'] = $search;
        }

        $results = wc_get_orders($query_args);

        $orders = [];
        $total  = 0;
        $max_num_pages = 0;

        if (is_object($results) && isset($results->orders)) {
            $total         = (int) $results->total;
            $max_num_pages = (int) $results->max_num_pages;

            foreach ($results->orders as $order_obj) {
                /** @var \WC_Order $order_obj */
                $items = [];
                foreach ($order_obj->get_items() as $item_id => $item) {
                    /** @var \WC_Order_Item_Product $item */
                    $items[] = [
                        'id'           => $item_id,
                        'product_id'   => $item->get_product_id(),
                        'variation_id' => $item->get_variation_id(),
                        'name'         => $item->get_name(),
                        'quantity'     => $item->get_quantity(),
                        'subtotal'     => $item->get_subtotal(),
                        'total'        => $item->get_total(),
                    ];
                }

                $orders[] = [
                    'id'                   => $order_obj->get_id(),
                    'order_number'         => $order_obj->get_order_number(),
                    'status'               => $order_obj->get_status(),
                    'date_created'         => $order_obj->get_date_created() ? $order_obj->get_date_created()->date('Y-m-d H:i:s') : null,
                    'date_modified'        => $order_obj->get_date_modified() ? $order_obj->get_date_modified()->date('Y-m-d H:i:s') : null,
                    'date_paid'            => $order_obj->get_date_paid() ? $order_obj->get_date_paid()->date('Y-m-d H:i:s') : null,
                    'currency'             => $order_obj->get_currency(),
                    'total'                => $order_obj->get_total(),
                    'subtotal'             => $order_obj->get_subtotal(),
                    'total_tax'            => $order_obj->get_total_tax(),
                    'shipping_total'       => $order_obj->get_shipping_total(),
                    'discount_total'       => $order_obj->get_discount_total(),
                    'payment_method'       => $order_obj->get_payment_method(),
                    'payment_method_title' => $order_obj->get_payment_method_title(),
                    'transaction_id'       => $order_obj->get_transaction_id(),
                    'item_count'           => $order_obj->get_item_count(),
                    'items'                => $items,
                    'customer'             => $this->format_anonymized_customer($order_obj),
                ];
            }
        }

        return $this->response([
            'total'       => $total,
            'total_pages' => $max_num_pages,
            'page'        => $page,
            'per_page'    => $per_page,
            'filters'     => [
                'status'      => $status_filter,
                'search'      => $search,
                'customer_id' => $customer_id,
            ],
            'count'       => count($orders),
            'orders'      => $orders,
        ]);
    }

    /**
     * GET /woocommerce/order/{id}
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_order(\WP_REST_Request $request) {
        if (!$this->is_woocommerce_active()) {
            return $this->error('woocommerce_not_active', esc_html__('WooCommerce is not active on this site.', 'woo-get-data-for-ai'), 400);
        }

        $id = (int) $request->get_param('id');
        $order = wc_get_order($id);

        if (!$order) {
            return $this->error('order_not_found', esc_html__('Order not found.', 'woo-get-data-for-ai'), 404);
        }

        // Line Items with Item Meta
        $items = [];
        foreach ($order->get_items() as $item_id => $item) {
            /** @var \WC_Order_Item_Product $item */
            $item_meta = [];
            foreach ($item->get_meta_data() as $meta) {
                $item_meta[$meta->key] = $meta->value;
            }

            $items[] = [
                'id'           => $item_id,
                'product_id'   => $item->get_product_id(),
                'variation_id' => $item->get_variation_id(),
                'name'         => $item->get_name(),
                'quantity'     => $item->get_quantity(),
                'subtotal'     => $item->get_subtotal(),
                'total'        => $item->get_total(),
                'tax_class'    => $item->get_tax_class(),
                'meta_data'    => $item_meta,
            ];
        }

        // Shipping lines
        $shipping_lines = [];
        foreach ($order->get_items('shipping') as $item_id => $item) {
            /** @var \WC_Order_Item_Shipping $item */
            $shipping_meta = [];
            foreach ($item->get_meta_data() as $meta) {
                $meta_data = $meta->get_data();
                $val       = $meta_data['value'];
                // Auto-décodage des objets JSON (ex: fs_costs: {"base":59,"additional":10})
                if (is_string($val) && (strpos($val, '{') === 0 || strpos($val, '[') === 0)) {
                    $decoded_val = json_decode($val, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $val = $decoded_val;
                    }
                }
                $shipping_meta[] = [
                    'id'    => $meta_data['id'] ?? null,
                    'key'   => $meta_data['key'],
                    'value' => $val,
                ];
            }
            $shipping_lines[] = [
                'id'           => $item_id,
                'method_title' => $item->get_name(),
                'method_id'    => $item->get_method_id(),
                'instance_id'  => $item->get_instance_id(),
                'total'        => $item->get_total(),
                'total_tax'    => $item->get_total_tax(),
                'meta_data'    => $shipping_meta,
            ];
        }

        // Fee lines
        $fee_lines = [];
        foreach ($order->get_items('fee') as $item_id => $item) {
            /** @var \WC_Order_Item_Fee $item */
            $fee_lines[] = [
                'id'        => $item_id,
                'name'      => $item->get_name(),
                'total'     => $item->get_total(),
                'total_tax' => $item->get_total_tax(),
            ];
        }

        // Coupon lines
        $coupon_lines = [];
        foreach ($order->get_items('coupon') as $item_id => $item) {
            /** @var \WC_Order_Item_Coupon $item */
            $coupon_lines[] = [
                'id'             => $item_id,
                'code'           => $item->get_code(),
                'discount'       => $item->get_discount(),
                'discount_tax'   => $item->get_discount_tax(),
            ];
        }

        // Refunds
        $refunds = [];
        foreach ($order->get_refunds() as $refund) {
            /** @var \WC_Order_Refund $refund */
            $refunds[] = [
                'id'           => $refund->get_id(),
                'amount'       => $refund->get_amount(),
                'reason'       => $refund->get_reason(),
                'date_created' => $refund->get_date_created() ? $refund->get_date_created()->date('Y-m-d H:i:s') : null,
            ];
        }

        // Order Notes (Crucial for diagnostic: payment gateway error codes, auth tokens)
        $order_notes = [];
        if (function_exists('wc_get_order_notes')) {
            $raw_notes = wc_get_order_notes(['order_id' => $order->get_id()]);
            foreach ($raw_notes as $note) {
                $order_notes[] = [
                    'id'            => $note->id,
                    'date_created'  => $note->date_created ? $note->date_created->date('Y-m-d H:i:s') : null,
                    'content'       => Redaction::redact_string($note->content),
                    'customer_note' => (bool) $note->customer_note,
                    'added_by'      => $note->added_by,
                ];
            }
        }

        // Order Metadata (HPOS or postmeta, sanitized via Redaction)
        $metadata = [];
        foreach ($order->get_meta_data() as $meta) {
            $metadata[$meta->key] = $meta->value;
        }

        return $this->response([
            'id'                   => $order->get_id(),
            'order_number'         => $order->get_order_number(),
            'status'               => $order->get_status(),
            'date_created'         => $order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : null,
            'date_modified'        => $order->get_date_modified() ? $order->get_date_modified()->date('Y-m-d H:i:s') : null,
            'date_paid'            => $order->get_date_paid() ? $order->get_date_paid()->date('Y-m-d H:i:s') : null,
            'date_completed'       => $order->get_date_completed() ? $order->get_date_completed()->date('Y-m-d H:i:s') : null,
            'currency'             => $order->get_currency(),
            'total'                => $order->get_total(),
            'subtotal'             => $order->get_subtotal(),
            'total_tax'            => $order->get_total_tax(),
            'shipping_total'       => $order->get_shipping_total(),
            'shipping_tax'         => $order->get_shipping_tax(),
            'discount_total'       => $order->get_discount_total(),
            'discount_tax'         => $order->get_discount_tax(),
            'payment_method'       => $order->get_payment_method(),
            'payment_method_title' => $order->get_payment_method_title(),
            'transaction_id'       => $order->get_transaction_id(),
            'customer'             => $this->format_anonymized_customer($order),
            'items'                => $items,
            'shipping_lines'       => $shipping_lines,
            'fee_lines'            => $fee_lines,
            'coupon_lines'         => $coupon_lines,
            'refunds'              => $refunds,
            'order_notes'          => $order_notes,
            'metadata'             => $metadata,
        ]);
    }

    /**
     * GET /woocommerce/settings
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_settings(\WP_REST_Request $request) {
        if (!$this->is_woocommerce_active()) {
            return $this->error('woocommerce_not_active', esc_html__('WooCommerce is not active on this site.', 'woo-get-data-for-ai'), 400);
        }

        // 1. General Settings
        $general = [
            'currency'              => get_option('woocommerce_currency', 'USD'),
            'currency_pos'          => get_option('woocommerce_currency_pos', 'right'),
            'price_thousand_sep'    => get_option('woocommerce_price_thousand_sep', ' '),
            'price_decimal_sep'     => get_option('woocommerce_price_decimal_sep', ','),
            'price_num_decimals'    => (int) get_option('woocommerce_price_num_decimals', 2),
            'default_country'       => get_option('woocommerce_default_country', ''),
            'allowed_countries'     => get_option('woocommerce_allowed_countries', 'all'),
            'all_except_countries'  => get_option('woocommerce_all_except_countries', []),
            'specific_allowed_countries' => get_option('woocommerce_specific_allowed_countries', []),
            'ship_to_countries'     => get_option('woocommerce_ship_to_countries', ''),
            'enable_coupons'        => get_option('woocommerce_enable_coupons', 'yes') === 'yes',
        ];

        // 2. Tax Settings
        $tax = [
            'calc_taxes'            => function_exists('wc_tax_enabled') ? wc_tax_enabled() : false,
            'prices_include_tax'    => function_exists('wc_prices_include_tax') ? wc_prices_include_tax() : false,
            'tax_based_on'          => get_option('woocommerce_tax_based_on', 'shipping'),
            'shipping_tax_class'    => get_option('woocommerce_shipping_tax_class', 'inherit'),
            'tax_round_at_subtotal' => get_option('woocommerce_tax_round_at_subtotal', 'no') === 'yes',
            'tax_display_shop'      => get_option('woocommerce_tax_display_shop', 'incl'),
            'tax_display_cart'      => get_option('woocommerce_tax_display_cart', 'incl'),
        ];

        // 3. Stock Settings
        $stock = [
            'manage_stock'          => get_option('woocommerce_manage_stock', 'yes') === 'yes',
            'hold_stock_minutes'    => (int) get_option('woocommerce_hold_stock_minutes', 60),
            'notify_low_stock'      => get_option('woocommerce_notify_low_stock', 'yes') === 'yes',
            'notify_no_stock'       => get_option('woocommerce_notify_no_stock', 'yes') === 'yes',
            'low_stock_amount'      => (int) get_option('woocommerce_notify_low_stock_amount', 2),
            'no_stock_amount'       => (int) get_option('woocommerce_notify_no_stock_amount', 0),
            'hide_out_of_stock'     => get_option('woocommerce_hide_out_of_stock_items', 'no') === 'yes',
            'stock_format'          => get_option('woocommerce_stock_format', ''),
        ];

        // 4. Payment Gateways (Settings sanitized via Redaction)
        $payment_gateways = [];
        if (function_exists('WC') && WC()->payment_gateways()) {
            $gateways = WC()->payment_gateways()->payment_gateways();
            foreach ($gateways as $gateway) {
                $payment_gateways[] = [
                    'id'                 => $gateway->id,
                    'title'              => $gateway->get_title(),
                    'description'        => $gateway->get_description(),
                    'enabled'            => $gateway->enabled === 'yes',
                    'method_title'       => $gateway->get_method_title(),
                    'method_description' => $gateway->get_method_description(),
                    'has_fields'         => (bool) $gateway->has_fields,
                    'supports'           => $gateway->supports,
                    'settings'           => $gateway->settings,
                ];
            }
        }

        // 5. Shipping Zones & Methods
        $shipping_zones = [];
        if (class_exists('\WC_Shipping_Zones')) {
            $zones = \WC_Shipping_Zones::get_zones();
            foreach ($zones as $zone_data) {
                $zone_obj         = new \WC_Shipping_Zone($zone_data['zone_id']);
                $shipping_zones[] = $this->format_shipping_zone($zone_obj, $zone_data['zone_order']);
            }
            // Zone "Reste du monde" (zone_id 0)
            $default_zone     = new \WC_Shipping_Zone(0);
            $shipping_zones[] = $this->format_shipping_zone($default_zone, 9999);
        }

        return $this->response([
            'general'          => $general,
            'tax'              => $tax,
            'stock'            => $stock,
            'payment_gateways' => $payment_gateways,
            'shipping_zones'   => $shipping_zones,
        ]);
    }

    /**
     * Dedicated endpoint for full logistics, shipping zones, geo-locations, and methods inspection.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function get_shipping_details(\WP_REST_Request $request) {
        if (!$this->is_woocommerce_active()) {
            return $this->error('woocommerce_not_active', esc_html__('WooCommerce is not active on this site.', 'woo-get-data-for-ai'), 400);
        }

        $zones = [];
        if (class_exists('\WC_Shipping_Zones')) {
            foreach (\WC_Shipping_Zones::get_zones() as $z) {
                $zones[] = $this->format_shipping_zone(new \WC_Shipping_Zone($z['zone_id']), $z['zone_order']);
            }
            $zones[] = $this->format_shipping_zone(new \WC_Shipping_Zone(0), 9999);
        }

        return $this->response([
            'zones_count' => count($zones),
            'zones'       => $zones,
        ]);
    }

    /**
     * Format a WooCommerce shipping zone with its locations and methods.
     *
     * @param \WC_Shipping_Zone $zone_obj
     * @param int $zone_order
     * @return array
     */
    protected function format_shipping_zone(\WC_Shipping_Zone $zone_obj, $zone_order = 0) {
        $methods = [];
        foreach ($zone_obj->get_shipping_methods() as $instance_id => $method) {
            $methods[] = $this->format_shipping_method($method, $instance_id);
        }

        // Retrieve postcodes, states, countries, or continents
        $locations = [];
        if (method_exists($zone_obj, 'get_zone_locations')) {
            foreach ($zone_obj->get_zone_locations() as $loc) {
                $code = is_object($loc) ? ($loc->code ?? '') : (is_array($loc) ? ($loc['code'] ?? '') : '');
                $type = is_object($loc) ? ($loc->type ?? '') : (is_array($loc) ? ($loc['type'] ?? '') : '');
                $locations[] = [
                    'code' => $code,
                    'type' => $type, // 'postcode', 'state', 'country', 'continent'
                ];
            }
        }

        return [
            'id'               => $zone_obj->get_id(),
            'zone_name'        => $zone_obj->get_zone_name(),
            'zone_order'       => $zone_order,
            'locations_count'  => count($locations),
            'locations'        => $locations,
            'shipping_methods' => $methods,
        ];
    }

    /**
     * Parse et formate les règles de calcul Flexible Shipping / Table Rate en préservant toutes les données brutes.
     *
     * @param mixed $raw_rules
     * @return array
     */
    protected function parse_flexible_shipping_rules($raw_rules) {
        if (is_string($raw_rules)) {
            $decoded = json_decode($raw_rules, true);
            $raw_rules = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw_rules)) {
            return [];
        }
        $formatted_rules = [];
        foreach ($raw_rules as $rule_idx => $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $formatted_rules[] = [
                'rule_index'          => $rule_idx,
                'conditions'          => isset($rule['conditions']) ? $rule['conditions'] : [],
                'cost_per_order'      => isset($rule['cost_per_order']) ? $rule['cost_per_order'] : (isset($rule['cost']) ? $rule['cost'] : 0),
                'additional_cost'     => isset($rule['additional_cost']) ? $rule['additional_cost'] : null,
                'additional_cost_per' => isset($rule['additional_cost_per']) ? $rule['additional_cost_per'] : null,
                'special_action'      => isset($rule['special_action']) ? $rule['special_action'] : 'none',
                'description'         => isset($rule['description']) ? $rule['description'] : '',
                'raw'                 => $rule,
            ];
        }
        return $formatted_rules;
    }

    /**
     * Format a shipping method with full backwards compatibility and advanced rules extraction.
     *
     * @param \WC_Shipping_Method $method
     * @param int $instance_id
     * @return array
     */
    protected function format_shipping_method(\WC_Shipping_Method $method, $instance_id) {
        // Base strict existing data (Guaranteed backwards compatibility)
        $data = [
            'instance_id' => (int) $instance_id,
            'id'          => $method->id,
            'title'       => $method->get_title(),
            'enabled'     => $method->is_enabled(),
            'cost'        => isset($method->cost) ? $method->cost : null,
            'tax_status'  => isset($method->tax_status) ? $method->tax_status : null,
        ];
        // 1. Native WooCommerce Shipping Method Settings
        if ('flat_rate' === $method->id) {
            $calculation_type = method_exists($method, 'get_instance_option') ? $method->get_instance_option('type', 'class') : 'class';
            $cost             = method_exists($method, 'get_instance_option') ? $method->get_instance_option('cost', '') : '';
            // Flexible Shipping Table Rate integration in Flat Rate (Flexible Shipping PRO)
            $fs_enabled_opt = method_exists($method, 'get_instance_option')
                ? $method->get_instance_option('fs_calculation_enabled', 'no')
                : ($method->instance_settings['fs_calculation_enabled'] ?? 'no');
            $fs_calculation_enabled = ('yes' === $fs_enabled_opt);
            $raw_fs_rules = method_exists($method, 'get_instance_option')
                ? $method->get_instance_option('fs_method_rules', null)
                : ($method->instance_settings['fs_method_rules'] ?? null);
            // Fallback direct sur l'option WordPress si absent de instance_settings
            if (null === $raw_fs_rules && $instance_id > 0) {
                $inst_opt = get_option('woocommerce_' . $method->id . '_' . $instance_id . '_settings', []);
                if (is_array($inst_opt)) {
                    $raw_fs_rules = $inst_opt['fs_method_rules'] ?? null;
                    if (!$fs_calculation_enabled && isset($inst_opt['fs_calculation_enabled']) && 'yes' === $inst_opt['fs_calculation_enabled']) {
                        $fs_calculation_enabled = true;
                    }
                }
            }
            $formatted_fs_rules = $this->parse_flexible_shipping_rules($raw_fs_rules);
            $data['flat_rate_settings'] = [
                'calculation_type'       => $calculation_type,
                'cost'                   => $cost,
                'fs_calculation_enabled' => $fs_calculation_enabled,
                'fs_method_rules'        => $formatted_fs_rules,
            ];
            // Si Flexible Shipping Table Rate est configuré sur cette méthode Flat Rate
            if ($fs_calculation_enabled || !empty($formatted_fs_rules)) {
                $data['flexible_shipping_table_rate'] = [
                    'enabled'     => $fs_calculation_enabled,
                    'is_pro'      => defined('FLEXIBLE_SHIPPING_PRO_VERSION') || class_exists('WPDesk_Flexible_Shipping_Pro_Plugin'),
                    'rules_count' => count($formatted_fs_rules),
                    'rules'       => $formatted_fs_rules,
                ];
            }
        } elseif ('free_shipping' === $method->id) {
            $data['free_shipping_settings'] = [
                'requires'         => method_exists($method, 'get_instance_option') ? $method->get_instance_option('requires', '') : '',
                'min_amount'       => method_exists($method, 'get_instance_option') ? $method->get_instance_option('min_amount', 0) : 0,
                'ignore_discounts' => method_exists($method, 'get_instance_option') ? ('yes' === $method->get_instance_option('ignore_discounts', 'no')) : false,
            ];
        } elseif ('local_pickup' === $method->id) {
            $data['local_pickup_settings'] = [
                'cost'       => method_exists($method, 'get_instance_option') ? $method->get_instance_option('cost', '') : '',
                'tax_status' => method_exists($method, 'get_instance_option') ? $method->get_instance_option('tax_status', 'taxable') : 'taxable',
            ];
        }
        // 2. Detection and extraction for Standalone Flexible Shipping & Flexible Shipping PRO
        if (in_array($method->id, ['flexible_shipping_single', 'flexible_shipping'], true)) {
            $raw_rules = method_exists($method, 'get_instance_option') ? $method->get_instance_option('method_rules', []) : [];
            $formatted_rules = $this->parse_flexible_shipping_rules($raw_rules);
            $data['flexible_shipping'] = [
                'is_pro'               => defined('FLEXIBLE_SHIPPING_PRO_VERSION') || class_exists('WPDesk_Flexible_Shipping_Pro_Plugin'),
                'calculation_method'   => method_exists($method, 'get_instance_option') ? $method->get_instance_option('method_calculation_method', 'sum') : 'sum',
                'free_shipping'        => method_exists($method, 'get_instance_option') ? $method->get_instance_option('method_free_shipping', '') : '',
                'free_shipping_label'  => method_exists($method, 'get_instance_option') ? $method->get_instance_option('method_free_shipping_label', '') : '',
                'visibility'           => method_exists($method, 'get_instance_option') ? $method->get_instance_option('method_visibility', 'all') : 'all',
                'method_description'   => method_exists($method, 'get_instance_option') ? $method->get_instance_option('method_description', '') : '',
                'rules_count'          => count($formatted_rules),
                'rules'                => $formatted_rules,
            ];
        }
        // 3. Extraction brute sécurisée des options de l'instance
        if (isset($method->instance_settings) && is_array($method->instance_settings)) {
            $sanitized_settings = [];
            foreach ($method->instance_settings as $s_k => $s_v) {
                if (preg_match('/(password|secret|key|token)/i', $s_k)) {
                    continue;
                }
                $sanitized_settings[$s_k] = $s_v;
            }
            $data['raw_instance_settings'] = $sanitized_settings;
        }
        return $data;
    }

    /**
     * Helper to resolve date range into start/end and prior comparison window.
     *
     * @param string $range
     * @param string $custom_start
     * @param string $custom_end
     * @return array
     */
    protected function resolve_date_range($range, $custom_start = '', $custom_end = '') {
        $now = current_time('timestamp');
        $today_start = strtotime('today 00:00:00', $now);
        $today_end   = strtotime('today 23:59:59', $now);

        switch ($range) {
            case 'today':
                $start      = $today_start;
                $end        = $today_end;
                $prev_start = strtotime('-1 day', $today_start);
                $prev_end   = strtotime('-1 day', $today_end);
                $label      = 'Today vs Yesterday';
                break;

            case 'yesterday':
                $start      = strtotime('yesterday 00:00:00', $now);
                $end        = strtotime('yesterday 23:59:59', $now);
                $prev_start = strtotime('-2 days 00:00:00', $now);
                $prev_end   = strtotime('-2 days 23:59:59', $now);
                $label      = 'Yesterday vs Day Prior';
                break;

            case 'last_7_days':
                $start      = strtotime('-6 days 00:00:00', $now);
                $end        = $today_end;
                $diff       = $end - $start;
                $prev_start = $start - $diff - 1;
                $prev_end   = $start - 1;
                $label      = 'Last 7 Days vs Previous 7 Days';
                break;

            case 'this_month':
                $start      = strtotime(date('Y-m-01 00:00:00', $now));
                $end        = $today_end;
                $diff       = $end - $start;
                $prev_start = strtotime('-1 month', $start);
                $prev_end   = $prev_start + $diff;
                $label      = 'This Month to Date vs Prior Month';
                break;

            case 'last_month':
                $start      = strtotime('first day of last month 00:00:00', $now);
                $end        = strtotime('last day of last month 23:59:59', $now);
                $diff       = $end - $start;
                $prev_start = $start - $diff - 1;
                $prev_end   = $start - 1;
                $label      = 'Last Month vs Prior Month';
                break;

            case 'this_year':
                $start      = strtotime(date('Y-01-01 00:00:00', $now));
                $end        = $today_end;
                $prev_start = strtotime('-1 year', $start);
                $prev_end   = strtotime('-1 year', $end);
                $label      = 'This Year to Date vs Prior Year';
                break;

            case 'custom':
                if (!empty($custom_start) && !empty($custom_end)) {
                    $start = strtotime($custom_start . ' 00:00:00');
                    $end   = strtotime($custom_end . ' 23:59:59');
                    $diff  = $end - $start;
                    if ($diff < 0) {
                        $temp  = $start;
                        $start = $end;
                        $end   = $temp;
                        $diff  = abs($diff);
                    }
                    $prev_start = $start - $diff - 1;
                    $prev_end   = $start - 1;
                    $label      = 'Custom Period vs Prior Period';
                    break;
                }
                // Fallthrough to last_30_days if custom dates invalid

            case 'last_30_days':
            default:
                $start      = strtotime('-29 days 00:00:00', $now);
                $end        = $today_end;
                $diff       = $end - $start;
                $prev_start = $start - $diff - 1;
                $prev_end   = $start - 1;
                $label      = 'Last 30 Days vs Previous 30 Days';
                break;
        }

        return [
            'start'      => date('Y-m-d H:i:s', $start),
            'end'        => date('Y-m-d H:i:s', $end),
            'prev_start' => date('Y-m-d H:i:s', $prev_start),
            'prev_end'   => date('Y-m-d H:i:s', $prev_end),
            'label'      => $label,
        ];
    }

    /**
     * Calculate growth percentage between current and previous values.
     *
     * @param float|int $current
     * @param float|int $previous
     * @return float
     */
    protected static function calculate_growth($current, $previous) {
        if ($previous > 0) {
            return round((($current - $previous) / $previous) * 100, 2);
        }
        return $current > 0 ? 100.0 : 0.0;
    }

    /**
     * GET /woocommerce/analytics/sales
     * 100% Native WooCommerce sales performance report without any external tracking plugins.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_sales_analytics(\WP_REST_Request $request) {
        if (!$this->is_woocommerce_active()) {
            return $this->error('woocommerce_not_active', 'WooCommerce is not active on this site.', 404);
        }

        global $wpdb;

        $range       = sanitize_text_field((string) $request->get_param('range'));
        $start_date  = sanitize_text_field((string) $request->get_param('start_date'));
        $end_date    = sanitize_text_field((string) $request->get_param('end_date'));
        $dates       = $this->resolve_date_range($range, $start_date, $end_date);

        $currency        = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR';
        $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol($currency), ENT_QUOTES, 'UTF-8') : '€';

        // Check if wc_order_stats table exists
        $order_stats_table = $wpdb->prefix . 'wc_order_stats';
        $has_order_stats   = $wpdb->get_var("SHOW TABLES LIKE '{$order_stats_table}'") === $order_stats_table;

        $paid_statuses = ['wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold'];
        $status_placeholders = implode("','", array_map('esc_sql', $paid_statuses));

        if ($has_order_stats) {
            // 1. Current Period Aggregates
            $curr_stats = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(order_id) as orders_count,
                    COALESCE(SUM(num_items_sold), 0) as items_sold,
                    COALESCE(SUM(net_total), 0) as net_sales,
                    COALESCE(SUM(total_sales), 0) as gross_sales,
                    COALESCE(SUM(tax_total), 0) as total_tax,
                    COALESCE(SUM(shipping_total), 0) as total_shipping
                FROM {$order_stats_table}
                WHERE date_created >= %s AND date_created <= %s
                AND status IN ('{$status_placeholders}')
            ", $dates['start'], $dates['end']), ARRAY_A);

            // 2. Refunds in Current Period
            $curr_refunds = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(order_id) as refunds_count,
                    COALESCE(SUM(ABS(net_total)), 0) as refunded_amount
                FROM {$order_stats_table}
                WHERE date_created >= %s AND date_created <= %s
                AND (status IN ('wc-refunded', 'refunded') OR net_total < 0)
            ", $dates['start'], $dates['end']), ARRAY_A);

            // 3. Previous Period Aggregates for Growth Comparison
            $prev_stats = $wpdb->get_row($wpdb->prepare("
                SELECT 
                    COUNT(order_id) as orders_count,
                    COALESCE(SUM(num_items_sold), 0) as items_sold,
                    COALESCE(SUM(net_total), 0) as net_sales,
                    COALESCE(SUM(total_sales), 0) as gross_sales
                FROM {$order_stats_table}
                WHERE date_created >= %s AND date_created <= %s
                AND status IN ('{$status_placeholders}')
            ", $dates['prev_start'], $dates['prev_end']), ARRAY_A);

            // 4. Daily Time Series Trend
            $daily_rows = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    DATE(date_created) as date,
                    COUNT(order_id) as orders,
                    COALESCE(SUM(net_total), 0) as net_sales,
                    COALESCE(SUM(total_sales), 0) as gross_sales,
                    COALESCE(SUM(num_items_sold), 0) as items_sold
                FROM {$order_stats_table}
                WHERE date_created >= %s AND date_created <= %s
                AND status IN ('{$status_placeholders}')
                GROUP BY DATE(date_created)
                ORDER BY date ASC
            ", $dates['start'], $dates['end']), ARRAY_A);

        } else {
            // Fallback to wc_orders (HPOS) or wp_posts (legacy)
            $orders_table = $wpdb->prefix . 'wc_orders';
            $has_hpos     = $wpdb->get_var("SHOW TABLES LIKE '{$orders_table}'") === $orders_table;

            if ($has_hpos) {
                $curr_stats = $wpdb->get_row($wpdb->prepare("
                    SELECT 
                        COUNT(id) as orders_count,
                        0 as items_sold,
                        COALESCE(SUM(total_amount - tax_amount), 0) as net_sales,
                        COALESCE(SUM(total_amount), 0) as gross_sales,
                        COALESCE(SUM(tax_amount), 0) as total_tax,
                        0 as total_shipping
                    FROM {$orders_table}
                    WHERE date_created_gmt >= %s AND date_created_gmt <= %s
                    AND type = 'shop_order'
                    AND status IN ('{$status_placeholders}')
                ", $dates['start'], $dates['end']), ARRAY_A);

                $curr_refunds = ['refunds_count' => 0, 'refunded_amount' => 0];

                $prev_stats = $wpdb->get_row($wpdb->prepare("
                    SELECT 
                        COUNT(id) as orders_count,
                        0 as items_sold,
                        COALESCE(SUM(total_amount - tax_amount), 0) as net_sales,
                        COALESCE(SUM(total_amount), 0) as gross_sales
                    FROM {$orders_table}
                    WHERE date_created_gmt >= %s AND date_created_gmt <= %s
                    AND type = 'shop_order'
                    AND status IN ('{$status_placeholders}')
                ", $dates['prev_start'], $dates['prev_end']), ARRAY_A);

                $daily_rows = $wpdb->get_results($wpdb->prepare("
                    SELECT 
                        DATE(date_created_gmt) as date,
                        COUNT(id) as orders,
                        COALESCE(SUM(total_amount - tax_amount), 0) as net_sales,
                        COALESCE(SUM(total_amount), 0) as gross_sales,
                        0 as items_sold
                    FROM {$orders_table}
                    WHERE date_created_gmt >= %s AND date_created_gmt <= %s
                    AND type = 'shop_order'
                    AND status IN ('{$status_placeholders}')
                    GROUP BY DATE(date_created_gmt)
                    ORDER BY date ASC
                ", $dates['start'], $dates['end']), ARRAY_A);
            } else {
                // Legacy wp_posts + postmeta
                $curr_stats   = ['orders_count' => 0, 'items_sold' => 0, 'net_sales' => 0, 'gross_sales' => 0, 'total_tax' => 0, 'total_shipping' => 0];
                $curr_refunds = ['refunds_count' => 0, 'refunded_amount' => 0];
                $prev_stats   = ['orders_count' => 0, 'items_sold' => 0, 'net_sales' => 0, 'gross_sales' => 0];
                $daily_rows   = [];
            }
        }

        $orders_count   = (int) ($curr_stats['orders_count'] ?? 0);
        $net_sales      = (float) ($curr_stats['net_sales'] ?? 0);
        $gross_sales    = (float) ($curr_stats['gross_sales'] ?? 0);
        $items_sold     = (int) ($curr_stats['items_sold'] ?? 0);
        $total_tax      = (float) ($curr_stats['total_tax'] ?? 0);
        $total_shipping = (float) ($curr_stats['total_shipping'] ?? 0);

        $aov = $orders_count > 0 ? round($net_sales / $orders_count, 2) : 0.0;

        $prev_orders_count = (int) ($prev_stats['orders_count'] ?? 0);
        $prev_net_sales    = (float) ($prev_stats['net_sales'] ?? 0);
        $prev_gross_sales  = (float) ($prev_stats['gross_sales'] ?? 0);
        $prev_aov          = $prev_orders_count > 0 ? round($prev_net_sales / $prev_orders_count, 2) : 0.0;

        $refunds_count   = (int) ($curr_refunds['refunds_count'] ?? 0);
        $refunded_amount = (float) ($curr_refunds['refunded_amount'] ?? 0);

        return $this->response([
            'engine'          => $has_order_stats ? 'woocommerce_order_stats' : ($has_hpos ? 'woocommerce_hpos' : 'legacy'),
            'currency'        => $currency,
            'currency_symbol' => $currency_symbol,
            'period'          => [
                'range'             => $range,
                'label'             => $dates['label'],
                'current_start'     => $dates['start'],
                'current_end'       => $dates['end'],
                'previous_start'    => $dates['prev_start'],
                'previous_end'      => $dates['prev_end'],
            ],
            'kpis'            => [
                'net_sales'              => round($net_sales, 2),
                'gross_sales'            => round($gross_sales, 2),
                'orders_count'           => $orders_count,
                'items_sold'             => $items_sold,
                'average_order_value'    => $aov,
                'total_tax'              => round($total_tax, 2),
                'total_shipping'         => round($total_shipping, 2),
                'refunds_count'          => $refunds_count,
                'refunded_amount'        => round($refunded_amount, 2),
            ],
            'growth_vs_previous' => [
                'net_sales_growth_pct'    => self::calculate_growth($net_sales, $prev_net_sales),
                'gross_sales_growth_pct'  => self::calculate_growth($gross_sales, $prev_gross_sales),
                'orders_count_growth_pct' => self::calculate_growth($orders_count, $prev_orders_count),
                'aov_growth_pct'          => self::calculate_growth($aov, $prev_aov),
                'previous_net_sales'      => round($prev_net_sales, 2),
                'previous_gross_sales'    => round($prev_gross_sales, 2),
                'previous_orders_count'   => $prev_orders_count,
                'previous_aov'            => $prev_aov,
            ],
            'trend'           => array_map(function ($row) {
                return [
                    'date'        => $row['date'],
                    'orders'      => (int) $row['orders'],
                    'net_sales'   => round((float) $row['net_sales'], 2),
                    'gross_sales' => round((float) $row['gross_sales'], 2),
                    'items_sold'  => (int) $row['items_sold'],
                ];
            }, $daily_rows),
        ]);
    }

    /**
     * GET /woocommerce/analytics/top-performers
     * Top selling products by revenue/volume and most used discount coupons.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_top_performers(\WP_REST_Request $request) {
        if (!$this->is_woocommerce_active()) {
            return $this->error('woocommerce_not_active', 'WooCommerce is not active on this site.', 404);
        }

        global $wpdb;

        $range      = sanitize_text_field((string) $request->get_param('range'));
        $start_date = sanitize_text_field((string) $request->get_param('start_date'));
        $end_date   = sanitize_text_field((string) $request->get_param('end_date'));
        $limit      = min(50, max(1, absint($request->get_param('limit')) ?: 10));

        $dates = $this->resolve_date_range($range, $start_date, $end_date);

        $currency        = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR';
        $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol($currency), ENT_QUOTES, 'UTF-8') : '€';

        $product_lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        $order_stats_table    = $wpdb->prefix . 'wc_order_stats';
        $coupon_lookup_table  = $wpdb->prefix . 'wc_order_coupon_lookup';

        $has_product_lookup = $wpdb->get_var("SHOW TABLES LIKE '{$product_lookup_table}'") === $product_lookup_table;
        $has_order_stats    = $wpdb->get_var("SHOW TABLES LIKE '{$order_stats_table}'") === $order_stats_table;
        $has_coupon_lookup  = $wpdb->get_var("SHOW TABLES LIKE '{$coupon_lookup_table}'") === $coupon_lookup_table;

        $paid_statuses = ['wc-completed', 'wc-processing', 'wc-on-hold', 'completed', 'processing', 'on-hold'];
        $status_placeholders = implode("','", array_map('esc_sql', $paid_statuses));

        $top_products = [];
        if ($has_product_lookup && $has_order_stats) {
            $product_rows = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    l.product_id,
                    COALESCE(SUM(l.product_qty), 0) as total_units_sold,
                    COALESCE(SUM(l.product_net_revenue), 0) as total_net_revenue
                FROM {$product_lookup_table} l
                INNER JOIN {$order_stats_table} s ON l.order_id = s.order_id
                WHERE s.date_created >= %s AND s.date_created <= %s
                AND s.status IN ('{$status_placeholders}')
                GROUP BY l.product_id
                ORDER BY total_net_revenue DESC
                LIMIT %d
            ", $dates['start'], $dates['end'], $limit), ARRAY_A);

            foreach ($product_rows as $row) {
                $pid     = (int) $row['product_id'];
                $product = function_exists('wc_get_product') ? wc_get_product($pid) : null;
                $top_products[] = [
                    'id'            => $pid,
                    'name'          => $product ? $product->get_name() : get_the_title($pid),
                    'sku'           => $product ? $product->get_sku() : '',
                    'price'         => $product ? (float) $product->get_price() : 0.0,
                    'stock_status'  => $product ? $product->get_stock_status() : 'unknown',
                    'stock_quantity'=> ($product && $product->managing_stock()) ? $product->get_stock_quantity() : null,
                    'units_sold'    => (int) $row['total_units_sold'],
                    'net_revenue'   => round((float) $row['total_net_revenue'], 2),
                    'edit_url'      => admin_url("post.php?post={$pid}&action=edit"),
                ];
            }
        }

        $top_coupons = [];
        if ($has_coupon_lookup && $has_order_stats) {
            $coupon_rows = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    c.coupon_id,
                    COUNT(c.order_id) as orders_count,
                    COALESCE(SUM(c.discount_amount), 0) as total_discount
                FROM {$coupon_lookup_table} c
                INNER JOIN {$order_stats_table} s ON c.order_id = s.order_id
                WHERE s.date_created >= %s AND s.date_created <= %s
                AND s.status IN ('{$status_placeholders}')
                GROUP BY c.coupon_id
                ORDER BY total_discount DESC
                LIMIT %d
            ", $dates['start'], $dates['end'], $limit), ARRAY_A);

            foreach ($coupon_rows as $row) {
                $cid   = (int) $row['coupon_id'];
                $code  = get_the_title($cid);
                $top_coupons[] = [
                    'id'             => $cid,
                    'code'           => !empty($code) ? $code : "coupon_{$cid}",
                    'orders_count'   => (int) $row['orders_count'],
                    'total_discount' => round((float) $row['total_discount'], 2),
                    'edit_url'       => admin_url("post.php?post={$cid}&action=edit"),
                ];
            }
        }

        return $this->response([
            'period'          => [
                'range'       => $range,
                'label'       => $dates['label'],
                'start'       => $dates['start'],
                'end'         => $dates['end'],
            ],
            'currency'        => $currency,
            'currency_symbol' => $currency_symbol,
            'limit'           => $limit,
            'top_products'    => $top_products,
            'top_coupons'     => $top_coupons,
        ]);
    }

    /**
     * GET /woocommerce/analytics/stock
     * Stock financial valuation, low stock alerts, and dead/dormant stock inventory.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_stock_analytics(\WP_REST_Request $request) {
        if (!$this->is_woocommerce_active()) {
            return $this->error('woocommerce_not_active', 'WooCommerce is not active on this site.', 404);
        }

        global $wpdb;

        $threshold_param = absint($request->get_param('low_stock_threshold'));
        $low_threshold   = $threshold_param > 0 ? $threshold_param : (int) get_option('woocommerce_notify_low_stock_amount', 2);

        $currency        = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EUR';
        $currency_symbol = function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol($currency), ENT_QUOTES, 'UTF-8') : '€';

        // 1. Total Catalog Products Count (Simple + Variable parents)
        $total_catalog_products = (int) $wpdb->get_var("
            SELECT COUNT(ID) FROM {$wpdb->posts}
            WHERE post_type = 'product' AND post_status = 'publish'
        ");

        // 2. Financial Valuation: Managed Stock in Stock
        $valuation_row = $wpdb->get_row("
            SELECT 
                COUNT(p.ID) as managed_products_count,
                COALESCE(SUM(CAST(stock_meta.meta_value AS SIGNED)), 0) as total_units_in_stock,
                COALESCE(SUM(CAST(stock_meta.meta_value AS SIGNED) * CAST(price_meta.meta_value AS DECIMAL(10,2))), 0) as total_inventory_value
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} manage_meta ON (p.ID = manage_meta.post_id AND manage_meta.meta_key = '_manage_stock' AND manage_meta.meta_value = 'yes')
            INNER JOIN {$wpdb->postmeta} stock_meta ON (p.ID = stock_meta.post_id AND stock_meta.meta_key = '_stock' AND CAST(stock_meta.meta_value AS SIGNED) > 0)
            LEFT JOIN {$wpdb->postmeta} price_meta ON (p.ID = price_meta.post_id AND price_meta.meta_key = '_price')
            WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status = 'publish'
        ", ARRAY_A);

        $managed_products_count = (int) ($valuation_row['managed_products_count'] ?? 0);
        $total_units            = (int) ($valuation_row['total_units_in_stock'] ?? 0);
        $total_valuation        = (float) ($valuation_row['total_inventory_value'] ?? 0);

        // 3. Out of Stock Items Count
        $out_of_stock_count = (int) $wpdb->get_var("
            SELECT COUNT(DISTINCT p.ID)
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} status_meta ON (p.ID = status_meta.post_id AND status_meta.meta_key = '_stock_status' AND status_meta.meta_value = 'outofstock')
            WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status = 'publish'
        ");

        // 4. Low Stock Items (Units > 0 and Units <= threshold)
        $low_stock_rows = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.ID as product_id,
                p.post_title,
                p.post_type,
                p.post_parent,
                CAST(stock_meta.meta_value AS SIGNED) as stock_qty,
                CAST(price_meta.meta_value AS DECIMAL(10,2)) as price
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} manage_meta ON (p.ID = manage_meta.post_id AND manage_meta.meta_key = '_manage_stock' AND manage_meta.meta_value = 'yes')
            INNER JOIN {$wpdb->postmeta} stock_meta ON (p.ID = stock_meta.post_id AND stock_meta.meta_key = '_stock' AND CAST(stock_meta.meta_value AS SIGNED) > 0 AND CAST(stock_meta.meta_value AS SIGNED) <= %d)
            LEFT JOIN {$wpdb->postmeta} price_meta ON (p.ID = price_meta.post_id AND price_meta.meta_key = '_price')
            WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status = 'publish'
            ORDER BY stock_qty ASC
            LIMIT 20
        ", $low_threshold), ARRAY_A);

        $low_stock_items = [];
        foreach ($low_stock_rows as $row) {
            $pid     = (int) $row['product_id'];
            $product = function_exists('wc_get_product') ? wc_get_product($pid) : null;
            $low_stock_items[] = [
                'id'             => $pid,
                'name'           => $product ? $product->get_name() : $row['post_title'],
                'sku'            => $product ? $product->get_sku() : '',
                'stock_quantity' => (int) $row['stock_qty'],
                'price'          => (float) $row['price'],
                'edit_url'       => admin_url("post.php?post={$pid}&action=edit"),
            ];
        }

        // 5. Dormant Inventory / Dead Stock (Items with stock > 0, published > 30 days ago, with 0 sales in past 90 days)
        $dormant_items = [];
        $product_lookup_table = $wpdb->prefix . 'wc_order_product_lookup';
        $order_stats_table    = $wpdb->prefix . 'wc_order_stats';
        $has_analytics_tables = $wpdb->get_var("SHOW TABLES LIKE '{$product_lookup_table}'") === $product_lookup_table
                             && $wpdb->get_var("SHOW TABLES LIKE '{$order_stats_table}'") === $order_stats_table;

        $dormant_cutoff_date = date('Y-m-d H:i:s', strtotime('-90 days'));
        $published_cutoff    = date('Y-m-d H:i:s', strtotime('-30 days'));

        if ($has_analytics_tables) {
            $dormant_rows = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    p.ID as product_id,
                    p.post_title,
                    CAST(stock_meta.meta_value AS SIGNED) as stock_qty,
                    CAST(price_meta.meta_value AS DECIMAL(10,2)) as price
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} manage_meta ON (p.ID = manage_meta.post_id AND manage_meta.meta_key = '_manage_stock' AND manage_meta.meta_value = 'yes')
                INNER JOIN {$wpdb->postmeta} stock_meta ON (p.ID = stock_meta.post_id AND stock_meta.meta_key = '_stock' AND CAST(stock_meta.meta_value AS SIGNED) > 0)
                LEFT JOIN {$wpdb->postmeta} price_meta ON (p.ID = price_meta.post_id AND price_meta.meta_key = '_price')
                WHERE p.post_type = 'product'
                AND p.post_status = 'publish'
                AND p.post_date < %s
                AND p.ID NOT IN (
                    SELECT DISTINCT l.product_id 
                    FROM {$product_lookup_table} l
                    INNER JOIN {$order_stats_table} s ON l.order_id = s.order_id
                    WHERE s.date_created >= %s
                )
                ORDER BY stock_qty DESC
                LIMIT 15
            ", $published_cutoff, $dormant_cutoff_date), ARRAY_A);

            foreach ($dormant_rows as $row) {
                $pid     = (int) $row['product_id'];
                $product = function_exists('wc_get_product') ? wc_get_product($pid) : null;
                $dormant_items[] = [
                    'id'             => $pid,
                    'name'           => $product ? $product->get_name() : $row['post_title'],
                    'sku'            => $product ? $product->get_sku() : '',
                    'stock_quantity' => (int) $row['stock_qty'],
                    'price'          => (float) $row['price'],
                    'locked_capital' => round((int) $row['stock_qty'] * (float) $row['price'], 2),
                    'edit_url'       => admin_url("post.php?post={$pid}&action=edit"),
                ];
            }
        }

        return $this->response([
            'currency'        => $currency,
            'currency_symbol' => $currency_symbol,
            'summary'         => [
                'total_catalog_products' => $total_catalog_products,
                'managed_stock_products' => $managed_products_count,
                'total_units_in_stock'   => $total_units,
                'total_inventory_value'  => round($total_valuation, 2),
                'out_of_stock_count'     => $out_of_stock_count,
                'low_stock_count'        => count($low_stock_items),
                'low_stock_threshold'    => $low_threshold,
                'dormant_items_count'    => count($dormant_items),
            ],
            'low_stock_alerts' => $low_stock_items,
            'dormant_stock_90d'=> $dormant_items,
        ]);
    }

    /**
     * GET /woocommerce/webhooks
     * WooCommerce Webhook inventory, status and delivery failure metrics.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_webhooks(\WP_REST_Request $request) {
        if (!$this->is_woocommerce_active()) {
            return $this->error('woocommerce_not_active', 'WooCommerce is not active on this site.', 404);
        }

        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_webhooks';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") !== $table_name) {
            return $this->response([
                'total_webhooks'   => 0,
                'active_webhooks'  => 0,
                'failing_webhooks' => 0,
                'webhooks'         => [],
                'message'          => 'No WooCommerce webhooks table found.',
            ]);
        }

        $rows = $wpdb->get_results("
            SELECT 
                webhook_id,
                status,
                name,
                delivery_url,
                topic,
                failure_count,
                date_created,
                date_modified,
                api_version
            FROM {$table_name}
            ORDER BY failure_count DESC, webhook_id ASC
        ", ARRAY_A);

        $active_count  = 0;
        $failing_count = 0;
        $webhooks      = [];

        foreach ($rows as $row) {
            $status        = $row['status'];
            $failure_count = (int) $row['failure_count'];
            $is_failing    = $failure_count >= 5 || $status === 'disabled';

            if ($status === 'active') {
                $active_count++;
            }
            if ($is_failing) {
                $failing_count++;
            }

            // Sanitize delivery URL (preserve destination domain & path, redact query param tokens)
            $delivery_url = $row['delivery_url'];
            $parsed_url   = parse_url($delivery_url);
            $clean_url    = $delivery_url;
            if (!empty($parsed_url['query'])) {
                // If query has tokens/secrets, redact them
                $clean_url = ($parsed_url['scheme'] ?? 'https') . '://' . ($parsed_url['host'] ?? '') . ($parsed_url['path'] ?? '') . '?[redacted-query]';
            }

            $webhooks[] = [
                'id'            => (int) $row['webhook_id'],
                'name'          => $row['name'],
                'status'        => $status,
                'topic'         => $row['topic'],
                'delivery_url'  => $clean_url,
                'failure_count' => $failure_count,
                'is_failing'    => $is_failing,
                'api_version'   => (int) $row['api_version'],
                'created_at'    => $row['date_created'],
                'updated_at'    => $row['date_modified'],
                'edit_url'      => admin_url("admin.php?page=wc-settings&tab=advanced&section=webhooks&edit-webhook={$row['webhook_id']}"),
            ];
        }

        return $this->response([
            'summary' => [
                'total'         => count($webhooks),
                'active'        => $active_count,
                'paused'        => count(array_filter($webhooks, function ($w) { return $w['status'] === 'paused'; })),
                'disabled'      => count(array_filter($webhooks, function ($w) { return $w['status'] === 'disabled'; })),
                'failing_count' => $failing_count,
                'health'        => $failing_count > 0 ? 'warning' : 'healthy',
                'alert'         => $failing_count > 0 ? sprintf('%d webhook(s) have failed repeatedly (failure count >= 5 or disabled). Integrations with external ERP/CRM may be broken.', $failing_count) : null,
            ],
            'webhooks' => $webhooks,
        ]);
    }
}
