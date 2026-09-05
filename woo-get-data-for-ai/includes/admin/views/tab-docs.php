<?php
if (!defined('ABSPATH')) {
    exit;
}

use WPAgentBridge\Security;

$prompt_tab_url = add_query_arg(['page' => 'wp-agent-bridge', 'tab' => 'ai_prompt'], admin_url('options-general.php'));
$perms_tab_url  = add_query_arg(['page' => 'wp-agent-bridge', 'tab' => 'permissions'], admin_url('options-general.php'));
$github_url     = defined('WOO_GET_DATA_AI_GITHUB_REPO') ? WOO_GET_DATA_AI_GITHUB_REPO : 'https://github.com/SOYOO974/woo-get-data-for-ai';
?>

<!-- Section 1: Hero Banner & What the Plugin Does -->
<div class="agent-bridge-card docs-hero-card">
    <div class="docs-hero-content">
        <h2>
            <span class="dashicons dashicons-book-alt"></span>
            <?php esc_html_e('WP Agent Bridge Documentation & Quick Guide', 'woo-get-data-for-ai'); ?>
        </h2>
        <p class="docs-hero-desc">
            <?php esc_html_e('WP Agent Bridge is an enterprise-grade, lightweight, and ultra-secure inspection plugin for WordPress & WooCommerce. It exposes a protected, 100% read-only REST API (agent-bridge/v1/) allowing AI coding assistants (Antigravity, Cursor, Claude, ChatGPT) and developer tools to safely audit, diagnose bugs, and inspect live site architecture without requiring risky SSH, SFTP, or database credentials.', 'woo-get-data-for-ai'); ?>
        </p>
    </div>
</div>

<!-- Section 2: Getting Started (First Steps) -->
<div class="agent-bridge-card">
    <h3>
        <span class="dashicons dashicons-controls-play"></span>
        <?php esc_html_e('Getting Started in 4 Quick Steps', 'woo-get-data-for-ai'); ?>
    </h3>
    <p class="description">
        <?php esc_html_e('Follow these simple steps to connect your AI assistant to this WordPress installation in less than two minutes.', 'woo-get-data-for-ai'); ?>
    </p>

    <div class="docs-steps-grid">
        <div class="docs-step-item">
            <div class="step-badge">1</div>
            <div class="step-body">
                <h4><?php esc_html_e('Verify Permissions & Token', 'woo-get-data-for-ai'); ?></h4>
                <p>
                    <?php esc_html_e('Make sure your Bearer token is active in the General tab, and select which data modules your AI is allowed to query in the Permissions Matrix tab.', 'woo-get-data-for-ai'); ?>
                </p>
                <a href="<?php echo esc_url($perms_tab_url); ?>" class="button button-secondary button-small">
                    <span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e('Configure Permissions', 'woo-get-data-for-ai'); ?>
                </a>
            </div>
        </div>

        <div class="docs-step-item step-highlight">
            <div class="step-badge">2</div>
            <div class="step-body">
                <h4><?php esc_html_e('Copy the AI Mega-Prompt', 'woo-get-data-for-ai'); ?></h4>
                <p>
                    <?php esc_html_e('Open the "AI Mega-Prompt & Skill" tab and click the copy button. The prompt is automatically pre-filled with your live site URL, active Bearer token, and available endpoints.', 'woo-get-data-for-ai'); ?>
                </p>
                <a href="<?php echo esc_url($prompt_tab_url); ?>" class="button button-primary button-small">
                    <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Go to AI Mega-Prompt Tab', 'woo-get-data-for-ai'); ?>
                </a>
            </div>
        </div>

        <div class="docs-step-item">
            <div class="step-badge">3</div>
            <div class="step-body">
                <h4><?php esc_html_e('Paste into your AI Assistant', 'woo-get-data-for-ai'); ?></h4>
                <p>
                    <?php esc_html_e('Paste the entire copied prompt into your chat session with Antigravity, Cursor, Claude Desktop, or ChatGPT. Your AI will instantly understand how to inspect the site safely.', 'woo-get-data-for-ai'); ?>
                </p>
            </div>
        </div>

        <div class="docs-step-item">
            <div class="step-badge">4</div>
            <div class="step-body">
                <h4><?php esc_html_e('Audit, Diagnose & Build', 'woo-get-data-for-ai'); ?></h4>
                <p>
                    <?php esc_html_e('Ask your AI to inspect recent WooCommerce fatal errors, analyze theme overrides, or examine custom WPCode snippets. The AI queries the endpoints via curl with zero SSH needed.', 'woo-get-data-for-ai'); ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Section 3: How It Works & Security Architecture -->
