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

$render_permissions_step = new ReflectionMethod('LCFA_Admin', 'render_permissions_step');

ob_start();
$render_permissions_step->invoke($admin, [
    'permission_profile'  => 'draft_preview',
    'allow_file_fallback' => false,
]);
$markup = (string) ob_get_clean();

lcfa_assert_contains('Step 5. Enable Full Access', $markup, 'permissions step should expose the explicit full-access title');
lcfa_assert_contains('name="step" value="5"', $markup, 'permissions step should still post step 5');
lcfa_assert_contains('This grants the companion the broadest operating scope', $markup, 'permissions step should explain the full-access consent clearly');
lcfa_assert_contains('Continue', $markup, 'permissions step should use a continue CTA');
lcfa_assert_not_contains('name="permission_profile"', $markup, 'permissions step should no longer expose permission profile radios');
lcfa_assert_not_contains('name="allow_file_fallback"', $markup, 'permissions step should no longer expose the file fallback checkbox');

echo "PASS\n";
