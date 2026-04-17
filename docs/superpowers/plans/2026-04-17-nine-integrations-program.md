# LiveCanvas Forge AI — 9 Integrations Program

## Goal

Finish the remaining product slices as one coherent program instead of isolated UI patches.

The target product shape is:

- Editor-first conversational workflow inside LiveCanvas
- Command Deck as control plane / inspection surface
- Deterministic Genesis execution loop for multi-step builds
- Framework-aware output across Picostrap, Picowind, and fallback themes
- Test coverage strong enough to keep shipping safely

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

## Execution Notes

- Prefer additive changes over rewrites
- Reuse `LCFA_Command_Deck::execute()` as the single write engine
- Reuse `LCFA_Thread_Message_Actions` as the action policy layer
- Do not build a second progress model outside `LCFA_Settings::get_genesis_progress()`
- Treat support panels as inspection surfaces, not primary workflow surfaces

## Current Slice

Start with Tranche A:

1. Add Genesis executor
2. Expose REST execution endpoints
3. Expose MCP execution tools
4. Lock the contract with tests

This gives the biggest real product movement per patch and creates the base for the rest of the 9 integrations.
