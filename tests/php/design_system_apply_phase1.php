<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-design-system-tests/');
define('LCFA_VERSION', '0.1.0-test');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_TEST_TMP', sys_get_temp_dir() . '/lcfa-design-system-tests');
define('WP_CONTENT_DIR', LCFA_TEST_TMP . '/wp-content');

@mkdir(LCFA_TEST_TMP, 0777, true);

$GLOBALS['lcfa_test_options'] = [];
$GLOBALS['lcfa_test_theme_mods'] = [];
$GLOBALS['lcfa_test_wp_cache'] = [];
$GLOBALS['lcfa_test_active_plugins'] = ['windpress/windpress.php'];
$GLOBALS['lcfa_test_stylesheet'] = 'picostrap-child';
$GLOBALS['lcfa_test_template'] = 'picostrap5';
$GLOBALS['lcfa_test_theme_name'] = 'Picostrap Child';

function __(string $text, string $domain = ''): string { return $text; }
function sanitize_key(string $value): string { $value = strtolower($value); return (string) preg_replace('/[^a-z0-9_\-]/', '', $value); }
function sanitize_title($value): string {
    $value = strtolower(trim((string) $value));
    $value = (string) preg_replace('/[^a-z0-9]+/', '-', $value);
    return trim($value, '-');
}
function sanitize_text_field($value): string { return trim((string) $value); }
function sanitize_textarea_field($value): string { return trim((string) $value); }
function sanitize_file_name(string $value): string { return (string) preg_replace('/[^A-Za-z0-9\.\-_]/', '-', $value); }
function absint($value): int { return abs((int) $value); }
function wp_json_encode($value, int $flags = 0): string { return (string) json_encode($value, $flags); }
function wp_unslash($value) { return $value; }
function trailingslashit(string $value): string { return rtrim($value, "/\\") . '/'; }
function untrailingslashit(string $value): string { return rtrim($value, "/\\"); }
function wp_normalize_path(string $value): string { return str_replace('\\', '/', $value); }
function wp_parse_args($args, $defaults = []): array {
    if (is_object($args)) {
        $args = get_object_vars($args);
    } elseif (!is_array($args)) {
        parse_str((string) $args, $parsed);
        $args = $parsed;
    }

    if (is_object($defaults)) {
        $defaults = get_object_vars($defaults);
    }

    return array_merge((array) $defaults, (array) $args);
}
function current_time(string $type = 'mysql', bool $gmt = false): string { return gmdate('Y-m-d H:i:s'); }
function apply_filters(string $hook, $value, ...$args) { return $value; }
function wp_parse_url(string $url, int $component = -1) { return parse_url($url, $component); }
function rest_url(string $path = ''): string { return home_url('/wp-json/' . ltrim($path, '/')); }
function wp_remote_get(string $url, array $args = []) { return new WP_Error('not_implemented', 'wp_remote_get should not be called in this harness'); }
function wp_remote_retrieve_response_code($response): int { return 500; }
function wp_get_upload_dir(): array {
    return [
        'basedir' => WP_CONTENT_DIR . '/uploads',
        'baseurl' => 'http://localhost:8887/wp-content/uploads',
    ];
}

function get_option(string $key, $default = false) {
    return $GLOBALS['lcfa_test_options'][$key] ?? $default;
}

function update_option(string $key, $value): bool {
    $GLOBALS['lcfa_test_options'][$key] = $value;
    return true;
}

function add_option(string $key, $value): bool {
    if (!array_key_exists($key, $GLOBALS['lcfa_test_options'])) {
        $GLOBALS['lcfa_test_options'][$key] = $value;
    }

    return true;
}

function delete_option(string $key): bool {
    unset($GLOBALS['lcfa_test_options'][$key]);
    return true;
}

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

function wp_mkdir_p(string $target): bool {
    return is_dir($target) || mkdir($target, 0777, true);
}

function get_plugins(): array {
    return [
        'windpress/windpress.php' => ['TextDomain' => 'windpress'],
    ];
}

function is_plugin_active(string $plugin_file): bool {
    return in_array($plugin_file, $GLOBALS['lcfa_test_active_plugins'], true);
}

