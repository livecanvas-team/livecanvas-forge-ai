# LiveCanvas Forge AI — 9 Integrations Program

## Goal

Finish the remaining product slices as one coherent program instead of isolated UI patches.

The target product shape is:

- Editor-first conversational workflow inside LiveCanvas
- Command Deck as control plane / inspection surface
- Deterministic Genesis execution loop for multi-step builds
- Framework-aware output across Picostrap, Picowind, and fallback themes
- Test coverage strong enough to keep shipping safely

## Status Snapshot

This document started as a forward-looking integration program. It now also acts as the live development snapshot.

### Done

- Command Deck thread-first completion
- Async preview/apply execution with execution-status polling
- Richer preview surface in the editor and support panels
- Target-specific context packs
- Genesis execution loop through plugin, REST, MCP, and admin
- Editor runtime test coverage
- Frontend-testable section prompting for:
  - `hero`
  - `pricing`
  - `features`
  - `testimonials`
  - `cta`
  - `faq`
  - `metrics`
  - `team`
  - `contact`

### Partial

- Screenshot-to-code inside the editor flow
  - attachments and references are supported
  - the editor composer now has a real drag-and-drop screenshot surface with preview and clear state
  - image-aware generation is not implemented yet
- Section generation depth
  - starters now use project brief inputs such as brand, sector, and tone
  - generation is still deterministic and not yet screenshot-driven or layout-aware
- Editor UX clarity
  - the drawer is now prompt-first and frontend-testable
  - the header is more orientation-focused (`You are in`, target type)
  - the composer now uses a visible `Send prompt -> Preview -> Apply` sequence
  - `Apply` is now intentionally gated behind a successful preview of the current suggestion
  - stronger visual feedback and a final total-flow pass still need another pass
- Precise placement operations
  - `replace hero` is now supported
  - `insert before footer` is now supported
  - `insert after selected section` is still open because the editor does not yet pass a stable selected-section anchor
- Setup / onboarding polish
  - much better than the initial state
  - still needs a final first-run pass after more real tests
- Richer preview surface
  - useful now
  - still not visual enough for final-product quality

### Still open

- Bare theme support
- Behavioral runtime tests for Command Deck flows
- Smarter section generation beyond deterministic starters
- Precise insertion operations tied to a concrete selected-section anchor
- Screenshot-derived layout generation

## Frontend-Testable Milestone

Forge AI in the LiveCanvas editor is now testable on a real page for practical prompt loops.

What already works:

- open the editor drawer
- write a natural prompt on the current page
- get a page-scoped suggestion instead of a generic audit
- preview the change with a real diff
- apply the change back to the current page

Prompts that should now be meaningful:

- `fammi una hero più chiara per questa pagina`
- `aggiungi una pricing con tre piani`
- `inserisci una sezione features con tre punti chiave`
- `metti dei testimonials più credibili`
- `aggiungi una CTA finale più forte`
- `aggiungi una sezione FAQ essenziale`
- `inserisci delle metriche chiave`
- `aggiungi una sezione team`
- `metti una contact section semplice`

The important caveat is quality, not plumbing:

- the loop is real
- the generated sections are still deterministic starters, not full creative generation

## Current Operational Plan

### Current state

- The editor drawer can now be tested on a real LiveCanvas page without backend detours.
- The request flow is explicit:
  1. send a prompt
  2. preview the suggested change
  3. apply the same change only after preview
- The current location is clearer in the drawer header.
- Screenshot upload is now a real composer surface, not a secondary broken-looking control.

### Immediate next blocks

1. **Precise placement operations**
   - Support `insert after selected section`
   - Introduce a stable selected-section anchor from the editor
   - Reduce the remaining reliance on generic `prepend` / `append`

2. **Generation quality**
   - Move beyond deterministic starters
   - Use target context and project brief more deeply
   - Make generated sections feel page-aware instead of generic

3. **Screenshot-derived generation**
   - Read the screenshot as a layout reference, not only as an attachment
   - Map screenshot structure into framework-aware markup
   - Keep preview/apply on the same editor loop

4. **Runtime hardening**
   - Add stronger behavioral coverage for Command Deck interactions
   - Do another real-page pass on existing LiveCanvas pages
   - Finish the remaining onboarding friction only after the editor loop stabilizes

## Integration Map

1. Command Deck thread-first completion
2. Async execution / richer state model
3. Richer preview surface
4. Screenshot-to-code inside the editor flow
5. Target-specific context packs
6. Genesis execution loop
7. Bare theme support
8. Behavioral runtime tests outside pure PHP contracts
9. Setup / onboarding polish for first-run success

## Dependency Order

### Tranche A — Shared execution foundation

This tranche unlocks most of the remaining work.

- Build Genesis executor with explicit progress transitions
- Expose execution-plan and execute-next/execute-task contracts through REST
- Expose the same contracts to MCP
- Keep thread/context propagation intact across editor, Command Deck, and Genesis