<div class="agent-bridge-card">
    <h3>
        <span class="dashicons dashicons-admin-network"></span>
        <?php esc_html_e('How It Works & Security Architecture', 'woo-get-data-for-ai'); ?>
    </h3>
    <p class="description">
        <?php esc_html_e('WP Agent Bridge has been engineered from the ground up for maximum safety on high-traffic production environments.', 'woo-get-data-for-ai'); ?>
    </p>

    <div class="docs-features-grid">
        <div class="docs-feature-card">
            <div class="feature-icon"><span class="dashicons dashicons-lock"></span></div>
            <div class="feature-content">
                <h4><?php esc_html_e('100% Read-Only Guarantee', 'woo-get-data-for-ai'); ?></h4>
                <p><?php esc_html_e('Every single endpoint strictly enforces HTTP GET (WP_REST_Server::READABLE). There are zero POST, PUT, or DELETE routes. The plugin contains no modification or execution primitives, ensuring your database and site files remain completely untouched.', 'woo-get-data-for-ai'); ?></p>
            </div>
        </div>

        <div class="docs-feature-card">
            <div class="feature-icon"><span class="dashicons dashicons-admin-network"></span></div>
            <div class="feature-content">
                <h4><?php esc_html_e('Bearer Token Authentication', 'woo-get-data-for-ai'); ?></h4>
                <p><?php esc_html_e('Requests must supply an Authorization: Bearer header. The 64-character cryptographically secure token can be rotated at any time with 1 click in admin, or permanently hardcoded via WP_AGENT_BRIDGE_TOKEN in wp-config.php.', 'woo-get-data-for-ai'); ?></p>
            </div>
        </div>

        <div class="docs-feature-card">
            <div class="feature-icon"><span class="dashicons dashicons-shield"></span></div>
            <div class="feature-content">
                <h4><?php esc_html_e('Anti-Brute-Force & IP Lockout', 'woo-get-data-for-ai'); ?></h4>
                <p><?php esc_html_e('Failed authentication attempts are automatically tracked per client IP. After exceeding the threshold, offending IPs are locked out with HTTP 429 responses. Active lockouts can be monitored and unlocked with 1 click in the admin.', 'woo-get-data-for-ai'); ?></p>
            </div>
        </div>

        <div class="docs-feature-card">
            <div class="feature-icon"><span class="dashicons dashicons-hidden"></span></div>
            <div class="feature-content">
                <h4><?php esc_html_e('Real-Time Secret & PII Redaction', 'woo-get-data-for-ai'); ?></h4>
                <p><?php esc_html_e('Before returning any JSON response, an integrated redaction engine systematically masks Stripe secret keys (sk_live_*), payment gateway secrets, database passwords, salts, and customer email addresses.', 'woo-get-data-for-ai'); ?></p>
            </div>
        </div>

        <div class="docs-feature-card">
            <div class="feature-icon"><span class="dashicons dashicons-media-text"></span></div>
            <div class="feature-content">
                <h4><?php esc_html_e('Memory-Safe Log Streaming', 'woo-get-data-for-ai'); ?></h4>
                <p><?php esc_html_e('Large log files (debug.log and WooCommerce logs) are extracted from the bottom up using reverse file pointer (fseek). This guarantees low memory consumption and avoids PHP out-of-memory errors on heavy sites.', 'woo-get-data-for-ai'); ?></p>
            </div>
        </div>

        <div class="docs-feature-card">
            <div class="feature-icon"><span class="dashicons dashicons-chart-pie"></span></div>
            <div class="feature-content">
                <h4><?php esc_html_e('Audit Trail & GeoIP Analytics', 'woo-get-data-for-ai'); ?></h4>
                <p><?php esc_html_e('All incoming API requests are logged in a lightweight table with timestamps, IP addresses, country flags (via Cloudflare header or cached GeoIP), endpoints, and HTTP response status codes.', 'woo-get-data-for-ai'); ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Section 4: What Data Can Be Inspected (Scope) -->
