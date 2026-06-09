# LiveCanvas Forge AI WordPress 7 Redesign Plan

## Purpose

This document defines the redesign of LiveCanvas Forge AI for WordPress 7.0+.

The current plugin is a LiveCanvas-aware execution bridge for coding agents. It exposes a custom REST contract, a local Node MCP server, a WordPress admin Command Deck, a LiveCanvas editor drawer, and stack-specific helpers for Picostrap, Picowind, WindPress, ACF, WooCommerce, and dynamic templates.

WordPress 7.0 changes the integration surface. The plugin should move from a mostly custom agent bridge to a WordPress-native AI orchestration layer built on:

- WordPress Abilities API
- WordPress MCP Adapter
- WordPress AI Client
- WordPress Connectors API
- Client-Side Abilities
- modern WordPress admin/editor surfaces

The goal is not to remove LiveCanvas integration. The goal is to make LiveCanvas Forge AI a first-class WordPress 7.0 AI plugin while keeping the current LiveCanvas-specific strengths.

## Current Architecture

Current primary path:

```text
Codex / Claude / Cursor / OpenCode
  -> LiveCanvas Forge custom MCP Node server
  -> LiveCanvas Forge custom REST API
  -> Command Deck / Genesis / Design System / LiveCanvas adapters
  -> WordPress / LiveCanvas / WindPress / theme files
```

Current strengths:

- agent-readable site context
- LiveCanvas page, partial, and dynamic template operations
- preview/apply command flow
- WindPress and Picowind awareness
- Picostrap design token support
- LocalWP and Codex setup helpers
- LiveCanvas editor drawer with prompt queue

Current limits:

- remote Codex requires manual setup and cannot use remote filesystem operations safely
- custom MCP duplicates functionality that WordPress 7.0 now standardizes
- AI provider/model configuration is plugin-specific instead of WordPress-native
- REST routes and MCP tools are tightly coupled
- admin UI is PHP-rendered and hard to evolve
- read/write permissions are mostly coarse-grained
- ability discovery is custom instead of shared across WordPress agents

## Target Architecture

Target primary path:

```text
Codex / Claude / Cursor / OpenCode
  -> WordPress MCP Adapter
  -> Forge Abilities
  -> Forge Orchestrator
  -> LiveCanvas / WordPress / WindPress / theme files
```

Target in-admin path:

```text
WordPress Admin / Site Editor / LiveCanvas Editor
  -> Client-Side Abilities / Forge Studio
  -> Forge Orchestrator
  -> preview/apply/rollback
```

Target AI generation path:

```text
Forge Planner / Prompt Suggester / Design System Composer
  -> wp_ai_client_prompt()
  -> WordPress Connectors
  -> provider/model selected by site owner
```

The custom MCP Node package remains, but it becomes a legacy/local-specialized runtime for:

- LocalWP stdio clients
- local WindPress compilation
- local filesystem reads and writes when the WordPress root is available
- backwards compatibility for WordPress 6.9 and current clients

## Design Principles

- WordPress-native first on WordPress 7.0+.
- Legacy compatible on WordPress 6.9 where practical.
- No duplicate AI credential storage when Connectors are available.
- All agent operations must be schema-described.
- Read, preview, apply, and destructive operations must be separate.
- Remote WordPress must work without direct filesystem access.
- Local filesystem operations must require an explicit local root.
- Every write path must support preview, audit logging, and rollback where possible.
- LiveCanvas remains the visual editing layer; Forge AI remains the automation and orchestration layer.

## Core Modules

### Forge Orchestrator

New shared service that owns command execution. It replaces the current pattern where REST routes, MCP tools, Genesis tasks, and admin forms each shape payloads independently.

Responsibilities:

- normalize payloads
- validate action policy
- detect target stack
- run preview/apply
- collect warnings
- write audit entries
- return a stable result envelope

Initial implementation can wrap `LCFA_Command_Deck::execute()` and gradually absorb its logic.

### Forge Abilities

WordPress Abilities are the public contract for WordPress 7.0 integrations.

Ability groups:

- `read`: snapshot, inventory, context, page HTML, theme context, actions
- `preview`: validate markup, preview page update, preview global shell, preview design system
- `apply`: apply page update, create page, update partial, create template, apply design system
- `build`: build WindPress cache, compile Picostrap bundle
- `admin`: repair Codex config, rotate MCP token, inspect diagnostics

Ability naming:

```text
livecanvas-forge-ai/get-snapshot
livecanvas-forge-ai/get-inventory
livecanvas-forge-ai/get-context
livecanvas-forge-ai/get-page-html
livecanvas-forge-ai/list-command-actions
livecanvas-forge-ai/preview-command
livecanvas-forge-ai/apply-command
```

Default MCP exposure:

- public by default: read-only abilities
- not public by default: preview/write/admin abilities
- custom MCP server can expose selected write abilities after explicit admin opt-in

### Forge MCP Adapter Integration

Preferred WordPress 7.0 agent connection:

```text
Agent -> WordPress MCP Adapter HTTP/STDIO -> Forge Abilities
```

Implementation strategy:

- register Forge abilities with `meta.mcp.public` for safe read-only tools
- detect whether `wordpress/mcp-adapter` is available
- optionally register a custom Forge MCP server that exposes curated abilities directly
- add a backend wizard for remote Codex using MCP Adapter
- keep current Node MCP as legacy/local runtime

Remote Codex should no longer depend on the plugin's local Node package for basic site operations.

### Forge AI Client

Use WordPress AI Client when available.

Responsibilities:

- build prompts for Genesis planning
- generate structured design-system candidates
- rewrite selected LiveCanvas sections
- generate metadata, summaries, image prompts, and content variants
- enforce budget and capability policies

Primary API:

```php
$result = wp_ai_client_prompt( $prompt )
    ->as_json_response( $schema )
    ->generate_text();
```

Fallback:

- keep current external/coding-agent workflows for WordPress versions without AI Client
- do not add new provider-specific credential storage

### Forge Connectors

Forge should not be an AI provider key manager on WordPress 7.0+.

Settings should store:

- preferred AI task profile
- speed/intelligence preset
- default connector/provider preference if core exposes that selection
- prompt governance settings

Settings should not store:

- OpenAI API key
- Anthropic API key
- Google API key