final class WP_Error {
    public function __construct(private string $code = '', private string $message = '') {}
    public function get_error_message(): string { return $this->message; }
}

function is_wp_error($value): bool {
    return $value instanceof WP_Error;
}

final class WP_Theme {
    public function __construct(private array $data = []) {}
    public function get(string $field): string { return (string) ($this->data[$field] ?? ''); }
    public function get_stylesheet(): string { return (string) ($this->data['stylesheet'] ?? ''); }
    public function get_template(): string { return (string) ($this->data['template'] ?? ''); }
    public function parent() { return null; }
}

function wp_get_theme(): WP_Theme {
    return new WP_Theme([
        'Name' => $GLOBALS['lcfa_test_theme_name'],
        'stylesheet' => $GLOBALS['lcfa_test_stylesheet'],
        'template' => $GLOBALS['lcfa_test_template'],
    ]);
}

function get_stylesheet_directory(): string {
    return LCFA_TEST_TMP . '/themes/' . $GLOBALS['lcfa_test_stylesheet'];
}

function get_template_directory(): string {
    return LCFA_TEST_TMP . '/themes/' . $GLOBALS['lcfa_test_template'];
}

function get_theme_root(string $stylesheet = ''): string {
    return LCFA_TEST_TMP . '/themes';
}

eval(<<<'PHP'
namespace WindPress\WindPress\Core {
    final class Volume {
        public static array $entries = [];

        public static function get_entries(): array {
            return self::$entries;
        }

        public static function save_entries(array $entries): void {
            self::$entries = $entries;
        }

        public static function get_available_handlers(): array {
            return [['value' => 'internal', 'label' => 'Internal', 'description' => 'Internal']];
        }

        public static function data_dir_path(): string {
            return \LCFA_TEST_TMP . '/windpress-data';
        }

        public static function data_dir_url(): string {
            return 'http://localhost:8887/wp-content/uploads/windpress/data';
        }
    }

    final class Cache {
        public static array $providers = [];
        public static string $themeJson = '';
        public static string $css = '';

        public const CSS_CACHE_FILE = 'cache.css';
        public const THEME_JSON_FILE = 'theme.json';

        public static function get_providers(): array {
            return self::$providers;
        }

        public static function save_theme_json(string $blob): void {
            self::$themeJson = $blob;
        }

        public static function save_cache(string $css): void {
            self::$css = $css;
        }

        public static function save_sourcemap(string $sourcemap): void {}

        public static function get_cache_path(string $file): string {
            return \LCFA_TEST_TMP . '/windpress-cache/' . $file;
        }

        public static function get_cache_url(string $file): string {
            return 'http://localhost:8887/wp-content/uploads/windpress/cache/' . $file;
        }
    }
}
PHP);

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected `' . var_export($expected, true) . '`, got `' . var_export($actual, true) . '`.');
    }
}

function lcfa_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

@mkdir(get_stylesheet_directory() . '/public/styles/presets', 0777, true);
@mkdir(get_template_directory(), 0777, true);
file_put_contents(get_stylesheet_directory() . '/public/styles/presets/daisyui.css', "/** preset */\n@plugin \"daisyui\" {\n    themes: light --default, dark;\n}\n");

require LCFA_DIR . 'includes/class-lcfa-settings.php';
require LCFA_DIR . 'includes/class-lcfa-environment.php';
require LCFA_DIR . 'includes/class-lcfa-inventory.php';
require LCFA_DIR . 'includes/class-lcfa-windpress-bridge.php';
require LCFA_DIR . 'includes/class-lcfa-theme-files-bridge.php';
require LCFA_DIR . 'includes/class-lcfa-local-mcp-bridge.php';
require LCFA_DIR . 'includes/class-lcfa-remote-client.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-build-gateway.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-picowind-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-fallback-executor.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-apply.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-preview.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-picostrap-composer.php';
require LCFA_DIR . 'includes/class-lcfa-design-system-compose.php';
require LCFA_DIR . 'includes/class-lcfa-command-deck.php';

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
    @mkdir(get_stylesheet_directory() . '/public/styles/presets', 0777, true);
    @mkdir(get_template_directory(), 0777, true);
    file_put_contents(get_stylesheet_directory() . '/public/styles/presets/daisyui.css', "/** preset */\n@plugin \"daisyui\" {\n    themes: light --default, dark;\n}\n");

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

