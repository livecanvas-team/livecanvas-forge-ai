# Connections Plug-And-Play Wizard Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the current `Connections` flat settings form with a capability-aware onboarding flow that can generate, install, download, and verify coding-agent connection bundles for local and remote usage.

**Architecture:** Keep the existing MCP and REST contracts intact, but move the onboarding logic into focused helpers so `LCFA_Admin` stops owning bundle generation, state detection, file writing, and artifact serialization directly. Introduce one normalized bundle builder plus one onboarding/service layer, then rewire the `Connections` page to render a wizard-first UI with safe local writes and remote downloads.

**Tech Stack:** WordPress PHP plugin code, existing admin-post handlers, existing `LCFA_Context_Builder`, existing connection tester, PHP test harnesses with WordPress stubs, CSS in `assets/admin.css`

---

### Task 1: Add Red Tests For Bundle Building, Local Path Resolution, And Wizard State

**Files:**
- Create: `docs/superpowers/plans/2026-04-13-connections-plug-and-play-wizard-phase-1.md`
- Create: `tests/php/connections_wizard_phase1.php`
- Modify: `livecanvas-forge-ai.php`

- [ ] **Step 1: Create the failing PHP harness**

```php
<?php
// tests/php/connections_wizard_phase1.php

declare(strict_types=1);

define('LCFA_DIR', dirname(__DIR__, 2) . '/');

require LCFA_DIR . 'includes/class-lcfa-settings.php';
require LCFA_DIR . 'includes/class-lcfa-connection-bundle-builder.php';
require LCFA_DIR . 'includes/class-lcfa-connection-onboarding.php';

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}
```

- [ ] **Step 2: Add a failing local `OpenCode` bundle test**

```php
$builder = new LCFA_Connection_Bundle_Builder();

$bundle = $builder->build([
    'client'      => 'opencode',
    'mode'        => 'local',
    'workspace_root' => '/Users/commander/Studio/consultala',
    'common'      => [
        'rest_base' => 'http://localhost:8887/wp-json/lcfa/v1/',
        'mcp_token' => 'test-token',
        'wp_root'   => '/wordpress',
    ],
    'client_payload' => [
        'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --agent=opencode',
        'env'     => [
            'LCFA_REST_BASE=http://localhost:8887/wp-json/lcfa/v1/',
            'LCFA_MCP_TOKEN=test-token',
            'LCFA_WP_ROOT=/wordpress',
        ],
    ],
]);

lcfa_assert_same('opencode', $bundle['client'], 'bundle should preserve the selected client');
lcfa_assert_same('local', $bundle['mode'], 'bundle should preserve local mode');
lcfa_assert_same('/Users/commander/Studio/consultala/opencode.json', $bundle['workspace_files'][0]['path'], 'local OpenCode bundle should target workspace opencode.json');
lcfa_assert_same('/Users/commander/Studio/consultala', $bundle['environment']['LCFA_WP_ROOT'], 'local bundle should replace container wp_root with workspace_root');
```

- [ ] **Step 3: Add a failing remote bundle test**

```php
$remoteBundle = $builder->build([
    'client'      => 'opencode',
    'mode'        => 'remote',
    'workspace_root' => '',
    'common'      => [
        'rest_base' => 'https://example.com/wp-json/lcfa/v1/',
        'mcp_token' => 'remote-token',
        'wp_root'   => '/wordpress',
    ],
    'client_payload' => [
        'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --agent=opencode',
        'env'     => [
            'LCFA_REST_BASE=https://example.com/wp-json/lcfa/v1/',
            'LCFA_MCP_TOKEN=remote-token',
        ],
    ],
]);

lcfa_assert_true(empty($remoteBundle['environment']['LCFA_WP_ROOT']), 'remote bundle should not expose LCFA_WP_ROOT');
lcfa_assert_true(empty($remoteBundle['workspace_files']), 'remote bundle should not propose local workspace writes');
lcfa_assert_same('opencode.json', $remoteBundle['download_files'][0]['name'], 'remote bundle should expose downloadable client config');
```

- [ ] **Step 4: Add a failing onboarding-state test**

```php
$onboarding = new LCFA_Connection_Onboarding($builder);

$state = $onboarding->derive_state([
    'preferred_client' => 'opencode',
    'workspace_root'   => '/Users/commander/Studio/consultala',
    'connection_status' => '',
    'connection_last_verified_at' => '',
    'connection_last_error' => '',
], [
    'local_ready'  => true,
    'remote_ready' => false,
]);

lcfa_assert_same('not_connected', $state['status'], 'empty verification metadata should keep the page in not_connected');
```

- [ ] **Step 5: Run the harness to verify it fails**