<div class="agent-bridge-card">
    <h3>
        <span class="dashicons dashicons-search"></span>
        <?php esc_html_e('Inspectable Technical Data', 'woo-get-data-for-ai'); ?>
    </h3>
    <p class="description">
        <?php esc_html_e('Here is the technical scope exposed by the plugin according to your permissions matrix:', 'woo-get-data-for-ai'); ?>
    </p>

    <div class="docs-scope-table-wrap">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 25%;"><?php esc_html_e('Domain', 'woo-get-data-for-ai'); ?></th>
                    <th style="width: 35%;"><?php esc_html_e('Available Endpoints', 'woo-get-data-for-ai'); ?></th>
                    <th style="width: 40%;"><?php esc_html_e('Data Returned to AI', 'woo-get-data-for-ai'); ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong><?php esc_html_e('System & Limits', 'woo-get-data-for-ai'); ?></strong></td>
                    <td><code>/ping</code>, <code>/system</code></td>
                    <td><?php esc_html_e('WordPress, PHP, MySQL versions, memory limits, active plugins with update status, HPOS state, Action Scheduler queues.', 'woo-get-data-for-ai'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('WooCommerce & Themes', 'woo-get-data-for-ai'); ?></strong></td>
                    <td><code>/theme/overrides</code>, <code>/theme/options</code>, <code>/theme/child</code></td>
                    <td><?php esc_html_e('Overridden WooCommerce templates with version check against core, Woodmart & Elessi theme settings, child theme functions.php and style.css.', 'woo-get-data-for-ai'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Code & Plugins', 'woo-get-data-for-ai'); ?></strong></td>
                    <td><code>/code/plugins</code>, <code>/code/file</code></td>
                    <td><?php esc_html_e('File trees of active plugins and mu-plugins, sandboxed reading of specific PHP, JS, or CSS files with strict path validation.', 'woo-get-data-for-ai'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Elementor Architecture', 'woo-get-data-for-ai'); ?></strong></td>
                    <td><code>/elementor/export-all</code>, <code>/elementor/list</code>, <code>/elementor/item/{id}</code>, <code>/elementor/forms</code>, <code>/elementor/kit</code></td>
                    <td><?php esc_html_e('Bulk export of all pages and templates, complete decoded JSON widget trees, form field definitions and webhook URLs, global design kit tokens.', 'woo-get-data-for-ai'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('WPCode Snippets', 'woo-get-data-for-ai'); ?></strong></td>
                    <td><code>/wpcode/snippets</code>, <code>/wpcode/snippet/{id}</code></td>
                    <td><?php esc_html_e('Inventory of all custom PHP/JS/CSS snippets stored in WPCode, execution location, active state, and full source code.', 'woo-get-data-for-ai'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Logs & Diagnostics', 'woo-get-data-for-ai'); ?></strong></td>
                    <td><code>/logs/sources</code>, <code>/logs/view</code></td>
                    <td><?php esc_html_e('List of available log files (debug.log, uploads/wc-logs/*.log), tail extraction up to 1000 lines, error and fatal filtering.', 'woo-get-data-for-ai'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('FlowMattic Workflows', 'woo-get-data-for-ai'); ?></strong></td>
                    <td><code>/flowmattic/export-all</code>, <code>/flowmattic/workflows</code>, <code>/flowmattic/workflow/{id}</code></td>
                    <td><?php esc_html_e('Bulk export and inspection of FlowMattic workflows, steps, triggers, action counts, and native importable JSON format.', 'woo-get-data-for-ai'); ?></td>
                </tr>
                <tr>
                    <td><strong><?php esc_html_e('Independent Analytics', 'woo-get-data-for-ai'); ?></strong></td>
                    <td><code>/analytics/overview</code>, <code>/analytics/summary</code>, <code>/analytics/pages</code>, <code>/analytics/referrers</code>, <code>/analytics/campaigns</code>, <code>/analytics/devices</code>, <code>/analytics/geo</code>, <code>/analytics/conversions</code></td>
                    <td><?php esc_html_e('Audience and conversion tracking: visits, views, bounce rate, WooCommerce conversion rate, net sales, top pages, acquisition channels, UTM campaigns, and device breakdowns.', 'woo-get-data-for-ai'); ?></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Section 5: GitHub Repository & Evolving the Plugin -->
<div class="agent-bridge-card docs-github-card">
    <div class="docs-github-header">
        <div class="github-title-group">
            <span class="dashicons dashicons-external github-icon"></span>
            <div>
                <h3><?php esc_html_e('Open Source & Plugin Evolution (GitHub)', 'woo-get-data-for-ai'); ?></h3>
                <p class="description">
                    <?php esc_html_e('WP Agent Bridge is actively maintained and open source. If you need new inspection capabilities or wish to contribute improvements, you are warmly invited to participate!', 'woo-get-data-for-ai'); ?>
                </p>
            </div>
        </div>
        <a href="<?php echo esc_url($github_url); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary button-hero docs-btn-github">
            <span class="dashicons dashicons-admin-links"></span> <?php esc_html_e('View Repository on GitHub', 'woo-get-data-for-ai'); ?>
        </a>
    </div>

    <div class="docs-github-grid">
        <div class="github-card-item">
            <h4><span class="dashicons dashicons-lightbulb"></span> <?php esc_html_e('Suggesting New Endpoints', 'woo-get-data-for-ai'); ?></h4>
            <p>
                <?php esc_html_e('Need to inspect WooCommerce shipping zones, tax rules, payment gateway settings, or custom database tables? You can open an issue or submit a Pull Request on GitHub.', 'woo-get-data-for-ai'); ?>
            </p>
            <p class="docs-subtext">
                <?php esc_html_e('The AI Mega-Prompt is also programmed to formulate ready-to-send feature requests and sample PHP controller code whenever your AI assistant identifies missing technical context!', 'woo-get-data-for-ai'); ?>
            </p>
        </div>

        <div class="github-card-item">
            <h4><span class="dashicons dashicons-update"></span> <?php esc_html_e('Automatic Updates via PUC', 'woo-get-data-for-ai'); ?></h4>
            <p>
                <?php esc_html_e('This plugin embeds the Plugin Update Checker (PUC v5.6). Whenever a new GitHub Release is tagged and published with a higher version number, your WordPress installation will automatically notify you and allow 1-click updates right from your Plugins dashboard.', 'woo-get-data-for-ai'); ?>
            </p>
        </div>

        <div class="github-card-item">
            <h4><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e('Contact & Direct Support', 'woo-get-data-for-ai'); ?></h4>
            <p>
                <?php esc_html_e('Have questions, ideas, or feedback? You can contact the author directly at:', 'woo-get-data-for-ai'); ?>
            </p>
            <p>
                <a href="mailto:julien@soyoo.re" class="docs-contact-link">
                    <code>julien@soyoo.re</code>
                </a>
            </p>
        </div>
    </div>
</div>
