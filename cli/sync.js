#!/usr/bin/env node
/**
 * WP Agent Bridge - Local Synchronization Client (CLI)
 * Zero external dependencies (uses native Node.js).
 */

const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');

// Simple .env parser
function loadEnv(envPath) {
    if (!fs.existsSync(envPath)) return {};
    const content = fs.readFileSync(envPath, 'utf8');
    const env = {};
    content.split('\n').forEach(line => {
        const trimmed = line.trim();
        if (trimmed && !trimmed.startsWith('#')) {
            const idx = trimmed.indexOf('=');
            if (idx > -1) {
                const key = trimmed.substring(0, idx).trim();
                let val = trimmed.substring(idx + 1).trim();
                if ((val.startsWith('"') && val.endsWith('"')) || (val.startsWith("'") && val.endsWith("'"))) {
                    val = val.slice(1, -1);
                }
                env[key] = val;
            }
        }
    });
    return env;
}

// Parse CLI arguments & environment
const envFromFile = loadEnv(path.resolve(process.cwd(), '.env')) || {};
const args = process.argv.slice(2);

function getArg(flag, envKey, fallback = '') {
    const found = args.find(a => a.startsWith(`--${flag}=`));
    if (found) return found.split('=')[1];
    return process.env[envKey] || envFromFile[envKey] || fallback;
}

let siteUrl = getArg('site', 'SITE_URL').replace(/\/+$/, '');
const token = getArg('token', 'AGENT_BRIDGE_TOKEN');
const outputDir = path.resolve(process.cwd(), getArg('out', 'OUTPUT_DIR', './synced-site-data'));
const command = args[0] && !args[0].startsWith('--') ? args[0] : 'pull:all';

if (!siteUrl || !token) {
    console.error('\x1b[31m%s\x1b[0m', 'Error: Missing SITE_URL or AGENT_BRIDGE_TOKEN.');
    console.log('Usage: node sync.js [command] --site=https://example.com --token=YOUR_TOKEN --out=./synced-site-data');
    console.log('Commands: pull:all, pull:system, pull:theme, pull:elementor, pull:snippets, pull:flowmattic, pull:analytics, pull:logs');
    process.exit(1);
}

// Base REST API URL
const apiBase = `${siteUrl}/wp-json/agent-bridge/v1`;

