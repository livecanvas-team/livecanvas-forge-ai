# OpenCode Fast-Path Wizard Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Shorten the local OpenCode onboarding flow in `Connections` to the minimum reliable path and add visual helper cards below the active step without breaking the existing MCP and smoke-test contract.

**Architecture:** Keep the current linear wizard shell, but make the presenter client-aware so `OpenCode + local` collapses into a shorter four-step path and exposes visual helper metadata. Update the admin renderer to show a dedicated visual strip below the active panel and demote the technical summary, then add focused CSS so the new guidance is clear without reworking the rest of the dashboard.

**Tech Stack:** WordPress admin PHP, existing `LCFA_Connection_Wizard_Presenter`, `LCFA_Admin`, plugin admin CSS, PHP regression harnesses

---

## File Map

- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-connection-wizard-presenter.php`
  - Make step definitions client-aware for `OpenCode + local`
  - Expose visual help metadata for the active step
  - Keep ready-state behavior unchanged
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
  - Render the shortened flow copy
  - Render the visual help strip below the active step
  - Keep technical summary secondary
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.css`
  - Style the new visual strip and reduce noise in the active-step area
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/agent-icons/`
  - Store exported/copied official client icons used by the wizard UI
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php`
  - Add presenter-level and render-adjacent assertions for the OpenCode fast path
- Optional sanity check only, no code change planned: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php`

### Task 1: Teach The Presenter The OpenCode Fast Path

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-connection-wizard-presenter.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php`

- [ ] **Step 1: Write the failing presenter tests for the shorter OpenCode path**

Add assertions like these to `connections_wizard_phase1.php` after the existing presenter checks:

```php
$opencode_fast_path = $presenter->build([
    'state' => [
        'status'       => 'not_connected',
        'current_step' => 'generate_bundle',
    ],
    'bundle' => [
        'client'         => 'opencode',
        'mode'           => 'local',
        'workspace_root' => '/Users/commander/Studio/consultala',
        'environment'    => [
            'LCFA_REST_BASE' => 'http://localhost:8887/wp-json/lcfa/v1/',
            'LCFA_MCP_TOKEN' => 'test-token',
        ],
        'workspace_files' => [
            ['path' => '/Users/commander/Studio/consultala/opencode.json', 'content' => '{}'],
        ],
        'download_files' => [
            ['name' => 'opencode.json', 'content' => '{}'],
        ],
    ],
    'workspace_access' => [
        'available' => false,
        'reason'    => 'unreachable',
        'path'      => '/Users/commander/Studio/consultala',
    ],
]);

lcfa_assert_same('Download opencode.json', $opencode_fast_path['active_panel']['primary_cta']['label'] ?? '', 'OpenCode fast path should use direct bundle copy');
lcfa_assert_same('What this looks like in OpenCode', $opencode_fast_path['visual_help']['title'] ?? '', 'OpenCode fast path should expose visual helper strip');
lcfa_assert_same('Check MCP: livecanvas-forge', $opencode_fast_path['visual_help']['items'][1]['title'] ?? '', 'OpenCode visual strip should explain the green MCP state');
lcfa_assert_false(($opencode_fast_path['technical_summary']['expanded'] ?? true), 'OpenCode bundle step should keep technical summary secondary');
```

Add one more assertion for smoke-test state:

```php
$opencode_smoke = $presenter->build([
    'state' => [
        'status'       => 'not_connected',
        'current_step' => 'smoke_test',
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

lcfa_assert_same('Run smoke test', $opencode_smoke['active_panel']['primary_cta']['label'] ?? '', 'smoke test CTA should remain explicit');
lcfa_assert_same('Return here and run the smoke test', $opencode_smoke['visual_help']['items'][2]['title'] ?? '', 'OpenCode smoke step should keep the return-to-WordPress cue');
```

- [ ] **Step 2: Run the presenter test harness and confirm it fails**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
```

Expected: `FAIL` because `visual_help` does not exist yet, OpenCode still says `Download client bundle`, and the technical summary is still expanded during `generate_bundle`.

- [ ] **Step 3: Implement client-aware step copy and visual help metadata in the presenter**

Update `class-lcfa-connection-wizard-presenter.php` with focused additions like these:

```php
private function is_opencode_local(array $bundle): bool {
    return (string) ($bundle['client'] ?? '') === 'opencode'
        && (string) ($bundle['mode'] ?? 'local') === 'local';
}

