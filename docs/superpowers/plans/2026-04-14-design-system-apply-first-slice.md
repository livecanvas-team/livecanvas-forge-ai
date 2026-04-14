# Design System Apply First Slice Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a first production-grade `design_system_apply` action that lets external coding agents apply deterministic design-system tokens to `Picostrap` and `Picowind/WindPress` using each stack's native source of truth.

**Architecture:** Introduce a small dedicated design-system service with two stack executors instead of burying more logic inside the `LCFA_Command_Deck` switch. Keep `run_lc_command` as the external entrypoint, but delegate `design_system_apply` to focused executors: `Picostrap` writes `theme_mods` plus explicit `PicoSass` handoff metadata, while `Picowind` writes `theme.json`, updates the DaisyUI preset asset and active `data_theme`, then attempts a capability-aware WindPress build through a thin local-build gateway.

**Tech Stack:** WordPress PHP plugin code, existing `LCFA_Command_Deck`, `LCFA_WindPress_Bridge`, `LCFA_Theme_Files_Bridge`, `LCFA_Local_MCP_Bridge`, Picostrap Customizer/theme mods, Picowind DaisyUI preset files, lightweight PHP regression harnesses

---

### Task 1: Add Red Contract Tests For `design_system_apply`

**Files:**
- Create: `tests/php/design_system_apply_phase1.php`
- Modify: `tests/php/foundation_contract_phase1.php`

- [ ] **Step 1: Create a dedicated PHP regression harness for design-system scenarios**

```php
<?php
// tests/php/design_system_apply_phase1.php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-design-system-tests/');
define('LCFA_VERSION', '0.1.0-test');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_TEST_TMP', sys_get_temp_dir() . '/lcfa-design-system-tests');

@mkdir(LCFA_TEST_TMP, 0777, true);

$GLOBALS['lcfa_test_theme_mods'] = [];
$GLOBALS['lcfa_test_wp_cache'] = [];

function __(string $text, string $domain = ''): string { return $text; }
function sanitize_key(string $value): string { $value = strtolower($value); return (string) preg_replace('/[^a-z0-9_\\-]/', '', $value); }
function sanitize_text_field($value): string { return trim((string) $value); }
function sanitize_textarea_field($value): string { return trim((string) $value); }
function sanitize_file_name(string $value): string { return (string) preg_replace('/[^A-Za-z0-9\\.\\-_]/', '-', $value); }
function wp_json_encode($value, int $flags = 0): string { return (string) json_encode($value, $flags); }
function wp_unslash($value) { return $value; }
function absint($value): int { return abs((int) $value); }
function trailingslashit(string $value): string { return rtrim($value, '/\\\\') . '/'; }
function untrailingslashit(string $value): string { return rtrim($value, '/\\\\'); }
function wp_normalize_path(string $value): string { return str_replace('\\\\', '/', $value); }
function current_time(string $type = 'mysql', bool $gmt = false): string { return gmdate('Y-m-d H:i:s'); }
function apply_filters(string $hook, $value) { return $value; }

function get_theme_mod(string $name, $default = false) {
    return $GLOBALS['lcfa_test_theme_mods'][$name] ?? $default;
}

function set_theme_mod(string $name, $value): bool {
    $GLOBALS['lcfa_test_theme_mods'][$name] = $value;
    return true;
}

function home_url(string $path = ''): string {
    return 'http://localhost:8887' . $path;
}

function admin_url(string $path = ''): string {
    return 'http://localhost:8887/wp-admin/' . ltrim($path, '/');
}

function wp_cache_set(string $key, $value, string $group = ''): bool {
    $GLOBALS['lcfa_test_wp_cache'][$group . ':' . $key] = $value;
    return true;
}

function wp_cache_get(string $key, string $group = '', bool $force = false) {
    return $GLOBALS['lcfa_test_wp_cache'][$group . ':' . $key] ?? false;
}

function wp_cache_flush(): bool {
    $GLOBALS['lcfa_test_wp_cache'] = [];
    return true;
}
```

- [ ] **Step 2: Add fake theme/WindPress runtime and assertion helpers**