function makeRequest(endpoint) {
    return new Promise((resolve, reject) => {
        const fullUrl = `${apiBase}${endpoint}`;
        const urlObj = new URL(fullUrl);
        const client = urlObj.protocol === 'https:' ? https : http;

        const options = {
            hostname: urlObj.hostname,
            port: urlObj.port || (urlObj.protocol === 'https:' ? 443 : 80),
            path: urlObj.pathname + urlObj.search,
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${token}`,
                'User-Agent': 'WP-Agent-Bridge-CLI/1.0',
                'Accept': 'application/json'
            }
        };

        const req = client.request(options, (res) => {
            let body = '';
            res.on('data', chunk => body += chunk);
            res.on('end', () => {
                if (res.statusCode >= 200 && res.statusCode < 300) {
                    try {
                        resolve(JSON.parse(body));
                    } catch (e) {
                        resolve(body);
                    }
                } else {
                    reject(new Error(`HTTP ${res.statusCode}: ${body}`));
                }
            });
        });

        req.on('error', reject);
        req.end();
    });
}

function ensureDir(dir) {
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
}

function writeJson(filePath, data) {
    ensureDir(path.dirname(filePath));
    fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf8');
}

function writeText(filePath, text) {
    ensureDir(path.dirname(filePath));
    fs.writeFileSync(filePath, text, 'utf8');
}

// Subcommands
async function pullSystem() {
    console.log('⏳ Pulling System & Environment...');
    try {
        const data = await makeRequest('/system');
        writeJson(path.join(outputDir, 'system/system.json'), data);

        // Generate Markdown summary
        let md = `# System Report for ${siteUrl}\n\n`;
        md += `**Generated**: ${new Date().toISOString()}\n\n`;
        md += `## Server & Environment\n`;
        md += `- **PHP**: ${data.system.php_version} (SAPI: ${data.system.php_sapi})\n`;
        md += `- **Web Server**: ${data.system.web_server}\n`;
        md += `- **MySQL**: ${data.system.mysql_version}\n`;
        md += `- **Memory Limit**: ${data.system.memory_limit} (WP: ${data.system.wp_memory_limit})\n\n`;

        md += `## WordPress Core\n`;
        md += `- **Version**: ${data.wordpress.version}\n`;
        md += `- **Debug Mode**: ${data.wordpress.debug_mode ? 'Enabled' : 'Disabled'}\n`;
        md += `- **Timezone**: ${data.wordpress.timezone}\n\n`;

        if (data.woocommerce) {
            md += `## WooCommerce\n`;
            md += `- **Version**: ${data.woocommerce.version}\n`;
            md += `- **Currency**: ${data.woocommerce.currency}\n`;
            if (data.woocommerce.hpos) {
                md += `- **HPOS Enabled**: ${data.woocommerce.hpos.enabled ? 'Yes' : 'No'}\n`;
            }
            md += `\n`;
        }

        md += `## Active Plugins (${data.plugins_count})\n`;
        data.plugins.filter(p => p.is_active).forEach(p => {
            md += `- **${p.name}** (v${p.version})${p.update_available ? ' ⚠️ [Update available: ' + p.new_version + ']' : ''}\n`;
        });

        writeText(path.join(outputDir, 'system-report.md'), md);
        console.log('✅ System report saved to system-report.md');
    } catch (err) {
        console.error('❌ Failed to pull system:', err.message);
    }
}

async function pullTheme() {
    console.log('⏳ Pulling Theme & Overrides...');
    try {
        // Theme Options
        const opts = await makeRequest('/theme/options');
        if (opts.woodmart) writeJson(path.join(outputDir, 'theme/woodmart-options.json'), opts.woodmart);
        if (opts.elessi) writeJson(path.join(outputDir, 'theme/elessi-options.json'), opts.elessi);
        if (opts.theme_mods) writeJson(path.join(outputDir, 'theme/theme-mods.json'), opts.theme_mods);

        // Child Theme Code
        const child = await makeRequest('/theme/child');
        if (child.functions_php && child.functions_php.content) {
            writeText(path.join(outputDir, 'theme/child-functions.php'), child.functions_php.content);
        }
        if (child.style_css && child.style_css.content) {
            writeText(path.join(outputDir, 'theme/child-style.css'), child.style_css.content);
        }

        // WooCommerce Overrides
        const overrides = await makeRequest('/theme/overrides');
        let ovMd = `# WooCommerce Template Overrides (${overrides.total_overrides})\n\n`;
        if (overrides.has_outdated) {
            ovMd += `> ⚠️ **Warning**: Some template overrides are outdated and may cause errors!\n\n`;
        }
        ovMd += `| Template | Theme Version | Core Version | Status |\n|---|---|---|---|\n`;
        (overrides.overrides || []).forEach(o => {
            const status = o.is_outdated ? '🔴 Outdated' : '🟢 Up to date';
            ovMd += `| \`${o.file}\` | ${o.theme_version || '-'} | ${o.core_version || '-'} | ${status} |\n`;
        });
        writeText(path.join(outputDir, 'theme/woocommerce-overrides.md'), ovMd);

        console.log('✅ Theme configurations and overrides saved.');
    } catch (err) {
        console.error('❌ Failed to pull theme:', err.message);
    }
}

async function pullElementor() {
    console.log('⏳ Pulling Elementor Data...');
    try {
        let bulkSuccess = false;
        try {
            // Attempt bulk export in 1 optimized request (v1.0.4+)
            const exportAll = await makeRequest('/elementor/export-all?per_page=100');
            if (exportAll && exportAll.items) {
                bulkSuccess = true;
                if (exportAll.kit) {
                    writeJson(path.join(outputDir, 'elementor/global-kit.json'), exportAll.kit);
                }
                if (exportAll.forms && exportAll.forms.forms) {
                    let formMd = `# Elementor Forms Inventory (${exportAll.forms.total_forms || 0})\n\n`;
                    (exportAll.forms.forms || []).forEach(f => {
                        formMd += `### Form: "${f.form_name}" (Page: ${f.post_title})\n`;
                        formMd += `- **Page URL**: ${f.post_url}\n`;
                        formMd += `- **Actions**: ${(f.submit_actions || []).join(', ')}\n`;
                        if (f.webhook_url) formMd += `- **Webhook URL**: \`${f.webhook_url}\`\n`;
                        if (f.email_to) formMd += `- **Email To**: \`${f.email_to}\`\n`;
                        formMd += `\n**Fields (${f.fields_count || 0})**:\n`;
                        (f.fields || []).forEach(fld => {
                            formMd += `  - \`${fld.id}\` [${fld.type}] "${fld.label}" ${fld.required ? '*(Required)*' : ''}\n`;
                        });
                        formMd += `\n---\n\n`;
                    });
                    writeText(path.join(outputDir, 'elementor/forms-inventory.md'), formMd);
                }

                console.log(`  Exported ${exportAll.count} of ${exportAll.total} Elementor items via bulk export...`);
                for (const item of (exportAll.items || [])) {
                    const folder = item.post_type === 'elementor_library' ? 'templates' : 'pages';
                    const filename = `${item.slug || item.id}.json`;
                    writeJson(path.join(outputDir, `elementor/${folder}/${filename}`), item);
                }

                // If more pages exist
                if (exportAll.total_pages > 1) {
                    for (let p = 2; p <= exportAll.total_pages; p++) {
                        const nextBatch = await makeRequest(`/elementor/export-all?per_page=100&page=${p}`);
                        for (const item of (nextBatch.items || [])) {
                            const folder = item.post_type === 'elementor_library' ? 'templates' : 'pages';
                            const filename = `${item.slug || item.id}.json`;
                            writeJson(path.join(outputDir, `elementor/${folder}/${filename}`), item);
                        }
                    }
                }
                console.log('✅ Elementor definitions successfully downloaded via bulk export.');
            }
        } catch (bulkErr) {
            // Bulk endpoint not available or errored, falling back to legacy individual requests
        }

        if (!bulkSuccess) {
            // Legacy individual requests fallback
            try {
                const kit = await makeRequest('/elementor/kit');
                writeJson(path.join(outputDir, 'elementor/global-kit.json'), kit);
            } catch (e) {}

            try {
                const formsData = await makeRequest('/elementor/forms');
                let formMd = `# Elementor Forms Inventory (${formsData.total_forms})\n\n`;
                (formsData.forms || []).forEach(f => {
                    formMd += `### Form: "${f.form_name}" (Page: ${f.post_title})\n`;
                    formMd += `- **Page URL**: ${f.post_url}\n`;
                    formMd += `- **Actions**: ${f.submit_actions.join(', ')}\n`;
                    if (f.webhook_url) formMd += `- **Webhook URL**: \`${f.webhook_url}\`\n`;
                    if (f.email_to) formMd += `- **Email To**: \`${f.email_to}\`\n`;
                    formMd += `\n**Fields (${f.fields_count})**:\n`;
                    f.fields.forEach(fld => {
                        formMd += `  - \`${fld.id}\` [${fld.type}] "${fld.label}" ${fld.required ? '*(Required)*' : ''}\n`;
                    });
                    formMd += `\n---\n\n`;
                });
                writeText(path.join(outputDir, 'elementor/forms-inventory.md'), formMd);
            } catch (e) {}

            const list = await makeRequest('/elementor/list');
            console.log(`  Found ${list.total} Elementor items. Downloading details...`);

            for (const item of (list.items || [])) {
                const itemData = await makeRequest(`/elementor/item/${item.id}`);
                const folder = item.post_type === 'elementor_library' ? 'templates' : 'pages';
                const filename = `${item.slug || item.id}.json`;
                writeJson(path.join(outputDir, `elementor/${folder}/${filename}`), itemData);
            }

            console.log('✅ Elementor definitions successfully downloaded.');
        }
    } catch (err) {
        console.error('❌ Failed to pull Elementor:', err.message);
    }
}

async function pullSnippets() {
    console.log('⏳ Pulling WPCode & Code Snippets...');
    try {
        const data = await makeRequest('/wpcode/snippets');
        (data.snippets || []).forEach(snip => {
            const ext = snip.code_type === 'javascript' || snip.code_type === 'js' ? 'js' : (snip.code_type === 'css' ? 'css' : 'php');
            const cleanTitle = (snip.title || 'snippet').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
            const filename = `${snip.id}-${cleanTitle}.${ext}`;

            let header = `/**\n * Snippet: ${snip.title}\n * Source: ${snip.source_plugin}\n * Status: ${snip.status}\n * Location: ${snip.location}\n * Priority: ${snip.priority}\n * Modified: ${snip.modified_at}\n */\n\n`;
            writeText(path.join(outputDir, `snippets/${filename}`), header + (snip.code || ''));
        });
        console.log(`✅ Saved ${data.total} snippets to ./snippets/`);
    } catch (err) {
        console.error('❌ Failed to pull snippets:', err.message);
    }
}

async function pullLogs() {
    console.log('⏳ Pulling Error & WooCommerce Logs...');
    try {
        const sourcesData = await makeRequest('/logs/sources');
        const sources = sourcesData.sources || [];

        // Pull debug.log if present
        const hasDebug = sources.find(s => s.name === 'debug.log');
        if (hasDebug) {
            const logData = await makeRequest('/logs/view?source=debug.log&lines=300');
            writeText(path.join(outputDir, 'logs/debug-tail.log'), (logData.lines || []).join('\n'));
        }

        // Pull top 3 recent WC logs
        const wcLogs = sources.filter(s => s.source_type === 'woocommerce').slice(0, 3);
        for (const wcLog of wcLogs) {
            const logData = await makeRequest(`/logs/view?source=${encodeURIComponent(wcLog.path)}&lines=200`);
            writeText(path.join(outputDir, `logs/${wcLog.name}`), (logData.lines || []).join('\n'));
        }

        console.log('✅ Recent logs downloaded to ./logs/');
    } catch (err) {
        console.error('❌ Failed to pull logs:', err.message);
    }
}

async function pullFlowmattic() {
    console.log('⏳ Pulling FlowMattic Workflows...');
    try {
        let bulkSuccess = false;
        try {
            // Attempt bulk export in 1 optimized request (v1.0.5+)
            const exportAll = await makeRequest('/flowmattic/export-all?per_page=100');
            if (exportAll && exportAll.flowmattic_installed && exportAll.items) {
                bulkSuccess = true;
                const items = exportAll.items || [];
                console.log(`  Found ${exportAll.total} FlowMattic workflows via bulk export...`);

                let summaryMd = `# FlowMattic Workflows (${exportAll.total})\n\n`;
                summaryMd += `| Workflow ID | Name | Status | Trigger | Actions | Tasks Executed |\n`;
                summaryMd += `|---|---|---|---|---|---|\n`;

                for (const item of items) {
                    const statusIcon = item.status === 'on' ? '🟢 On' : '⚪ Off';
                    summaryMd += `| \`${item.workflow_id}\` | **${item.workflow_name}** | ${statusIcon} | \`${item.trigger}\` | ${item.actions_count} | ${item.task_count} |\n`;

                    // Write native FlowMattic JSON export file
                    const cleanName = (item.workflow_name || 'workflow').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                    const filename = `workflow-${item.workflow_id}-${cleanName}.json`;
                    writeJson(path.join(outputDir, `flowmattic/workflows/${filename}`), item.export_data || item);
                }

                // If more pages exist
                if (exportAll.total_pages > 1) {
                    for (let p = 2; p <= exportAll.total_pages; p++) {
                        const nextBatch = await makeRequest(`/flowmattic/export-all?per_page=100&page=${p}`);
                        for (const item of (nextBatch.items || [])) {
                            const statusIcon = item.status === 'on' ? '🟢 On' : '⚪ Off';
                            summaryMd += `| \`${item.workflow_id}\` | **${item.workflow_name}** | ${statusIcon} | \`${item.trigger}\` | ${item.actions_count} | ${item.task_count} |\n`;

                            const cleanName = (item.workflow_name || 'workflow').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                            const filename = `workflow-${item.workflow_id}-${cleanName}.json`;
                            writeJson(path.join(outputDir, `flowmattic/workflows/${filename}`), item.export_data || item);
                        }
                    }
                }

                writeText(path.join(outputDir, 'flowmattic/workflows-summary.md'), summaryMd);
                console.log(`✅ Saved ${exportAll.total} FlowMattic workflows to ./flowmattic/workflows/`);
            } else if (exportAll && exportAll.flowmattic_installed === false) {
                console.log('ℹ️  FlowMattic is not installed or active on the site (skipped).');
                return;
            }
        } catch (bulkErr) {
            // Bulk endpoint not available, falling back to individual requests
        }

        if (!bulkSuccess) {
            const listData = await makeRequest('/flowmattic/workflows');
            if (listData && listData.flowmattic_installed === false) {
                console.log('ℹ️  FlowMattic is not installed or active on the site (skipped).');
                return;
            }

            const workflows = listData.workflows || [];
            console.log(`  Found ${workflows.length} FlowMattic workflows. Downloading individual exports...`);

            let summaryMd = `# FlowMattic Workflows (${workflows.length})\n\n`;
            summaryMd += `| Workflow ID | Name | Status | Trigger | Actions | Tasks Executed |\n`;
            summaryMd += `|---|---|---|---|---|---|\n`;

            for (const wf of workflows) {
                const statusIcon = wf.status === 'on' ? '🟢 On' : '⚪ Off';
                summaryMd += `| \`${wf.workflow_id}\` | **${wf.name}** | ${statusIcon} | \`${wf.trigger}\` | ${wf.actions_count} | ${wf.task_count} |\n`;

                try {
                    const exportData = await makeRequest(`/flowmattic/workflow/${wf.workflow_id}?format=export`);
                    const cleanName = (wf.name || 'workflow').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                    const filename = `workflow-${wf.workflow_id}-${cleanName}.json`;
                    writeJson(path.join(outputDir, `flowmattic/workflows/${filename}`), exportData);
                } catch (e) {
                    console.warn(`  ⚠️ Failed to download workflow ${wf.workflow_id}:`, e.message);
                }
            }

            writeText(path.join(outputDir, 'flowmattic/workflows-summary.md'), summaryMd);
            console.log(`✅ Saved ${workflows.length} FlowMattic workflows to ./flowmattic/workflows/`);
        }
    } catch (err) {
        console.error('❌ Failed to pull FlowMattic:', err.message);
    }
}

// Pull Independent Analytics
async function pullAnalytics() {
    console.log('📊 Pulling Independent Analytics data...');
    try {
        const overview = await makeRequest('/analytics/overview?range=last_30_days');
        if (overview && overview.installed === false) {
            console.log('ℹ️  Independent Analytics is not installed on the site (skipped).');
            return;
        }

        const analyticsDir = path.join(outputDir, 'analytics');
        ensureDir(analyticsDir);

        writeJson(path.join(analyticsDir, 'overview.json'), overview);

        // Generate clean markdown executive report
        const summary = overview.summary || {};
        const traffic = summary.traffic || {};
        const ecommerce = summary.ecommerce || {};
        const growth = overview.growth || {};

        let md = `# Independent Analytics — 30-Day Executive Summary\n\n`;
        md += `> **Period**: ${overview.period?.label || 'Last 30 Days'} (${overview.period?.start_local || ''} to ${overview.period?.end_local || ''})\n\n`;

        md += `## 🚀 Key Performance Indicators (KPIs)\n\n`;
        md += `| Metric | Current Value | Growth vs Previous Period |\n`;
        md += `|---|---|---|\n`;
        md += `| **Unique Visitors** | ${traffic.visitors?.toLocaleString() || 0} | ${growth.visitors_growth_percent >= 0 ? '+' : ''}${growth.visitors_growth_percent || 0}% |\n`;
        md += `| **Page Views** | ${traffic.views?.toLocaleString() || 0} | ${growth.views_growth_percent >= 0 ? '+' : ''}${growth.views_growth_percent || 0}% |\n`;
        md += `| **Total Sessions** | ${traffic.sessions?.toLocaleString() || 0} | ${growth.sessions_growth_percent >= 0 ? '+' : ''}${growth.sessions_growth_percent || 0}% |\n`;
        md += `| **Bounce Rate** | ${traffic.bounce_rate_percent || 0}% | - |\n`;
        md += `| **Avg Session Duration** | ${traffic.avg_session_duration_fmt || '0m 00s'} | - |\n`;
        md += `| **WooCommerce Orders** | ${ecommerce.orders?.toLocaleString() || 0} | ${growth.orders_growth_percent >= 0 ? '+' : ''}${growth.orders_growth_percent || 0}% |\n`;
        md += `| **Net Sales Revenue** | ${ecommerce.net_sales?.toLocaleString() || 0} ${overview.meta?.currency || ''} | ${growth.net_sales_growth_percent >= 0 ? '+' : ''}${growth.net_sales_growth_percent || 0}% |\n`;
        md += `| **E-commerce Conversion Rate** | **${ecommerce.conversion_rate_percent || 0}%** | ${growth.conversion_rate_growth_percent >= 0 ? '+' : ''}${growth.conversion_rate_growth_percent || 0}% |\n`;
        md += `| **Average Order Value (AOV)** | ${ecommerce.average_order_value?.toLocaleString() || 0} ${overview.meta?.currency || ''} | - |\n\n`;

        // Top pages
        if (overview.top_pages && overview.top_pages.length > 0) {
            md += `## 📄 Top Pages & Products\n\n`;
            md += `| Page / Product | Type | Views | Visitors | Orders | Net Sales | Conv. Rate |\n`;
            md += `|---|---|---|---|---|---|---|\n`;
            for (const p of overview.top_pages) {
                md += `| [${p.title || p.url}](${p.url}) | \`${p.page_type || 'page'}\` | ${p.views} | ${p.visitors} | ${p.orders} | ${p.net_sales} | **${p.conversion_rate_percent}%** |\n`;
            }
            md += `\n`;
        }

        // Top referrers
        if (overview.top_referrers && overview.top_referrers.length > 0) {
            md += `## 🔗 Top Traffic Sources\n\n`;
            md += `| Source Domain | Visitors | Sessions | Orders | Net Sales | Conv. Rate |\n`;
            md += `|---|---|---|---|---|---|\n`;
            for (const r of overview.top_referrers) {
                md += `| **${r.domain}** | ${r.visitors} | ${r.sessions} | ${r.orders} | ${r.net_sales} | **${r.conversion_rate_percent}%** |\n`;
            }
            md += `\n`;
        }

        // Device Types
        if (overview.device_types && overview.device_types.length > 0) {
            md += `## 📱 Device Performance (Conversion Comparison)\n\n`;
            md += `| Device Type | Visitors | Sessions | Orders | Net Sales | Conv. Rate | Bounce Rate |\n`;
            md += `|---|---|---|---|---|---|---|\n`;
            for (const d of overview.device_types) {
                md += `| **${d.type}** | ${d.visitors} | ${d.sessions} | ${d.orders} | ${d.net_sales} | **${d.conversion_rate_percent}%** | ${d.bounce_rate_percent}% |\n`;
            }
            md += `\n`;
        }

        writeText(path.join(analyticsDir, 'summary.md'), md);
        console.log('✅ Saved Independent Analytics data to ./analytics/ (overview.json & summary.md)');
    } catch (err) {
        console.error('❌ Failed to pull Independent Analytics:', err.message);
    }
}

// Main Runner
async function run() {
    console.log(`\n🚀 WP Agent Bridge CLI connecting to: ${siteUrl}`);
    console.log(`📁 Target Directory: ${outputDir}\n`);

    ensureDir(outputDir);

    switch (command) {
        case 'pull:system':
            await pullSystem();
            break;
        case 'pull:theme':
            await pullTheme();
            break;
        case 'pull:elementor':
            await pullElementor();
            break;
        case 'pull:snippets':
            await pullSnippets();
            break;
        case 'pull:flowmattic':
            await pullFlowmattic();
            break;
        case 'pull:analytics':
            await pullAnalytics();
            break;
        case 'pull:logs':
            await pullLogs();
            break;
        case 'pull:all':
        default:
            await pullSystem();
            await pullTheme();
            await pullElementor();
            await pullSnippets();
            await pullFlowmattic();
            await pullAnalytics();
            await pullLogs();
            break;
    }

    console.log('\n✨ Done! All requested site data is available locally.\n');
}

run();
