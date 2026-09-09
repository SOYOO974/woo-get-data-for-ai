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

        // GET /content/seo/settings (Audit global settings & configuration best practices of active SEO plugin)
        register_rest_route(self::NAMESPACE, '/content/seo/settings', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_seo_settings'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'content');
            },
        ]);

        // GET /content/redirections (List URL redirects from Redirection plugin, Rank Math, or 301 Redirects)
        register_rest_route(self::NAMESPACE, '/content/redirections', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_redirections'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'content');
            },
            'args'                => [
                'status'   => [
                    'default'           => 'all',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'search'   => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'code'     => [
                    'default'           => null,
                    'sanitize_callback' => function ($param) {
                        return null !== $param && '' !== $param ? absint($param) : null;
                    },
                ],
                'group'    => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'per_page' => [
                    'default'           => 50,
                    'sanitize_callback' => 'absint',
                ],
                'page'     => [
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);

        // GET /content/redirections/404 (Recent 404 monitoring logs from Redirection plugin)
        register_rest_route(self::NAMESPACE, '/content/redirections/404', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [$this, 'get_404_logs'],
            'permission_callback' => function ($request) {
                return $this->check_access($request, 'content');
            },
            'args'                => [
                'limit'  => [
                    'default'           => 50,
                    'sanitize_callback' => 'absint',
                ],
                'search' => [
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
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

                    // Inspect term meta for noindex across SEO providers
                    $term_noindex = false;
                    if ($seo_plugin['provider'] === 'rank_math') {
                        $robots = get_term_meta($term->term_id, 'rank_math_robots', true);
                        $term_noindex = is_array($robots) ? in_array('noindex', $robots, true) : (strpos((string) $robots, 'noindex') !== false);
                    } elseif ($seo_plugin['provider'] === 'yoast') {
                        $wpseo_meta = get_option('wpseo_taxonomy_meta');
                        if (isset($wpseo_meta[$term->taxonomy][$term->term_id]['wpseo_noindex'])) {
                            $term_noindex = ($wpseo_meta[$term->taxonomy][$term->term_id]['wpseo_noindex'] === 'noindex');
                        }
                        if (!$term_noindex) {
                            $term_meta_noindex = get_term_meta($term->term_id, 'wpseo_noindex', true);
                            $term_noindex = ($term_meta_noindex === 'noindex');
                        }
                    } elseif ($seo_plugin['provider'] === 'the_seo_framework') {
                        $tsf_term = get_term_meta($term->term_id, 'autodescription-term-settings', true);
                        if (is_array($tsf_term) && !empty($tsf_term['noindex'])) {
                            $term_noindex = true;
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

        // Post types global defaults & critical alert
        $page_noindex_default    = $this->is_post_type_globally_noindexed('page', $seo_plugin['provider']);
        $post_noindex_default    = $this->is_post_type_globally_noindexed('post', $seo_plugin['provider']);
        $product_noindex_default = class_exists('WooCommerce') ? $this->is_post_type_globally_noindexed('product', $seo_plugin['provider']) : false;

        $visibility_alerts = [];
        if (!$is_public) {
            $visibility_alerts[] = esc_html__('WARNING: The entire site has search engine visibility disabled (discourage search engines from indexing this site is checked)!', 'woo-get-data-for-ai');
        }
        if ($product_noindex_default) {
            $visibility_alerts[] = esc_html__('CRITICAL ALERT: All WooCommerce products are set to noindex by default in global SEO settings!', 'woo-get-data-for-ai');
        }
        if ($page_noindex_default) {
            $visibility_alerts[] = esc_html__('WARNING: All WordPress pages are set to noindex by default in global SEO settings!', 'woo-get-data-for-ai');
        }

        // Redirections summary
        $redirections_summary = $this->get_redirections_summary();

        return $this->response([
            'generated_at'            => current_time('mysql'),
            'seo_provider'            => $seo_plugin,
            'site_visibility'         => [
                'is_public'             => $is_public,
                'blog_public_raw'       => (int) get_option('blog_public'),
                'alerts'                => $visibility_alerts,
                'alert'                 => !empty($visibility_alerts) ? implode(' | ', $visibility_alerts) : null,
                'sitemap'               => $this->detect_sitemap_info($seo_plugin),
                'post_types_defaults'   => [
                    'page'    => ['noindex' => $page_noindex_default],
                    'post'    => ['noindex' => $post_noindex_default],
                    'product' => ['noindex' => class_exists('WooCommerce') ? $product_noindex_default : null],
                ],
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
                'redirections_count'          => isset($redirections_summary['total_redirects']) ? $redirections_summary['total_redirects'] : 0,
            ],
            'issues'                  => [
                'noindex_pages'          => $noindex_items,
                'missing_meta_desc'      => $missing_meta_desc,
                'weak_or_missing_titles' => $weak_or_missing_titles,
                'missing_og_images'      => $missing_og_images,
                'thin_content'           => $thin_content_items,
            ],
            'categories_audit'        => $categories_audit,
            'redirections'            => $redirections_summary,
            'settings_audit'          => $this->build_seo_settings_audit($seo_plugin),
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
    /**
     * Detects the active SEO plugin on the WordPress installation.
     */
    public function detect_seo_plugin() {
        if (defined('WPSEO_VERSION') || class_exists('WPSEO_Options')) {
            return [
                'provider'           => 'yoast',
                'label'              => 'Yoast SEO',
                'version'            => defined('WPSEO_VERSION') ? WPSEO_VERSION : null,
                'is_premium'         => defined('WPSEO_PREMIUM_VERSION') || class_exists('WPSEO_Premium'),
                'is_woocommerce_seo' => defined('WPSEO_WOO_VERSION'),
            ];
        }

        if (defined('RANK_MATH_VERSION') || class_exists('RankMath')) {
            return [
                'provider' => 'rank_math',
                'label'    => 'Rank Math SEO',
                'version'  => defined('RANK_MATH_VERSION') ? RANK_MATH_VERSION : null,
                'is_pro'   => defined('RANK_MATH_PRO_VERSION'),
            ];
        }

        if (defined('THE_SEO_FRAMEWORK_VERSION') || defined('AUTODESCRIPTION_VERSION') || function_exists('the_seo_framework') || class_exists('The_SEO_Framework\Load')) {
            $tsf_version = null;
            if (defined('THE_SEO_FRAMEWORK_VERSION')) {
                $tsf_version = THE_SEO_FRAMEWORK_VERSION;
            } elseif (defined('AUTODESCRIPTION_VERSION')) {
                $tsf_version = AUTODESCRIPTION_VERSION;
            } elseif (function_exists('the_seo_framework') && method_exists(the_seo_framework(), 'get_version')) {
                $tsf_version = the_seo_framework()->get_version();
            }

            return [
                'provider'     => 'the_seo_framework',
                'label'        => 'The SEO Framework',
                'version'      => $tsf_version,
                'is_extension' => defined('TSFE_VERSION'),
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
        $post     = get_post($post_id);
        $post_type = $post ? $post->post_type : 'post';

        switch ($provider) {
            case 'rank_math':
                $title   = (string) get_post_meta($post_id, 'rank_math_title', true);
                $desc    = (string) get_post_meta($post_id, 'rank_math_description', true);
                $robots  = get_post_meta($post_id, 'rank_math_robots', true);
                if (is_array($robots)) {
                    $noindex = in_array('noindex', $robots, true);
                } elseif (is_string($robots) && !empty($robots)) {
                    $noindex = strpos($robots, 'noindex') !== false;
                } else {
                    $noindex = $this->is_post_type_globally_noindexed($post_type, 'rank_math');
                }
                break;

            case 'yoast':
                $title        = (string) get_post_meta($post_id, '_yoast_wpseo_title', true);
                $desc         = (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                $noindex_meta = (int) get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
                if ($noindex_meta === 1) {
                    $noindex = true;
                } elseif ($noindex_meta === 2) {
                    $noindex = false;
                } else {
                    $noindex = $this->is_post_type_globally_noindexed($post_type, 'yoast');
                }
                break;

            case 'the_seo_framework':
                $title        = (string) get_post_meta($post_id, '_genesis_title', true);
                $desc         = (string) get_post_meta($post_id, '_genesis_description', true);
                $noindex_meta = get_post_meta($post_id, '_genesis_noindex', true);
                if ($noindex_meta !== '' && $noindex_meta !== false) {
                    $noindex = (int) $noindex_meta === 1;
                } else {
                    $noindex = $this->is_post_type_globally_noindexed($post_type, 'the_seo_framework');
                }
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
        $post_id   = $post->ID;
        $post_type = $post->post_type;
        $provider  = $seo_plugin['provider'];

        $title_raw           = '';
        $desc_raw            = '';
        $canonical           = '';
        $noindex             = false;
        $nofollow            = false;
        $noarchive           = false;
        $advanced_robots     = [];
        $og_title            = '';
        $og_desc             = '';
        $og_image            = '';
        $twitter_title       = '';
        $twitter_desc        = '';
        $twitter_image       = '';
        $twitter_card_type   = '';
        $focus_keywords      = [];
        $seo_score           = null;
        $readability_score   = null;
        $primary_category_id = null;
        $breadcrumb_title    = '';
        $redirect_url        = '';
        $schema_type         = '';
        $no_blogname         = false;

        switch ($provider) {
            case 'rank_math':
                $title_raw = (string) get_post_meta($post_id, 'rank_math_title', true);
                $desc_raw  = (string) get_post_meta($post_id, 'rank_math_description', true);
                $canonical = (string) get_post_meta($post_id, 'rank_math_canonical_url', true);
                $robots    = get_post_meta($post_id, 'rank_math_robots', true);
                if (is_array($robots)) {
                    $noindex   = in_array('noindex', $robots, true);
                    $nofollow  = in_array('nofollow', $robots, true);
                    $noarchive = in_array('noarchive', $robots, true);
                } elseif (is_string($robots) && !empty($robots)) {
                    $noindex   = strpos($robots, 'noindex') !== false;
                    $nofollow  = strpos($robots, 'nofollow') !== false;
                    $noarchive = strpos($robots, 'noarchive') !== false;
                } else {
                    $noindex = $this->is_post_type_globally_noindexed($post_type, 'rank_math');
                }

                $adv_robots_meta = get_post_meta($post_id, 'rank_math_advanced_robots', true);
                if (is_array($adv_robots_meta)) {
                    $advanced_robots = $adv_robots_meta;
                } elseif (is_string($adv_robots_meta) && !empty($adv_robots_meta)) {
                    $advanced_robots = array_map('trim', explode(',', $adv_robots_meta));
                }

                $og_title          = (string) get_post_meta($post_id, 'rank_math_facebook_title', true);
                $og_desc           = (string) get_post_meta($post_id, 'rank_math_facebook_description', true);
                $og_image          = (string) get_post_meta($post_id, 'rank_math_facebook_image', true);
                $twitter_title     = (string) get_post_meta($post_id, 'rank_math_twitter_title', true);
                $twitter_desc      = (string) get_post_meta($post_id, 'rank_math_twitter_description', true);
                $twitter_image     = (string) get_post_meta($post_id, 'rank_math_twitter_image', true);
                $twitter_card_type = (string) get_post_meta($post_id, 'rank_math_twitter_card_type', true);

                $kw_raw = (string) get_post_meta($post_id, 'rank_math_focus_keyword', true);
                if (!empty($kw_raw)) {
                    $focus_keywords = array_map('trim', explode(',', $kw_raw));
                }

                $rm_score = get_post_meta($post_id, 'rank_math_seo_score', true);
                if (is_numeric($rm_score) && (int) $rm_score > 0) {
                    $seo_score = (int) $rm_score;
                }

                $primary_tax         = ($post_type === 'product') ? 'product_cat' : 'category';
                $primary_category_id = (int) (get_post_meta($post_id, 'rank_math_primary_' . $primary_tax, true) ?: get_post_meta($post_id, 'rank_math_primary_category', true));
                $breadcrumb_title    = (string) get_post_meta($post_id, 'rank_math_breadcrumb_title', true);
                $schema_type         = (string) get_post_meta($post_id, 'rank_math_rich_snippet', true);
                break;

            case 'yoast':
                $title_raw    = (string) get_post_meta($post_id, '_yoast_wpseo_title', true);
                $desc_raw     = (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
                $canonical    = (string) get_post_meta($post_id, '_yoast_wpseo_canonical', true);
                $noindex_meta = (int) get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
                if ($noindex_meta === 1) {
                    $noindex = true;
                } elseif ($noindex_meta === 2) {
                    $noindex = false;
                } else {
                    $noindex = $this->is_post_type_globally_noindexed($post_type, 'yoast');
                }

                $nofollow = (int) get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true) === 1;

                $adv_meta = (string) get_post_meta($post_id, '_yoast_wpseo_meta-robots-adv', true);
                if (!empty($adv_meta)) {
                    $advanced_robots = array_map('trim', explode(',', $adv_meta));
                    $noarchive       = in_array('noarchive', $advanced_robots, true);
                }

                $og_title      = (string) get_post_meta($post_id, '_yoast_wpseo_opengraph-title', true);
                $og_desc       = (string) get_post_meta($post_id, '_yoast_wpseo_opengraph-description', true);
                $og_image      = (string) get_post_meta($post_id, '_yoast_wpseo_opengraph-image', true);
                $twitter_title = (string) get_post_meta($post_id, '_yoast_wpseo_twitter-title', true);
                $twitter_desc  = (string) get_post_meta($post_id, '_yoast_wpseo_twitter-description', true);
                $twitter_image = (string) get_post_meta($post_id, '_yoast_wpseo_twitter-image', true);

                $kw_raw = (string) get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
                if (!empty($kw_raw)) {
                    $focus_keywords = [$kw_raw];
                }

                $linkdex = get_post_meta($post_id, '_yoast_wpseo_linkdex', true);
                if (is_numeric($linkdex) && (int) $linkdex > 0) {
                    $seo_score = (int) $linkdex;
                }

                $readability = get_post_meta($post_id, '_yoast_wpseo_content_score', true);
                if (!empty($readability)) {
                    $readability_score = is_numeric($readability) ? (int) $readability : (string) $readability;
                }

                $primary_tax         = ($post_type === 'product') ? 'product_cat' : 'category';
                $primary_category_id = (int) (get_post_meta($post_id, '_yoast_wpseo_primary_' . $primary_tax, true) ?: get_post_meta($post_id, '_yoast_wpseo_primary_category', true));
                $breadcrumb_title    = (string) get_post_meta($post_id, '_yoast_wpseo_bctitle', true);
                $schema_type         = (string) get_post_meta($post_id, '_yoast_wpseo_schema_page_type', true);
                break;

            case 'the_seo_framework':
                $title_raw    = (string) get_post_meta($post_id, '_genesis_title', true);
                $no_blogname  = (int) get_post_meta($post_id, '_tsf_title_no_blogname', true) === 1;
                $desc_raw     = (string) get_post_meta($post_id, '_genesis_description', true);
                $canonical    = (string) get_post_meta($post_id, '_genesis_canonical_uri', true);
                $noindex_meta = get_post_meta($post_id, '_genesis_noindex', true);
                if ($noindex_meta !== '' && $noindex_meta !== false) {
                    $noindex = (int) $noindex_meta === 1;
                } else {
                    $noindex = $this->is_post_type_globally_noindexed($post_type, 'the_seo_framework');
                }

                $nofollow  = (int) get_post_meta($post_id, '_genesis_nofollow', true) === 1;
                $noarchive = (int) get_post_meta($post_id, '_genesis_noarchive', true) === 1;
                if ($noarchive) {
                    $advanced_robots[] = 'noarchive';
                }

                $og_title          = (string) get_post_meta($post_id, '_open_graph_title', true);
                $og_desc           = (string) get_post_meta($post_id, '_open_graph_description', true);
                $og_image          = (string) get_post_meta($post_id, '_social_image_url', true);
                $twitter_title     = (string) get_post_meta($post_id, '_twitter_title', true);
                $twitter_desc      = (string) get_post_meta($post_id, '_twitter_description', true);
                $twitter_card_type = (string) get_post_meta($post_id, '_tsf_twitter_card_type', true);

                $primary_tax         = ($post_type === 'product') ? 'product_cat' : 'category';
                $primary_category_id = (int) (get_post_meta($post_id, '_primary_term_' . $primary_tax, true) ?: get_post_meta($post_id, '_primary_term_category', true));
                $redirect_url        = (string) get_post_meta($post_id, 'redirect', true);
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
            $base_title = html_entity_decode(get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $resolved_title = $no_blogname ? $base_title : ($base_title . ' - ' . $site_name);
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

        // Primary category info
        $primary_category = null;
        if ($primary_category_id > 0) {
            $cat_term = get_term($primary_category_id);
            if ($cat_term && !is_wp_error($cat_term)) {
                $primary_category = [
                    'id'   => $cat_term->term_id,
                    'name' => html_entity_decode($cat_term->name, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'slug' => $cat_term->slug,
                ];
            }
        }

        $post_type_default_noindex = $this->is_post_type_globally_noindexed($post_type, $provider);

        return [
            'provider'                  => $provider,
            'provider_label'            => $seo_plugin['label'],
            'is_customized'             => !empty($title_raw) || !empty($desc_raw),
            'title'                     => $resolved_title,
            'title_raw'                 => $title_raw ?: null,
            'description'               => $resolved_desc,
            'description_raw'           => $desc_raw ?: null,
            'canonical_url'             => !empty($canonical) ? $canonical : get_permalink($post_id),
            'robots'                    => [
                'noindex'                   => $noindex || $global_noindex,
                'nofollow'                  => $nofollow,
                'noarchive'                 => $noarchive,
                'advanced_robots'           => !empty($advanced_robots) ? $advanced_robots : null,
                'post_type_default_noindex' => $post_type_default_noindex,
                'global_site_discouraged'   => $global_noindex,
            ],
            'social'                    => [
                'opengraph' => [
                    'title'       => !empty($og_title) ? $og_title : $resolved_title,
                    'description' => !empty($og_desc) ? $og_desc : $resolved_desc,
                    'image_url'   => $og_image ?: null,
                ],
                'twitter'   => [
                    'card_type'   => !empty($twitter_card_type) ? $twitter_card_type : 'summary_large_image',
                    'title'       => !empty($twitter_title) ? $twitter_title : (!empty($og_title) ? $og_title : $resolved_title),
                    'description' => !empty($twitter_desc) ? $twitter_desc : (!empty($og_desc) ? $og_desc : $resolved_desc),
                    'image_url'   => !empty($twitter_image) ? $twitter_image : ($og_image ?: null),
                ],
            ],
            // Backwards compatibility alias for top-level opengraph
            'opengraph'                 => [
                'title'       => !empty($og_title) ? $og_title : $resolved_title,
                'description' => !empty($og_desc) ? $og_desc : $resolved_desc,
                'image_url'   => $og_image ?: null,
            ],
            'focus_keywords'            => $focus_keywords,
            'scores'                    => [
                'seo_score'         => $seo_score,
                'readability_score' => $readability_score,
            ],
            'primary_category'          => $primary_category,
            'breadcrumb_title'          => !empty($breadcrumb_title) ? $breadcrumb_title : null,
            'redirect'                  => !empty($redirect_url) ? $redirect_url : null,
            'schema_type'               => $schema_type ?: ($post_type === 'page' ? 'WebPage' : ($post_type === 'product' ? 'Product' : 'Article')),
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

        // Extract category name if available
        $cat_name = '';
        $categories = get_the_category($post->ID);
        if (!empty($categories) && !is_wp_error($categories)) {
            $cat_name = $categories[0]->name;
        }

        $replacements = [
            '%%title%%'            => $title,
            '%title%'              => $title,
            '%%sitename%%'         => $site_name,
            '%sitename%'           => $site_name,
            '%%sep%%'              => $sep,
            '%sep%'                => $sep,
            '%%date%%'             => get_the_date('', $post),
            '%currentdate%'        => get_the_date('', $post),
            '%%excerpt%%'          => has_excerpt($post) ? wp_strip_all_tags(get_the_excerpt($post)) : '',
            '%excerpt%'            => has_excerpt($post) ? wp_strip_all_tags(get_the_excerpt($post)) : '',
            '%%category%%'         => $cat_name,
            '%category%'           => $cat_name,
            '%%primary_category%%' => $cat_name,
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

    // ==========================================
    // HELPER METHODS: SITEMAP & DEFAULTS
    // ==========================================

    /**
     * Detects active sitemap settings and canonical sitemap URL.
     *
     * @param array $seo_plugin
     * @return array
     */
    protected function detect_sitemap_info(array $seo_plugin) {
        $provider = $seo_plugin['provider'];
        $enabled  = false;
        $url      = null;

        switch ($provider) {
            case 'rank_math':
                $rm_modules = get_option('rank_math_modules', []);
                $enabled    = is_array($rm_modules) ? in_array('sitemap', $rm_modules, true) : true;
                $url        = home_url('/sitemap_index.xml');
                break;

            case 'yoast':
                $wpseo_opt = get_option('wpseo', []);
                $enabled   = !isset($wpseo_opt['enable_xml_sitemap']) || !empty($wpseo_opt['enable_xml_sitemap']);
                $url       = home_url('/sitemap_index.xml');
                break;

            case 'the_seo_framework':
                $tsf_settings = get_option('autodescription-site-settings', []);
                $enabled      = !isset($tsf_settings['sitemaps_output']) || !empty($tsf_settings['sitemaps_output']);
                $url          = home_url('/sitemap.xml');
                break;

            case 'seopress':
                $url     = home_url('/sitemaps.xml');
                $enabled = true;
                break;

            case 'aioseo':
                $url     = home_url('/sitemap.xml');
                $enabled = true;
                break;

            default:
                $enabled = function_exists('wp_sitemaps_get_server');
                $url     = home_url('/wp-sitemap.xml');
                break;
        }

        return [
            'enabled'  => $enabled,
            'url'      => $enabled ? $url : null,
            'provider' => $provider,
        ];
    }

    /**
     * Checks whether a post type is globally set to noindex in the active SEO plugin settings.
     *
     * @param string $post_type
     * @param string $provider
     * @return bool
     */
    protected function is_post_type_globally_noindexed($post_type, $provider) {
        if (empty($post_type)) {
            return false;
        }

        switch ($provider) {
            case 'yoast':
                $wpseo_titles = get_option('wpseo_titles', []);
                return !empty($wpseo_titles['noindex-' . $post_type]);

            case 'rank_math':
                $rm_titles = get_option('rank-math-options-titles', []);
                if (!empty($rm_titles['pt_' . $post_type . '_custom_robots'])) {
                    $robots = isset($rm_titles['pt_' . $post_type . '_robots']) ? $rm_titles['pt_' . $post_type . '_robots'] : [];
                    if (is_array($robots)) {
                        return in_array('noindex', $robots, true);
                    }
                    if (is_string($robots)) {
                        return strpos($robots, 'noindex') !== false;
                    }
                }
                return false;

            case 'the_seo_framework':
                $tsf_settings = get_option('autodescription-site-settings', []);
                if (isset($tsf_settings['robots_' . $post_type . '_noindex'])) {
                    return !empty($tsf_settings['robots_' . $post_type . '_noindex']);
                }
                return false;

            default:
                return false;
        }
    }

    /**
     * Returns high-level redirection summary for SEO audit.
     *
     * @return array
     */
    protected function get_redirections_summary() {
        global $wpdb;

        $redirection_table = $wpdb->prefix . 'redirection_items';
        $rank_math_table   = $wpdb->prefix . 'rank_math_redirections';
        $table_404         = $wpdb->prefix . 'redirection_404';

        // 1. Redirection plugin
        $has_redirection = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $redirection_table)) === $redirection_table;
        if ($has_redirection) {
            $total_redirects = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$redirection_table}");
            $enabled_count   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$redirection_table} WHERE status='enabled'");
            $has_404_table   = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_404)) === $table_404;
            $total_404       = $has_404_table ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_404}") : 0;

            return [
                'provider'           => 'redirection',
                'provider_label'     => 'Redirection (John Godley)',
                'total_redirects'    => $total_redirects,
                'enabled_redirects'  => $enabled_count,
                'disabled_redirects' => $total_redirects - $enabled_count,
                'total_404_logged'   => $total_404,
            ];
        }

        // 2. Rank Math Redirections
        $has_rm_redir = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rank_math_table)) === $rank_math_table;
        if ($has_rm_redir) {
            $total_redirects = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$rank_math_table}");
            $active_count    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$rank_math_table} WHERE status='active'");

            return [
                'provider'           => 'rank_math',
                'provider_label'     => 'Rank Math Redirections',
                'total_redirects'    => $total_redirects,
                'enabled_redirects'  => $active_count,
                'disabled_redirects' => $total_redirects - $active_count,
                'total_404_logged'   => null,
            ];
        }

        // 3. 301 Redirects plugin
        $eps_redirects = get_option('eps_redirects_301');
        if (is_array($eps_redirects) && !empty($eps_redirects)) {
            $total = count($eps_redirects);
            return [
                'provider'           => '301_redirects',
                'provider_label'     => '301 Redirects',
                'total_redirects'    => $total,
                'enabled_redirects'  => $total,
                'disabled_redirects' => 0,
                'total_404_logged'   => null,
            ];
        }

        return [
            'provider'           => null,
            'provider_label'     => 'None Detected',
            'total_redirects'    => 0,
            'enabled_redirects'  => 0,
            'disabled_redirects' => 0,
            'total_404_logged'   => 0,
        ];
    }

    // ==========================================
    // REDIRECTIONS CONTROLLER METHODS
    // ==========================================

    /**
     * GET /content/redirections
     * Lists active 301, 302, 307, 410 URL redirects across Redirection plugin, Rank Math, or 301 Redirects.
     */
    public function get_redirections(\WP_REST_Request $request) {
        global $wpdb;

        $status   = strtolower(trim((string) ($request->get_param('status') ?: 'all')));
        $search   = trim((string) ($request->get_param('search') ?: ''));
        $code     = $request->get_param('code') ? absint($request->get_param('code')) : null;
        $group    = trim((string) ($request->get_param('group') ?: ''));
        $per_page = min(max(1, (int) ($request->get_param('per_page') ?: 50)), 200);
        $page     = max(1, (int) ($request->get_param('page') ?: 1));
        $offset   = ($page - 1) * $per_page;

        $redirection_table = $wpdb->prefix . 'redirection_items';
        $rank_math_table   = $wpdb->prefix . 'rank_math_redirections';

        // 1. Check John Godley's Redirection plugin
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $redirection_table));
        if ($table_exists === $redirection_table) {
            return $this->get_redirections_from_redirection_plugin($per_page, $offset, $page, $status, $search, $code, $group);
        }

        // 2. Check Rank Math Redirections
        $rm_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rank_math_table));
        if ($rm_table_exists === $rank_math_table) {
            return $this->get_redirections_from_rank_math($per_page, $offset, $page, $status, $search, $code);
        }

        // 3. Check 301 Redirects plugin (stored in option eps_redirects_301)
        $eps_redirects = get_option('eps_redirects_301');
        if (is_array($eps_redirects) && !empty($eps_redirects)) {
            return $this->get_redirections_from_eps($eps_redirects, $per_page, $page, $search, $code);
        }

        return $this->response([
            'provider'        => null,
            'provider_label'  => 'None Detected',
            'total_redirects' => 0,
            'total_pages'     => 0,
            'per_page'        => $per_page,
            'page'            => $page,
            'redirects'       => [],
            'notice'          => esc_html__('No supported redirection plugin (Redirection, Rank Math Redirections, 301 Redirects) detected on this site.', 'woo-get-data-for-ai'),
        ]);
    }

    /**
     * Fetch redirects from John Godley's Redirection plugin tables.
     */
    protected function get_redirections_from_redirection_plugin($per_page, $offset, $page, $status, $search, $code, $group) {
        global $wpdb;

        $table_items  = $wpdb->prefix . 'redirection_items';
        $table_groups = $wpdb->prefix . 'redirection_groups';

        $where  = [];
        $params = [];

        if ($status === 'enabled') {
            $where[] = "i.status = 'enabled'";
        } elseif ($status === 'disabled') {
            $where[] = "i.status != 'enabled'";
        }

        if (!empty($code)) {
            $where[]  = "i.action_code = %d";
            $params[] = $code;
        }

        if (!empty($search)) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = "(i.url LIKE %s OR i.action_data LIKE %s OR i.title LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if (!empty($group)) {
            if (is_numeric($group)) {
                $where[]  = "i.group_id = %d";
                $params[] = (int) $group;
            } else {
                $where[]  = "g.name LIKE %s";
                $params[] = '%' . $wpdb->esc_like($group) . '%';
            }
        }

        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count query
        $count_sql = "SELECT COUNT(*) FROM {$table_items} i LEFT JOIN {$table_groups} g ON i.group_id = g.id {$where_sql}";
        $total     = !empty($params) ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params)) : (int) $wpdb->get_var($count_sql);

        // Fetch query
        $query_sql = "SELECT i.id, i.url, i.match_url, i.action_data, i.action_code, i.action_type, i.match_type, i.regex, i.position, i.last_count, i.last_access, i.status, i.title, g.name AS group_name
                      FROM {$table_items} i
                      LEFT JOIN {$table_groups} g ON i.group_id = g.id
                      {$where_sql}
                      ORDER BY i.position ASC, i.id ASC
                      LIMIT %d OFFSET %d";
        $fetch_params = array_merge($params, [$per_page, $offset]);
        $rows         = $wpdb->get_results($wpdb->prepare($query_sql, $fetch_params));

        $redirects = [];
        foreach ($rows as $r) {
            $redirects[] = [
                'id'            => (int) $r->id,
                'source_url'    => $r->url,
                'target_url'    => $r->action_data,
                'status_code'   => (int) $r->action_code ?: 301,
                'action_type'   => $r->action_type,
                'match_type'    => $r->match_type,
                'is_regex'      => (int) $r->regex === 1,
                'position'      => (int) $r->position,
                'hits'          => (int) $r->last_count,
                'last_accessed' => !empty($r->last_access) && $r->last_access !== '0000-00-00 00:00:00' ? $r->last_access : null,
                'status'        => $r->status === 'enabled' ? 'enabled' : 'disabled',
                'title'         => $r->title ?: null,
                'group'         => $r->group_name ?: 'Default',
            ];
        }

        return $this->response([
            'provider'        => 'redirection',
            'provider_label'  => 'Redirection (John Godley)',
            'total_redirects' => $total,
            'total_pages'     => ceil($total / $per_page),
            'per_page'        => $per_page,
            'page'            => $page,
            'redirects'       => $redirects,
        ]);
    }

    /**
     * Fetch redirects from Rank Math Redirections table.
     */
    protected function get_redirections_from_rank_math($per_page, $offset, $page, $status, $search, $code) {
        global $wpdb;

        $table_rm = $wpdb->prefix . 'rank_math_redirections';
        $where    = [];
        $params   = [];

        if ($status === 'enabled') {
            $where[] = "status = 'active'";
        } elseif ($status === 'disabled') {
            $where[] = "status != 'active'";
        }

        if (!empty($code)) {
            $where[]  = "header_code = %d";
            $params[] = $code;
        }

        if (!empty($search)) {
            $like     = '%' . $wpdb->esc_like($search) . '%';
            $where[]  = "(sources LIKE %s OR url_to LIKE %s)";
            $params[] = $like;
            $params[] = $like;
        }

        $where_sql  = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $count_sql  = "SELECT COUNT(*) FROM {$table_rm} {$where_sql}";
        $total      = !empty($params) ? (int) $wpdb->get_var($wpdb->prepare($count_sql, $params)) : (int) $wpdb->get_var($count_sql);

        $query_sql  = "SELECT * FROM {$table_rm} {$where_sql} ORDER BY id ASC LIMIT %d OFFSET %d";
        $fetch_params = array_merge($params, [$per_page, $offset]);
        $rows       = $wpdb->get_results($wpdb->prepare($query_sql, $fetch_params));

        $redirects = [];
        foreach ($rows as $r) {
            $sources_data = maybe_unserialize($r->sources);
            $source_url   = '';
            $is_regex     = false;

            if (is_array($sources_data)) {
                if (isset($sources_data[0]['pattern'])) {
                    $source_url = $sources_data[0]['pattern'];
                    $is_regex   = isset($sources_data[0]['comparison']) && $sources_data[0]['comparison'] === 'regex';
                } else {
                    $source_url = json_encode($sources_data);
                }
            } else {
                $source_url = (string) $r->sources;
            }

            $redirects[] = [
                'id'            => (int) $r->id,
                'source_url'    => $source_url,
                'target_url'    => $r->url_to,
                'status_code'   => (int) $r->header_code ?: 301,
                'action_type'   => 'url',
                'match_type'    => $is_regex ? 'regex' : 'exact',
                'is_regex'      => $is_regex,
                'position'      => (int) $r->id,
                'hits'          => (int) $r->hits,
                'last_accessed' => !empty($r->last_accessed) && $r->last_accessed !== '0000-00-00 00:00:00' ? $r->last_accessed : null,
                'status'        => $r->status === 'active' ? 'enabled' : 'disabled',
                'title'         => null,
                'group'         => 'Rank Math',
            ];
        }

        return $this->response([
            'provider'        => 'rank_math',
            'provider_label'  => 'Rank Math Redirections',
            'total_redirects' => $total,
            'total_pages'     => ceil($total / $per_page),
            'per_page'        => $per_page,
            'page'            => $page,
            'redirects'       => $redirects,
        ]);
    }

    /**
     * Fetch redirects from 301 Redirects plugin options.
     */
    protected function get_redirections_from_eps(array $eps_redirects, $per_page, $page, $search, $code) {
        $filtered = [];
        foreach ($eps_redirects as $item) {
            $from = isset($item['url_from']) ? $item['url_from'] : '';
            $to   = isset($item['url_to']) ? $item['url_to'] : '';
            $sc   = isset($item['status']) ? (int) $item['status'] : 301;

            if (!empty($code) && $sc !== $code) {
                continue;
            }

            if (!empty($search)) {
                if (stripos($from, $search) === false && stripos($to, $search) === false) {
                    continue;
                }
            }

            $filtered[] = [
                'id'            => isset($item['id']) ? (int) $item['id'] : null,
                'source_url'    => $from,
                'target_url'    => $to,
                'status_code'   => $sc,
                'action_type'   => 'url',
                'match_type'    => 'exact',
                'is_regex'      => false,
                'position'      => 0,
                'hits'          => isset($item['count']) ? (int) $item['count'] : 0,
                'last_accessed' => isset($item['last_access']) ? $item['last_access'] : null,
                'status'        => 'enabled',
                'title'         => null,
                'group'         => '301 Redirects',
            ];
        }

        $total   = count($filtered);
        $offset  = ($page - 1) * $per_page;
        $paged   = array_slice($filtered, $offset, $per_page);

        return $this->response([
            'provider'        => '301_redirects',
            'provider_label'  => '301 Redirects',
            'total_redirects' => $total,
            'total_pages'     => ceil($total / $per_page),
            'per_page'        => $per_page,
            'page'            => $page,
            'redirects'       => $paged,
        ]);
    }

    /**
     * GET /content/redirections/404
     * Lists recent and top 404 monitoring logs from Redirection plugin.
     */
    public function get_404_logs(\WP_REST_Request $request) {
        global $wpdb;

        $limit  = min(max(10, (int) ($request->get_param('limit') ?: 50)), 200);
        $search = trim((string) ($request->get_param('search') ?: ''));

        $table_404 = $wpdb->prefix . 'redirection_404';
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_404));

        if ($table_exists !== $table_404) {
            return $this->response([
                'provider'       => null,
                'total_404_logs' => 0,
                'top_404_urls'   => [],
                'recent_logs'    => [],
                'notice'         => esc_html__('No Redirection 404 monitoring table found on this site.', 'woo-get-data-for-ai'),
            ]);
        }

        $where  = '';
        $params = [];
        if (!empty($search)) {
            $where    = "WHERE url LIKE %s";
            $params[] = '%' . $wpdb->esc_like($search) . '%';
        }

        $total = !empty($params) ? (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table_404} {$where}", $params)) : (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table_404}");

        // Top 404 URLs grouped by frequency
        $top_sql = "SELECT url, COUNT(*) as hits_count, MAX(created) as last_seen
                    FROM {$table_404}
                    {$where}
                    GROUP BY url
                    ORDER BY hits_count DESC
                    LIMIT %d";
        $top_params = array_merge($params, [$limit]);
        $top_rows = $wpdb->get_results($wpdb->prepare($top_sql, $top_params));

        $top_404_urls = [];
        foreach ($top_rows as $row) {
            $top_404_urls[] = [
                'url'        => $row->url,
                'hits_count' => (int) $row->hits_count,
                'last_seen'  => $row->last_seen,
            ];
        }

        // Recent 404 entries
        $recent_sql = "SELECT id, created, url, domain, agent, referrer, ip
                       FROM {$table_404}
                       {$where}
                       ORDER BY id DESC
                       LIMIT %d";
        $recent_params = array_merge($params, [min($limit, 30)]);
        $recent_rows   = $wpdb->get_results($wpdb->prepare($recent_sql, $recent_params));

        $recent_logs = [];
        foreach ($recent_rows as $row) {
            $recent_logs[] = [
                'id'       => (int) $row->id,
                'created'  => $row->created,
                'url'      => $row->url,
                'domain'   => $row->domain,
                'agent'    => $row->agent,
                'referrer' => $row->referrer,
                'ip'       => !empty($row->ip) ? Redaction::mask_ip($row->ip) : null,
            ];
        }

        return $this->response([
            'provider'       => 'redirection',
            'provider_label' => 'Redirection 404 Monitor',
            'total_404_logs' => $total,
            'top_404_urls'   => $top_404_urls,
            'recent_logs'    => $recent_logs,
        ]);
    }

    /**
     * Endpoint callback: GET /content/seo/settings
     * Audits global settings & configuration best practices of active SEO plugin.
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public function get_seo_settings(\WP_REST_Request $request) {
        $seo_plugin = $this->detect_seo_plugin();
        $audit      = $this->build_seo_settings_audit($seo_plugin);
        return $this->response($audit);
    }

    /**
     * Build SEO settings audit with actionable recommendations.
     *
     * @param array $seo_plugin
     * @return array
     */
    private function build_seo_settings_audit(array $seo_plugin) {
        $is_public       = (int) get_option('blog_public') === 1;
        $recommendations = [];

        // 1. Global site visibility check
        if ($is_public) {
            $recommendations[] = [
                'key'    => 'blog_public',
                'status' => 'optimal',
                'title'  => esc_html__('Search Engine Visibility Enabled', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Site is configured to allow search engines to index pages.', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'blog_public',
                'status' => 'critical',
                'title'  => esc_html__('Search Engine Visibility Disabled', 'woo-get-data-for-ai'),
                'detail' => esc_html__('CRITICAL: "Discourage search engines from indexing this site" is checked in WordPress Settings > Reading.', 'woo-get-data-for-ai'),
            ];
        }

        // 2. Provider-specific settings audit
        $provider_data = [];
        $provider_key  = isset($seo_plugin['provider']) ? $seo_plugin['provider'] : 'native';

        switch ($provider_key) {
            case 'rank_math':
                $provider_data = $this->get_rank_math_settings_audit();
                break;
            case 'yoast':
                $provider_data = $this->get_yoast_settings_audit();
                break;
            case 'the_seo_framework':
                $provider_data = $this->get_tsf_settings_audit();
                break;
            default:
                $provider_data = [
                    'settings'        => [],
                    'recommendations' => [
                        [
                            'key'    => 'no_seo_plugin',
                            'status' => 'warning',
                            'title'  => esc_html__('No Dedicated SEO Plugin Detected', 'woo-get-data-for-ai'),
                            'detail' => esc_html__('The site relies on native WordPress titles. Installing Rank Math, Yoast SEO, or The SEO Framework is recommended for complete meta and sitemap management.', 'woo-get-data-for-ai'),
                        ],
                    ],
                ];
                break;
        }

        if (!empty($provider_data['recommendations'])) {
            $recommendations = array_merge($recommendations, $provider_data['recommendations']);
        }

        // 3. Redirection plugin settings audit (if active)
        $redir_audit = $this->get_redirection_settings_audit();
        if ($redir_audit && !empty($redir_audit['recommendations'])) {
            $recommendations = array_merge($recommendations, $redir_audit['recommendations']);
        }

        // Compute summary counts
        $optimal_count  = 0;
        $warning_count  = 0;
        $notice_count   = 0;
        $critical_count = 0;

        foreach ($recommendations as $rec) {
            switch ($rec['status']) {
                case 'optimal':
                    $optimal_count++;
                    break;
                case 'critical':
                    $critical_count++;
                    break;
                case 'warning':
                    $warning_count++;
                    break;
                case 'notice':
                    $notice_count++;
                    break;
            }
        }

        $total_checks = count($recommendations);
        $score        = $total_checks > 0 ? round(($optimal_count / $total_checks) * 100) : 100;

        return [
            'generated_at'         => current_time('mysql'),
            'provider'             => $seo_plugin,
            'site_visibility'      => [
                'is_public'       => $is_public,
                'blog_public_raw' => (int) get_option('blog_public'),
            ],
            'settings'             => isset($provider_data['settings']) ? $provider_data['settings'] : [],
            'redirection_settings' => $redir_audit && isset($redir_audit['settings']) ? $redir_audit['settings'] : null,
            'summary'              => [
                'total_checks'   => $total_checks,
                'optimal_count'  => $optimal_count,
                'critical_count' => $critical_count,
                'warning_count'  => $warning_count,
                'notice_count'   => $notice_count,
                'health_score'   => $score,
            ],
            'recommendations'      => $recommendations,
        ];
    }

    /**
     * Extract and audit Rank Math settings.
     *
     * @return array
     */
    private function get_rank_math_settings_audit() {
        $general = get_option('rank-math-options-general', []);
        $titles  = get_option('rank-math-options-titles', []);
        $sitemap = get_option('rank-math-options-sitemap', []);
        $modules = get_option('rank_math_modules', []);
        if (!is_array($modules)) {
            $modules = [];
        }

        $recommendations = [];

        // 1. Attachment redirects
        $attach_redirect = isset($general['attachment_redirect_urls']) ? $general['attachment_redirect_urls'] : 'off';
        if ($attach_redirect === 'on') {
            $recommendations[] = [
                'key'    => 'attachment_redirects',
                'status' => 'optimal',
                'title'  => esc_html__('Media Attachment URLs Redirection', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Attachment pages are automatically redirected to parent posts (prevents thin-content zombie URLs).', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'attachment_redirects',
                'status' => 'warning',
                'title'  => esc_html__('Media Attachment URLs Not Redirected', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Media attachment pages are not redirected. WordPress creates an empty webpage for every uploaded image. Enable "Redirect Attachments" in Rank Math General Settings.', 'woo-get-data-for-ai'),
            ];
        }

        // 2. Strip category base
        $strip_cat = isset($general['strip_category_base']) ? $general['strip_category_base'] : 'off';
        if ($strip_cat === 'on') {
            $recommendations[] = [
                'key'    => 'strip_category_base',
                'status' => 'optimal',
                'title'  => esc_html__('Strip Category Base', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Category base prefix is stripped from URLs for cleaner permalinks.', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'strip_category_base',
                'status' => 'notice',
                'title'  => esc_html__('Category Base Retained (/category/)', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Category base (/category/) is retained in category URLs. Enabling "Strip Category Base" can shorten URLs if no redirect conflicts exist.', 'woo-get-data-for-ai'),
            ];
        }

        // 3. Tag archives indexation (post_tag)
        $tag_robots = isset($titles['tax_post_tag_robots']) && is_array($titles['tax_post_tag_robots']) ? $titles['tax_post_tag_robots'] : [];
        if (in_array('noindex', $tag_robots, true)) {
            $recommendations[] = [
                'key'    => 'tags_indexation',
                'status' => 'optimal',
                'title'  => esc_html__('Tag Archives Set to Noindex', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Blog post tags (post_tag) are set to noindex, preventing thin taxonomy duplication.', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'tags_indexation',
                'status' => 'notice',
                'title'  => esc_html__('Tag Archives Are Indexed', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Post tags are set to index. Unless you provide unique editorial text on each tag page, consider setting tags to noindex to protect your crawl budget.', 'woo-get-data-for-ai'),
            ];
        }

        // 4. Product tags indexation (WooCommerce)
        if (class_exists('WooCommerce')) {
            $prod_tag_robots = isset($titles['tax_product_tag_robots']) && is_array($titles['tax_product_tag_robots']) ? $titles['tax_product_tag_robots'] : [];
            if (in_array('noindex', $prod_tag_robots, true)) {
                $recommendations[] = [
                    'key'    => 'product_tags_indexation',
                    'status' => 'optimal',
                    'title'  => esc_html__('Product Tag Archives Set to Noindex', 'woo-get-data-for-ai'),
                    'detail' => esc_html__('WooCommerce product tags (product_tag) are set to noindex.', 'woo-get-data-for-ai'),
                ];
            } else {
                $recommendations[] = [
                    'key'    => 'product_tags_indexation',
                    'status' => 'notice',
                    'title'  => esc_html__('Product Tag Archives Are Indexed', 'woo-get-data-for-ai'),
                    'detail' => esc_html__('Product tags are set to index. Empty or auto-generated product tags can dilute product category rankings.', 'woo-get-data-for-ai'),
                ];
            }
        }

        // 5. Default OpenGraph image
        $default_og = !empty($titles['open_graph_image']) ? $titles['open_graph_image'] : '';
        if (!empty($default_og)) {
            $recommendations[] = [
                'key'    => 'default_og_image',
                'status' => 'optimal',
                'title'  => esc_html__('Default OpenGraph Fallback Image', 'woo-get-data-for-ai'),
                'detail' => esc_html__('A default social sharing image is configured for pages and products without featured images.', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'default_og_image',
                'status' => 'warning',
                'title'  => esc_html__('Missing Default OpenGraph Image', 'woo-get-data-for-ai'),
                'detail' => esc_html__('No default social fallback image is configured in Rank Math Titles & Meta > Global Meta. Social shares of content lacking featured images will display without visuals.', 'woo-get-data-for-ai'),
            ];
        }

        // 6. Schema Knowledge Graph
        $kg_type = !empty($titles['knowledgegraph_type']) ? $titles['knowledgegraph_type'] : 'company';
        $kg_logo = !empty($titles['knowledgegraph_logo']) ? $titles['knowledgegraph_logo'] : '';
        if (!empty($kg_logo)) {
            $recommendations[] = [
                'key'    => 'schema_organization',
                'status' => 'optimal',
                'title'  => esc_html__('Schema Knowledge Graph & Logo', 'woo-get-data-for-ai'),
                'detail' => sprintf(esc_html__('Knowledge Graph entity is configured as "%s" with an official logo.', 'woo-get-data-for-ai'), $kg_type),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'schema_organization',
                'status' => 'warning',
                'title'  => esc_html__('Missing Knowledge Graph Logo', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Organization/Person logo is missing in Rank Math Titles & Meta > Local SEO. Google uses this logo in Knowledge Panels.', 'woo-get-data-for-ai'),
            ];
        }

        // 7. Breadcrumbs
        $breadcrumbs = isset($general['breadcrumbs']) ? $general['breadcrumbs'] : 'off';
        if ($breadcrumbs === 'on') {
            $recommendations[] = [
                'key'    => 'breadcrumbs',
                'status' => 'optimal',
                'title'  => esc_html__('Schema Breadcrumbs Active', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Rank Math Breadcrumbs function is enabled.', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'breadcrumbs',
                'status' => 'notice',
                'title'  => esc_html__('Breadcrumbs Disabled in Rank Math', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Breadcrumbs are turned off in Rank Math. Ensure your theme provides native breadcrumbs with Schema.org markup.', 'woo-get-data-for-ai'),
            ];
        }

        // 8. Key Modules status
        $key_modules = [
            'image-seo'    => ['title' => 'Image SEO', 'essential' => false],
            'rich-snippet' => ['title' => 'Schema (Structured Data)', 'essential' => true],
            '404-monitor'  => ['title' => '404 Monitor', 'essential' => true],
            'redirections' => ['title' => 'Redirections', 'essential' => true],
            'sitemap'      => ['title' => 'XML Sitemap', 'essential' => true],
            'woocommerce'  => ['title' => 'WooCommerce SEO', 'essential' => class_exists('WooCommerce')],
        ];
        foreach ($key_modules as $mod_key => $mod_info) {
            $is_active = in_array($mod_key, $modules, true);
            if ($is_active) {
                $recommendations[] = [
                    'key'    => "module_{$mod_key}",
                    'status' => 'optimal',
                    'title'  => sprintf(esc_html__('Module Active: %s', 'woo-get-data-for-ai'), $mod_info['title']),
                    'detail' => sprintf(esc_html__('Rank Math %s module is enabled and operational.', 'woo-get-data-for-ai'), $mod_info['title']),
                ];
            } elseif ($mod_info['essential']) {
                $recommendations[] = [
                    'key'    => "module_{$mod_key}",
                    'status' => 'warning',
                    'title'  => sprintf(esc_html__('Recommended Module Inactive: %s', 'woo-get-data-for-ai'), $mod_info['title']),
                    'detail' => sprintf(esc_html__('Rank Math %s module is disabled in active modules.', 'woo-get-data-for-ai'), $mod_info['title']),
                ];
            }
        }

        return [
            'settings'        => [
                'strip_category_base'      => $strip_cat === 'on',
                'attachment_redirect_urls' => $attach_redirect === 'on',
                'breadcrumbs_enabled'      => $breadcrumbs === 'on',
                'knowledgegraph_type'      => $kg_type,
                'knowledgegraph_logo'      => $kg_logo,
                'default_og_image'         => $default_og,
                'sitemap_items_per_page'   => isset($sitemap['items_per_page']) ? (int) $sitemap['items_per_page'] : 200,
                'sitemap_include_images'   => (isset($sitemap['include_images']) ? $sitemap['include_images'] : 'on') === 'on',
                'active_modules'           => array_values($modules),
            ],
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Extract and audit Yoast SEO settings.
     *
     * @return array
     */
    private function get_yoast_settings_audit() {
        $wpseo  = get_option('wpseo', []);
        $titles = get_option('wpseo_titles', []);
        $social = get_option('wpseo_social', []);

        $recommendations = [];

        // 1. Tag archives indexation (post_tag)
        $tag_noindex = !empty($titles['noindex-tax-post_tag']);
        if ($tag_noindex) {
            $recommendations[] = [
                'key'    => 'tags_indexation',
                'status' => 'optimal',
                'title'  => esc_html__('Tag Archives Set to Noindex', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Post tags are set to noindex in Yoast, preventing duplicate taxonomy listings.', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'tags_indexation',
                'status' => 'notice',
                'title'  => esc_html__('Tag Archives Are Indexed', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Post tags are set to index in Yoast. Setting tags to noindex avoids duplicate content unless tag pages have unique copy.', 'woo-get-data-for-ai'),
            ];
        }

        // 2. Product tags indexation (WooCommerce)
        if (class_exists('WooCommerce')) {
            $prod_tag_noindex = !empty($titles['noindex-tax-product_tag']);
            if ($prod_tag_noindex) {
                $recommendations[] = [
                    'key'    => 'product_tags_indexation',
                    'status' => 'optimal',
                    'title'  => esc_html__('Product Tag Archives Set to Noindex', 'woo-get-data-for-ai'),
                    'detail' => esc_html__('WooCommerce product tags are set to noindex in Yoast.', 'woo-get-data-for-ai'),
                ];
            } else {
                $recommendations[] = [
                    'key'    => 'product_tags_indexation',
                    'status' => 'notice',
                    'title'  => esc_html__('Product Tag Archives Are Indexed', 'woo-get-data-for-ai'),
                    'detail' => esc_html__('Product tags are set to index in Yoast. Consider noindex if tags lack custom content.', 'woo-get-data-for-ai'),
                ];
            }
        }

        // 3. Strip category base
        $strip_cat = !empty($titles['stripcategorybase']);
        if ($strip_cat) {
            $recommendations[] = [
                'key'    => 'strip_category_base',
                'status' => 'optimal',
                'title'  => esc_html__('Strip Category Base', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Category prefix (/category/) is stripped for cleaner URLs.', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'strip_category_base',
                'status' => 'notice',
                'title'  => esc_html__('Category Base Retained (/category/)', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Category prefix (/category/) is retained. Removing it can shorten permalinks.', 'woo-get-data-for-ai'),
            ];
        }

        // 4. Default OpenGraph Image
        $default_og = !empty($social['og_default_image']) ? $social['og_default_image'] : '';
        if (!empty($default_og)) {
            $recommendations[] = [
                'key'    => 'default_og_image',
                'status' => 'optimal',
                'title'  => esc_html__('Default OpenGraph Fallback Image', 'woo-get-data-for-ai'),
                'detail' => esc_html__('A default social sharing image is configured in Yoast Social settings.', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'default_og_image',
                'status' => 'warning',
                'title'  => esc_html__('Missing Default OpenGraph Image', 'woo-get-data-for-ai'),
                'detail' => esc_html__('No default social fallback image is configured in Yoast Settings > Social Sharing. Content shared without featured images will lack visuals.', 'woo-get-data-for-ai'),
            ];
        }

        // 5. Schema Knowledge Graph
        $company_or_person = !empty($titles['company_or_person']) ? $titles['company_or_person'] : 'company';
        $company_logo      = !empty($titles['company_logo']) ? $titles['company_logo'] : '';
        if (!empty($company_logo)) {
            $recommendations[] = [
                'key'    => 'schema_organization',
                'status' => 'optimal',
                'title'  => esc_html__('Schema Organization & Logo', 'woo-get-data-for-ai'),
                'detail' => sprintf(esc_html__('Yoast site representation is configured as "%s" with an official logo.', 'woo-get-data-for-ai'), $company_or_person),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'schema_organization',
                'status' => 'warning',
                'title'  => esc_html__('Missing Organization Logo', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Organization logo is missing in Yoast Site Representation settings.', 'woo-get-data-for-ai'),
            ];
        }

        // 6. Breadcrumbs
        $breadcrumbs = !empty($titles['breadcrumbs-enable']);
        if ($breadcrumbs) {
            $recommendations[] = [
                'key'    => 'breadcrumbs',
                'status' => 'optimal',
                'title'  => esc_html__('Schema Breadcrumbs Active', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Yoast Breadcrumbs are enabled.', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'breadcrumbs',
                'status' => 'notice',
                'title'  => esc_html__('Breadcrumbs Disabled in Yoast', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Breadcrumbs are turned off in Yoast settings.', 'woo-get-data-for-ai'),
            ];
        }

        // 7. Author & Date archives
        $author_disabled = !empty($titles['disable-author']) || !empty($titles['noindex-author-wpseo']);
        if ($author_disabled) {
            $recommendations[] = [
                'key'    => 'author_archives',
                'status' => 'optimal',
                'title'  => esc_html__('Author Archives Protected', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Author archives are disabled or noindexed in Yoast.', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'author_archives',
                'status' => 'notice',
                'title'  => esc_html__('Author Archives Are Active', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Author archives are enabled and indexable. On single-author sites, disabling them prevents duplicate homepage content.', 'woo-get-data-for-ai'),
            ];
        }

        $date_disabled = !empty($titles['disable-date']) || !empty($titles['noindex-date-wpseo']);
        if ($date_disabled) {
            $recommendations[] = [
                'key'    => 'date_archives',
                'status' => 'optimal',
                'title'  => esc_html__('Date Archives Disabled', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Date archives are disabled or noindexed in Yoast.', 'woo-get-data-for-ai'),
            ];
        }

        return [
            'settings'        => [
                'strip_category_base' => $strip_cat,
                'breadcrumbs_enabled' => $breadcrumbs,
                'company_or_person'   => $company_or_person,
                'company_logo'        => $company_logo,
                'default_og_image'    => $default_og,
                'xml_sitemap_enabled' => !empty($wpseo['enable_xml_sitemap']),
                'tracking_enabled'    => !empty($wpseo['tracking']),
            ],
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Extract and audit The SEO Framework settings.
     *
     * @return array
     */
    private function get_tsf_settings_audit() {
        $tsf = get_option('autodescription-site-settings', []);
        $recommendations = [];

        // 1. Tag archives indexation
        $tax_settings = isset($tsf['taxonomies']) && is_array($tsf['taxonomies']) ? $tsf['taxonomies'] : [];
        $tag_noindex  = !empty($tax_settings['post_tag']['noindex']);
        if ($tag_noindex) {
            $recommendations[] = [
                'key'    => 'tags_indexation',
                'status' => 'optimal',
                'title'  => esc_html__('Tag Archives Set to Noindex', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Post tags are set to noindex in The SEO Framework.', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'tags_indexation',
                'status' => 'notice',
                'title'  => esc_html__('Tag Archives Are Indexed', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Post tags are set to index. Consider noindex to consolidate crawl budget.', 'woo-get-data-for-ai'),
            ];
        }

        // 2. Default Social Fallback Image
        $social_img = !empty($tsf['social_image_url']) ? $tsf['social_image_url'] : '';
        if (!empty($social_img)) {
            $recommendations[] = [
                'key'    => 'default_og_image',
                'status' => 'optimal',
                'title'  => esc_html__('Default Social Fallback Image', 'woo-get-data-for-ai'),
                'detail' => esc_html__('A default social image is set in The SEO Framework.', 'woo-get-data-for-ai'),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'default_og_image',
                'status' => 'warning',
                'title'  => esc_html__('Missing Default Social Image', 'woo-get-data-for-ai'),
                'detail' => esc_html__('No default social sharing image is configured in The SEO Framework Social settings.', 'woo-get-data-for-ai'),
            ];
        }

        // 3. Schema Knowledge Graph
        $kg_type = !empty($tsf['knowledge_type']) ? $tsf['knowledge_type'] : 'organization';
        $kg_logo = !empty($tsf['knowledge_logo']) ? $tsf['knowledge_logo'] : '';
        if (!empty($kg_logo)) {
            $recommendations[] = [
                'key'    => 'schema_organization',
                'status' => 'optimal',
                'title'  => esc_html__('Schema Knowledge Graph & Logo', 'woo-get-data-for-ai'),
                'detail' => sprintf(esc_html__('Knowledge Graph is set to "%s" with an official logo.', 'woo-get-data-for-ai'), $kg_type),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'schema_organization',
                'status' => 'warning',
                'title'  => esc_html__('Missing Organization Logo', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Organization logo is missing in The SEO Framework Schema settings.', 'woo-get-data-for-ai'),
            ];
        }

        // 4. Author & Date archives
        $author_noindex = !empty($tsf['author_noindex']);
        if ($author_noindex) {
            $recommendations[] = [
                'key'    => 'author_archives',
                'status' => 'optimal',
                'title'  => esc_html__('Author Archives Protected', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Author archives are set to noindex in The SEO Framework.', 'woo-get-data-for-ai'),
            ];
        }

        return [
            'settings'        => [
                'title_separator'    => isset($tsf['title_separator']) ? $tsf['title_separator'] : '-',
                'knowledge_type'     => $kg_type,
                'knowledge_logo'     => $kg_logo,
                'default_social_img' => $social_img,
                'author_noindex'     => $author_noindex,
                'date_noindex'       => !empty($tsf['date_noindex']),
            ],
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Extract and audit Redirection plugin options.
     *
     * @return array|null
     */
    private function get_redirection_settings_audit() {
        $options = get_option('redirection_options', []);
        if (empty($options) || !is_array($options)) {
            return null;
        }

        $recommendations = [];

        // 1. URL change monitoring
        $monitor = isset($options['monitor_types']) && is_array($options['monitor_types']) ? $options['monitor_types'] : [];
        if (!empty($monitor)) {
            $recommendations[] = [
                'key'    => 'permalink_monitor',
                'status' => 'optimal',
                'title'  => esc_html__('URL Permalink Change Monitoring Active', 'woo-get-data-for-ai'),
                'detail' => sprintf(esc_html__('Automatic 301 redirects are created when URLs change for: %s.', 'woo-get-data-for-ai'), implode(', ', $monitor)),
            ];
        } else {
            $recommendations[] = [
                'key'    => 'permalink_monitor',
                'status' => 'notice',
                'title'  => esc_html__('Permalink Change Monitoring Inactive', 'woo-get-data-for-ai'),
                'detail' => esc_html__('Automatic 301 redirection on slug/permalink changes is turned off in Redirection plugin.', 'woo-get-data-for-ai'),
            ];
        }

        // 2. 404 Log retention
        $has_404 = !empty($options['support_404']);
        $exp_404 = isset($options['expire_404']) ? (int) $options['expire_404'] : 0;
        if ($has_404) {
            if ($exp_404 > 0 && $exp_404 <= 30) {
                $recommendations[] = [
                    'key'    => '404_retention',
                    'status' => 'optimal',
                    'title'  => esc_html__('404 Log Retention Healthy', 'woo-get-data-for-ai'),
                    'detail' => sprintf(esc_html__('404 logs are automatically purged after %d days (protects database size).', 'woo-get-data-for-ai'), $exp_404),
                ];
            } elseif ($exp_404 > 60 || $exp_404 === 0) {
                $recommendations[] = [
                    'key'    => '404_retention',
                    'status' => 'warning',
                    'title'  => esc_html__('404 Log Retention High or Unlimited', 'woo-get-data-for-ai'),
                    'detail' => sprintf(esc_html__('404 logs retention is set to %d days. High retention can cause database bloat in the wp_redirection_404 table.', 'woo-get-data-for-ai'), $exp_404),
                ];
            }
        }

        return [
            'settings'        => [
                'monitor_types'    => $monitor,
                'support_404'      => $has_404,
                'expire_404'       => $exp_404,
                'expire_redirect'  => isset($options['expire_redirect']) ? (int) $options['expire_redirect'] : 0,
            ],
            'recommendations' => $recommendations,
        ];
    }
}

