# Design System Compose Preview First Slice Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a preview-first `design_system_compose` action for `Picostrap` so coding agents can send a simple creative brief and receive a safe, apply-ready Bootstrap design-system payload before any theme write happens.

**Architecture:** Keep `design_system_apply` as the deterministic write layer and add a small composition layer above it. The new layer uses deterministic prompt heuristics plus strict token pruning to produce a human-facing preview and an `apply_payload` that can be handed straight to `design_system_apply` without extra translation.

**Tech Stack:** WordPress PHP, existing `LCFA_Command_Deck`, existing `LCFA_Design_System_Apply`, custom PHP harness tests, MCP `run_lc_command`.

---

## File Structure

- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-design-system-picostrap-composer.php`
  Responsibility: Convert a simple prompt plus optional brand hints into a strict Picostrap preview payload and warnings.
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-design-system-compose.php`
  Responsibility: Resolve the target stack, delegate to the stack composer, and return the preview-first contract consumed by `LCFA_Command_Deck`.
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php`
  Responsibility: First-slice contract tests for compose preview, unsupported concepts, vague prompts, and compose->apply roundtrip.
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-command-deck.php`
  Responsibility: Expose `design_system_compose`, inject the compose service, and merge compose results into the existing command envelope.
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-plugin.php`
  Responsibility: Instantiate the compose service and pass it into `LCFA_Command_Deck`.
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/livecanvas-forge-ai.php`
  Responsibility: Load the new compose classes during plugin bootstrap.
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php`
  Responsibility: Load the new compose classes so the existing harness keeps booting cleanly.

## Task 1: Add Red Contract Tests For `design_system_compose`

**Files:**
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php`

- [ ] **Step 1: Write the failing harness with the first-slice contract**

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/design_system_apply_phase1.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-composer.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-compose.php';

function lcfa_compose_service(): LCFA_Design_System_Compose {
    $environment = new LCFA_Environment();
    $apply = new LCFA_Design_System_Apply(
        $environment,
        new LCFA_Design_System_Picostrap_Executor(),
        new LCFA_Design_System_Picowind_Executor(
            new LCFA_WindPress_Bridge(),
            new LCFA_Theme_Files_Bridge(),
            new LCFA_Design_System_Build_Gateway(new LCFA_Local_MCP_Bridge())
        )
    );

    return new LCFA_Design_System_Compose(
        $environment,
        new LCFA_Design_System_Picostrap_Composer(),
        $apply
    );
}

function test_picostrap_compose_preview(): void {
    $compose = lcfa_compose_service();

    $result = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'Create a bold, vibrant, slightly premium Bootstrap design system with bright pink, electric blue, rounded buttons, and expressive headings.',
    ]);

    lcfa_assert_true(!empty($result['ok']), 'Compose preview should succeed for Picostrap');
    lcfa_assert_same('design_system_compose', $result['action'], 'Compose should expose the action name');
    lcfa_assert_same('preview', $result['mode'], 'Compose must stay preview-only');
    lcfa_assert_same('picostrap', $result['target_stack'], 'Compose should resolve Picostrap');
    lcfa_assert_same('design_system_apply', $result['apply_payload']['action'], 'Compose should emit an apply payload');
    lcfa_assert_true(isset($result['preview']['palette']['primary']), 'Preview should expose a primary color');
    lcfa_assert_true(isset($result['preview']['buttons']['btn_border_radius']), 'Preview should expose button shape');
}

function test_unsupported_concepts_are_warned_and_dropped(): void {
    $compose = lcfa_compose_service();

    $result = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'Create a premium system with an accent color, card shadows, and soft motion.',
    ]);

    lcfa_assert_true(!empty($result['ok']), 'Compose should still succeed with partial support');
    lcfa_assert_true(!empty($result['warnings']), 'Unsupported concepts should generate warnings');
    lcfa_assert_true(!isset($result['apply_payload']['colors']['accent']), 'Unsupported accent token must not leak into apply payload');
}