private function build_visual_help(string $current_step, array $bundle): array {
    if (!$this->is_opencode_local($bundle) || !in_array($current_step, ['generate_bundle', 'smoke_test'], true)) {
        return [];
    }

    return [
        'title' => __('What this looks like in OpenCode', 'livecanvas-forge-ai'),
        'items' => [
            [
                'title' => __('Open this project in OpenCode', 'livecanvas-forge-ai'),
                'caption' => __('Use the same project folder that contains this WordPress install.', 'livecanvas-forge-ai'),
                'tone' => 'project',
            ],
            [
                'title' => __('Check MCP: livecanvas-forge', 'livecanvas-forge-ai'),
                'caption' => __('The MCP indicator should turn green before you continue.', 'livecanvas-forge-ai'),
                'tone' => 'mcp',
            ],
            [
                'title' => __('Return here and run the smoke test', 'livecanvas-forge-ai'),
                'caption' => __('Once OpenCode is connected, verify the connection back in WordPress.', 'livecanvas-forge-ai'),
                'tone' => 'verify',
            ],
        ],
    ];
}
```

Update `build()` and `build_active_panel()` so OpenCode copy is direct:

```php
'primary_cta' => [
    'label'  => $this->is_opencode_local($bundle)
        ? __('Download opencode.json', 'livecanvas-forge-ai')
        : __('Download client bundle', 'livecanvas-forge-ai'),
    'action' => 'download',
],
```

Update `build_technical_summary()` so OpenCode local keeps details collapsed unless the user reaches `ready`:

```php
private function build_technical_summary(string $current_step, array $bundle): array {
    $expanded = in_array($current_step, ['generate_bundle', 'smoke_test'], true);

    if ($this->is_opencode_local($bundle)) {
        $expanded = false;
    }

    return [
        'expanded' => $expanded,
        'bundle'   => $bundle,
    ];
}
```

Make sure `build()` returns:

```php
'visual_help' => $this->build_visual_help($current_step, $bundle),
```

- [ ] **Step 4: Run the presenter test harness again**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
```

Expected: `PASS`

- [ ] **Step 5: Commit presenter work**

```bash
cd /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai
git add includes/class-lcfa-connection-wizard-presenter.php tests/php/connections_wizard_phase1.php
git commit -m "feat: shorten opencode wizard path"
```

### Task 2: Render The Visual Strip Below The Active Step

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php`

- [ ] **Step 1: Write the failing render-level regression**

Extend `connections_wizard_phase1.php` with an output-buffer test around a tiny admin shim:

```php
final class LCFA_Admin_Render_Shim extends LCFA_Admin {
    public function __construct() {}
    public function render_visual_help_for_test(array $wizard_view): string {
        ob_start();
        $method = new ReflectionMethod(LCFA_Admin::class, 'render_connection_visual_help_strip');
        $method->setAccessible(true);
        $method->invoke($this, $wizard_view);
        return (string) ob_get_clean();
    }
}

$admin_shim = new LCFA_Admin_Render_Shim();
$visual_help_markup = $admin_shim->render_visual_help_for_test($opencode_fast_path);

lcfa_assert_true(strpos($visual_help_markup, 'What this looks like in OpenCode') !== false, 'admin should render the visual strip title');
lcfa_assert_true(strpos($visual_help_markup, 'Check MCP: livecanvas-forge') !== false, 'admin should render the OpenCode MCP instruction');
```

If `LCFA_Admin` is too heavy to instantiate in the harness, extract the rendering into a small dedicated method first and test that method through a lightweight shim class in the same file.

- [ ] **Step 2: Run the harness and confirm the render test fails**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
```

Expected: `FAIL` because `render_connection_visual_help_strip()` does not exist yet.

- [ ] **Step 3: Add the visual strip renderer and place it below the active step**

In `class-lcfa-admin.php`, add a new renderer and wire it into `render_connection_wizard()` immediately after the active panel and before the technical summary:

