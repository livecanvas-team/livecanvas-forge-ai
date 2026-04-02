# LiveCanvas Forge MCP

Local MCP package for `LiveCanvas Forge AI`.

## Modes

- `stdio`: MCP server for agent clients such as Codex, Claude Code, OpenCode, or Cursor.
- `bridge`: local HTTP/WebSocket bridge on the configured host and port.

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
- `GET /command/actions`
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

WebSocket bridge messages accept:

- `{ "action": "tools/list" }`
- `{ "action": "tools/call", "name": "get_snapshot", "arguments": {} }`
- `{ "tool": "run_lc_command", "arguments": { "action": "site_audit", "dry_run": true } }`

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
