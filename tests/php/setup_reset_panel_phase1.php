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

require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();

$method = new ReflectionMethod('LCFA_Admin', 'render_setup_reset_panel');

ob_start();
$method->invoke($admin);
$markup = (string) ob_get_clean();

lcfa_assert_contains('Reset setup', $markup, 'setup should expose a reset panel at the bottom');
lcfa_assert_contains('This does not uninstall the plugin', $markup, 'reset panel should clarify that it is not a destructive uninstall');
lcfa_assert_contains('Connections are cleared and a new MCP token is generated.', $markup, 'reset panel should clarify that connections are reset');
lcfa_assert_contains('Project Brief and Command Deck history stay intact.', $markup, 'reset panel should clarify preserved project data');
lcfa_assert_contains('If you already wrote a client config into your workspace, reset will not delete that file.', $markup, 'reset panel should clarify that workspace files are not deleted');
lcfa_assert_contains('Any previously written or downloaded client bundle may no longer match the new token after reset.', $markup, 'reset panel should warn that old bundles become stale after reset');
lcfa_assert_contains('name="action" value="lcfa_reset_setup"', $markup, 'reset panel should post the setup reset action');
lcfa_assert_contains('Reset setup and start again', $markup, 'reset panel should expose the reset CTA');

echo "PASS\n";