function test_vague_prompt_fails_cleanly(): void {
    $compose = lcfa_compose_service();

    $result = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'make it nice',
    ]);

    lcfa_assert_true(empty($result['ok']), 'Overly vague prompt should fail');
    lcfa_assert_true(stripos((string) $result['message'], 'more direction') !== false, 'Failure should ask for more direction');
}

function test_compose_roundtrip_into_apply(): void {
    $compose = lcfa_compose_service();

    $preview = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'Create a vibrant premium design system with warm body background, pill buttons, and bold display headings.',
    ]);

    $apply = new LCFA_Design_System_Apply(
        new LCFA_Environment(),
        new LCFA_Design_System_Picostrap_Executor(),
        new LCFA_Design_System_Picowind_Executor(
            new LCFA_WindPress_Bridge(),
            new LCFA_Theme_Files_Bridge(),
            new LCFA_Design_System_Build_Gateway(new LCFA_Local_MCP_Bridge())
        )
    );

    $result = $apply->run($preview['apply_payload'], false);

    lcfa_assert_true(!empty($result['ok']), 'Apply should accept the compose payload without translation');
    lcfa_assert_same('picosass_handoff', $result['data']['build_strategy'], 'Compose roundtrip should still use Picostrap handoff');
}
```

- [ ] **Step 2: Run the new harness and confirm it fails**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php
```

Expected: FAIL because the compose classes do not exist yet and `LCFA_Command_Deck` does not expose `design_system_compose`.

- [ ] **Step 3: Load the future compose classes in the foundation harness**

```php
require_once LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-composer.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-compose.php';
```

Place those alongside the existing design-system `require_once` lines in `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php`.

- [ ] **Step 4: Re-run the foundation harness to capture the same missing-class failure early**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php
```

Expected: FAIL with missing compose class includes. This confirms the bootstrap surface that must be added in later tasks.

- [ ] **Step 5: Commit the red tests**

```bash
git -C /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai add tests/php/design_system_compose_phase1.php tests/php/foundation_contract_phase1.php
git -C /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai commit -m "test: add design system compose contract harness"
```

## Task 2: Implement The Picostrap Composer And Preview Contract

**Files:**
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-design-system-picostrap-composer.php`
- Create: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-design-system-compose.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php`

- [ ] **Step 1: Write the minimal Picostrap composer with strict supported-token pruning**

```php
<?php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Picostrap_Composer {
    private const SUPPORTED_COLORS = ['primary', 'secondary', 'success', 'info', 'warning', 'danger', 'light', 'dark', 'body_bg', 'body_color'];
    private const SUPPORTED_TYPOGRAPHY = ['font_family_base', 'headings_font_family', 'font_size_base', 'line_height_base'];
    private const SUPPORTED_RADIUS = ['border_radius', 'border_radius_sm', 'border_radius_lg'];
    private const SUPPORTED_BUTTONS = ['btn_padding_y', 'btn_padding_x', 'btn_border_radius'];

    public function compose(array $payload): array {
        $prompt = strtolower(trim((string) ($payload['prompt'] ?? '')));

        if ($prompt === '' || in_array($prompt, ['make it nice', 'nice', 'good'], true)) {
            return [
                'ok' => false,
                'message' => __('Add more direction about color, typography, or button shape before composing a design system.', 'livecanvas-forge-ai'),
                'warnings' => [],
            ];
        }

        $warnings = [];
        $tokens = [
            'colors' => $this->compose_colors($prompt, $warnings),
            'typography' => $this->compose_typography($prompt, $warnings),
            'radius' => $this->compose_radius($prompt, $warnings),
            'buttons' => $this->compose_buttons($prompt, $warnings),
        ];

        $tokens = $this->prune_supported($tokens);

        return [
            'ok' => true,
            'summary' => __('Composed a Picostrap design system preview.', 'livecanvas-forge-ai'),
            'message' => __('Design system preview prepared.', 'livecanvas-forge-ai'),
            'preview' => [
                'mood' => $this->compose_mood($prompt, $payload),
                'palette' => $tokens['colors'],
                'typography' => $tokens['typography'],
                'radius' => $tokens['radius'],
                'buttons' => $tokens['buttons'],
            ],
            'apply_payload' => [
                'action' => 'design_system_apply',
                'framework' => 'picostrap',
                'colors' => $tokens['colors'],
                'typography' => $tokens['typography'],
                'radius' => $tokens['radius'],
                'buttons' => $tokens['buttons'],
            ],
            'warnings' => $warnings,
        ];
    }
}
```

- [ ] **Step 2: Fill the deterministic heuristics instead of placeholder composition**

```php
private function compose_colors(string $prompt, array &$warnings): array {
    if (str_contains($prompt, 'vibrant') || str_contains($prompt, 'bright') || str_contains($prompt, 'bold')) {
        return [
            'primary' => '#ff2d55',
            'secondary' => '#6a00ff',
            'success' => '#39ff14',
            'info' => '#00cfff',
            'warning' => '#ffb703',
            'danger' => '#ff3b30',
            'light' => '#fff4d6',
            'dark' => '#111827',
            'body_bg' => '#fff8ef',
            'body_color' => '#1f2937',
        ];
    }

    if (str_contains($prompt, 'minimal') || str_contains($prompt, 'clean')) {
        return [
            'primary' => '#2563eb',
            'secondary' => '#64748b',
            'success' => '#16a34a',
            'info' => '#0891b2',
            'warning' => '#d97706',
            'danger' => '#dc2626',
            'light' => '#f8fafc',
            'dark' => '#0f172a',
            'body_bg' => '#ffffff',
            'body_color' => '#1e293b',
        ];
    }

    $warnings[] = __('No explicit palette direction was found. A safe energetic Bootstrap palette was used.', 'livecanvas-forge-ai');

    return [
        'primary' => '#ff2d55',
        'secondary' => '#6a00ff',
        'success' => '#39ff14',
        'info' => '#00cfff',
        'warning' => '#ffb703',
        'danger' => '#ff3b30',
        'light' => '#fff4d6',
        'dark' => '#111827',
        'body_bg' => '#fff8ef',
        'body_color' => '#1f2937',
    ];
}