Those belong in `Settings > Connectors`.

### Forge Studio

Modern backend UI replacing most of the large PHP-rendered admin page.

Recommended sections:

- Dashboard
- Connections
- Abilities
- Command Deck
- Genesis
- Runs / Audit Log
- Diagnostics
- Settings

Implementation target:

- React-based WordPress admin app
- DataViews for runs, pages, templates, abilities, diagnostics
- DataForms for settings and connector selection
- command palette actions where available

### LiveCanvas Editor Adapter

The drawer remains but becomes an abilities client.

Responsibilities:

- detect current post/section
- upload screenshot or logo reference
- queue frontend prompt
- call preview ability
- show diff/summary
- apply only after confirmation

The editor drawer should no longer know low-level command details.

### Blocks And Patterns Layer

WordPress 7.0 makes it more valuable to generate native blocks and patterns.

New capabilities:

- register Forge PHP-only blocks for common UI primitives
- generate block patterns from design-system tokens
- convert selected LiveCanvas sections to reusable patterns when safe
- support block bindings for dynamic values
- keep LiveCanvas as the target for pages/templates where LiveCanvas is active

## Ability Safety Model

Every ability must declare:

- `readonly`: true/false
- `destructive`: true/false
- `idempotent`: true/false
- required capability
- whether it is MCP-public by default
- whether it supports dry-run
- whether rollback is available

Suggested defaults:

| Ability class | Capability | MCP public | Requires confirmation |
| --- | --- | --- | --- |
| Read-only | `edit_pages` | yes | no |
| Preview | `edit_pages` | optional | no |
| Apply content | `edit_pages` | no | yes |
| Theme files | `edit_theme_options` | no | yes |
| Admin/config | `manage_options` | no | yes |

## Remote WordPress Strategy

Remote mode has three tiers.

### Tier 1: REST/Ability Remote

Works without filesystem access.

Supported:

- snapshot
- inventory
- context
- page create/update
- LiveCanvas content edits
- dynamic template edits
- design-system preview
- selected WindPress REST operations

### Tier 2: Remote MCP Adapter

Preferred Codex remote mode on WordPress 7.0+.

Supported:

- ability discovery
- ability execution
- authenticated HTTP transport
- no local Node package required for basic operations

### Tier 3: Local Mirror Or Mount

Required for:

- direct theme file writes
- local WindPress compiler
- local Sass/Picostrap compile operations
- filesystem backup restore

This tier must require explicit `LCFA_WP_ROOT` or a configured local project root.

## Compatibility Matrix

| Environment | Target behavior |
| --- | --- |
| WP 7.0+ with MCP Adapter | primary path via Abilities and MCP Adapter |
| WP 7.0+ without MCP Adapter | abilities registered; prompt admin to install MCP Adapter |
| WP 7.0+ with Connectors | AI Client path enabled |
| WP 7.0+ without configured connector | show setup requirement, no plugin key storage |
| WP 6.9 | Abilities may be available, but AI Client/Connectors fallback needed |
| WP <= 6.8 | legacy REST/MCP only or unsupported in future major version |

## Migration Plan

### Phase 0: Compatibility Baseline

Deliverables:

- document target architecture
- add `LCFA_Ability_Registry`
- register safe read-only abilities when Abilities API exists
- expose abilities manifest in PHP tests
- keep existing REST/MCP unchanged

Exit criteria:

- no behavior regression on current WordPress
- no fatal when Abilities API is missing
- read-only abilities can be discovered by MCP Adapter when installed

### Phase 1: Read-Only Ability Parity

Deliverables:

- convert these MCP tools to WordPress abilities:
  - `get_snapshot`
  - `get_inventory`
  - `get_context`
  - `get_theme_context`
  - `get_page_html`
  - `list_command_actions`
  - `get_mcp_status`
  - `get_windpress_status`
- add source-of-truth schemas
- add ability diagnostics to Connections tab

Exit criteria:

- Codex can inspect a remote WP 7.0 site through MCP Adapter
- current Node MCP still works

### Phase 2: Preview Ability Parity

Deliverables:

- `preview-command`
- `validate-markup-for-framework`
- `preview-page-upsert`
- `preview-global-shell`
- `preview-design-system`

Exit criteria:

- agents can ask for preview safely through WordPress MCP Adapter
- preview responses include diffs, warnings, target URLs, and required next actions

### Phase 3: Controlled Apply Abilities

Deliverables:

- `apply-page-upsert`
- `apply-global-shell`
- `apply-dynamic-template`
- `apply-design-system`
- audit log
- rollback references

Exit criteria:

- write abilities are disabled by default for MCP
- enabling write abilities requires admin opt-in
- every write returns an audit ID and rollback availability

### Phase 4: AI Client Migration

Deliverables:

- `LCFA_AI_Client`
- Genesis planner through `wp_ai_client_prompt()`
- prompt suggester through AI Client
- design-system compose through AI Client
- connector diagnostics

Exit criteria:

- no plugin-specific provider keys on WP 7.0+
- clear admin state when no connector is configured

### Phase 5: Forge Studio UI

Deliverables:

- React admin app
- DataViews for abilities, runs, plans, diagnostics
- modern Connections wizard
- remote Codex wizard using MCP Adapter

Exit criteria:

- PHP admin rendering is reduced to shell/bootstrap
- current workflows are preserved

### Phase 6: Blocks And Patterns

Deliverables:

- PHP-only Forge blocks
- pattern generator
- native block fallback for non-LiveCanvas sites
- pattern library export/import

Exit criteria:

- Forge can build useful WP-native pages even when LiveCanvas is absent
- LiveCanvas-specific paths remain optimized

## Immediate Development Slice

The first implementation slice is intentionally small and safe:

1. Add this redesign document.
2. Add `LCFA_Ability_Registry`.
3. Register a Forge ability category.
4. Register safe read-only abilities:
   - `livecanvas-forge-ai/get-snapshot`
   - `livecanvas-forge-ai/get-inventory`
   - `livecanvas-forge-ai/get-context`
   - `livecanvas-forge-ai/get-page-html`
   - `livecanvas-forge-ai/list-command-actions`
5. Mark read-only abilities MCP-public.
6. Add a custom MCP server registration hook when the MCP Adapter package is present.
7. Add tests that verify no fatal behavior without WordPress 7 APIs and correct ability registration with stubs.

