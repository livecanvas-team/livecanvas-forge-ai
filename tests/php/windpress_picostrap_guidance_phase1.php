<?php

declare(strict_types=1);

require_once __DIR__ . '/reflection-compat.php';

define('ABSPATH', sys_get_temp_dir() . '/lcfa-windpress-guidance/');

function __(string $text, string $domain = ''): string { return $text; }
function esc_html__(string $text, string $domain = ''): string { return $text; }
function esc_html(string $value): string { return $value; }
function esc_attr(string $value): string { return $value; }
function esc_url(string $value): string { return $value; }
function admin_url(string $path = ''): string { return 'https://example.test/wp-admin/' . ltrim($path, '/'); }
function wp_nonce_field(string $action): void {
    echo '<input type="hidden" name="_wpnonce" value="nonce-' . $action . '">';
}

function lcfa_windpress_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-admin.php';

$reflection = new ReflectionClass('LCFA_Admin');
$admin = $reflection->newInstanceWithoutConstructor();
$render = lcfa_test_reflection_method('LCFA_Admin', 'render_windpress_framework_advisory');

ob_start();
$render->invoke($admin, [
    'detected_framework' => 'picostrap',
    'windpress_installed' => true,
    'windpress_active' => true,
    'windpress_compatibility' => [
        'status' => 'deactivation_recommended',
        'action' => 'deactivate',
    ],
]);
$picostrap_output = (string) ob_get_clean();

lcfa_windpress_assert(str_contains($picostrap_output, 'lcfa-runtime-advisory'), 'Picostrap with active WindPress should render the focused runtime advisory.');
lcfa_windpress_assert(str_contains($picostrap_output, 'WindPress is not used by Picostrap'), 'The advisory should explain the framework relationship directly.');
lcfa_windpress_assert(str_contains($picostrap_output, 'Deactivate WindPress'), 'The advisory should expose one clear admin action.');
lcfa_windpress_assert(str_contains($picostrap_output, 'lcfa_deactivate_redundant_windpress'), 'The action should use the dedicated protected admin-post handler.');
lcfa_windpress_assert(str_contains($picostrap_output, 'lcfa-button-with-icon'), 'The dashboard action should include the standard icon button treatment.');

ob_start();
$render->invoke($admin, [
    'detected_framework' => 'picowind',
    'windpress_installed' => true,
    'windpress_active' => true,
    'windpress_compatibility' => [
        'status' => 'ready',
        'action' => 'none',
    ],
]);
$picowind_output = (string) ob_get_clean();
lcfa_windpress_assert($picowind_output === '', 'Picowind with active WindPress should not render a deactivation advisory.');

ob_start();
$render->invoke($admin, [
    'detected_framework' => 'picostrap',
    'windpress_installed' => true,
    'windpress_active' => false,
    'windpress_compatibility' => [
        'status' => 'not_required',
        'action' => 'none',
    ],
]);
$inactive_output = (string) ob_get_clean();
lcfa_windpress_assert($inactive_output === '', 'Inactive WindPress should not keep warning Picostrap users.');

$admin_source = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/class-lcfa-admin.php');
lcfa_windpress_assert(str_contains($admin_source, "add_action('admin_notices', [\$this, 'render_picostrap_windpress_admin_notice'])"), 'The Plugins screen should register the compatibility notice.');
lcfa_windpress_assert(str_contains($admin_source, "admin_post_lcfa_deactivate_redundant_windpress"), 'The explicit deactivation action should be registered.');
lcfa_windpress_assert(str_contains($admin_source, "check_admin_referer('lcfa_deactivate_redundant_windpress')"), 'The deactivation action should require a nonce.');

$css = (string) file_get_contents(dirname(__DIR__, 2) . '/assets/admin-v2.css');
lcfa_windpress_assert(str_contains($css, '.lcfa-admin .lcfa-runtime-advisory'), 'The dashboard advisory should have a dedicated responsive visual treatment.');
lcfa_windpress_assert(str_contains($css, 'border-color: var(--lcfa-v2-amber);'), 'The advisory action should override the low-contrast WordPress button color.');
lcfa_windpress_assert(str_contains($css, '.lcfa-runtime-advisory__action .button:focus-visible'), 'The advisory action should keep a visible keyboard focus state.');

echo "PASS\n";
