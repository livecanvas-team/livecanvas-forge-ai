# Connections Linear Wizard Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convert the `Connections` tab into a true linear wizard with one active step, one primary CTA, explicit next-action alerts, and a lower-priority technical summary.

**Architecture:** Keep the existing bundle builder, workspace access helper, and REST/MCP contract intact. Move wizard progression into `LCFA_Connection_Onboarding`, add a small presenter to turn state into renderable step/alert/CTA data, then rewire `LCFA_Admin` to render only the active step instead of the current dashboard-like layout.

**Tech Stack:** WordPress PHP plugin code, existing admin-post handlers, PHP harness tests with WordPress stubs, CSS in `assets/admin.css`

---

## File Map

- `includes/class-lcfa-settings.php`
  - Persist linear-wizard state such as `connection_current_step`.
- `includes/class-lcfa-connection-onboarding.php`
  - Own wizard progression and connection state derivation.
- `includes/class-lcfa-connection-wizard-presenter.php`
  - New pure-PHP presentation helper that builds stepper state, alert copy, and CTA metadata.
- `includes/class-lcfa-admin.php`
  - Render hero, `What to do now` alert, stateful stepper, one active step panel, and ready card based on presenter output.
- `livecanvas-forge-ai.php`
  - Load the new presenter file.
- `assets/admin.css`
  - Style the active/done/locked stepper, alert hierarchy, and single-panel wizard layout.
- `tests/php/connections_wizard_phase1.php`
  - Extend the existing harness with linear wizard state, CTA, and presenter assertions.

---

### Task 1: Add Red Tests For Linear Wizard State And CTA Selection

**Files:**
- Modify: `tests/php/connections_wizard_phase1.php`
- Create: `includes/class-lcfa-connection-wizard-presenter.php`

- [ ] **Step 1: Extend the harness to load the new presenter**

```php
require LCFA_DIR . 'includes/class-lcfa-connection-bundle-builder.php';
require LCFA_DIR . 'includes/class-lcfa-connection-onboarding.php';
require LCFA_DIR . 'includes/class-lcfa-connection-wizard-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-workspace-access.php';
```

- [ ] **Step 2: Add a failing onboarding-state test for `choose_client`**

```php
$onboarding = new LCFA_Connection_Onboarding($builder);

$state = $onboarding->derive_state([
    'preferred_client'            => '',
    'connection_mode'             => '',
    'workspace_root'              => '',
    'connection_status'           => '',
    'connection_last_verified_at' => '',
    'connection_last_error'       => '',
    'connection_current_step'     => '',
]);

lcfa_assert_same('not_connected', $state['status'] ?? '', 'missing client should keep the connection in not_connected');
lcfa_assert_same('choose_client', $state['current_step'] ?? '', 'missing client should open the choose_client step');
```

- [ ] **Step 3: Add a failing onboarding-state test for `generate_bundle`**

```php
$bundleState = $onboarding->derive_state([
    'preferred_client'            => 'opencode',
    'connection_mode'             => 'local',
    'workspace_root'              => '/Users/commander/Studio/consultala',
    'connection_status'           => '',
    'connection_last_verified_at' => '',
    'connection_last_error'       => '',
    'connection_current_step'     => 'generate_bundle',
]);

lcfa_assert_same('generate_bundle', $bundleState['current_step'] ?? '', 'saved wizard progress should reopen the bundle step');
```

- [ ] **Step 4: Add a failing presenter test for local non-writable primary CTA**