### Implemented In The First Development Pass

The current branch now includes:

- `LCFA_Ability_Registry`
- WordPress 7 Abilities hooks with no fatal behavior when the Abilities API is unavailable
- optional custom MCP Adapter server registration when `wordpress/mcp-adapter` is installed
- public read-only/default-safe abilities:
  - `livecanvas-forge-ai/get-snapshot`
  - `livecanvas-forge-ai/get-inventory`
  - `livecanvas-forge-ai/get-context`
  - `livecanvas-forge-ai/get-theme-context`
  - `livecanvas-forge-ai/get-page-html`
  - `livecanvas-forge-ai/list-command-actions`
  - `livecanvas-forge-ai/get-mcp-status`
  - `livecanvas-forge-ai/get-windpress-status`
  - `livecanvas-forge-ai/get-ai-client-status`
  - `livecanvas-forge-ai/validate-markup-for-framework`
- non-public controlled abilities:
  - `livecanvas-forge-ai/preview-command`
  - `livecanvas-forge-ai/apply-command`
  - `livecanvas-forge-ai/generate-ai-text`
- `LCFA_AI_Client`, a small WordPress AI Client wrapper that:
  - detects whether `wp_ai_client_prompt()` is available
  - checks text-generation support without making an AI call
  - sends server-side text prompts through configured WordPress Connectors when available
  - supports system instruction, temperature, max tokens, model preference, and JSON response schema
  - parses structured JSON responses for planning workflows
- PHP tests for the Abilities registry and AI Client wrapper.

Write and arbitrary AI-generation abilities are intentionally not MCP-public by default.

### Implemented In The Second Development Pass

The next block now includes:

- Genesis Planner can use WordPress AI Client to enrich the deterministic build plan.
  - The deterministic planner remains the source of truth for executable payloads.
  - AI can refine page descriptions, task labels/descriptions/prompts, and add advisory tasks without write payloads.
  - The generated plan now includes an `ai` metadata block with availability, usage, provider, message, and advisories.
- Design System Compose can use WordPress AI Client for Picostrap token refinement.
  - The deterministic Picostrap composer still runs first and remains the fallback.
  - AI output is schema-constrained and sanitized against supported Picostrap token buckets.
  - Unsupported AI keys such as `accent` are dropped before reaching `design_system_apply`.
  - AI usage is reported under `data.ai_client`.
- Added tests for:
  - AI Client structured JSON parsing
  - Genesis AI enrichment
  - Design System AI token enhancement

### Implemented In The Third Development Pass

The remote Codex block now includes:

- remote Forge snapshots report WordPress MCP Adapter availability and the custom Forge MCP URL
- remote status now carries MCP Adapter metadata back to the local Connections wizard
- Codex remote bundle generation now uses the WordPress MCP Adapter remote proxy:
  - command: `npx -y @automattic/mcp-wordpress-remote@latest`
  - env: `WP_API_URL`, `WP_API_USERNAME`, `WP_API_PASSWORD`, `LOG_FILE`
- remote Codex no longer reuses the local Forge Node MCP command or `LCFA_WP_ROOT`
- the Connections wizard shows MCP Adapter URL, remote user, and remote proxy details instead of local REST token details for Codex remote mode
- Codex remote setup is promoted to a copy/run shortcut flow, matching the local Codex wizard ergonomics
- the smoke-test helper for remote Codex checks Codex MCP registration instead of appending local bridge CLI flags to the remote proxy package
- added tests for:
  - remote Codex MCP Adapter bundle generation
  - remote Codex wizard copy flow
  - MCP Adapter status detection and custom URL reporting

### Implemented In The Fourth Development Pass

The preview ability parity block now includes:

- dedicated MCP-public preview abilities that always force `dry_run=true`:
  - `livecanvas-forge-ai/preview-page-upsert`
  - `livecanvas-forge-ai/preview-global-shell`
  - `livecanvas-forge-ai/preview-design-system`
- `livecanvas-forge-ai/get-ability-diagnostics` for ability inventory, MCP exposure, preview exposure, and MCP Adapter status
- the generic `preview-command` remains non-public to avoid exposing the full Command Deck surface by default
- `apply-command` and arbitrary AI text generation remain non-public
- preview results now carry `required_next_actions` when the command succeeds
- WordPress Ability provenance is preserved in Command Deck results instead of being normalized back to admin/browser origin

### Implemented In The Fifth Development Pass

The controlled apply/audit block now includes:

- successful local and remote apply results receive an `audit_id`
- write apply results include `data.audit` with action, target, execution target, provenance, rollback availability, and rollback reference metadata
- page, partial, header, footer, dynamic template, and global shell applies report rollback availability when a usable previous-content or created-post reference exists
- read actions do not receive write audit IDs
- command history entries now include `audit_id` and `rollback_available`
- added focused tests for preview next actions, ability provenance, apply audit envelope, and rollback reference metadata

### Implemented In The Sixth Development Pass

The diagnostics and WordPress-native patterns block now includes:

- Connections secondary panels include an Ability diagnostics card loaded asynchronously with the existing remote/advanced panels
- Ability diagnostics show:
  - total registered Forge abilities
  - MCP-public ability count
  - MCP-public preview ability count
  - whether any destructive write ability is exposed publicly
  - MCP Adapter custom server URL when available
- `LCFA_Block_Patterns`, a first WordPress-native fallback layer:
  - PHP-only dynamic block: `livecanvas-forge-ai/section-shell`
  - pattern category: `livecanvas-forge-ai`
  - native patterns:
    - `livecanvas-forge-ai/conversion-hero`
    - `livecanvas-forge-ai/feature-grid`
- public read-only ability `livecanvas-forge-ai/get-block-patterns` exposes the block/pattern manifest to agents
- added tests for block registration, pattern registration, dynamic block rendering, pattern manifest, and admin diagnostics rendering

### Implemented In The Seventh Development Pass

The controlled write, rollback, and LiveCanvas adapter block now includes:

- explicit admin opt-in and per-ability allowlist for MCP-public write abilities:
  - default: disabled
  - enabled from `Connections > Advanced settings`
  - destructive ability exposure is controlled one ability at a time
  - an enabled master switch with an empty allowlist exposes no write abilities
  - generic `apply-command` remains non-public even when opt-in is enabled