Run: `php tests/php/connections_wizard_phase1.php`

Expected: FAIL because `LCFA_Connection_Bundle_Builder` and `LCFA_Connection_Onboarding` do not exist yet and no bundle normalization behavior exists.

- [ ] **Step 6: Commit the red test slice**

```bash
git add tests/php/connections_wizard_phase1.php docs/superpowers/plans/2026-04-13-connections-plug-and-play-wizard-phase-1.md
git commit -m "test: add connections wizard phase 1 regressions"
```

### Task 2: Implement Bundle Builder, Onboarding State, And Connection Metadata

**Files:**
- Modify: `livecanvas-forge-ai.php`
- Modify: `includes/class-lcfa-settings.php`
- Create: `includes/class-lcfa-connection-bundle-builder.php`
- Create: `includes/class-lcfa-connection-onboarding.php`
- Modify: `includes/class-lcfa-plugin.php`

- [ ] **Step 1: Extend connection defaults with onboarding metadata**

```php
public static function connection_defaults(): array {
    return [
        'transport'                   => 'rest',
        'picowind_package_url'        => '',
        'picostrap_package_url'       => '',
        'local_bridge_url'            => rest_url('lcfa/v1/'),
        'mcp_enabled'                 => true,
        'mcp_host'                    => '127.0.0.1',
        'mcp_port'                    => '7681',
        'mcp_token'                   => self::generate_mcp_token(),
        'remote_site_url'             => '',
        'remote_username'             => '',
        'remote_application_password' => '',
        'mcp_server_command'          => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio',
        'preferred_client'            => '',
        'workspace_root'              => '',
        'connection_status'           => '',
        'connection_mode'             => '',
        'connection_last_verified_at' => '',
        'connection_last_error'       => '',
        'connection_last_bundle_hash' => '',
    ];
}
```

- [ ] **Step 2: Sanitize the new fields**

```php
'workspace_root'              => sanitize_text_field($connections['workspace_root'] ?? ''),
'connection_status'           => sanitize_key($connections['connection_status'] ?? ''),
'connection_mode'             => in_array($connections['connection_mode'] ?? '', ['local', 'remote'], true) ? $connections['connection_mode'] : '',
'connection_last_verified_at' => sanitize_text_field($connections['connection_last_verified_at'] ?? ''),
'connection_last_error'       => sanitize_text_field($connections['connection_last_error'] ?? ''),
'connection_last_bundle_hash' => sanitize_text_field($connections['connection_last_bundle_hash'] ?? ''),
```

- [ ] **Step 3: Add the bundle builder**

```php
final class LCFA_Connection_Bundle_Builder {
    public function build(array $payload): array {
        $client = (string) ($payload['client'] ?? 'codex');
        $mode = (string) ($payload['mode'] ?? 'local');
        $workspaceRoot = $this->normalize_workspace_root((string) ($payload['workspace_root'] ?? ''));
        $command = $this->normalize_command((string) ($payload['client_payload']['command'] ?? ''));
        $environment = $this->normalize_environment((array) ($payload['client_payload']['env'] ?? []), $workspaceRoot, $mode);

        return [
            'client'          => $client,
            'mode'            => $mode,
            'server_name'     => 'livecanvas-forge',
            'command'         => $command,
            'environment'     => $environment,
            'workspace_files' => $this->build_workspace_files($client, $mode, $workspaceRoot, $command, $environment),
            'download_files'  => $this->build_download_files($client, $command, $environment),
            'smoke_test_command' => $this->build_smoke_test_command($environment),
            'status'          => 'generated',
        ];
    }
}
```

- [ ] **Step 4: Add the onboarding helper**

```php
final class LCFA_Connection_Onboarding {
    private LCFA_Connection_Bundle_Builder $bundle_builder;

    public function __construct(LCFA_Connection_Bundle_Builder $bundle_builder) {
        $this->bundle_builder = $bundle_builder;
    }

    public function derive_state(array $connections, array $capabilities): array {
        if (($connections['connection_status'] ?? '') === 'ready' && !empty($connections['connection_last_verified_at'])) {
            return ['status' => 'ready'];
        }

        if (!empty($connections['connection_last_error'])) {
            return ['status' => 'needs_attention'];
        }

        return ['status' => 'not_connected'];
    }
}
```

- [ ] **Step 5: Load and inject the new helpers**

```php
require_once LCFA_DIR . 'includes/class-lcfa-connection-bundle-builder.php';
require_once LCFA_DIR . 'includes/class-lcfa-connection-onboarding.php';
```

and in the plugin bootstrap:

```php
$bundle_builder = new LCFA_Connection_Bundle_Builder();
$connection_onboarding = new LCFA_Connection_Onboarding($bundle_builder);
```

