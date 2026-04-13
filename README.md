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

## Local Connection Guide

Local mode means:

- the WordPress site runs on your machine
- the coding agent runs on your machine
- the MCP bridge can optionally use the local WordPress root for filesystem-aware operations

Typical local requirements:

- LiveCanvas is active
- the site REST API is reachable
- Node.js is available
- the plugin MCP bridge is present
- the MCP token is available

Typical local variables:

```bash
LCFA_REST_BASE="http://your-local-site.test/wp-json/lcfa/v1/"
LCFA_MCP_TOKEN="your-generated-token"
LCFA_WP_ROOT="/absolute/path/to/wordpress"
```

Typical local MCP server command:

```bash
node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio
```

The easiest path is:

1. open `Connections`
2. choose your coding agent tab
3. copy the generated command and environment
4. register the MCP server in your coding client
5. run `get_snapshot` as the first smoke test

## Remote Connection Guide

Remote mode means:

- the WordPress site is hosted elsewhere
- the coding agent still talks to the plugin through its REST contract
- local filesystem access is usually not available

Typical remote variables:

```bash
LCFA_REST_BASE="https://example.com/wp-json/lcfa/v1/"
LCFA_MCP_TOKEN="remote-site-token"
```

In most remote scenarios, `LCFA_WP_ROOT` is not required.

Remote usage currently has two valid patterns:

### Pattern A: Agent directly targets the remote site

This is the cleaner agent workflow.

The agent talks directly to the remote plugin companion with:

- remote REST base
- remote MCP token

### Pattern B: Local WordPress companion controls a remote WordPress companion

This uses:

- remote site URL
- remote WordPress username
- remote Application Password

This is configured inside the local plugin `Connections` screen.

## What The Coding-Agent Guides In The Plugin Now Cover

The `Connections` page now includes an onboarding section specifically for coding agents.

That section explains, in English:

- what command to use
- what environment variables to set
- how to test the bridge manually from terminal
- how to register the MCP server in a client such as Codex

The intent is to make the coding-agent connection more plug-and-play and less transport-heavy.

## Current Foundation Direction

The product is moving away from ambiguous prompt guessing and toward deterministic foundation operations.

The target contract includes actions such as:

- `site_audit`
- `site_prepare`
- `design_system_apply`
- `global_shell_apply`
- `page_upsert`
- `site_foundation_run`

The first foundation slice already started with:

- stronger safety rules
- better page create/update behavior
- final URLs in page responses

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

- harden safety and policy behavior
- finish normalized page creation/update flows
- keep local and remote page execution behavior aligned
- simplify coding-agent connection UX

### Phase 2: Design-System Execution

- implement `design_system_apply`
- integrate `Picostrap` through `PicoSass`
- integrate `Picowind` through `WindPress` and `DaisyUI`
- expose preview/apply behavior for design changes

### Phase 3: Global Shell

- implement `global_shell_apply`
- make header/footer creation and update first-class
- improve partial inventory and variant handling

### Phase 4: Site Foundation Run

- implement `site_foundation_run`
- orchestrate site audit, design system, shell, and starter pages
- make first-install workflows deterministic

### Phase 5: Dynamic Templates And Data-Aware Builds

- real LiveCanvas dynamic-template assignment
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
