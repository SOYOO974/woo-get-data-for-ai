# Antigravity Agent Guidelines for WP Agent Bridge

## 🚨 MANDATORY AUTOMATIC RELEASE DIRECTIVE (USER INSTRUCTION)

Whenever you finish working on a new feature, improvement, or bugfix in this codebase:

**YOU MUST SYSTEMATICALLY AND AUTOMATICALLY PUBLISH A NEW GITHUB RELEASE BEFORE ENDING YOUR WORK.**

Do not ask the user for permission or wait for them to request it; execute this protocol as the mandatory final step of any coding task:

1. **Version Bump**:
   - Increment the semantic version in `woo-get-data-for-ai/woo-get-data-for-ai.php` (both plugin header `Version: X.Y.Z` and constant `WOO_GET_DATA_AI_VERSION`).
2. **Synchronize Procedural Playbooks & Skills (The MECE 7 Master Pillars Principle)**:
   - Whenever adding or changing endpoints or diagnostic capabilities, integrate the endpoint into one of the **7 Strategic MECE Master Pillars** in `includes/class-playbooks.php`.
   - **CRITICAL ANTI-PROLIFERATION MANDATE**: Do **NOT** create an 8th or ad-hoc micro-playbook. Every diagnostic capability must naturally fit into one of the 7 MECE pillars to keep LLM context light, avoid prompt dilution, and prevent trigger collisions.
3. **Synchronize Internationalization (i18n) & Loco Translate (MANDATORY)**:
   - Whenever any user-facing text, label, badge, button, description, error message, or notice is added or modified in PHP views or JS:
     - Always wrap all PHP strings in standard gettext functions (`__()`, `_e()`, `esc_html__()`, `esc_html_e()`, `esc_attr__()`, `esc_attr_e()`, etc.) with domain `'woo-get-data-for-ai'`.
     - In JS, pass strings via `agentBridgeData` in `Admin_Settings::enqueue_assets()` (never hardcode English strings in JavaScript).
     - **Execute `php cli/sync-i18n.php` (or `npm run i18n` in `cli/`)** to automatically regenerate `languages/woo-get-data-for-ai.pot`, update `languages/woo-get-data-for-ai-fr_FR.po`, and compile binary `languages/woo-get-data-for-ai-fr_FR.mo`.
     - Ensure French translation coverage remains at **100%**. Add any new French translations to `cli/translations-fr.php`.
   > ⚠️ **CRITICAL WHY**: Loco Translate on client sites (e.g. conforama.re) and native WordPress French locale rely strictly on up-to-date `.pot`, `.po`, and compiled binary `.mo` files in `woo-get-data-for-ai/languages/`. If this step is omitted, production sites running in French display untranslated English strings and Loco Translate cannot synchronize new strings.
4. **Update Documentation**:
   - Update `PROJECT_CONTEXT.md` (permissions matrix, endpoint catalog, playbooks list, i18n status, version).
   - Update `README.md` (capabilities, REST API table, playbooks, CLI usage, version).
   - Update `cli/sync.js` if new endpoints were introduced.
5. **Commit & Push**:
   ```bash
   git add .
   git commit -m "feat(<module>): ... (vX.Y.Z)"
   git push origin main
   ```
6. **Generate Release Archive**:
   ```bash
   # CRITICAL: Do NOT use PowerShell Compress-Archive! On Windows, Compress-Archive stores backslashes (\)
   # which breaks file paths on Linux servers (e.g. conforama.re) during WordPress unzip_file().
   # Always use standard tar (natively available on Windows 10/11 & Linux) to enforce forward slashes (/):
   tar -a -cf woo-get-data-for-ai.zip woo-get-data-for-ai
   ```
7. **Publish GitHub Release**:
   ```bash
   gh release create vX.Y.Z woo-get-data-for-ai.zip --title "vX.Y.Z - <Summary>" --notes "..."
   ```

> ⚠️ **CRITICAL WHY**: The plugin uses `plugin-update-checker` (PUC v5.6). Client WordPress sites will **ONLY** trigger and install auto-updates if a formal GitHub Release tag exists with the `woo-get-data-for-ai.zip` asset attached. Without this, client sites never receive the updates. Also, archives must use forward slashes (`/`) so Linux unzippers don't flatten files or fail class autoloading.

---

## ⚡ MANDATORY ARCHITECTURAL DIRECTIVE: ZERO BLOAT & ANTI-"USINE À GAZ" (TOP PRIORITY)

As the plugin evolves and more inspection endpoints are added over time, **preventing the plugin from ever becoming an "usine à gaz" (a bloated, over-engineered, convoluted system) is a MANDATORY TOP PRIORITY during all developments.**

The plugin MUST always remain ultra-efficient, fast, laser-focused, and lightweight.

### 🛡️ Non-Negotiable Anti-Bloat Rules for Developers & AI Agents:

1. **Avoid Over-Engineering & Unnecessary Complexity**:
   - Keep the codebase simple, direct, and easily maintainable.
   - Refuse over-engineering, multi-layered abstractions, unnecessary wrappers, or complex architectural design patterns where clean, native WordPress/PHP patterns work best.
   - Do NOT add endpoints or options just because we can. Every new endpoint must serve a concrete, high-value diagnostic or context-fetching purpose for AI agents and developers. Refuse feature creep.