```php
$presenter = new LCFA_Connection_Wizard_Presenter();
$view = $presenter->build([
    'state' => [
        'status'       => 'not_connected',
        'current_step' => 'generate_bundle',
    ],
    'bundle' => [
        'client'          => 'opencode',
        'mode'            => 'local',
        'workspace_root'  => '/Users/commander/Studio/consultala',
        'workspace_files' => [['path' => '/Users/commander/Studio/consultala/opencode.json', 'content' => '{}']],
        'download_files'  => [['name' => 'opencode.json', 'content' => '{}']],
    ],
    'workspace_access' => [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ],
]);

lcfa_assert_same('Download client bundle', $view['active_panel']['primary_cta']['label'] ?? '', 'non-writable local mode should prioritize download');
lcfa_assert_true(($view['technical_summary']['expanded'] ?? false) === true, 'bundle step should expand the technical summary');
lcfa_assert_same('active', $view['steps'][3]['state'] ?? '', 'generate_bundle should be the active step');
lcfa_assert_same('locked', $view['steps'][4]['state'] ?? '', 'smoke_test should remain locked before bundle completion');
```

- [ ] **Step 5: Add a failing presenter test for ready state**

```php
$readyView = $presenter->build([
    'state' => [
        'status'           => 'ready',
        'current_step'     => 'ready',
        'last_verified_at' => '2026-04-13 21:00:00',
    ],
    'bundle' => [
        'client'         => 'opencode',
        'mode'           => 'local',
        'workspace_root' => '/Users/commander/Studio/consultala',
    ],
    'workspace_access' => [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ],
]);

lcfa_assert_same('ready', $readyView['mode'] ?? '', 'presenter should switch to ready mode after a passing smoke test');
lcfa_assert_same('Run checks', $readyView['ready_panel']['primary_cta']['label'] ?? '', 'ready state should expose Run checks as primary action');
```

- [ ] **Step 6: Run the test to verify it fails**

Run: `php tests/php/connections_wizard_phase1.php`

Expected: FAIL because `derive_state()` does not yet expose `current_step` and the presenter class does not exist.

- [ ] **Step 7: Commit the red test**

```bash
git add tests/php/connections_wizard_phase1.php
git commit -m "test: add linear connections wizard regressions"
```

### Task 2: Implement Wizard Progression In Settings And Onboarding

**Files:**
- Modify: `includes/class-lcfa-settings.php`
- Modify: `includes/class-lcfa-connection-onboarding.php`

- [ ] **Step 1: Add the persisted wizard step field**

```php
// includes/class-lcfa-settings.php
return [
    // ...
    'connection_last_bundle_hash' => '',
    'connection_current_step'     => '',
];
```

- [ ] **Step 2: Sanitize the new field**

```php
'connection_current_step' => in_array($connections['connection_current_step'] ?? '', [
    '',
    'choose_client',
    'choose_mode',
    'confirm_details',
    'generate_bundle',
    'smoke_test',
    'ready',
], true) ? $connections['connection_current_step'] : '',
```

- [ ] **Step 3: Expand `derive_state()` into a real step-state machine**

```php
public function derive_state(array $connections, array $capabilities = []): array {
    $status = sanitize_key((string) ($connections['connection_status'] ?? ''));
    $saved_step = sanitize_key((string) ($connections['connection_current_step'] ?? ''));
    $preferred_client = sanitize_key((string) ($connections['preferred_client'] ?? ''));
    $connection_mode = sanitize_key((string) ($connections['connection_mode'] ?? ''));
    $last_verified_at = sanitize_text_field((string) ($connections['connection_last_verified_at'] ?? ''));
    $last_error = sanitize_text_field((string) ($connections['connection_last_error'] ?? ''));

    if ($status === 'ready' && $last_verified_at !== '') {
        return [
            'status'           => 'ready',
            'current_step'     => 'ready',
            'last_verified_at' => $last_verified_at,
            'message'          => __('Connection verified.', 'livecanvas-forge-ai'),
        ];
    }

    if ($last_error !== '') {
        return [
            'status'       => 'needs_attention',
            'current_step' => $saved_step !== '' ? $saved_step : 'smoke_test',
            'message'      => $last_error,
        ];
    }

    if ($preferred_client === '') {
        return [
            'status'       => 'not_connected',
            'current_step' => 'choose_client',
            'message'      => __('Choose the coding agent you want to connect.', 'livecanvas-forge-ai'),
        ];
    }

    if ($connection_mode === '') {
        return [
            'status'       => 'not_connected',
            'current_step' => 'choose_mode',
            'message'      => __('Choose whether the coding agent should target this local site or a remote site.', 'livecanvas-forge-ai'),
        ];
    }

    return [
        'status'       => 'not_connected',
        'current_step' => $saved_step !== '' ? $saved_step : 'confirm_details',
        'message'      => __('Confirm the connection details and generate the client bundle.', 'livecanvas-forge-ai'),
    ];
}
```

