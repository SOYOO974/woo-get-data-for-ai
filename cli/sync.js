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
const codePath = getArg('path', 'CODE_PATH', '');
const command = args[0] && !args[0].startsWith('--') ? args[0] : 'pull:all';

if (!siteUrl || !token) {
    console.error('\x1b[31m%s\x1b[0m', 'Error: Missing SITE_URL or AGENT_BRIDGE_TOKEN.');
    console.log('Usage: node sync.js [command] --site=https://example.com --token=YOUR_TOKEN --out=./synced-site-data [--status=active|inactive|all] [--path=plugins/my-plugin]');
    console.log('Commands: pull:all, pull:capabilities, pull:skill, pull:system, pull:scheduler, pull:theme, pull:code, pull:checksums, pull:elementor, pull:snippets, pull:flowmattic, pull:analytics, pull:meta, pull:woocommerce, pull:content, pull:performance, pull:pmpro, pull:masterstudy, pull:logs, purge:cache');
    process.exit(1);
}

// Base REST API URL
const apiBase = `${siteUrl}/wp-json/agent-bridge/v1`;

function makeRequest(endpoint, method = 'GET', data = null) {
    return new Promise((resolve, reject) => {
        const fullUrl = `${apiBase}${endpoint}`;
        const urlObj = new URL(fullUrl);
        const client = urlObj.protocol === 'https:' ? https : http;

        const payload = data ? JSON.stringify(data) : null;

        const headers = {
            'Authorization': `Bearer ${token}`,
            'User-Agent': 'WP-Agent-Bridge-CLI/1.0',
            'Accept': 'application/json'
        };

        if (payload) {
            headers['Content-Type'] = 'application/json';
            headers['Content-Length'] = Buffer.byteLength(payload);
        }

        const options = {
            hostname: urlObj.hostname,
            port: urlObj.port || (urlObj.protocol === 'https:' ? 443 : 80),
            path: urlObj.pathname + urlObj.search,
            method: method,
            headers: headers
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
        if (payload) {
            req.write(payload);
        }
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
    console.log('⏳ Pulling System & Environment (including Database Health)...');
    try {
        const sysDir = path.join(outputDir, 'system');
        ensureDir(sysDir);

        const data = await makeRequest('/system');
        writeJson(path.join(sysDir, 'system.json'), data);

        // Fetch Database Health
        let dbData = null;
        try {
            dbData = await makeRequest('/system/database');
            writeJson(path.join(sysDir, 'database.json'), dbData);
        } catch (dbErr) {
            console.warn('  ⚠️ Could not fetch /system/database:', dbErr.message);
        }

        // Fetch Mail Diagnostic
        let mailData = null;
        try {
            mailData = await makeRequest('/system/mail');
            writeJson(path.join(sysDir, 'mail.json'), mailData);
        } catch (mailErr) {
            console.warn('  ⚠️ Could not fetch /system/mail:', mailErr.message);
        }

        // Fetch Security Audit
        let secData = null;
        try {
            secData = await makeRequest('/system/security');
            writeJson(path.join(sysDir, 'security.json'), secData);
        } catch (secErr) {
            console.warn('  ⚠️ Could not fetch /system/security:', secErr.message);
        }

        // Generate Markdown summary
        let md = `# System Report for ${siteUrl}\n\n`;
        md += `**Generated**: ${new Date().toISOString()}\n\n`;
        md += `## Server & Environment\n`;
        md += `- **PHP**: ${data.system.php_version} (SAPI: ${data.system.php_sapi})\n`;
        md += `- **Web Server**: ${data.system.web_server}\n`;
        md += `- **MySQL**: ${data.system.mysql_version}\n`;
        md += `- **Memory Limit**: ${data.system.memory_limit} (WP: ${data.system.wp_memory_limit})\n\n`;

        if (dbData) {
            md += `## 🗄️ Database Health & Autoload\n`;
            md += `- **Total DB Size**: ${dbData.database ? dbData.database.total_size : 'Unknown'} (${dbData.database ? dbData.database.tables_count : 0} tables)\n`;
            md += `- **Autoload Size**: ${dbData.autoload_health ? dbData.autoload_health.total_size : 'Unknown'} across ${dbData.autoload_health ? dbData.autoload_health.total_options_count : 0} options\n`;
            md += `- **Autoload Status**: ${dbData.autoload_health && dbData.autoload_health.status === 'healthy' ? '🟢 Healthy (< 800 KB)' : '🔴 ' + (dbData.autoload_health ? dbData.autoload_health.status.toUpperCase() : '')}\n`;
            if (dbData.autoload_health && dbData.autoload_health.alert) {
                md += `> ⚠️ **${dbData.autoload_health.alert}**\n\n`;
            }
            if (dbData.caching) {
                md += `- **Object Cache**: ${dbData.caching.external_object_cache ? '🟢 Active' : '⚪ Not Active'}\n`;
            }
            if (dbData.top_tables && dbData.top_tables.length > 0) {
                md += `\n### Top Heavy Tables\n\n`;
                md += `| Table | Rows | Total Size | Engine |\n|---|---|---|---|\n`;
                dbData.top_tables.slice(0, 10).forEach(t => {
                    md += `| \`${t.table}\` | ${t.rows.toLocaleString()} | ${t.total_human} | ${t.engine} |\n`;
                });
                md += `\n`;
            }
            if (dbData.autoload_health && dbData.autoload_health.top_heavy_options && dbData.autoload_health.top_heavy_options.length > 0) {
                md += `### Top Autoloaded Options\n\n`;
                md += `| Option Name | Size |\n|---|---|\n`;
                dbData.autoload_health.top_heavy_options.slice(0, 8).forEach(o => {
                    md += `| \`${o.option_name}\` | ${o.size_human} |\n`;
                });
                md += `\n`;
            }
        }

        if (mailData) {
            md += `## 📧 Transactional Emails & SMTP\n`;
            md += `- **Active Plugin**: ${mailData.active_plugin}\n`;
            md += `- **Transport Provider**: \`${mailData.transport_provider}\` (${mailData.is_authenticated ? '🟢 Authenticated' : '🔴 Unauthenticated'})\n`;
            md += `- **Health**: ${mailData.health === 'healthy' ? '🟢 Healthy' : (mailData.health === 'warning' ? '🔴 Warning' : '🟡 Notice')}\n`;
            if (mailData.alerts && mailData.alerts.length > 0) {
                mailData.alerts.forEach(al => {
                    md += `> ⚠️ **${al}**\n\n`;
                });
            }
            if (mailData.recent_failures && mailData.recent_failures.length > 0) {
                md += `### Recent Delivery Failures\n\n`;
                md += `| ID | Date | Recipient | Error |\n|---|---|---|---|\n`;
                mailData.recent_failures.slice(0, 5).forEach(f => {
                    md += `| ${f.id} | ${f.date} | \`${f.recipient || '-'}\` | ${f.error || '-'} |\n`;
                });
                md += `\n`;
            }
        }

        if (secData) {
            md += `## 🛡️ Security Hardening Audit\n`;
            md += `- **Health Status**: ${secData.health === 'hardened' ? '🟢 Hardened' : (secData.health === 'needs_attention' ? '🔴 Needs Attention' : '🟡 Moderate')}\n`;
            md += `- **DISALLOW_FILE_EDIT**: ${secData.constants && secData.constants.disallow_file_edit ? '🟢 Enabled' : '🔴 Disabled'}\n`;
            md += `- **WP_DEBUG_DISPLAY**: ${secData.constants && secData.constants.wp_debug_display ? '🔴 Enabled (Risk in production)' : '🟢 Disabled'}\n`;
            md += `- **SSL Enforcement**: ${secData.constants && secData.constants.is_ssl ? '🟢 HTTPS Active' : '🔴 No SSL'}\n`;
            md += `- **XML-RPC**: ${secData.attack_surface && secData.attack_surface.xmlrpc_enabled ? '🟡 Active' : '🟢 Disabled'}\n`;
            if (secData.recommendations && secData.recommendations.length > 0) {
                md += `\n### Security Recommendations\n`;
                secData.recommendations.forEach(r => {
                    md += `- [${r.severity.toUpperCase()}] **${r.issue}**: ${r.solution}\n`;
                });
                md += `\n`;
            }
        }

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

        if (data.plugins_summary) {
            md += `## Plugins Overview\n`;
            md += `- **Active Plugins**: ${data.plugins_summary.active_count}\n`;
            md += `- **Inactive Plugins**: ${data.plugins_summary.inactive_count}\n`;
            md += `- **Total Installed**: ${data.plugins_summary.total_installed}\n`;
            md += `- **Must-Use Plugins**: ${data.plugins_summary.must_use_count}\n\n`;
        }

        md += `## Plugins (${data.plugins_count})\n`;
        (data.plugins || []).forEach(p => {
            md += `- **${p.name}** (v${p.version})${p.is_active ? '' : ' [Inactive]'}${p.update_available ? ' ⚠️ [Update available: ' + p.new_version + ']' : ''}\n`;
        });

        writeText(path.join(outputDir, 'system-report.md'), md);
        console.log('✅ System & Database health report saved to system-report.md');
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
        const query = `?status=${encodeURIComponent(statusFilter)}`;
        let data;
        try {
            data = await makeRequest(`/snippets${query}`);
        } catch (e) {
            data = await makeRequest(`/wpcode/snippets${query}`);
        }
        let activeSaved = 0;
        let inactiveSaved = 0;

        (data.snippets || []).forEach(snip => {
            const ext = (snip.code_type === 'javascript' || snip.code_type === 'js') ? 'js' : (snip.code_type === 'css' ? 'css' : (snip.code_type === 'html' ? 'html' : 'php'));
            const cleanTitle = (snip.title || 'snippet').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
            const filename = `${snip.id}-${cleanTitle}.${ext}`;
            const isAct = (snip.is_active || snip.status === 'active');
            const subfolder = isAct ? 'active' : 'inactive';

            if (isAct) {
                activeSaved++;
            } else {
                inactiveSaved++;
            }

            const tagsStr = Array.isArray(snip.tags) && snip.tags.length > 0 ? snip.tags.join(', ') : 'none';
            let header = `/**\n * Snippet: ${snip.title}\n * Source: ${snip.source_plugin}\n * Status: ${snip.status}\n * Type: ${snip.code_type}\n * Location: ${snip.location}\n * Priority: ${snip.priority}\n * Description: ${snip.description || 'none'}\n * Tags: ${tagsStr}\n * Admin URL: ${snip.admin_edit_url || 'n/a'}\n * Modified: ${snip.modified_at}\n */\n\n`;
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

                if (asData.retention) {
                    const ret = asData.retention;
                    asMd += `## Retention & Hygiene Policy\n`;
                    asMd += `- **Retention Period**: **${ret.retention_period_days} days** (${ret.is_default ? 'Default' : 'Custom'})\n`;
                    asMd += `- **Batch Size**: ${ret.cleanup_batch_size} actions / batch\n`;
                    if (ret.alert_bloat) {
                        asMd += `- **Bloat Alert**: ⚠️ **HIGH RETENTION BLOAT** — ${ret.recommendation}\n`;
                    }
                    asMd += `\n`;
                }

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

        // 2b. Pull Dedicated Shipping Logistics
        console.log('   🚚 Fetching Shipping Zones, Locations & Matrix Rules...');
        let shippingData = null;
        try {
            shippingData = await makeRequest('/woocommerce/shipping');
            writeJson(path.join(wcDir, 'shipping.json'), shippingData);
        } catch (shipErr) {
            console.warn('  ⚠️ Could not fetch /woocommerce/shipping:', shipErr.message);
        }

        // 3. Pull Products
        console.log(`   🛍️  Fetching Products (status: ${statusFilter === 'all' ? 'publish' : statusFilter})...`);
        const prodStatus = statusFilter === 'all' ? 'publish' : statusFilter;
        const products = await makeRequest(`/woocommerce/products?status=${encodeURIComponent(prodStatus)}&per_page=50`);
        writeJson(path.join(wcDir, 'products.json'), products);

        // 4. Pull Recent Orders (anonymized)
        console.log('   📋 Fetching Recent Orders (anonymized)...');
        const orders = await makeRequest('/woocommerce/orders?per_page=20');
        writeJson(path.join(wcDir, 'orders.json'), orders);

        // 4b. Pull Promotional Coupons
        console.log('   🎟️  Fetching Promotional Coupons...');
        let couponsData = null;
        try {
            couponsData = await makeRequest('/woocommerce/coupons?per_page=50');
            writeJson(path.join(wcDir, 'coupons.json'), couponsData);
        } catch (coupErr) {
            console.warn('  ⚠️ Could not fetch /woocommerce/coupons:', coupErr.message);
        }

        // 5. Pull Sales Analytics
        console.log('   📈 Fetching WooCommerce Native Sales Analytics...');
        let salesData = null;
        try {
            salesData = await makeRequest('/woocommerce/analytics/sales?range=last_30_days');
            writeJson(path.join(wcDir, 'analytics-sales.json'), salesData);

            if (salesData && salesData.kpis) {
                let salesMd = `# WooCommerce Sales Performance Report (${salesData.period ? salesData.period.label : 'Last 30 Days'})\n\n`;
                salesMd += `**Currency**: ${salesData.currency_symbol} (${salesData.currency})  \n`;
                salesMd += `**Engine**: \`${salesData.engine}\`  \n\n`;
                salesMd += `## 📊 Executive KPIs\n\n`;
                salesMd += `| KPI | Current Period | Previous Period | Growth % |\n|---|---|---|---|\n`;
                const g = salesData.growth_vs_previous || {};
                salesMd += `| **Net Sales** | **${salesData.kpis.net_sales} ${salesData.currency_symbol}** | ${g.previous_net_sales || 0} ${salesData.currency_symbol} | ${g.net_sales_growth_pct >= 0 ? '+' : ''}${g.net_sales_growth_pct}% |\n`;
                salesMd += `| **Gross Sales** | ${salesData.kpis.gross_sales} ${salesData.currency_symbol} | ${g.previous_gross_sales || 0} ${salesData.currency_symbol} | ${g.gross_sales_growth_pct >= 0 ? '+' : ''}${g.gross_sales_growth_pct}% |\n`;
                salesMd += `| **Paid Orders** | **${salesData.kpis.orders_count}** | ${g.previous_orders_count || 0} | ${g.orders_count_growth_pct >= 0 ? '+' : ''}${g.orders_count_growth_pct}% |\n`;
                salesMd += `| **Average Order Value (AOV)** | **${salesData.kpis.average_order_value} ${salesData.currency_symbol}** | ${g.previous_aov || 0} ${salesData.currency_symbol} | ${g.aov_growth_pct >= 0 ? '+' : ''}${g.aov_growth_pct}% |\n`;
                salesMd += `| **Items Sold** | ${salesData.kpis.items_sold} units | - | - |\n`;
                salesMd += `| **Refunds** | ${salesData.kpis.refunds_count} (${salesData.kpis.refunded_amount} ${salesData.currency_symbol}) | - | - |\n\n`;

                writeText(path.join(wcDir, 'sales-report.md'), salesMd);
            }
        } catch (salesErr) {
            console.warn('  ⚠️ Could not fetch /woocommerce/analytics/sales:', salesErr.message);
        }

        // 6. Pull Top Performers
        console.log('   🏆 Fetching Top Selling Products & Coupons...');
        try {
            const topData = await makeRequest('/woocommerce/analytics/top-performers?limit=15&range=last_30_days');
            writeJson(path.join(wcDir, 'top-performers.json'), topData);
        } catch (topErr) {
            console.warn('  ⚠️ Could not fetch /woocommerce/analytics/top-performers:', topErr.message);
        }

        // 7. Pull Stock Analytics
        console.log('   📊 Fetching Stock Valuation & Inventory Health...');
        try {
            const stockData = await makeRequest('/woocommerce/analytics/stock');
            writeJson(path.join(wcDir, 'stock-analytics.json'), stockData);

            if (stockData && stockData.summary) {
                let stockMd = `# WooCommerce Stock & Inventory Valuation Report\n\n`;
                stockMd += `**Total Inventory Value**: **${stockData.summary.total_inventory_value} ${stockData.currency_symbol}**  \n`;
                stockMd += `**Total Units in Stock**: ${(stockData.summary.total_units_in_stock || 0).toLocaleString()} units across ${stockData.summary.managed_stock_products} managed products  \n`;
                stockMd += `**Out of Stock Count**: ${stockData.summary.out_of_stock_count} products  \n`;
                stockMd += `**Low Stock Alerts**: ${stockData.summary.low_stock_count} products (threshold: <= ${stockData.summary.low_stock_threshold})  \n\n`;

                if (stockData.low_stock_alerts && stockData.low_stock_alerts.length > 0) {
                    stockMd += `### ⚠️ Low Stock Alerts (Action Required)\n\n`;
                    stockMd += `| Product | SKU | Units Remaining | Price |\n|---|---|---|---|\n`;
                    stockData.low_stock_alerts.forEach(p => {
                        stockMd += `| [${p.name}](${p.edit_url}) | \`${p.sku || '-'}\` | **${p.stock_quantity}** | ${p.price} ${stockData.currency_symbol} |\n`;
                    });
                    stockMd += `\n`;
                }

                if (stockData.dormant_stock_90d && stockData.dormant_stock_90d.length > 0) {
                    stockMd += `### 💤 Dormant Stock (0 sales in last 90 days)\n\n`;
                    stockMd += `| Product | SKU | Stock Qty | Locked Capital |\n|---|---|---|---|\n`;
                    stockData.dormant_stock_90d.forEach(p => {
                        stockMd += `| [${p.name}](${p.edit_url}) | \`${p.sku || '-'}\` | ${p.stock_quantity} | **${p.locked_capital} ${stockData.currency_symbol}** |\n`;
                    });
                    stockMd += `\n`;
                }

                writeText(path.join(wcDir, 'stock-health.md'), stockMd);
            }
        } catch (stockErr) {
            console.warn('  ⚠️ Could not fetch /woocommerce/analytics/stock:', stockErr.message);
        }

        // 8. Pull Webhooks
        console.log('   🔗 Fetching WooCommerce Webhooks...');
        let webhooksData = null;
        try {
            webhooksData = await makeRequest('/woocommerce/webhooks');
            writeJson(path.join(wcDir, 'webhooks.json'), webhooksData);
        } catch (whErr) {
            console.warn('  ⚠️ Could not fetch /woocommerce/webhooks:', whErr.message);
        }

        // 9. Generate Markdown Report
        let md = `# WooCommerce Store Report for ${siteUrl}\n\n`;
        md += `**Generated**: ${new Date().toISOString()}\n`;
        md += `**WC Version**: ${summary.woocommerce ? summary.woocommerce.version : 'Unknown'}\n`;
        md += `**HPOS**: ${summary.woocommerce && summary.woocommerce.hpos_enabled ? '✅ Enabled' : '❌ Disabled'} (${summary.woocommerce ? summary.woocommerce.authoritative_source : ''})\n`;
        if (summary.woocommerce && summary.woocommerce.performance_features) {
            const pf = summary.woocommerce.performance_features;
            md += `- **HPOS Data Caching**: ${pf.hpos_data_caching && pf.hpos_data_caching.enabled ? '✅ Enabled' : '⚪ Disabled'}\n`;
            md += `- **Deferred Transactional Emails**: ${pf.deferred_transactional_emails && pf.deferred_transactional_emails.enabled ? '✅ Enabled' : '⚪ Disabled'}\n`;
            md += `- **Checkout Rate Limiting**: ${pf.checkout_rate_limiting && pf.checkout_rate_limiting.enabled ? '✅ Enabled' : '⚪ Disabled'}\n`;
            md += `- **HPOS Full-Text Search**: ${pf.hpos_full_text_search && pf.hpos_full_text_search.enabled ? '✅ Enabled' : '⚪ Disabled (Experimental)'}\n`;
            if (pf.recommendations && pf.recommendations.length > 0) {
                md += `\n> 💡 **Performance Recommendations**:\n`;
                pf.recommendations.forEach(r => {
                    md += `> - **[${r.priority.toUpperCase()}] ${r.title}**: ${r.description}\n`;
                });
            }
        }
        md += `\n`;

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

            if (summary.orders.health_analysis) {
                const h = summary.orders.health_analysis;
                md += `### 🩺 Order Volume & Hygiene Analysis\n\n`;
                md += `- **Cancelled Orders**: ${h.cancelled_count} (**${h.cancelled_ratio_percent}%** of total)${h.alert_high_cancellations ? ' ⚠️ (High volume)' : ''}\n`;
                md += `- **Failed Orders**: ${h.failed_count} (${h.failed_ratio_percent}% of total)\n`;
                if (h.cancelled_unpaid_older_than_1y_estimate > 0) {
                    md += `- **Stale Abandoned Orders (> 1 year)**: ⚠️ **${h.cancelled_unpaid_older_than_1y_estimate} orders** (cluttering order/HPOS tables)\n`;
                }
                if (h.recommendation) {
                    md += `- **Recommendation**: ${h.recommendation}\n`;
                }
                md += `\n`;
            }
        }

        if (webhooksData && webhooksData.summary) {
            md += `## 🔗 Webhooks Integration Health\n`;
            md += `- **Total Webhooks**: ${webhooksData.summary.total} (Active: ${webhooksData.summary.active}, Disabled: ${webhooksData.summary.disabled})\n`;
            md += `- **Health Status**: ${webhooksData.summary.health === 'healthy' ? '🟢 Healthy' : '🔴 Warning'}\n`;
            if (webhooksData.summary.alert) {
                md += `> ⚠️ **${webhooksData.summary.alert}**\n\n`;
            }
            if (webhooksData.webhooks && webhooksData.webhooks.length > 0) {
                md += `| Name | Status | Topic | Failure Count |\n|---|---|---|---|\n`;
                webhooksData.webhooks.forEach(w => {
                    md += `| ${w.name} | ${w.status === 'active' ? '🟢 Active' : '⚪ ' + w.status} | \`${w.topic}\` | ${w.failure_count > 0 ? '⚠️ ' + w.failure_count : 0} |\n`;
                });
                md += `\n`;
            }
        }

        md += `## 💳 Payment Gateways\n`;
        if (summary.payment_gateways && Array.isArray(summary.payment_gateways.active_gateways)) {
            md += `- **Active Gateways (${summary.payment_gateways.active_count}/${summary.payment_gateways.total_installed})**: `;
            md += summary.payment_gateways.active_gateways.map(g => `\`${g.title || g.id}\``).join(', ') + `\n\n`;
        }

        md += `## 🚚 Shipping & Logistics\n`;
        if (shippingData && Array.isArray(shippingData.zones)) {
            md += `- **Total Zones**: ${shippingData.zones_count || shippingData.zones.length}\n\n`;
            md += `| Zone | Locations | Methods | Details |\n|---|---|---|---|\n`;
            for (const z of shippingData.zones) {
                const locCount = z.locations_count || (z.locations ? z.locations.length : 0);
                const locSummary = locCount > 0 ? `${locCount} location(s)` : 'All / Rest of World';
                const methodsList = (z.shipping_methods || []).map(m => {
                    let desc = m.title;
                    const fs = m.flexible_shipping || m.flexible_shipping_table_rate;
                    if (fs && fs.rules_count) {
                        desc += ` (${fs.rules_count} matrix rules${fs.is_pro ? ' [PRO]' : ''})`;
                    }
                    return `${m.enabled ? '🟢' : '⚪'} \`${m.id}\`: ${desc}`;
                }).join('<br>');
                md += `| **${z.zone_name}** (ID: ${z.id}) | ${locSummary} | ${(z.shipping_methods || []).length} method(s) | ${methodsList || '-'} |\n`;
            }
            md += `\n`;
        } else {
            md += `- **Configured Zones**: ${summary.shipping_zones ? summary.shipping_zones.configured_zones : 0}\n\n`;
        }

        md += `## 👥 Customers\n`;
        md += `- **Registered Customers**: ${summary.customers ? summary.customers.total_registered : 0}\n`;

        writeText(path.join(wcDir, 'summary.md'), md);
        console.log('✅ Saved WooCommerce store data to ./woocommerce/ (summary.json, settings.json, shipping.json, products.json, orders.json, analytics-sales.json, stock-analytics.json, webhooks.json, summary.md, sales-report.md, stock-health.md)');
    } catch (err) {
        console.error('❌ Failed to pull WooCommerce data:', err.message);
    }
}

async function pullContent() {
    console.log('⏳ Pulling WordPress Pages, Content & SEO...');
    try {
        const contentDir = path.join(outputDir, 'content');
        ensureDir(contentDir);

        // 1. Pull SEO Audit
        try {
            const seoAudit = await makeRequest('/content/seo-audit?include_posts=true&include_products=true&include_categories=true');
            writeJson(path.join(contentDir, 'seo-audit.json'), seoAudit);

            let seoMd = `# SEO Audit Report for ${siteUrl}\n\n`;
            seoMd += `**Generated**: ${seoAudit.generated_at || new Date().toISOString()}\n`;
            seoMd += `**SEO Provider**: ${seoAudit.seo_provider ? seoAudit.seo_provider.label + ' (' + seoAudit.seo_provider.provider + ')' : 'Native'}\n`;
            if (seoAudit.site_visibility) {
                seoMd += `**Site Visibility**: ${seoAudit.site_visibility.is_public ? '🟢 Public (Indexable)' : '🔴 Discouraged (Hidden from search engines)'}\n`;
                if (seoAudit.site_visibility.alert) {
                    seoMd += `> ⚠️ **${seoAudit.site_visibility.alert}**\n\n`;
                }
            }
            if (seoAudit.summary) {
                seoMd += `\n## 📊 Summary Metrics\n`;
                seoMd += `- **Total Items Audited**: ${seoAudit.summary.total_audited} (Pages, Posts, Products)\n`;
                if (seoAudit.summary.products_audited_count) {
                    seoMd += `- **Products Sampled**: ${seoAudit.summary.products_audited_count}\n`;
                }
                seoMd += `- **Meta Description Coverage**: ${seoAudit.summary.meta_description_coverage}\n`;
                seoMd += `- **OpenGraph Image Coverage**: ${seoAudit.summary.og_image_coverage}\n`;
                seoMd += `- **Pages/Products with Noindex**: ${seoAudit.summary.noindex_count}\n`;
                seoMd += `- **Missing Meta Description**: ${seoAudit.summary.missing_meta_desc_count}\n`;
                seoMd += `- **Weak or Missing Titles**: ${seoAudit.summary.weak_titles_count}\n`;
                seoMd += `- **Thin Content Pages/Products**: ${seoAudit.summary.thin_content_count}\n\n`;
            }

            if (seoAudit.issues && seoAudit.issues.noindex_pages && seoAudit.issues.noindex_pages.length > 0) {
                seoMd += `### 🚫 Pages & Products marked as Noindex\n\n`;
                seoMd += `| ID | Type | Title | Slug | Critical Warning |\n|---|---|---|---|---|\n`;
                seoAudit.issues.noindex_pages.forEach(p => {
                    seoMd += `| ${p.id} | ${p.post_type} | ${p.title} | \`${p.slug}\` | ${p.critical_warning || '-'} |\n`;
                });
                seoMd += `\n`;
            }

            if (seoAudit.categories_audit && seoAudit.categories_audit.missing_descriptions && seoAudit.categories_audit.missing_descriptions.length > 0) {
                seoMd += `### 🏷️ Categories Missing Description Text\n\n`;
                seoMd += `| ID | Taxonomy | Category Name | Slug | Products Count | Status |\n|---|---|---|---|---|---|\n`;
                seoAudit.categories_audit.missing_descriptions.slice(0, 30).forEach(c => {
                    const status = c.item_count > 0 ? '⚠️ High traffic collection without SEO text' : 'Empty collection';
                    seoMd += `| ${c.term_id} | \`${c.taxonomy}\` | [${c.name}](${c.url}) | \`${c.slug}\` | ${c.item_count} | ${status} |\n`;
                });
                seoMd += `\n`;
            }

            // Pull SEO Plugin Settings & Optimization Audit
            try {
                const seoSettings = await makeRequest('/content/seo/settings');
                writeJson(path.join(contentDir, 'seo-settings.json'), seoSettings);

                if (seoSettings.summary) {
                    seoMd += `## ⚙️ Global SEO Plugin Settings Audit\n\n`;
                    seoMd += `- **Provider**: ${seoSettings.provider ? seoSettings.provider.label : 'N/A'}\n`;
                    seoMd += `- **Health Score**: **${seoSettings.summary.health_score !== undefined ? seoSettings.summary.health_score + '%' : '100%'}**\n`;
                    seoMd += `- **Optimal Settings**: 🟢 ${seoSettings.summary.optimal_count || 0}\n`;
                    seoMd += `- **Critical Issues**: 🔴 ${seoSettings.summary.critical_count || 0}\n`;
                    seoMd += `- **Warnings**: 🟡 ${seoSettings.summary.warning_count || 0}\n`;
                    seoMd += `- **Notices**: ℹ️ ${seoSettings.summary.notice_count || 0}\n\n`;

                    if (Array.isArray(seoSettings.recommendations) && seoSettings.recommendations.length > 0) {
                        seoMd += `### 🛠️ SEO Settings Recommendations\n\n`;
                        seoMd += `| Severity | Check | Issue | Recommendation |\n|---|---|---|---|\n`;
                        seoSettings.recommendations.forEach(r => {
                            const icon = r.severity === 'critical' ? '🔴' : (r.severity === 'warning' ? '🟡' : 'ℹ️');
                            seoMd += `| ${icon} ${(r.severity || '').toUpperCase()} | **${r.label || r.key}** | ${r.issue || '-'} | ${r.recommendation || '-'} |\n`;
                        });
                        seoMd += `\n`;
                    }
                }
            } catch (settingsErr) {
                console.warn('  ⚠️ Failed to pull /content/seo/settings:', settingsErr.message);
            }

            writeText(path.join(contentDir, 'seo-audit.md'), seoMd);
        } catch (seoErr) {
            console.warn('  ⚠️ Failed to pull /content/seo-audit:', seoErr.message);
        }

        // 2. Pull Pages list
        const pagesData = await makeRequest('/content/pages?status=all&per_page=100');
        writeJson(path.join(contentDir, 'pages.json'), pagesData);

        let pagesMd = `# WordPress Pages Catalog (${pagesData.total || (pagesData.pages ? pagesData.pages.length : 0)})\n\n`;
        pagesMd += `**Generated**: ${new Date().toISOString()}\n`;
        pagesMd += `**SEO Plugin**: ${pagesData.seo_plugin ? pagesData.seo_plugin.label : 'N/A'}\n\n`;
        pagesMd += `| ID | Title | Slug | Status | Template | Editor | Roles | SEO Title | Noindex |\n`;
        pagesMd += `|---|---|---|---|---|---|---|---|---|\n`;

        if (Array.isArray(pagesData.pages)) {
            const pagesDetailDir = path.join(contentDir, 'pages');
            ensureDir(pagesDetailDir);

            for (const p of pagesData.pages) {
                const roles = (p.special_roles || []).map(r => `\`${r}\``).join(' ');
                const seoTitle = p.seo && p.seo.title ? p.seo.title.replace(/\|/g, '-') : '-';
                const noindex = p.seo && p.seo.is_noindex ? '🔴 Noindex' : '🟢 Index';
                pagesMd += `| ${p.id} | [${p.title}](${p.url}) | \`${p.slug}\` | \`${p.status}\` | \`${p.template}\` | ${p.editor_type} | ${roles || '-'} | ${seoTitle} | ${noindex} |\n`;

                // Fetch details for up to 15 key pages or all if under 15
                if (pagesData.pages.indexOf(p) < 15) {
                    try {
                        const detail = await makeRequest(`/content/page/${p.id}`);
                        writeJson(path.join(pagesDetailDir, `${p.id}-${p.slug}.json`), detail);
                    } catch (detErr) {
                        // ignore individual page fetch error
                    }
                }
            }
        }
        writeText(path.join(contentDir, 'pages.md'), pagesMd);

        // 3. Pull recent blog posts
        try {
            const postsData = await makeRequest('/content/posts?status=publish&per_page=50');
            writeJson(path.join(contentDir, 'posts.json'), postsData);
        } catch (postErr) {
            console.warn('  ⚠️ Failed to pull /content/posts:', postErr.message);
        }

        // 4. Pull Redirections & 404 logs
        try {
            const redirData = await makeRequest('/content/redirections?per_page=100');
            writeJson(path.join(contentDir, 'redirections.json'), redirData);

            let redirMd = `# URL Redirections Report for ${siteUrl}\n\n`;
            redirMd += `**Generated**: ${new Date().toISOString()}\n`;
            redirMd += `**Provider**: ${redirData.provider ? redirData.provider.label + ' (' + redirData.provider.key + ')' : 'None'}\n`;
            redirMd += `**Total Rules**: ${redirData.total || 0}\n\n`;

            if (redirData.rules && redirData.rules.length > 0) {
                redirMd += `### Active Redirection Rules (Sample)\n\n`;
                redirMd += `| Source URL | Target URL | HTTP Code | Hits | Last Access |\n|---|---|---|---|---|\n`;
                redirData.rules.slice(0, 50).forEach(r => {
                    redirMd += `| \`${r.source_url}\` | \`${r.target_url}\` | ${r.status_code || 301} | ${r.hits || 0} | ${r.last_accessed || '-'} |\n`;
                });
                redirMd += `\n`;
            }

            // Pull 404 logs if available
            try {
                const logs404 = await makeRequest('/content/redirections/404?per_page=50');
                writeJson(path.join(contentDir, '404-logs.json'), logs404);

                if (logs404.logs && logs404.logs.length > 0) {
                    redirMd += `### Recent 404 Errors (Top Hits)\n\n`;
                    redirMd += `| Requested URL | Hits | Referrer | Last Detected |\n|---|---|---|---|\n`;
                    logs404.logs.slice(0, 30).forEach(l => {
                        redirMd += `| \`${l.url}\` | ${l.hits || 1} | \`${l.referrer || '-'}\` | ${l.last_detected || '-'} |\n`;
                    });
                    redirMd += `\n`;
                }
            } catch (e404) {
                // 404 table not available or disabled
            }

            writeText(path.join(contentDir, 'redirections.md'), redirMd);
        } catch (redirErr) {
            console.warn('  ⚠️ Failed to pull /content/redirections:', redirErr.message);
        }

        console.log('✅ Saved WordPress pages, content trees, SEO audit, SEO plugin settings, and redirections to ./content/');
    } catch (err) {
        console.error('❌ Failed to pull content & SEO data:', err.message);
    }
}

async function pullLogs() {
    console.log('⏳ Pulling Error Logs & Crash Watch...');
    try {
        const logsDir = path.join(outputDir, 'logs');
        ensureDir(logsDir);

        // 1. Crash Watch: Aggregated recent fatal errors & exceptions
        try {
            const errorsData = await makeRequest('/logs/errors-summary?limit=25');
            writeJson(path.join(logsDir, 'errors-summary.json'), errorsData);

            let errMd = `# Crash Watch — PHP Error Summary for ${siteUrl}\n\n`;
            errMd += `**Generated**: ${new Date().toISOString()}\n`;
            errMd += `**Health Status**: ${errorsData.status === 'clean' ? '🟢 Clean (No recent fatal errors detected)' : '🔴 Issues Detected (' + errorsData.unique_issues_count + ' unique crash signatures)'}\n`;
            errMd += `**Scanned Log Sources**: ${(errorsData.scanned_sources || []).join(', ')}\n\n`;

            if (errorsData.recent_crashes && errorsData.recent_crashes.length > 0) {
                errMd += `## ⚠️ Recent Critical Crashes & Fatal Errors\n\n`;
                errMd += `| Component | File & Line | Occurrences | Last Seen | Error Excerpt |\n|---|---|---|---|---|\n`;
                errorsData.recent_crashes.forEach(e => {
                    const comp = `**${e.component_type}**: \`${e.component_name}\``;
                    const fileLoc = `\`${e.file}:${e.line || '?'}\``;
                    const excerpt = (e.raw_excerpt || '').replace(/\|/g, '-').slice(0, 120);
                    errMd += `| ${comp} | ${fileLoc} | **${e.occurrences}** | ${e.last_seen} | ${excerpt}... |\n`;
                });
                errMd += `\n`;
            } else {
                errMd += `> ✨ Zero PHP fatal errors or unhandled exceptions detected in recent logs.\n\n`;
            }

            writeText(path.join(logsDir, 'errors-summary.md'), errMd);
        } catch (errSummaryErr) {
            console.warn('  ⚠️ Failed to pull /logs/errors-summary:', errSummaryErr.message);
        }

        // 2. Discover available log sources
        try {
            const sourcesData = await makeRequest('/logs/sources');
            writeJson(path.join(logsDir, 'sources.json'), sourcesData);

            // Tail debug.log if available
            const hasDebugLog = Array.isArray(sourcesData) && sourcesData.some(s => s.source_type === 'wp_debug');
            if (hasDebugLog) {
                try {
                    const debugTail = await makeRequest('/logs/view?source=debug.log&lines=300');
                    if (debugTail && Array.isArray(debugTail.lines)) {
                        writeText(path.join(logsDir, 'debug.tail.log'), debugTail.lines.join('\n'));
                    }
                } catch (tailErr) {
                    // Ignore tail error
                }
            }
        } catch (srcErr) {
            console.warn('  ⚠️ Failed to pull /logs/sources:', srcErr.message);
        }

        console.log('✅ Saved Logs & Crash Watch report to ./logs/ (errors-summary.md, errors-summary.json, sources.json)');
    } catch (err) {
        console.error('❌ Failed to pull logs:', err.message);
    }
}

async function pullCode() {
    console.log('⏳ Pulling Plugins Code Tree & Diagnostics...');
    try {
        const codeDir = path.join(outputDir, 'code');
        ensureDir(codeDir);

        const filterParam = statusFilter !== 'all' ? `?status=${statusFilter}` : '?status=all';
        const data = await makeRequest(`/code/plugins${filterParam}`);
        writeJson(path.join(codeDir, 'plugins.json'), data);

        let md = `# Code & Plugins Tree for ${siteUrl}\n\n`;
        md += `**Generated**: ${new Date().toISOString()}\n`;
        md += `**Filter**: ${data.filter || statusFilter} (Active: ${data.active_count || 0}, Inactive: ${data.inactive_count || 0}, Total: ${data.total || 0})\n\n`;

        md += `## Standard Plugins (${(data.plugins || []).length})\n\n`;
        md += `| Plugin Name | Status | Version | File Count | Main File |\n| :--- | :---: | :---: | :---: | :--- |\n`;
        (data.plugins || []).forEach(p => {
            const statusBadge = p.is_active ? '🟢 Active' : '⚪ Inactive';
            md += `| **${p.name}** | ${statusBadge} | \`${p.version || '-'}\` | ${p.files_count || 0} | \`${p.plugin_file}\` |\n`;
        });

        if (Array.isArray(data.mu_plugins) && data.mu_plugins.length > 0) {
            md += `\n## Must-Use Plugins (MU-Plugins) (${data.mu_plugins.length})\n\n`;
            md += `| Name | Version | Size | File |\n| :--- | :---: | :---: | :--- |\n`;
            data.mu_plugins.forEach(mu => {
                md += `| **${mu.name}** | \`${mu.version || '-'}\` | ${mu.size_bytes} bytes | \`${mu.file}\` |\n`;
            });
        }

        writeText(path.join(codeDir, 'plugins.md'), md);
        console.log('✅ Saved Code & Plugins tree to ./code/plugins.json & plugins.md');

        // Check if a specific directory path was provided for checksums drift detection
        if (codePath) {
            console.log(`⏳ Pulling directory checksums for: ${codePath}...`);
            try {
                const checksumsData = await makeRequest(`/code/checksums?path=${encodeURIComponent(codePath)}&algo=md5`);
                writeJson(path.join(codeDir, 'checksums.json'), checksumsData);

                let cmd = `# Checksums Fingerprint for ${checksumsData.target || codePath}\n\n`;
                cmd += `**Generated**: ${new Date().toISOString()}\n`;
                cmd += `**Base Path**: \`${checksumsData.base_path || codePath}\`\n`;
                cmd += `**Total Files**: ${checksumsData.total_files || 0} (${Math.round((checksumsData.total_size_bytes || 0) / 1024)} KB)\n`;
                cmd += `**Algorithm**: ${checksumsData.algorithm || 'md5'}\n\n`;
                cmd += `| Relative File Path | Size | Modified At | Hash (${checksumsData.algorithm || 'md5'}) |\n| :--- | :---: | :---: | :--- |\n`;

                if (checksumsData.checksums) {
                    Object.entries(checksumsData.checksums).forEach(([file, meta]) => {
                        cmd += `| \`${file}\` | ${meta.size_bytes} B | ${meta.modified_at} | \`${meta.hash}\` |\n`;
                    });
                }

                writeText(path.join(codeDir, 'checksums.md'), cmd);
                console.log(`✅ Saved checksums fingerprint to ./code/checksums.json & checksums.md`);
            } catch (csErr) {
                console.warn(`  ⚠️ Could not fetch /code/checksums:`, csErr.message);
            }
        }
    } catch (err) {
        console.error('❌ Failed to pull code tree:', err.message);
    }
}

async function pullCapabilities() {
    console.log('⏳ Pulling Capabilities, Playbooks & Schema Discovery...');
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

        if (Array.isArray(data.playbooks) && data.playbooks.length > 0) {
            md += `\n## 🎯 Procedural Audit Playbooks (${data.playbooks.length} Active)\n\n`;
            data.playbooks.forEach((p, idx) => {
                md += `### ${idx + 1}. ${p.title}\n`;
                md += `**Description**: ${p.description}\n`;
                md += `**Intent Keywords**: \`${(p.intent_triggers || []).join('`, `')}\`\n\n`;
                md += `| Step | Action | Endpoint | Key Signals |\n`;
                md += `| :---: | :--- | :--- | :--- |\n`;
                (p.workflow || []).forEach(s => {
                    const paramsStr = s.params && Object.keys(s.params).length ? '?' + new URLSearchParams(s.params).toString() : '';
                    const sigs = s.key_signals ? s.key_signals.join(', ') : '-';
                    md += `| ${s.step} | **${s.action}** | \`${s.endpoint}${paramsStr}\` | ${sigs} |\n`;
                });
                md += `\n`;
            });
        }

        writeText(path.join(outputDir, 'capabilities.md'), md);
        console.log('✅ Saved Capabilities & Playbooks catalog to ./capabilities.json & capabilities.md');
    } catch (err) {
        console.error('❌ Failed to pull capabilities:', err.message);
    }
}

async function pullSkill() {
    console.log('⏳ Pulling Live AI Skill & Playbooks (.agents/skills/wp-agent-bridge/SKILL.md)...');
    try {
        const skillMd = await makeRequest('/capabilities?format=skill');
        const skillDir = path.join(process.cwd(), '.agents', 'skills', 'wp-agent-bridge');
        ensureDir(skillDir);
        writeText(path.join(skillDir, 'SKILL.md'), skillMd);
        writeText(path.join(outputDir, 'SKILL.md'), skillMd);
        console.log('✅ Live Skill & Playbooks saved to .agents/skills/wp-agent-bridge/SKILL.md & ./SKILL.md');
    } catch (err) {
        console.error('❌ Failed to pull skill:', err.message);
    }
}

async function pullPerformance() {
    console.log('⏳ Pulling Performance & Plugin Footprint Diagnostics...');
    try {
        const perfDir = path.join(outputDir, 'performance');
        ensureDir(perfDir);

        const [caching, templatesUrls, homeProfile, autoload, pluginsSummary] = await Promise.all([
            makeRequest('/performance/caching').catch(e => ({ error: e.message })),
            makeRequest('/performance/templates-urls').catch(e => ({ error: e.message })),
            makeRequest('/performance/profile?path=/&include_assets=true&include_queries=true').catch(e => ({ error: e.message })),
            makeRequest('/performance/autoload?limit=50').catch(e => ({ error: e.message })),
            makeRequest('/performance/plugins-summary?status=active').catch(e => ({ error: e.message }))
        ]);

        writeJson(path.join(perfDir, 'caching.json'), caching);
        writeJson(path.join(perfDir, 'templates-urls.json'), templatesUrls);
        writeJson(path.join(perfDir, 'profile-home.json'), homeProfile);
        writeJson(path.join(perfDir, 'autoload.json'), autoload);
        writeJson(path.join(perfDir, 'plugins-summary.json'), pluginsSummary);

        let md = `# ⚡ Site Performance & Plugin Footprint Report\n\n`;

        if (!caching.error) {
            md += `## 1. Caching & Optimization Infrastructure\n\n`;
            md += `- **Object Cache**: ${caching.object_cache && caching.object_cache.enabled ? '🟢 Active' : '🔴 Inactive'} (${caching.object_cache && caching.object_cache.recommendation ? caching.object_cache.recommendation : ''})\n`;
            md += `- **Page Cache Drop-in**: ${caching.page_cache && caching.page_cache.advanced_cache_dropin ? '🟢 advanced-cache.php present' : '🔴 Missing'}\n`;
            md += `- **Active Caching Engine**: **${caching.page_cache ? caching.page_cache.active_engine : 'Unknown'}**\n`;
            if (caching.wp_rocket && caching.wp_rocket.is_active) {
                const rk = caching.wp_rocket;
                md += `\n### WP Rocket Configuration (v${rk.version || 'unknown'})\n\n`;
                md += `- **Mobile Cache**: ${rk.mobile_cache && rk.mobile_cache.enabled ? 'Enabled' : 'Disabled'} (Separate files: ${rk.mobile_cache && rk.mobile_cache.separate_files ? 'Yes' : 'No'})\n`;
                md += `- **CSS Optimization**: Mode = **${rk.css ? rk.css.mode : 'unknown'}**, Minify = ${rk.css && rk.css.minify ? 'Yes' : 'No'}, Safelist = ${rk.css && rk.css.safelist ? rk.css.safelist.length : 0} rules\n`;
                md += `- **JavaScript Optimization**: Delay JS = **${rk.javascript && rk.javascript.delay_js ? 'Enabled' : 'Disabled'}**, Defer = ${rk.javascript && rk.javascript.defer ? 'Yes' : 'No'}, Minify = ${rk.javascript && rk.javascript.minify ? 'Yes' : 'No'}, Exclusions = ${rk.javascript && rk.javascript.delay_js_exclusions ? rk.javascript.delay_js_exclusions.length : 0} rules\n`;
                md += `- **Media Optimization**: Lazyload Images = ${rk.media && rk.media.lazyload_images ? 'Yes' : 'No'}, Iframes = ${rk.media && rk.media.lazyload_iframes ? 'Yes' : 'No'}, CSS BG = ${rk.media && rk.media.lazyload_css_bg ? 'Yes' : 'No'}, Image Dimensions = ${rk.media && rk.media.image_dimensions ? 'Yes' : 'No'}\n`;
            }
            if (caching.breeze && caching.breeze.is_active) {
                const bz = caching.breeze;
                md += `\n### Breeze Configuration (v${bz.version || 'unknown'})\n\n`;
                md += `- **Cache System**: ${bz.basic && bz.basic.cache_system ? '🟢 Enabled' : '🔴 Disabled'} (TTL: ${bz.basic ? bz.basic.cache_ttl_min : 1440} min, Gzip: ${bz.basic && bz.basic.gzip_compression ? 'Yes' : 'No'}, Browser Cache: ${bz.basic && bz.basic.browser_cache ? 'Yes' : 'No'})\n`;
                md += `- **File Optimization**: Minify HTML = ${bz.file_optimization && bz.file_optimization.minify_html ? 'Yes' : 'No'}, CSS = ${bz.file_optimization && bz.file_optimization.minify_css ? 'Yes' : 'No'}, JS = ${bz.file_optimization && bz.file_optimization.minify_js ? 'Yes' : 'No'}, Group CSS = ${bz.file_optimization && bz.file_optimization.group_css ? 'Yes' : 'No'}, Group JS = ${bz.file_optimization && bz.file_optimization.group_js ? 'Yes' : 'No'}\n`;
                md += `- **JavaScript Optimization**: Delay JS = **${bz.file_optimization && bz.file_optimization.delay_js ? 'Enabled' : 'Disabled'}**, Delayed Scripts = ${bz.file_optimization && bz.file_optimization.delayed_scripts ? bz.file_optimization.delayed_scripts.length : 0} rules, Defer JS = ${bz.file_optimization ? bz.file_optimization.defer_js_count : 0} rules\n`;
                md += `- **Varnish & Edge**: Auto-Purge = ${bz.varnish && bz.varnish.auto_purge ? 'Yes' : 'No'} (Server IP: ${bz.varnish ? bz.varnish.server_ip : '127.0.0.1'}), CDN Active = ${bz.cdn && bz.cdn.active ? 'Yes' : 'No'}\n`;
            }
            md += `\n`;
        }

        if (!templatesUrls.error && templatesUrls.templates) {
            md += `## 2. Strategic E-Commerce Archetype URLs\n\n`;
            md += `| Archetype | Resolved URL | Context |\n|---|---|---|\n`;
            Object.entries(templatesUrls.templates).forEach(([key, item]) => {
                md += `| **${item.label || key}** | [${item.url}](${item.url}) | ${item.type || key} |\n`;
            });
            md += `\n`;
        }

        if (!homeProfile.error && homeProfile.profile) {
            const p = homeProfile.profile;
            md += `## 3. Homepage Synthetic Benchmark & Server Metrics\n\n`;
            md += `- **TTFB**: **${p.ttfb_ms} ms**\n`;
            md += `- **Peak Memory**: **${p.memory_peak_mb} MB**\n`;
            md += `- **Total SQL Queries**: **${p.sql ? p.sql.total_queries : '-'}**\n`;
            md += `- **Enqueued Scripts**: **${p.assets ? p.assets.total_scripts : '-'}** | **Enqueued Stylesheets**: **${p.assets ? p.assets.total_styles : '-'}**\n\n`;

            if (p.pagespeed_audits) {
                const psa = p.pagespeed_audits;
                md += `### Native Core Web Vitals & Frontend Signals\n\n`;
                const domIcon = psa.dom_health ? (psa.dom_health.total_nodes > 1400 ? '🔴' : (psa.dom_health.total_nodes > 800 ? '🟡' : '🟢')) : '🟢';
                const domNodes = psa.dom_health ? psa.dom_health.total_nodes : (psa.dom_nodes_count || 0);
                md += `- **DOM Elements Count**: ${domIcon} **${domNodes} nodes** ${domNodes > 1400 ? '(Critical DOM bloat)' : ''}\n`;
                if (psa.dom_health && psa.dom_health.elementor_nodes_count > 0) {
                    md += `- **Elementor DOM Footprint**: **${psa.dom_health.elementor_nodes_count} nodes** (${psa.dom_health.elementor_percent}% of total DOM)\n`;
                }
                const missingDims = psa.cls_image_dimensions ? psa.cls_image_dimensions.missing_dimensions_count : (psa.images_missing_dimensions || 0);
                md += `- **Missing Image Dimensions (CLS)**: ${missingDims > 0 ? `⚠️ **${missingDims} images** without width/height attributes` : '✅ All images have dimensions'}\n`;
                if (psa.image_formats) {
                    const legacyCount = psa.image_formats.legacy_formats_count || 0;
                    md += `- **Legacy Image Formats (.png/.jpg)**: ${legacyCount > 0 ? `⚠️ **${legacyCount} legacy images** (recommend WebP/AVIF: ~30-70% bandwidth savings)` : '✅ Modern formats (WebP/AVIF) used'}\n`;
                }
                if (psa.google_fonts && psa.google_fonts.detected) {
                    md += `- **Google Fonts**: ${psa.google_fonts.missing_swap ? '⚠️ **display=swap missing** (risk of FOIT/blank text on mobile)' : '✅ Loaded with display=swap'}\n`;
                }
                if (psa.render_blocking_in_head) {
                    const rbCount = psa.render_blocking_in_head.render_blocking_count || 0;
                    md += `- **Render-blocking Scripts in <head>**: ${rbCount > 0 ? `⚠️ **${rbCount} render-blocking script(s)**` : '✅ Zero render-blocking scripts in head'}\n`;
                }
                if (psa.core_bloat) {
                    const bloatCount = psa.core_bloat.detected_bloat_count || 0;
                    md += `- **WordPress Core Bloat Scripts**: ${bloatCount > 0 ? `⚠️ **${bloatCount} core script(s)** (${(psa.core_bloat.detected_handles || []).join(', ')})` : '✅ Core bloat cleaned up'}\n`;
                }
                if (psa.woocommerce_cart_fragments && psa.woocommerce_cart_fragments.active) {
                    md += `- **WooCommerce Cart Fragments**: ⚠️ **wc-cart-fragments AJAX polling active** (slows non-cart pages)\n`;
                }
            }

            if (p.sql && p.sql.by_component && Object.keys(p.sql.by_component).length > 0) {
                md += `### SQL Queries by Component\n\n`;
                md += `| Component | Queries | Total Time (ms) |\n|---|---|---|\n`;
                Object.entries(p.sql.by_component).forEach(([comp, data]) => {
                    md += `| **${comp}** | ${data.count} | ${data.total_time_ms} ms |\n`;
                });
                md += `\n`;
            }
        }

        if (!autoload.error) {
            const statusIcon = autoload.status === 'good' ? '🟢' : (autoload.status === 'warning' ? '🟡' : '🔴');
            md += `## 4. wp_options Autoload Footprint\n\n`;
            md += `- **Health Status**: ${statusIcon} **${(autoload.status || 'unknown').toUpperCase()}**\n`;
            md += `- **Total Autoloaded Options**: ${autoload.total_options}\n`;
            md += `- **Total Autoload Size**: **${autoload.total_size_kb} KB** (Recommended limit: < ${autoload.recommended_max_kb} KB)\n`;
            if (autoload.alert) {
                md += `> ⚠️ **Alert**: ${autoload.alert}\n\n`;
            }

            if (autoload.by_component && Object.keys(autoload.by_component).length > 0) {
                md += `\n### Autoload by Plugin / Component\n\n`;
                md += `| Component | Options Count | Total Size (KB) |\n|---|---|---|\n`;
                Object.entries(autoload.by_component).forEach(([comp, data]) => {
                    md += `| **${comp}** | ${data.count} | ${data.total_kb} KB |\n`;
                });
                md += `\n`;
            }

            if (Array.isArray(autoload.top_heavy_options) && autoload.top_heavy_options.length > 0) {
                md += `### Top Heaviest Autoload Options\n\n`;
                md += `| Option Name | Component | Size (KB) |\n|---|---|---|\n`;
                autoload.top_heavy_options.slice(0, 15).forEach(opt => {
                    md += `| \`${opt.option_name}\` | ${opt.component} | ${opt.size_kb} KB |\n`;
                });
                md += `\n`;
            }
        }

        if (!pluginsSummary.error && Array.isArray(pluginsSummary.plugins)) {
            md += `## 5. Active Plugins Database Footprint\n\n`;
            md += `| Plugin | Slug | Version | DB Tables | DB Size (KB) | DB Rows |\n|---|---|---|---|---|---|\n`;
            pluginsSummary.plugins.forEach(p => {
                md += `| **${p.name}** | \`${p.slug}\` | v${p.version} | ${p.tables_count} | ${p.db_size_kb} KB | ${p.db_rows.toLocaleString()} |\n`;
            });
            md += `\n`;
        }

        writeText(path.join(perfDir, 'performance-report.md'), md);
        console.log('✅ Performance & Autoload audit saved to ./performance/performance-report.md');
    } catch (err) {
        console.error('❌ Failed to pull performance:', err.message);
    }
}

async function pullPmpro() {
    console.log('⏳ Pulling Paid Memberships Pro (PMPro)...');
    try {
        const pmproDir = path.join(outputDir, 'pmpro');
        ensureDir(pmproDir);

        const levels = await makeRequest('/pmpro/levels');
        writeJson(path.join(pmproDir, 'levels.json'), levels);

        if (levels && levels.levels && Array.isArray(levels.levels)) {
            console.log(`  ✓ Successfully fetched ${levels.levels.length} PMPro levels.`);
            let lmd = `# Paid Memberships Pro — Levels Overview\n\n`;
            lmd += `**Total Levels**: ${levels.total_levels || levels.levels.length}\n`;
            lmd += `**Active Subscriptions**: ${levels.total_active_memberships || 0}\n\n`;
            lmd += `| ID | Name | Duration | Price / Billing | Active Members | Anomalies |\n`;
            lmd += `|---|---|---|---|---|---|\n`;
            levels.levels.forEach(l => {
                const anomalyBadge = l.duration_anomaly ? '⚠️ ' + l.duration_anomaly_description : '🟢 OK';
                lmd += `| \`${l.id}\` | **${l.name}** | ${l.duration_text || '-'} | ${l.billing_type || '-'} | ${l.active_members_count || 0} | ${anomalyBadge} |\n`;
            });
            writeText(path.join(pmproDir, 'levels.md'), lmd);
        }

        try {
            const members = await makeRequest('/pmpro/members?status=active&per_page=50');
            writeJson(path.join(pmproDir, 'members_active.json'), members);
            console.log(`  ✓ Successfully fetched active PMPro members.`);
        } catch (mErr) {
            console.warn('  ⚠️ Could not fetch /pmpro/members:', mErr.message);
        }

        console.log('✅ PMPro data saved to ./pmpro/ (levels.json, levels.md, members_active.json)');
    } catch (err) {
        console.warn('  ⚠️ Paid Memberships Pro skipped or not active:', err.message);
    }
}

async function pullMasterstudy() {
    console.log('⏳ Pulling MasterStudy LMS...');
    try {
        const msDir = path.join(outputDir, 'masterstudy');
        ensureDir(msDir);

        const courses = await makeRequest('/masterstudy/courses?per_page=50');
        writeJson(path.join(msDir, 'courses.json'), courses);

        if (courses && courses.courses && Array.isArray(courses.courses)) {
            console.log(`  ✓ Successfully fetched ${courses.courses.length} MasterStudy LMS courses.`);
            let cmd = `# MasterStudy LMS — Courses Overview\n\n`;
            cmd += `**Total Courses**: ${courses.pagination ? courses.pagination.total : courses.courses.length}\n\n`;
            cmd += `| ID | Course Title | Pricing Mode | Price | Product ID | Enrolled Students | Lessons | Membership Access |\n`;
            cmd += `|---|---|---|---|---|---|---|---|\n`;
            courses.courses.forEach(c => {
                const levelsStr = (c.membership_levels_allowed || []).map(l => l.name).join(', ') || (c.not_membership ? '⛔ No Membership' : 'Any');
                cmd += `| \`${c.id}\` | **${c.title}** | \`${c.pricing_mode}\` | ${c.price} | ${c.stm_lms_product_id || '-'} | ${c.total_students || 0} | ${c.lessons_count || 0} | ${levelsStr} |\n`;
            });
            writeText(path.join(msDir, 'courses.md'), cmd);
        }

        console.log('✅ MasterStudy LMS data saved to ./masterstudy/ (courses.json, courses.md)');
    } catch (err) {
        console.warn('  ⚠️ MasterStudy LMS skipped or not active:', err.message);
    }
}

// Purge Cache Layers
async function purgeCache() {
    const scope = getArg('scope', 'CACHE_SCOPE', 'all');
    console.log(`🧹 Purging multi-layer cache (scope: ${scope})...`);
    try {
        const res = await makeRequest('/performance/cache/purge', 'POST', { scope });
        console.log('✅ Cache purge response:');
        console.log(JSON.stringify(res, null, 2));
    } catch (err) {
        console.error('❌ Cache purge failed:', err.message);
        process.exit(1);
    }
}

// Main Runner
async function run() {
    console.log(`\n🚀 WP Agent Bridge CLI connecting to: ${siteUrl}`);
    console.log(`📁 Target Directory: ${outputDir}\n`);

    ensureDir(outputDir);

    switch (command) {
        case 'purge:cache':
        case 'cache:purge':
            await purgeCache();
            break;
        case 'pull:capabilities':
            await pullCapabilities();
            break;
        case 'pull:skill':
        case 'pull:playbooks':
            await pullSkill();
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
        case 'pull:code':
        case 'pull:checksums':
            await pullCode();
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
        case 'pull:content':
        case 'pull:pages':
        case 'pull:seo':
            await pullContent();
            break;
        case 'pull:performance':
        case 'pull:perf':
            await pullPerformance();
            break;
        case 'pull:pmpro':
            await pullPmpro();
            break;
        case 'pull:masterstudy':
        case 'pull:lms':
            await pullMasterstudy();
            break;
        case 'pull:logs':
            await pullLogs();
            break;
        case 'pull:all':
        default:
            await pullCapabilities();
            await pullSkill();
            await pullSystem();
            await pullScheduler();
            await pullTheme();
            await pullCode();
            await pullElementor();
            await pullSnippets();
            await pullFlowmattic();
            await pullAnalytics();
            await pullMeta();
            await pullWooCommerce();
            await pullContent();
            await pullPerformance();
            await pullPmpro();
            await pullMasterstudy();
            await pullLogs();
            break;
    }

    console.log('\n✨ Done! All requested site data is available locally.\n');
}

run();
