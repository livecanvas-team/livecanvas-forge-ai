# LiveCanvas Forge MCP

Local MCP package for `LiveCanvas Forge AI`.

## Modes

- `stdio`: MCP server for agent clients such as Codex, Claude Code, OpenCode, or Cursor.
- `bridge`: local HTTP/WebSocket bridge on the configured host and port.
- `--tool`: one-shot CLI mode for local orchestration from WordPress or shell scripts.

## Minimal usage

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

- Tailwind v4 local compilation now works by shimming `file://` fetch only for the MCP process, so the WindPress WASM parser can initialize under Node without patching the WindPress plugin.
- Local filesystem and local WindPress compilation require `LCFA_WP_ROOT` to point at the WordPress root when auto-detection is not sufficient.