- [ ] **Step 4: Add a helper to advance the wizard step predictably**

```php
public function next_step(string $current_step): string {
    $order = [
        'choose_client',
        'choose_mode',
        'confirm_details',
        'generate_bundle',
        'smoke_test',
        'ready',
    ];

    $index = array_search($current_step, $order, true);
    if ($index === false || !isset($order[$index + 1])) {
        return $current_step;
    }

    return $order[$index + 1];
}
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php tests/php/connections_wizard_phase1.php`

Expected: still FAIL because the presenter is not implemented yet, but the missing `current_step` failures should be gone.

- [ ] **Step 6: Commit the onboarding state changes**

```bash
git add includes/class-lcfa-settings.php includes/class-lcfa-connection-onboarding.php tests/php/connections_wizard_phase1.php
git commit -m "feat: add linear connections wizard state"
```

### Task 3: Implement The Presenter For Steps, Alerts, And CTAs

**Files:**
- Create: `includes/class-lcfa-connection-wizard-presenter.php`
- Modify: `livecanvas-forge-ai.php`
- Modify: `tests/php/connections_wizard_phase1.php`

- [ ] **Step 1: Create the presenter skeleton**

```php
<?php

defined('ABSPATH') || exit;

final class LCFA_Connection_Wizard_Presenter {
    public function build(array $payload): array {
        $state = is_array($payload['state'] ?? null) ? $payload['state'] : [];
        $bundle = is_array($payload['bundle'] ?? null) ? $payload['bundle'] : [];
        $workspace_access = is_array($payload['workspace_access'] ?? null) ? $payload['workspace_access'] : [];
        $current_step = (string) ($state['current_step'] ?? 'choose_client');

        if (($state['status'] ?? '') === 'ready') {
            return [
                'mode'        => 'ready',
                'steps'       => $this->build_steps('ready'),
                'ready_panel' => $this->build_ready_panel($state, $bundle, $workspace_access),
            ];
        }

        return [
            'mode'              => 'wizard',
            'banner'            => $this->build_banner($current_step, $bundle, $workspace_access),
            'steps'             => $this->build_steps($current_step),
            'active_panel'      => $this->build_active_panel($current_step, $bundle, $workspace_access),
            'technical_summary' => $this->build_technical_summary($current_step, $bundle),
        ];
    }
}
```

- [ ] **Step 2: Implement step-state generation**

```php
private function build_steps(string $current_step): array {
    $order = [
        'choose_client'   => ['title' => __('Choose your coding agent', 'livecanvas-forge-ai'), 'helper' => __('Pick the client', 'livecanvas-forge-ai')],
        'choose_mode'     => ['title' => __('Choose local or remote', 'livecanvas-forge-ai'), 'helper' => __('Choose the target', 'livecanvas-forge-ai')],
        'confirm_details' => ['title' => __('Confirm connection details', 'livecanvas-forge-ai'), 'helper' => __('Review the inputs', 'livecanvas-forge-ai')],
        'generate_bundle' => ['title' => __('Generate the client bundle', 'livecanvas-forge-ai'), 'helper' => __('Create the config', 'livecanvas-forge-ai')],
        'smoke_test'      => ['title' => __('Run the smoke test', 'livecanvas-forge-ai'), 'helper' => __('Verify the connection', 'livecanvas-forge-ai')],
    ];

    $keys = array_keys($order);
    $activeIndex = $current_step === 'ready' ? count($keys) : max(0, (int) array_search($current_step, $keys, true));

    $steps = [];
    foreach ($keys as $index => $key) {
        $steps[] = [
            'key'    => $key,
            'number' => sprintf('%02d', $index + 1),
            'title'  => $order[$key]['title'],
            'helper' => $order[$key]['helper'],
            'state'  => $index < $activeIndex ? 'done' : ($index === $activeIndex ? 'active' : 'locked'),
        ];
    }

    return $steps;
}
```

