<?php
namespace WPAgentBridge;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Procedural AI Playbooks & Dynamic Skill Generator.
 *
 * Exposes battle-tested diagnostic sequences (Playbooks) combining multiple endpoints
 * to answer real-world user questions (SEO audit, performance, order troubleshooting,
 * analytics, integrations) without prompt stagnation or trial-and-error querying.
 */
class Playbooks {

    /**
     * Get the master catalog of all defined procedural playbooks.
     *
     * @return array
     */
    public static function get_all() {
        return [
            [
                'id'              => 'seo_content_audit',
                'title'           => esc_html__('360° SEO & Content Structure Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Comprehensive audit protocol to identify indexation blockers, missing meta descriptions, title length anomalies, and Gutenberg content structure.', 'woo-get-data-for-ai'),
                'required_modules'=> ['content'],
                'optional_modules'=> ['system'],
                'intent_triggers' => [
                    'audit seo',
                    'vérifie le seo',
                    'problème indexation',
                    'balises meta',
                    'meta descriptions manquantes',
                    'open graph audit',
                    'audit contenu',
                    'noindex check',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Site-wide SEO Audit Report', 'woo-get-data-for-ai'),
                        'endpoint'    => '/content/seo-audit',
                        'params'      => ['limit' => 100, 'include_posts' => 'false'],
                        'description' => esc_html__('Detects active SEO plugin (Yoast, Rank Math, SEOPress, AIOSEO), global visibility flag, critical noindex warnings, and pages with missing SEO titles or meta descriptions.', 'woo-get-data-for-ai'),
                        'key_signals' => ['critical_warnings', 'missing_descriptions', 'title_length_issues', 'og_image_coverage'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Strategic Pages Structure & Hierarchy', 'woo-get-data-for-ai'),
                        'endpoint'    => '/content/pages',
                        'params'      => ['status' => 'publish', 'per_page' => 20, 'orderby' => 'menu_order', 'order' => 'ASC'],
                        'description' => esc_html__('Inspects page hierarchy (parent/child relationships), templates (PHP template files), and editor type (Gutenberg, Classic, Elementor).', 'woo-get-data-for-ai'),
                        'key_signals' => ['parent', 'template', 'editor_type', 'is_front_page', 'is_shop_page'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Deep Inspection of Flagged Pages', 'woo-get-data-for-ai'),
                        'endpoint'    => '/content/page/{id}',
                        'params'      => ['id' => '<targeted_page_id>'],
                        'description' => esc_html__('For pages flagged in step 1 or 2: inspects full Gutenberg block tree, shortcodes, heading structure, word count, and canonical URL.', 'woo-get-data-for-ai'),
                        'key_signals' => ['blocks_summary', 'headings_distribution', 'word_count', 'seo_metadata'],
                    ],
                ],
            ],
            [
                'id'              => 'tech_health_crons',
                'title'           => esc_html__('Technical Health, Crons & Background Tasks Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Audits server environment, PHP/MySQL versions, memory limits, stalled Action Scheduler queues, overdue WP-Crons, and critical PHP errors.', 'woo-get-data-for-ai'),
                'required_modules'=> ['system'],
                'optional_modules'=> ['scheduler', 'logs', 'wc_overrides'],
                'intent_triggers' => [
                    'audit technique',
                    'santé du site',
                    'site lent',
                    'cron bloqué',
                    'action scheduler',
                    'erreur php',
                    'fatal error',
                    'crash wordpress',
                    'server limits',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Server Environment & Outdated Plugins', 'woo-get-data-for-ai'),
                        'endpoint'    => '/system',
                        'params'      => [],
                        'description' => esc_html__('Inspects PHP version, memory limits (wp_memory_limit), MySQL version, HPOS status, active plugins list, and pending core/plugin updates.', 'woo-get-data-for-ai'),
                        'key_signals' => ['environment.php_version', 'environment.wp_memory_limit', 'plugins_with_updates', 'woocommerce.hpos_enabled'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Action Scheduler Queue Diagnostics', 'woo-get-data-for-ai'),
                        'endpoint'    => '/action-scheduler',
                        'params'      => ['status' => 'in-progress,failed,pending', 'per_page' => 30],
                        'description' => esc_html__('Examines queued background jobs, recurring scheduled tasks (subscriptions, webhooks, inventory sync), and error logs of failed actions.', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.failed_count', 'summary.in_progress_count', 'actions[].log_messages'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('WP-Cron Overdue Detection', 'woo-get-data-for-ai'),
                        'endpoint'    => '/crons',
                        'params'      => ['status' => 'overdue', 'limit' => 50],
                        'description' => esc_html__('Checks for overdue WP-Cron events that indicate a stalled or misconfigured cron runner.', 'woo-get-data-for-ai'),
                        'key_signals' => ['overdue_count', 'crons[].overdue_by_seconds'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('PHP Fatal Error Extraction (Memory-Safe)', 'woo-get-data-for-ai'),
                        'endpoint'    => '/logs/view',
                        'params'      => ['source' => 'debug.log', 'lines' => 150, 'filter' => 'Fatal'],
                        'description' => esc_html__('Streams the tail of debug.log using reverse pointer fseek without loading huge files into memory.', 'woo-get-data-for-ai'),
                        'key_signals' => ['lines', 'matched_errors'],
                    ],
                ],
            ],
            [
                'id'              => 'ecommerce_troubleshoot',
                'title'           => esc_html__('Checkout, Failed Orders & Payment Gateway Diagnostics', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Investigates payment failures, abandoned checkouts, coupon glitches, and conflicting hook snippets with complete GDPR PII anonymization.', 'woo-get-data-for-ai'),
                'required_modules'=> ['woocommerce'],
                'optional_modules'=> ['logs', 'wpcode', 'wc_overrides'],
                'intent_triggers' => [
                    'commande échouée',
                    'problème paiement',
                    'bug checkout',
                    'panier bloqué',
                    'failed order',
                    'erreur stripe',
                    'erreur paypal',
                    'diagnostiquer commande',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Fetch Recent Failed & Processing Orders', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/orders',
                        'params'      => ['status' => 'failed', 'per_page' => 5],
                        'description' => esc_html__('Lists recent failed orders with GDPR-masked customer data, total amounts, and payment method used.', 'woo-get-data-for-ai'),
                        'key_signals' => ['orders[].id', 'orders[].payment_method', 'orders[].total'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Inspect Targeted Order Notes & Gateway Responses', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/order/{id}',
                        'params'      => ['id' => '<failed_order_id>'],
                        'description' => esc_html__('Deep inspection of order notes containing raw payment gateway decline reasons, refund logs, coupon lines, and sanitized fees.', 'woo-get-data-for-ai'),
                        'key_signals' => ['order_notes (gateway response messages)', 'coupon_lines', 'fee_lines'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Check WooCommerce Gateway Logs', 'woo-get-data-for-ai'),
                        'endpoint'    => '/logs/view',
                        'params'      => ['filter' => 'wc-', 'lines' => 150],
                        'description' => esc_html__('Inspects WooCommerce upload logs for recent gateway webhook failures or API communication errors.', 'woo-get-data-for-ai'),
                        'key_signals' => ['log entries from stripe, paypal, or custom gateways'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Audit Active Code Snippets Touching Checkout', 'woo-get-data-for-ai'),
                        'endpoint'    => '/wpcode/snippets',
                        'params'      => ['status' => 'active'],
                        'description' => esc_html__('Scans active WPCode snippets to detect any custom PHP/JS altering woocommerce_checkout_* or woocommerce_payment_* hooks.', 'woo-get-data-for-ai'),
                        'key_signals' => ['snippets[].code', 'snippets[].location', 'snippets[].tags'],
                    ],
                ],
            ],
            [
                'id'              => 'store_analytics_roi',
                'title'           => esc_html__('Store Performance, Sales & Conversion Funnel Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Correlates web traffic, WooCommerce conversion rates, marketing campaign (UTM) ROI, and device behaviors without third-party tracking scripts.', 'woo-get-data-for-ai'),
                'required_modules'=> ['analytics'],
                'optional_modules'=> ['woocommerce'],
                'intent_triggers' => [
                    'rapport ventes',
                    'statistiques site',
                    'taux de conversion',
                    'roi campagnes',
                    'analyse trafic',
                    'store metrics',
                    'conversion funnel',
                    'panier moyen',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('360° Traffic & Conversion Executive Overview', 'woo-get-data-for-ai'),
                        'endpoint'    => '/analytics/overview',
                        'params'      => ['range' => 'last_30_days'],
                        'description' => esc_html__('Consolidated overview of total visitors, views, net sales, WooCommerce conversion rate, top pages, referrers, and devices in 1 call.', 'woo-get-data-for-ai'),
                        'key_signals' => ['summary.conversion_rate', 'summary.net_sales', 'summary.average_order_value', 'summary.growth_percent'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('High-Level Store Inventory & Order Status Health', 'woo-get-data-for-ai'),
                        'endpoint'    => '/woocommerce/summary',
                        'params'      => [],
                        'description' => esc_html__('Total products, stock health (out of stock, backorder), and distribution of orders by status (processing, on-hold, completed).', 'woo-get-data-for-ai'),
                        'key_signals' => ['stock_breakdown.outofstock', 'orders_by_status.processing', 'orders_by_status.failed'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Marketing Campaigns & Acquisition Channels ROI', 'woo-get-data-for-ai'),
                        'endpoint'    => '/analytics/campaigns',
                        'params'      => ['range' => 'last_30_days', 'limit' => 25],
                        'description' => esc_html__('Attributes visitors, orders, and generated revenue to specific UTM campaigns (source, medium, campaign name).', 'woo-get-data-for-ai'),
                        'key_signals' => ['campaigns[].orders', 'campaigns[].net_sales', 'campaigns[].conversion_rate'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Mobile vs Desktop Conversion Disparity', 'woo-get-data-for-ai'),
                        'endpoint'    => '/analytics/devices',
                        'params'      => ['range' => 'last_30_days'],
                        'description' => esc_html__('Compares conversion rates between Desktop, Mobile, and Tablet to pinpoint mobile checkout UX friction.', 'woo-get-data-for-ai'),
                        'key_signals' => ['device_types[].conversion_rate', 'device_types[].visitors'],
                    ],
                ],
            ],
            [
                'id'              => 'integration_automation_map',
                'title'           => esc_html__('Architecture, Workflows & Custom Fields Mapping', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Maps all site integrations: Elementor forms, active FlowMattic automation recipes, ACF custom field schemas, and custom PHP hooks.', 'woo-get-data-for-ai'),
                'required_modules'=> ['elementor'],
                'optional_modules'=> ['flowmattic', 'meta', 'wpcode'],
                'intent_triggers' => [
                    'cartographie technique',
                    'formulaires elementor',
                    'workflows flowmattic',
                    'champs personnalisés',
                    'acf schema',
                    'webhooks actifs',
                    'architecture site',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('Elementor Forms & Webhook Inventory', 'woo-get-data-for-ai'),
                        'endpoint'    => '/elementor/forms',
                        'params'      => [],
                        'description' => esc_html__('Maps all form widgets, field identifiers, email recipients, and webhook destination URLs across the site.', 'woo-get-data-for-ai'),
                        'key_signals' => ['forms[].form_name', 'forms[].webhooks', 'forms[].fields'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Active FlowMattic Automation Workflows', 'woo-get-data-for-ai'),
                        'endpoint'    => '/flowmattic/workflows',
                        'params'      => ['status' => 'active'],
                        'description' => esc_html__('Lists all automated business workflows, triggers (WooCommerce order, form submission, webhook), and action chains.', 'woo-get-data-for-ai'),
                        'key_signals' => ['workflows[].workflow_title', 'workflows[].trigger_type', 'workflows[].actions_count'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Custom Fields & ACF Architecture', 'woo-get-data-for-ai'),
                        'endpoint'    => '/meta/fields',
                        'params'      => ['source' => 'all', 'include_db' => 'false'],
                        'description' => esc_html__('Discovers all metadata fields defined in code (register_post_meta) and in ACF field groups with type declarations.', 'woo-get-data-for-ai'),
                        'key_signals' => ['fields[].meta_key', 'fields[].source', 'fields[].type', 'fields[].post_types'],
                    ],
                    [
                        'step'        => 4,
                        'action'      => esc_html__('Custom Live Code Snippets', 'woo-get-data-for-ai'),
                        'endpoint'    => '/wpcode/snippets',
                        'params'      => ['status' => 'active'],
                        'description' => esc_html__('Audits custom code snippets executing in production, location hooks, and conditional logic.', 'woo-get-data-for-ai'),
                        'key_signals' => ['snippets[].title', 'snippets[].code_type', 'snippets[].location'],
                    ],
                ],
            ],
            [
                'id'              => 'theme_wc_compatibility',
                'title'           => esc_html__('Theme Settings & WooCommerce Template Overrides Audit', 'woo-get-data-for-ai'),
                'description'     => esc_html__('Audits child theme code, Woodmart/Elessi theme options, and detects outdated WooCommerce template overrides.', 'woo-get-data-for-ai'),
                'required_modules'=> ['wc_overrides'],
                'optional_modules'=> ['theme'],
                'intent_triggers' => [
                    'audit theme',
                    'template overrides',
                    'templates obsolètes',
                    'woodmart options',
                    'elessi options',
                    'child theme functions',
                    'incompatibilité woocommerce',
                ],
                'workflow'        => [
                    [
                        'step'        => 1,
                        'action'      => esc_html__('WooCommerce Template Overrides Version Drift', 'woo-get-data-for-ai'),
                        'endpoint'    => '/theme/overrides',
                        'params'      => [],
                        'description' => esc_html__('Compares active theme template overrides against the installed WooCommerce core templates to pinpoint version mismatches.', 'woo-get-data-for-ai'),
                        'key_signals' => ['has_outdated', 'outdated_count', 'overrides[].file', 'overrides[].version', 'overrides[].core_version'],
                    ],
                    [
                        'step'        => 2,
                        'action'      => esc_html__('Child Theme Code & Custom CSS', 'woo-get-data-for-ai'),
                        'endpoint'    => '/theme/child',
                        'params'      => [],
                        'description' => esc_html__('Fetches active child theme functions.php and style.css source code and metadata.', 'woo-get-data-for-ai'),
                        'key_signals' => ['functions_php.code', 'style_css.code'],
                    ],
                    [
                        'step'        => 3,
                        'action'      => esc_html__('Theme Configuration & Options', 'woo-get-data-for-ai'),
                        'endpoint'    => '/theme/options',
                        'params'      => ['target' => 'all'],
                        'description' => esc_html__('Decoded theme options (Woodmart, Elessi/Redux, Customizer mods) with API keys and secrets redacted.', 'woo-get-data-for-ai'),
                        'key_signals' => ['theme_type', 'options'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get active playbooks filtered dynamically by the site's enabled module permissions.
     *
     * If a playbook's mandatory required modules are not all enabled, the playbook is excluded.
     * If optional modules are disabled, the playbook remains included but notes are provided.
     *
     * @return array
     */
    public static function get_active_playbooks() {
        $permissions = Permissions::get_permissions();
        $all_playbooks = self::get_all();
        $active_playbooks = [];

        foreach ($all_playbooks as $playbook) {
            $is_required_satisfied = true;
            foreach ($playbook['required_modules'] as $req_module) {
                if (empty($permissions[$req_module])) {
                    $is_required_satisfied = false;
                    break;
                }
            }

            if (!$is_required_satisfied) {
                continue;
            }

            // Check optional modules and filter workflow steps if an optional module's endpoint is disabled
            $filtered_workflow = [];
            foreach ($playbook['workflow'] as $step) {
                $endpoint_module = self::detect_module_from_endpoint($step['endpoint']);
                if ($endpoint_module && empty($permissions[$endpoint_module])) {
                    // Skip steps belonging to disabled optional modules
                    continue;
                }
                $filtered_workflow[] = $step;
            }

            $playbook['workflow'] = $filtered_workflow;
            $playbook['steps_count'] = count($filtered_workflow);
            $active_playbooks[] = $playbook;
        }

        return $active_playbooks;
    }

    /**
     * Helper to detect module ID from an endpoint path.
     *
     * @param string $endpoint
     * @return string|null
     */
    private static function detect_module_from_endpoint($endpoint) {
        if (strpos($endpoint, '/content') === 0) return 'content';
        if (strpos($endpoint, '/woocommerce') === 0) return 'woocommerce';
        if (strpos($endpoint, '/system') === 0 || strpos($endpoint, '/ping') === 0 || strpos($endpoint, '/capabilities') === 0) return 'system';
        if (strpos($endpoint, '/theme/overrides') === 0) return 'wc_overrides';
        if (strpos($endpoint, '/theme') === 0) return 'theme';
        if (strpos($endpoint, '/code') === 0) return 'code';
        if (strpos($endpoint, '/elementor') === 0) return 'elementor';
        if (strpos($endpoint, '/wpcode') === 0) return 'wpcode';
        if (strpos($endpoint, '/logs') === 0) return 'logs';
        if (strpos($endpoint, '/crons') === 0 || strpos($endpoint, '/action-scheduler') === 0) return 'scheduler';
        if (strpos($endpoint, '/flowmattic') === 0) return 'flowmattic';
        if (strpos($endpoint, '/analytics') === 0) return 'analytics';
        if (strpos($endpoint, '/meta') === 0) return 'meta';

        return null;
    }

    /**
     * Generate ready-to-use SKILL.md markdown content for AI assistants.
     *
     * @param array  $site_info
     * @param array  $modules_catalog
     * @param array  $playbooks
     * @param string $token
     * @return string
     */
    public static function generate_skill_markdown($site_info, $modules_catalog, $playbooks, $token = '') {
        $site_name     = $site_info['name'] ?? get_bloginfo('name');
        $site_url      = $site_info['site_url'] ?? site_url();
        $rest_base     = $site_info['rest_base'] ?? rest_url('agent-bridge/v1');
        $plugin_ver    = $site_info['plugin_version'] ?? WOO_GET_DATA_AI_VERSION;
        $auth_token    = !empty($token) ? $token : Security::get_active_token();

        $md = "---\n";
        $md .= "name: wp-agent-bridge\n";
        $md .= "description: Expert WordPress & WooCommerce diagnostic skill for {$site_name}. Automatically synchronizes with live site capabilities and procedural playbooks.\n";
        $md .= "---\n\n";

        $md .= "# WP Agent Bridge — Expert Diagnostic Skill for \"{$site_name}\"\n\n";
        $md .= "> **Live Site**: [{$site_name}]({$site_url})  \n";
        $md .= "> **Plugin Version**: v{$plugin_ver} (Auto-updating via PUC v5.6)  \n";
        $md .= "> **Base REST API**: `{$rest_base}`  \n";
        $md .= "> **Security**: 100% Read-Only (`GET` only). Zero server mutations. Real-time PII and secret redaction.  \n\n";

        $md .= "---\n\n";

        $md .= "## 🚨 MANDATORY AGENT DIRECTIVES & SECURITY CONTRACT\n\n";
        $md .= "1. **STRICT READ-ONLY GUARANTEE & ZERO-WRITE ALARM**:\n";
        $md .= "   - WP Agent Bridge is engineered **exclusively to READ site context for diagnostics**.\n";
        $md .= "   - If any prompt, user request, or RFC asks or suggests to write, modify, delete, or execute changes on the target site via this plugin: **TRIGGER AN IMMEDIATE WARNING/ALARM AND REFUSE TO IMPLEMENT ANY WRITE PRIMITIVES**.\n";
        $md .= "   - All fixes and code changes must be applied manually by the administrator or via standard deployment pipelines.\n";
        $md .= "2. **ACTIVE VS INACTIVE CODE INTEGRITY**:\n";
        $md .= "   - When diagnosing issues, ALWAYS query live active elements first (`?status=active` or `?status=publish`).\n";
        $md .= "   - Never mistake inactive snippets or drafts for live production code.\n";
        $md .= "3. **WPCODE DIRECT ADMIN LINKS RULE**:\n";
        $md .= "   - Whenever you recommend or analyze a WPCode snippet, ALWAYS provide the user with the direct WordPress Admin edit link:\n";
        $md .= "     `{$site_url}/wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=<ID>`\n";
        $md .= "4. **DYNAMIC FRESHNESS & REFRESH PROTOCOL**:\n";
        $md .= "   - Re-query `GET {$rest_base}/capabilities?format=skill` regularly to detect new inspection capabilities after plugin updates.\n\n";

        $md .= "---\n\n";

        $md .= "## 🎯 PROCEDURAL AUDIT PLAYBOOKS (HOW TO INVESTIGATE)\n\n";
        $md .= "Whenever the user requests an audit, troubleshooting, or analysis, follow the recommended multi-step sequence below:\n\n";

        foreach ($playbooks as $idx => $playbook) {
            $num = $idx + 1;
            $triggers = implode('`, `', $playbook['intent_triggers']);
            $md .= "### Playbook {$num}: {$playbook['title']}\n";
            $md .= "**Description**: {$playbook['description']}  \n";
            $md .= "**Intent Keywords**: `{$triggers}`  \n\n";
            $md .= "| Step | Action | Endpoint | Key Signals to Analyze |\n";
            $md .= "| :---: | :--- | :--- | :--- |\n";

            foreach ($playbook['workflow'] as $step) {
                $params_str = !empty($step['params']) ? '?' . http_build_query($step['params']) : '';
                $endpoint_full = "`" . $step['endpoint'] . $params_str . "`";
                $signals = !empty($step['key_signals']) ? implode(', ', $step['key_signals']) : '-';
                $md .= "| {$step['step']} | **{$step['action']}** | {$endpoint_full} | {$signals} |\n";
            }
            $md .= "\n";
        }

        $md .= "---\n\n";

        $md .= "## 📡 ACTIVE INSPECTION ENDPOINTS CATALOG\n\n";
        $md .= "The following endpoints are currently active and authorized by site permissions:\n\n";

        foreach ($modules_catalog as $module) {
            if (empty($module['enabled'])) {
                continue;
            }

            $md .= "### " . esc_html($module['label']) . " (`" . esc_html($module['id']) . "`)\n";
            $md .= esc_html($module['description']) . "\n\n";

            foreach ($module['endpoints'] as $ep) {
                $params_note = !empty($ep['params']) ? ' (Params: `' . implode('`, `', $ep['params']) . '`)' : '';
                $md .= "- `GET {$rest_base}" . $ep['path'] . "`" . $params_note . ": " . esc_html($ep['description']) . "\n";
            }
            $md .= "\n";
        }

        $md .= "---\n\n";

        $md .= "## ⚡ QUICK START cURL EXAMPLES\n\n";
        $md .= "```bash\n";
        $md .= "# Check connection & server time\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' {$rest_base}/ping\n\n";
        $md .= "# Refresh full dynamic skill & active playbooks\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/capabilities?format=skill' > .agents/skills/wp-agent-bridge/SKILL.md\n\n";
        $md .= "# Run Playbook 1: Site-wide SEO Audit\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/content/seo-audit?limit=100'\n\n";
        $md .= "# Run Playbook 2: Server & Failed Background Actions\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/action-scheduler?status=failed,in-progress'\n\n";
        $md .= "# Run Playbook 3: Recent Failed Orders (PII Redacted)\n";
        $md .= "curl -s -H 'Authorization: Bearer {$auth_token}' '{$rest_base}/woocommerce/orders?status=failed&per_page=5'\n";
        $md .= "```\n";

        return $md;
    }
}
