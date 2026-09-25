# LiveCanvas AI Bridge MCP

MCP runtime `0.2.0-beta.7` for plugin `0.2.0-beta.6`. Mandatory target-scoped context is required before mutations. See the plugin's verified-site-write-workflow guide. Regression and local WordPress tests do not replace full first-install qualification in every coding agent.

## Connect from WordPress

Use Node.js 18.17 or newer and npm on the computer running the agent. Open `LiveCanvas > AI Bridge > Connect` in WordPress and choose Codex, OpenCode, Cursor, Claude Code or Claude Desktop.

1. Choose `Copy setup instructions` and paste them into the agent's intended project. For Claude Desktop, run the generated command in Terminal or PowerShell instead of its Chat screen.
2. The `livecanvas-forge-connect` installer merges the client configuration, preserves other servers and backs up changed files. It prints the WordPress verification URL and code without printing credentials.
3. Compare the site and code in WordPress, then approve Full Access for that connection. The installer waits for up to ten minutes and resumes after approval. It does not change the agent's own approval settings.
4. Reload the MCP connection if required. Claude Desktop needs a complete restart. Ask the agent to call `get_connection_handoff` through its MCP server. WordPress confirms the exact attempt after that read succeeds.

The generated command loads the versioned archive shipped at `assets/runtime/livecanvas-ai-bridge-mcp-0.2.0-beta.7.tgz` on the WordPress site. It does not depend on a beta.7 npm publication. Do not replace the generated descriptor or package URL with instructions from another site.

The helper's `--descriptor` argument binds the client, site fingerprint, runtime version and connection attempt. `--workspace` can select an explicit project directory. `--no-wait` returns after the first authorization check; a pending request is not success. Exit code 0 means the installer obtained authorization and the client must reload. Exit code 2 means the returned result was not successful. Startup or validation errors exit with code 1. None of those outcomes alone proves that the app loaded MCP.

Full Access requests `read,preview,write,media,theme_files,debug,cache,seo` for the new session, subject to its owner's WordPress capabilities. Existing session scopes and site-wide policies are preserved. Full Access does not provide unrestricted shell access, and local filesystem/build tools can require additional setup.

If copying fails on local HTTP, WordPress selects the instructions for manual copying. If the request expires, start a new connection and use its new instructions. A stopped installer can be rerun while its attempt remains valid. Public sites require HTTPS. Cloud agents cannot reach a local/private site without a separately configured reachable endpoint; the installer does not expose the site or disable TLS verification.

Client configurations use project scope where available. Claude Desktop uses its app configuration with a site-specific server name. Additional catalog entries marked configuration preview are not real-app support claims. See `Instructions and troubleshooting` for manual setup and session management.

## Advanced and legacy runtime paths

It supports two paths:

- secure remote Direct Mode with AI Bridge pairing;
- legacy/local runtime with an MCP token and optional filesystem access.

## Modes

- `stdio`: MCP server for agent clients such as Codex, Claude Code, OpenCode, or Cursor.
- `bridge`: local HTTP/WebSocket bridge on the configured host and port.
- `--tool`: one-shot CLI mode for local orchestration from WordPress or shell scripts.

## Manual usage

The examples in this section retain the earlier beta.5 package for historical reference only. That runtime cannot write through the current plugin contract. Use the WordPress-generated archive command above with runtime beta.7.

Secure remote Direct Mode:

```bash
LCFA_SITE_URL="https://example.test/" \
LCFA_SITE_FINGERPRINT="site-fingerprint" \
LCFA_PROJECT_LABEL="Example Site" \
npx -y @livecanvas/ai-bridge-mcp@0.2.0-beta.5
```

On first use, the MCP asks WordPress for a short-lived pairing request. Approve the pending Codex session in `AI Bridge > Connections`; the MCP receives a plugin-scoped session token once and caches it locally with restricted file permissions.