```php
final class WP_Theme {
    public function __construct(private array $data = []) {}
    public function get(string $field): string { return (string) ($this->data[$field] ?? ''); }
    public function get_stylesheet(): string { return (string) ($this->data['stylesheet'] ?? ''); }
    public function get_template(): string { return (string) ($this->data['template'] ?? ''); }
    public function parent() { return null; }
}

function wp_get_theme(): WP_Theme {
    return new WP_Theme([
        'Name' => $GLOBALS['lcfa_test_theme_name'] ?? 'Picostrap Child',
        'stylesheet' => $GLOBALS['lcfa_test_stylesheet'] ?? 'picostrap-child',
        'template' => $GLOBALS['lcfa_test_template'] ?? 'picostrap5',
    ]);
}

function get_stylesheet_directory(): string {
    return LCFA_TEST_TMP . '/themes/' . ($GLOBALS['lcfa_test_stylesheet'] ?? 'picostrap-child');
}

function get_template_directory(): string {
    return LCFA_TEST_TMP . '/themes/' . ($GLOBALS['lcfa_test_template'] ?? 'picostrap5');
}

function get_theme_root(string $stylesheet = ''): string {
    return LCFA_TEST_TMP . '/themes';
}

eval(<<<'PHP'
namespace WindPress\WindPress\Core {
    final class Volume {
        public static array $entries = [];
        public static function get_entries(): array { return self::$entries; }
        public static function save_entries(array $entries): void { self::$entries = $entries; }
        public static function get_available_handlers(): array { return [['value' => 'internal', 'label' => 'Internal', 'description' => 'Internal']]; }
        public static function data_dir_path(): string { return \LCFA_TEST_TMP . '/windpress-data'; }
        public static function data_dir_url(): string { return 'http://localhost:8887/wp-content/uploads/windpress/data'; }
    }

    final class Cache {
        public static array $providers = [];
        public static string $themeJson = '';
        public static string $css = '';
        public const CSS_CACHE_FILE = 'cache.css';
        public const THEME_JSON_FILE = 'theme.json';
        public static function get_providers(): array { return self::$providers; }
        public static function save_theme_json(string $blob): void { self::$themeJson = $blob; }
        public static function save_cache(string $css): void { self::$css = $css; }
        public static function get_cache_path(string $file): string { return \LCFA_TEST_TMP . '/windpress-cache/' . $file; }
        public static function get_cache_url(string $file): string { return 'http://localhost:8887/wp-content/uploads/windpress/cache/' . $file; }
    }
}
PHP);

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

- [ ] **Step 3: Add helper factories plus three red scenarios: Picostrap, Picowind, and command-deck exposure**

```php
require LCFA_DIR . 'includes/class-lcfa-inventory.php';
require LCFA_DIR . 'includes/class-lcfa-settings.php';
require LCFA_DIR . 'includes/class-lcfa-environment.php';
require LCFA_DIR . 'includes/class-lcfa-windpress-bridge.php';
require LCFA_DIR . 'includes/class-lcfa-theme-files-bridge.php';
require LCFA_DIR . 'includes/class-lcfa-local-mcp-bridge.php';
require LCFA_DIR . 'includes/class-lcfa-remote-client.php';
require LCFA_DIR . 'includes/class-lcfa-command-deck.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-build-gateway.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-picowind-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-apply.php';

$scenario = $argv[1] ?? 'all';

@mkdir(get_stylesheet_directory() . '/public/styles/presets', 0777, true);
@mkdir(get_template_directory(), 0777, true);
file_put_contents(get_stylesheet_directory() . '/public/styles/presets/daisyui.css', "/** preset */\n@plugin \"daisyui\" {\n    themes: light --default, dark;\n}\n");

final class Test_Design_System_Build_Gateway extends LCFA_Design_System_Build_Gateway {
    public array $last_build_arguments = [];

    public function __construct(private array $status, private array $build_result) {}

    public function get_status(): array {
        return $this->status;
    }

    public function build_windpress_cache(array $arguments = []): array {
        $this->last_build_arguments = $arguments;
        return $this->build_result;
    }
}

function lcfa_make_design_system_service_for_picostrap(): LCFA_Design_System_Apply {
    $environment = new LCFA_Environment();
    $windpress = new LCFA_WindPress_Bridge($environment);
    $theme_files = new LCFA_Theme_Files_Bridge($environment);
    $build_gateway = new Test_Design_System_Build_Gateway(['build_available' => false, 'message' => 'disabled'], ['ok' => false, 'message' => 'disabled']);

    return new LCFA_Design_System_Apply(
        $environment,
        new LCFA_Design_System_Picostrap_Executor(),
        new LCFA_Design_System_Picowind_Executor($windpress, $theme_files, $build_gateway)
    );
}

