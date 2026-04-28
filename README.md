# LiveCanvas Forge AI

`LiveCanvas Forge AI` is a companion plugin for [LiveCanvas](https://livecanvas.com/) that lets coding agents work inside a real WordPress + LiveCanvas site.

It does not replace LiveCanvas. It handles structural work, agent integration, previews, page/template operations, and foundation setup while LiveCanvas remains the visual and code-level editing layer.

## Current Status

Status: `alpha`

Usable today:

- connect coding agents through the local MCP bridge or REST contract
- inspect WordPress, LiveCanvas, Picostrap, Picowind, WindPress, WooCommerce, and ACF context
- run preview/apply operations from the Command Deck
- create and update LiveCanvas pages with `page_upsert`
- create and update header/footer partials with `global_shell_apply`
- apply design-system tokens for Picostrap, Picowind/WindPress, or a custom-theme fallback
- run first-pass site foundation workflows with `site_foundation_run`
- create/update LiveCanvas dynamic templates and sync supported assignments to native `is_*` meta
- use the Forge drawer inside the LiveCanvas editor for prompt-driven edits and screenshot references

Still in progress:

- richer WooCommerce product/archive template generation
- ACF-aware markup generation
- broader real-site smoke testing across LiveCanvas/Picostrap/Picowind installs
- more creative screenshot-aware generation
- stronger remote/local parity testing for complex write workflows
- more complete fallback enqueue behavior for custom themes

## What It Does

The plugin acts as a WordPress execution engine for coding agents.

```text
Coding Agent -> MCP or REST -> LiveCanvas Forge AI -> WordPress / LiveCanvas / WindPress
```

Main areas:

- `Setup`: project profile, framework, site mode, policy
- `Connections`: agent bootstrap for Codex, Cursor, Claude Code, OpenCode, and generic MCP clients
- `Genesis`: project brief and executable site plan
- `Command Deck`: preview/apply console for structured operations
- `LiveCanvas editor drawer`: in-editor prompt surface for contextual page edits
- `MCP package`: local Node bridge in [`mcp/`](./mcp/)

## Requirements

Recommended:

- WordPress
- LiveCanvas
- PHP compatible with your WordPress install
- Node.js for MCP/coding-agent integrations
- Picostrap, Picowind/WindPress, or another active WordPress theme

## Installation

From `wp-content/plugins`:

```bash
git clone https://github.com/livecanvas-team/livecanvas-forge-ai.git
```

Then activate:

```text
WordPress Admin > Plugins > LiveCanvas Forge AI > Activate
```

You can also upload a ZIP from:

```text
WordPress Admin > Plugins > Add New > Upload Plugin
```

The plugin folder should be named:

```text
livecanvas-forge-ai
```

After activation, open:

```text
WordPress Admin > Forge AI
```

If LiveCanvas is active, Forge AI also appears inside the LiveCanvas admin area.

## Quick Start

1. Activate the plugin.
2. Open `WordPress Admin > Forge AI`.
3. Complete the setup wizard.
4. Open `Connections`.
5. Choose your coding agent and local/remote mode.
6. Generate or install the client config.
7. Run `get_snapshot` from the coding agent.
8. Open `Command Deck`.
9. Start with `dry_run: true`.
10. Apply only after the preview result looks correct.

For detailed MCP setup, see [`mcp/README.md`](./mcp/README.md). The preferred setup path is the in-plugin `Connections` wizard.

## Core Commands

Common `run_lc_command` actions:

| Action | Purpose |
| --- | --- |
| `site_audit` | Inspect the site, stack, inventory, and capabilities. |
| `site_prepare` | Check readiness before larger foundation work. |
| `design_system_apply` | Apply normalized design tokens to the active stack. |
| `global_shell_apply` | Create or update LiveCanvas header/footer partials. |
| `page_upsert` | Create or update a LiveCanvas page and return URLs. |
| `create_dynamic_template` | Create a LiveCanvas dynamic template. |
| `update_dynamic_template` | Update a LiveCanvas dynamic template. |
| `site_foundation_run` | Orchestrate preflight, design system, shell, and starter pages. |

## Coding Agent Test Prompts

Use these from Codex, OpenCode, Cursor, Claude Code, or another MCP-connected client. They are ordered from simplest to more complex.

### 1. Connection Smoke Test

```text
Call the MCP tool livecanvas-forge_get_snapshot.
Return only the raw JSON result.
```

### 2. Site Audit Preview

```text
Call livecanvas-forge_run_lc_command with this JSON:

{
  "action": "site_audit",
  "dry_run": true
}

Return only the raw JSON result.
```

### 3. Create A Draft Smoke-Test Page

```text
Call livecanvas-forge_run_lc_command with this JSON:

{
  "action": "page_upsert",
  "title": "Forge Smoke Test",
  "slug": "forge-smoke-test",
  "status": "draft",
  "body_html": "<section class=\"py-5\"><div class=\"container\"><h1>Forge Smoke Test</h1><p>The MCP bridge is working.</p></div></section>"
}

Return the target_id, frontend_url, edit_url, and summary.
```

### 4. Preview A Header/Footer Update

```text
Call livecanvas-forge_run_lc_command with this JSON:

{
  "action": "global_shell_apply",
  "variant": "1",
  "dry_run": true,
  "header_html": "<header class=\"py-3\"><nav class=\"container d-flex justify-content-between\"><strong>Forge</strong><a href=\"/contact/\">Contact</a></nav></header>",
  "footer_html": "<footer class=\"py-4\"><div class=\"container\"><p>Built with LiveCanvas Forge AI.</p></div></footer>"
}

Return the summary, warnings, and diff_html.
```

### 5. Preview A Dynamic Template Assignment

```text
Call livecanvas-forge_run_lc_command with this JSON:

{
  "action": "create_dynamic_template",
  "title": "Single Service Template",
  "slug": "single-service-template",
  "status": "draft",
  "dry_run": true,
  "content": "<main><section class=\"py-5\"><div class=\"container\"><h1>[lc_the_title]</h1></div></section></main>",
  "template_assignment": {
    "target": "single",
    "post_type": "service"
  }
}

Return template_assignment and native_template_keys.
```

### 6. Preview A Foundation Run

```text
Call livecanvas-forge_run_lc_command with this JSON:

{
  "action": "site_foundation_run",
  "dry_run": true,
  "header_html": "<header><nav class=\"container py-3\">Forge Demo</nav></header>",
  "footer_html": "<footer><div class=\"container py-4\">Footer demo</div></footer>",
  "pages": [
    {
      "title": "Home",
      "slug": "home",
      "status": "draft",
      "body_html": "<section class=\"py-5\"><div class=\"container\"><h1>Home</h1><p>Foundation run preview.</p></div></section>"
    },
    {
      "title": "Contact",
      "slug": "contact",
      "status": "draft",
      "body_html": "<section class=\"py-5\"><div class=\"container\"><h1>Contact</h1><p>Contact page draft.</p></div></section>"
    }
  ]
}

Return the step summary and any warnings.
```

## LiveCanvas Editor Drawer Test Prompts

Use these inside the Forge drawer in the LiveCanvas editor. They are ordered from simplest to more complex.

### 1. Simple Page Edit

```text
Add a short intro paragraph below the first heading. Keep the existing layout and classes.
```

### 2. Add A Section

```text
Add a compact FAQ section with three questions at the end of this page.
```

### 3. Replace The Hero

```text
Replace the current hero with a clearer headline, one supporting paragraph, and one primary call-to-action button. Keep it compatible with the current framework.
```

### 4. Insert After Selected Section

Select a section in LiveCanvas, then send:

```text
Add a three-step timeline immediately after the selected section. Keep the same visual style as the page.
```

### 5. Add Pricing

```text
Add a pricing section with three plans: Starter, Pro, and Team. Include a highlighted middle plan and concise feature bullets.
```

### 6. Use A Screenshot Reference

Attach a screenshot in the Forge drawer, then send:

```text
Use the attached screenshot as a visual reference. Rework this section to match its spacing, hierarchy, and CTA structure while keeping the site's existing colors and framework classes.
```

### 7. Page-Level Refresh

```text
Refresh this page for a consulting business. Keep the current content intent, improve section order, add a stronger CTA before the footer, and avoid changing the global header or footer.
```

## Development Roadmap

### Phase 1: Foundation Contract

- stronger safety and policy behavior
- normalized page create/update flows
- local and remote execution parity
- simpler coding-agent connection UX

### Phase 2: Design-System Execution

- Picostrap token mapping
- Picowind/WindPress and DaisyUI integration
- custom-theme fallback assets
- clearer preview/apply behavior

### Phase 3: Global Shell

- header/footer partial create/update
- explicit variant support
- real-install partial discovery hardening

### Phase 4: Site Foundation Run

- preflight, design system, shell, and starter page orchestration
- Genesis task hydration into the Command Deck
- richer first-install workflows

### Phase 5: Dynamic Templates And Data-Aware Builds

- native LiveCanvas `is_*` template meta sync
- WooCommerce single/archive support
- custom post type support
- ACF-aware template generation

### Phase 6: Deeper LiveCanvas Editing Loop

- richer editor-side chat workflows
- more contextual LiveCanvas edits
- stronger screenshot-aware generation

## Repository Notes

- WordPress plugin entrypoint: [`livecanvas-forge-ai.php`](./livecanvas-forge-ai.php)
- MCP package: [`mcp/`](./mcp/)
- Foundation design spec: [`docs/superpowers/specs/2026-04-10-livecanvas-foundation-orchestrator-design.md`](./docs/superpowers/specs/2026-04-10-livecanvas-foundation-orchestrator-design.md)
- Current foundation plan: [`docs/superpowers/plans/2026-04-10-foundation-contract-phase-1.md`](./docs/superpowers/plans/2026-04-10-foundation-contract-phase-1.md)