- [ ] **Step 3: Implement CTA and alert selection for `generate_bundle`**

```php
private function build_active_panel(string $current_step, array $bundle, array $workspace_access): array {
    if ($current_step === 'generate_bundle') {
        $localWritable = ($bundle['mode'] ?? 'local') === 'local' && !empty($workspace_access['available']) && !empty($bundle['workspace_files']);

        return [
            'title'       => __('How do you want to continue?', 'livecanvas-forge-ai'),
            'description' => __('Create the client configuration now, then move to verification.', 'livecanvas-forge-ai'),
            'alert'       => [
                'eyebrow' => __('What to do now', 'livecanvas-forge-ai'),
                'title'   => $localWritable ? __('Write the client config in this workspace', 'livecanvas-forge-ai') : __('Download the client bundle', 'livecanvas-forge-ai'),
                'body'    => $localWritable
                    ? __('Forge AI can write the client artifact directly inside this workspace.', 'livecanvas-forge-ai')
                    : __('This browser runtime cannot write to the selected host workspace directly. Download the bundle, open the project in your coding agent, then return here for the smoke test.', 'livecanvas-forge-ai'),
                'next'    => __('After this step, come back here and run the smoke test.', 'livecanvas-forge-ai'),
            ],
            'primary_cta' => [
                'label'  => $localWritable ? __('Write config in workspace', 'livecanvas-forge-ai') : __('Download client bundle', 'livecanvas-forge-ai'),
                'action' => $localWritable ? 'install' : 'download',
            ],
        ];
    }

    return [
        'title'       => __('Which coding agent are you connecting?', 'livecanvas-forge-ai'),
        'description' => __('Start with the client choice, then the wizard will guide the rest of the setup.', 'livecanvas-forge-ai'),
    ];
}
```

- [ ] **Step 4: Load the new presenter file**

```php
require_once LCFA_DIR . 'includes/class-lcfa-connection-wizard-presenter.php';
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `php tests/php/connections_wizard_phase1.php`

Expected: PASS

- [ ] **Step 6: Commit the presenter**

```bash
git add includes/class-lcfa-connection-wizard-presenter.php livecanvas-forge-ai.php tests/php/connections_wizard_phase1.php
git commit -m "feat: add connections wizard presenter"
```

### Task 4: Rewire `LCFA_Admin` To Render One Active Step

**Files:**
- Modify: `includes/class-lcfa-admin.php`

- [ ] **Step 1: Add the presenter dependency**

```php
private LCFA_Connection_Wizard_Presenter $connection_wizard_presenter;

public function __construct() {
    // ...
    $this->connection_wizard_presenter = new LCFA_Connection_Wizard_Presenter();
}
```

- [ ] **Step 2: Build the presentation model inside the `Connections` tab**

```php
$onboarding_state = $this->connection_onboarding->derive_state($connections, [
    'local_ready'  => !empty($mcp_status['local_bridge']['available']),
    'remote_ready' => !empty($remote_status['connected']),
]);
$workspace_state = LCFA_Workspace_Access::inspect((string) ($bundle['workspace_root'] ?? ''));
$wizard_view = $this->connection_wizard_presenter->build([
    'state'            => $onboarding_state,
    'bundle'           => $bundle,
    'workspace_access' => $workspace_state,
]);
```

- [ ] **Step 3: Replace the static `<ol>` step list with a stateful stepper**

```php
echo '<ol class="lcfa-wizard__steps">';
foreach ($wizard_view['steps'] as $step) {
    echo '<li class="is-' . esc_attr((string) $step['state']) . '">';
    echo '<span class="lcfa-wizard__step-number">' . esc_html((string) $step['number']) . '</span>';
    echo '<strong>' . esc_html((string) $step['title']) . '</strong>';
    echo '<span class="lcfa-wizard__step-helper">' . esc_html((string) $step['helper']) . '</span>';
    echo '<span class="lcfa-wizard__step-state">' . esc_html(ucfirst((string) $step['state'])) . '</span>';
    echo '</li>';
}
echo '</ol>';
```

- [ ] **Step 4: Render only the active step card**

```php
$panel = $wizard_view['active_panel'];