function lcfa_make_design_system_service_for_picowind(): LCFA_Design_System_Apply {
    $GLOBALS['lcfa_test_theme_name'] = 'Picowind Child';
    $GLOBALS['lcfa_test_stylesheet'] = 'picowind-child';
    $GLOBALS['lcfa_test_template'] = 'picowind';

    $environment = new LCFA_Environment();
    $windpress = new LCFA_WindPress_Bridge($environment);
    $theme_files = new LCFA_Theme_Files_Bridge($environment);
    $build_gateway = new Test_Design_System_Build_Gateway(
        ['build_available' => true, 'message' => 'ready'],
        ['ok' => true, 'result' => ['stored' => true]]
    );

    return new LCFA_Design_System_Apply(
        $environment,
        new LCFA_Design_System_Picostrap_Executor(),
        new LCFA_Design_System_Picowind_Executor($windpress, $theme_files, $build_gateway)
    );
}

if ($scenario === 'picostrap' || $scenario === 'all') {
    $service = lcfa_make_design_system_service_for_picostrap();
    $preview = $service->run([
        'action' => 'design_system_apply',
        'framework' => 'picostrap',
        'colors' => ['primary' => '#112233', 'body_bg' => '#ffffff'],
        'typography' => ['font_family_base' => '"Inter", sans-serif'],
        'radius' => ['border_radius' => '0.5rem'],
        'buttons' => ['btn_border_radius' => '0.5rem'],
    ], true);

    lcfa_assert_same('design_system_apply', $preview['action'], 'Picostrap preview should expose the action name');
    lcfa_assert_same('theme_mods', $preview['source_of_truth'], 'Picostrap preview should resolve theme_mods');
    lcfa_assert_true(in_array('SCSSvar_primary', $preview['changed_keys'], true), 'Picostrap preview should report SCSSvar_primary');
    lcfa_assert_same([], $GLOBALS['lcfa_test_theme_mods'], 'Picostrap preview must not write theme mods');
}

if ($scenario === 'picowind' || $scenario === 'all') {
    $service = lcfa_make_design_system_service_for_picowind();
    $apply = $service->run([
        'action' => 'design_system_apply',
        'framework' => 'picowind',
        'preset' => ['skin' => 'corporate', 'active_theme' => 'corporate'],
        'colors' => ['primary' => '#123456', 'body_bg' => '#ffffff', 'body_color' => '#111111'],
        'typography' => ['font_family_base' => 'Inter', 'font_size_base' => '1rem', 'line_height_base' => '1.5'],
        'radius' => ['border_radius' => '0.75rem'],
    ], false);

    lcfa_assert_same('windpress_cache_runtime', $apply['source_of_truth'], 'Picowind apply should resolve WindPress runtime');
    lcfa_assert_same('corporate', get_theme_mod('data_theme'), 'Picowind apply should update data_theme');
    lcfa_assert_true($apply['build_executed'] === true, 'Picowind apply should execute a build when the gateway reports build availability');
    lcfa_assert_true(\WindPress\WindPress\Core\Cache::$themeJson !== '', 'Picowind apply should store theme.json');
}

if ($scenario === 'command' || $scenario === 'all') {
    $environment = new LCFA_Environment();
    $inventory = new LCFA_Inventory($environment);
    $windpress = new LCFA_WindPress_Bridge($environment);
    $themeFiles = new LCFA_Theme_Files_Bridge($environment);
    $localBridge = new LCFA_Local_MCP_Bridge($environment);
    $remote = new LCFA_Remote_Client();
    $designSystem = lcfa_make_design_system_service_for_picostrap();
    $commandDeck = new LCFA_Command_Deck($environment, $inventory, $windpress, $themeFiles, $localBridge, $remote, $designSystem);

    lcfa_assert_true(isset($commandDeck->get_actions()['design_system_apply']), 'Command deck should expose the design_system_apply action');
}
```

- [ ] **Step 4: Run each scenario to verify the harness fails**

Run: `php tests/php/design_system_apply_phase1.php picostrap`

Expected: FAIL with `class-lcfa-design-system-build-gateway.php` or equivalent missing.

Run: `php tests/php/design_system_apply_phase1.php picowind`

Expected: FAIL for the same reason.

Run: `php tests/php/design_system_apply_phase1.php command`

Expected: FAIL because `design_system_apply` is not yet wired into `LCFA_Command_Deck`.

- [ ] **Step 5: Load the new design-system classes in the existing foundation harness**

```php
require LCFA_DIR . 'includes/class-lcfa-design-system-build-gateway.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-picowind-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-apply.php';
```

- [ ] **Step 6: Commit the red harness**

```bash
git add tests/php/design_system_apply_phase1.php tests/php/foundation_contract_phase1.php
git commit -m "test: add design system apply phase 1 regressions"
```

### Task 2: Implement The Picostrap Executor And Shared Coordinator

**Files:**
- Create: `includes/class-lcfa-design-system-build-gateway.php`
- Create: `includes/class-lcfa-design-system-picostrap-executor.php`
- Create: `includes/class-lcfa-design-system-picowind-executor.php`
- Create: `includes/class-lcfa-design-system-apply.php`
- Modify: `livecanvas-forge-ai.php`
- Modify: `tests/php/design_system_apply_phase1.php`

- [ ] **Step 1: Add a small build gateway wrapper around the final local MCP bridge**

```php
<?php
// includes/class-lcfa-design-system-build-gateway.php

