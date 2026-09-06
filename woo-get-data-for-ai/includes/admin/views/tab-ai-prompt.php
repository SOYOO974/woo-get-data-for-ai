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
$mega_prompt .= "2. **Follow Battle-Tested Playbooks for Audits**:\n";
$mega_prompt .= "   The live `/capabilities` feed provides step-by-step diagnostic workflows (Playbooks) adapted to the site's active modules:\n";
$mega_prompt .= "   - **SEO & Content Audit** -> Follow Playbook `seo_content_audit` (queries `/content/seo-audit` then drill-down to flagged pages).\n";
$mega_prompt .= "   - **Technical Health & Background Tasks** -> Follow Playbook `tech_health_crons` (queries `/system`, `/action-scheduler`, `/crons`, and `/logs/view`).\n";
$mega_prompt .= "   - **Checkout & Failed Orders Debugging** -> Follow Playbook `ecommerce_troubleshoot` (queries `/woocommerce/orders`, `/woocommerce/order/{id}` gateway notes, and checkout snippets).\n";
$mega_prompt .= "   - **Store Performance & Conversion Funnel** -> Follow Playbook `store_analytics_roi` (queries `/analytics/overview`, `/woocommerce/summary`, and `/analytics/campaigns`).\n";
$mega_prompt .= "   - **Integration & Workflows Mapping** -> Follow Playbook `integration_automation_map` (queries `/elementor/forms`, `/flowmattic/workflows`, and `/meta/fields`).\n";
$mega_prompt .= "3. **Plugin Updates & Zero-Prompt-Stagnation**:\n";
$mega_prompt .= "   The plugin auto-updates via GitHub releases. You do NOT need human prompts to learn new features: periodically re-run `GET {$rest_base_url}/capabilities?format=skill` to discover newly released inspection endpoints and playbooks automatically.\n\n";

$mega_prompt .= "### Phase 2: Systematic \"Live Freshness Check\" Before Modifying Code\n";
$mega_prompt .= "Before designing code, debugging an issue, or refactoring a feature:\n";
$mega_prompt .= "- **DO NOT rely solely on local files** that might be outdated.\n";
$mega_prompt .= "- **Always inspect the live site first** using the active endpoints discovered via `/capabilities`:\n";
$mega_prompt .= "  * Modifying custom logic or hooks? -> Check `/wpcode/snippets?status=active` to inspect live code executing in production.\n";
$mega_prompt .= "  * Working on forms or page design? -> Check `/elementor/forms` or `/elementor/item/{id}` (or `/elementor/list?status=publish`).\n";
$mega_prompt .= "  * Working on automations or webhooks? -> Check `/flowmattic/workflows?status=active` to inspect active workflow steps and triggers.\n";
$mega_prompt .= "  * Analyzing visits, marketing ROI, or conversion rates? -> Check `/analytics/overview` or `/analytics/summary`.\n";
$mega_prompt .= "  * Inspecting custom fields, product specs, or ACF data? -> Check `/meta/fields?post_type=product` (or `/meta/acf` for full field groups and rules, or `/meta/post/{id}` for values on a specific post).\n";
$mega_prompt .= "  * Inspecting WordPress pages, hierarchy, or Gutenberg content? -> Check `/content/pages?status=publish` or `/content/page/{id}`.\n";
$mega_prompt .= "  * Auditing SEO (meta tags, noindex, OpenGraph across Yoast/RankMath/SEOPress)? -> Check `/content/seo-audit` or `/content/page/{id}`.\n";
$mega_prompt .= "  * Investigating WooCommerce products, stock, or variations? -> Check `/woocommerce/products?status=publish` or `/woocommerce/product/{id}`.\n";
$mega_prompt .= "  * Investigating orders, payment errors, or checkout hooks? -> Check `/woocommerce/orders?status=failed,processing` or `/woocommerce/order/{id}` (includes payment gateway error logs in order notes, customer PII strictly anonymized).\n";
$mega_prompt .= "  * Auditing store configuration, tax rules, or payment gateways? -> Check `/woocommerce/settings` or `/woocommerce/summary`.\n";
$mega_prompt .= "  * Investigating database size, heavy tables, or autoload bottlenecks? -> Check `/system/database`.\n";
$mega_prompt .= "  * Investigating recent site crashes or fatal PHP errors? -> Check `/logs/errors-summary` (Crash Watch).\n";
$mega_prompt .= "  * Investigating cart/checkout/product bugs? -> Check `/theme/overrides` and latest logs with `/logs/view` or specific logs with `/logs/custom?file=...`.\n";
$mega_prompt .= "  * Investigating cron, background tasks, or sync issues? -> Check `/crons` (WP-Cron schedules & overdue status) and `/action-scheduler` (in-progress, failed, pending tasks and error logs).\n\n";