private function compose_typography(string $prompt, array &$warnings): array {
    if (str_contains($prompt, 'expressive') || str_contains($prompt, 'display') || str_contains($prompt, 'premium')) {
        return [
            'font_family_base' => '"Poppins", sans-serif',
            'headings_font_family' => '"Bebas Neue", sans-serif',
            'font_size_base' => '1rem',
            'line_height_base' => '1.6',
        ];
    }

    return [
        'font_family_base' => '"Inter", sans-serif',
        'headings_font_family' => '"Inter", sans-serif',
        'font_size_base' => '1rem',
        'line_height_base' => '1.6',
    ];
}

private function compose_radius(string $prompt, array &$warnings): array {
    if (str_contains($prompt, 'round') || str_contains($prompt, 'rounded') || str_contains($prompt, 'pill')) {
        return [
            'border_radius' => '1rem',
            'border_radius_sm' => '0.6rem',
            'border_radius_lg' => '1.4rem',
        ];
    }

    return [
        'border_radius' => '0.5rem',
        'border_radius_sm' => '0.35rem',
        'border_radius_lg' => '0.85rem',
    ];
}

private function compose_buttons(string $prompt, array &$warnings): array {
    if (str_contains($prompt, 'pill')) {
        return [
            'btn_padding_y' => '0.75rem',
            'btn_padding_x' => '1.4rem',
            'btn_border_radius' => '999px',
        ];
    }

    return [
        'btn_padding_y' => '0.75rem',
        'btn_padding_x' => '1.25rem',
        'btn_border_radius' => '1rem',
    ];
}
```

- [ ] **Step 3: Add unsupported-concept warnings and pruning helpers**

```php
private function compose_mood(string $prompt, array $payload): string {
    $parts = [];

    foreach ((array) ($payload['brand_personality'] ?? []) as $value) {
        $value = sanitize_text_field($value);
        if ($value !== '') {
            $parts[] = $value;
        }
    }

    foreach (['vibrant', 'premium', 'energetic', 'minimal', 'clean', 'playful'] as $keyword) {
        if (str_contains($prompt, $keyword) && !in_array($keyword, $parts, true)) {
            $parts[] = $keyword;
        }
    }

    return implode(', ', $parts ?: ['balanced']);
}

