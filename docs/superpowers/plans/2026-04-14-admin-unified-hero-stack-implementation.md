# Admin Unified Hero Stack Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the standalone `Stack snapshot` card with a single compact shared hero across the main dashboard tabs, using logos and chips instead of repeated text.

**Architecture:** Keep the current dashboard entrypoint in `LCFA_Admin`, but move top-of-page state shaping into a dedicated hero presenter/helper so the render path stops duplicating stack facts in multiple places. The admin CSS should be updated in one pass to convert the hero into a two-column responsive header with inline details and to remove the visual need for a separate snapshot card.

**Tech Stack:** WordPress admin PHP, existing `LCFA_Admin` renderer, plugin partner logo helpers, plugin admin CSS, PHP regression harnesses.

---

## File Structure

**Modify:**
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
  Purpose: replace the current top-area render flow, remove the standalone snapshot card from dashboard composition, and render the shared hero using compact stack marks/chips plus inline details.
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.css`
  Purpose: restyle the hero as the primary compact top block, add logo/chip/detail panel rules, and remove dependence on the old snapshot card layout.
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php`
  Purpose: extend the existing admin/dashboard regression harness to cover hero content, compact stack rendering intent, and the removal of separate snapshot-card dependency.

**Create:**
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin-hero-presenter.php`
  Purpose: build one normalized payload for the shared hero: title, subtitle, stack marks, chips, and details rows.
- `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_hero_phase1.php`
  Purpose: focused renderer contract test for the new presenter output and for the unified hero composition without needing to run the full dashboard flow.

---

### Task 1: Add a shared admin hero presenter

**Files:**
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin-hero-presenter.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_hero_phase1.php`

- [ ] **Step 1: Write the failing presenter test**

```php
<?php

declare(strict_types=1);

require __DIR__ . '/foundation_contract_phase1.php';
require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';

$presenter = new LCFA_Admin_Hero_Presenter();

$hero = $presenter->build('connections', [
    'current_theme_name' => 'Picowind Child',
    'current_theme_stylesheet' => 'picowind-child',
    'current_theme_template' => 'picowind',
    'detected_framework' => 'picowind',
    'framework_slug' => 'daisyui-5',
    'livecanvas_active' => true,
    'windpress_active' => true,
    'acf_active' => false,
    'tangible_available' => true,
], [
    'site_mode' => 'local',
    'preferred_client' => 'codex',
]);

lcfa_assert_same('Connections', $hero['title'] ?? '', 'hero presenter should keep the approved tab title');
lcfa_assert_same('connections', $hero['tab'] ?? '', 'hero presenter should keep the current tab key');
lcfa_assert_true(count($hero['marks'] ?? []) >= 2, 'hero presenter should expose compact stack marks');
lcfa_assert_true(count($hero['chips'] ?? []) >= 3, 'hero presenter should expose compact stack chips');
lcfa_assert_same('daisyui-5', $hero['chips'][3]['value'] ?? '', 'hero presenter should surface editor config as a chip');
lcfa_assert_true(count($hero['details'] ?? []) >= 3, 'hero presenter should move technical facts into details');

echo "PASS\n";
```

- [ ] **Step 2: Run the new test and verify it fails**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_hero_phase1.php
```

Expected: FAIL because `LCFA_Admin_Hero_Presenter` does not exist yet.

- [ ] **Step 3: Write the minimal presenter implementation**

```php
<?php

defined('ABSPATH') || exit;

final class LCFA_Admin_Hero_Presenter {
    public function build(string $tab, array $snapshot, array $settings): array {
        $content = $this->get_tab_content($tab);

        return [
            'tab' => $tab,
            'title' => $content['title'],
            'subtitle' => $content['subtitle'],
            'marks' => $this->build_marks($snapshot),
            'chips' => $this->build_chips($snapshot, $settings),
            'details' => $this->build_details($snapshot, $settings),
        ];
    }

    private function get_tab_content(string $tab): array {
        $map = [
            'setup' => ['title' => 'Forge Setup', 'subtitle' => 'Prepare the site and connection flow.'],
            'connections' => ['title' => 'Connections', 'subtitle' => 'Connect and verify your coding agent.'],
            'genesis' => ['title' => 'Project Brief & Build Plan', 'subtitle' => 'Shape the project after the connection is ready.'],
            'command' => ['title' => 'Command Deck', 'subtitle' => 'Run concrete site operations through Forge AI.'],
        ];

        return $map[$tab] ?? $map['connections'];
    }
}
```

- [ ] **Step 4: Run the presenter test and make it pass**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_hero_phase1.php
```

Expected: `PASS`

- [ ] **Step 5: Commit the presenter slice**

```bash
git add /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin-hero-presenter.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_hero_phase1.php
git commit -m "feat: add shared admin hero presenter"
```

### Task 2: Wire the dashboard to the unified hero and remove the separate snapshot card

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php`

- [ ] **Step 1: Write the failing dashboard regression**

Add assertions like:

```php
$hero_presenter_method = new ReflectionMethod('LCFA_Admin', 'get_dashboard_hero_content');
$hero_presenter_method->setAccessible(true);

$hero = $hero_presenter_method->invoke($admin_instance, 'connections');
lcfa_assert_same('Connections', $hero['title'] ?? '', 'connections hero should keep the tab title');