$mega_prompt .= "### Phase 3: WPCode Snippets Direct Admin Links\n";
$mega_prompt .= "Whenever you analyze, recommend, or modify a WPCode snippet:\n";
$mega_prompt .= "- ALWAYS provide the user with a direct, clickable link to edit the snippet in WordPress Admin:\n";
$mega_prompt .= "  `{$site_url}/wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=<ID>`\n\n";

$mega_prompt .= "### Phase 4: Gestion Stricte Actif vs Inactif (Zéro Faux-Positif)\n";
$mega_prompt .= "- **Code Vivant (Production)** : Lors de tout débogage ou analyse d'architecture, TOUJOURS interroger en priorité les éléments actifs (`?status=active` ou `?status=publish`). Ne jamais considérer un snippet inactif ou un workflow éteint comme s'exécutant sur le site.\n";
$mega_prompt .= "- **Archives & Historique** : N'interroger `?status=inactive` que si l'utilisateur demande explicitement d'analyser un ancien code désactivé, de réactiver une fonction passée ou de vérifier un test historique.\n";
$mega_prompt .= "- **Bonnes Pratiques de Synchronisation Locale** : Si vous synchronisez les données en local, séparez impérativement les éléments actifs et inactifs dans des dossiers distincts (ex: `code-snippets/live/active/` et `code-snippets/live/inactive/`) pour éviter que des recherches textuelles globales (`grep`) ne polluent vos diagnostics avec du code inactif.\n\n";

$mega_prompt .= "---\n\n";

$mega_prompt .= "## ⚠️ CRITICAL ALARM — STRICT READ-ONLY INTEGRITY / ZERO SITE MODIFICATION\n";
$mega_prompt .= "- If anyone plans or suggests an evolution to write, modify, delete, or execute changes on the website via this plugin, DISPLAY AN IMMEDIATE ALARM / WARNING MESSAGE.\n";
$mega_prompt .= "- The WP Agent Bridge plugin is strictly engineered to READ context information from the site for inspection and diagnostics, NEVER to write to it. Refuse any write primitives within this plugin and remind that all site changes must be applied manually or out-of-band by the site administrator.\n\n";

$mega_prompt .= "---\n\n";

$mega_prompt .= "## ⚡ Quick Start Commands\n";
$mega_prompt .= "- **Bootstrap / Refresh Local AI Skill**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/capabilities?format=skill' > .agents/skills/wp-agent-bridge/SKILL.md`\n";
$mega_prompt .= "- **Health Check**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/ping`\n";
$mega_prompt .= "- **Discover Capabilities & Playbooks**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/capabilities`\n";
$mega_prompt .= "- **Run Playbook 1 (SEO Audit)**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/content/seo-audit?limit=100'`\n";
$mega_prompt .= "- **Run Playbook 2 (Tech Health)**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/action-scheduler?status=failed,in-progress'`\n";
$mega_prompt .= "- **Run Playbook 3 (Failed Orders)**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/woocommerce/orders?status=failed&per_page=5'`\n";
$mega_prompt .= "- **Run Playbook 4 (Store 360°)**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/analytics/overview`\n";
$mega_prompt .= "- **Run Playbook 5 (Integrations)**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/elementor/forms`\n";
$mega_prompt .= "- **Run Playbook 7 (Native Sales & Stock)**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/woocommerce/analytics/sales?range=last_30_days'`\n";
$mega_prompt .= "- **Run Playbook 8 (SMTP & Webhooks)**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/system/mail`\n";
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