```php
$this->render_connection_active_step_panel(...);
$this->render_connection_visual_help_strip($wizard_view);
$this->render_connection_technical_summary(...);
```

Add the new method:

```php
private function render_connection_visual_help_strip(array $wizard_view): void {
    $visual_help = is_array($wizard_view['visual_help'] ?? null) ? $wizard_view['visual_help'] : [];

    if (empty($visual_help['items'])) {
        return;
    }

    echo '<section class="lcfa-visual-help">';
    echo '<div class="lcfa-guide">';
    echo '<h3>' . esc_html((string) ($visual_help['title'] ?? __('What this looks like', 'livecanvas-forge-ai'))) . '</h3>';
    echo '</div>';
    echo '<div class="lcfa-visual-help__grid">';

    foreach ((array) $visual_help['items'] as $item) {
        echo '<article class="lcfa-visual-help__card tone-' . esc_attr((string) ($item['tone'] ?? 'default')) . '">';
        echo '<div class="lcfa-visual-help__frame" aria-hidden="true"></div>';
        echo '<strong>' . esc_html((string) ($item['title'] ?? '')) . '</strong>';
        echo '<p>' . esc_html((string) ($item['caption'] ?? '')) . '</p>';
        echo '</article>';
    }

    echo '</div>';
    echo '</section>';
}
```

While editing the active step copy, keep it short for OpenCode:

```php
if ($current_step === 'generate_bundle' && (string) ($bundle['client'] ?? '') === 'opencode') {
    $description = __('Download the OpenCode config, then switch to OpenCode once.', 'livecanvas-forge-ai');
}
```

- [ ] **Step 4: Run the harness again**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
```

Expected: `PASS`

- [ ] **Step 5: Commit admin rendering work**

```bash
cd /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai
git add includes/class-lcfa-admin.php tests/php/connections_wizard_phase1.php
git commit -m "feat: add opencode visual wizard help"
```

### Task 3: Style The Fast Path So The Action Is Obvious

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.css`
- Test: manual verification in the browser

- [ ] **Step 1: Add the CSS for the visual strip and lighter summary**

Append styles like these to `assets/admin.css` near the wizard section:

```css
.lcfa-visual-help {
  margin-top: 1.25rem;
  padding-top: 1.25rem;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.lcfa-visual-help__grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 1rem;
}

.lcfa-visual-help__card {
  padding: 1rem;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
}

.lcfa-visual-help__frame {
  height: 110px;
  margin-bottom: 0.85rem;
  border-radius: 14px;
  background:
    radial-gradient(circle at top right, rgba(255, 0, 153, 0.16), transparent 45%),
    linear-gradient(180deg, rgba(255,255,255,0.08), rgba(255,255,255,0.02));
}

.lcfa-wizard__summary.is-collapsed {
  opacity: 0.88;
}
```

Use three tone modifiers so the OpenCode cards do not look identical:

```css
.lcfa-visual-help__card.tone-project .lcfa-visual-help__frame { background-color: rgba(94, 234, 212, 0.08); }
.lcfa-visual-help__card.tone-mcp .lcfa-visual-help__frame { background-color: rgba(96, 165, 250, 0.08); }
.lcfa-visual-help__card.tone-verify .lcfa-visual-help__frame { background-color: rgba(244, 114, 182, 0.08); }
```

- [ ] **Step 2: Run syntax-safe checks**

Run:

```bash
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-connection-wizard-presenter.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php
```

Expected:

```text
No syntax errors detected
PASS
PASS
```

- [ ] **Step 3: Manually verify the OpenCode local flow in the browser**

Open:

```text
http://localhost:8887/wp-admin/admin.php?page=lcfa-dashboard&tab=connections
```

Verify:

```text
1. OpenCode local path shows the shorter sequence.
2. Bundle step uses "Download opencode.json".
3. Visual helper cards appear below the active step, not inside the form.
4. Technical summary stays below the visual strip and looks secondary.
5. Ready state still shows Run checks / Regenerate bundle / Reconfigure.
```

- [ ] **Step 4: Commit CSS and verification**

```bash
cd /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai
git add assets/admin.css includes/class-lcfa-admin.php includes/class-lcfa-connection-wizard-presenter.php tests/php/connections_wizard_phase1.php
git commit -m "style: clarify opencode onboarding flow"
```

