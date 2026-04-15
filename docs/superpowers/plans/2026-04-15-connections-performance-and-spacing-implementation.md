# Connections Performance And Spacing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the `Connections` tab render faster by removing non-critical diagnostics from the initial request path and fix the compressed spacing between wizard sections.

**Architecture:** Keep the main onboarding flow server-rendered, but move `Remote site` and `Advanced settings` to an authenticated async payload loaded after first paint. Add a dedicated admin-side secondary payload builder and hydrate two placeholder sections from `assets/admin.js`. At the same time, introduce a single vertical rhythm wrapper for the wizard instead of relying on tight adjacent blocks.

**Tech Stack:** WordPress admin PHP, WordPress REST API, existing LCFA admin renderer, vanilla admin JS, existing PHP harness tests.

---

## File Map

- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
  - Stop eager secondary diagnostics work in `render_connections_tab()`.
  - Render placeholders for async panels.
  - Add structured helper methods for secondary payload building and panel rendering.
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-rest-api.php`
  - Register and serve the new admin-only `connections-secondary` endpoint.
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.js`
  - Fetch secondary diagnostics payload and hydrate placeholders after DOM ready.
  - Preserve existing copy/highlight behavior.
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.css`
  - Add spacing stack for the wizard.
  - Add placeholder and async panel loading/error styles.
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php`
  - Focused harness for lazy payload shape and initial render placeholders.
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_performance_phase1.php`
  - Extend regression coverage to prove the initial path avoids unnecessary secondary probes.
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php`
  - Assert placeholder structure and spacing wrapper markup where appropriate.

---

### Task 1: Add Failing Tests For Lazy Secondary Panels

**Files:**
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_performance_phase1.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php`

- [ ] **Step 1: Write the failing phase-2 harness for `Connections` placeholders and payload shape**

```php
<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('LCFA_VERSION', 'test-version');

action stubs and minimal WP helper stubs...

final class LCFA_Test_Remote_Client {
    public int $status_calls = 0;
    public function get_status(): array {
        $this->status_calls++;
        return ['available' => true, 'message' => 'Remote companion reachable.'];
    }
}

final class LCFA_Test_Context_Builder {
    public int $mcp_status_calls = 0;
    public int $bootstrap_calls = 0;
    public function get_mcp_status(): array {
        $this->mcp_status_calls++;
        return ['endpoint' => 'ws://127.0.0.1:7681'];
    }
    public function get_bootstrap_payload(): array {
        $this->bootstrap_calls++;
        return ['common' => [], 'clients' => []];
    }
}
```

- [ ] **Step 2: Add assertions that describe the new expected behavior**

```php
ob_start();
$render_connections_tab->invoke($admin, $settings, $snapshot);
$output = (string) ob_get_clean();

lcfa_assert_contains('data-lcfa-connections-secondary-root', $output, 'connections tab should render an async secondary root');
lcfa_assert_contains('data-lcfa-connections-panel="remote"', $output, 'connections tab should render a remote placeholder');
lcfa_assert_contains('data-lcfa-connections-panel="advanced"', $output, 'connections tab should render an advanced settings placeholder');
lcfa_assert_same(0, $remote_client->status_calls, 'initial connections render should not call remote status eagerly');
lcfa_assert_same(0, $context_builder->mcp_status_calls, 'initial connections render should not call mcp status eagerly for secondary panels');
lcfa_assert_same(0, $context_builder->bootstrap_calls, 'initial connections render should not build bootstrap payload eagerly');
```

- [ ] **Step 3: Extend the existing performance harness with the new lazy expectation**

```php
lcfa_assert_same(0, $GLOBALS['lcfa_test_remote_request_calls'], 'connections first paint should not trigger remote fetches');
```

- [ ] **Step 4: Run the new focused tests and verify they fail for the right reason**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_performance_phase1.php
```

Expected:
- `FAIL` because `render_connections_tab()` still calls remote/mcp/bootstrap work synchronously
- `FAIL` because placeholder containers are not present yet

- [ ] **Step 5: Commit the red tests**

```bash
git add /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_performance_phase1.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
git commit -m "test: define lazy connections secondary panel behavior"
```

