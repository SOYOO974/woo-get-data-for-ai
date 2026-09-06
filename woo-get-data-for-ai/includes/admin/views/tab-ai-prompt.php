<?php
if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Security;

$active_token = Security::get_active_token();
$site_name    = html_entity_decode(get_bloginfo('name'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$site_url     = site_url();
$rest_base_url = rest_url('agent-bridge/v1');
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

$mega_prompt .= "## 🚀 MANDATORY ONBOARDING & OPERATIONAL PROTOCOL\n\n";

$mega_prompt .= "### Phase 1: Dynamic Discovery & Local Skill Bootstrap\n";
$mega_prompt .= "On your first interaction or when starting a new session on this project, YOU MUST:\n";
$mega_prompt .= "1. **Discover Live Capabilities & Available Data Types**:\n";
$mega_prompt .= "   Query the self-describing `/capabilities` discovery endpoint:\n";
$mega_prompt .= "   `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/capabilities`\n";
$mega_prompt .= "   This returns the real-time catalog of all active modules (System, Analytics, Elementor, WPCode, FlowMattic, Logs, Themes), their enabled/disabled permission status, all endpoint paths, and supported query parameters.\n";
$mega_prompt .= "2. **Bootstrap or Update Your Local Skill**:\n";
$mega_prompt .= "   Create or update `.agents/skills/wp-agent-bridge/SKILL.md` (or your workspace instructions) with the connection details and the list of active routes discovered from `/capabilities`.\n";
$mega_prompt .= "3. **Plugin Update & Freshness Check**:\n";
$mega_prompt .= "   The plugin regularly receives updates introducing new data sources. Regularly re-query `GET /capabilities` on new tasks to discover newly added data types and keep your local skill up to date automatically.\n\n";

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
$mega_prompt .= "- **Health Check**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/ping`\n";
$mega_prompt .= "- **Discover Capabilities**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/capabilities`\n";
$mega_prompt .= "- **Pages & Content**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/content/pages?per_page=5'`\n";
$mega_prompt .= "- **Site-wide SEO Audit**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/content/seo-audit`\n";
$mega_prompt .= "- **WooCommerce Store Summary**: `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/woocommerce/summary`\n";
$mega_prompt .= "- **WooCommerce Products**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/woocommerce/products?per_page=5'`\n";
$mega_prompt .= "- **WooCommerce Orders**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/woocommerce/orders?status=processing&per_page=5'`\n";
$mega_prompt .= "- **Custom Fields & ACF**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/meta/fields?post_type=product'`\n";
$mega_prompt .= "- **Active Snippets**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/wpcode/snippets?status=active'`\n";
$mega_prompt .= "- **Active Workflows**: `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/flowmattic/workflows?status=active'`\n";
?>

<div class="agent-bridge-card">
    <div class="card-header-with-action">
        <div>
            <h3><?php esc_html_e('AI Onboarding & Skill Mega-Prompt', 'woo-get-data-for-ai'); ?></h3>
            <p class="description">
                <?php esc_html_e('Connect any AI assistant (Antigravity, Cursor, Claude, ChatGPT) in 2 clicks. Copy the pre-filled prompt below and paste it directly into your AI chat session. It contains the site URL, your active access key, and all active endpoints.', 'woo-get-data-for-ai'); ?>
            </p>
        </div>
        <button type="button" class="button button-primary button-hero btn-copy" data-target="mega-prompt-textarea">
            <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy Mega-Prompt for AI', 'woo-get-data-for-ai'); ?>
        </button>
    </div>

    <div class="mega-prompt-box">
        <textarea id="mega-prompt-textarea" readonly class="large-text code" rows="28"><?php echo esc_textarea($mega_prompt); ?></textarea>
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