private function prune_supported(array $tokens): array {
    return [
        'colors' => array_intersect_key($tokens['colors'], array_flip(self::SUPPORTED_COLORS)),
        'typography' => array_intersect_key($tokens['typography'], array_flip(self::SUPPORTED_TYPOGRAPHY)),
        'radius' => array_intersect_key($tokens['radius'], array_flip(self::SUPPORTED_RADIUS)),
        'buttons' => array_intersect_key($tokens['buttons'], array_flip(self::SUPPORTED_BUTTONS)),
    ];
}

private function warn_unsupported_concepts(string $prompt, array &$warnings): void {
    if (str_contains($prompt, 'accent')) {
        $warnings[] = __('"accent" is not a first-slice Picostrap token and was omitted.', 'livecanvas-forge-ai');
    }

    if (str_contains($prompt, 'shadow') || str_contains($prompt, 'card shadow')) {
        $warnings[] = __('"card shadow" is conceptual only in this slice and was not included in apply_payload.', 'livecanvas-forge-ai');
    }

    if (str_contains($prompt, 'motion') || str_contains($prompt, 'animation')) {
        $warnings[] = __('Motion and animation systems are outside first-slice Picostrap support and were omitted.', 'livecanvas-forge-ai');
    }
}
```

Call `warn_unsupported_concepts()` near the start of `compose()`.

- [ ] **Step 4: Add the orchestration service that resolves stack and wraps the preview contract**

```php
<?php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Compose {
    public function __construct(
        private LCFA_Environment $environment,
        private LCFA_Design_System_Picostrap_Composer $picostrap_composer,
        private LCFA_Design_System_Apply $design_system_apply
    ) {}

    public function run(array $payload): array {
        $framework = sanitize_key((string) ($payload['framework'] ?? $this->environment->detect_framework_family()));

        if ($framework !== 'picostrap') {
            return [
                'ok' => false,
                'action' => 'design_system_compose',
                'mode' => 'preview',
                'execution_target' => 'local',
                'target_stack' => $framework,
                'message' => __('design_system_compose first slice currently supports Picostrap only.', 'livecanvas-forge-ai'),
                'summary' => __('Unsupported design-system compose stack.', 'livecanvas-forge-ai'),
                'warnings' => [],
                'preview' => [],
                'apply_payload' => [],
                'data' => [
                    'supports_apply' => false,
                    'preview_only' => true,
                ],
            ];
        }

        $result = $this->picostrap_composer->compose($payload);

        if (empty($result['ok'])) {
            return array_merge([
                'ok' => false,
                'action' => 'design_system_compose',
                'mode' => 'preview',
                'execution_target' => 'local',
                'target_stack' => 'picostrap',
                'summary' => __('Unable to compose a safe Picostrap design system preview.', 'livecanvas-forge-ai'),
                'preview' => [],
                'apply_payload' => [],
                'data' => [
                    'supports_apply' => false,
                    'preview_only' => true,
                ],
            ], $result);
        }

        return array_merge([
            'ok' => true,
            'action' => 'design_system_compose',
            'mode' => 'preview',
            'execution_target' => 'local',
            'target_stack' => 'picostrap',
            'data' => [
                'supports_apply' => true,
                'preview_only' => true,
            ],
        ], $result);
    }
}
```

- [ ] **Step 5: Run the compose harness until all direct service tests pass**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php
```

Expected: PASS for preview, unsupported-concept warnings, vague-prompt failure, and compose->apply roundtrip.

- [ ] **Step 6: Commit the compose service layer**

```bash
git -C /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai add includes/class-lcfa-design-system-picostrap-composer.php includes/class-lcfa-design-system-compose.php tests/php/design_system_compose_phase1.php
git -C /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai commit -m "feat: add preview-first design system compose service"
```

## Task 3: Wire `design_system_compose` Into The Plugin And Command Deck

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-command-deck.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-plugin.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/livecanvas-forge-ai.php`
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php`

- [ ] **Step 1: Add compose injection and action metadata to `LCFA_Command_Deck`**

