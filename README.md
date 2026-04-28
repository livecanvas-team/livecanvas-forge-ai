# LiveCanvas Forge AI

`LiveCanvas Forge AI` is a companion plugin for [LiveCanvas](https://livecanvas.com/) built for AI-assisted web development.

The goal is not to replace LiveCanvas.

The goal is to let a coding agent handle the heavy structural work of building a site, while LiveCanvas remains the visual and code-level fine-tuning layer.

In practical terms, this means:

- a coding agent can inspect the WordPress + LiveCanvas stack
- create or update LiveCanvas pages
- work on headers, footers, partials, templates, and site-wide structure
- help prepare a design system
- operate on both local and remote WordPress sites
- return real URLs and concrete results that can then be refined in LiveCanvas

## Product Vision

This plugin is designed for a specific workflow:

1. you work in LiveCanvas, Picostrap, or Picowind
2. you connect a coding agent such as Codex, Cursor, Claude Code, or OpenCode
3. the agent performs high-leverage tasks such as setup, audits, page creation, shell scaffolding, and design-system work
4. you open the result in LiveCanvas and fine-tune the output

This makes the plugin a bridge between:

- the AI coding-agent world
- the LiveCanvas editing world
- the WordPress runtime

## What It Supports

The plugin is built to work with:

- `LiveCanvas`
- `Picostrap`
- `Picowind`
- `WindPress`

Current design-system direction:

- `Picostrap` uses `PicoSass` and the Bootstrap variable/compiler flow
- `Picowind` uses `WindPress` and `DaisyUI` as the theme/runtime layer
- custom themes will eventually use a plugin-managed fallback design-system layer

## What Exists Today

The current plugin includes:

- a setup wizard
- stack detection for LiveCanvas, framework, and site mode
- a `Genesis` brief and planning flow
- a `Connections` screen for local, remote, and coding-agent bootstrap
- a `Command Deck` for preview/apply actions
- a REST API under `/wp-json/lcfa/v1/`
- a local MCP package in [`mcp/`](./mcp/)
- a remote companion flow using WordPress Application Passwords
- a theme-files bridge
- a WindPress bridge

Recent foundation work also introduced:

- a normalized `page_upsert` action for create/update page flows
- final `frontend_url` and `edit_url` in page results
- safer policy enforcement on direct theme-file write routes
- better prompt routing so page creation requests are not incorrectly diverted into WindPress runtime actions

## Current Status

Status: `alpha`

The plugin is already useful for experimentation and structured development workflows, but it is still evolving toward a more deterministic and plug-and-play product.

The current direction is:

- make agent connection simpler
- expose clearer foundation operations
- keep local and remote behavior as symmetrical as possible
- make LiveCanvas page generation and global site scaffolding reliable first

## Core Idea

The plugin should be thought of as an execution engine.

The coding agent does the planning and prompting.

The plugin does the actual work inside WordPress.

That means the architecture is:

```text
Coding Agent -> MCP bridge or REST contract -> LiveCanvas Forge AI -> WordPress / LiveCanvas / WindPress
```

## Main Areas In The Plugin

### Setup

The setup flow profiles the project:

- preflight
- framework choice
- site mode
- preferred client
- operational policy

### Genesis

The Genesis flow stores a project brief and generates a structured plan for the site.

This is the planning layer for larger site builds.

### Connections

The Connections page is now wizard-first.

It is the shortest path between the plugin and a coding agent.

The page is organized around:

- `Connect your coding agent`
- a local or remote connection wizard
- generated client bundles
- smoke-test verification
- advanced settings only when needed

It now includes built-in, English quickstart guides for:

- `Codex`
- `Cursor`
- `Claude Code`
- `OpenCode`
- `Generic MCP client`

Each guide is shown in a dedicated tab and includes:

- step-by-step instructions
- the MCP server command
- the environment variables
- a terminal smoke test
- a Codex registration shortcut when relevant

The wizard also supports:

- local workspace writes for supported client artifacts
- downloadable bundles for remote setups
- a `workspace_root` override for environments where WordPress runtime paths and host machine paths differ
- a ready or needs-attention state after verification

### Command Deck

The Command Deck is the operational console for preview/apply actions.

This is where the plugin currently executes work such as:

- site audits
- page creation and update
- header and footer updates
- dynamic-template operations
- WindPress runtime actions
- theme-file fallback operations

## Installation

Install the plugin into:

```text
wp-content/plugins/livecanvas-forge-ai
```

Then activate it from WordPress admin.

After activation, open:

```text
WordPress Admin > Forge AI
```

If LiveCanvas is active, Forge AI also appears inside the LiveCanvas admin area.

## Recommended First Run

1. activate the plugin
2. open `Forge AI`
3. complete the setup wizard
4. open `Connections`
5. choose your coding agent
6. choose `local` or `remote`
7. save the wizard selection
8. install the local config or download the generated bundle
9. run the smoke test with `get_snapshot`
10. move to `Command Deck` and start with a preview-first flow

## How Agent Connection Works

There are two different connection models in this project.

This distinction matters.

### 1. Coding Agent -> Plugin

This is the most important one for Codex, Cursor, Claude Code, OpenCode, and similar tools.

The coding agent connects to the plugin by using:

- the plugin REST base
- the MCP token generated by the plugin
- optionally the WordPress root path when local filesystem access is needed

The plugin ships with a Node MCP bridge that turns the WordPress companion into an MCP server for coding agents.

See:

- [`mcp/README.md`](./mcp/README.md)
- [`mcp/bin/livecanvas-forge-mcp.js`](./mcp/bin/livecanvas-forge-mcp.js)

### 2. Plugin -> Remote Plugin

This is a different flow.

It is used when one WordPress companion wants to orchestrate another WordPress site remotely.

This remote flow uses:

- remote site URL
- remote WordPress username
- remote Application Password

This is not the same thing as the MCP-token flow used by coding agents.

## Coding Agent Setup

This plugin is designed to be connected from the `Connections` tab, not by inventing MCP config by hand.

For a local site, the shortest flow is always:

1. activate the plugin
2. complete Forge Setup
3. open `Connections`
4. choose the coding agent
5. choose `This local site`
6. confirm `REST base`, `MCP token`, and `Local workspace root`
7. generate the client config
8. open the same project in the coding agent
9. run `get_snapshot` as the first smoke test

Local mode means:

- the WordPress site runs on your machine
- the coding agent runs on your machine
- the MCP bridge runs on your machine
- `LCFA_WP_ROOT` can be used for local filesystem-aware operations

Typical local values are:

```bash
LCFA_REST_BASE="http://your-local-site.test/wp-json/lcfa/v1/"
LCFA_MCP_TOKEN="your-generated-token"
LCFA_WP_ROOT="/absolute/path/to/wordpress"
```

### OpenCode Setup

This is the cleanest current integration path.

Prerequisites:

- Node.js is available on your machine
- the WordPress site is reachable
- the Forge AI plugin is active
- the `Connections` wizard shows a valid local workspace root

Step by step:

1. open `WordPress Admin > Forge AI > Connections`
2. in the wizard choose `OpenCode`
3. choose `This local site`
4. confirm the connection details
5. use `Download opencode.json`
6. place `opencode.json` in the project root if it is not already there
7. open the same project root in OpenCode
8. open the MCP panel in OpenCode
9. confirm that `livecanvas-forge` is green
10. run a first prompt that calls `get_snapshot`

The generated `opencode.json` points OpenCode to the local MCP bridge in stdio mode.

The important command behind it is:

```bash
node /absolute/path/to/wordpress/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --agent=opencode
```

The first smoke-test prompt should be:

```text
Call the MCP tool livecanvas-forge_get_snapshot.
Return only the raw JSON result.
```

If that works, the next write test should be:

```text
Call the MCP tool livecanvas-forge_run_lc_command with this JSON:

{
  "action": "page_upsert",
  "title": "OpenCode Smoke Test",
  "slug": "opencode-smoke-test",
  "status": "draft",
  "content": "<main><h1>OpenCode Smoke Test</h1><p>MCP is working.</p></main>"
}

Return only the raw JSON result.
```

### Codex Setup

Codex uses the same MCP bridge, but the registration step is different.

Prerequisites:

- Node.js is available on your machine
- the Forge AI plugin is active
- the `Connections` wizard can show the generated Codex shortcut
- Codex desktop is installed, or `codex` is already available in your shell PATH

Step by step:

1. open `WordPress Admin > Forge AI > Connections`
2. in the wizard choose `Codex`
3. choose `This local site`
4. confirm the connection details
5. continue until the bundle or registration step
6. copy the `Codex shortcut` shown by the plugin
7. open a terminal in the same WordPress workspace
8. run that command once
9. the shortcut will first try `codex` from your PATH, then fall back to `/Applications/Codex.app/Contents/Resources/codex`
10. verify the registration with `codex mcp list` or `/Applications/Codex.app/Contents/Resources/codex mcp list`
11. open Codex on the same workspace and run `get_snapshot`

The Codex shortcut generated by the plugin has this shape:

```bash
LCFA_CODEX_BIN=""
if command -v codex >/dev/null 2>&1; then
  LCFA_CODEX_BIN="$(command -v codex)"
elif [ -x "/Applications/Codex.app/Contents/Resources/codex" ]; then
  LCFA_CODEX_BIN="/Applications/Codex.app/Contents/Resources/codex"
fi

if [ -n "$LCFA_CODEX_BIN" ]; then
  "$LCFA_CODEX_BIN" mcp add livecanvas-forge \
    --env LCFA_SITE_URL=http://your-local-site.test/ \
    --env LCFA_REST_BASE=http://your-local-site.test/wp-json/lcfa/v1/ \
    --env LCFA_MCP_ENDPOINT=http://your-local-site.test/wp-json/lcfa/v1/mcp/status \
    --env LCFA_MCP_TOKEN=your-generated-token \
    --env LCFA_WP_ROOT=/absolute/path/to/wordpress \
    -- node /absolute/path/to/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio
fi
```

If neither `codex` nor the embedded desktop CLI is available, the generated shortcut also prints a fallback snippet you can paste into `~/.codex/config.toml`.

The first smoke-test prompt in Codex should be:

```text
Call the MCP tool livecanvas-forge_get_snapshot.
Return only the raw JSON result.
```

Then test a write:

```text
Call the MCP tool livecanvas-forge_run_lc_command with this JSON:

{
  "action": "page_upsert",
  "title": "Codex Smoke Test",
  "slug": "codex-smoke-test",
  "status": "draft",
  "content": "<main><h1>Codex Smoke Test</h1><p>MCP is working.</p></main>"
}

Return only the raw JSON result.
```

### Remote Setup

Remote mode means:

- the WordPress site is hosted elsewhere
- the coding agent still talks to the plugin through its REST contract
- local filesystem access is usually not available

Typical remote values are:

```bash
LCFA_REST_BASE="https://example.com/wp-json/lcfa/v1/"
LCFA_MCP_TOKEN="remote-site-token"
```

In most remote cases, `LCFA_WP_ROOT` is not required.

The clean remote pattern is:

1. install Forge AI on the remote site
2. open `Connections` on the remote site
3. choose the coding agent
4. choose `Remote site`
5. generate the client bundle
6. use the remote `REST base` and remote `MCP token`
7. run `get_snapshot` first

There is also a second flow where one WordPress companion controls another WordPress companion through:

- remote site URL
- remote WordPress username
- remote Application Password

That plugin-to-plugin flow is not the same thing as the MCP-token flow used by Codex or OpenCode.

## What The Coding-Agent Guides In The Plugin Now Cover

The `Connections` page includes built-in English onboarding for coding agents.

It shows:

- the MCP server command
- the environment variables
- the local or remote mode
- the Codex shortcut when relevant
- the OpenCode flow with the expected green MCP state
- the first smoke-test command

The intent is to keep agent setup plug-and-play and centered on the wizard instead of low-level transport details.

## Current Foundation Direction

The product is moving away from ambiguous prompt guessing and toward deterministic foundation operations.

The foundation contract now includes deterministic actions such as:

- `site_audit`
- `site_prepare`
- `design_system_apply`
- `design_system_compose`
- `global_shell_apply`
- `page_upsert`
- `validate_markup_for_framework`
- `create_dynamic_template`
- `update_dynamic_template`
- `site_foundation_run`

The current foundation slice includes:

- stronger safety rules
- better page create/update behavior
- final URLs in page responses
- stack preflight via `site_prepare`
- Picostrap, Picowind, and fallback design-system execution paths
- variant-aware header/footer creation and update via `global_shell_apply`
- orchestrated setup via `site_foundation_run`
- editor-side section starters and visual reference metadata
- assignment metadata for dynamic templates

## Design-System Direction

The design-system work is intentionally theme-aware.

Planned execution model:

- `Picostrap` -> translate intent into `PicoSass` variables -> compile Bootstrap bundle
- `Picowind` -> translate intent into `WindPress` and `DaisyUI` runtime changes
- custom themes -> fallback plugin-owned canonical design-system layer

This matters because the plugin is not supposed to dump generic CSS into the project when a native theme/compiler/runtime already exists.

## What The Project Is Becoming

This is not just a generic AI plugin for WordPress.

It is becoming a site-foundation engine for LiveCanvas-powered development.

The long-term workflow is:

1. connect the coding agent in minutes
2. audit the site
3. apply foundation work
4. generate or update real pages and templates
5. open the result in LiveCanvas
6. fine-tune visually and structurally

## Roadmap

### Phase 1: Foundation Contract

- continue hardening safety and policy behavior
- keep normalized page creation/update flows aligned across local and remote execution
- expand the foundation contract tests around failure and rollback paths
- keep simplifying coding-agent connection UX

### Phase 2: Design-System Execution

- `design_system_apply` maps Picostrap tokens to theme mods
- Picowind integrates through WindPress and DaisyUI cache paths
- custom themes use portable fallback assets in the active stylesheet theme
- preview/apply behavior stays explicit for design changes
- expand fallback enqueue guidance for themes without a native compiler/runtime

### Phase 3: Global Shell

- `global_shell_apply` creates or updates header/footer partials
- header/footer operations support explicit variants
- inventory exposes partial type and variant metadata
- broaden partial variant discovery against real LiveCanvas installs

### Phase 4: Site Foundation Run

- `site_foundation_run` orchestrates preflight, design system, shell, and starter pages
- Genesis task loading hydrates structured payloads into the Command Deck
- enrich starter page plans from the project brief
- make first-install workflows deterministic end to end

### Phase 5: Dynamic Templates And Data-Aware Builds

- store assignment metadata for LiveCanvas dynamic templates
- sync assignment metadata to native LiveCanvas `is_*` template meta where available
- WooCommerce single/archive support
- custom post type support
- ACF-aware template generation

### Phase 6: Deeper LiveCanvas Editing Loop

- tighter editor-side chat workflows
- more contextual editing inside LiveCanvas
- better collaboration between generated output and human fine-tuning

## Repository Notes

- WordPress companion plugin entrypoint: [`livecanvas-forge-ai.php`](./livecanvas-forge-ai.php)
- MCP package: [`mcp/`](./mcp/)
- foundation design spec: [`docs/superpowers/specs/2026-04-10-livecanvas-foundation-orchestrator-design.md`](./docs/superpowers/specs/2026-04-10-livecanvas-foundation-orchestrator-design.md)
- current phase-1 implementation plan: [`docs/superpowers/plans/2026-04-10-foundation-contract-phase-1.md`](./docs/superpowers/plans/2026-04-10-foundation-contract-phase-1.md)

## Short Summary

`LiveCanvas Forge AI` is a companion layer that lets coding agents do serious WordPress and LiveCanvas work, while keeping LiveCanvas as the place where final refinement happens.

That is the product.
