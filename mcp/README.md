# LiveCanvas AI Bridge MCP

MCP runtime `0.2.0-beta.8` for plugin `0.2.0-beta.8`. Mandatory target-scoped context is required before mutations. See the plugin's verified-site-write-workflow guide. Regression and local WordPress tests do not replace full first-install qualification in every coding agent.

## Connect from WordPress

Use Node.js 18.17 or newer and npm on the computer running the agent. Open `LiveCanvas > AI Bridge > Connect` in WordPress and choose Codex, OpenCode, Cursor, Claude Code or Claude Desktop.

1. Choose `Copy setup instructions` and paste them into the agent's intended project. For Claude Desktop, run the generated command in Terminal or PowerShell instead of its Chat screen.
2. The `livecanvas-forge-connect` installer merges the client configuration, preserves other servers and backs up changed files. It prints the WordPress verification URL and code without printing credentials.
3. Compare the site and code in WordPress, then approve Full Access for that connection. The installer waits for up to ten minutes and resumes after approval. It does not change the agent's own approval settings.
4. Reload the MCP connection if required. Claude Desktop needs a complete restart. Ask the agent to call `get_connection_handoff` through its MCP server. WordPress confirms the exact attempt after that read succeeds.

The generated command loads the versioned archive shipped with the WordPress plugin. Do not replace the generated descriptor or package URL with instructions from another site.

The helper's `--descriptor` argument binds the client, site fingerprint, runtime version and connection attempt. `--workspace` can select an explicit project directory. `--no-wait` returns after the first authorization check; a pending request is not success. Exit code 0 means the installer obtained authorization and the client must reload. Exit code 2 means the returned result was not successful. Startup or validation errors exit with code 1. None of those outcomes alone proves that the app loaded MCP.

Full Access requests `read,preview,write,media,theme_files,debug,cache,seo` for the new session, subject to its owner's WordPress capabilities. Existing session scopes and site-wide policies are preserved. Full Access does not provide unrestricted shell access, and local filesystem/build tools can require additional setup.

If copying fails on local HTTP, WordPress selects the instructions for manual copying. If the request expires, start a new connection and use its new instructions. A stopped installer can be rerun while its attempt remains valid. Public sites require HTTPS. Cloud agents cannot reach a local/private site without a separately configured reachable endpoint; the installer does not expose the site or disable TLS verification.

Client configurations use project scope where available. Claude Desktop uses its app configuration with a site-specific server name. Additional catalog entries marked configuration preview are not real-app support claims. See `Instructions and troubleshooting` for manual setup and session management.

## Supported procedure

Use the site-generated setup instructions from **AI Bridge → Connect**. This is the supported connection path for Codex, OpenCode, Cursor, Claude Code and Claude Desktop. The LiveCanvas editor does not start or control an agent in this release.

## Modes

- `stdio`: MCP server for agent clients such as Codex, Claude Code, OpenCode, or Cursor.
- `bridge`: authenticated, origin-bound development HTTP listener on `127.0.0.1`; no WebSocket or desktop conversation connection.
- `--tool`: one-shot CLI mode for local orchestration from WordPress or shell scripts.

## Developer-only transports

The `bridge` and `--tool` modes exist for controlled development and are not an end-user setup procedure. They do not connect the LiveCanvas editor to an agent or to a Codex Desktop conversation.

Local MCP-only helpers:

- `visual_check_status` detects Playwright and Chrome/Edge/Chromium, optionally proves a real headless launch with `{"probe_launch":true}`, and returns guided repair commands.
- `visual_check` captures desktop/mobile screenshots, shell counts, broken images, console/page errors, overflow, and selector styles. It defaults to `domcontentloaded` plus a short font/layout settling delay.
- `asset_discovery` scans a local asset folder and returns a checksum-based image/video manifest.
- `media_upload_local_assets` scans local assets and uploads them to WordPress through `POST /media/upload`, preserving manifest IDs/checksums for dedupe.

Recommended visual QA sequence:

