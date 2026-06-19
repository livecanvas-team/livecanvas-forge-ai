# LiveCanvas AI Bridge

`LiveCanvas AI Bridge` is a companion plugin for [LiveCanvas](https://livecanvas.com/) that lets coding agents work inside a real WordPress + LiveCanvas site.

It does not replace LiveCanvas. It handles structural work, agent integration, previews, page/template operations, and foundation setup while LiveCanvas remains the visual and code-level editing layer.

## Current Status

Status: `alpha`

Usable today:

- connect coding agents through the local MCP bridge or REST contract
- use Codex Direct Mode through secure AI Bridge pairing, without storing a WordPress Application Password in Codex
- keep Codex connections site-bound with project-scoped `.codex/config.toml` files and a Site ID fingerprint
- expose WordPress 7 Abilities and a custom WordPress MCP Adapter server when available
- inspect WordPress, LiveCanvas, Picostrap, Picowind, WindPress, WooCommerce, and ACF context
- run preview/apply operations from the Command Deck
- use dedicated preview/apply abilities for page upsert, global shell, design system, dynamic templates, and audit rollback
- create and update LiveCanvas pages with `page_upsert`
- create and update header/footer partials with `global_shell_apply`
- apply design-system tokens for Picostrap, Picowind/WindPress, or a custom-theme fallback
- run first-pass site foundation workflows with `site_foundation_run`
- create/update LiveCanvas dynamic templates and sync supported assignments to native `is_*` meta
- use the AI Bridge drawer inside the LiveCanvas editor for prompt-driven edits and screenshot references
- queue LiveCanvas editor prompts with a preferred WordPress Ability contract for the connected coding agent
- inspect recent runs with audit IDs and restore stored rollback records for local apply operations
- use the PHP-rendered `AI Studio` tab to inspect abilities, MCP write exposure, AI readiness, and audited runs
- consume the read-only `/wp-json/lcfa/v1/studio` state endpoint for a future React/DataViews Studio UI
- load a first progressive React `AI Studio` shell that consumes the Studio endpoint and falls back to the PHP view if REST or WordPress admin JS dependencies are unavailable
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
- copy a first agent prompt from `Connections` that starts Codex/MCP clients with the connection handoff
- inspect the same first-prompt connection handoff inside `AI Studio` and the agent handoff package
- fetch only the connection handoff from `/wp-json/lcfa/v1/studio/connection-handoff` or the MCP `get_connection_handoff` tool
- export native WordPress AI Bridge block patterns with content, byte counts, and SHA-256 checksums for agent handoff
- read, run, inspect, and copy native page blueprint previews in AI Studio before composing WordPress block page previews
- review guarded agent smoke tests for native draft creation before exposing `apply-native-pattern-page`
- see handoff readiness ratios for read-only, preview, and guarded-write smoke tests
- use `forge-handoff-summary.json` inside handoff packages for quick agent decisions
- run the AI Studio integration test plan with copy-ready REST endpoints, MCP tools, and no-write preview checks
- review the Power Mode policy foundation; advanced filesystem, WP-CLI, upload, admin-link, and sandbox tools are prepared but not exposed in this release

Still in progress:

- richer WooCommerce product/archive template generation
- ACF-aware markup generation
- broader real-site smoke testing across LiveCanvas/Picostrap/Picowind installs
- more creative screenshot-aware generation
- stronger remote/local parity testing for complex write workflows
- more complete fallback enqueue behavior for custom themes
- a complete DataViews-based AI Studio UI; the first progressive React shell is now available

## What It Does

The plugin acts as a WordPress execution engine for coding agents.

```text
Coding Agent -> AI Bridge MCP or local MCP -> LiveCanvas AI Bridge -> WordPress / LiveCanvas / WindPress
```

Main areas:

- `Setup`: project profile, framework, site mode, policy
- `Connections`: agent bootstrap for Codex, Cursor, Claude Code, OpenCode, and generic MCP clients
- `Genesis`: project brief and executable site plan
- `AI Studio`: operational view for abilities, native page blueprints, MCP exposure, AI readiness, runs, audit IDs, and rollback shortcuts
- `Command Deck`: preview/apply console for structured operations
- `LiveCanvas editor drawer`: in-editor prompt surface for contextual page edits
- `MCP package`: local Node bridge in [`mcp/`](./mcp/)

## Product Family

- `LiveCanvas AI Bridge`: this plugin. It connects WordPress, LiveCanvas, Picostrap, Picowind, WindPress, and coding agents through safe read/preview/apply workflows.
- `LiveCanvas AI Vision`: planned premium extension for screenshot-to-code and URL-to-page rebuilds. It will analyze long screenshots or URLs, split pages into sections, extract a reusable design system, generate or map missing assets, and create editable Picowind/Tailwind pages through AI Bridge.

## Requirements

Recommended:

- WordPress
- LiveCanvas
- PHP compatible with your WordPress install
- Node.js for local MCP/coding-agent integrations and the WordPress MCP remote proxy
- Picostrap, Picowind/WindPress, or another active WordPress theme

## Installation

From `wp-content/plugins`:

```bash
git clone https://github.com/livecanvas-team/livecanvas-forge-ai.git
```

Then activate:

```text
WordPress Admin > Plugins > LiveCanvas AI Bridge > Activate
```

You can also upload a ZIP from:

```text
WordPress Admin > Plugins > Add New > Upload Plugin
```

The current build artifact is committed at:

```text
dist/livecanvas-forge-ai.zip
```

The plugin folder should be named:

```text
livecanvas-forge-ai
```

After activation, open:

```text
WordPress Admin > AI Bridge
```

If LiveCanvas is active, AI Bridge also appears inside the LiveCanvas admin area.

## Quick Start

1. Activate the plugin.
2. Open `WordPress Admin > AI Bridge`.
3. Complete the setup wizard.
4. Open `Connections`.
5. Use the default `Connect Codex` Direct Mode path, or open `Other clients`.
6. For Direct Mode, confirm the site URL and add a clear Codex project label.
7. Generate the secure Codex setup.
8. Prefer the Project TOML snippet and save it in the `.codex/config.toml` file of the Codex project for this WordPress site.
9. Restart/reload the MCP server and ask Codex to call `get_connection_handoff`.
10. Approve the pending Codex pairing request in WordPress, then run the smoke test.
11. Check the returned `site_identity.fingerprint` before write requests when you work across multiple sites.
12. Start with preview abilities or `dry_run: true`.
13. Apply only after the preview result looks correct.

For detailed MCP setup, see [`mcp/README.md`](./mcp/README.md). The preferred setup path is the in-plugin `Connections` wizard.

## Codex Multi-Site Safety

Codex can read MCP configuration from a project-level `.codex/config.toml`. AI Bridge now uses that as the preferred path for local Codex runtime setup and shows a Project TOML snippet for Direct Mode remote setup.

Recommended structure when you manage multiple sites:

```text
site-a/.codex/config.toml -> livecanvas-forge points to Site A
site-b/.codex/config.toml -> livecanvas-forge points to Site B
remote-client/.codex/config.toml -> livecanvas-ai-bridge points to the remote WordPress site URL
```

Each MCP config includes `LCFA_SITE_FINGERPRINT`. MCP requests send that fingerprint to WordPress. If a Codex chat is connected to a different site, AI Bridge rejects MCP write commands before they reach the Command Deck.

Remote Direct Mode uses a plugin-scoped AI Bridge session token. The token is created only after WordPress admin approval, stored hashed in WordPress, can be revoked from `Connections`, and is not a WordPress account credential. The old Application Password adapter remains available only under `Advanced/manual fallback`.

When you start a Codex session, first call:

```text
get_connection_handoff
```

or, when using the WordPress Ability adapter:

```text
livecanvas-forge-ai/get-connection-handoff
```

Then confirm:

```text
status: ready
site_identity.fingerprint: matches the Site ID shown in Connections
guardrail: read_only_first
```

Use the global Codex MCP entry only as an advanced fallback. A global `~/.codex/config.toml` entry is convenient, but it is easier to point the wrong Codex project at the wrong WordPress site.

## How To Test This Build

1. Activate the plugin and open `WordPress Admin > AI Bridge > AI Studio`.
2. Click `Refresh`, then open the `Integration test plan` panel.
3. Copy the REST endpoint checklist and verify the read-only endpoints return `200 OK`.
4. Click `Refresh summary` in `Handoff summary`; parity should become `verified` unless the backend state changed between requests.
5. In `Native page blueprints`, run `Run preview` first. This must not create content.
6. Only after the preview looks valid, use `Create draft page` to create a new draft page with rollback metadata.
7. For Codex/MCP, copy `Copy Codex smoke prompt` from the test plan and run it in the connected agent.

Minimum pass condition:

```text
Studio loads, handoff summary refreshes, parity is verified, native page preview succeeds without writing, and MCP can read get_connection_handoff + get_handoff_summary.
```

## Core Commands

Common `run_lc_command` actions:

| Action | Purpose |
| --- | --- |
| `site_audit` | Inspect the site, stack, inventory, and capabilities. |
| `site_prepare` | Check readiness before larger foundation work. |
| `design_system_apply` | Apply normalized design tokens to the active stack. |
| `global_shell_apply` | Create or update LiveCanvas header/footer partials. |
| `page_upsert` | Create or update a LiveCanvas page and return URLs. |
| `update_partial` | Update a reusable LiveCanvas partial that is not header/footer. |
| `create_dynamic_template` | Create a LiveCanvas dynamic template. |
| `update_dynamic_template` | Update a LiveCanvas dynamic template. |
| `site_foundation_run` | Orchestrate preflight, design system, shell, and starter pages. |
| `restore_audit_rollback` | Restore stored previous content for a local apply audit ID. |

WordPress 7 ability highlights:

| Ability | Purpose |
| --- | --- |
| `livecanvas-forge-ai/get-snapshot` | Read site and AI Bridge runtime context. |
| `livecanvas-forge-ai/get-runs` | Read recent runs and rollback availability. |
| `livecanvas-forge-ai/get-connection-handoff` | Read the first agent prompt and connection guardrails. |
| `livecanvas-forge-ai/get-handoff-summary` | Read compact readiness, blocker, warning, and next-action metadata. |
| `livecanvas-forge-ai/get-agent-handoff-package` | Read a sanitized virtual handoff package for connected agents. |
| `livecanvas-forge-ai/get-block-pattern-library` | Read export-ready native block patterns with checksums. |
| `livecanvas-forge-ai/get-native-pattern-page-blueprints` | Read no-write native page blueprint recipes. |
| `livecanvas-forge-ai/preview-page-upsert` | Preview page create/update without writing. |
| `livecanvas-forge-ai/apply-page-upsert` | Apply page create/update with audit metadata. |
| `livecanvas-forge-ai/preview-global-shell` | Preview header/footer shell changes. |
| `livecanvas-forge-ai/apply-global-shell` | Apply header/footer shell changes. |
| `livecanvas-forge-ai/preview-command` | Run any supported AI Bridge command as a forced dry-run preview. |
| `livecanvas-forge-ai/preview-block-pattern` | Convert supplied HTML into a native block pattern preview. |
| `livecanvas-forge-ai/preview-native-pattern-page` | Compose a native block page preview from AI Bridge patterns. |
| `livecanvas-forge-ai/apply-native-pattern-page` | Create a new draft native WordPress page from AI Bridge patterns. |
| `livecanvas-forge-ai/restore-audit-rollback` | Restore a stored rollback by audit ID. |

Read and preview abilities are MCP-public by default. Curated write abilities are also enabled by default for paired AI Bridge agents so they can create and update content after previews. Under `AI Bridge > Connections > Advanced settings`, review the master write switch and per-ability allowlist; disable the switch or uncheck individual abilities when a client should stay read/preview-only.

The backend also exposes `GET /wp-json/lcfa/v1/studio`, `GET /wp-json/lcfa/v1/studio/connection-handoff`, `GET /wp-json/lcfa/v1/studio/handoff-summary`, `GET /wp-json/lcfa/v1/studio/block-pattern-library`, `GET /wp-json/lcfa/v1/studio/native-pattern-page-blueprints`, `POST /wp-json/lcfa/v1/studio/native-pattern-page-preview`, `POST /wp-json/lcfa/v1/studio/native-pattern-page-apply`, and `GET /wp-json/lcfa/v1/studio/handoff-package` for authenticated users or valid MCP tokens. Studio returns contract metadata, summary, readiness alerts, connection handoff, handoff summary, block pattern library, native page blueprints, handoff readiness, briefing, runbook, smoke tests, ability diagnostics, manifest, MCP write policy, AI/MCP readiness, run-health analytics, and sanitized run/audit rows without exposing rollback payload content. The connection-handoff endpoint returns only the first-prompt bootstrap. The handoff-summary endpoint returns only compact status, score, blocker, warning, missing-test, write-guard, and next-action metadata. The block-pattern-library endpoint returns only export-ready native patterns. The native-pattern-page-blueprints endpoint returns page recipes plus copy-ready preview/apply requests. The native-pattern-page preview endpoint composes block content from registered patterns without writing. The native-pattern-page apply endpoint creates a new draft native page and records a rollback reference. AI Studio can run the preview, create the draft from the blueprint panel, refresh audit state, and open the rollback flow for the created draft. MCP clients can also call `get_connection_handoff`, `get_handoff_summary`, `get_block_pattern_library`, `get_native_pattern_page_blueprints`, `preview_native_pattern_page`, `apply_native_pattern_page`, and `get_agent_handoff_package`.

## Example User Prompts For Coding Agents

Use these from Codex, OpenCode, Cursor, Claude Code, or another MCP-connected client. They are natural-language prompts, ordered from simplest to more complex.

### 1. Check The Integration

```text
Check that LiveCanvas AI Bridge is connected correctly. Inspect the site context and tell me which WordPress theme, LiveCanvas stack, framework, and AI Bridge capabilities are available. Do not change anything.
```

### 2. Audit Before Editing

```text
Audit this WordPress + LiveCanvas site before we make changes. Summarize the existing pages, header/footer partials, dynamic templates, design-system status, and anything that could block automated edits.
```

### 3. Create A Test Page

```text
Create a draft LiveCanvas page called "AI Bridge Integration Test". Add a clean hero section, one short content section, and a final call-to-action. Keep it simple and return the frontend URL and editor URL.
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

Use these inside the AI Bridge drawer while editing a page in LiveCanvas. They are scoped to the current page or selected section.

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

Attach a screenshot in the AI Bridge drawer, then send:

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