defined('ABSPATH') || exit;

class LCFA_Design_System_Build_Gateway {
    public function __construct(private LCFA_Local_MCP_Bridge $local_mcp_bridge) {}

    public function get_status(): array {
        return $this->local_mcp_bridge->get_status();
    }

    public function build_windpress_cache(array $arguments = []): array {
        return $this->local_mcp_bridge->build_windpress_cache($arguments);
    }
}
```

- [ ] **Step 2: Implement the Picostrap token map and deterministic result envelope**

```php
<?php
// includes/class-lcfa-design-system-picostrap-executor.php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Picostrap_Executor {
    private const TOKEN_MAP = [
        'colors.primary' => 'SCSSvar_primary',
        'colors.secondary' => 'SCSSvar_secondary',
        'colors.success' => 'SCSSvar_success',
        'colors.info' => 'SCSSvar_info',
        'colors.warning' => 'SCSSvar_warning',
        'colors.danger' => 'SCSSvar_danger',
        'colors.light' => 'SCSSvar_light',
        'colors.dark' => 'SCSSvar_dark',
        'colors.body_bg' => 'SCSSvar_body-bg',
        'colors.body_color' => 'SCSSvar_body-color',
        'typography.font_family_base' => 'SCSSvar_font-family-base',
        'typography.headings_font_family' => 'SCSSvar_headings-font-family',
        'typography.font_size_base' => 'SCSSvar_font-size-base',
        'typography.line_height_base' => 'SCSSvar_line-height-base',
        'radius.border_radius' => 'SCSSvar_border-radius',
        'radius.border_radius_sm' => 'SCSSvar_border-radius-sm',
        'radius.border_radius_lg' => 'SCSSvar_border-radius-lg',
        'buttons.btn_padding_y' => 'SCSSvar_btn-padding-y',
        'buttons.btn_padding_x' => 'SCSSvar_btn-padding-x',
        'buttons.btn_border_radius' => 'SCSSvar_btn-border-radius',
    ];

    public function execute(array $payload, bool $dry_run): array {
        $writes = $this->collect_theme_mod_writes($payload);
        $changed = [];

        foreach ($writes as $key => $value) {
            if (get_theme_mod($key, null) !== $value) {
                $changed[] = $key;
            }
        }

        if (!$dry_run) {
            foreach ($writes as $key => $value) {
                set_theme_mod($key, $value);
            }
        }

        return [
            'ok' => true,
            'action' => 'design_system_apply',
            'mode' => $dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'message' => $dry_run
                ? __('Picostrap design system preview prepared.', 'livecanvas-forge-ai')
                : __('Picostrap design system applied.', 'livecanvas-forge-ai'),
            'target_stack' => 'picostrap',
            'source_of_truth' => 'theme_mods',
            'summary' => $dry_run
                ? __('Preview Picostrap design system changes.', 'livecanvas-forge-ai')
                : __('Applied design system tokens to Picostrap theme mods.', 'livecanvas-forge-ai'),
            'changed_keys' => $changed,
            'build_required' => true,
            'build_executed' => false,
            'build_strategy' => 'picosass_handoff',
            'compile_url' => home_url('/?compile_sass=1&sass_nocache=1'),
            'warnings' => $this->build_font_warnings($payload),
            'data' => [
                'changed_theme_mods' => array_intersect_key($writes, array_flip($changed)),
            ],
        ];
    }

    private function collect_theme_mod_writes(array $payload): array {
        $writes = [];

        foreach (self::TOKEN_MAP as $source => $target) {
            $value = $this->read_nested_value($payload, $source);

            if ($value === null || $value === '') {
                continue;
            }

            $writes[$target] = $value;
        }

        $font_assets = is_array($payload['font_assets'] ?? null) ? $payload['font_assets'] : [];

        if (!empty($font_assets['body_font_object'])) {
            $writes['body_font_object'] = $font_assets['body_font_object'];
        }

        if (!empty($font_assets['headings_font_object'])) {
            $writes['headings_font_object'] = $font_assets['headings_font_object'];
        }

        if (array_key_exists('fonts_header_code', $font_assets) && $font_assets['fonts_header_code'] !== '') {
            $writes['picostrap_fonts_header_code'] = (string) $font_assets['fonts_header_code'];
        }

        return $writes;
    }

    private function build_font_warnings(array $payload): array {
        $font_assets = is_array($payload['font_assets'] ?? null) ? $payload['font_assets'] : [];
        $typography = is_array($payload['typography'] ?? null) ? $payload['typography'] : [];

        if (($typography['font_family_base'] ?? '') === '' && ($typography['headings_font_family'] ?? '') === '') {
            return [];
        }

        if (!empty($font_assets['body_font_object']) || !empty($font_assets['headings_font_object']) || !empty($font_assets['fonts_header_code'])) {
            return [];
        }

        return [__('Font family tokens were applied, but no Picostrap font asset metadata was provided.', 'livecanvas-forge-ai')];
    }

    private function read_nested_value(array $payload, string $path) {
        $segments = explode('.', $path);
        $cursor = $payload;

        foreach ($segments as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
```

- [ ] **Step 3: Add a Picowind shell plus the shared coordinator that resolves the active stack**

```php
<?php
// includes/class-lcfa-design-system-picowind-executor.php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Picowind_Executor {
    public function __construct(
        private LCFA_WindPress_Bridge $windpress_bridge,
        private LCFA_Theme_Files_Bridge $theme_files_bridge,
        private LCFA_Design_System_Build_Gateway $build_gateway
    ) {}

    public function execute(array $payload, bool $dry_run): array {
        return [
            'ok' => false,
            'action' => 'design_system_apply',
            'mode' => $dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'message' => __('Picowind executor shell was invoked before Task 3 replaced it with the real implementation.', 'livecanvas-forge-ai'),
            'summary' => '',
            'warnings' => [],
            'data' => [],
        ];
    }
}
```

```php
<?php
// includes/class-lcfa-design-system-apply.php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Apply {
    public function __construct(
        private LCFA_Environment $environment,
        private LCFA_Design_System_Picostrap_Executor $picostrap_executor,
        private LCFA_Design_System_Picowind_Executor $picowind_executor
    ) {}

    public function run(array $payload, bool $dry_run): array {
        $framework = $this->resolve_framework($payload);

        if ($framework === 'picostrap') {
            return $this->picostrap_executor->execute($payload, $dry_run);
        }

        if ($framework === 'picowind') {
            return $this->picowind_executor->execute($payload, $dry_run);
        }

        return [
            'ok' => false,
            'action' => 'design_system_apply',
            'mode' => $dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'message' => __('Unable to resolve a supported design-system target stack.', 'livecanvas-forge-ai'),
            'summary' => '',
            'warnings' => [],
            'data' => ['requested_framework' => (string) ($payload['framework'] ?? '')],
        ];
    }

    private function resolve_framework(array $payload): string {
        $explicit = sanitize_key((string) ($payload['framework'] ?? ''));

        if (in_array($explicit, ['picostrap', 'picowind'], true)) {
            return $explicit;
        }

        return $this->environment->detect_framework_family();
    }
}
```

- [ ] **Step 4: Load the new classes in the plugin bootstrap**

```php
require_once LCFA_DIR . 'includes/class-lcfa-design-system-build-gateway.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-executor.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-picowind-executor.php';
require_once LCFA_DIR . 'includes/class-lcfa-design-system-apply.php';
```

- [ ] **Step 5: Run the Picostrap scenario until it passes**

Run: `php tests/php/design_system_apply_phase1.php picostrap`

Expected: PASS. The harness should report no assertion failures, and `Picowind` plus command-deck scenarios can remain red.

- [ ] **Step 6: Commit the Picostrap slice**

```bash
git add livecanvas-forge-ai.php includes/class-lcfa-design-system-build-gateway.php includes/class-lcfa-design-system-picostrap-executor.php includes/class-lcfa-design-system-picowind-executor.php includes/class-lcfa-design-system-apply.php tests/php/design_system_apply_phase1.php
git commit -m "feat: add picostrap design system executor"
```

### Task 3: Implement The Picowind / WindPress Executor

**Files:**
- Modify: `includes/class-lcfa-design-system-picowind-executor.php`
- Modify: `includes/class-lcfa-design-system-apply.php`
- Modify: `tests/php/design_system_apply_phase1.php`

- [ ] **Step 1: Build a theme.json generator and DaisyUI preset writer**

```php
<?php
// includes/class-lcfa-design-system-picowind-executor.php