```php
private LCFA_Design_System_Compose $design_system_compose;
private LCFA_Design_System_Apply $design_system_apply;

public function __construct(
    LCFA_Environment $environment,
    LCFA_Inventory $inventory,
    LCFA_WindPress_Bridge $windpress_bridge,
    LCFA_Theme_Files_Bridge $theme_files_bridge,
    LCFA_Local_MCP_Bridge $local_mcp_bridge,
    LCFA_Remote_Client $remote_client,
    ?LCFA_Design_System_Apply $design_system_apply = null,
    ?LCFA_Design_System_Compose $design_system_compose = null
) {
    // existing assignments...
    $this->design_system_apply = $design_system_apply ?: /* existing apply bootstrap */;
    $this->design_system_compose = $design_system_compose ?: new LCFA_Design_System_Compose(
        $environment,
        new LCFA_Design_System_Picostrap_Composer(),
        $this->design_system_apply
    );
}
```

Add to `get_actions()`:

```php
'design_system_compose' => [
    'label'       => __('Compose design system preview', 'livecanvas-forge-ai'),
    'description' => __('Turns a simple creative brief into a previewable, apply-ready Picostrap design system without writing.', 'livecanvas-forge-ai'),
],
```

- [ ] **Step 2: Route the new action in `execute()` and keep it preview-first**

```php
case 'design_system_compose':
    $result = array_merge($result, $this->design_system_compose->run($payload));
    break;
```

And add it to the non-LiveCanvas-gated list:

```php
return in_array($action, [
    'site_audit',
    'design_system_compose',
    'design_system_apply',
    'windpress_audit',
    // existing items...
], true);
```

- [ ] **Step 3: Instantiate compose in the plugin bootstrap**

```php
$design_system_apply = new LCFA_Design_System_Apply(
    $this->environment,
    new LCFA_Design_System_Picostrap_Executor(),
    new LCFA_Design_System_Picowind_Executor(
        $this->windpress_bridge,
        $this->theme_files_bridge,
        new LCFA_Design_System_Build_Gateway($this->local_mcp_bridge)
    )
);

$design_system_compose = new LCFA_Design_System_Compose(
    $this->environment,
    new LCFA_Design_System_Picostrap_Composer(),
    $design_system_apply
);

$this->command_deck = new LCFA_Command_Deck(
    $this->environment,
    $this->inventory,
    $this->windpress_bridge,
    $this->theme_files_bridge,
    $this->local_mcp_bridge,
    $this->remote_client,
    $design_system_apply,
    $design_system_compose
);
```

- [ ] **Step 4: Load the new classes during plugin bootstrap**

```php
require_once LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-composer.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-compose.php';
```

Add both includes near the existing `design_system_apply` bootstrap lines in `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/livecanvas-forge-ai.php`.

- [ ] **Step 5: Extend the compose harness with `Command Deck` coverage**

```php
function test_command_deck_exposes_and_executes_design_system_compose(): void {
    $environment = new LCFA_Environment();
    $apply = new LCFA_Design_System_Apply(
        $environment,
        new LCFA_Design_System_Picostrap_Executor(),
        new LCFA_Design_System_Picowind_Executor(
            new LCFA_WindPress_Bridge(),
            new LCFA_Theme_Files_Bridge(),
            new LCFA_Design_System_Build_Gateway(new LCFA_Local_MCP_Bridge())
        )
    );
    $compose = new LCFA_Design_System_Compose(
        $environment,
        new LCFA_Design_System_Picostrap_Composer(),
        $apply
    );

    $deck = new LCFA_Command_Deck(
        $environment,
        new LCFA_Inventory(),
        new LCFA_WindPress_Bridge(),
        new LCFA_Theme_Files_Bridge(),
        new LCFA_Local_MCP_Bridge(),
        new LCFA_Remote_Client(),
        $apply,
        $compose
    );

    lcfa_assert_true(isset($deck->get_actions()['design_system_compose']), 'Command deck should expose design_system_compose');

    $result = $deck->execute([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'Create a vibrant premium design system with rounded buttons and expressive headings.',
    ]);

    lcfa_assert_true(!empty($result['ok']), 'Command deck should execute design_system_compose');
    lcfa_assert_same('preview', $result['mode'], 'Command deck should preserve preview mode for compose');
    lcfa_assert_same('design_system_apply', $result['apply_payload']['action'], 'Command deck should return an apply-ready payload');
}
```