- dedicated write abilities:
  - `livecanvas-forge-ai/apply-page-upsert`
  - `livecanvas-forge-ai/apply-global-shell`
  - `livecanvas-forge-ai/apply-dynamic-template`
  - `livecanvas-forge-ai/apply-design-system`
  - `livecanvas-forge-ai/restore-audit-rollback`
- real local rollback storage:
  - previous post content is stored privately by audit ID
  - created posts can be moved to trash through rollback
  - rollback restore supports preview/apply from the Command Deck
- run/audit discovery:
  - `livecanvas-forge-ai/get-runs` exposes recent run metadata without leaking rollback content
  - Command Deck history shows audit IDs and a restore shortcut for local rollback-ready runs
- AI/Connector diagnostics:
  - AI Client status now reports JSON/model-preference support and connector registry counts when available
  - Connections ability diagnostics display AI text readiness and connector counts
- native pattern previews:
  - `livecanvas-forge-ai/preview-block-pattern` wraps supplied HTML as a WordPress block pattern preview without registering or writing
- LiveCanvas editor queue contracts:
  - frontend prompt requests now carry preferred preview/apply ability names for the selected action
  - coding agents can use dedicated abilities instead of inferring from generic command payloads
- added tests for:
  - MCP write opt-in
  - dedicated apply abilities
  - rollback storage and restore
  - run history ability
  - connector diagnostics
  - block pattern preview generation
  - frontend queue ability contracts

### Implemented In The Eighth Development Pass

The first Forge Studio admin surface now includes:

- a dedicated `Forge Studio` dashboard tab between Genesis and Command Deck
- a Studio overview with ability count, MCP-public count, public write count, recent run count, rollback count, framework, MCP Adapter state, and AI text readiness
- an abilities panel that lists registered Forge abilities with MCP public/private, read/write, destructive, and idempotent state
- an MCP write policy panel that shows:
  - master write opt-in state
  - per-ability allowlist count
  - currently exposed destructive ability count
  - a direct link back to `Connections` for allowlist edits
- a Runs & Audit panel that shows recent command history, audit IDs, rollback-ready state, and restore shortcuts routed through the existing Command Deck flow
- focused admin tests for the Studio tab, ability list, write policy, and rollback shortcut rendering

### Implemented In The Ninth Development Pass

The Forge Studio data/bootstrap block now includes:

- read-only `GET /wp-json/lcfa/v1/studio` endpoint for authenticated users or valid MCP tokens
- Studio REST payload with:
  - summary counts for abilities, MCP-public abilities, public writes, runs, rollbacks, framework, setup, MCP Adapter, and AI text readiness
  - ability diagnostics from `LCFA_Ability_Registry`
  - MCP write policy with master state, allowlist, available writes, exposed writes, and counts
  - sanitized run/audit rows that keep audit IDs and rollback availability but do not expose stored rollback payload content
- localized admin config for the Studio endpoint and REST nonce
- progressive client-side Forge Studio filters:
  - ability search
  - MCP public/private/write/destructive filters
  - run search
  - rollback/error/apply filters
- tests for the REST Studio state payload, admin Studio controls, and admin JS filter bootstrap

### Implemented In The Tenth Development Pass

The first React-backed Forge Studio shell now includes:

- `assets/studio-app.js`, a no-build WordPress admin React shell using `wp.element` and `wp.apiFetch`
- localized `lcfaStudio` config with:
  - Studio REST endpoint
  - REST nonce
  - Connections and Command Deck links
  - loading/error labels
- progressive enhancement in the Studio tab:
  - React app mounts into `data-lcfa-studio-app-root`
  - PHP-rendered Studio remains as `data-lcfa-studio-fallback`
  - fallback is hidden only after the React app successfully loads REST state
  - fallback stays visible if React, API fetch, or the endpoint fails
- React-rendered Studio panels for:
  - overview summary
  - ability explorer
  - MCP write policy
  - runs and audit list
- static tests that verify the Studio app asset, WordPress dependencies, app root, fallback contract, and key React panels

### Implemented In The Eleventh Development Pass

The Forge Studio React shell now moves closer to a DataViews-style experience:

- ability explorer supports:
  - search
  - public/private/write/destructive filters
  - sorting by label, name, MCP exposure, and read/write kind
  - configurable name/exposure/kind/actions columns
  - copy ability-name action
- runs explorer supports:
  - search
  - rollback/error/apply filters
  - sorting by newest, action, status, and rollback availability
  - configurable summary/status/audit/actions columns
  - copy audit ID action
  - restore and Command Deck shortcuts
- shared React helpers for list sorting and column toggling
- CSS for Studio select controls and column toggles
- static tests updated to verify sorting, column controls, and action affordances

### Implemented In The Twelfth Development Pass

The Studio shell now preserves operator view state and exposes manual refresh controls:

- local `lcfaStudio.*` preference storage for:
  - ability filter
  - ability sort
  - ability columns
  - run filter
  - run sort
  - run columns
- resilient storage helpers that no-op when `localStorage` is unavailable
- manual REST refresh button with loading state
- reset-view action that clears persisted Studio preferences and restores defaults
- copy-state action that copies the sanitized Studio REST payload currently loaded in the app
- generated-at metadata from the Studio endpoint is displayed in the overview card
- static tests updated for persisted preferences, refresh/reset, and copy-state controls

### Implemented In The Thirteenth Development Pass

The Studio shell now includes a detail inspector:

- ability rows expose an `Inspect` action
- run rows expose an `Inspect` action
- selected rows are visually highlighted
- sidebar Inspector panel shows selected ability metadata:
  - ability name
  - MCP public/private state
  - read/write state
  - destructive/non-destructive state
  - copy ability JSON action
- sidebar Inspector panel shows selected run metadata:
  - summary/message
  - action and mode
  - OK/error state
  - audit ID
  - rollback availability
  - copy run JSON action
  - restore shortcut when rollback is locally available
  - Command Deck shortcut
- inspector uses only sanitized data already returned by `/lcfa/v1/studio`
- static tests updated for inspector helpers, inspect actions, and copy JSON affordances

### Implemented In The Fourteenth Development Pass

The Studio shell now surfaces readiness signals instead of requiring operators to infer them from raw state:

- `/wp-json/lcfa/v1/studio` returns an `alerts` list with sanitized severity, code, title, and message fields
- readiness alerts cover setup completion, ability registration, MCP Adapter availability, AI text readiness, MCP write allowlist exposure, and recent run errors
- the Studio summary now includes `run_errors` and the MCP write master state
- the React sidebar includes a Readiness panel with alert cards and AI/MCP connector chips
- operators can copy the current diagnostics bundle from the Readiness panel
- tests cover alert codes, run-error counts, public write exposure, and the new React readiness affordances

### Implemented In The Fifteenth Development Pass

The Studio shell now includes run-health analytics:

- `/wp-json/lcfa/v1/studio` returns a `run_analysis` object derived only from sanitized run metadata
- run analysis includes totals for successful runs, failed runs, apply/preview modes, audited runs, and rollback-ready runs
- run analysis groups recent activity by action, mode, and origin, with per-group error counts
- recent error metadata is exposed without rollback payload content
- the React Studio shell renders a Run Health panel with:
  - metric tiles
  - action mix
  - origin mix
  - recent timeline
  - copy run-analysis action
- tests cover run-analysis totals, action error grouping, sanitized recent errors, and the new React panel affordances

### Implemented In The Sixteenth Development Pass

The Studio shell now exposes a compact MCP ability manifest:

- `/wp-json/lcfa/v1/studio` returns `ability_manifest`
- the manifest is built from the registered `LCFA_Ability_Registry` manifest when available
- minimal runtimes fall back to the existing ability diagnostics list
- each compact entry includes:
  - name, label, description, and category
  - MCP public/private state
  - read/write, destructive, and idempotent flags
  - REST visibility
  - input schema type
  - required input fields
  - input property names and count
- the React Studio shell renders an Ability Manifest panel with copy/export support and schema hints
- tests cover diagnostics fallback, manifest counts, ability names, schema fallback, and the React affordances

### Implemented In The Seventeenth Development Pass

The Studio shell now produces an operator briefing for agent handoff:

- `/wp-json/lcfa/v1/studio` returns `operator_briefing`
- the briefing is generated from sanitized summary, readiness alerts, ability manifest, MCP write policy, and run-health analytics
- the briefing includes:
  - concise state summary lines
  - active risk list
  - recommended next actions
  - copy-ready read-only agent prompt
- the generated agent prompt instructs connected coding agents to start with read-only abilities before any apply workflow
- rollback payload content is not included in the briefing or prompt
- the React Studio shell renders an Operator Briefing panel with copy actions for the prompt and full briefing
- tests cover briefing title, read-only prompt content, risk/action codes, rollback-payload exclusion, and React affordances

### Implemented In The Eighteenth Development Pass

The Studio shell now includes an agent smoke-test plan:

- `/wp-json/lcfa/v1/studio` returns `agent_smoke_tests`
- the plan is generated from the compact ability manifest, summary, and MCP write policy
- smoke tests are ordered as read-only first, then preview checks, then explicit write guards
- planned checks include:
  - snapshot handshake
  - ability diagnostics
  - recent sanitized runs
  - framework validation preview
  - page upsert preview
  - write ability guard
- each smoke test includes ability name, phase, intent, example payload, expected result, availability, and public-write exposure where relevant
- the React Studio shell renders an Agent Smoke Tests panel with copy actions for the whole plan and individual test payloads
- tests cover smoke-test mode, counts, available fallback abilities, write-guard exposure, rollback-payload exclusion, and React affordances

### Implemented In The Nineteenth Development Pass

The Studio shell now generates a Markdown agent runbook:

- `/wp-json/lcfa/v1/studio` returns `agent_runbook`
- the runbook is generated from sanitized summary, operator briefing, agent smoke tests, ability manifest, and MCP write policy
- the Markdown runbook includes:
  - current state
  - guardrail checklist
  - active risks
  - next actions
  - smoke-test order
  - handoff prompt
- the React Studio shell renders an Agent Runbook panel with copy actions for Markdown and JSON
- rollback payload content is not included in the runbook
- tests cover runbook title, format, line count, smoke-test order, read-only ability guidance, rollback-payload exclusion, and React affordances

### Implemented In The Twentieth Development Pass

The Studio endpoint now exposes explicit API contract metadata:

- `/wp-json/lcfa/v1/studio` returns top-level `contract`
- `studio` metadata now includes `schema_version`
- contract metadata includes:
  - schema version
  - payload version
  - SHA-256 fingerprint excluding request-time generation timestamp
  - available top-level sections
  - section count
  - run limits
  - readiness flags
- the React Studio shell renders a Studio Contract panel with copy support, fingerprint, section chips, readiness chips, and run limits
- tests cover schema version, payload version, fingerprint length, section listing, run limit, readiness flags, and React affordances

### Implemented In The Twenty-First Development Pass

The Studio endpoint now computes handoff readiness:

- `/wp-json/lcfa/v1/studio` returns top-level `handoff_readiness`
- readiness is calculated from setup state, MCP Adapter readiness, smoke-test availability, MCP write exposure, recent run errors, and runbook availability
- readiness output includes:
  - status
  - numeric score
  - recommended operating mode
  - pass/warn/fail gates
  - blockers
  - warnings
  - gate counts
- the React Studio shell renders a Handoff Readiness panel with copy support, score, mode, blocker/warning counts, and gate detail rows
- tests cover blocked state, read-only recommendation, score reduction, gate counts, blockers, warnings, contract section listing, and React affordances

### Implemented In The Twenty-Second Development Pass

The Studio endpoint now builds a copy-ready agent handoff package:

- `/wp-json/lcfa/v1/studio` returns top-level `agent_handoff_package`
- the package is a set of virtual files, not server-side writes
- included virtual files:
  - `forge-agent-runbook.md`
  - `forge-agent-smoke-tests.json`
  - `forge-operator-briefing.json`
  - `forge-handoff-readiness.json`
  - `forge-ability-manifest.json`
  - `forge-mcp-write-policy.json`
- every file includes media type, byte size, content, and SHA-256 hash
- the package manifest includes paths, per-file checksums, file metadata, and a package checksum
- the React Studio shell renders an Agent Handoff Package panel with copy actions for package, manifest, file content, and checksums
- tests cover package version, virtual-file format, readiness mirroring, file paths, checksums, contract section listing, rollback-payload exclusion, and React affordances

