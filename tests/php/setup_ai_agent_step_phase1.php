<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('LCFA_VERSION', 'test-version');
define('WP_PLUGIN_DIR', '/Users/commander/Studio/consultala/wp-content/plugins');

function __(string $text, string $domain = ''): string {
    return $text;
}

function esc_html(string $value): string {
    return $value;
}

function esc_html__(string $text, string $domain = ''): string {
    return $text;
}

function esc_attr(string $value): string {
    return $value;
}

function esc_url(string $value): string {
    return $value;
}

function admin_url(string $path = ''): string {
    return 'http://example.test/wp-admin/' . ltrim($path, '/');
}

function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): void {
}

function checked($checked, $current = true, bool $display = true): string {
    return $checked == $current ? ' checked="checked"' : '';
}

function sanitize_html_class(string $value): string {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?: '';
}

function plugins_url(string $path = '', string $plugin = ''): string {
    return 'http://example.test/wp-content/plugins/' . ltrim($path, '/');
}

function rest_url(string $path = ''): string {
    return 'http://example.test/wp-json/' . ltrim($path, '/');
}

function wp_json_encode($value, int $flags = 0, int $depth = 512): string {
    return json_encode($value, $flags, $depth) ?: '';
}

final class LCFA_Settings {
    public static function get(): array {
        return [
            'site_mode' => 'local',
        ];
    }
}

function lcfa_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_not_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();

$render_ai_tool_step = new ReflectionMethod('LCFA_Admin', 'render_ai_tool_step');
$render_step_nav = new ReflectionMethod('LCFA_Admin', 'render_step_nav');

ob_start();
$render_ai_tool_step->invoke($admin, [
    'ai_tool' => 'codex',
]);
$step_markup = (string) ob_get_clean();

ob_start();
$render_step_nav->invoke($admin, 4, [
    'last_completed_step' => 3,
], [
    'livecanvas_active' => true,
]);
$nav_markup = (string) ob_get_clean();

lcfa_assert_contains('Step 4. AI Coding Agent', $step_markup, 'step 4 should be titled AI Coding Agent');
lcfa_assert_contains('Save AI Coding Agent', $step_markup, 'step 4 CTA should reference AI Coding Agent');
lcfa_assert_contains('>Claude<', $step_markup, 'step 4 should render Claude as the setup option label');
lcfa_assert_not_contains('>Claude Code<', $step_markup, 'step 4 should no longer render Claude Code as the setup option label');
lcfa_assert_not_contains('Step 4. AI client', $step_markup, 'step 4 should no longer refer to AI client');
lcfa_assert_contains('4. AI Coding Agent', $nav_markup, 'step navigation should use AI Coding Agent for step 4');
lcfa_assert_not_contains('4. Client', $nav_markup, 'step navigation should no longer use Client for step 4');

echo "PASS\n";