$render_method = new ReflectionMethod('LCFA_Admin', 'render_page_header');
$render_method->setAccessible(true);
ob_start();
$render_method->invoke($admin_instance, 'Connections', 'Connect and verify your coding agent.', $snapshot, $settings);
$output = ob_get_clean();

lcfa_assert_contains('lcfa-hero-stack', $output, 'page header should render compact stack marks inside the hero');
lcfa_assert_contains('lcfa-hero-details', $output, 'page header should render inline details instead of a separate snapshot card');
lcfa_assert_true(strpos($output, 'Stack snapshot') === false, 'page header should stop rendering stack snapshot copy');
```

- [ ] **Step 2: Run the targeted harness and verify it fails**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
```

Expected: FAIL because the current header still uses badges plus the old stack card exists elsewhere.

- [ ] **Step 3: Wire the presenter into `LCFA_Admin`**

Use a focused integration shape like:

```php
private LCFA_Admin_Hero_Presenter $admin_hero_presenter;

public function __construct(...) {
    // existing assignments
    $this->admin_hero_presenter = new LCFA_Admin_Hero_Presenter();
}

private function render_page_header(string $title, string $subtitle, array $snapshot, array $settings, string $tab = 'connections'): void {
    $hero = $this->admin_hero_presenter->build($tab, $snapshot, $settings);

    echo '<section class="lcfa-hero">';
    echo '<div class="lcfa-hero-main">';
    echo '<div class="lcfa-hero-copy">...';
    echo '<div class="lcfa-hero-stack">...';
    echo '<div class="lcfa-hero-chips">...';
    echo '<button type="button" class="lcfa-hero-details-toggle">Details</button>';
    echo '<div class="lcfa-hero-details">...';
    echo '</section>';
}
```

Also remove the call site that renders the separate snapshot card from the main dashboard path.

- [ ] **Step 4: Run the harness and verify it passes**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
```

Expected: `PASS`

- [ ] **Step 5: Commit the dashboard wiring slice**

```bash
git add /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
git commit -m "feat: unify admin hero and stack context"
```

### Task 3: Restyle the hero for compact logos, chips, and inline details

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.css`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_hero_phase1.php`

- [ ] **Step 1: Add a failing markup-level assertion for details and stack rows**

Extend the renderer test to require the new structural classes:

```php
ob_start();
$render_method->invoke($admin_instance, 'Connections', 'Connect and verify your coding agent.', $snapshot, $settings, 'connections');
$output = ob_get_clean();

lcfa_assert_contains('lcfa-hero-main', $output, 'hero should expose a dedicated main grid');
lcfa_assert_contains('lcfa-hero-stack', $output, 'hero should expose a compact logo row');
lcfa_assert_contains('lcfa-hero-chip', $output, 'hero should render compact chips');
```

- [ ] **Step 2: Run the PHP regression and verify it fails**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_hero_phase1.php
```

Expected: FAIL until the new classes and CSS-targeted markup exist.

- [ ] **Step 3: Update `admin.css` with the compact hero layout**

Add rules in this shape:

```css
.lcfa-admin .lcfa-hero-main {
    display: grid;
    grid-template-columns: minmax(0, 1.4fr) minmax(320px, 1fr);
    gap: 18px;
    align-items: start;
}

.lcfa-admin .lcfa-hero-stack {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    justify-content: flex-end;
}

.lcfa-admin .lcfa-hero-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    border-radius: 999px;
}

.lcfa-admin .lcfa-hero-details {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
}
```

Also remove or de-emphasize `.lcfa-snapshot-card` rules now that the separate block is gone.

- [ ] **Step 4: Run syntax + targeted regression**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_hero_phase1.php
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php
```

Expected: both pass.

- [ ] **Step 5: Commit the styling slice**

```bash
git add /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.css /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_hero_phase1.php
git commit -m "style: compact forge admin hero"
```

### Task 4: Run final regression and manual verification

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.css`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin-hero-presenter.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_hero_phase1.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php`

- [ ] **Step 1: Run the full PHP regression set touched by the redesign**

Run:
```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_hero_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_performance_phase1.php
```

Expected: all `PASS`

- [ ] **Step 2: Verify the modified PHP files have no syntax errors**

Run:
```bash
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin-hero-presenter.php
```

Expected: `No syntax errors detected`

- [ ] **Step 3: Manual browser verification**

Open the dashboard and check:
- top area shows a single unified hero
- no standalone `Stack snapshot` card remains
- logos appear before repeated framework words where assets exist
- chips show `local/remote`, theme, client, editor config
- `Details` is collapsed by default and expands inline
- mobile width still wraps cleanly

- [ ] **Step 4: Commit the final verification state**

```bash
git add /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-admin-hero-presenter.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/assets/admin.css /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/admin_hero_phase1.php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
git commit -m "refactor: unify admin hero and stack summary"
```

---

## Self-Review
- Spec coverage: the plan covers unified hero rendering, stack integration, logo/chip prioritization, inline details, removal of the separate snapshot card, and responsive styling.
- Placeholder scan: no `TBD`/`TODO` placeholders remain; every task has file paths, commands, and concrete code shapes.
- Type consistency: the plan consistently uses `LCFA_Admin_Hero_Presenter`, `marks`, `chips`, `details`, `lcfa-hero-stack`, and `lcfa-hero-details` across implementation and tests.