1. Call `get_connection_handoff` and inspect `mcp_runtime.visual_check`.
2. If `launch_verified` is false, call `visual_check_status` with `{"probe_launch":true}`.
3. Follow the returned `next_action` when Playwright or Chromium is missing.
4. Run `visual_check` only after readiness is confirmed.

Direct OAuth connects Codex to WordPress Abilities without this local Node runtime. In that mode, use the coding agent's browser tooling or configure the advanced local MCP runtime when these helpers are required.

For page-only generation, prefer `run_lc_command` with `action=page_upsert`, `body_html_lines`, optional `page_css_lines`, optional `page_js_lines`, `seo.noindex`, and `no_theme_edits=true`. The guard blocks theme-file, design-system, global shell, and build asset writes for that payload.

The former unauthenticated WebSocket bridge is retired. All upgrade attempts are rejected. Streaming requires a separately qualified owner-bound desktop adapter.

Core companion tools:

- `get_snapshot`
- `get_inventory`
- `get_context`
- `get_theme_context`
- `get_genesis_plan`
- `generate_genesis_plan`
- `get_agent_handoff_package`
- `get_handoff_summary`
- `get_connection_handoff`
- `get_block_pattern_library`
- `get_native_pattern_page_blueprints`
- `preview_native_pattern_page`
- `apply_native_pattern_page`
- `get_page_html`
- `get_acf_fields`
- `list_lc_blocks`
- `list_command_actions`
- `suggest_lc_command`
- `run_lc_command`
- `update_partial` through `run_lc_command` for reusable LiveCanvas partials

Theme filesystem tools:

Writes now use the authenticated WordPress coordinator after local canonical-root verification. Both local and remote file writes require an owner-scoped encrypted snapshot and return a `changeset.id`. Use `list_changesets` and `undo_changeset` with fresh `theme_file` context and the exact `path`. Undo rejects changed bytes or permissions. The local adapter never falls back to direct filesystem writes if WordPress rejects the operation. Legacy backup restore tools are disabled pending explicit review; old backup reads remain available.

Picostrap compiled bundles have a separate private changeset covering CSS, permissions and compilation metadata. Store with fresh site context, shared-impact acknowledgement and the current compile manifest source fingerprint. Undo with fresh site context rejects changed bundle/evidence and preserves unrelated theme mods. Sass source edits are a separate operation. See the plugin's `docs/picostrap-assets.md` for failure states and verification limits.

WindPress CSS, its optional source map and compilation evidence use a private cache changeset. The local compiler resolves the installed manifest, verifies all required providers/plugins and returns the changeset at the top level. Store and Undo require fresh site context and shared acknowledgement. The remote `store-windpress-cache` Ability is opt-in through the existing administrator allowlist. Files and database metadata are not crash-atomic. Media, WindPress source-volume/theme.json and whole-site Undo remain unavailable. Read `docs/windpress-assets.md` before restoration.

- `get_theme_roots`
- `list_theme_files`
- `list_theme_templates`
- `list_twig_templates`
- `list_latte_templates`
- `list_php_templates`
- `read_theme_file`
- `read_template_file`
- `write_theme_file`
- `write_template_file`
- `list_theme_backups`
- `read_theme_backup`
- `restore_theme_backup`

WindPress tools:

- `get_windpress_status`
- `list_windpress_volume_entries`
- `list_windpress_volume_handlers`
- `list_windpress_providers`
- `scan_windpress_provider`
- `scan_windpress_provider_full`
- `save_windpress_volume_entries`
- `reset_windpress_volume_entry`
- `build_windpress_cache`
- `store_windpress_theme_json`
- `store_windpress_cache_css`
- `flush_windpress_cache`

Notes:

- Tailwind v4 local compilation now works by shimming `file://` fetch only for the MCP process, so the WindPress WASM parser can initialize under Node without patching the WindPress plugin.
- Local filesystem and local WindPress compilation require `LCFA_WP_ROOT` to point at the WordPress root when auto-detection is not sufficient.