### Task 2: Add Admin Secondary Payload Endpoint

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-rest-api.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php`

- [ ] **Step 1: Register the new route in REST API**

Add a route alongside the existing admin-safe routes:

```php
register_rest_route('lcfa/v1', '/admin/connections-secondary', [
    'methods'             => WP_REST_Server::READABLE,
    'callback'            => [$this, 'get_admin_connections_secondary'],
    'permission_callback' => [$this, 'can_manage_admin'],
]);
```

- [ ] **Step 2: Implement the endpoint callback with the exact payload shape**

```php
public function get_admin_connections_secondary(): WP_REST_Response {
    $payload = $this->admin->build_connections_secondary_payload();

    return new WP_REST_Response($payload, 200);
}
```

- [ ] **Step 3: Add the admin-side payload builder so logic stays centralized**

```php
public function build_connections_secondary_payload(): array {
    $connections = LCFA_Settings::get_connections();
    $snapshot = $this->environment->get_snapshot();
    $preferred_client = $this->normalize_connection_client((string) ($connections['preferred_client'] ?: 'codex'));
    $remote_status = $this->remote_client->get_status();
    $mcp_status = $this->context_builder->get_mcp_status();
    $mcp_bootstrap = $this->context_builder->get_bootstrap_payload();
    $preferred_bootstrap = $mcp_bootstrap['clients'][$preferred_client] ?? ($mcp_bootstrap['clients']['codex'] ?? ['command' => '', 'env' => []]);

    return [
        'remote_status' => $remote_status,
        'mcp_status' => $mcp_status,
        'bootstrap_payload' => $mcp_bootstrap,
        'preferred_bootstrap' => $preferred_bootstrap,
        'common_bootstrap' => $this->build_common_bootstrap_text($mcp_bootstrap),
        'command_example' => wp_json_encode([
            'action' => 'site_audit',
            'dry_run' => true,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        'workspace_root' => (string) ($connections['workspace_root'] ?? ''),
        'preferred_client' => $preferred_client,
        'snapshot_framework' => (string) ($snapshot['detected_framework'] ?? ''),
    ];
}
```

- [ ] **Step 4: Run the focused harness and verify the new route payload test passes**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php
```

Expected:
- payload contains `remote_status`, `mcp_status`, `bootstrap_payload`, `preferred_bootstrap`, `common_bootstrap`, `command_example`, `workspace_root`, `preferred_client`

- [ ] **Step 5: Commit the endpoint slice**

```bash
git add /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-rest-api.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php
git commit -m "feat: add async connections secondary payload"
```

### Task 3: Remove Secondary Diagnostics From Initial Connections Render

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_performance_phase1.php`

- [ ] **Step 1: Strip eager remote and secondary bootstrap work from `render_connections_tab()`**

Refactor the method so the initial path keeps only:

```php
$connections = LCFA_Settings::get_connections();
$connection_test = LCFA_Settings::consume_connection_test_result();
$preferred_client = $this->normalize_connection_client((string) ($connections['preferred_client'] ?: ($settings['ai_tool'] ?: 'codex')));
$selected_mode = $this->normalize_connection_mode((string) ($connections['connection_mode'] ?: 'local'));
$bundle = $this->build_selected_connection_bundle([...]);
$workspace_write_state = LCFA_Workspace_Access::inspect((string) ($bundle['workspace_root'] ?? ''));
$onboarding_state = $this->connection_onboarding->derive_state($connections, [
    'local_ready' => !empty($connections['mcp_token']),
    'remote_ready' => !empty($connections['remote_site_url']) && !empty($connections['remote_username']) && !empty($connections['remote_application_password']),
]);
$wizard_view = $this->connection_wizard_presenter->build([...]);
```

- [ ] **Step 2: Render lightweight placeholders instead of secondary cards directly**

Add a renderer like:

```php
private function render_connections_secondary_placeholders(): void {
    echo '<section class="lcfa-connections-secondary" data-lcfa-connections-secondary-root data-lcfa-connections-secondary-endpoint="' . esc_url(rest_url('lcfa/v1/admin/connections-secondary')) . '">';
    echo '<div class="lcfa-card lcfa-card--placeholder" data-lcfa-connections-panel="remote"><p>Loading remote diagnostics...</p></div>';
    echo '<div class="lcfa-card lcfa-card--placeholder" data-lcfa-connections-panel="advanced"><p>Loading advanced settings...</p></div>';
    echo '</section>';
}
```

- [ ] **Step 3: Reuse existing panel renderers for hydrated HTML**

Add two admin render helpers:

```php
public function render_remote_companion_card_from_payload(array $remote_status): void
public function render_advanced_connection_settings_from_payload(array $payload, array $connections): void
```

Keep these thin wrappers around the existing panel render methods so duplication stays low.

- [ ] **Step 4: Run tests to verify the initial render now stays light**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_performance_phase1.php
```

Expected:
- `PASS`
- remote/mcp/bootstrap counters stay at `0` during initial render path

- [ ] **Step 5: Commit the render split**

```bash
git add /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_performance_phase1.php
git commit -m "refactor: lazy-load connections secondary diagnostics"
```

### Task 4: Hydrate Secondary Panels In Admin JS

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.js`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php`

- [ ] **Step 1: Add structured dataset output to the placeholder root**

Render nonce and endpoint metadata in the placeholder root:

```php
echo '<section class="lcfa-connections-secondary" data-lcfa-connections-secondary-root data-endpoint="' . esc_url(rest_url('lcfa/v1/admin/connections-secondary')) . '" data-rest-nonce="' . esc_attr(wp_create_nonce('wp_rest')) . '">';
```

- [ ] **Step 2: Extend `assets/admin.js` with a dedicated secondary hydration path**

Add logic like:

```js
function hydrateConnectionsSecondary() {
  var root = document.querySelector('[data-lcfa-connections-secondary-root]');
  if (!root) return;

  fetch(root.getAttribute('data-endpoint'), {
    headers: { 'X-WP-Nonce': root.getAttribute('data-rest-nonce') || '' }
  })
    .then(function (response) { return response.ok ? response.json() : Promise.reject(response); })
    .then(function (payload) { renderConnectionsSecondary(root, payload); })
    .catch(function () { renderConnectionsSecondaryError(root); });
}
```

- [ ] **Step 3: Render the hydrated panel markup from server-generated partial endpoints or inline HTML strings**

Preferred implementation in this slice:
- add lightweight admin AJAX/REST-rendered HTML snippets through a single helper method invoked by the JS bootstrap payload already returned from PHP
- if HTML snippet generation becomes too invasive, serialize panel fields and render concise DOM client-side for the placeholders only

The important constraint:
- do not reintroduce synchronous work into the initial page render

- [ ] **Step 4: Add the compact error placeholder state**

Use output like:

```js
function renderConnectionsSecondaryError(root) {
  root.querySelectorAll('[data-lcfa-connections-panel]').forEach(function (panel) {
    panel.innerHTML = '<p class="lcfa-connections-secondary__error">Diagnostics could not be loaded. Reload the page to try again.</p>';
  });
}
```

- [ ] **Step 5: Run the focused harness and JS syntax check**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php
node --check /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.js
```

Expected:
- `PASS`
- no JS syntax errors

- [ ] **Step 6: Commit the hydration slice**

```bash
git add /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.js /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php
git commit -m "feat: hydrate connections diagnostics after first paint"
```

### Task 5: Fix Wizard Spacing System

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.css`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php`

- [ ] **Step 1: Add a wrapper that governs wizard vertical rhythm**

Wrap the major wizard sections in a stack container:

```php
echo '<div class="lcfa-wizard__stack">';
$this->render_connection_now_alert($banner);
$this->render_connection_stepper(...);
$this->render_connection_active_step_panel(...);
$this->render_connection_visual_help_strip(...);
$this->render_agent_connection_guide(...);
$this->render_connection_technical_summary(...);
echo '</div>';
```

- [ ] **Step 2: Add explicit gap rules in CSS**

```css
.lcfa-admin .lcfa-wizard__stack {
    display: grid;
    gap: 24px;
}

.lcfa-admin .lcfa-wizard__stack > .lcfa-visual-help,
.lcfa-admin .lcfa-wizard__stack > .lcfa-agent-guide,
.lcfa-admin .lcfa-wizard__stack > .lcfa-wizard__summary {
    margin-top: 4px;
}

@media (max-width: 782px) {
    .lcfa-admin .lcfa-wizard__stack {
        gap: 18px;
    }
}
```

- [ ] **Step 3: Tune the spacing specifically between alert and stepper**

```css
.lcfa-admin .lcfa-wizard__alert {
    margin: 0;
}

.lcfa-admin .lcfa-wizard__steps {
    margin: 0;
}
```

Use the wrapper gap, not local margin hacks, as the primary spacer.

- [ ] **Step 4: Add structure assertions to the wizard test harness**

```php
lcfa_assert_true(strpos($wizard_markup, 'lcfa-wizard__stack') !== false, 'connections wizard should render a dedicated spacing stack');
```

- [ ] **Step 5: Run the wizard regressions**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
```

Expected:
- `PASS`

- [ ] **Step 6: Commit the spacing slice**

```bash
git add /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.css /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
git commit -m "style: add coherent spacing rhythm to connections wizard"
```

### Task 6: Final Verification

**Files:**
- Verify only

- [ ] **Step 1: Run all targeted PHP regressions**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_performance_phase2.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_performance_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php
```

Expected:
- all `PASS`

- [ ] **Step 2: Run syntax checks**

Run:
```bash
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-rest-api.php
node --check /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.js
```

Expected:
- no syntax errors

- [ ] **Step 3: Manual browser verification on the local admin page**

Check:
1. Open `http://localhost:8887/wp-admin/admin.php?page=lcfa-dashboard&tab=connections`
2. Confirm first paint shows hero + wizard immediately
3. Confirm `Remote site` and `Advanced settings` appear after load
4. Confirm a failed async payload, if simulated, does not break the wizard
5. Confirm `lcfa-wizard__alert` and `lcfa-wizard__steps` are no longer visually glued together
6. Confirm support sections also breathe correctly below the active panel

- [ ] **Step 4: Commit the verified integration**

```bash
git add -A
git commit -m "perf: lazy-load connections diagnostics and improve spacing"
```

---

## Self-Review

Spec coverage:
- lazy loading of `Remote site` and `Advanced settings`: Tasks 2-4
- reduce synchronous work in `render_connections_tab()`: Task 3
- async endpoint for secondary payload: Task 2
- spacing system for wizard sections: Task 5
- regression and manual verification: Task 6

Placeholder scan:
- no `TODO` / `TBD`
- each code-changing step contains concrete code or exact target behavior

Type consistency:
- endpoint name stays `lcfa/v1/admin/connections-secondary`
- payload keys remain aligned with the spec
- main renderer stays `render_connections_tab()` throughout the plan
