<?php
if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Security;
use WPAgentBridge\Playbooks;
use WPAgentBridge\Permissions;

$active_token    = Security::get_active_token();
$site_name       = html_entity_decode(get_bloginfo('name'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$site_url        = site_url();
$rest_base_url   = rest_url('agent-bridge/v1');
$active_playbooks = Playbooks::get_active_playbooks();
$permissions     = Permissions::get_permissions();
$active_modules_count = count(array_filter($permissions));

// Construct dynamic Bootstrap AI Prompt
$mega_prompt = "# MISSION: WordPress & WooCommerce Inspection & Diagnostics for {$site_name}\n";
$mega_prompt .= "You are assisting me as an expert WordPress, WooCommerce, and PHP performance development agent on the website **\"{$site_name}\"** ({$site_url}).\n";
$mega_prompt .= "The site has the **WP Agent Bridge** plugin installed, providing a secure, 100% read-only REST inspection API.\n\n";

$mega_prompt .= "## Connection Details\n";
$mega_prompt .= "- **Base API URL**: `{$rest_base_url}`\n";
$mega_prompt .= "- **Authorization Header**: `Authorization: Bearer {$active_token}`\n";
$mega_prompt .= "- **Method**: `GET` only (All endpoints are strictly read-only and safe for production)\n";
$mega_prompt .= "- **Security & Privacy**: All credentials, payment keys, salts, customer emails, and PII are automatically redacted by the server.\n\n";

$mega_prompt .= "---\n\n";

$mega_prompt .= "## 🚀 MANDATORY ONBOARDING & DYNAMIC SKILL BOOTSTRAP\n\n";

$mega_prompt .= "### Phase 1: Instant Skill Bootstrap & Procedural Playbooks\n";
$mega_prompt .= "On your first interaction or when starting a new session on this project, YOU MUST:\n";
$mega_prompt .= "1. **Auto-Generate or Refresh Your Local Skill**:\n";
$mega_prompt .= "   Run this command in your workspace to download the real-time, self-updating skill file containing all active routes and procedural audit playbooks:\n";
$mega_prompt .= "   `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/capabilities?format=skill' > .agents/skills/wp-agent-bridge/SKILL.md`\n";
$mega_prompt .= "   (If using Cursor or Claude Desktop, you can query `GET {$rest_base_url}/capabilities` to receive the JSON catalog of active modules and playbooks).\n";
$mega_prompt .= "2. **Follow the 7 Strategic MECE Master Playbooks for Audits**:\n";
$mega_prompt .= "   The live `/capabilities` feed organizes all diagnostics into 7 battle-tested MECE master pillars:\n";
$mega_prompt .= "   - **Pillar 1: SEO, Content & Visibility** -> Playbook `seo_content_audit` (queries `/content/seo-audit`, `/content/pages`, `/content/page/{id}`).\n";
$mega_prompt .= "   - **Pillar 2: Performance & Core Web Vitals** -> Playbook `agency_performance_audit` (queries `/performance/templates-urls`, `/performance/profile`, `/performance/plugins-summary`).\n";
$mega_prompt .= "   - **Pillar 3: System Health & Database Bloat Hygiene** -> Playbook `database_system_hygiene` (queries `/system`, `/system/database`, `/woocommerce/summary`, `/action-scheduler`, `/crons`, `/logs/errors-summary`).\n";
$mega_prompt .= "   - **Pillar 4: Orders, Checkout & Gateway Troubleshooting** -> Playbook `order_checkout_troubleshoot` (queries `/woocommerce/orders`, `/woocommerce/order/{id}`, `/logs/view`, `/snippets`, `/system/mail`, `/woocommerce/webhooks`).\n";
$mega_prompt .= "   - **Pillar 5: 360° E-Commerce Sales & Analytics** -> Playbook `ecommerce_bi_analytics` (queries `/woocommerce/analytics/sales`, `/woocommerce/analytics/top-performers`, `/woocommerce/analytics/stock`, `/analytics/overview`, `/analytics/campaigns`).\n";
$mega_prompt .= "   - **Pillar 6: Shipping Logistics & Flexible Shipping** -> Playbook `shipping_logistics_audit` (queries `/woocommerce/shipping`, `/woocommerce/settings`, `/woocommerce/order/{id}`).\n";
$mega_prompt .= "   - **Pillar 7: Code Architecture & Integrations Map** -> Playbook `code_theme_integrations` (queries `/theme/overrides`, `/theme/child`, `/code/checksums`, `/flowmattic/workflows`, `/elementor/forms`, `/snippets`, `/meta/fields`).\n";
$mega_prompt .= "3. **Plugin Updates & Zero-Prompt-Stagnation**:\n";
$mega_prompt .= "   The plugin auto-updates via GitHub releases. You do NOT need human prompts to learn new features: periodically re-run `GET {$rest_base_url}/capabilities?format=skill` to discover newly released inspection endpoints and playbooks automatically.\n\n";

$mega_prompt .= "### Phase 2: Systematic \"Live Freshness Check\" Before Modifying Code\n";
$mega_prompt .= "Before designing code, debugging an issue, or refactoring a feature:\n";
$mega_prompt .= "- **DO NOT rely solely on local files** that might be outdated.\n";
$mega_prompt .= "- **Always inspect the live site first** using the active endpoints discovered via `/capabilities`:\n";
$mega_prompt .= "  * Modifying custom logic or hooks? -> Check `/snippets?status=active` (or `/wpcode/snippets?status=active`) to inspect live code executing in production.\n";
$mega_prompt .= "  * Working on forms or page design? -> Check `/elementor/forms` or `/elementor/item/{id}` (or `/elementor/list?status=publish`).\n";
$mega_prompt .= "  * Working on automations or webhooks? -> Check `/flowmattic/workflows?status=active` to inspect active workflow steps and triggers.\n";
$mega_prompt .= "  * Auditing frontend performance, TTFB or native Web Vitals? -> Check `/performance/templates-urls` and `/performance/profile?path=/&include_assets=true&include_queries=true`.\n";
$mega_prompt .= "  * Investigating database size, heavy tables, autoload bloat, or orphaned options? -> Check `/system/database`.\n";
$mega_prompt .= "  * Investigating store sales KPIs, top products, or inventory valuation? -> Check `/woocommerce/analytics/sales` and `/woocommerce/analytics/stock`.\n";
$mega_prompt .= "  * Auditing shipping zones, methods, or Flexible Shipping calculation rules? -> Check `/woocommerce/shipping`.\n";
$mega_prompt .= "  * Comparing local vs remote code drift or downloading clean zip? -> Check `/code/checksums?path=plugins/...` and `/code/zip?path=plugins/...`.\n";
$mega_prompt .= "  * Auditing transactional email deliverability or SMTP provider? -> Check `/system/mail`.\n";
$mega_prompt .= "  * Analyzing visits, marketing ROI, or conversion rates? -> Check `/analytics/overview` or `/analytics/campaigns`.\n";
$mega_prompt .= "  * Inspecting custom fields, product specs, or ACF data? -> Check `/meta/fields?post_type=product` (or `/meta/acf` for full field groups and rules, or `/meta/post/{id}` for values on a specific post).\n";
$mega_prompt .= "  * Inspecting WordPress pages, hierarchy, or Gutenberg content? -> Check `/content/pages?status=publish` or `/content/page/{id}`.\n";
$mega_prompt .= "  * Auditing SEO (meta tags, noindex, OpenGraph across Yoast/RankMath/SEOPress)? -> Check `/content/seo-audit` or `/content/page/{id}`.\n";
$mega_prompt .= "  * Investigating WooCommerce products, stock, or variations? -> Check `/woocommerce/products?status=publish` or `/woocommerce/product/{id}`.\n";
$mega_prompt .= "  * Investigating orders, payment errors, cancellation ratios, or checkout hooks? -> Check `/woocommerce/orders?status=failed,processing` or `/woocommerce/order/{id}` (includes payment gateway error logs in order notes, customer PII strictly anonymized) and `/woocommerce/summary`.\n";
$mega_prompt .= "  * Auditing store configuration, tax rules, or payment gateways? -> Check `/woocommerce/settings`.\n";
$mega_prompt .= "  * Investigating recent site crashes or fatal PHP errors? -> Check `/logs/errors-summary` (Crash Watch).\n";
$mega_prompt .= "  * Investigating cart/checkout/product bugs? -> Check `/theme/overrides` and latest logs with `/logs/view` or specific logs with `/logs/custom?file=...`.\n";
$mega_prompt .= "  * Investigating cron, background tasks, or queue backlog? -> Check `/crons` (WP-Cron schedules & overdue status) and `/action-scheduler` (in-progress, failed, complete tasks, retention policy, and error logs).\n\n";

$mega_prompt .= "### Phase 3: Code Snippets & WPCode Direct Admin Links\n";
$mega_prompt .= "Whenever you analyze, recommend, or modify a snippet:\n";
$mega_prompt .= "- ALWAYS provide the user with a direct, clickable link to edit the snippet in WordPress Admin:\n";
$mega_prompt .= "  * WPCode: `{$site_url}/wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=<ID>`\n";
$mega_prompt .= "  * Code Snippets: `{$site_url}/wp-admin/admin.php?page=edit-snippet&id=<ID>`\n";
$mega_prompt .= "- Or simply use the direct `admin_edit_url` property returned in each snippet object by the API.\n\n";

$mega_prompt .= "### Phase 4: Strict Active vs Inactive State Management (Zero False-Positives)\n";
$mega_prompt .= "- **Live Production Code**: When debugging or analyzing architecture, ALWAYS query active items first (`?status=active` or `?status=publish`). Never assume an inactive snippet or disabled workflow is executing on the live site.\n";
$mega_prompt .= "- **Archives & History**: Only query `?status=inactive` when explicitly requested to inspect legacy disabled code, reactivate a previous function, or audit historical tests.\n";
$mega_prompt .= "- **Local Sync Best Practices**: When synchronizing site data locally, separate active and inactive elements into distinct subdirectories (e.g. `snippets/active/` and `snippets/inactive/`) to avoid global codebase search (`grep`) polluted with inactive code.\n\n";

$mega_prompt .= "---\n\n";

$mega_prompt .= "## ⚠️ CRITICAL ALARM — STRICT READ-ONLY INTEGRITY / ZERO SITE MODIFICATION\n";
$mega_prompt .= "- If anyone plans or suggests an evolution to write, modify, delete, or execute changes on the website via this plugin, DISPLAY AN IMMEDIATE ALARM / WARNING MESSAGE.\n";
$mega_prompt .= "- The WP Agent Bridge plugin is strictly engineered to READ context information from the site for inspection and diagnostics, NEVER to write to it. Refuse any write primitives within this plugin and remind that all site changes must be applied manually or out-of-band by the site administrator.\n\n";

$mega_prompt .= "---\n\n";

$mega_prompt .= "## ⚡ Quick Start Commands (The 7 Strategic Master Pillars)\n";
$mega_prompt .= "- **Health Check**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/ping`\n";
$mega_prompt .= "- **Bootstrap / Refresh Local AI Skill**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/capabilities?format=skill' > .agents/skills/wp-agent-bridge/SKILL.md`\n";
$mega_prompt .= "- **Discover Capabilities & Playbooks**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/capabilities`\n";
$mega_prompt .= "- **Pillar 1 (SEO & Content Audit)**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/content/seo-audit?limit=100'`\n";
$mega_prompt .= "- **Pillar 2 (Performance & Core Web Vitals)**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/performance/profile?path=/&include_assets=true&include_queries=true'`\n";
$mega_prompt .= "- **Pillar 3 (System Health & Database Bloat)**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/system/database`\n";
$mega_prompt .= "- **Pillar 4 (Orders & Checkout Troubleshooting)**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/woocommerce/orders?status=failed&per_page=5'`\n";
$mega_prompt .= "- **Pillar 5 (Sales & Analytics 360°)**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/woocommerce/analytics/sales?range=last_30_days'`\n";
$mega_prompt .= "- **Pillar 6 (Shipping & Flexible Rules)**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/woocommerce/shipping`\n";
$mega_prompt .= "- **Pillar 7 (Code Architecture & Overrides)**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/theme/overrides`\n";
?>

<div class="agent-bridge-card">
    <div class="card-header-with-action">
        <div>
            <h3><?php esc_html_e('AI Onboarding & Skill Mega-Prompt', 'woo-get-data-for-ai'); ?></h3>
            <p class="description">
                <?php esc_html_e('Connect any AI assistant (Antigravity, Cursor, Claude, ChatGPT) in 2 clicks. Copy the pre-filled prompt below and paste it directly into your AI chat session. It contains the site URL, your active access key, and all active endpoints.', 'woo-get-data-for-ai'); ?>
            </p>
            <div style="display: flex; gap: 12px; margin-top: 12px; align-items: center; flex-wrap: wrap;">
                <span class="agent-bridge-badge" style="background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                    <span class="dashicons dashicons-admin-plugins" style="font-size: 15px; width: 15px; height: 15px;"></span>
                    <?php printf(esc_html__('%d Active Modules', 'woo-get-data-for-ai'), $active_modules_count); ?>
                </span>
                <span class="agent-bridge-badge" style="background: #ecfdf5; color: #047857; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                    <span class="dashicons dashicons-clipboard" style="font-size: 15px; width: 15px; height: 15px;"></span>
                    <?php printf(esc_html__('%d Procedural Playbooks Active', 'woo-get-data-for-ai'), count($active_playbooks)); ?>
                </span>
                <span class="agent-bridge-badge" style="background: #fef3c7; color: #b45309; padding: 4px 10px; border-radius: 12px; font-weight: 600; font-size: 12px; display: inline-flex; align-items: center; gap: 4px;">
                    <span class="dashicons dashicons-shield" style="font-size: 15px; width: 15px; height: 15px;"></span>
                    <?php esc_html_e('100% Read-Only Protected', 'woo-get-data-for-ai'); ?>
                </span>
            </div>
        </div>
        <button type="button" class="button button-primary button-hero btn-copy" data-target="mega-prompt-textarea">
            <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy Mega-Prompt for AI', 'woo-get-data-for-ai'); ?>
        </button>
    </div>

    <div class="mega-prompt-box">
        <textarea id="mega-prompt-textarea" readonly class="large-text code" rows="28"><?php echo esc_textarea($mega_prompt); ?></textarea>
    </div>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; margin-top: 15px;">
        <h4 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
            <span class="dashicons dashicons-terminal"></span>
            <?php esc_html_e('One-Line Terminal Skill Generator (Creates local .agents/skills/wp-agent-bridge/SKILL.md)', 'woo-get-data-for-ai'); ?>
        </h4>
        <p style="margin: 0 0 10px 0; color: #64748b; font-size: 13px;">
            <?php esc_html_e('Run this directly in your terminal (Antigravity, Cursor, VS Code) to download the live skill with all playbooks in 1 second:', 'woo-get-data-for-ai'); ?>
        </p>
        <code style="display: block; padding: 10px 14px; background: #1e293b; color: #38bdf8; border-radius: 4px; font-size: 12px; word-break: break-all;">
            mkdir -p .agents/skills/wp-agent-bridge && curl -s -H 'Authorization: Bearer <?php echo esc_html($active_token); ?>' '<?php echo esc_url($rest_base_url . '/capabilities?format=skill'); ?>' > .agents/skills/wp-agent-bridge/SKILL.md
        </code>
    </div>

    <div class="quick-hints">
        <h4><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e('How to use this in 2 clicks:', 'woo-get-data-for-ai'); ?></h4>
        <ol>
            <li><?php esc_html_e('Click the "Copy Mega-Prompt for AI" button above.', 'woo-get-data-for-ai'); ?></li>
            <li><?php esc_html_e('Paste it into your conversation with Antigravity, Cursor, or Claude Desktop.', 'woo-get-data-for-ai'); ?></li>
            <li><?php esc_html_e('Your AI is instantly aware of your site architecture and can query logs, plugins, Elementor templates, and settings safely!', 'woo-get-data-for-ai'); ?></li>
        </ol>
    </div>
</div>
