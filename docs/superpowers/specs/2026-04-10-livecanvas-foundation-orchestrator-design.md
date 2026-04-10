# LiveCanvas Forge AI Foundation Orchestrator Design

Date: 2026-04-10
Status: Proposed
Scope: `livecanvas-forge-ai` plugin only

## Goal

Turn `LiveCanvas Forge AI` into a reliable foundation orchestrator for site-level work driven by external coding agents.

The first milestone is not "generate a nice page". The first milestone is:

- let a coding agent connect with minimal setup
- inspect the real WordPress + LiveCanvas stack
- prepare the site for work in local and remote modes
- apply a framework-aware design system
- create or update global shell elements like header and footer
- create or update LiveCanvas pages and always return final URLs

## Product Direction

The plugin is the execution engine. The coding agent is the planner.

The plugin should not be the primary source of high-ambiguity intelligence. It should expose deterministic site operations with consistent payloads, preview/apply safety, and local/remote parity.

This keeps the AI layer flexible while making the WordPress side reliable.

## Key Requirement: Plug-And-Play Connection

The current connection model is too complex.

The new architecture must make agent-to-plugin connection feel plug and play:

- one obvious bootstrap path per client
- minimal required variables
- no fragile multi-step manual configuration for common local usage
- clear fallback between local and remote transports
- identical logical contracts for local and remote execution
- enough introspection for an agent to self-diagnose setup issues

The connection story must optimize for "start using it quickly", not "maximum configurability first".

## Real Stack Constraints

### Picostrap

If the site uses `Picostrap`, the design system must be applied through the Picostrap compilation path, not through generic CSS mutation.

Expected behavior:

- translate design intent into Picostrap/PicoSass-compatible variables
- run the Sass compilation flow that produces the Bootstrap bundle
- expose compilation preview, warnings, and final output metadata

### Picowind + WindPress

If the site uses `Picowind`, the design system must be applied through `WindPress`, using its runtime, preset, skin, and cache/build model.

Expected behavior:

- translate design intent into WindPress-compatible configuration and token updates
- use DaisyUI/WindPress-native primitives where appropriate
- support build/cache refresh through the existing WindPress bridge

### Custom Themes

If the site does not use Picostrap or Picowind, the plugin may use a canonical internal design-system fallback.

This is not the primary path. It is a compatibility path.

## Source Of Truth Model

Use a theme-native source of truth first, with a plugin-normalized intent record.

Rules:

- `Picostrap` is the visual source of truth on Picostrap sites
- `WindPress` is the visual source of truth on Picowind sites
- the plugin stores a normalized design intent record for audit, replay, preview, and local/remote parity

The plugin-owned normalized record is not the final visual authority. It is an orchestration artifact.

## In Scope For Milestone 1

- stack detection and readiness checks
- local and remote execution parity for foundation operations
- framework-aware design system application
- header/footer creation or update
- page create/update with final URLs
- foundation orchestration for first-install and large structural actions
- connection simplification and bootstrap cleanup

## Out Of Scope For Milestone 1

- fully assigned dynamic templates for WooCommerce, taxonomies, and CPT/ACF
- advanced editor-side chat workflow
- high-ambiguity autonomous prompt orchestration entirely inside the plugin
- broad third-party UI library orchestration beyond the design-system foundation layer

## Primary Intents

Milestone 1 introduces a first-class intent layer. These intents replace the current over-reliance on scattered low-level actions.

### `site_audit`

Reads:

- stack
- framework
- theme
- LiveCanvas status
- WindPress status
- local/remote capability state
- plugin and partial availability
- existing key pages and shell targets

Returns a normalized site capability report.

### `site_prepare`

Prepares the site for work:

- checks required plugins
- activates installable dependencies where allowed
- validates framework assumptions
- validates local or remote transport readiness

Returns concrete readiness state and blocking issues.

### `design_system_apply`

Applies a design-system intent against the active framework:

- `Picostrap -> PicoSass/compiler path`
- `Picowind -> WindPress/DaisyUI/runtime path`
- `custom theme -> canonical fallback path`

Supports preview and apply.

### `global_shell_apply`

Creates or updates:

- header partial
- footer partial

Supports explicit variant targeting and creation when missing.

### `page_upsert`

Creates or updates a LiveCanvas page and always returns:

- `post_id`
- `frontend_url`
- `edit_url`
- `status`
- created vs updated state

### `site_foundation_run`

High-level orchestration intent that combines:

- site audit
- site preparation
- design system application
- global shell application
- initial page upsert operations

This is the first-install / structural-work primitive for external coding agents.

## Intent Contracts

All first-class intents must follow the same result envelope.

### Shared Result Envelope

Every intent should return:

```json
{
  "ok": true,
  "action": "page_upsert",
  "mode": "preview",
  "execution_target": "local",
  "target_type": "page",
  "target_id": 123,
  "target_title": "Landing Page 1",
  "frontend_url": "https://example.test/landing-page-1/",
  "edit_url": "https://example.test/wp-admin/post.php?post=123&action=edit",
  "summary": "Created draft page.",
  "warnings": [],
  "data": {}
}
```

Current command output should be normalized toward this contract.

### `page_upsert`

Input:

```json
{
  "title": "Landing Page 1",
  "slug": "landing-page-1",
  "status": "draft",
  "content": "<main>...</main>",
  "post_id": 0
}
```

Behavior:

- create when `post_id` is absent
- update when `post_id` is present
- always enforce `_lc_livecanvas_enabled = 1`
- always return final URLs
- preserve idempotent behavior when possible

### `design_system_apply`

Input should be structured, not raw CSS-first:

```json
{
  "framework": "picostrap",
  "brand": {
    "name": "Consultala"
  },
  "preset": {
    "name": "corporate",
    "source": "daisyui"
  },
  "colors": {
    "primary": "#0f766e",
    "secondary": "#164e63",
    "accent": "#f59e0b",
    "surface": "#f8fafc",
    "text": "#0f172a"
  },
  "typography": {
    "heading_font": "Manrope",
    "body_font": "Inter",
    "scale": "comfortable"
  },
  "layout": {
    "container": "wide",
    "section_spacing": "lg",
    "radius": "md"
  },
  "custom_css": ""
}
```

Behavior:

- choose framework writer automatically from stack
- preview the exact mapping before apply
- return touched targets, warnings, and build results

### `global_shell_apply`

Input:

```json
{
  "variant": "1",
  "header_html": "<header>...</header>",
  "footer_html": "<footer>...</footer>"
}
```

Behavior:

- resolve or create target partials
- return IDs and edit URLs
- respect policy and preview/apply

### `site_foundation_run`

Input:

```json
{
  "site_profile": "local",
  "framework": "picowind",
  "required_plugins": ["livecanvas", "windpress"],
  "design_system": {},
  "header": {},
  "footer": {},
  "pages": []
}
```

Behavior:

- orchestrate foundation work in order
- preserve step-by-step result visibility
- expose per-step URLs, warnings, and failures

## Architecture Refactor

The current plugin already contains useful pieces:

- stack detection
- command deck
- REST routes
- local MCP bridge
- remote client
- WindPress bridge
- theme file bridge

Milestone 1 should reorganize these pieces instead of replacing them wholesale.

### 1. Intent Layer

Add an explicit intent-dispatch layer above the current command execution flow.

Responsibilities:

- validate structured payloads
- pick the correct executor
- normalize responses
- enforce preview/apply semantics

`LCFA_Prompt_Suggester` should become an optional helper that suggests intent payloads, not the core execution model.

### 2. Execution Layer

Split execution by domain:

- `Foundation executor`
- `LiveCanvas page executor`
- `Global shell executor`
- `Picostrap design-system executor`
- `Picowind design-system executor`
- `Remote execution adapter`