defined('ABSPATH') || exit;

final class LCFA_Design_System_Picowind_Executor {
    public function __construct(
        private LCFA_WindPress_Bridge $windpress_bridge,
        private LCFA_Theme_Files_Bridge $theme_files_bridge,
        private LCFA_Design_System_Build_Gateway $build_gateway
    ) {}

    public function execute(array $payload, bool $dry_run): array {
        $preset = is_array($payload['preset'] ?? null) ? $payload['preset'] : [];
        $theme_json = $this->build_theme_json($payload);
        $changed_keys = $this->collect_changed_keys($payload, $preset);
        $warnings = [];
        $build_required = true;
        $build_executed = false;

        if (!$dry_run) {
            if (!empty($preset['active_theme'])) {
                set_theme_mod('data_theme', (string) $preset['active_theme']);
            }

            if (!empty($preset['skin'])) {
                $this->theme_files_bridge->write_file([
                    'root_scope' => 'stylesheet',
                    'path' => 'public/styles/presets/daisyui.css',
                    'content' => $this->render_daisyui_preset((string) $preset['skin']),
                ]);
            }

            $stored = $this->windpress_bridge->save_theme_json($theme_json);
            if (empty($stored['ok'])) {
                return [
                    'ok' => false,
                    'action' => 'design_system_apply',
                    'mode' => 'apply',
                    'execution_target' => 'local',
                    'message' => (string) ($stored['message'] ?? __('Unable to store WindPress theme.json.', 'livecanvas-forge-ai')),
                    'summary' => '',
                    'warnings' => [],
                    'data' => [],
                ];
            }
        }
```

- [ ] **Step 2: Attempt a capability-aware WindPress build and surface warnings instead of fake success**

```php
        $gateway_status = $this->build_gateway->get_status();

        if (!$dry_run && !empty($gateway_status['build_available'])) {
            $build = $this->build_gateway->build_windpress_cache([
                'kind' => 'full',
                'store' => true,
                'source_map' => false,
            ]);

            if (!empty($build['ok'])) {
                $build_executed = true;
            } else {
                $warnings[] = (string) ($build['message'] ?? __('WindPress build failed after storing theme.json.', 'livecanvas-forge-ai'));
            }
        } elseif (!$dry_run) {
            $warnings[] = (string) ($gateway_status['message'] ?? __('WindPress build is not available from this runtime.', 'livecanvas-forge-ai'));
        }

        return [
            'ok' => true,
            'action' => 'design_system_apply',
            'mode' => $dry_run ? 'preview' : 'apply',
            'execution_target' => 'local',
            'message' => $dry_run
                ? __('Picowind design system preview prepared.', 'livecanvas-forge-ai')
                : __('Picowind design system applied.', 'livecanvas-forge-ai'),
            'target_stack' => 'picowind',
            'source_of_truth' => 'windpress_cache_runtime',
            'summary' => $dry_run
                ? __('Preview Picowind design system changes.', 'livecanvas-forge-ai')
                : __('Applied design system tokens to Picowind and WindPress.', 'livecanvas-forge-ai'),
            'changed_keys' => $changed_keys,
            'build_required' => $build_required,
            'build_executed' => $build_executed,
            'build_strategy' => 'windpress_runtime_build',
            'warnings' => $warnings,
            'data' => [
                'theme_json' => $theme_json,
                'active_theme' => (string) ($preset['active_theme'] ?? ''),
                'preset_skin' => (string) ($preset['skin'] ?? ''),
            ],
        ];
    }
}
```

- [ ] **Step 3: Encode the first-slice `theme.json` mapping exactly once**

```php
private function build_theme_json(array $payload): array {
    $colors = is_array($payload['colors'] ?? null) ? $payload['colors'] : [];
    $typography = is_array($payload['typography'] ?? null) ? $payload['typography'] : [];
    $radius = is_array($payload['radius'] ?? null) ? $payload['radius'] : [];

    return [
        '$schema' => 'https://schemas.wp.org/trunk/theme.json',
        'version' => 3,
        'settings' => [
            'color' => [
                'palette' => array_values(array_filter([
                    !empty($colors['primary']) ? ['slug' => 'primary', 'name' => 'Primary', 'color' => (string) $colors['primary']] : null,
                    !empty($colors['secondary']) ? ['slug' => 'secondary', 'name' => 'Secondary', 'color' => (string) $colors['secondary']] : null,
                    !empty($colors['light']) ? ['slug' => 'light', 'name' => 'Light', 'color' => (string) $colors['light']] : null,
                    !empty($colors['dark']) ? ['slug' => 'dark', 'name' => 'Dark', 'color' => (string) $colors['dark']] : null,
                ])),
            ],
            'typography' => [
                'fontFamilies' => !empty($typography['font_family_base']) ? [[
                    'slug' => 'body',
                    'name' => 'Body',
                    'fontFamily' => (string) $typography['font_family_base'],
                ]] : [],
            ],
        ],
        'styles' => [
            'color' => [
                'background' => (string) ($colors['body_bg'] ?? ''),
                'text' => (string) ($colors['body_color'] ?? ''),
            ],
            'typography' => [
                'fontSize' => (string) ($typography['font_size_base'] ?? ''),
                'lineHeight' => (string) ($typography['line_height_base'] ?? ''),
            ],
            'elements' => [
                'button' => [
                    'border' => [
                        'radius' => (string) ($radius['border_radius'] ?? ''),
                    ],
                ],
            ],
        ],
    ];
}