- [ ] **Step 6: Run the compose harness again and verify command-deck coverage passes**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php
```

Expected: PASS, including the `LCFA_Command_Deck` execution path.

- [ ] **Step 7: Commit the command-deck wiring**

```bash
git -C /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai add includes/class-lcfa-command-deck.php includes/class-lcfa-plugin.php livecanvas-forge-ai.php tests/php/design_system_compose_phase1.php tests/php/foundation_contract_phase1.php
git -C /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai commit -m "feat: wire design system compose into command deck"
```

## Task 4: Final Verification And Usage Regression

**Files:**
- Modify: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php`
- Test: `/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php`

- [ ] **Step 1: Add one unsupported-stack regression and one exact payload-shape assertion**

```php
function test_unsupported_stack_fails_cleanly(): void {
    $compose = lcfa_compose_service();

    $result = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picowind',
        'prompt' => 'Create a vibrant design system.',
    ]);

    lcfa_assert_true(empty($result['ok']), 'Picowind should be rejected in the first slice');
    lcfa_assert_same([], $result['apply_payload'], 'Unsupported stacks must not return an apply payload');
}

function test_apply_payload_contains_only_supported_picostrap_buckets(): void {
    $compose = lcfa_compose_service();
    $result = $compose->run([
        'action' => 'design_system_compose',
        'framework' => 'picostrap',
        'prompt' => 'Create a vibrant premium design system with rounded buttons and display headings.',
    ]);

    lcfa_assert_same(
        ['action', 'framework', 'colors', 'typography', 'radius', 'buttons'],
        array_keys($result['apply_payload']),
        'Compose should expose a stable apply payload shape'
    );
}
```

- [ ] **Step 2: Run the full verification matrix**

Run:

```bash
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_compose_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/design_system_apply_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/foundation_contract_phase1.php
php /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/tests/php/connections_wizard_phase1.php
node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/tool-registry.test.js
node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/stdio-startup.test.js
node /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/tests/workspace-root-sync.test.js
```

Expected: all suites PASS. The new compose layer must not regress the existing OpenCode, wizard, or design-system-apply work.

- [ ] **Step 3: Sanity-check the real OpenCode prompt path manually**

Use this exact prompt in OpenCode:

```text
Call the MCP tool livecanvas-forge_run_lc_command with this JSON:

{
  "action": "design_system_compose",
  "framework": "picostrap",
  "prompt": "Create a bold, vibrant, slightly premium Bootstrap design system with bright colors, rounded buttons, and expressive headings."
}

Return only the raw JSON result.
```

Expected:
- `ok: true`
- `action: design_system_compose`
- `mode: preview`
- `preview.palette.primary` present
- `apply_payload.action: design_system_apply`

- [ ] **Step 4: Commit the verified first slice**

```bash
git -C /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai add includes/class-lcfa-design-system-picostrap-composer.php includes/class-lcfa-design-system-compose.php includes/class-lcfa-command-deck.php includes/class-lcfa-plugin.php livecanvas-forge-ai.php tests/php/design_system_compose_phase1.php tests/php/foundation_contract_phase1.php
git -C /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai commit -m "feat: add preview-first design system compose flow"
```

## Self-Review

- Spec coverage:
  - preview-first `design_system_compose`: Task 2 and Task 3
  - Picostrap-only first slice: Task 2 and Task 4
  - unsupported concept warnings: Task 2
  - unsupported stack error: Task 4
  - compose->apply roundtrip: Task 1 and Task 4
- Placeholder scan:
  - No `TODO`, `TBD`, or “implement later” placeholders remain in task steps.
- Type consistency:
  - `design_system_compose`, `apply_payload`, `preview`, and the supported Picostrap token buckets use the same names as the spec and existing `design_system_apply` contract.
