# LiveCanvas Forge AI

`LiveCanvas Forge AI` is a WordPress companion plugin for [LiveCanvas](https://livecanvas.com/).

It adds:

- a guided onboarding wizard for LiveCanvas projects
- a persistent `Genesis` brief and build plan
- a `Command Deck` for preview/apply operations on LiveCanvas targets
- a REST contract for local and remote automation
- a local MCP package for Codex, OpenCode, Claude Code, Cursor, and similar clients
- optional theme-file and WindPress/Tailwind bridges for advanced workflows

This plugin is designed to work with:

- `LiveCanvas`
- `Picostrap` / Bootstrap
- `Picowind` / Tailwind
- `WindPress` for Tailwind runtime and cache management

## Status

Current status: `alpha`.

The WordPress companion, setup flow, Genesis planning, Command Deck, REST API, local MCP package, remote Application Password support, theme fallback layer, and WindPress bridge are implemented.

## What The Plugin Does

Inside WordPress, the plugin gives you a single control surface with four tabs:

- `Setup`: onboarding wizard and stack detection
- `Genesis`: project brief and build plan
- `Connections`: local, remote, and MCP bootstrap configuration
- `Command Deck`: preview/apply operations on pages, partials, dynamic templates, theme files, and WindPress actions

Outside WordPress, the plugin exposes a companion contract that AI agents can use through:

- WordPress REST routes under `/wp-json/lcfa/v1/`
- a local MCP package included in [`mcp/`](mcp/)

## Requirements

- WordPress with administrator access
- `LiveCanvas` installed and active
- one of:
  - `Picostrap`
  - `Picowind`
- `Node.js` if you want to use the local MCP runtime
- `WindPress` if you want Tailwind cache scans, cache writes, or local Tailwind build orchestration

## Installation

### 1. Install The Plugin

Install `LiveCanvas Forge AI` into:

```text
wp-content/plugins/livecanvas-forge-ai
```

Then activate it from the WordPress Plugins screen.

### 2. Open Forge AI

After activation, open:

```text
WordPress Admin > Forge AI
```

If LiveCanvas is available, Forge AI will also appear under the LiveCanvas admin area.

### 3. Run Forge Setup

The setup wizard validates the stack in this order:

1. `Preflight`
   - checks that LiveCanvas is installed and active
2. `Framework`
   - confirms whether the project should use `Picostrap` or `Picowind`
3. `Site`
   - chooses `local`, `remote`, or hybrid workflow intent
4. `Client`
   - selects the preferred AI client
5. `Policy`
   - defines read/write behavior and file fallback rules
6. `Finish`
   - closes the wizard and unlocks the operational tabs

## First-Time Configuration

After the wizard, configure the project in this order.

### A. Genesis

Open the `Genesis` tab and fill in:

- project mode
- brand name
- sector
- tone of voice
- logo status
- required pages
- additional notes

Then:

1. click `Save Genesis Brief`
2. click `Generate Build Plan`

Forge AI will generate:

- planned pages
- suggested execution order
- advisory tasks
- quick links into `Command Deck`

### B. Connections

Open the `Connections` tab and review:

- transport mode
- preferred client
- local bridge URL
- MCP host/port/token
- remote site URL and credentials
- framework package URLs if you want guided installer support

Then click:

```text
Run connection checks
```

This validates:

- local REST bridge
- local MCP runtime
- remote companion connection

### C. Command Deck

Use the `Command Deck` tab for operational work.

Typical workflow:

1. choose a task from `Genesis` or open `Command Deck` directly
2. review the suggested action
3. run a preview first
4. inspect diff and structured payload
5. apply only when the result is correct

The plugin stores:

- command history
- persistent threads
- Genesis task progress
- theme/template backups for restore operations

## How Local AI Integration Works

The AI client does not talk directly to LiveCanvas.

It talks to:

1. the local MCP package, or
2. the companion REST API

The companion then executes WordPress-safe operations.

### Flow

```text
AI Client -> MCP or REST -> LiveCanvas Forge AI -> WordPress / LiveCanvas / WindPress
```

### Local Requirements

For local Codex/OpenCode usage, all of the following must be true:

- the site is detected as `local`
- the local WordPress URL responds correctly
- `/wp-json/lcfa/v1/mcp/status` is reachable
- Node.js is available
- the MCP script is present

If local REST loopback fails, the MCP runtime will not be available end-to-end.

## Codex Quick Start

Use the values shown in:

```text
Forge AI > Connections > MCP bootstrap
```

Typical command:

```bash
node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio
```

Typical environment:

```bash
LCFA_SITE_URL="http://your-local-site.test/"
LCFA_REST_BASE="http://your-local-site.test/wp-json/lcfa/v1/"
LCFA_MCP_ENDPOINT="ws://127.0.0.1:7681"
LCFA_MCP_TOKEN="your-generated-token"
LCFA_WP_ROOT="/absolute/path/to/wordpress"
```

Use the exact values generated by the plugin for the current site.

## OpenCode Quick Start

Typical command:

```bash
node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --agent=opencode
```

Typical environment:

```bash
LCFA_REST_BASE="http://your-local-site.test/wp-json/lcfa/v1/"
LCFA_MCP_TOKEN="your-generated-token"
LCFA_WP_ROOT="/absolute/path/to/wordpress"
```

## Claude Code And Cursor

Forge AI also prepares bootstrap values for:

- `Claude Code`
- `Cursor`

The generated values are shown in the `Connections` tab.

## Remote Site Setup

Remote mode requires the same companion plugin to be installed on the remote WordPress site.

### Remote Requirements

- `LiveCanvas Forge AI` installed on the remote site
- LiveCanvas installed and active on the remote site
- a dedicated WordPress user on the remote site
- an `Application Password` for that user

### Remote Configuration

In `Connections`, fill in:

- `Remote site URL`
- `Remote username`
- `Remote application password`

Then run connection checks again.

When the remote companion is reachable, you can use:

```text
Command Deck > Execution target = Remote site
```

## Command Deck Actions

Current supported actions include:

- `site_audit`
- `create_page`
- `update_page`
- `update_header`
- `update_footer`
- `create_dynamic_template`
- `update_dynamic_template`
- `windpress_audit`
- `windpress_scan_provider`
- `windpress_reset_entry`
- `windpress_store_theme_json`
- `windpress_store_cache_css`
- `build_windpress_cache`
- `windpress_flush_cache`
- `theme_files_audit`
- `theme_backups_audit`
- `write_theme_template`
- `write_theme_file`
- `restore_theme_backup`

## Genesis Task Progress

Each Genesis task can be in one of these states:

- `Pending`
- `Previewed`
- `Applied`
- `Needs attention`

The status changes automatically when a Command Deck run is linked to a Genesis task.

## WindPress And Tailwind

If the site uses `Picowind`, Forge AI can work with `WindPress`.

Supported operations include:

- provider audit
- provider scan
- volume reset
- cache flush
- theme.json cache write
- CSS cache write
- local Tailwind build orchestration

Local Tailwind build requires:

- local site mode
- Node.js available to PHP
- REST loopback working
- `WindPress` active

## Theme File Fallback Layer

When LiveCanvas dynamic markup is not enough, the companion can work on theme files.

Supported file types include:

- Twig
- Latte
- PHP
- HTML
- CSS
- JS
- JSON

Every overwrite goes through:

- preview
- diff
- optional apply
- backup capture
- restore support

## REST Contract

Main routes live under:

```text
/wp-json/lcfa/v1/
```

Examples:

- `/snapshot`
- `/inventory`
- `/context`
- `/theme-context`
- `/genesis/plan`
- `/command/actions`
- `/command/suggest`
- `/command`

## MCP Package

The bundled MCP package lives in:

- [mcp/README.md](mcp/README.md)

It supports:

- `stdio` mode
- `bridge` mode
- one-shot `--tool` mode

## Recommended Human Test

Before testing with Codex or OpenCode, validate the plugin from WordPress first.

1. complete `Setup`
2. save a `Genesis Brief`
3. generate a `Genesis Build Plan`
4. open the first task in `Command Deck`
5. run a preview
6. verify the task becomes `Previewed`
7. apply a safe task such as `create_page`
8. verify the task becomes `Applied`
9. run `Connections > Run connection checks`
10. only after that, connect Codex or OpenCode

This separates:

- plugin UX issues
- WordPress connectivity issues
- MCP/runtime issues
- client integration issues

## Troubleshooting

### Local REST bridge fails

Check:

- the local site URL is correct
- the local web server is running
- `/wp-json/lcfa/v1/mcp/status` is reachable

### Local MCP runtime fails

Check:

- Node.js is installed
- the MCP script exists
- WordPress REST loopback works
- the site is detected as `local`

### Remote connection fails

Check:

- the plugin is installed on the remote site
- the remote URL is correct
- the username is correct
- the Application Password is valid
- the remote user has enough permissions

### WindPress local build is unavailable

Check:

- `WindPress` is active
- the site is local
- Node.js is available
- REST loopback works

## Repository Structure

```text
livecanvas-forge-ai/
├── assets/
├── includes/
├── mcp/
└── livecanvas-forge-ai.php
```

## Notes

- UI copy is intentionally English.
- This plugin does not replace LiveCanvas. It orchestrates LiveCanvas-compatible operations.
- For production use, treat this project as an advanced companion layer, not as a fully finalized commercial release yet.
