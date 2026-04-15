# Connections Performance And Spacing Design

## Goal
Reduce perceived and real load time for `wp-admin/admin.php?page=lcfa-dashboard`, especially the `Connections` tab, without destabilizing the existing onboarding flow. In parallel, introduce a consistent vertical spacing system so the wizard sections no longer feel visually glued together.

## Problem Statement
The current `Connections` tab still performs non-essential health and bootstrap work on the initial server render:
- remote companion probing through `LCFA_Remote_Client::get_status()`
- local MCP bridge probing through `LCFA_Context_Builder::get_mcp_status()` and `LCFA_Local_MCP_Bridge::get_status()`
- bootstrap payload generation for secondary reference panels

Those calls are partially cached, but they still remain in the critical path whenever cache expires, and they introduce visible backend sluggishness. At the same time, the wizard layout uses tight section spacing, so `lcfa-wizard__alert`, `lcfa-wizard__steps`, the active panel, and support sections read as one compressed block.

## Scope
This slice changes only the `Connections` admin experience.

Included:
- move non-critical `Remote site` and `Advanced settings` data out of the initial synchronous render
- add an admin-facing async data endpoint for the secondary panels
- keep the wizard, ready card, technical summary, and primary flow server-rendered
- add a coherent vertical spacing rhythm for the wizard and support sections
- add regression tests for the lazy payload and render structure

Not included:
- full admin architecture refactor
- replacing all admin rendering with client-side rendering
- changing the command deck or project brief tabs
- redesigning card visuals beyond spacing and hierarchy

## Root Cause
The slow render is not primarily a CSS or DOM problem. It is caused by unnecessary work in the request path for `render_connections_tab()`.

The expensive parts are:
- remote status probing, which can involve authenticated network calls
- local bridge status probing, which can involve node detection and REST loopback checks
- full bootstrap payload assembly for sections that the user does not need immediately

The current design mixes:
- critical onboarding UI
- secondary diagnostics
- deep technical reference

into one server-rendered response.

## Design Principles
- keep first paint focused on the main task
- only block on data the user needs immediately
- diagnostics and deep reference belong after the first interaction, not before
- spacing should be governed by one section rhythm, not ad hoc margins

## Render Model

### Initial Server Render
The initial HTML for `Connections` must include:
- page hero
- admin notice if present
- connection test result transient if present
- onboarding hero
- wizard or ready card
- visual help strip
- technical summary based on the current bundle already stored in settings

The initial render must not require live remote probing or full diagnostics payload assembly.

### Deferred Async Panels
The following panels become async after first paint:
- `Remote site`
- `Advanced settings`

The page initially renders lightweight placeholders for these sections. After the page is visible, admin JS requests a dedicated payload and hydrates those panels.

## Async Endpoint
Introduce a dedicated admin REST endpoint for secondary `Connections` diagnostics.

Proposed route:
- `lcfa/v1/admin/connections-secondary`

Response payload:
- `remote_status`
- `mcp_status`
- `bootstrap_payload`
- `preferred_bootstrap`
- `common_bootstrap`
- `command_example`
- `workspace_root`
- `preferred_client`

This route must only be available to authorized admin users and must reuse existing service objects rather than duplicating logic.

## Rendering Strategy For Secondary Panels
The admin page will render:
- `Remote site` placeholder container
- `Advanced settings` placeholder container

Admin JS will:
1. fetch the secondary payload after DOM ready
2. replace placeholders with hydrated markup
3. render a concise loading state before payload arrives
4. render a compact error state if the async payload fails

The failure state must not block the wizard or the rest of the page.

## Server Responsibilities
`render_connections_tab()` should only compute the minimal state required for the main onboarding flow.

It should no longer synchronously compute remote diagnostics just to render secondary cards.

The synchronous path keeps:
- `connections`
- selected client and mode
- selected bundle
- onboarding state
- workspace accessibility
- presenter output for wizard and ready state

It should stop eagerly computing:
- remote status panel data
- advanced bootstrap/reference panel data

## Spacing System
The `Connections` wizard gets one explicit section stack rhythm.

A single wrapper governs vertical spacing between major blocks:
- alert
- stepper
- active panel
- visual help
- client guide
- technical summary

Desktop spacing:
- main section gap: `24px`
- support section gap after active panel: `28px`
- technical summary offset: `32px`

Mobile spacing:
- main section gap: `18px`
- support section gap after active panel: `22px`
- technical summary offset: `24px`

The implementation must avoid scattered `margin-bottom` fixes where possible. Prefer one stack wrapper and only minimal internal spacing within each component.

## UX Behavior
The user should perceive the page in this order:
1. current connection state
2. next action
3. step progression
4. active task
5. optional guidance
6. technical/diagnostic material

That means the wizard remains immediate, while diagnostics load in the background without blocking the primary task.

## Error Handling
If the async secondary payload fails:
- keep the main `Connections` flow usable
- replace placeholders with a compact inline error message
- provide a `Retry` action from JS if practical in this slice; otherwise show a static reload hint

If the async route succeeds but individual diagnostics are unhealthy:
- render those statuses normally inside the secondary cards
- do not treat that as a page render failure

## Testing

### PHP
Add coverage for:
- secondary connections payload endpoint returns the required shape
- `render_connections_tab()` still renders the main wizard without forcing secondary panel payload into the initial markup
- placeholders for async panels are present

### Existing Regression Suites
Keep green:
- `connections_wizard_phase1.php`
- `foundation_contract_phase1.php`
- `admin_performance_phase1.php`

### Manual Verification
Verify in the browser:
- `Connections` first paint feels faster on cold-ish cache
- `Remote site` and `Advanced settings` appear shortly after load
- a failed async payload does not break the wizard
- spacing between `lcfa-wizard__alert` and `lcfa-wizard__steps` is visibly improved
- spacing between wizard support sections is no longer compressed

## Acceptance Criteria
- initial `Connections` render no longer blocks on remote diagnostics
- secondary cards are loaded asynchronously after first paint
- the main onboarding flow remains fully functional without those cards
- spacing between wizard sections is visibly and structurally improved
- regression tests pass

## Follow-up
A later phase can split `LCFA_Admin` by tab renderer and make more sections lazily hydrated. This slice intentionally stops short of a broad refactor and focuses on the highest-value performance path.