private function collect_changed_keys(array $payload, array $preset): array {
    $changed = [];

    if (!empty($preset['skin'])) {
        $changed[] = 'preset.skin';
    }

    if (!empty($preset['active_theme'])) {
        $changed[] = 'theme_mod.data_theme';
    }

    foreach (['primary', 'secondary', 'light', 'dark', 'body_bg', 'body_color'] as $key) {
        if (!empty($payload['colors'][$key])) {
            $changed[] = 'theme_json.color.' . $key;
        }
    }

    foreach (['font_family_base', 'font_size_base', 'line_height_base'] as $key) {
        if (!empty($payload['typography'][$key])) {
            $changed[] = 'theme_json.typography.' . $key;
        }
    }

    if (!empty($payload['radius']['border_radius'])) {
        $changed[] = 'theme_json.radius.border_radius';
    }

    return $changed;
}

private function render_daisyui_preset(string $skin): string {
    return "/**\n * Create a custom theme for yourself using daisyUI theme generator.\n * https://daisyui.com/theme-generator/\n */\n\n@plugin \"daisyui\" {\n    themes: {$skin} --default, dark;\n}\n\n/* @plugin \"@tailwindcss/typography\"; */\n\n/* Add your custom styles below this line */\n\nbody {\n}\n";
}
```

- [ ] **Step 4: Run the Picowind scenario until it passes**

Run: `php tests/php/design_system_apply_phase1.php picowind`

Expected: PASS. `data_theme` should be written, the fake WindPress cache should receive a `theme.json` blob, and `build_executed` should be `true` when the fake gateway reports build availability.

- [ ] **Step 5: Commit the Picowind slice**

```bash
git add includes/class-lcfa-design-system-picowind-executor.php includes/class-lcfa-design-system-apply.php tests/php/design_system_apply_phase1.php
git commit -m "feat: add picowind design system executor"
```

### Task 4: Wire `design_system_apply` Into The Plugin And Command Deck

**Files:**
- Modify: `includes/class-lcfa-plugin.php`
- Modify: `includes/class-lcfa-command-deck.php`
- Modify: `tests/php/design_system_apply_phase1.php`
- Modify: `tests/php/foundation_contract_phase1.php`

- [ ] **Step 1: Instantiate the new executors and service in the plugin bootstrap**

```php
$design_system_build_gateway = new LCFA_Design_System_Build_Gateway($this->local_mcp_bridge);
$picostrap_design_system = new LCFA_Design_System_Picostrap_Executor();
$picowind_design_system = new LCFA_Design_System_Picowind_Executor(
    $this->windpress_bridge,
    $this->theme_files_bridge,
    $design_system_build_gateway
);
$design_system_apply = new LCFA_Design_System_Apply(
    $this->environment,
    $picostrap_design_system,
    $picowind_design_system
);