2. **Ultra-Lean Runtime Footprint & High Efficiency**:
   - **Zero Idle Impact**: The plugin runs on live production e-commerce stores. Its footprint must be practically invisible when idle (zero heavy background crons, zero continuous background polling, zero unsolicited external API calls, zero clutter in `wp_options` autoload).
   - **Blazing-Fast Execution**: Every REST request must return within milliseconds while consuming minimal server RAM and CPU.
   - **Memory-Safe by Design**: Strictly enforce streaming (`fseek`) for logs and files, require sensible default pagination limits (`limit`, `offset`), select only necessary columns/fields, and never load unbounded collections of orders or products into PHP memory.

3. **Modular, Self-Contained & Defensive Independence**:
   - Keep controllers standalone, isolated, and cleanly decoupled under `includes/api/`.
   - Always verify dependencies defensively (e.g., `class_exists()`, `is_plugin_active()`, or database table existence) before executing queries.
   - Zero bulky third-party SDK dependencies or bloated vendor libraries. Keep the plugin ZIP small, clean, and fast to download and unpack.

4. **Strictly Read-Only (Diagnostic Instrument Only)**:
   - WP Agent Bridge is an inspection window and context provider, NOT a remote site management, automation, or code execution suite. Never introduce write/POST/PUT/mutation endpoints.

5. **Pre-Implementation Sanity Check (The "Usine à Gaz" Filter)**:
   - Before implementing any new endpoint, controller, or feature, always evaluate:
     - *Does this solve a genuine diagnostic or technical audit need for AI agents or developers?*
     - *Can existing endpoints or parameters provide this context without adding a new route?*
     - *Is the implementation lightweight, clean, defensive, and lightning fast?*
   - If any answer is unsatisfactory, **challenge the need and do not implement it.**

6. **Playbook Governance & Anti-Proliferation (The 7 MECE Pillars Rule)**:
   - **Zero Playbook Bloat**: The plugin strictly maintains **only 7 Strategic MECE Master Playbooks**:
     1. `seo_content_audit` (SEO, Contenus & Visibilité)
     2. `agency_performance_audit` (Performance Multi-Templates & Core Web Vitals)
     3. `database_system_hygiene` (Santé Système, BDD Bloat & Hygiène Background)
     4. `order_checkout_troubleshoot` (Dépannage Commandes, Passerelles, SMTP & Webhooks)
     5. `ecommerce_bi_analytics` (Analytics Ventes, Produits & CRO 360°)
     6. `shipping_logistics_audit` (Zones d'expédition, Méthodes & Flexible Shipping)
     7. `code_theme_integrations` (Architecture Code, Thème, Hooks, Snippets & Automations)
   - Micro-playbooks (e.g. creating a separate playbook for SMTP, a separate one for database bloat, a separate one for code drift, etc.) dilute the LLM's attention span, bloat generated `SKILL.md` files by thousands of tokens, and cause semantic routing collisions.
   - Any new or improved endpoint MUST be integrated into one of these 7 master pillars as a step or contextual signal. Never register an 8th top-level playbook without strict architectural justification.

---

## 🌐 MANDATORY INTERNATIONALIZATION & LOCO TRANSLATE DIRECTIVE (ANTI-REGRESSION)

To ensure client sites (e.g. conforama.re) and WordPress administrators never see untranslated English strings in the administration interface or experience sync issues with **Loco Translate**:

### 🛡️ Non-Negotiable Translation Rules:

1. **Zero Raw User-Facing Strings in PHP**:
   - Every single user-facing string (tab title, badge, button, description, table header, settings label, notice, or diagnostic message) MUST be wrapped in standard WordPress gettext functions:
     - `esc_html__()` or `esc_html_e()` for standard HTML output.
     - `esc_attr__()` or `esc_attr_e()` for HTML attributes and input values.
     - `_n()` for plural-dependent numbers.
     - `_x()` for disambiguated contextual strings.
   - Text domain MUST strictly be `'woo-get-data-for-ai'`.

2. **Zero Hardcoded Strings in JavaScript**:
   - In `assets/js/admin.js`, NEVER write raw English strings for alerts, confirmation modals, button states, or error messages.
   - Pass all dynamic and UI strings through `wp_localize_script('agent-bridge-admin-js', 'agentBridgeData', [...])` in `Admin_Settings::enqueue_assets()`.

3. **Systematic Automated Sync Before Release (`php cli/sync-i18n.php`)**:
   - Whenever any UI file (`includes/admin/views/*.php`), class (`class-admin-settings.php`, `class-permissions.php`, `class-playbooks.php`), or JS file is touched:
     - Run `php cli/sync-i18n.php` (or `npm run i18n` in `cli/`).
     - This script automatically:
       1. Scans all PHP files for gettext tokens.
       2. Regenerates the master template `woo-get-data-for-ai/languages/woo-get-data-for-ai.pot`.
       3. Merges existing translations with `cli/translations-fr.php` into `woo-get-data-for-ai-fr_FR.po`.
       4. Compiles the binary `woo-get-data-for-ai-fr_FR.mo` file natively.
     - If any string is reported as untranslated, add its French translation to `cli/translations-fr.php` and re-run the sync until coverage reaches **100%**.