- [ ] **Step 6: Run the focused harness and verify it passes**

Run: `php tests/php/connections_wizard_phase1.php`

Expected: PASS for local bundle normalization, remote bundle output, and onboarding-state defaults.

- [ ] **Step 7: Commit the backend foundation**

```bash
git add livecanvas-forge-ai.php includes/class-lcfa-settings.php includes/class-lcfa-connection-bundle-builder.php includes/class-lcfa-connection-onboarding.php includes/class-lcfa-plugin.php tests/php/connections_wizard_phase1.php
git commit -m "feat: add connection onboarding bundle builder"
```

### Task 3: Implement Admin Handlers For Bundle Install, Download, And Verification State

**Files:**
- Modify: `includes/class-lcfa-admin.php`
- Modify: `includes/class-lcfa-settings.php`
- Modify: `includes/class-lcfa-connection-tester.php`

- [ ] **Step 1: Register new admin-post handlers**

```php
add_action('admin_post_lcfa_install_client_bundle', [$this, 'handle_install_client_bundle_post']);
add_action('admin_post_lcfa_download_client_bundle', [$this, 'handle_download_client_bundle_post']);
add_action('admin_post_lcfa_reconfigure_connection', [$this, 'handle_reconfigure_connection_post']);
```

- [ ] **Step 2: Update the main connections save flow to persist wizard metadata**

```php
LCFA_Settings::update_connections(array_merge($_POST, [
    'connection_mode' => sanitize_key($_POST['connection_mode'] ?? ''),
]));
```

- [ ] **Step 3: Persist verification metadata after connection checks**

```php
$connections = LCFA_Settings::get_connections();
$connections['connection_last_verified_at'] = (string) ($result['checked_at'] ?? '');
$connections['connection_status'] = !empty($result['ok']) ? 'ready' : 'needs_attention';
$connections['connection_last_error'] = !empty($result['ok']) ? '' : (string) ($result['summary'] ?? '');
LCFA_Settings::update_connections($connections);
```

- [ ] **Step 4: Add safe local bundle installation**

```php
public function handle_install_client_bundle_post(): void {
    check_admin_referer('lcfa_install_client_bundle');

    $bundle = $this->build_selected_connection_bundle($_POST);
    $target = $bundle['workspace_files'][0] ?? null;

    if (!$target || empty($target['path'])) {
        LCFA_Settings::set_notice(__('No writable workspace artifact was generated for this client.', 'livecanvas-forge-ai'), 'error');
        wp_safe_redirect(admin_url('admin.php?page=lcfa-dashboard&tab=connections'));
        exit;
    }

    $this->write_connection_artifact((string) $target['path'], (string) ($target['content'] ?? ''), !empty($_POST['create_backup']));
}
```

- [ ] **Step 5: Add remote bundle download**

```php
public function handle_download_client_bundle_post(): void {
    check_admin_referer('lcfa_download_client_bundle');

    $bundle = $this->build_selected_connection_bundle($_REQUEST);
    $file = $bundle['download_files'][0] ?? null;

    if (!$file) {
        wp_die(esc_html__('No bundle is available for download.', 'livecanvas-forge-ai'));
    }

    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . sanitize_file_name((string) $file['name']) . '"');
    echo (string) ($file['content'] ?? '');
    exit;
}
```

- [ ] **Step 6: Re-run the harness and a syntax check**

Run: `php tests/php/connections_wizard_phase1.php`

Expected: PASS remains green after handler changes.

Run: `php -l includes/class-lcfa-admin.php`

Expected: `No syntax errors detected in includes/class-lcfa-admin.php`

- [ ] **Step 7: Commit the admin-post slice**

```bash
git add includes/class-lcfa-admin.php includes/class-lcfa-settings.php includes/class-lcfa-connection-tester.php
git commit -m "feat: add connection bundle install and download handlers"
```

### Task 4: Rebuild The Connections UI Around Wizard State, Bundle Actions, And Advanced Settings

**Files:**
- Modify: `includes/class-lcfa-admin.php`
- Modify: `assets/admin.css`
- Modify: `README.md`

- [ ] **Step 1: Move the coding-agent section to the top and branch on page state**

```php
$bundle = $this->build_preferred_connection_bundle($connections, $mcp_bootstrap, $remote_status);
$onboarding_state = $this->connection_onboarding->derive_state($connections, [
    'local_ready'  => !empty($mcp_status['rest_base']),
    'remote_ready' => !empty($remote_status['available']),
]);

$this->render_agent_connection_hero($bundle, $onboarding_state, $mcp_status);
```