$this->command_deck = new LCFA_Command_Deck(
    $this->environment,
    $this->inventory,
    $this->windpress_bridge,
    $this->theme_files_bridge,
    $this->local_mcp_bridge,
    $this->remote_client,
    $design_system_apply
);
```

- [ ] **Step 2: Add a backwards-compatible optional dependency to the command deck**

```php
private LCFA_Design_System_Apply $design_system_apply;

public function __construct(
    LCFA_Environment $environment,
    LCFA_Inventory $inventory,
    LCFA_WindPress_Bridge $windpress_bridge,
    LCFA_Theme_Files_Bridge $theme_files_bridge,
    LCFA_Local_MCP_Bridge $local_mcp_bridge,
    LCFA_Remote_Client $remote_client,
    ?LCFA_Design_System_Apply $design_system_apply = null
) {
    $this->environment = $environment;
    $this->inventory = $inventory;
    $this->windpress_bridge = $windpress_bridge;
    $this->theme_files_bridge = $theme_files_bridge;
    $this->local_mcp_bridge = $local_mcp_bridge;
    $this->remote_client = $remote_client;
    $this->design_system_apply = $design_system_apply ?: new LCFA_Design_System_Apply(
        $environment,
        new LCFA_Design_System_Picostrap_Executor(),
        new LCFA_Design_System_Picowind_Executor(
            $windpress_bridge,
            $theme_files_bridge,
            new LCFA_Design_System_Build_Gateway($local_mcp_bridge)
        )
    );
}
```

- [ ] **Step 3: Expose the action and delegate the switch branch**

```php
'design_system_apply' => [
    'label' => __('Apply design system', 'livecanvas-forge-ai'),
    'description' => __('Applies stack-native design tokens to Picostrap or Picowind and returns explicit build metadata.', 'livecanvas-forge-ai'),
],
```

```php
case 'design_system_apply':
    $result = array_merge($result, $this->design_system_apply->run($payload, $dry_run));
    break;
