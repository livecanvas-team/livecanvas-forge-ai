# Admin Unified Hero + Stack Compact Design

## Goal
Replace the separate `Stack snapshot` card with a single unified hero used across the main admin tabs. The new hero must reduce duplication, surface the active stack faster, and use logos/chips wherever a visual mark communicates better than repeated text.

## Scope
Applies to the main dashboard tabs:
- Setup
- Connections
- Project Brief
- Command Deck

This slice only changes the top-of-page admin layout and stack presentation. It does not redesign the body content of each tab.

## Chosen Direction
Use a single compact hero across all tabs.

The hero should:
- keep one shared visual structure across tabs
- change only the tab title, subtitle, and a small number of contextual chips
- absorb the useful parts of `Stack snapshot`
- remove the separate stack card from the main layout

## Information Hierarchy
### Always visible
- Forge AI identity
- current tab title
- one short subtitle
- LiveCanvas logo/mark
- active framework logo/mark
- supporting stack logos when relevant
- compact chips for:
  - site mode (`local` or `remote`)
  - active theme stylesheet/name
  - active coding agent/client when configured
  - editor profile when relevant, for example `daisyui-5`

### Hidden from primary view
Do not keep these as top-level always-visible rows:
- verbose stack summary copy
- separate `Stack snapshot` card
- repeated framework/theme labels in multiple places
- long label-value technical lists in the top area

### Moved into `Details`
Collapsed by default:
- stylesheet slug
- template slug
- ACF/Tangible support state
- workspace sync state
- MCP technical metadata
- secondary detection notes

## Layout
### Desktop
Single full-width hero.

Left column:
- Forge AI identity
- current tab title
- short subtitle

Right column:
- row of stack logos/marks
- row of compact chips
- discreet `Details` toggle

Below the hero content, visually attached:
- tab navigation

### Mobile
- title/content stack vertically
- logos wrap to multiple lines if needed
- chips wrap cleanly
- details toggle remains visible

## Behavior
### Hero model
The hero renderer should be shared across tabs.

Inputs:
- current tab
- snapshot
- connections
- optional tab-specific status

Outputs:
- title
- subtitle
- stack marks
- stack chips
- details payload

### Details toggle
- collapsed by default
- expands inline inside the same hero
- never opens as a modal
- never renders as a second side card

## Visual language
- prefer logos/marks over repeated framework names when an official asset already exists
- use chips for instance-specific values
- keep copy terse
- remove repeated explanatory sentences when the same fact is already expressed by a chip or mark

## Content rules
### Good candidates for logos/marks
- LiveCanvas
- Bootstrap
- Picowind
- WindPress
- coding agents where official assets exist

### Good candidates for chips
- `local` / `remote`
- active theme
- active client
- editor config

### Avoid
- stacked mini dashboards inside the hero
- repeated status strings already shown elsewhere
- a separate right rail only for stack metadata

## Technical implementation slice
1. Introduce a unified hero renderer.
2. Move stack snapshot data mapping into that renderer.
3. Remove the standalone `Stack snapshot` card from the main layout.
4. Add inline `Details` collapse state.
5. Update CSS for compact hero, logo rows, chips, and responsive wrapping.

## Success criteria
- the top of the admin is visually lighter
- stack context is readable in under 2 seconds
- framework/theme/client are no longer repeated in multiple top-area blocks
- `Stack snapshot` no longer appears as a standalone card
- layout remains usable on mobile widths