- [ ] **Step 2: Render the wizard when state is `not_connected` or `needs_attention`**

```php
if (($onboarding_state['status'] ?? 'not_connected') !== 'ready') {
    $this->render_connection_wizard($bundle, $connections, $remote_status, $onboarding_state);
} else {
    $this->render_connection_ready_card($bundle, $connections, $onboarding_state);
}
```

- [ ] **Step 3: Move the current low-level form into `Advanced settings`**

```php
echo '<details class="lcfa-advanced-settings">';
echo '<summary>' . esc_html__('Advanced settings', 'livecanvas-forge-ai') . '</summary>';
$this->render_connection_profile_form($connections, $preferred_client);
echo '</details>';
```

- [ ] **Step 4: Add workspace-root UX for local installs**

```php
echo '<label><span>' . esc_html__('Local workspace root', 'livecanvas-forge-ai') . '</span><input type="text" name="workspace_root" value="' . esc_attr($connections['workspace_root']) . '" placeholder="/Users/you/project"></label>';
echo '<p class="description">' . esc_html__('Use the real project path on your machine. This is required when WordPress runtime paths differ from your host workspace path.', 'livecanvas-forge-ai') . '</p>';
```

- [ ] **Step 5: Add wizard and readiness styles**

```css
.lcfa-admin .lcfa-onboarding-hero {
    display: grid;
    gap: 16px;
}

.lcfa-admin .lcfa-wizard {
    display: grid;
    gap: 18px;
}

.lcfa-admin .lcfa-wizard__steps {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 10px;
}

.lcfa-admin .lcfa-ready-card {
    display: grid;
    gap: 14px;
}
```

- [ ] **Step 6: Update the README to match the new Connections flow**

```md
## Connections workflow

The Connections page is now wizard-first:

1. choose your coding agent
2. choose local or remote mode
3. review the generated MCP payload
4. install locally or download a client bundle
5. verify the setup with `get_snapshot`
```

- [ ] **Step 7: Run syntax and focused regression checks**

Run: `php tests/php/connections_wizard_phase1.php`

Expected: PASS

Run: `php -l includes/class-lcfa-admin.php && php -l includes/class-lcfa-connection-bundle-builder.php && php -l includes/class-lcfa-connection-onboarding.php`

Expected: `No syntax errors detected ...` for each file.

- [ ] **Step 8: Commit the UI slice**

```bash
git add includes/class-lcfa-admin.php assets/admin.css README.md
git commit -m "feat: redesign connections as onboarding wizard"
```

### Task 5: Manual Verification On The Local WordPress And OpenCode Smoke-Test Path

**Files:**
- Modify: none expected

- [ ] **Step 1: Run the existing foundation regression**

Run: `php tests/php/foundation_contract_phase1.php`

Expected: `PASS`

- [ ] **Step 2: Run the new wizard regression**

Run: `php tests/php/connections_wizard_phase1.php`

Expected: `PASS`

- [ ] **Step 3: Verify the page renders in wp-admin**

Run:

```bash
php -r "require '/Users/commander/Studio/consultala/wp-load.php'; echo rest_url('lcfa/v1/'), PHP_EOL;"
```

Expected: prints the local REST base without fatal errors.

- [ ] **Step 4: Verify the MCP bridge still works with the generated payload**

Run:

```bash
LCFA_REST_BASE='http://localhost:8887/wp-json/lcfa/v1/' \
LCFA_MCP_TOKEN='YOUR_TOKEN_HERE' \
LCFA_WP_ROOT='/Users/commander/Studio/consultala' \
node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js \
  --tool get_snapshot \
  --output pretty
```

Expected: JSON snapshot output with `livecanvas_active`, current framework, and MCP metadata.

- [ ] **Step 5: Commit verification-only fixes if needed**

```bash
git add -A
git commit -m "test: verify connections wizard flow"
```

## Spec Coverage Check

- page order is covered in Task 4
- wizard states are covered in Tasks 1, 2, and 4
- local vs remote bundle behavior is covered in Tasks 1, 2, and 3
- local safe write flow is covered in Task 3
- remote download flow is covered in Task 3
- verification and readiness are covered in Tasks 3, 4, and 5
- advanced settings fallback is covered in Task 4

## Placeholder Scan

No `TBD`, `TODO`, or “similar to previous task” shortcuts remain in this plan.

## Type Consistency Check

The plan consistently uses:

- `LCFA_Connection_Bundle_Builder`
- `LCFA_Connection_Onboarding`
- `workspace_root`
- `connection_status`
- `connection_mode`
- `connection_last_verified_at`
- `connection_last_error`
- `connection_last_bundle_hash`

No conflicting names are introduced later in the plan.