echo '<section class="lcfa-wizard__panel">';
echo '<div class="lcfa-wizard__alert">';
echo '<span class="lcfa-wizard__alert-eyebrow">' . esc_html((string) ($panel['alert']['eyebrow'] ?? __('What to do now', 'livecanvas-forge-ai'))) . '</span>';
echo '<h3>' . esc_html((string) ($panel['alert']['title'] ?? '')) . '</h3>';
echo '<p>' . esc_html((string) ($panel['alert']['body'] ?? '')) . '</p>';
echo '<p class="lcfa-wizard__next">' . esc_html((string) ($panel['alert']['next'] ?? '')) . '</p>';
echo '</div>';
// Render only the controls for the current step here.
echo '</section>';
```

- [ ] **Step 5: Gate the technical summary until step 4**

```php
if (!empty($wizard_view['technical_summary']['expanded'])) {
    $this->render_connection_bundle_details($bundle);
}
```

- [ ] **Step 6: Run the test to verify no regressions**

Run:

```bash
php tests/php/connections_wizard_phase1.php
php tests/php/foundation_contract_phase1.php
```

Expected: PASS

- [ ] **Step 7: Commit the linear wizard rendering**

```bash
git add includes/class-lcfa-admin.php
git commit -m "feat: render connections as linear wizard"
```

### Task 5: Persist Step Transitions In The Existing POST Handlers

**Files:**
- Modify: `includes/class-lcfa-admin.php`

- [ ] **Step 1: Capture the current wizard step in the main form**

```php
echo '<input type="hidden" name="connection_current_step" value="' . esc_attr((string) ($onboarding_state['current_step'] ?? 'choose_client')) . '">';
```

- [ ] **Step 2: Advance the step in `handle_connections_post()`**

```php
$current_step = sanitize_key((string) ($_POST['connection_current_step'] ?? 'choose_client'));
$next_step = $this->connection_onboarding->next_step($current_step);

$connections = LCFA_Settings::sanitize_connections(array_merge(LCFA_Settings::get_connections(), $_POST, [
    'connection_status'           => '',
    'connection_last_verified_at' => '',
    'connection_last_error'       => '',
    'connection_current_step'     => $next_step,
]));
```

- [ ] **Step 3: Mark bundle generation as complete in install/download flows**

```php
$connections['connection_current_step'] = 'smoke_test';
$connections['connection_last_bundle_hash'] = md5((string) ($target['content'] ?? ''));
```

```php
$connections = LCFA_Settings::get_connections();
$connections['connection_current_step'] = 'smoke_test';
LCFA_Settings::update_connections($connections);
```

- [ ] **Step 4: Move into `ready` on successful smoke test and reopen the failing step on error**

```php
$connections['connection_status'] = !empty($result['ok']) ? 'ready' : 'needs_attention';
$connections['connection_current_step'] = !empty($result['ok']) ? 'ready' : 'smoke_test';
```

- [ ] **Step 5: Reset wizard progress on reconfigure**

```php
$connections['connection_status'] = '';
$connections['connection_last_verified_at'] = '';
$connections['connection_last_error'] = '';
$connections['connection_current_step'] = 'choose_client';
```

- [ ] **Step 6: Run the regression tests**

Run:

```bash
php tests/php/connections_wizard_phase1.php
php tests/php/foundation_contract_phase1.php
```

Expected: PASS

- [ ] **Step 7: Commit the step-transition wiring**

```bash
git add includes/class-lcfa-admin.php includes/class-lcfa-settings.php includes/class-lcfa-connection-onboarding.php
git commit -m "feat: persist connections wizard progression"
```

### Task 6: Update Styles For Active, Done, Locked, And Alert Hierarchy

**Files:**
- Modify: `assets/admin.css`

- [ ] **Step 1: Add stateful stepper styles**

```css
.lcfa-admin .lcfa-wizard__steps li.is-active {
    border-color: rgba(38, 198, 218, 0.42);
    background: linear-gradient(180deg, rgba(38, 198, 218, 0.16), rgba(255, 255, 255, 0.05));
    box-shadow: 0 18px 40px rgba(13, 16, 40, 0.28);
}