### Task 4: Replace Invented Agent Icons With Official Assets

**Files:**
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/agent-icons/codex.png`
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/agent-icons/opencode.png`
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/agent-icons/cursor.png`
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/agent-icons/claude-code.svg`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.css`

- [ ] **Step 1: Export the official app icons into plugin assets**

Use the installed app assets as the source of truth:

```bash
mkdir -p /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/agent-icons
sips -s format png /Applications/Codex.app/Contents/Resources/codexTemplate@2x.png --out /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/agent-icons/codex.png
sips -s format png /Applications/OpenCode.app/Contents/Resources/icon.icns --out /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/agent-icons/opencode.png
sips -s format png /Applications/Cursor.app/Contents/Resources/Cursor.icns --out /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/agent-icons/cursor.png
cp /Users/commander/Downloads/claude-color.svg /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/agent-icons/claude-code.svg
```

The Claude asset should stay as SVG unless a rendering issue forces rasterization later.

- [ ] **Step 2: Add a small helper that resolves agent icon URLs**

In `class-lcfa-admin.php`, add a helper alongside the existing asset URL helpers:

```php
private function get_agent_icon_url(string $client): string {
    $map = [
        'codex'       => 'assets/agent-icons/codex.png',
        'opencode'    => 'assets/agent-icons/opencode.png',
        'cursor'      => 'assets/agent-icons/cursor.png',
        'claude-code' => 'assets/agent-icons/claude-code.svg',
    ];

    if (empty($map[$client])) {
        return '';
    }

    return plugins_url($map[$client], LCFA_DIR . 'livecanvas-forge-ai.php');
}
```

- [ ] **Step 3: Replace the invented client glyphs in the wizard UI where relevant**

Use the new icon URL in the client selector and in the visual helper strip. Keep the old inline SVGs only as a fallback when no official asset exists:

```php
$icon_url = $this->get_agent_icon_url($client_key);

if ($icon_url !== '') {
    echo '<img src="' . esc_url($icon_url) . '" alt="" class="lcfa-agent-icon" loading="lazy">';
} else {
    echo $this->get_icon_svg($fallback_icon_name);
}
```

Do not remove generic utility icons such as `plug`, `stars`, `shield-check`, or `rocket`; only replace the client-logo representations.

- [ ] **Step 4: Style the icon treatment so the assets render cleanly**

Add CSS like this:

```css
.lcfa-agent-icon {
  width: 18px;
  height: 18px;
  object-fit: contain;
  border-radius: 4px;
  flex: 0 0 auto;
}

.lcfa-visual-help__card .lcfa-agent-icon {
  width: 24px;
  height: 24px;
}
```

- [ ] **Step 5: Verify assets and UI**

Run:

```bash
ls -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/agent-icons
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
```

Then manually verify in the browser that the OpenCode/Codex/Cursor/Claude choices show official client assets instead of invented inline glyphs.

- [ ] **Step 6: Commit the icon work**

```bash
cd /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai
git add assets/agent-icons includes/class-lcfa-admin.php assets/admin.css
git commit -m "feat: use official coding agent icons"
```

## Self-Review

### Spec Coverage

- Shorter OpenCode path: covered in Task 1 presenter changes and Task 2 admin copy
- Visual guidance below active step: covered in Task 1 metadata and Task 2 renderer
- Technical details secondary: covered in Task 1 summary behavior and Task 3 styling
- Ready state unchanged: covered in Task 1 regression plus Task 3 manual verification
- Official agent icons: covered in Task 4 asset export and UI wiring

No spec gaps found.

### Placeholder Scan

- No `TODO`, `TBD`, or “implement later” placeholders left in the task steps
- Every code-changing step includes concrete file paths, code, and commands

### Type Consistency

- `visual_help` is the single presenter/admin payload name throughout the plan
- Step names stay aligned with the existing wizard keys: `choose_client`, `choose_mode`, `confirm_details`, `generate_bundle`, `smoke_test`
- `tone` keys for visual cards are defined once and reused consistently
- Client icon filenames stay aligned with client keys: `codex.png`, `opencode.png`, `cursor.png`, `claude-code.svg`
