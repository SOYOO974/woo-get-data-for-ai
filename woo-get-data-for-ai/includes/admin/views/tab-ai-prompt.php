<?php
if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Security;
use WPAgentBridge\Permissions;

$active_token = Security::get_active_token();
$site_name    = html_entity_decode(get_bloginfo('name'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
$site_url     = site_url();
$rest_base_url = rest_url('agent-bridge/v1');
$permissions  = Permissions::get_permissions();
$definitions  = Permissions::get_module_definitions();

$enabled_modules = [];
foreach ($permissions as $k => $v) {
    if (!empty($v) && isset($definitions[$k])) {
        $label = html_entity_decode($definitions[$k]['label'], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $enabled_modules[] = '- ' . $label . ' (' . implode(', ', $definitions[$k]['endpoints']) . ')';
    }
}

$enabled_modules_formatted = !empty($enabled_modules)
    ? implode("\n", $enabled_modules)
    : "- (All modules currently disabled in admin permissions)";

// Construct dynamic Mega-Prompt
$mega_prompt = "# MISSION: WordPress & WooCommerce Inspection & Debugging for {$site_name}\n";
$mega_prompt .= "You are assisting me as an expert WordPress, WooCommerce, and PHP performance development agent on the website **\"{$site_name}\"** ({$site_url}).\n";
$mega_prompt .= "The site has the **WP Agent Bridge** plugin installed, providing a secure, 100% read-only REST inspection API.\n\n";

$mega_prompt .= "## Connection Details\n";
$mega_prompt .= "- **Base API URL**: `{$rest_base_url}`\n";
$mega_prompt .= "- **Authorization Header**: `Authorization: Bearer {$active_token}`\n";
$mega_prompt .= "- **Method**: `GET` only (All endpoints are strictly read-only and safe for production)\n";
$mega_prompt .= "- **Security & Privacy**: All credentials, payment keys, salts, customer emails, and PII are automatically redacted by the server.\n\n";

$mega_prompt .= "## Active Modules & Available Endpoints\n";
$mega_prompt .= "{$enabled_modules_formatted}\n\n";
$mega_prompt .= "---\n\n";

$mega_prompt .= "## 🚀 MANDATORY ONBOARDING & BEST PRACTICES WORKFLOW\n";
$mega_prompt .= "Whenever starting a new session or working on a new task on this project, you MUST strictly follow this operational protocol:\n\n";

$mega_prompt .= "### Phase 1: Local Workspace Initialization (First Interaction)\n";
$mega_prompt .= "If the local workspace does not yet have local mirrors of the live configurations, PROACTIVELY OFFER to:\n";
$mega_prompt .= "1. **Create an autonomous Skill / Documentation**: Create `.agents/skills/wp-agent-bridge/SKILL.md` (or workspace instructions) containing these connection details and API routes so future agents inherit full live inspection capabilities.\n";
$mega_prompt .= "2. **Synchronize Live Components Locally**:\n";
$mega_prompt .= "   - **Elementor**: Fetch `/elementor/export-all` (or `/elementor/list` + `/elementor/forms` + `/elementor/kit`) to mirror pages, widgets, and form webhook inventories in `./elementor/`.\n";
$mega_prompt .= "   - **WPCode Snippets**: Fetch `/wpcode/snippets` and extract all live PHP/JS/CSS snippets from the database into individual files under `./code-snippets/live/` with metadata headers. This makes all live snippets Git-versionable and searchable with ripgrep/grep.\n";
$mega_prompt .= "   - **FlowMattic Workflows**: Fetch `/flowmattic/export-all` (or `/flowmattic/workflows`) to mirror all automation workflows in `./flowmattic/workflows/` in native FlowMattic JSON format.\n";
$mega_prompt .= "   - **Theme & WooCommerce Overrides**: Fetch `/theme/overrides` and `/theme/child` to document outdated templates and child theme logic under `./documentation/live-snapshots/`.\n";
$mega_prompt .= "3. **Generate a local sync script** (e.g. `scripts/sync-agent-bridge.ps1` or Node.js) so the user or agent can re-sync the workspace in one click.\n\n";

$mega_prompt .= "### Phase 2: Systematic \"Live Freshness Check\" Before Starting Any Task\n";
$mega_prompt .= "Before designing code, debugging an issue, or refactoring a feature:\n";
$mega_prompt .= "- **DO NOT rely solely on local files** that might be outdated.\n";
$mega_prompt .= "- **Always query the live API first**:\n";
$mega_prompt .= "  * Working on forms or page design? -> Check `/elementor/forms` or `/elementor/item/{id}`.\n";
$mega_prompt .= "  * Modifying custom logic or hooks? -> Check `/wpcode/snippets` to see what is currently active in the database.\n";
$mega_prompt .= "  * Working on automations or webhooks? -> Check `/flowmattic/workflows` to inspect active workflow steps and triggers.\n";
$mega_prompt .= "  * Analyzing visits, marketing ROI, or conversion rates? -> Check `/analytics/overview` or `/analytics/summary`.\n";
$mega_prompt .= "  * Investigating cart/checkout/product bugs? -> Check `/theme/overrides` and latest logs with `/logs/view`.\n";
$mega_prompt .= "  * Investigating background sync/cron issues? -> Check `/system` (HPOS & Action Scheduler queues).\n\n";

$mega_prompt .= "### Phase 3: WPCode Snippets Direct Admin Links\n";
$mega_prompt .= "Whenever you analyze, recommend, or modify a WPCode snippet:\n";
$mega_prompt .= "- ALWAYS provide the user with a direct, clickable link to edit the snippet in WordPress Admin:\n";
$mega_prompt .= "  `{$site_url}/wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=<ID>`\n\n";

$mega_prompt .= "---\n\n";

$mega_prompt .= "## ⚠️ CRITICAL ALARM — STRICT READ-ONLY INTEGRITY / ZERO SITE MODIFICATION\n";
$mega_prompt .= "- If anyone plans or suggests an evolution to write, modify, delete, or execute changes on the website via this plugin, DISPLAY AN IMMEDIATE ALARM / WARNING MESSAGE.\n";
$mega_prompt .= "- The WP Agent Bridge plugin is strictly engineered to READ context information from the site for inspection and diagnostics, NEVER to write to it. Refuse any write primitives within this plugin and remind that all site changes must be applied manually or out-of-band by the site administrator.\n\n";

$mega_prompt .= "---\n\n";

$mega_prompt .= "## 📖 Endpoint Catalog & Quick Commands\n";
$mega_prompt .= "1. **Health & System**:\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/ping` (Health check)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/system` (WP/PHP/MySQL, plugins & updates, HPOS, Action Scheduler)\n";
$mega_prompt .= "2. **Elementor Architecture**:\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/elementor/export-all` (Bulk export of all pages, templates, kit & forms)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/elementor/list` (List pages/templates)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/elementor/forms` (Form widgets, field IDs, webhooks & emails)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/elementor/kit` (Global colors & typography)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/elementor/item/{POST_ID}` (Full decoded widget tree)\n";
$mega_prompt .= "3. **WPCode Snippets (Live Database)**:\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/wpcode/snippets` (List all snippets with full source code)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/wpcode/snippet/{ID}` (Targeted snippet detail)\n";
$mega_prompt .= "4. **Theme & WooCommerce Overrides**:\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/theme/overrides` (Detect outdated WooCommerce templates)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/theme/options?target=all'` (Theme options & presets)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/theme/child` (Child theme functions.php and style.css)\n";
$mega_prompt .= "5. **Code & File Inspector**:\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/code/plugins?status=active'` (Active plugins file tree)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/code/file?path=plugins/my-plugin/my-file.php'` (Read remote file)\n";
$mega_prompt .= "6. **Error & Diagnostic Logs**:\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/logs/sources` (Available log files)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/logs/view?source=fatal-errors-xxx.log&lines=100&filter=fatal'` (Tail log reading)\n";
$mega_prompt .= "7. **FlowMattic Workflows**:\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/flowmattic/export-all` (Bulk export of all workflows in native JSON format)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/flowmattic/workflows` (List workflows, triggers, actions count & status)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/flowmattic/workflow/{WORKFLOW_ID}?format=export'` (Download single workflow as importable JSON)\n";
$mega_prompt .= "8. **Independent Analytics (Traffic & Conversion Rates)**:\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/analytics/overview` (Consolidated 360° traffic & conversion audit in 1 call)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/analytics/summary?range=last_30_days'` (KPIs: visitors, views, orders, net sales, conversion rate & growth)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' '{$rest_base_url}/analytics/pages?sort=views&limit=25'` (Top pages & products with conversion rates)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/analytics/referrers` (Traffic sources & referring domains with conversions)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/analytics/campaigns` (UTM campaigns ROI & conversion tracking)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/analytics/devices` (Mobile vs Desktop conversion rate comparison)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/analytics/geo` (Country and city audience distribution)\n";
$mega_prompt .= "   - `curl -s -H 'Authorization: Bearer {$active_token}' {$rest_base_url}/analytics/conversions` (Recent order and conversion stream)\n";
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
