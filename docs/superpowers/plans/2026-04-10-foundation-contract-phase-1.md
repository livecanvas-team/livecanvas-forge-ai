# Foundation Contract Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Introduce the first reliable foundation contract slice for `livecanvas-forge-ai` by adding `page_upsert` with final URLs and by enforcing existing policy rules on direct theme write REST routes.

**Architecture:** Keep the current plugin structure intact, but add one normalized foundation-style intent (`page_upsert`) and a reusable policy entrypoint for direct write routes. Preserve backward compatibility for existing `create_page` and `update_page` actions while moving external agent flows toward the new contract.

**Tech Stack:** WordPress PHP plugin code, existing REST API classes, existing command deck, lightweight PHP test harness with stubbed WordPress functions

---

### Task 1: Add Red Tests For `page_upsert` And Direct Write Policy

**Files:**
- Create: `docs/superpowers/plans/2026-04-10-foundation-contract-phase-1.md`
- Create: `tests/php/foundation_contract_phase1.php`
- Modify: `tests/php/.gitkeep` (optional if needed)

- [ ] **Step 1: Write the failing test harness**

```php
<?php
// tests/php/foundation_contract_phase1.php

declare(strict_types=1);

require __DIR__ . '/helpers/wp-stubs.php';
require __DIR__ . '/helpers/assert.php';

require LCFA_TEST_PLUGIN_ROOT . '/includes/class-lcfa-settings.php';
require LCFA_TEST_PLUGIN_ROOT . '/includes/class-lcfa-environment.php';
require LCFA_TEST_PLUGIN_ROOT . '/includes/class-lcfa-inventory.php';
require LCFA_TEST_PLUGIN_ROOT . '/includes/class-lcfa-windpress-bridge.php';
require LCFA_TEST_PLUGIN_ROOT . '/includes/class-lcfa-theme-files-bridge.php';
require LCFA_TEST_PLUGIN_ROOT . '/includes/class-lcfa-local-mcp-bridge.php';
require LCFA_TEST_PLUGIN_ROOT . '/includes/class-lcfa-remote-client.php';
require LCFA_TEST_PLUGIN_ROOT . '/includes/class-lcfa-context-builder.php';
require LCFA_TEST_PLUGIN_ROOT . '/includes/class-lcfa-prompt-suggester.php';
require LCFA_TEST_PLUGIN_ROOT . '/includes/class-lcfa-genesis-planner.php';
require LCFA_TEST_PLUGIN_ROOT . '/includes/class-lcfa-command-deck.php';
require LCFA_TEST_PLUGIN_ROOT . '/includes/class-lcfa-rest-api.php';
```

- [ ] **Step 2: Add a failing `page_upsert` create test**

```php
$result = $command_deck->execute([
    'action'  => 'page_upsert',
    'title'   => 'Landing Page 1',
    'slug'    => 'landing-page-1',
    'status'  => 'draft',
    'content' => '<main>Hero</main>',
]);

lcfa_assert_true($result['ok'] === true, 'page_upsert should succeed');
lcfa_assert_same('page', $result['target_type'], 'page_upsert should target pages');
lcfa_assert_same('/landing-page-1/', parse_url($result['frontend_url'], PHP_URL_PATH), 'page_upsert should return frontend_url');
lcfa_assert_contains('post.php?post=', $result['edit_url'], 'page_upsert should return edit_url');
```

- [ ] **Step 3: Add a failing direct-write policy test**

```php
LCFA_Settings::update([
    'permission_profile'  => 'draft_preview',
    'allow_file_fallback' => false,
]);

$request = new WP_REST_Request([
    'root_scope' => 'stylesheet',
    'path'       => 'assets/theme.css',
    'content'    => 'body{color:red;}',
    'dry_run'    => false,
]);

$response = $rest_api->save_theme_file($request);
$payload = $response->get_data();

lcfa_assert_true(isset($payload['result']['dry_run']), 'save_theme_file should return a write result');
lcfa_assert_true($payload['result']['dry_run'] === true, 'policy should downgrade direct theme writes to preview');
```

- [ ] **Step 4: Run the test to verify it fails**

Run: `php tests/php/foundation_contract_phase1.php`

Expected: FAIL because `page_upsert` is unsupported and direct theme writes are not downgraded to preview.

- [ ] **Step 5: Commit the red test**

```bash
git add tests/php/foundation_contract_phase1.php
git commit -m "test: add foundation contract phase 1 regressions"
```

### Task 2: Implement `page_upsert` With Final URLs

**Files:**
- Modify: `includes/class-lcfa-command-deck.php`
- Modify: `includes/class-lcfa-prompt-suggester.php`
- Modify: `includes/class-lcfa-admin.php`

- [ ] **Step 1: Add the `page_upsert` action metadata**

