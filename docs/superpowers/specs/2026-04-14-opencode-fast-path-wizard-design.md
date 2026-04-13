# OpenCode Fast-Path Wizard Design

Date: 2026-04-14
Scope: `Connections` tab in `livecanvas-forge-ai`
Status: Draft for review

## Goal

Reduce the `OpenCode` onboarding flow to the shortest reliable path from:

`not connected -> bundle generated -> OpenCode opened -> MCP green -> smoke test -> ready`

without forcing the user to read technical details unless they want them.

## Why This Change

The current linear wizard is already better than the old screen, but it is still heavier than necessary for the main local flow:

- too much emphasis on transport and bundle details
- not enough visual guidance when the user must switch from WordPress to OpenCode
- the shortest successful path is not obvious enough

For the OpenCode path, the primary requirement is speed and clarity, not configurability.

## Design Principles

1. One primary action per step.
2. Keep the path to `Ready` as short as possible.
3. Put visual guidance below the active step, not inside the form.
4. Keep technical details secondary.
5. Keep failure recovery visible, but not dominant.

## Proposed UX

### Top Of Page

Keep:

- `Connect your coding agent` hero
- wizard state badge
- current client and mode chips

Reduce noise in the hero copy so the eye lands on the current action quickly.

### Wizard Structure

For `OpenCode + local`, the recommended flow becomes:

1. `Choose agent`
2. `Confirm local workspace`
3. `Download bundle`
4. `Run smoke test`

This intentionally collapses the previous `choose mode -> confirm details -> generate bundle` weight into a shorter sequence for the common case.

### Active Step Card

The active step card remains the main interaction area:

- short question title
- one sentence of context
- one primary CTA
- at most one secondary CTA

Examples:

- `Choose OpenCode`
- `Confirm your local workspace path`
- `Download opencode.json`
- `Run smoke test`

### Visual Help Strip

Add a new section directly below the active step card:

`What this looks like in OpenCode`

This section is not interactive. It is a visual helper strip made of 1-3 mini-cards with image placeholders and one short caption each.

For the OpenCode local flow, the default sequence is:

1. `Open the project folder in OpenCode`
2. `Check that livecanvas-forge is green in MCP`
3. `Return here and run the smoke test`

This strip appears only when useful:

- visible during `Download bundle`
- visible during `Run smoke test`
- optional or hidden on earlier steps

### Technical Summary

Keep the technical summary below the visual help strip, collapsed unless the current step needs it.

Do not let it compete visually with the primary path.

## Content Rules

### OpenCode Copy

Use direct action copy:

- `Download opencode.json`
- `Open this project in OpenCode`
- `Check MCP: livecanvas-forge`
- `Return here and run the smoke test`

Avoid abstract wording like:

- `generate client bundle`
- `configure transport`
- `bootstrap the MCP server`

unless shown in advanced/technical sections.

### Image Strategy

Phase 1:

- use product-style placeholder cards inside WordPress admin
- each card contains:
  - small frame/mock screenshot area
  - short title
  - one-line caption

Phase 2:

- replace placeholders with curated screenshots for OpenCode, Codex, Cursor, and Claude Code

The layout should support both without structural changes.

### Agent Icons

Do not use invented SVGs for the coding-agent badges or helper cards.

Use official assets when available:

- `Codex`: source from the installed macOS app asset (`/Applications/Codex.app/Contents/Resources/codexTemplate@2x.png`)
- `OpenCode`: source from the installed macOS app icon (`/Applications/OpenCode.app/Contents/Resources/icon.icns`)
- `Cursor`: source from the installed macOS app icon (`/Applications/Cursor.app/Contents/Resources/Cursor.icns`)
- `Claude Code`: use the user-provided official Claude asset (`/Users/commander/Downloads/claude-color.svg`)

These source files should be copied/exported into plugin-owned assets so the WordPress admin UI does not depend on `/Applications/...` at runtime.

## State Behavior

### Not Connected

Show the fast-path wizard.

### In Progress

Keep the current step active and show the matching visual help strip.

### Ready

Show:

- `Ready` status card
- `Run checks`
- `Regenerate bundle`
- `Reconfigure`

Below that, optionally keep a compact visual reminder of the successful OpenCode path.

## Client-Specific Behavior

This redesign is OpenCode-first, but the structure should be reusable:

- same wizard shell for all clients
- client-specific copy in the step card
- client-specific visual help strip below the active step

OpenCode gets the first complete pass because it is the current tested path.

## Non-Goals

This change does not:

- redesign the remote-site section
- remove advanced settings
- replace the technical summary with images
- introduce modal walkthroughs

## Implementation Notes

1. Extend the presenter so `bundle` and `smoke_test` steps can expose visual help metadata.
2. Render a new section below the active step panel in `Connections`.
3. Start with static visual helper cards for OpenCode local mode.
4. Keep all current contract and test behavior intact.

## Testing

Add or update tests for:

- OpenCode local wizard shows the shortened primary path copy
- visual help strip appears on the correct steps
- technical summary remains secondary
- ready state still behaves the same

For manual verification:

1. Choose `OpenCode`
2. Choose `This local site`
3. Confirm workspace path
4. Download bundle
5. Open project in OpenCode
6. Confirm MCP is green
7. Run smoke test
8. Confirm `Ready`
