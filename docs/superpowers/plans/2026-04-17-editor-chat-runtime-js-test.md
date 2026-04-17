# Editor Chat Runtime JS Test Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a browser-like Node harness for `assets/editor-chat.js` so the critical drawer flow is covered beyond PHP contract tests.

**Architecture:** Reuse the existing lightweight `tests/js` pattern based on `vm.runInContext()`. Build a minimal DOM stub that is just rich enough for the editor chat runtime: shell bootstrap, button click listeners, delegated thread actions, `fetch`, `localStorage`, and command URL updates. Keep this focused on behavior, not visual rendering.

**Tech Stack:** Node.js, `vm`, built-in `assert`, custom mock DOM elements, existing asset files.

---

### Task 1: Add a failing runtime test for the drawer flow

**Files:**
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/js/editor_chat_runtime_phase1.js`

- [ ] Load `assets/editor-chat.js` in a VM context with a fake `document`, `window`, and `fetch`
- [ ] Assert the runtime binds itself to the editor shell and persists the selected thread key
- [ ] Simulate `open -> analyze -> preview/apply` and assert the command payload restores `thread_id`, `context_post_id`, `post_id`, `target_id`, and `variant`
- [ ] Run `node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/js/editor_chat_runtime_phase1.js` and confirm it fails first if the harness reveals a regression

### Task 2: Fix any runtime gaps exposed by the harness

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/editor-chat.js` only if behavior diverges from the intended flow

- [ ] Keep the runtime thread-first and context-preserving
- [ ] Avoid changing the `data-lcfa-*` DOM contract unless the test proves a real bug

### Task 3: Re-run focused verification

**Files:**
- Modify only if needed based on failures above

- [ ] Re-run `node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/js/editor_chat_runtime_phase1.js`
- [ ] Re-run `php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/editor_chat_bridge_phase1.php`
- [ ] Re-run `php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php`
