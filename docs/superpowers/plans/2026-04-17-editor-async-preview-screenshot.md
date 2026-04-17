# Editor Async Preview Screenshot Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an async execution loop for editor preview/apply, improve the support-details preview surface, and integrate screenshot-to-code attachments inside the LiveCanvas editor drawer.

**Architecture:** Keep `LCFA_Command_Deck::execute()` as the single write engine. Add a lightweight async layer in REST using transient-backed execution records with enqueue/poll semantics, then upgrade the editor drawer to consume those records and render richer result/attachment previews. Persist screenshot attachments in thread messages so the editor chat keeps visual context.

**Tech Stack:** WordPress REST API, PHP transients/options, existing editor-chat.js/editor-chat.css runtime, LiveCanvas editor bridge, PHP contract tests and JS runtime tests.

---

### Task 1: Async execution contract

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-rest-api.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php`

- [ ] Add failing PHP contract assertions for `enqueue_command_execution()` and `get_command_execution_status()`.
- [ ] Verify the new test fails because the REST API does not expose async execution yet.
- [ ] Implement transient-backed execution records with `queued`, `running`, `completed`, `failed` states.
- [ ] Re-run the PHP contract test until it passes.

### Task 2: Richer support-details preview

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/editor-chat.css`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/editor-chat.js`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/editor_chat_bridge_phase1.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/js/editor_chat_runtime_phase1.js`

- [ ] Add failing assertions for preview panes, compact launcher placement, and slimmer drawer layout.
- [ ] Verify the runtime/bridge tests fail for missing preview panes and layout hooks.
- [ ] Implement compact top-right launcher positioning, slimmer drawer structure, and dedicated preview panes for summary/target/diff/proposed content.
- [ ] Re-run PHP + JS editor tests until they pass.

### Task 3: Screenshot-to-code attachment flow

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-rest-api.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-settings.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-prompt-suggester.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/editor-chat.css`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/editor-chat.js`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/editor_chat_bridge_phase1.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/js/editor_chat_runtime_phase1.js`

- [ ] Add failing contract/runtime tests for screenshot attachment metadata and thumbnail rendering in the thread/support panel.
- [ ] Verify they fail before implementation.
- [ ] Persist a compact screenshot attachment payload on user thread messages and expose it back through the decorated thread payload.
- [ ] Upgrade the editor drawer to accept image attachments, preview them, and include them in analysis requests.
- [ ] Re-run the full PHP + JS verification batch.