```php
'page_upsert' => [
    'label'       => __('Create or update page', 'livecanvas-forge-ai'),
    'description' => __('Creates a page when no target exists, or updates the existing LiveCanvas page and always returns final URLs.', 'livecanvas-forge-ai'),
],
```

- [ ] **Step 2: Implement `page_upsert` in the command deck**

```php
case 'page_upsert':
    $existing = $target_id ? $this->inventory->get_target_content('page', $target_id) : ['post' => null, 'content' => ''];
    $is_update = !empty($existing['post']);

    if ($is_update) {
        $updated = wp_update_post([
            'ID'           => $target_id,
            'post_title'   => $title !== '' ? $title : $existing['post']['title'],
            'post_name'    => $slug !== '' ? $slug : $existing['post']['slug'],
            'post_status'  => $status,
            'post_content' => $content,
        ], true);
    } else {
        $updated = wp_insert_post([
            'post_type'    => 'page',
            'post_title'   => $title,
            'post_name'    => $slug !== '' ? $slug : '',
            'post_status'  => $status,
            'post_content' => $content,
        ], true);
    }
```

- [ ] **Step 3: Normalize URL fields in page results**

```php
$page_id = (int) $updated;
update_post_meta($page_id, '_lc_livecanvas_enabled', '1');

$result['target_id']    = $page_id;
$result['target_type']  = 'page';
$result['target_title'] = html_entity_decode(get_the_title($page_id) ?: __('Untitled', 'livecanvas-forge-ai'));
$result['frontend_url'] = get_permalink($page_id);
$result['edit_url']     = get_edit_post_link($page_id, 'raw');
```

- [ ] **Step 4: Route page-oriented prompt suggestions to `page_upsert`**

```php
private function detect_page_action(string $prompt, int $target_id): string {
    return 'page_upsert';
}
```

- [ ] **Step 5: Keep current admin contexts backward compatible**

```php
if ($context['action'] === 'update_page') {
    $context['action'] = 'page_upsert';
}
```

- [ ] **Step 6: Run the focused test to verify it passes**

Run: `php tests/php/foundation_contract_phase1.php`

Expected: the `page_upsert` assertions pass, while the direct-write policy assertion still fails.

- [ ] **Step 7: Commit the green page-upsert slice**

```bash
git add includes/class-lcfa-command-deck.php includes/class-lcfa-prompt-suggester.php includes/class-lcfa-admin.php tests/php/foundation_contract_phase1.php
git commit -m "feat: add page_upsert foundation action"
```

### Task 3: Enforce Policy On Direct Theme Write REST Routes

**Files:**
- Modify: `includes/class-lcfa-command-deck.php`
- Modify: `includes/class-lcfa-rest-api.php`
- Modify: `tests/php/foundation_contract_phase1.php`

- [ ] **Step 1: Add a reusable public policy entrypoint**

```php
public function evaluate_action_policy_for_rest(string $action, bool $dry_run): array {
    return $this->evaluate_policy($action, $dry_run);
}
```

- [ ] **Step 2: Apply policy before direct theme writes**

```php
$policy = $this->command_deck->evaluate_action_policy_for_rest('write_theme_file', !empty($payload['dry_run']));

if (empty($policy['ok'])) {
    return new WP_REST_Response(['error' => (string) $policy['message']], 403);
}

$effective_dry_run = !empty($policy['force_preview']) ? true : !empty($payload['dry_run']);
```

- [ ] **Step 3: Preserve route compatibility while returning policy metadata**

```php
$result['policy'] = [
    'profile'             => $policy['profile'],
    'allow_file_fallback' => $policy['allow_file_fallback'],
    'force_preview'       => !empty($policy['force_preview']),
    'notice'              => (string) ($policy['notice'] ?? ''),
];
```

- [ ] **Step 4: Re-run the test to verify it passes**

Run: `php tests/php/foundation_contract_phase1.php`

Expected: PASS for both `page_upsert` and direct-write preview downgrade behavior.

- [ ] **Step 5: Commit the safety fix**

```bash
git add includes/class-lcfa-command-deck.php includes/class-lcfa-rest-api.php tests/php/foundation_contract_phase1.php
git commit -m "fix: enforce policy on direct theme write routes"
```

### Task 4: Verify The Slice End-To-End

**Files:**
- Modify: none expected

- [ ] **Step 1: Run the PHP syntax check**

Run: `for f in livecanvas-forge-ai.php includes/*.php; do php -l "$f" || exit 1; done`

Expected: all files report `No syntax errors detected`.

- [ ] **Step 2: Run the regression harness**

Run: `php tests/php/foundation_contract_phase1.php`

Expected: PASS with zero assertion failures.

- [ ] **Step 3: Review the diff**

Run: `git status --short && git diff --stat HEAD~2..HEAD`

Expected: only the planned plugin files and tests are changed.

- [ ] **Step 4: Commit any final cleanup**

```bash
git add livecanvas-forge-ai.php includes tests
git commit -m "chore: finalize foundation contract phase 1"
```