This avoids bloating `LCFA_Command_Deck` with unrelated responsibilities.

### 3. State And Audit Layer

Persist normalized execution state for:

- last site audit
- last foundation run
- last design-system apply result
- entity map for created/updated shell and pages
- local vs remote target status

This state is necessary for reliable replay and agent context.

### 4. Admin/UI Layer

Focus the admin UX around four main surfaces:

- `Setup`
- `Connections`
- `Foundation`
- `Command Deck`

`Foundation` becomes the main site bootstrap surface for structural work.

## Connection Simplification Design

This is a first-class requirement, not a nice-to-have.

### Local

Preferred local flow:

1. plugin exposes one clearly recommended command per client
2. plugin exposes exact env values
3. agent can verify readiness through one health/status path
4. local MCP bootstrap works without optional fields unless strictly required

The local user should not need to understand transport internals before creating value.

### Remote

Preferred remote flow:

1. install plugin on remote site
2. provide remote URL + WordPress user + Application Password
3. run one validation
4. use the same logical intent contracts as local

Remote should feel like "same tool, different target", not a different product.

### Product Requirement

The plugin should eventually be able to generate one compact bootstrap snippet or one importable client profile per supported code agent.

## Safety Model

Current safety is not strict enough because some write routes bypass policy intent.

Milestone 1 must enforce:

- one policy model for all write paths
- preview/apply available consistently
- file and framework writes respect the same policy layer
- remote and local writes both report downgraded preview states clearly

## Design-System Mapping Rules

### Picostrap Mapping

Pipeline:

1. parse normalized design intent
2. map intent to Picostrap/PicoSass variables
3. preview variable diff and compilation plan
4. compile the CSS bundle
5. return build result and affected output references

Required output metadata:

- mapped variables
- compiler status
- output bundle reference
- warnings

### Picowind Mapping

Pipeline:

1. parse normalized design intent
2. resolve DaisyUI skin/preset or explicit token override
3. map to WindPress-compatible data structures
4. apply through WindPress bridge/runtime
5. rebuild cache when required

Required output metadata:

- applied preset/skin
- token overrides
- touched entries or cache targets
- build status
- warnings

### Custom Theme Mapping

Fallback pipeline:

1. parse normalized design intent
2. store plugin-owned canonical representation
3. write controlled fallback assets where allowed by policy

This path should remain secondary.

## Local/Remote Parity

Local and remote must keep one logical contract even when execution internals differ.

Rules:

- same intent names
- same payload shape
- same result envelope
- same preview/apply semantics
- same URL semantics

Only the executor and transport should differ.

## Milestone 1 Delivery Plan

### Milestone 1A: Contract And Safety

- normalize result envelopes
- introduce intent-oriented dispatch
- fix policy bypass on direct write paths
- guarantee page result URLs

### Milestone 1B: Page Upsert Local + Remote

- deliver reliable `page_upsert`
- create/update behavior
- final URL return
- local/remote parity

### Milestone 1C: Design System Apply

- Picostrap writer via PicoSass/compiler
- Picowind writer via WindPress
- preview/apply flow

### Milestone 1D: Global Shell Apply

- reliable header/footer creation or update
- variant-aware targeting

### Milestone 1E: Foundation Run

- orchestrate site-level structural setup
- bundle audit, prepare, design system, shell, and starter pages

## Known Gaps In Current Alpha

The current alpha has useful groundwork, but these gaps block the milestone:

- direct theme write routes bypass the operational policy
- dynamic template creation is not yet assignment-aware
- prompt suggestion favors keyword heuristics over structured intent contracts
- partial discovery and quick actions are biased toward variant `1`
- connection UX still exposes too much transport complexity

## Recommendation

Do not optimize the next phase around "better prompt guessing".

Optimize it around:

- deterministic intent contracts
- plug-and-play bootstrap
- framework-native design-system execution
- always-return-final-URLs behavior
- local/remote symmetry

That combination will make the plugin genuinely useful for structural site work from coding agents.
