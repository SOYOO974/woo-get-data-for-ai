# Antigravity Agent Guidelines for WP Agent Bridge

## 🚨 MANDATORY AUTOMATIC RELEASE DIRECTIVE (USER INSTRUCTION)

Whenever you finish working on a new feature, improvement, or bugfix in this codebase:

**YOU MUST SYSTEMATICALLY AND AUTOMATICALLY PUBLISH A NEW GITHUB RELEASE BEFORE ENDING YOUR WORK.**

Do not ask the user for permission or wait for them to request it; execute this protocol as the mandatory final step of any coding task:

1. **Version Bump**:
   - Increment the semantic version in `woo-get-data-for-ai/woo-get-data-for-ai.php` (both plugin header `Version: X.Y.Z` and constant `WOO_GET_DATA_AI_VERSION`).
2. **Synchronize Procedural Playbooks & Skills**:
   - Whenever adding or changing endpoints or diagnostic capabilities, update `includes/class-playbooks.php` to integrate the endpoint into existing playbooks or create a new dedicated Playbook so AI agents automatically learn how to use it without prompt re-copying.
3. **Update Documentation**:
   - Update `PROJECT_CONTEXT.md` (permissions matrix, endpoint catalog, playbooks list, version).
   - Update `README.md` (capabilities, REST API table, playbooks, CLI usage, version).
   - Update `cli/sync.js` if new endpoints were introduced.
4. **Commit & Push**:
   ```bash
   git add .
   git commit -m "feat(<module>): ... (vX.Y.Z)"
   git push origin main
   ```
5. **Generate Release Archive**:
   ```bash
   # CRITICAL: Do NOT use PowerShell Compress-Archive! On Windows, Compress-Archive stores backslashes (\)
   # which breaks file paths on Linux servers (e.g. conforama.re) during WordPress unzip_file().
   # Always use standard tar (natively available on Windows 10/11 & Linux) to enforce forward slashes (/):
   tar -a -cf woo-get-data-for-ai.zip woo-get-data-for-ai
   ```
6. **Publish GitHub Release**:
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

