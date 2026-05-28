# LiveCanvas Forge AI

`LiveCanvas Forge AI` is a companion plugin for [LiveCanvas](https://livecanvas.com/) that lets coding agents work inside a real WordPress + LiveCanvas site.

It does not replace LiveCanvas. It handles structural work, agent integration, previews, page/template operations, and foundation setup while LiveCanvas remains the visual and code-level editing layer.

## Current Status

Status: `alpha`

Usable today:

- connect coding agents through the local MCP bridge or REST contract
- expose WordPress 7 Abilities and a custom WordPress MCP Adapter server when available
- inspect WordPress, LiveCanvas, Picostrap, Picowind, WindPress, WooCommerce, and ACF context
- run preview/apply operations from the Command Deck
- use dedicated preview/apply abilities for page upsert, global shell, design system, dynamic templates, and audit rollback
- create and update LiveCanvas pages with `page_upsert`
- create and update header/footer partials with `global_shell_apply`
- apply design-system tokens for Picostrap, Picowind/WindPress, or a custom-theme fallback
- run first-pass site foundation workflows with `site_foundation_run`
- create/update LiveCanvas dynamic templates and sync supported assignments to native `is_*` meta
- use the Forge drawer inside the LiveCanvas editor for prompt-driven edits and screenshot references
- queue LiveCanvas editor prompts with a preferred WordPress Ability contract for the connected coding agent
- inspect recent runs with audit IDs and restore stored rollback records for local apply operations
- use the PHP-rendered `Forge Studio` tab to inspect abilities, MCP write exposure, AI readiness, and audited runs
- consume the read-only `/wp-json/lcfa/v1/studio` state endpoint for a future React/DataViews Studio UI
- load a first progressive React `Forge Studio` shell that consumes the Studio endpoint and falls back to the PHP view if REST or WordPress admin JS dependencies are unavailable
- use the React Studio shell with search, filters, sorting, configurable columns, copy actions, and rollback shortcuts for abilities and runs
- persist Studio view preferences locally and provide refresh/reset/copy-state controls for the REST-backed Studio shell
- inspect selected abilities and runs from the React Studio sidebar, with copy JSON and rollback/deck shortcuts
- review Studio readiness alerts for setup gaps, MCP write exposure, AI/MCP diagnostics, and recent run errors
- review run-health analytics with action/origin mix, timeline, failures, audited runs, and rollback counts
- copy a compact ability manifest with MCP exposure, write/read-only flags, and input schema hints
- copy an operator briefing and read-only agent prompt generated from the current Studio state
- run an ordered agent smoke-test plan for read-only, preview, and write-guard verification
- copy a Markdown agent runbook with current state, guardrails, risks, next actions, and smoke-test order
- inspect Studio API contract metadata, section list, run limits, readiness flags, and SHA-256 payload fingerprint
- review a backend-calculated handoff readiness score with pass/warn/fail gates for agent delivery
- copy a virtual agent handoff package with runbook, smoke tests, briefing, readiness, ability manifest, write policy, and checksums
- fetch the same handoff package from the dedicated read-only `/wp-json/lcfa/v1/studio/handoff-package` endpoint

Still in progress:

- richer WooCommerce product/archive template generation
- ACF-aware markup generation
- broader real-site smoke testing across LiveCanvas/Picostrap/Picowind installs
- more creative screenshot-aware generation
- stronger remote/local parity testing for complex write workflows
- more complete fallback enqueue behavior for custom themes
- a complete DataViews-based Forge Studio UI; the first progressive React shell is now available

## What It Does

The plugin acts as a WordPress execution engine for coding agents.

```text
Coding Agent -> MCP or REST -> LiveCanvas Forge AI -> WordPress / LiveCanvas / WindPress
```

Main areas:

- `Setup`: project profile, framework, site mode, policy
- `Connections`: agent bootstrap for Codex, Cursor, Claude Code, OpenCode, and generic MCP clients
- `Genesis`: project brief and executable site plan
- `Forge Studio`: operational view for abilities, MCP exposure, AI readiness, runs, audit IDs, and rollback shortcuts
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
| `restore_audit_rollback` | Restore stored previous content for a local apply audit ID. |

WordPress 7 ability highlights:

| Ability | Purpose |
| --- | --- |
| `livecanvas-forge-ai/get-snapshot` | Read site and Forge runtime context. |
| `livecanvas-forge-ai/get-runs` | Read recent runs and rollback availability. |
| `livecanvas-forge-ai/get-agent-handoff-package` | Read a sanitized virtual handoff package for connected agents. |
| `livecanvas-forge-ai/preview-page-upsert` | Preview page create/update without writing. |
| `livecanvas-forge-ai/apply-page-upsert` | Apply page create/update with audit metadata. |
| `livecanvas-forge-ai/preview-global-shell` | Preview header/footer shell changes. |
| `livecanvas-forge-ai/apply-global-shell` | Apply header/footer shell changes. |
| `livecanvas-forge-ai/preview-block-pattern` | Convert supplied HTML into a native block pattern preview. |
| `livecanvas-forge-ai/restore-audit-rollback` | Restore a stored rollback by audit ID. |

Write abilities are not MCP-public by default. Under `Forge AI > Connections > Advanced settings`, enable the master write opt-in only for trusted MCP clients, then select the specific write abilities to expose in the allowlist.

The backend also exposes `GET /wp-json/lcfa/v1/studio` and `GET /wp-json/lcfa/v1/studio/handoff-package` for authenticated users or valid MCP tokens. Studio returns contract metadata, summary, readiness alerts, handoff readiness, briefing, runbook, smoke tests, ability diagnostics, manifest, MCP write policy, AI/MCP readiness, run-health analytics, and sanitized run/audit rows without exposing rollback payload content. The handoff-package endpoint returns only the copy-ready agent bundle and its fingerprint. MCP clients can also call `get_agent_handoff_package`.

## Example User Prompts For Coding Agents

Use these from Codex, OpenCode, Cursor, Claude Code, or another MCP-connected client. They are natural-language prompts, ordered from simplest to more complex.

### 1. Check The Integration

```text
Check that LiveCanvas Forge AI is connected correctly. Inspect the site context and tell me which WordPress theme, LiveCanvas stack, framework, and Forge capabilities are available. Do not change anything.
```

### 2. Audit Before Editing

```text
Audit this WordPress + LiveCanvas site before we make changes. Summarize the existing pages, header/footer partials, dynamic templates, design-system status, and anything that could block automated edits.
```

### 3. Create A Test Page

```text
Create a draft LiveCanvas page called "Forge Integration Test". Add a clean hero section, one short content section, and a final call-to-action. Keep it simple and return the frontend URL and editor URL.
```

### 4. Create A Page From A Short Brief

```text
Create a draft landing page for a consulting studio. The page should have a strong hero, three service cards, a short proof section, a FAQ, and a final contact CTA. Use the site's current framework and do not touch the global header or footer.
```

### 5. Generate A Design System From A Logo

```text
I uploaded a logo for the brand. Build a first design system from it: extract a primary color direction, secondary/accent colors, button style, heading style, spacing feel, and radius scale. Preview the design-system changes first and explain what will be applied before writing anything.
```

### 6. Apply A Brand Foundation

```text
Using the uploaded logo and the current site stack, create a brand foundation for a premium local services business. Apply the design system, create a matching header and footer, and generate draft Home, Services, About, and Contact pages. Preview everything first.
```

### 7. Create A Single Post Dynamic Template

```text
Create a LiveCanvas dynamic template for single blog posts. It should have a large hero with the post title, a wide featured image, author/date metadata, readable content spacing, related posts at the bottom, and a newsletter CTA. Assign it to single posts.
```

### 8. Create A Custom Post Type Template

```text
Create a dynamic template for the "service" custom post type. Each single service page should have a large title hero, optional featured image, key benefits, process steps, testimonial area, and a contact CTA. Assign it to single service posts and keep the markup compatible with the current framework.
```

### 9. Create A WooCommerce Product Template

```text
Create a first draft WooCommerce single product template. Use a large product image area, product title, price, add-to-cart area, short description, benefits section, product details, reviews anchor, and related products. Preview the template assignment before applying it.
```

### 10. Full Site Foundation From A Brief

```text
Build the first site foundation for a boutique architecture studio. Use the uploaded logo as brand reference. Create the design system, global header and footer, Home, Studio, Projects, Services, Journal, and Contact draft pages. Also create a single post template for Journal articles with a large featured image and editorial layout. Preview the full plan first, then apply only after I confirm.
```

## Example User Prompts In The LiveCanvas Editor

Use these inside the Forge drawer while editing a page in LiveCanvas. They are scoped to the current page or selected section.

### 1. Small Text Improvement

```text
Improve the copy in this section. Make it clearer and more direct, but keep the same layout, classes, and structure.
```

### 2. Add A Section

```text
Add a compact FAQ section with three questions at the end of this page.
```

### 3. Improve The Hero

```text
Rework this hero section with a stronger headline, a short supporting paragraph, one primary CTA, one secondary CTA, and better spacing. Keep the current colors and framework classes.
```

### 4. Use The Selected Section As An Anchor

Select a section in LiveCanvas, then send:

```text
Add a three-step process section immediately after the selected section. It should feel like part of the same page and should not duplicate existing content.
```

### 5. Add Pricing

```text
Add a pricing section with three plans: Starter, Pro, and Team. Make Pro the recommended plan, include concise feature bullets, and add a CTA button for each plan.
```

### 6. Match An Uploaded Screenshot

Attach a screenshot in the Forge drawer, then send:

```text
Use the uploaded screenshot as a visual reference for this section. Match the hierarchy, spacing, and CTA structure, but keep this site's colors, typography, and framework classes.
```

### 7. Add A Logo-Informed Brand Section

Upload the logo in the drawer, then send:

```text
Use this logo as brand reference and redesign the current section around it. Create a premium visual feel, choose supporting colors from the logo, and keep the section responsive in LiveCanvas.
```

### 8. Create A Rich Blog Hero

```text
Turn this top section into a blog-post hero with a large featured image area, category label, title, excerpt, author/date metadata, and a clean scroll path into the article content.
```

### 9. Page-Level Refresh

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