Why first:

- Async, preview, and screenshot-to-code all need a reliable execution backbone
- Without deterministic execution state, the UI keeps duplicating state in panels

### Tranche B — Thread-first surfaces

- Reduce Command Deck panels to support/inspection role
- Keep thread as the primary operational history
- Add richer preview and execution-state labels
- Make failed/suggested states recoverable from the thread itself

Why second:

- The editor chat is already close to the desired shape
- The remaining UX work is mostly about removing duplication and clarifying state

### Tranche C — Context and generation depth

- Add target-specific context packs
- Add screenshot-to-code into the editor flow
- Improve preview payload quality per target type
- Prepare framework fallback for bare themes

Why third:

- These improve generation quality after the execution path is stable

### Tranche D — Hardening and onboarding

- Runtime/browser-like tests for Command Deck flows
- Setup flow refinements after real execution paths are in place
- Final first-run reduction of friction

## Concrete Deliverables

### A1. Genesis executor

- New `LCFA_Genesis_Executor`
- Computes execution plan from stored Genesis plan + progress
- Supports:
  - `get_execution_plan()`
  - `execute_next(array $options = [])`
  - `execute_task(string $task_id, array $options = [])`
- Maps command results into Genesis progress states:
  - `previewed`
  - `applied`
  - `failed`
- Handles advisory tasks with no command payload deterministically

### A2. REST contracts

- `GET /lcfa/v1/genesis/execution-plan`
- `POST /lcfa/v1/genesis/execute-next`
- `POST /lcfa/v1/genesis/execute-task`

### A3. MCP contracts

- `get_genesis_execution_plan`
- `execute_genesis_next`
- `execute_genesis_task`

### B1. Command Deck thread-first completion

- Keep support panels secondary
- Use shared context when preview/apply is launched from:
  - thread actions
  - suggestion panel
  - result panel
- Avoid repeating data already visible in the thread

### C1. Target-specific context packs

Add stronger structured hints for:

- page
- header
- footer
- dynamic template
- theme file
- backup restore
- Genesis task

### D1. Runtime coverage

- Keep PHP contract tests for data/markup
- Add JS/runtime coverage for Command Deck interactions comparable to editor drawer coverage

## What Changed Since The Original Program

The early tranches have effectively been delivered.

### Delivered from Tranche A

- Genesis executor
- Genesis REST contracts
- Genesis MCP contracts
- thread/context propagation kept intact across editor, Command Deck, and Genesis

### Delivered from Tranche B

- Command Deck is now much closer to thread-first
- support panels are secondary rather than primary
- failed/suggested states are recoverable from the thread
- editor drawer is slimmer and structurally cleaner

### Delivered from Tranche C

- target-specific context packs
- editor attachments wired into the prompt flow
- section-aware page suggestions
- brief-aware section starters for the first wave of common section intents

### Not delivered yet from Tranche C / D

- true screenshot-to-code generation
- bare theme support
- Command Deck behavioral runtime tests
- final onboarding pass

## Remaining Program

The remaining work is no longer a broad 9-item program. It is now a tighter set of product-hardening slices.

### R1. Section generation depth

- move from deterministic starters to richer generation guidance
- let tone, sector, page context, and active framework shape the generated section more strongly
- expand supported section intents:
  - faq
  - logo cloud
  - metrics
  - comparison
  - timeline
  - team
  - contact

### R2. Placement precision

- support:
  - replace existing hero
  - insert after selected section
  - insert before footer
  - refine selected block
- stop treating all page writes as prepend/append only

### R3. Screenshot-to-code for real

- use uploaded image references to shape layout, hierarchy, and density
- connect screenshot-aware intent to page generation instead of only storing the attachment in the thread

### R4. Bare theme support

- add a valid fallback for themes outside Picostrap/Picowind
- make page and design-system output still usable when framework family is not strongly typed

### R5. Command Deck runtime tests

- add browser-like interaction coverage comparable to the editor drawer JS test harness

### R6. Final onboarding polish

- validate the setup flow after the stronger frontend loop is in place
- reduce first-run ambiguity and sharpen the first successful smoke test path

## Execution Notes

- Prefer additive changes over rewrites
- Reuse `LCFA_Command_Deck::execute()` as the single write engine
- Reuse `LCFA_Thread_Message_Actions` as the action policy layer
- Do not build a second progress model outside `LCFA_Settings::get_genesis_progress()`
- Treat support panels as inspection surfaces, not primary workflow surfaces

## Current Slice

The next sensible slice is no longer Tranche A. The new priority order is:

1. section generation depth
2. placement precision
3. screenshot-to-code for real
4. bare theme support
5. Command Deck runtime tests
6. final onboarding polish

This is the shortest path from “frontend testable” to “frontend strong enough for repeated product demos”.