.lcfa-admin .lcfa-wizard__steps li.is-done {
    border-color: rgba(77, 201, 126, 0.28);
    background: rgba(77, 201, 126, 0.08);
}

.lcfa-admin .lcfa-wizard__steps li.is-locked {
    opacity: 0.56;
}
```

- [ ] **Step 2: Add the main `What to do now` alert styling**

```css
.lcfa-admin .lcfa-wizard__alert {
    display: grid;
    gap: 10px;
    padding: 20px 22px;
    border-radius: 20px;
    border: 1px solid rgba(38, 198, 218, 0.24);
    background: radial-gradient(circle at top left, rgba(38, 198, 218, 0.16), rgba(255, 255, 255, 0.04));
}

.lcfa-admin .lcfa-wizard__alert-eyebrow {
    color: var(--color1);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
```

- [ ] **Step 3: Reduce the visual weight of the technical summary**

```css
.lcfa-admin .lcfa-wizard__summary {
    opacity: 0.9;
    border-top: 1px solid var(--lcfa-border);
}

.lcfa-admin .lcfa-wizard__summary.is-collapsed {
    display: none;
}
```

- [ ] **Step 4: Run syntax-safe verification**

Run:

```bash
php -l includes/class-lcfa-admin.php
php -l includes/class-lcfa-connection-onboarding.php
php -l includes/class-lcfa-connection-wizard-presenter.php
php -l includes/class-lcfa-settings.php
php tests/php/connections_wizard_phase1.php
php tests/php/foundation_contract_phase1.php
```

Expected: all PHP lint commands report `No syntax errors detected`; both harnesses report `PASS`.

- [ ] **Step 5: Commit the styling pass**

```bash
git add assets/admin.css
git commit -m "style: clarify linear connections wizard flow"
```

### Task 7: Manual Verification And Copy Tightening

**Files:**
- Modify: `includes/class-lcfa-admin.php`
- Modify: `assets/admin.css`

- [ ] **Step 1: Verify the local OpenCode flow in the browser**

Run through:

1. Open `http://localhost:8887/wp-admin/admin.php?page=lcfa-dashboard&tab=connections`
2. Confirm only one step panel is active.
3. Confirm `What to do now` is visible above the active controls.
4. Confirm step 4 makes `Download client bundle` the primary CTA when browser writes are unavailable.

Expected: the next action is obvious without scanning the whole page.

- [ ] **Step 2: Verify the ready-state path**

After a successful smoke test, confirm:

1. the stepper no longer shows multiple open controls
2. the ready card is compact
3. `Run checks` is the primary action

- [ ] **Step 3: Tighten copy only if the browser test reveals ambiguity**

```php
__('Open this project in your coding agent, let the MCP bridge start once, then return here and run the smoke test.', 'livecanvas-forge-ai')
```

- [ ] **Step 4: Re-run the automated checks**

Run:

```bash
php tests/php/connections_wizard_phase1.php
php tests/php/foundation_contract_phase1.php
```

Expected: PASS

- [ ] **Step 5: Commit the final polish**

```bash
git add includes/class-lcfa-admin.php assets/admin.css
git commit -m "polish: simplify connections wizard guidance"
```