```

Add it to the non-LiveCanvas-gated list:

```php
return !in_array($action, [
    'design_system_apply',
    'windpress_audit',
    'windpress_scan_provider',
    'windpress_reset_entry',
    'windpress_store_theme_json',
```

- [ ] **Step 4: Keep the legacy harness green by loading the new classes**

```php
require LCFA_DIR . 'includes/class-lcfa-design-system-build-gateway.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-picowind-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-apply.php';
```

- [ ] **Step 5: Run the command-deck exposure test and the existing foundation harness**

Run: `php tests/php/design_system_apply_phase1.php command`

Expected: PASS. `get_actions()` should include `design_system_apply`.

Run: `php tests/php/foundation_contract_phase1.php`

Expected: PASS. The earlier foundation slice must remain green after the constructor/bootstrap changes.

- [ ] **Step 6: Commit the command-deck wiring**

```bash
git add includes/class-lcfa-plugin.php includes/class-lcfa-command-deck.php tests/php/design_system_apply_phase1.php tests/php/foundation_contract_phase1.php
git commit -m "feat: wire design system apply into command deck"
```

### Task 5: Verify The First Slice End-To-End

**Files:**
- Modify: none expected

- [ ] **Step 1: Run syntax checks on the new PHP files**

Run:

```bash
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-design-system-build-gateway.php
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-design-system-picostrap-executor.php
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-design-system-picowind-executor.php
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-design-system-apply.php
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-command-deck.php
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/includes/class-lcfa-plugin.php
php -l /Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/livecanvas-forge-ai.php
```

Expected: every file reports `No syntax errors detected`.

- [ ] **Step 2: Run the dedicated regression harness in full**

Run: `php tests/php/design_system_apply_phase1.php`

Expected: PASS. The Picostrap, Picowind, and command-deck scenarios should all pass in one run.

- [ ] **Step 3: Re-run the already-green regression suites**

Run:

```bash
php tests/php/foundation_contract_phase1.php
php tests/php/connections_wizard_phase1.php
node mcp/tests/tool-registry.test.js
node mcp/tests/stdio-startup.test.js
node mcp/tests/workspace-root-sync.test.js
```

Expected: all suites PASS. `design_system_apply` should not regress the OpenCode/MCP or foundation-contract work already completed.

- [ ] **Step 4: Inspect the diff before any final cleanup**

Run:

```bash
git status --short
git diff --stat HEAD~3..HEAD
```

Expected: only the planned design-system files, the bootstrap wiring, and the two PHP harnesses are part of this slice.

- [ ] **Step 5: Commit any final cleanup**

```bash
git add livecanvas-forge-ai.php includes/class-lcfa-plugin.php includes/class-lcfa-command-deck.php includes/class-lcfa-design-system-build-gateway.php includes/class-lcfa-design-system-picostrap-executor.php includes/class-lcfa-design-system-picowind-executor.php includes/class-lcfa-design-system-apply.php tests/php/design_system_apply_phase1.php tests/php/foundation_contract_phase1.php
git commit -m "chore: finalize design system apply first slice"
```
