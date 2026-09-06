<?php
namespace WPAgentBridge\Api;

if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Permissions;
use WPAgentBridge\Redaction;

class Content_Controller extends Rest_Controller {

    public function register_routes() {
        // GET /content/pages (List WordPress pages with hierarchy, templates, editor types, and SEO summary)
        register_rest_route(self::NAMESPACE, '/content/pages', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_pages'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'content');
            },
            'args'                => [
                'status'   => [
                    'default'           => 'publish',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'parent'   => [
                    'default'           => null,
                    'sanitize_callback' => function ($param) {
                        return null !== $param && '' !== $param ? absint($param) : null;
                    },
                ],
                'search'   => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'per_page' => [
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
                'page'     => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'orderby'  => [
                    'default'           => 'menu_order',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'order'    => [
                    'default'           => 'ASC',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        // GET /content/page/{id} (Detailed page inspection: raw/rendered content, Gutenberg blocks, and full SEO)
        register_rest_route(self::NAMESPACE, '/content/page/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_page_details'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'content');
            },
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                ],
            ],
        ]);

        // GET /content/posts (List WordPress blog posts with categories, tags, and SEO summary)
        register_rest_route(self::NAMESPACE, '/content/posts', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_posts_list'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'content');
            },
            'args'                => [
                'status'   => [
                    'default'           => 'publish',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'category' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'tag'      => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search'   => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'per_page' => [
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
                'page'     => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'orderby'  => [
                    'default'           => 'date',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'order'    => [
                    'default'           => 'DESC',
                    'sanitize_callback' => 'sanitize_key',
                ],
            ],
        ]);

        // GET /content/post/{id} (Detailed blog post inspection)
        register_rest_route(self::NAMESPACE, '/content/post/(?P<id>\d+)', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_post_details'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'content');
            },
            'args'                => [
                'id' => [
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param > 0;
                    },
                ],
            ],
        ]);

        // GET /content/seo-audit (Site-wide SEO audit report across pages, posts, products, and categories)
        register_rest_route(self::NAMESPACE, '/content/seo-audit', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_seo_audit'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'content');
            },
            'args'                => [
                'include_posts'      => [
                    'default'           => 'false',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'include_products'   => [
                    'default'           => 'false',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'include_categories' => [
                    'default'           => 'false',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'limit'              => [
                    'default'           => 100,
                    'sanitize_callback' => 'absint',
                ],
                'limit_products'     => [
                    'default'           => 50,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    /**
     * GET /content/pages
     * Lists WordPress pages with hierarchy, template info, editor detection, and quick SEO preview.
     */
    public function get_pages(\WP_REST_Request $request) {
        $status   = $request->get_param('status') ?: 'publish';
        $parent   = $request->get_param('parent');
        $search   = $request->get_param('search');
        $per_page = min(max(1, (int) ($request->get_param('per_page') ?: 20)), 100);
        $page     = max(1, (int) ($request->get_param('page') ?: 1));
        $orderby  = $request->get_param('orderby') ?: 'menu_order';
        $order    = strtoupper($request->get_param('order') ?: 'ASC');

        $query_args = [
            'post_type'      => 'page',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => $orderby,
            'order'          => $order,
            'post_status'    => ($status === 'all') ? ['publish', 'draft', 'pending', 'private', 'future'] : explode(',', $status),
        ];

        if (null !== $parent) {
            $query_args['post_parent'] = $parent;
        }

        if (!empty($search)) {
            $query_args['s'] = $search;
        }

        $query = new \WP_Query($query_args);
        $pages = [];

        $front_page_id   = (int) get_option('page_on_front');
        $posts_page_id   = (int) get_option('page_for_posts');
        $privacy_page_id = (int) get_option('wp_page_for_privacy_policy');

        $seo_plugin = $this->detect_seo_plugin();

        foreach ($query->posts as $post) {
            $template_slug = get_page_template_slug($post->ID);
            $editor_type   = $this->detect_editor_type($post);
            $special_roles = $this->get_special_page_roles($post->ID, $front_page_id, $posts_page_id, $privacy_page_id);
            $seo_summary   = $this->get_quick_seo_summary($post->ID, $seo_plugin);

            $pages[] = [
                'id'            => $post->ID,
                'title'         => html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'slug'          => $post->post_name,
                'status'        => $post->post_status,
                'parent_id'     => (int) $post->post_parent,
                'menu_order'    => (int) $post->menu_order,
                'date'          => $post->post_date,
                'modified'      => $post->post_modified,
                'template'      => $template_slug ?: 'default',
                'editor_type'   => $editor_type,
                'special_roles' => $special_roles,
                'word_count'    => str_word_count(wp_strip_all_tags($post->post_content)),
                'seo'           => $seo_summary,
                'url'           => get_permalink($post->ID),
            ];
        }

        return $this->response([
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'per_page'    => $per_page,
            'page'        => $page,
            'seo_plugin'  => $seo_plugin,
            'pages'       => $pages,
        ]);
    }

    /**
     * GET /content/page/{id}
     * Inspects a specific WordPress page in deep detail.
     */
    public function get_page_details(\WP_REST_Request $request) {
        $id = (int) $request->get_param('id');
        $post = get_post($id);

        if (!$post || $post->post_type !== 'page') {
            return $this->error('not_found', 'WordPress page not found.', 404);
        }

        $front_page_id   = (int) get_option('page_on_front');
        $posts_page_id   = (int) get_option('page_for_posts');
        $privacy_page_id = (int) get_option('wp_page_for_privacy_policy');

        $template_slug = get_page_template_slug($post->ID);
        $template_name = $this->get_template_name($template_slug);
        $editor_type   = $this->detect_editor_type($post);
        $special_roles = $this->get_special_page_roles($post->ID, $front_page_id, $posts_page_id, $privacy_page_id);

        // Parent hierarchy
        $parent_info = null;
        if ($post->post_parent > 0) {
            $parent_post = get_post($post->post_parent);
            if ($parent_post) {
                $parent_info = [
                    'id'    => $parent_post->ID,
                    'title' => html_entity_decode(get_the_title($parent_post), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'slug'  => $parent_post->post_name,
                    'url'   => get_permalink($parent_post->ID),
                ];
            }
        }

        // Child pages
        $child_query = new \WP_Query([
            'post_type'      => 'page',
            'post_parent'    => $post->ID,
            'posts_per_page' => 50,
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
            'post_status'    => 'any',
        ]);
        $child_pages = [];
        foreach ($child_query->posts as $child) {
            $child_pages[] = [
                'id'     => $child->ID,
                'title'  => html_entity_decode(get_the_title($child), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'slug'   => $child->post_name,
                'status' => $child->post_status,
                'url'    => get_permalink($child->ID),
            ];
        }

        // Blocks and Shortcodes analysis
        $has_blocks       = has_blocks($post->post_content);
        $blocks_summary   = $has_blocks ? $this->analyze_blocks($post->post_content) : [];
        $shortcodes_found = $this->detect_shortcodes($post->post_content);

        // Full SEO data
        $seo_plugin = $this->detect_seo_plugin();
        $seo_data   = $this->get_full_seo_data($post, $seo_plugin);

        // Custom fields / meta (excluding internal WP editor noise)
        $custom_fields = $this->get_sanitized_custom_fields($post->ID);

        return $this->response([
            'id'               => $post->ID,
            'title'            => html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'slug'             => $post->post_name,
            'status'           => $post->post_status,
            'date'             => $post->post_date,
            'modified'         => $post->post_modified,
            'author'           => [
                'id'           => (int) $post->post_author,
                'display_name' => get_the_author_meta('display_name', $post->post_author),
            ],
            'url'              => get_permalink($post->ID),
            'menu_order'       => (int) $post->menu_order,
            'comment_status'   => $post->comment_status,
            'template'         => [
                'slug' => $template_slug ?: 'default',
                'name' => $template_name,
            ],
            'editor_type'      => $editor_type,
            'special_roles'    => $special_roles,
            'hierarchy'        => [
                'parent'   => $parent_info,
                'children' => $child_pages,
            ],
            'content'          => [
                'raw'              => $post->post_content,
                'rendered'         => apply_filters('the_content', $post->post_content),
                'text'             => wp_strip_all_tags(strip_shortcodes($post->post_content)),
                'excerpt'          => get_the_excerpt($post),
                'word_count'       => str_word_count(wp_strip_all_tags($post->post_content)),
                'has_blocks'       => $has_blocks,
                'blocks_summary'   => $blocks_summary,
                'shortcodes_found' => $shortcodes_found,
            ],
            'featured_image'   => [
                'id'  => (int) get_post_thumbnail_id($post->ID),
                'url' => get_the_post_thumbnail_url($post->ID, 'full') ?: null,
            ],
            'seo'              => $seo_data,
            'custom_fields'    => $custom_fields,
        ]);
    }

    /**
     * GET /content/posts
     * Lists WordPress blog posts with categories, tags, and quick SEO preview.
     */
    public function get_posts_list(\WP_REST_Request $request) {
        $status   = $request->get_param('status') ?: 'publish';
        $category = $request->get_param('category');
        $tag      = $request->get_param('tag');
        $search   = $request->get_param('search');
        $per_page = min(max(1, (int) ($request->get_param('per_page') ?: 20)), 100);
        $page     = max(1, (int) ($request->get_param('page') ?: 1));
        $orderby  = $request->get_param('orderby') ?: 'date';
        $order    = strtoupper($request->get_param('order') ?: 'DESC');

        $query_args = [
            'post_type'      => 'post',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => $orderby,
            'order'          => $order,
            'post_status'    => ($status === 'all') ? ['publish', 'draft', 'pending', 'private', 'future'] : explode(',', $status),
        ];

        if (!empty($category)) {
            $query_args['category_name'] = $category;
        }

        if (!empty($tag)) {
            $query_args['tag'] = $tag;
        }

        if (!empty($search)) {
            $query_args['s'] = $search;
        }

        $query = new \WP_Query($query_args);
        $posts = [];
        $seo_plugin = $this->detect_seo_plugin();

        foreach ($query->posts as $post) {
            $categories = wp_get_post_categories($post->ID, ['fields' => 'all']);
            $cat_data   = array_map(function ($c) {
                return ['id' => $c->term_id, 'name' => $c->name, 'slug' => $c->slug];
            }, $categories);

            $tags     = wp_get_post_tags($post->ID, ['fields' => 'all']);
            $tag_data = array_map(function ($t) {
                return ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug];
            }, $tags);

            $editor_type = $this->detect_editor_type($post);
            $seo_summary = $this->get_quick_seo_summary($post->ID, $seo_plugin);

            $posts[] = [
                'id'          => $post->ID,
                'title'       => html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'slug'        => $post->post_name,
                'status'      => $post->post_status,
                'date'        => $post->post_date,
                'modified'    => $post->post_modified,
                'author'      => [
                    'id'           => (int) $post->post_author,
                    'display_name' => get_the_author_meta('display_name', $post->post_author),
                ],
                'categories'  => $cat_data,
                'tags'        => $tag_data,
                'editor_type' => $editor_type,
                'word_count'  => str_word_count(wp_strip_all_tags($post->post_content)),
                'seo'         => $seo_summary,
                'url'         => get_permalink($post->ID),
            ];
        }

        return $this->response([
            'total'       => (int) $query->found_posts,
            'total_pages' => (int) $query->max_num_pages,
            'per_page'    => $per_page,
            'page'        => $page,
            'seo_plugin'  => $seo_plugin,
            'posts'       => $posts,
        ]);
    }

    /**
     * GET /content/post/{id}
     * Inspects a specific WordPress blog post (or CPT) in deep detail.
     */
    public function get_post_details(\WP_REST_Request $request) {
        $id = (int) $request->get_param('id');
        $post = get_post($id);

        if (!$post) {
            return $this->error('not_found', 'WordPress post not found.', 404);
        }

        $editor_type = $this->detect_editor_type($post);
        $has_blocks  = has_blocks($post->post_content);

        $categories = wp_get_post_categories($post->ID, ['fields' => 'all']);
        $cat_data   = array_map(function ($c) {
            return ['id' => $c->term_id, 'name' => $c->name, 'slug' => $c->slug];
        }, $categories);

        $tags     = wp_get_post_tags($post->ID, ['fields' => 'all']);
        $tag_data = array_map(function ($t) {
            return ['id' => $t->term_id, 'name' => $t->name, 'slug' => $t->slug];
        }, $tags);

        $seo_plugin    = $this->detect_seo_plugin();
        $seo_data      = $this->get_full_seo_data($post, $seo_plugin);
        $custom_fields = $this->get_sanitized_custom_fields($post->ID);

        return $this->response([
            'id'             => $post->ID,
            'post_type'      => $post->post_type,
            'title'          => html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'slug'           => $post->post_name,
            'status'         => $post->post_status,
            'date'           => $post->post_date,
            'modified'       => $post->post_modified,
            'author'         => [
                'id'           => (int) $post->post_author,
                'display_name' => get_the_author_meta('display_name', $post->post_author),
            ],
            'url'            => get_permalink($post->ID),
            'comment_status' => $post->comment_status,
            'categories'     => $cat_data,
            'tags'           => $tag_data,
            'editor_type'    => $editor_type,
            'content'        => [
                'raw'              => $post->post_content,
                'rendered'         => apply_filters('the_content', $post->post_content),
                'text'             => wp_strip_all_tags(strip_shortcodes($post->post_content)),
                'excerpt'          => get_the_excerpt($post),
                'word_count'       => str_word_count(wp_strip_all_tags($post->post_content)),
                'has_blocks'       => $has_blocks,
                'blocks_summary'   => $has_blocks ? $this->analyze_blocks($post->post_content) : [],
                'shortcodes_found' => $this->detect_shortcodes($post->post_content),
            ],
            'featured_image' => [
                'id'  => (int) get_post_thumbnail_id($post->ID),
                'url' => get_the_post_thumbnail_url($post->ID, 'full') ?: null,
            ],
            'seo'            => $seo_data,
            'custom_fields'  => $custom_fields,
        ]);
    }

    /**
     * GET /content/seo-audit
     * Global site-wide SEO audit report across all published pages, blog posts, WooCommerce products, and categories.
     */
    public function get_seo_audit(\WP_REST_Request $request) {
        $include_posts      = filter_var($request->get_param('include_posts'), FILTER_VALIDATE_BOOLEAN);
        $include_products   = filter_var($request->get_param('include_products'), FILTER_VALIDATE_BOOLEAN);
        $include_categories = filter_var($request->get_param('include_categories'), FILTER_VALIDATE_BOOLEAN);
        $limit              = min(max(10, (int) ($request->get_param('limit') ?: 100)), 300);
        $limit_products     = min(max(10, (int) ($request->get_param('limit_products') ?: 50)), 200);

        $seo_plugin = $this->detect_seo_plugin();
        $is_public  = (int) get_option('blog_public') === 1;

        $post_types = ['page'];
        if ($include_posts) {
            $post_types[] = 'post';
        }

        $posts = get_posts([
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'post_type',
            'order'          => 'ASC',
        ]);

        // Optional: Include WooCommerce Products in the audit
        $audited_products_count = 0;
        if ($include_products && class_exists('WooCommerce')) {
            $products = get_posts([
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => $limit_products,
                'orderby'        => 'date',
                'order'          => 'DESC',
            ]);
            $audited_products_count = count($products);
            $posts = array_merge($posts, $products);
        }

        $total_audited           = count($posts);
        $missing_meta_desc       = [];
        $weak_or_missing_titles  = [];
        $noindex_items           = [];
        $missing_og_images       = [];
        $thin_content_items      = [];

        $front_page_id   = (int) get_option('page_on_front');
        $privacy_page_id = (int) get_option('wp_page_for_privacy_policy');

        foreach ($posts as $p) {
            $full_seo    = $this->get_full_seo_data($p, $seo_plugin);
            $is_product  = ($p->post_type === 'product');
            $word_count  = str_word_count(wp_strip_all_tags($p->post_content));
            if ($is_product && !empty($p->post_excerpt)) {
                $word_count += str_word_count(wp_strip_all_tags($p->post_excerpt));
            }
            $is_critical = ($p->ID === $front_page_id || $this->is_wc_critical_page($p->ID) || $is_product);

            $item_info = [
                'id'          => $p->ID,
                'post_type'   => $p->post_type,
                'title'       => html_entity_decode(get_the_title($p), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'slug'        => $p->post_name,
                'url'         => get_permalink($p->ID),
                'is_critical' => $is_critical,
            ];

            // 1. Meta description check
            if (empty(trim($full_seo['description']))) {
                $missing_meta_desc[] = $item_info;
            }

            // 2. Title length check (< 30 or > 65 characters)
            $title_len = mb_strlen($full_seo['title']);
            if ($title_len === 0 || $title_len < 30 || $title_len > 65) {
                $weak_or_missing_titles[] = array_merge($item_info, [
                    'current_title' => $full_seo['title'],
                    'length'        => $title_len,
                    'issue'         => ($title_len === 0) ? 'missing' : (($title_len < 30) ? 'too_short' : 'too_long'),
                ]);
            }

            // 3. Noindex detection (CRITICAL alert if on shop/home/cart/checkout/published product)
            if (!empty($full_seo['robots']['noindex'])) {
                $critical_msg = null;
                if ($is_product) {
                    $critical_msg = 'CRITICAL: Published WooCommerce product is set to noindex!';
                } elseif ($is_critical) {
                    $critical_msg = 'CRITICAL: Key conversion or homepage page is marked as noindex!';
                }

                $noindex_items[] = array_merge($item_info, [
                    'critical_warning' => $critical_msg,
                ]);
            }

            // 4. OpenGraph / Featured image check
            if (empty($full_seo['opengraph']['image_url'])) {
                $missing_og_images[] = $item_info;
            }

            // 5. Thin content check (< 150 words for pages, < 40 words for products)
            $thin_threshold = $is_product ? 40 : 150;
            if ($word_count < $thin_threshold && $p->ID !== $privacy_page_id && !$this->is_wc_system_page($p->ID)) {
                $thin_content_items[] = array_merge($item_info, [
                    'word_count' => $word_count,
                    'threshold'  => $thin_threshold,
                ]);
            }
        }

        // Optional: Categories SEO audit (Missing descriptions & empty taxonomy metas)
        $categories_audit = null;
        if ($include_categories) {
            $taxonomies = ['category'];
            if (class_exists('WooCommerce')) {
                $taxonomies[] = 'product_cat';
            }

            $all_terms = get_terms([
                'taxonomy'   => $taxonomies,
                'hide_empty' => false,
                'number'     => 100,
            ]);

            $missing_cat_desc    = [];
            $noindex_categories  = [];
            $total_cats_audited  = 0;

            if (is_array($all_terms) && !is_wp_error($all_terms)) {
                $total_cats_audited = count($all_terms);
                foreach ($all_terms as $term) {
                    $has_desc   = !empty(trim($term->description));
                    $term_link  = get_term_link($term);
                    $term_url   = !is_wp_error($term_link) ? $term_link : '';
                    $item_count = (int) $term->count;

                    // Inspect term meta for noindex
                    $term_noindex = false;
                    if ($seo_plugin['provider'] === 'rank_math') {
                        $robots = get_term_meta($term->term_id, 'rank_math_robots', true);
                        $term_noindex = is_array($robots) ? in_array('noindex', $robots, true) : (strpos((string) $robots, 'noindex') !== false);
                    } elseif ($seo_plugin['provider'] === 'yoast') {
                        $wpseo_meta = get_option('wpseo_taxonomy_meta');
                        if (isset($wpseo_meta[$term->taxonomy][$term->term_id]['wpseo_noindex'])) {
                            $term_noindex = ($wpseo_meta[$term->taxonomy][$term->term_id]['wpseo_noindex'] === 'noindex');
                        }
                    }

                    if (!$has_desc) {
                        $missing_cat_desc[] = [
                            'term_id'    => $term->term_id,
                            'taxonomy'   => $term->taxonomy,
                            'name'       => html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                            'slug'       => $term->slug,
                            'item_count' => $item_count,
                            'url'        => $term_url,
                            'impact'     => ($item_count > 0) ? 'high_traffic_collection_missing_description' : 'empty_category',
                        ];
                    }

                    if ($term_noindex) {
                        $noindex_categories[] = [
                            'term_id'    => $term->term_id,
                            'taxonomy'   => $term->taxonomy,
                            'name'       => html_entity_decode($term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                            'slug'       => $term->slug,
                            'item_count' => $item_count,
                            'url'        => $term_url,
                        ];
                    }
                }
            }

            $categories_audit = [
                'total_categories_audited'      => $total_cats_audited,
                'missing_description_count'     => count($missing_cat_desc),
                'noindex_categories_count'      => count($noindex_categories),
                'missing_descriptions'          => $missing_cat_desc,
                'noindex_categories'            => $noindex_categories,
            ];
        }

        // Calculate scores
        $desc_coverage = $total_audited > 0 ? round((($total_audited - count($missing_meta_desc)) / $total_audited) * 100, 1) : 100;
        $og_coverage   = $total_audited > 0 ? round((($total_audited - count($missing_og_images)) / $total_audited) * 100, 1) : 100;

        return $this->response([
            'generated_at'            => current_time('mysql'),
            'seo_provider'            => $seo_plugin,
            'site_visibility'         => [
                'is_public'       => $is_public,
                'blog_public_raw' => (int) get_option('blog_public'),
                'alert'           => !$is_public ? 'WARNING: The entire site has search engine visibility disabled (discourage search engines from indexing this site is checked)!' : null,
            ],
            'summary'                 => [
                'total_audited'               => $total_audited,
                'products_audited_count'      => $audited_products_count,
                'meta_description_coverage'   => "{$desc_coverage}%",
                'og_image_coverage'           => "{$og_coverage}%",
                'missing_meta_desc_count'     => count($missing_meta_desc),
                'weak_titles_count'           => count($weak_or_missing_titles),
                'noindex_count'               => count($noindex_items),
                'thin_content_count'          => count($thin_content_items),
            ],
            'issues'                  => [
                'noindex_pages'          => $noindex_items,
                'missing_meta_desc'      => $missing_meta_desc,
                'weak_or_missing_titles' => $weak_or_missing_titles,
                'missing_og_images'      => $missing_og_images,
                'thin_content'           => $thin_content_items,
            ],
            'categories_audit'        => $categories_audit,
        ]);
    }

    // ==========================================
    // HELPER METHODS: SEO DETECTION & EXTRACTION
    // ==========================================

    /**
     * Public static helper to extract unified SEO data for any post or product.
     *
     * @param \WP_Post|int $post
     * @return array
     */
    public static function get_post_seo_data($post) {
        if (is_numeric($post)) {
            $post = get_post($post);
        }
        if (!$post) {
            return [];
        }
        $controller = new self();
        $seo_plugin = $controller->detect_seo_plugin();
        return $controller->get_full_seo_data($post, $seo_plugin);
    }

    /**
     * Detects the active SEO plugin on the WordPress installation.
     */
    public function detect_seo_plugin() {
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) {
            return [
                'provider' => 'yoast',
                'label'    => 'Yoast SEO',
                'version'  => defined('WPSEO_VERSION') ? WPSEO_VERSION : null,
            ];
        }

        if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
            return [
                'provider' => 'rank_math',
                'label'    => 'Rank Math SEO',
                'version'  => defined('RANK_MATH_VERSION') ? RANK_MATH_VERSION : null,
            ];
        }

        if (defined('SEOPRESS_VERSION') || function_exists('seopress_init')) {
            return [
                'provider' => 'seopress',
                'label'    => 'SEOPress',
                'version'  => defined('SEOPRESS_VERSION') ? SEOPRESS_VERSION : null,
            ];
        }

        if (defined('AIOSEO_VERSION') || class_exists('AIOSEO\Plugin\AIOSEO')) {
            return [
                'provider' => 'aioseo',
                'label'    => 'All in One SEO',
                'version'  => defined('AIOSEO_VERSION') ? AIOSEO_VERSION : null,
            ];
        }

        return [
            'provider' => 'native',
            'label'    => 'WordPress Native',
            'version'  => get_bloginfo('version'),
        ];
    }

    /**
     * Returns a lightweight SEO summary for listing endpoints.
     */
    protected function get_quick_seo_summary($post_id, array $seo_plugin) {
        $provider = $seo_plugin['provider'];
        $title    = '';
        $desc     = '';
        $noindex  = false;

        switch ($provider) {
            case 'rank_math':
                $title   = (string) get_post_meta($post_id, 'rank_math_title', true);
                $desc    = (string) get_post_meta($post_id, 'rank_math_description', true);
                $robots  = get_post_meta($post_id, 'rank_math_robots', true);
                if (is_array($robots)) {
                    $noindex = in_array('noindex', $robots, true);
                } elseif (is_string($robots)) {
                    $noindex = strpos($robots, 'noindex') !== false;
                }
                break;

            case 'yoast':
                $title   = (string) get_post_meta($post_id, '_yoast_wpseo_title', true);
                $desc    = (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                $noindex = (int) get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true) === 1;
                break;

            case 'seopress':
                $title   = (string) get_post_meta($post_id, '_seopress_titles_title', true);
                $desc    = (string) get_post_meta($post_id, '_seopress_titles_desc', true);
                $noindex = get_post_meta($post_id, '_seopress_robots_index', true) === 'yes';
                break;

            case 'aioseo':
                $title = (string) get_post_meta($post_id, '_aioseo_title', true);
                $desc  = (string) get_post_meta($post_id, '_aioseo_description', true);
                break;
        }

        // Fallbacks
        $post = get_post($post_id);
        $final_title = !empty($title) ? $title : ($post ? html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8') : '');
        $final_desc  = !empty($desc) ? $desc : ($post && has_excerpt($post) ? get_the_excerpt($post) : '');

        return [
            'provider'      => $provider,
            'is_customized' => !empty($title) || !empty($desc),
            'title'         => $final_title,
            'description'   => $final_desc,
            'is_noindex'    => $noindex,
        ];
    }

    /**
     * Returns full unified SEO data normalized across SEO plugins.
     */
    public function get_full_seo_data($post, array $seo_plugin) {
        $post_id  = $post->ID;
        $provider = $seo_plugin['provider'];

        $title_raw       = '';
        $desc_raw        = '';
        $canonical       = '';
        $noindex         = false;
        $nofollow        = false;
        $og_title        = '';
        $og_desc         = '';
        $og_image        = '';
        $focus_keywords  = [];
        $schema_type     = '';

        switch ($provider) {
            case 'rank_math':
                $title_raw = (string) get_post_meta($post_id, 'rank_math_title', true);
                $desc_raw  = (string) get_post_meta($post_id, 'rank_math_description', true);
                $canonical = (string) get_post_meta($post_id, 'rank_math_canonical_url', true);
                $robots    = get_post_meta($post_id, 'rank_math_robots', true);
                if (is_array($robots)) {
                    $noindex  = in_array('noindex', $robots, true);
                    $nofollow = in_array('nofollow', $robots, true);
                } elseif (is_string($robots)) {
                    $noindex  = strpos($robots, 'noindex') !== false;
                    $nofollow = strpos($robots, 'nofollow') !== false;
                }
                $og_title = (string) get_post_meta($post_id, 'rank_math_facebook_title', true);
                $og_desc  = (string) get_post_meta($post_id, 'rank_math_facebook_description', true);
                $og_image = (string) get_post_meta($post_id, 'rank_math_facebook_image', true);
                $kw_raw   = (string) get_post_meta($post_id, 'rank_math_focus_keyword', true);
                if (!empty($kw_raw)) {
                    $focus_keywords = array_map('trim', explode(',', $kw_raw));
                }
                $schema_type = (string) get_post_meta($post_id, 'rank_math_rich_snippet', true);
                break;

            case 'yoast':
                $title_raw = (string) get_post_meta($post_id, '_yoast_wpseo_title', true);
                $desc_raw  = (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                $canonical = (string) get_post_meta($post_id, '_yoast_wpseo_canonical', true);
                $noindex   = (int) get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true) === 1;
                $nofollow  = (int) get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true) === 1;
                $og_title  = (string) get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true);
                $og_desc   = (string) get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true);
                $og_image  = (string) get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true);
                $kw_raw    = (string) get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
                if (!empty($kw_raw)) {
                    $focus_keywords = [$kw_raw];
                }
                $schema_type = (string) get_post_meta($post_id, '_yoast_wpseo_schema_page_type', true);
                break;

            case 'seopress':
                $title_raw = (string) get_post_meta($post_id, '_seopress_titles_title', true);
                $desc_raw  = (string) get_post_meta($post_id, '_seopress_titles_desc', true);
                $canonical = (string) get_post_meta($post_id, '_seopress_robots_canonical', true);
                $noindex   = get_post_meta($post_id, '_seopress_robots_index', true) === 'yes';
                $nofollow  = get_post_meta($post_id, '_seopress_robots_follow', true) === 'yes';
                $og_title  = (string) get_post_meta($post_id, '_seopress_social_fb_title', true);
                $og_desc   = (string) get_post_meta($post_id, '_seopress_social_fb_desc', true);
                $og_image  = (string) get_post_meta($post_id, '_seopress_social_fb_img', true);
                $kw_raw    = (string) get_post_meta($post_id, '_seopress_analysis_target_kw', true);
                if (!empty($kw_raw)) {
                    $focus_keywords = array_map('trim', explode(',', $kw_raw));
                }
                break;

            case 'aioseo':
                $title_raw = (string) get_post_meta($post_id, '_aioseo_title', true);
                $desc_raw  = (string) get_post_meta($post_id, '_aioseo_description', true);
                $og_title  = (string) get_post_meta($post_id, '_aioseo_og_title', true);
                $og_desc   = (string) get_post_meta($post_id, '_aioseo_og_description', true);
                break;
        }

        // Resolving variables & fallbacks
        $site_name = html_entity_decode(get_bloginfo('name'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $resolved_title = $this->resolve_seo_template($title_raw, $post, $site_name);
        if (empty($resolved_title)) {
            $resolved_title = html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8') . ' - ' . $site_name;
        }

        $resolved_desc = $this->resolve_seo_template($desc_raw, $post, $site_name);
        if (empty($resolved_desc) && has_excerpt($post)) {
            $resolved_desc = html_entity_decode(get_the_excerpt($post), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Featured image fallback for OG
        if (empty($og_image)) {
            $og_image = get_the_post_thumbnail_url($post_id, 'large') ?: '';
        }

        // Global site noindex
        $global_noindex = (int) get_option('blog_public') === 0;

        return [
            'provider'            => $provider,
            'provider_label'      => $seo_plugin['label'],
            'is_customized'       => !empty($title_raw) || !empty($desc_raw),
            'title'               => $resolved_title,
            'title_raw'           => $title_raw ?: null,
            'description'         => $resolved_desc,
            'description_raw'     => $desc_raw ?: null,
            'canonical_url'       => !empty($canonical) ? $canonical : get_permalink($post_id),
            'robots'              => [
                'noindex'                   => $noindex || $global_noindex,
                'nofollow'                  => $nofollow,
                'global_site_discouraged'   => $global_noindex,
            ],
            'opengraph'           => [
                'title'       => !empty($og_title) ? $og_title : $resolved_title,
                'description' => !empty($og_desc) ? $og_desc : $resolved_desc,
                'image_url'   => $og_image ?: null,
            ],
            'focus_keywords'      => $focus_keywords,
            'schema_type'         => $schema_type ?: ($post->post_type === 'page' ? 'WebPage' : 'Article'),
        ];
    }

    /**
     * Replaces standard SEO template placeholders (Yoast / Rank Math).
     */
    protected function resolve_seo_template($template, $post, $site_name) {
        if (empty($template)) {
            return '';
        }

        $title = html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sep   = '-';

        $replacements = [
            '%%title%%'     => $title,
            '%title%'       => $title,
            '%%sitename%%'  => $site_name,
            '%sitename%'    => $site_name,
            '%%sep%%'       => $sep,
            '%sep%'         => $sep,
            '%%date%%'      => get_the_date('', $post),
            '%currentdate%' => get_the_date('', $post),
        ];

        return trim(str_replace(array_keys($replacements), array_values($replacements), $template));
    }

    // ==========================================
    // HELPER METHODS: CONTENT & ENVIRONMENT
    // ==========================================

    /**
     * Detects whether a page is built using Elementor, Gutenberg blocks, or Classic editor.
     */
    protected function detect_editor_type($post) {
        if (get_post_meta($post->ID, '_elementor_edit_mode', true) === 'builder') {
            return 'elementor';
        }

        if (has_blocks($post->post_content)) {
            return 'gutenberg';
        }

        return 'classic';
    }

    /**
     * Resolves human-readable template name from template slug.
     */
    protected function get_template_name($template_slug) {
        if (empty($template_slug) || $template_slug === 'default') {
            return 'Default Template';
        }

        $templates = wp_get_theme()->get_page_templates();
        return isset($templates[$template_slug]) ? $templates[$template_slug] : basename($template_slug);
    }

    /**
     * Identifies special roles assigned to a page (Front Page, WooCommerce endpoints, etc.).
     */
    protected function get_special_page_roles($post_id, $front_id, $posts_id, $privacy_id) {
        $roles = [];

        if ($post_id === $front_id) {
            $roles[] = 'front_page';
        }
        if ($post_id === $posts_id) {
            $roles[] = 'posts_page';
        }
        if ($post_id === $privacy_id) {
            $roles[] = 'privacy_policy';
        }

        // WooCommerce special pages
        if (function_exists('wc_get_page_id')) {
            if ($post_id === wc_get_page_id('cart')) {
                $roles[] = 'woocommerce_cart';
            }
            if ($post_id === wc_get_page_id('checkout')) {
                $roles[] = 'woocommerce_checkout';
            }
            if ($post_id === wc_get_page_id('myaccount')) {
                $roles[] = 'woocommerce_myaccount';
            }
            if ($post_id === wc_get_page_id('shop')) {
                $roles[] = 'woocommerce_shop';
            }
            if ($post_id === wc_get_page_id('terms')) {
                $roles[] = 'woocommerce_terms';
            }
        }

        return $roles;
    }

    /**
     * Checks if a post is a critical WooCommerce page (Shop, Cart, Checkout, My Account).
     */
    protected function is_wc_critical_page($post_id) {
        if (!function_exists('wc_get_page_id')) {
            return false;
        }

        $critical_ids = [
            wc_get_page_id('shop'),
            wc_get_page_id('cart'),
            wc_get_page_id('checkout'),
            wc_get_page_id('myaccount'),
        ];

        return in_array($post_id, $critical_ids, true);
    }

    /**
     * Checks if a post is a WooCommerce system page where thin content is expected (e.g. cart/checkout containing only shortcode).
     */
    protected function is_wc_system_page($post_id) {
        if (!function_exists('wc_get_page_id')) {
            return false;
        }

        $system_ids = [
            wc_get_page_id('cart'),
            wc_get_page_id('checkout'),
            wc_get_page_id('myaccount'),
        ];

        return in_array($post_id, $system_ids, true);
    }

    /**
     * Summarizes block types used in a Gutenberg content tree.
     */
    protected function analyze_blocks($content) {
        $blocks = parse_blocks($content);
        $summary = [];

        $this->count_blocks_recursive($blocks, $summary);
        return $summary;
    }

    protected function count_blocks_recursive($blocks, &$summary) {
        foreach ($blocks as $block) {
            if (!empty($block['blockName'])) {
                $name = $block['blockName'];
                $summary[$name] = isset($summary[$name]) ? $summary[$name] + 1 : 1;
            }
            if (!empty($block['innerBlocks'])) {
                $this->count_blocks_recursive($block['innerBlocks'], $summary);
            }
        }
    }

    /**
     * Detects shortcodes used inside content.
     */
    protected function detect_shortcodes($content) {
        if (empty($content)) {
            return [];
        }

        preg_match_all('/\[([a-zA-Z0-9_\-]+)[^\]]*\]/', $content, $matches);
        if (!empty($matches[1])) {
            return array_values(array_unique($matches[1]));
        }

        return [];
    }

    /**
     * Returns sanitized custom fields excluding WordPress internal editor noise.
     */
    protected function get_sanitized_custom_fields($post_id) {
        $all_meta = get_post_meta($post_id);
        $clean    = [];

        $noise_prefixes = ['_edit_lock', '_edit_last', '_encloseme', '_pingme'];

        foreach ($all_meta as $key => $values) {
            if (in_array($key, $noise_prefixes, true)) {
                continue;
            }

            // Exclude raw elementor json dump here (already handled via /elementor/item/{id})
            if ($key === '_elementor_data') {
                $clean['_elementor_data'] = '[elementor_tree_available_via_/elementor/item/' . $post_id . ']';
                continue;
            }

            $val = count($values) === 1 ? maybe_unserialize($values[0]) : array_map('maybe_unserialize', $values);
            $clean[$key] = $val;
        }

        return Redaction::redact_data($clean);
    }
}
