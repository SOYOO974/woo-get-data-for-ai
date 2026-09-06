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
const statusFilter = getArg('status', 'STATUS_FILTER', 'all');
const command = args[0] && !args[0].startsWith('--') ? args[0] : 'pull:all';

if (!siteUrl || !token) {
    console.error('\x1b[31m%s\x1b[0m', 'Error: Missing SITE_URL or AGENT_BRIDGE_TOKEN.');
    console.log('Usage: node sync.js [command] --site=https://example.com --token=YOUR_TOKEN --out=./synced-site-data [--status=active|inactive|all]');
    console.log('Commands: pull:all, pull:capabilities, pull:system, pull:scheduler, pull:theme, pull:elementor, pull:snippets, pull:flowmattic, pull:analytics, pull:meta, pull:woocommerce, pull:logs');
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
        const statusParam = statusFilter !== 'all' ? `&status=${encodeURIComponent(statusFilter)}` : '';
        try {
            // Attempt bulk export in 1 optimized request (v1.0.4+)
            const exportAll = await makeRequest(`/elementor/export-all?per_page=100${statusParam}`);
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

                console.log(`  Exported ${exportAll.count} of ${exportAll.total} Elementor items via bulk export (${exportAll.published_count || 0} published, ${exportAll.draft_count || 0} drafts)...`);
                for (const item of (exportAll.items || [])) {
                    const statusFolder = (item.is_published || item.status === 'publish') ? 'published' : 'draft';
                    const typeFolder = item.post_type === 'elementor_library' ? 'templates' : 'pages';
                    const filename = `${item.slug || item.id}.json`;
                    writeJson(path.join(outputDir, `elementor/${typeFolder}/${statusFolder}/${filename}`), item);
                }

                // If more pages exist
                if (exportAll.total_pages > 1) {
                    for (let p = 2; p <= exportAll.total_pages; p++) {
                        const nextBatch = await makeRequest(`/elementor/export-all?per_page=100&page=${p}${statusParam}`);
                        for (const item of (nextBatch.items || [])) {
                            const statusFolder = (item.is_published || item.status === 'publish') ? 'published' : 'draft';
                            const typeFolder = item.post_type === 'elementor_library' ? 'templates' : 'pages';
                            const filename = `${item.slug || item.id}.json`;
                            writeJson(path.join(outputDir, `elementor/${typeFolder}/${statusFolder}/${filename}`), item);
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

            const listQuery = statusFilter !== 'all' ? `?status=${encodeURIComponent(statusFilter)}` : '';
            const list = await makeRequest(`/elementor/list${listQuery}`);
            console.log(`  Found ${list.total} Elementor items. Downloading details...`);

            for (const item of (list.items || [])) {
                const itemData = await makeRequest(`/elementor/item/${item.id}`);
                const statusFolder = (item.is_published || item.status === 'publish') ? 'published' : 'draft';
                const typeFolder = item.post_type === 'elementor_library' ? 'templates' : 'pages';
                const filename = `${item.slug || item.id}.json`;
                writeJson(path.join(outputDir, `elementor/${typeFolder}/${statusFolder}/${filename}`), itemData);
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
        const query = statusFilter !== 'all' ? `?status=${encodeURIComponent(statusFilter)}` : '';
        const data = await makeRequest(`/wpcode/snippets${query}`);
        let activeSaved = 0;
        let inactiveSaved = 0;

        (data.snippets || []).forEach(snip => {
            const ext = snip.code_type === 'javascript' || snip.code_type === 'js' ? 'js' : (snip.code_type === 'css' ? 'css' : 'php');
            const cleanTitle = (snip.title || 'snippet').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
            const filename = `${snip.id}-${cleanTitle}.${ext}`;
            const isAct = (snip.is_active || snip.status === 'active');
            const subfolder = isAct ? 'active' : 'inactive';

            if (isAct) {
                activeSaved++;
            } else {
                inactiveSaved++;
            }

            let header = `/**\n * Snippet: ${snip.title}\n * Source: ${snip.source_plugin}\n * Status: ${snip.status}\n * Location: ${snip.location}\n * Priority: ${snip.priority}\n * Modified: ${snip.modified_at}\n */\n\n`;
            writeText(path.join(outputDir, `snippets/${subfolder}/${filename}`), header + (snip.code || ''));
        });
        console.log(`✅ Saved ${activeSaved} active snippets to ./snippets/active/ and ${inactiveSaved} inactive snippets to ./snippets/inactive/ (Total: ${data.total || (activeSaved + inactiveSaved)})`);
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

        // Pull custom logs found in wp-content/ (e.g. komela-order-status-sync.log)
        const customLogs = sources.filter(s => s.source_type === 'custom');
        for (const cLog of customLogs) {
            try {
                const logData = await makeRequest(`/logs/custom?file=${encodeURIComponent(cLog.name)}&lines=200`);
                writeText(path.join(outputDir, `logs/${cLog.name}`), (logData.lines || []).join('\n'));
            } catch (e) {
                // Silently skip if custom log read fails
            }
        }

        console.log('✅ Recent logs downloaded to ./logs/');
    } catch (err) {
        console.error('❌ Failed to pull logs:', err.message);
    }
}

async function pullScheduler() {
    console.log('⏳ Pulling WP-Cron & Action Scheduler...');
    try {
        // 1. WP-Cron
        try {
            const cronData = await makeRequest('/crons?limit=200');
            writeJson(path.join(outputDir, 'scheduler/crons.json'), cronData);

            let cronMd = `# WP-Cron Registry (${cronData.summary?.total_registered || 0} registered)\n\n`;
            cronMd += `**Server Time (Local)**: ${cronData.cron_system?.server_time_local || '-'}\n`;
            cronMd += `**WP-Cron Enabled**: ${cronData.cron_system?.cron_enabled ? '✅ Yes' : '❌ No (DISABLE_WP_CRON active)'}\n`;
            cronMd += `**Overdue Jobs**: ${cronData.summary?.overdue_count > 0 ? '⚠️ ' + cronData.summary.overdue_count + ' overdue' : '✅ 0 overdue'}\n\n`;

            cronMd += `| Hook | Next Run | Interval | Overdue? | Arguments |\n`;
            cronMd += `|---|---|---|---|---|\n`;

            for (const c of (cronData.crons || [])) {
                const overdueBadge = c.is_overdue ? '🔴 Overdue' : '🟢 Normal';
                const argsSummary = c.args && (Array.isArray(c.args) ? c.args.length > 0 : Object.keys(c.args).length > 0)
                    ? `\`${JSON.stringify(c.args)}\``
                    : '-';
                cronMd += `| \`${c.hook}\` | ${c.human_diff} (${c.next_run_local}) | ${c.schedule_name} | ${overdueBadge} | ${argsSummary} |\n`;
            }

            writeText(path.join(outputDir, 'scheduler/crons-summary.md'), cronMd);
            console.log('  ✅ Saved WP-Cron schedules to ./scheduler/ (crons.json & crons-summary.md)');
        } catch (e) {
            console.warn('  ⚠️ Failed to pull crons:', e.message);
        }

        // 2. Action Scheduler
        try {
            const asData = await makeRequest('/action-scheduler?status=in-progress,failed,pending&per_page=100');
            if (asData && asData.action_scheduler_installed === false) {
                console.log('  ℹ️  Action Scheduler is not installed or active on the site (skipped).');
            } else {
                writeJson(path.join(outputDir, 'scheduler/action-scheduler.json'), asData);

                let asMd = `# Action Scheduler Queue Diagnostic\n\n`;
                const s = asData.summary || {};
                asMd += `## Queue Summary\n`;
                asMd += `- **Pending**: ${s.pending || 0}\n`;
                asMd += `- **In-Progress**: ${s['in-progress'] || 0}\n`;
                asMd += `- **Failed**: ${s.failed || 0} ${s.failed > 0 ? '⚠️' : ''}\n`;
                asMd += `- **Complete**: ${s.complete || 0}\n`;
                asMd += `- **Canceled**: ${s.canceled || 0}\n`;
                asMd += `- **Total Tracked**: ${s.total || 0}\n\n`;

                const actions = asData.actions || [];
                if (actions.length > 0) {
                    asMd += `## Actions (Showing ${actions.length} actionable jobs)\n\n`;
                    asMd += `| ID | Status | Hook | Group | Scheduled Local | Attempts | Error / Log |\n`;
                    asMd += `|---|---|---|---|---|---|---|\n`;

                    for (const a of actions) {
                        const statusIcon = a.status === 'failed' ? '🔴 Failed' : (a.status === 'in-progress' ? '🟡 Running' : '🔵 Pending');
                        const latestLog = a.logs && a.logs.length > 0 ? a.logs[0].message.replace(/\|/g, '\\|') : '-';
                        asMd += `| \`${a.action_id}\` | ${statusIcon} | \`${a.hook}\` | ${a.group || '-'} | ${a.scheduled_date_local || '-'} | ${a.attempts} | ${latestLog} |\n`;
                    }
                }

                writeText(path.join(outputDir, 'scheduler/action-scheduler-summary.md'), asMd);
                console.log('  ✅ Saved Action Scheduler queue to ./scheduler/ (action-scheduler.json & action-scheduler-summary.md)');
            }
        } catch (e) {
            console.warn('  ⚠️ Failed to pull Action Scheduler:', e.message);
        }
    } catch (err) {
        console.error('❌ Failed to pull Scheduler:', err.message);
    }
}

async function pullFlowmattic() {
    console.log('⏳ Pulling FlowMattic Workflows...');
    try {
        let bulkSuccess = false;
        const statusParam = statusFilter !== 'all' ? `&status=${encodeURIComponent(statusFilter)}` : '';
        try {
            // Attempt bulk export in 1 optimized request (v1.0.5+)
            const exportAll = await makeRequest(`/flowmattic/export-all?per_page=100${statusParam}`);
            if (exportAll && exportAll.flowmattic_installed && exportAll.items) {
                bulkSuccess = true;
                const items = exportAll.items || [];
                console.log(`  Found ${exportAll.total} FlowMattic workflows via bulk export (${exportAll.active_count || 0} active, ${exportAll.inactive_count || 0} inactive)...`);

                let summaryMd = `# FlowMattic Workflows (${exportAll.total})\n\n`;
                summaryMd += `| Workflow ID | Name | Status | Trigger | Actions | Tasks Executed |\n`;
                summaryMd += `|---|---|---|---|---|---|\n`;

                let activeSaved = 0;
                let inactiveSaved = 0;

                for (const item of items) {
                    const isAct = (item.is_active || item.status === 'on');
                    if (isAct) activeSaved++;
                    else inactiveSaved++;
                    const statusSubfolder = isAct ? 'active' : 'inactive';
                    const statusIcon = isAct ? '🟢 On' : '⚪ Off';
                    summaryMd += `| \`${item.workflow_id}\` | **${item.workflow_name}** | ${statusIcon} | \`${item.trigger}\` | ${item.actions_count} | ${item.task_count} |\n`;

                    // Write native FlowMattic JSON export file
                    const cleanName = (item.workflow_name || 'workflow').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                    const filename = `workflow-${item.workflow_id}-${cleanName}.json`;
                    writeJson(path.join(outputDir, `flowmattic/workflows/${statusSubfolder}/${filename}`), item.export_data || item);
                }

                // If more pages exist
                if (exportAll.total_pages > 1) {
                    for (let p = 2; p <= exportAll.total_pages; p++) {
                        const nextBatch = await makeRequest(`/flowmattic/export-all?per_page=100&page=${p}${statusParam}`);
                        for (const item of (nextBatch.items || [])) {
                            const isAct = (item.is_active || item.status === 'on');
                            if (isAct) activeSaved++;
                            else inactiveSaved++;
                            const statusSubfolder = isAct ? 'active' : 'inactive';
                            const statusIcon = isAct ? '🟢 On' : '⚪ Off';
                            summaryMd += `| \`${item.workflow_id}\` | **${item.workflow_name}** | ${statusIcon} | \`${item.trigger}\` | ${item.actions_count} | ${item.task_count} |\n`;

                            const cleanName = (item.workflow_name || 'workflow').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                            const filename = `workflow-${item.workflow_id}-${cleanName}.json`;
                            writeJson(path.join(outputDir, `flowmattic/workflows/${statusSubfolder}/${filename}`), item.export_data || item);
                        }
                    }
                }

                writeText(path.join(outputDir, 'flowmattic/workflows-summary.md'), summaryMd);
                console.log(`✅ Saved ${activeSaved} active workflows to ./flowmattic/workflows/active/ and ${inactiveSaved} inactive workflows to ./flowmattic/workflows/inactive/`);
            } else if (exportAll && exportAll.flowmattic_installed === false) {
                console.log('ℹ️  FlowMattic is not installed or active on the site (skipped).');
                return;
            }
        } catch (bulkErr) {
            // Bulk endpoint not available, falling back to individual requests
        }

        if (!bulkSuccess) {
            const listQuery = statusFilter !== 'all' ? `?status=${encodeURIComponent(statusFilter)}` : '';
            const listData = await makeRequest(`/flowmattic/workflows${listQuery}`);
            if (listData && listData.flowmattic_installed === false) {
                console.log('ℹ️  FlowMattic is not installed or active on the site (skipped).');
                return;
            }

            const workflows = listData.workflows || [];
            console.log(`  Found ${workflows.length} FlowMattic workflows. Downloading individual exports...`);

            let summaryMd = `# FlowMattic Workflows (${workflows.length})\n\n`;
            summaryMd += `| Workflow ID | Name | Status | Trigger | Actions | Tasks Executed |\n`;
            summaryMd += `|---|---|---|---|---|---|\n`;

            let activeSaved = 0;
            let inactiveSaved = 0;

            for (const wf of workflows) {
                const isAct = (wf.is_active || wf.status === 'on');
                if (isAct) activeSaved++;
                else inactiveSaved++;
                const statusSubfolder = isAct ? 'active' : 'inactive';
                const statusIcon = isAct ? '🟢 On' : '⚪ Off';
                summaryMd += `| \`${wf.workflow_id}\` | **${wf.name}** | ${statusIcon} | \`${wf.trigger}\` | ${wf.actions_count} | ${wf.task_count} |\n`;

                try {
                    const exportData = await makeRequest(`/flowmattic/workflow/${wf.workflow_id}?format=export`);
                    const cleanName = (wf.name || 'workflow').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
                    const filename = `workflow-${wf.workflow_id}-${cleanName}.json`;
                    writeJson(path.join(outputDir, `flowmattic/workflows/${statusSubfolder}/${filename}`), exportData);
                } catch (e) {
                    console.warn(`  ⚠️ Failed to download workflow ${wf.workflow_id}:`, e.message);
                }
            }

            writeText(path.join(outputDir, 'flowmattic/workflows-summary.md'), summaryMd);
            console.log(`✅ Saved ${activeSaved} active workflows to ./flowmattic/workflows/active/ and ${inactiveSaved} inactive workflows to ./flowmattic/workflows/inactive/`);
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

async function pullMeta() {
    console.log('⏳ Pulling Custom Fields & Meta (ACF & Code)...');
    try {
        const metaDir = path.join(outputDir, 'meta');
        ensureDir(metaDir);

        // 1. Pull /meta/fields
        const fieldsData = await makeRequest('/meta/fields?source=all&include_db=true');
        writeJson(path.join(metaDir, 'fields.json'), fieldsData);

        // 2. Pull /meta/acf (deep inspection)
        try {
            const acfData = await makeRequest('/meta/acf');
            writeJson(path.join(metaDir, 'acf.json'), acfData);
        } catch (acfErr) {
            console.warn('  ⚠️ Failed to pull /meta/acf:', acfErr.message);
        }

        // 3. Generate summary markdown
        let md = `# Custom Fields & Meta Report for ${siteUrl}\n\n`;
        md += `**Generated**: ${new Date().toISOString()}\n\n`;

        if (fieldsData && fieldsData.summary) {
            md += `## Overview Summary\n`;
            md += `- **Code Registered Fields**: ${fieldsData.summary.code_registered_fields_count || 0}\n`;
            md += `- **ACF Field Groups**: ${fieldsData.summary.acf_field_groups_count || 0}\n`;
            md += `- **ACF Fields**: ${fieldsData.summary.acf_fields_count || 0}\n`;
            md += `- **Discovered Database Keys**: ${fieldsData.summary.database_keys_count || 0}\n\n`;
        }

        // ACF Field Groups Table
        if (fieldsData && fieldsData.acf && Array.isArray(fieldsData.acf.field_groups) && fieldsData.acf.field_groups.length > 0) {
            md += `## Advanced Custom Fields (ACF) Groups (${fieldsData.acf.field_groups.length})\n\n`;
            md += `| Group Key | Title | Source | Fields | Target Post Types | Location Summary |\n`;
            md += `|---|---|---|---|---|---|\n`;
            fieldsData.acf.field_groups.forEach(g => {
                const targets = (g.target_post_types || []).join(', ') || 'Any';
                md += `| \`${g.key}\` | **${g.title}** | ${g.source} | ${g.fields_count} | \`${targets}\` | ${g.location_summary || '-'} |\n`;
            });
            md += `\n`;
        }

        // Code-Registered Meta Table
        if (fieldsData && fieldsData.code_registered_meta && Array.isArray(fieldsData.code_registered_meta.fields) && fieldsData.code_registered_meta.fields.length > 0) {
            md += `## WordPress Code-Registered Meta (${fieldsData.code_registered_meta.count})\n\n`;
            md += `| Object | Subtype | Meta Key | Type | Single | Description |\n`;
            md += `|---|---|---|---|---|---|\n`;
            fieldsData.code_registered_meta.fields.forEach(f => {
                md += `| \`${f.object_type}\` | \`${f.subtype}\` | \`**${f.meta_key}**\` | \`${f.type}\` | ${f.single ? 'Yes' : 'No'} | ${f.description || '-'} |\n`;
            });
            md += `\n`;
        }

        // Discovered DB keys Table
        if (fieldsData && fieldsData.database_meta && Array.isArray(fieldsData.database_meta.meta_keys) && fieldsData.database_meta.meta_keys.length > 0) {
            md += `## Discovered Database Keys (Sampled)\n\n`;
            md += `| Post Type | Meta Key | Occurrences | Type Hint |\n`;
            md += `|---|---|---|---|\n`;
            fieldsData.database_meta.meta_keys.slice(0, 50).forEach(k => {
                md += `| \`${k.post_type}\` | \`${k.meta_key}\` | ${k.occurrences} | \`${k.type_hint}\` |\n`;
            });
            md += `\n`;
        }

        writeText(path.join(metaDir, 'meta-summary.md'), md);
        console.log('✅ Saved Custom Fields & Meta to ./meta/ (fields.json, acf.json & meta-summary.md)');
    } catch (err) {
        console.error('❌ Failed to pull Meta:', err.message);
    }
}

async function pullWooCommerce() {
    console.log('⏳ Pulling WooCommerce Store Data (Summary, Settings, Products, Orders)...');
    try {
        const wcDir = path.join(outputDir, 'woocommerce');
        ensureDir(wcDir);

        // 1. Pull Summary
        console.log('   📦 Fetching WooCommerce Summary...');
        const summary = await makeRequest('/woocommerce/summary');
        writeJson(path.join(wcDir, 'summary.json'), summary);

        // 2. Pull Settings
        console.log('   ⚙️  Fetching WooCommerce Settings & Gateways...');
        const settings = await makeRequest('/woocommerce/settings');
        writeJson(path.join(wcDir, 'settings.json'), settings);

        // 3. Pull Products
        console.log(`   🛍️  Fetching Products (status: ${statusFilter === 'all' ? 'publish' : statusFilter})...`);
        const prodStatus = statusFilter === 'all' ? 'publish' : statusFilter;
        const products = await makeRequest(`/woocommerce/products?status=${encodeURIComponent(prodStatus)}&per_page=50`);
        writeJson(path.join(wcDir, 'products.json'), products);

        // 4. Pull Recent Orders (anonymized)
        console.log('   📋 Fetching Recent Orders (anonymized)...');
        const orders = await makeRequest('/woocommerce/orders?per_page=20');
        writeJson(path.join(wcDir, 'orders.json'), orders);

        // 5. Generate Markdown Report
        let md = `# WooCommerce Store Report for ${siteUrl}\n\n`;
        md += `**Generated**: ${new Date().toISOString()}\n`;
        md += `**WC Version**: ${summary.woocommerce ? summary.woocommerce.version : 'Unknown'}\n`;
        md += `**Currency**: ${summary.woocommerce ? summary.woocommerce.currency_symbol + ' (' + summary.woocommerce.currency + ')' : ''}\n`;
        md += `**HPOS**: ${summary.woocommerce && summary.woocommerce.hpos_enabled ? '✅ Enabled' : '❌ Disabled'} (${summary.woocommerce ? summary.woocommerce.authoritative_source : ''})\n\n`;

        md += `## 📦 Products Overview\n`;
        md += `- **Total Products**: ${summary.products ? summary.products.total : 0}\n`;
        if (summary.products && summary.products.by_status) {
            md += `- **Published**: ${summary.products.by_status.publish || 0} | **Draft**: ${summary.products.by_status.draft || 0} | **Trash**: ${summary.products.by_status.trash || 0}\n`;
        }
        if (summary.products && summary.products.by_stock) {
            md += `- **In Stock**: ${summary.products.by_stock.instock || 0} | **Out of Stock**: ${summary.products.by_stock.outofstock || 0} | **On Backorder**: ${summary.products.by_stock.onbackorder || 0}\n\n`;
        }

        md += `## 📋 Orders Overview\n`;
        md += `- **Total Orders**: ${summary.orders ? summary.orders.total : 0}\n`;
        if (summary.orders && summary.orders.by_status) {
            md += `\n| Status | Count |\n| :--- | :--- |\n`;
            Object.keys(summary.orders.by_status).forEach(st => {
                const item = summary.orders.by_status[st];
                md += `| **${item.label || st}** | ${item.count} |\n`;
            });
            md += `\n`;
        }

        md += `## 💳 Payment Gateways\n`;
        if (summary.payment_gateways && Array.isArray(summary.payment_gateways.active_gateways)) {
            md += `- **Active Gateways (${summary.payment_gateways.active_count}/${summary.payment_gateways.total_installed})**: `;
            md += summary.payment_gateways.active_gateways.map(g => `\`${g.title || g.id}\``).join(', ') + `\n\n`;
        }

        md += `## 🚚 Shipping\n`;
        md += `- **Configured Zones**: ${summary.shipping_zones ? summary.shipping_zones.configured_zones : 0}\n\n`;

        md += `## 👥 Customers\n`;
        md += `- **Registered Customers**: ${summary.customers ? summary.customers.total_registered : 0}\n`;

        writeText(path.join(wcDir, 'summary.md'), md);
        console.log('✅ Saved WooCommerce store data to ./woocommerce/ (summary.json, settings.json, products.json, orders.json, summary.md)');
    } catch (err) {
        console.error('❌ Failed to pull WooCommerce data:', err.message);
    }
}

async function pullCapabilities() {
    console.log('⏳ Pulling Capabilities & Schema Discovery...');
    try {
        const data = await makeRequest('/capabilities');
        writeJson(path.join(outputDir, 'capabilities.json'), data);

        let md = `# API Capabilities & Dynamic Schema for ${siteUrl}\n\n`;
        md += `**Plugin**: ${data.plugin ? data.plugin.name : 'WP Agent Bridge'} (v${data.plugin ? data.plugin.version : '?'})\n`;
        md += `**Generated**: ${new Date().toISOString()}\n`;
        md += `**REST Base**: \`${apiBase}\`\n\n`;
        md += `## Modules Catalog\n\n`;
        md += `| Module | Status | Endpoints | Description |\n`;
        md += `| :--- | :--- | :--- | :--- |\n`;

        if (Array.isArray(data.modules)) {
            data.modules.forEach(m => {
                const status = m.enabled ? '✅ Enabled' : '❌ Disabled';
                const eps = (m.endpoints || []).map(e => `\`${e.path || e}\``).join('<br>');
                md += `| **${m.label || m.id}** | ${status} | ${eps} | ${m.description || ''} |\n`;
            });
        }

        writeText(path.join(outputDir, 'capabilities.md'), md);
        console.log('✅ Saved Capabilities catalog to ./capabilities.json & capabilities.md');
    } catch (err) {
        console.error('❌ Failed to pull capabilities:', err.message);
    }
}

// Main Runner
async function run() {
    console.log(`\n🚀 WP Agent Bridge CLI connecting to: ${siteUrl}`);
    console.log(`📁 Target Directory: ${outputDir}\n`);

    ensureDir(outputDir);

    switch (command) {
        case 'pull:capabilities':
            await pullCapabilities();
            break;
        case 'pull:system':
            await pullSystem();
            break;
        case 'pull:scheduler':
            await pullScheduler();
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
        case 'pull:meta':
            await pullMeta();
            break;
        case 'pull:woocommerce':
        case 'pull:wc':
            await pullWooCommerce();
            break;
        case 'pull:logs':
            await pullLogs();
            break;
        case 'pull:all':
        default:
            await pullCapabilities();
            await pullSystem();
            await pullScheduler();
            await pullTheme();
            await pullElementor();
            await pullSnippets();
            await pullFlowmattic();
            await pullAnalytics();
            await pullMeta();
            await pullWooCommerce();
            await pullLogs();
            break;
    }

    console.log('\n✨ Done! All requested site data is available locally.\n');
}

run();