### Implemented In The Twenty-Third Development Pass

The handoff package is now available as a dedicated REST surface:

- `GET /wp-json/lcfa/v1/studio/handoff-package` returns only the agent handoff bundle, Studio route metadata, and a small endpoint contract
- `/wp-json/lcfa/v1/studio` now includes `studio.handoff_package_route`
- the handoff-package endpoint preserves the requested run limit and returns a SHA-256 payload fingerprint
- the React Studio shell adds a `Copy package endpoint` action in the Agent Handoff Package panel
- tests cover route metadata, endpoint contract, fingerprint length, run limit, checksum parity with Studio state, rollback-payload exclusion, and React affordances

### Implemented In The Twenty-Fourth Development Pass

The handoff package is now exposed directly to agent runtimes:

- WordPress 7 Abilities now register `livecanvas-forge-ai/get-agent-handoff-package`
- the ability is read-only, idempotent, MCP-public by default, and returns a sanitized virtual file package
- the ability package includes:
  - runbook Markdown
  - smoke-test plan JSON
  - ability diagnostics JSON
  - sanitized run history JSON
  - MCP status JSON
  - AI Client status JSON
- every ability package file includes byte size and SHA-256 checksum, plus a package checksum
- the local Node MCP bridge exposes `get_agent_handoff_package`, forwarding to `/wp-json/lcfa/v1/studio/handoff-package`
- tests cover ability registration/counts, MCP-public diagnostics, package structure, checksum presence, rollback-payload exclusion, MCP tool schema, and WP client routing

### Implemented In The Twenty-Fifth Development Pass

Connection bundles now bootstrap agents with a lightweight handoff first:

- generated bundles expose `agent_start_tool`, `connection_handoff_tool`, `handoff_package_tool`, and `agent_start_prompt`
- local MCP clients start with `get_connection_handoff`
- Codex remote mode through WordPress MCP Adapter starts with `livecanvas-forge-ai/get-connection-handoff`
- the full package remains available through `get_agent_handoff_package` or `livecanvas-forge-ai/get-agent-handoff-package`
- generic and Claude reference/helper files include a first-prompt section for operators
- the Connections technical summary renders a copyable `First agent prompt` panel
- the Codex visual guide now points users to the connection handoff instead of the legacy snapshot-only check
- tests cover local/remote tool naming, admin rendering, generated helper content, and Codex visual guidance

### Implemented In The Twenty-Sixth Development Pass

Studio and handoff packages now carry the same connection bootstrap:

- `/wp-json/lcfa/v1/studio` returns top-level `connection_handoff`
- the Studio contract lists `connection_handoff` as a stable section
- the connection handoff includes client, mode, status, transport, first tool, first prompt, guardrail, recommended sequence, and setup summary
- remote Codex with WordPress MCP Adapter receives the lightweight WordPress Ability tool name: `livecanvas-forge-ai/get-connection-handoff`
- local MCP flows receive the lightweight local Node tool name: `get_connection_handoff`
- both flows also expose the full package tool for deeper runbook and smoke-test context
- the React Studio shell renders a `Connection handoff` panel with copy actions for the first prompt and full JSON
- REST handoff packages include:
  - `forge-agent-start-prompt.txt`
  - `forge-connection-handoff.json`
- the WordPress Ability handoff package includes the same start prompt and connection handoff metadata for remote MCP Adapter clients
- tests cover Studio section exposure, prompt content, package virtual files, React affordances, and Ability package parity

### Implemented In The Twenty-Seventh Development Pass

The connection handoff is now available as a small dedicated agent surface:

- REST registers `GET /wp-json/lcfa/v1/studio/connection-handoff`
- `/wp-json/lcfa/v1/studio` now includes `studio.connection_handoff_route`
- the endpoint returns only:
  - route metadata
  - payload contract and SHA-256 fingerprint
  - `connection_handoff`
- the local Node MCP bridge exposes `get_connection_handoff`
- `WPClient` routes `getConnectionHandoff()` to `studio/connection-handoff`
- WordPress 7 Abilities register `livecanvas-forge-ai/get-connection-handoff`
- the connection handoff ability is read-only, idempotent, MCP-public by default, and does not include the larger virtual file package
- the React Studio handoff panel can copy the dedicated endpoint
- tests cover REST route registration, endpoint contract, MCP tool schema, WP client routing, Ability registration/counts, MCP Adapter public exposure, and package separation

### Implemented In The Twenty-Eighth Development Pass

Agent bootstrap prompts now start from the smallest safe surface:

- Connection bundles, Studio connection handoff, and WordPress 7 Ability handoff all start with `get_connection_handoff` or `livecanvas-forge-ai/get-connection-handoff`
- `get_agent_handoff_package` and `livecanvas-forge-ai/get-agent-handoff-package` remain copy-ready follow-up tools for the full runbook, smoke tests, ability diagnostics, MCP status, AI status, and recent run summary
- the Connections wizard visual guide now points Codex users to the connection handoff instead of the larger package
- the admin `First agent prompt` explanation now describes the lightweight connection handoff path
- tests cover local/remote bundle prompts, Studio endpoint parity, WordPress Ability parity, admin rendering, and Codex visual guidance

### Implemented In The Twenty-Ninth Development Pass

The native block pattern layer now has an agent-readable export surface:

- `LCFA_Block_Patterns` exposes a `block-pattern-library.v1` export with pattern metadata, optional content, byte counts, suggested use, and SHA-256 checksums
- WordPress 7 Abilities register `livecanvas-forge-ai/get-block-pattern-library` as a read-only MCP-public ability
- REST registers `GET /wp-json/lcfa/v1/studio/block-pattern-library` with endpoint contract metadata and payload fingerprinting
- `/wp-json/lcfa/v1/studio` includes top-level `block_pattern_library` plus `studio.block_pattern_library_route`
- Studio handoff packages include `forge-block-pattern-library.json`
- the local MCP bridge exposes `get_block_pattern_library`
- the React Studio shell renders a Block Pattern Library panel with copy actions for the library, endpoint, pattern content, and checksums
- tests cover service export shape, metadata-only exports, Ability counts/public exposure, REST endpoint parity, MCP tool schema, WP client routing, handoff package inclusion, and React affordances

