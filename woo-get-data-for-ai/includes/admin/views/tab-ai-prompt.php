<?php
if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Security;
use WPAgentBridge\Permissions;

$active_token = Security::get_active_token();
$site_name = get_bloginfo('name');
$rest_base_url = rest_url('agent-bridge/v1');
$permissions = Permissions::get_permissions();
$definitions = Permissions::get_module_definitions();

$enabled_modules = [];
foreach ($permissions as $k => $v) {
    if (!empty($v) && isset($definitions[$k])) {
        $enabled_modules[] = $definitions[$k]['label'] . ' (' . implode(', ', $definitions[$k]['endpoints']) . ')';
    }
}

// Construct dynamic Mega-Prompt
$mega_prompt = "# MISSION: WordPress & WooCommerce Inspection & Debugging for {$site_name}\n\n";
$mega_prompt .= "You are assisting me as an expert WordPress and WooCommerce development agent on the website **\"{$site_name}\"**.\n";
$mega_prompt .= "The site has the **WP Agent Bridge** plugin installed, providing a secure, 100% read-only REST inspection API.\n\n";
$mega_prompt .= "## Connection Details\n";
$mega_prompt .= "- **Base API URL**: `{$rest_base_url}`\n";
$mega_prompt .= "- **Authorization Header**: `Authorization: Bearer {$active_token}`\n";
$mega_prompt .= "- **Method**: `GET` only (All endpoints are strictly read-only and safe for production)\n\n";

$mega_prompt .= "## Active Modules & Available Endpoints\n";
if (!empty($enabled_modules)) {
    foreach ($enabled_modules as $mod) {
        $mega_prompt .= "- " . $mod . "\n";
    }
} else {
    $mega_prompt .= "- (All modules currently disabled in admin permissions)\n";
}

$mega_prompt .= "\n## Endpoint Catalog & Usage Guide\n";
$mega_prompt .= "1. **System & Environment**:\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" {$rest_base_url}/ping` (Health check)\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" {$rest_base_url}/system` (WP/PHP/MySQL versions, active plugins, HPOS state, Action Scheduler)\n\n";

$mega_prompt .= "2. **Theme & WooCommerce Overrides**:\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" {$rest_base_url}/theme/overrides` (Scan WooCommerce template overrides & outdated versions)\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" {$rest_base_url}/theme/options?target=all` (Woodmart 'xts-woodmart-options', Elessi options, theme mods)\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" {$rest_base_url}/theme/child` (Child theme functions.php and style.css)\n\n";

$mega_prompt .= "3. **Code & Plugin Inspection**:\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" {$rest_base_url}/code/plugins` (File tree of active plugins and mu-plugins)\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" \"{$rest_base_url}/code/file?path=plugins/my-plugin/my-plugin.php\"` (Read specific sandboxed code file)\n\n";

$mega_prompt .= "4. **Elementor Architecture**:\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" {$rest_base_url}/elementor/list` (List pages/templates built with Elementor)\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" {$rest_base_url}/elementor/item/{POST_ID}` (Get complete decoded JSON element tree)\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" {$rest_base_url}/elementor/forms` (Inventory of Elementor forms, fields & submit webhooks)\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" {$rest_base_url}/elementor/kit` (Global colors and typography tokens)\n\n";

$mega_prompt .= "5. **WPCode Snippets**:\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" {$rest_base_url}/wpcode/snippets` (List all PHP/JS/CSS snippets and locations)\n\n";

$mega_prompt .= "6. **Logs & Debugging**:\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" {$rest_base_url}/logs/sources` (List available debug.log and wc-logs)\n";
$mega_prompt .= "   - `curl -H \"Authorization: Bearer {$active_token}\" \"{$rest_base_url}/logs/view?source=debug.log&lines=100\"` (Tail last 100 lines)\n\n";

$mega_prompt .= "## Instructions for the AI\n";
$mega_prompt .= "Whenever I ask you to investigate a bug, explain how a feature works, or plan an evolution:\n";
$mega_prompt .= "1. Use these endpoints via curl or HTTP requests to inspect the live configuration.\n";
$mega_prompt .= "2. If you support custom skills (like Antigravity SKILL.md or Claude MCP), automatically generate a skill or helper script so you can call these endpoints autonomously.\n";
$mega_prompt .= "3. All sensitive tokens, customer emails, and passwords are automatically redacted by the server.\n";
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
        <textarea id="mega-prompt-textarea" readonly class="large-text code" rows="18"><?php echo esc_textarea($mega_prompt); ?></textarea>
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