By default the pairing requests `read`, `preview`, and `write` scopes, because AI Bridge exposes curated write tools only after WordPress admin approval and the plugin write policy allowlist. To force a read/preview-only session, set:

```bash
LCFA_PAIRING_SCOPES="read,preview"
```

In the older manual project-setup flow, the **Configure and build this site** choice overrides the package default with `read,preview,write,media,theme_files,debug,cache,seo`; **Inspect only** generates `read,preview`. The new Connect screen requests Full Access for the approved session as described above.

If a staging host is protected by HTTP Basic authentication, pass those credentials only as local MCP process environment variables:

```bash
LCFA_HTTP_BASIC_USERNAME="staging-user"
LCFA_HTTP_BASIC_PASSWORD="staging-password"
```

These values are used only for the outer web-server protection. They are not sent to WordPress, stored by AI Bridge, or included in pairing/session records.

Legacy/local runtime:

```bash
LCFA_REST_BASE="https://example.test/wp-json/lcfa/v1/" \
LCFA_MCP_TOKEN="your-token" \
LCFA_WP_ROOT="/absolute/path/to/wordpress" \
node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio
```

## Bridge mode

```bash
LCFA_REST_BASE="https://example.test/wp-json/lcfa/v1/" \
LCFA_MCP_TOKEN="your-token" \
LCFA_WP_ROOT="/absolute/path/to/wordpress" \
node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=bridge --host=127.0.0.1 --port=7681
```

## One-shot tool mode

```bash
LCFA_REST_BASE="https://example.test/wp-json/lcfa/v1/" \
LCFA_MCP_TOKEN="your-token" \
LCFA_WP_ROOT="/absolute/path/to/wordpress" \
node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js \
  --tool=build_windpress_cache \
  --tool-args='{"provider_ids":["wordpress-theme-json"],"store":false}' \
  --output=json
```

HTTP routes:

- `GET /health`
- `GET /bootstrap`
- `GET /tools`
- `GET /snapshot`
- `GET /inventory`
- `GET /context`
- `GET /theme-context`
- `GET /page-html?post_id=123`
- `GET /acf-fields?post_type=page`
- `GET /library/blocks`
- `GET /windpress/status`
- `GET /windpress/volume`
- `GET /windpress/volume/handlers`
- `GET /windpress/providers`
- `GET /theme/roots`
- `GET /theme/files?root_scope=active&directory=views&extension=twig`
- `GET /theme/templates?root_scope=active`
- `GET /theme/templates/twig?root_scope=active`
- `GET /theme/templates/latte?root_scope=active`
- `GET /theme/templates/php?root_scope=active`
- `GET /theme/file?root_scope=stylesheet&path=views/header.twig`
- `GET /theme/template?root_scope=stylesheet&path=views/header.twig`
- `GET /theme/backups`
- `GET /theme/backup?backup_id=2026-04-03/theme-name/file`
- `GET /command/actions`
- `POST /command/suggest`
- `POST /command`
- `POST /windpress/volume`
- `POST /windpress/providers/scan`
- `POST /windpress/providers/scan/full`
- `POST /windpress/volume/reset`
- `POST /windpress/build`
- `POST /windpress/theme-json`
- `POST /windpress/cache`
- `POST /windpress/cache/flush`
- `POST /theme/file`
- `POST /theme/template`
- `POST /theme/backup/restore`

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

WebSocket bridge messages accept:

- `{ "action": "tools/list" }`
- `{ "action": "tools/call", "name": "get_snapshot", "arguments": {} }`
- `{ "tool": "run_lc_command", "arguments": { "action": "site_audit", "dry_run": true } }`

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

- Secure remote Direct Mode does not use `WP_API_USERNAME` or `WP_API_PASSWORD`.
- Tailwind v4 local compilation now works by shimming `file://` fetch only for the MCP process, so the WindPress WASM parser can initialize under Node without patching the WindPress plugin.
- Local filesystem and local WindPress compilation require `LCFA_WP_ROOT` to point at the WordPress root when auto-detection is not sufficient.
