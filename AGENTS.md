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
   ```powershell
   Compress-Archive -Path woo-get-data-for-ai -DestinationPath woo-get-data-for-ai.zip -Force
   ```
6. **Publish GitHub Release**:
   ```bash
   gh release create vX.Y.Z woo-get-data-for-ai.zip --title "vX.Y.Z - <Summary>" --notes "..."
   ```

> ⚠️ **CRITICAL WHY**: The plugin uses `plugin-update-checker` (PUC v5.6). Client WordPress sites will **ONLY** trigger and install auto-updates if a formal GitHub Release tag exists with the `woo-get-data-for-ai.zip` asset attached. Without this, client sites never receive the updates.