### Implemented In The Thirtieth Development Pass

Agents can now compose native WordPress page previews from registered Forge patterns:

- `LCFA_Block_Patterns` exposes `build_native_page_preview()` with ordered pattern selection, slug normalization, missing-pattern warnings, block content checksums, and no-write next actions
- WordPress 7 Abilities register `livecanvas-forge-ai/preview-native-pattern-page` as an MCP-public preview ability
- REST registers `POST /wp-json/lcfa/v1/studio/native-pattern-page-preview` for local MCP and admin clients
- the local MCP bridge exposes `preview_native_pattern_page`
- Studio smoke tests include a native pattern page preview check alongside framework and page previews
- tests cover native block content composition, missing pattern reporting, Ability registration/exposure, REST routing, WP client routing, and MCP schema items

### Implemented In The Thirty-First Development Pass

Native page composition now has a read-only recipe layer before preview:

- `LCFA_Block_Patterns` exposes `native-pattern-page-blueprints.v1` recipes such as `starter-landing`, `hero-only`, and `feature-summary`
- WordPress 7 Abilities register `livecanvas-forge-ai/get-native-pattern-page-blueprints` as an MCP-public read-only ability
- REST registers `GET /wp-json/lcfa/v1/studio/native-pattern-page-blueprints` with payload fingerprinting and metadata-only mode
- `/wp-json/lcfa/v1/studio` includes top-level `native_pattern_page_blueprints` plus `studio.native_pattern_page_blueprints_route`
- Studio handoff packages include `forge-native-pattern-page-blueprints.json`
- `preview-native-pattern-page` accepts `blueprint` or `blueprint_id` and rejects unknown blueprint-only requests with available alternatives
- the local MCP bridge exposes `get_native_pattern_page_blueprints`
- tests cover blueprint export shape, REST endpoint parity, Ability exposure, MCP tool schema, WP client routing, and handoff package inclusion

### Implemented In The Thirty-Second Development Pass

Forge Studio now exposes the native page blueprint layer to operators:

- the React Studio shell renders a `Native page blueprints` panel from `native_pattern_page_blueprints`
- operators can copy the full blueprint payload, the dedicated endpoint, individual blueprint ids, and preview payloads
- the panel shows availability, blueprint count, preview ability, pattern count, pattern names, descriptions, and suggested use
- tests cover the new React component, copy actions, data attributes, and Studio route tokens

### Implemented In The Thirty-Third Development Pass

Native page preview routes are now discoverable from Studio:

- `/wp-json/lcfa/v1/studio` exposes `studio.native_pattern_page_preview_route`
- the React `Native page blueprints` panel can copy the dedicated preview endpoint
- operators can now copy the blueprint endpoint, preview endpoint, blueprint id, and preview payload from the same panel
- tests cover the Studio route field and the new copy affordance

### Implemented In The Thirty-Fourth Development Pass

Native page blueprints now include copy-ready preview requests:

- each blueprint carries `preview_request` with method, REST route, WordPress Ability, local MCP tool, and payload
- the blueprint library advertises `preview_tool` and `preview_route` alongside `preview_ability`
- the React Studio blueprint panel can copy either the bare preview payload or the complete preview request
- tests cover the request shape, MCP tool hints, REST state payload, and Studio copy token

### Implemented In The Thirty-Fifth Development Pass

Native page blueprint previews can now run directly inside Forge Studio:

- the Studio React shell includes a reusable `postStudioJson()` helper for authenticated no-write POST previews
- each blueprint row has a `Run preview` action that posts its preview payload to `studio/native-pattern-page-preview`
- preview results are rendered inline with status, byte count, checksum, and copy actions for full result and block content
- tests cover the new helper, preview action labels, result copy actions, and preview result data attribute

### Implemented In The Thirty-Sixth Development Pass

Native page preview results now include operator-facing diagnostics in Studio:

- inline preview output shows page title, content format, byte count, pattern count, warning count, and checksum
- selected pattern metadata is rendered with names, titles, byte counts, and shortened checksums
- warning and missing-pattern details are visible without opening raw JSON
- no-write next actions are listed below the result so operators can decide whether to review, copy, or hand off the generated block content
- tests cover the diagnostic tokens and preview result sections

### Implemented In The Thirty-Seventh Development Pass

Native WordPress page generation now has a dedicated apply path:

- WordPress 7 Abilities register `livecanvas-forge-ai/apply-native-pattern-page` as a write ability that is not MCP-public by default
- `LCFA_Settings::get_mcp_write_ability_options()` includes the apply ability so trusted MCP clients can explicitly allowlist it
- REST registers `POST /wp-json/lcfa/v1/studio/native-pattern-page-apply`
- the local MCP bridge exposes `apply_native_pattern_page`
- the ability always creates a new native WordPress page and supports only `draft`, `pending`, or `private` statuses
- successful applies store an audit entry and rollback record that can trash the created page through `restore-audit-rollback`
- tests cover ability registration, opt-in counts, REST routing, MCP routing, successful draft creation, history, and rollback metadata

### Implemented In The Thirty-Eighth Development Pass

Forge Studio can now execute the native page apply flow from the blueprint panel:

- native page blueprint exports include copy-ready `apply_request` objects with REST route, WordPress Ability, MCP tool, and draft payload
- the blueprint library advertises `apply_tool` and `apply_route` alongside the existing preview contract
- the React `Native page blueprints` panel can copy the apply endpoint and request
- operators can run preview first, then create a new draft page from the same blueprint row after confirmation
- inline apply results show created page ID, draft status, audit ID, edit link, view link, and copy action
- tests cover the apply contract tokens, blueprint apply request shape, and Studio UI affordances

### Implemented In The Thirty-Ninth Development Pass

Native page apply results are now wired into the Studio audit workflow:

- successful blueprint applies trigger a Studio state refresh so summary, runs, and rollback counts reflect the new draft
- inline apply results show rollback readiness when the native page creation stored a restore record
- operators can copy the apply audit ID directly from the blueprint row
- rollback-ready native drafts link back to the Command Deck restore flow with the generated audit ID
- tests cover the refresh callback, audit copy action, rollback shortcut, and rollback data attribute

### Implemented In The Fortieth Development Pass

Agent handoff smoke tests now cover native page apply safety:

