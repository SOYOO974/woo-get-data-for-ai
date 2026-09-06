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
            $shipping_lines[] = [
                'id'           => $item_id,
                'method_title' => $item->get_name(),
                'method_id'    => $item->get_method_id(),
                'instance_id'  => $item->get_instance_id(),
                'total'        => $item->get_total(),
                'total_tax'    => $item->get_total_tax(),
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
                $zone_obj = new \WC_Shipping_Zone($zone_data['zone_id']);
                $methods = [];
                foreach ($zone_obj->get_shipping_methods() as $instance_id => $method) {
                    $methods[] = [
                        'instance_id' => $instance_id,
                        'id'          => $method->id,
                        'title'       => $method->get_title(),
                        'enabled'     => $method->is_enabled(),
                        'cost'        => isset($method->cost) ? $method->cost : null,
                    ];
                }

                $shipping_zones[] = [
                    'id'               => $zone_data['zone_id'],
                    'zone_name'        => $zone_data['zone_name'],
                    'zone_order'       => $zone_data['zone_order'],
                    'shipping_methods' => $methods,
                ];
            }

            // Add Rest of the World zone (zone_id 0)
            $default_zone = new \WC_Shipping_Zone(0);
            $default_methods = [];
            foreach ($default_zone->get_shipping_methods() as $instance_id => $method) {
                $default_methods[] = [
                    'instance_id' => $instance_id,
                    'id'          => $method->id,
                    'title'       => $method->get_title(),
                    'enabled'     => $method->is_enabled(),
                    'cost'        => isset($method->cost) ? $method->cost : null,
                ];
            }
            $shipping_zones[] = [
                'id'               => 0,
                'zone_name'        => $default_zone->get_zone_name(),
                'zone_order'       => 9999,
                'shipping_methods' => $default_methods,
            ];
        }

        return $this->response([
            'general'          => $general,
            'tax'              => $tax,
            'stock'            => $stock,
            'payment_gateways' => $payment_gateways,
            'shipping_zones'   => $shipping_zones,
        ]);
    }
}