$scenario = $argv[1] ?? 'all';

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
    lcfa_assert_true(in_array('SCSSvar_primary', $preview['changed_keys'] ?? [], true), 'Picostrap preview should report SCSSvar_primary');
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

if ($scenario === 'fallback' || $scenario === 'all') {
    $GLOBALS['lcfa_test_theme_name'] = 'Custom Child';
    $GLOBALS['lcfa_test_stylesheet'] = 'custom-child';
    $GLOBALS['lcfa_test_template'] = 'custom-parent';
    @mkdir(get_stylesheet_directory(), 0777, true);
    @mkdir(get_template_directory(), 0777, true);
    @unlink(get_stylesheet_directory() . '/assets/lcfa-design-system.css');
    @unlink(get_stylesheet_directory() . '/assets/lcfa-design-system.json');

    $environment = new LCFA_Environment();
    $themeFiles = new LCFA_Theme_Files_Bridge($environment);
    $service = new LCFA_Design_System_Apply(
        $environment,
        new LCFA_Design_System_Picostrap_Executor(),
        new LCFA_Design_System_Picowind_Executor(
            new LCFA_WindPress_Bridge($environment),
            $themeFiles,
            new Test_Design_System_Build_Gateway(['build_available' => false, 'message' => 'disabled'], ['ok' => false, 'message' => 'disabled'])
        ),
        new LCFA_Design_System_Fallback_Executor($environment, $themeFiles)
    );

    $fallbackPreview = $service->run([
        'action' => 'design_system_apply',
        'framework' => 'custom',
        'colors' => ['primary' => '#abcdef', 'body_bg' => '#ffffff'],
        'typography' => ['font_family_base' => 'Inter, sans-serif'],
    ], true);
    $fallbackCssPath = get_stylesheet_directory() . '/assets/lcfa-design-system.css';

    lcfa_assert_same('fallback_theme', $fallbackPreview['target_stack'], 'Fallback preview should target portable theme assets');
    lcfa_assert_same('theme_file_assets', $fallbackPreview['source_of_truth'], 'Fallback preview should use theme files as source of truth');
    lcfa_assert_true(!file_exists($fallbackCssPath), 'Fallback preview must not write CSS files');
    lcfa_assert_true(!empty($fallbackPreview['data']['writes']['css']['dry_run']), 'Fallback preview should report a dry-run CSS write');

    $fallbackApply = $service->run([
        'action' => 'design_system_apply',
        'framework' => 'custom',
        'colors' => ['primary' => '#abcdef', 'body_bg' => '#ffffff'],
        'radius' => ['border_radius' => '8px'],
    ], false);

    lcfa_assert_same('fallback_theme', $fallbackApply['target_stack'], 'Fallback apply should target portable theme assets');
    lcfa_assert_true(file_exists($fallbackCssPath), 'Fallback apply should write the portable CSS asset');
    lcfa_assert_true(strpos((string) file_get_contents($fallbackCssPath), '--lcfa-color-primary: #abcdef;') !== false, 'Fallback CSS should contain the requested primary token');

    $GLOBALS['lcfa_test_theme_name'] = 'Picostrap Child';
    $GLOBALS['lcfa_test_stylesheet'] = 'picostrap-child';
    $GLOBALS['lcfa_test_template'] = 'picostrap5';
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

    $commandResult = $commandDeck->execute([
        'action' => 'design_system_apply',
        'framework' => 'picostrap',
        'dry_run' => true,
        'colors' => ['primary' => '#334455'],
    ]);

    lcfa_assert_true(!empty($commandResult['ok']), 'Command deck should execute design_system_apply');
    lcfa_assert_same('design_system_apply', $commandResult['action'], 'Command deck should preserve the design_system_apply action');
    lcfa_assert_same('theme_mods', $commandResult['source_of_truth'] ?? '', 'Command deck should return the Picostrap source of truth');
    lcfa_assert_true(in_array('SCSSvar_primary', $commandResult['changed_keys'] ?? [], true), 'Command deck should bubble up changed_keys from the design system service');
}

echo "PASS\n";