- Studio smoke tests include a dedicated `native_pattern_page_apply_guard` entry for `livecanvas-forge-ai/apply-native-pattern-page`
- the guard is a write-check entry with an empty payload so agents do not accidentally create a draft from a copied smoke test
- expected-result text instructs agents to run `native_pattern_page_preview` first, review the result, and create only a new draft page after approval
- the generated runbook now lists the native apply guard alongside the existing page-upsert write guard
- tests cover smoke-test counts, guarded-write totals, runbook guidance, and public-write exposure status

### Implemented In The Forty-First Development Pass

Handoff readiness now follows the smoke-test contract dynamically:

- read-only, preview, and guarded-write readiness counts are derived from smoke test phases instead of fixed ID lists
- native page preview now contributes to preview readiness before native draft creation is considered
- guarded write checks now have their own readiness gate so missing apply guards surface before agent handoff
- Forge Studio shows read-only, preview, and write-guard availability ratios directly in the readiness panel
- tests cover dynamic readiness counts, native preview availability, guarded-write warnings, and the new Studio chips

### Implemented In The Forty-Second Development Pass

Agent handoff packages now include a compact machine-readable summary:

- both Studio REST handoff packages and WordPress Ability handoff packages include `forge-handoff-summary.json`
- the summary exposes status, recommended mode, score, next action, smoke counts, blocker/warning lists, unavailable tests, and guarded write tests
- package summaries surface readiness score, blocker count, warning count, unavailable smoke-test count, and write-guard count for quick UI/agent inspection
- the WordPress Ability handoff package now includes the native page apply guard in its smoke-test bundle too
- Forge Studio displays package score, blocker, warning, and missing-test chips in the Agent Handoff Package panel
- tests cover package file inclusion, summary schema/source, native apply guard presence, and sanitized payload safety

### Implemented In The Forty-Third Development Pass

The compact handoff summary is now available without downloading the full package:

- REST registers `GET /wp-json/lcfa/v1/studio/handoff-summary`
- Studio state exposes `studio.handoff_summary_route` and top-level `handoff_summary`
- WordPress 7 Abilities register `livecanvas-forge-ai/get-handoff-summary` as MCP-public read-only
- the local Node MCP bridge exposes `get_handoff_summary`
- agents can now fetch just status, score, blockers, warnings, unavailable smoke tests, guarded write tests, and next action before deciding whether the full handoff package is needed
- tests cover REST routing, endpoint payloads, ability registration, MCP exposure, local WP client routing, and tool schema

### Implemented In The Forty-Fourth Development Pass

Forge Studio now exposes the compact handoff summary as a first-class UI panel:

- the React Studio app renders `Handoff summary` directly after the readiness gates
- operators can copy the compact summary, summary endpoint, unavailable smoke tests, and guarded write tests without opening the full handoff package
- the panel shows score, status, next action, recommended mode, framework, public writes, run errors, smoke-test counts, and write-policy counts
- blockers and warnings are rendered as alert rows, while unavailable tests and guarded writes are rendered as smoke-test rows
- tests cover the new component, copy actions, data attributes, and handoff-summary contract tokens

### Implemented In The Forty-Fifth Development Pass

Forge Studio can now verify the compact handoff summary endpoint independently:

- the Studio runtime has a shared `fetchStudioJson` helper for GET requests with nonce-aware fallback fetch support
- the `Handoff summary` panel can refresh `studio.handoff_summary_route` directly instead of relying only on the initial Studio payload
- a successful endpoint refresh switches the panel source from `studio_state` to `endpoint`
- operators can copy the raw endpoint response for agent debugging and contract comparison
- endpoint errors are rendered inline so REST connectivity issues are visible without opening browser dev tools
- tests cover the refresh action, endpoint response copy action, source marker, and endpoint error marker

### Implemented In The Forty-Sixth Development Pass

Forge Studio now checks handoff summary parity after endpoint refresh:

- the `Handoff summary` panel compares the initial Studio payload with the dedicated summary endpoint response
- parity checks cover status, recommended mode, score, next action, framework, public writes, run errors, readiness counts, smoke-test counts, blocker counts, warning counts, unavailable tests, and guarded write tests
- the panel exposes `Parity: unverified`, `Parity: verified`, or `Parity: drift` with match counts
- drift rows identify the exact field and show the Studio value versus the endpoint value
- operators can copy the endpoint parity report for debugging agent handoff contract mismatches
- tests cover parity helpers, UI labels, copy action, and drift data markers

### Implemented In The Forty-Seventh Development Pass

Forge Studio now includes a copy-ready integration test plan:

- the React Studio app renders `Integration test plan` immediately after the overview
- the test plan lists the read-only REST endpoints, MCP tools, native page preview, and guarded draft creation path
- operators can copy the full test plan, a Codex smoke prompt, the REST endpoint checklist, and the first native preview request
- the smoke prompt instructs agents to verify connection handoff, compact summary, blueprint reads, and preview before any write
- the README now includes a short `How To Test This Build` section with the minimum pass condition
- tests cover the new panel, schema token, copy actions, and MCP tool references

## Open Questions

- Should write abilities be exposed only on the custom Forge MCP server, or also through the default WordPress MCP Adapter surface when WordPress core adds finer-grained controls?
- Should Forge require WordPress 7.0 in the next major release, or support WordPress 6.9 for one cycle?
- Should the legacy Node MCP package stay bundled long-term or move to a separate package?
- How much of the LiveCanvas section-selection flow should move from prompt queue metadata into first-class ability schemas?
- How much of WindPress local compilation can move server-side safely?

## Source References

- WordPress 7.0 release announcement: https://wordpress.org/news/2026/05/armstrong/
- `wp_register_ability()`: https://developer.wordpress.org/reference/functions/wp_register_ability/
- WordPress AI Client dev note: https://make.wordpress.org/core/2026/03/24/introducing-the-ai-client-in-wordpress-7-0/
- WordPress Connectors API dev note: https://make.wordpress.org/core/2026/03/18/introducing-the-connectors-api-in-wordpress-7-0/
- WordPress MCP Adapter developer article: https://developer.wordpress.org/news/2026/02/from-abilities-to-ai-agents-introducing-the-wordpress-mcp-adapter/
- WordPress MCP Adapter repository: https://github.com/wordpress/mcp-adapter
