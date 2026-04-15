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

require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();

$method = new ReflectionMethod('LCFA_Admin', 'render_connection_generate_bundle_actions');

ob_start();
$method->invoke($admin, [
    'client' => 'codex',
    'mode' => 'local',
    'workspace_root' => '/Users/example/project',
    'workspace_files' => [
        ['name' => 'livecanvas-forge.codex.sh'],
    ],
], [
    'available' => true,
], [
    'primary_cta' => [
        'label' => 'Write config in workspace',
        'action' => 'install',
    ],
    'secondary_ctas' => [
        [
            'label' => 'Download client bundle',
            'action' => 'download',
        ],
    ],
]);
$markup = (string) ob_get_clean();

lcfa_assert_contains('lcfa-choice-grid lcfa-choice-grid--actions', $markup, 'generate bundle step should render action cards in a dedicated choice grid');
lcfa_assert_contains('Recommended', $markup, 'generate bundle step should mark the main path as recommended');
lcfa_assert_contains('Manual option', $markup, 'generate bundle step should mark the fallback path as manual');
lcfa_assert_contains('Write the config for me', $markup, 'generate bundle step should explain the direct workspace path');
lcfa_assert_contains('Download and place it yourself', $markup, 'generate bundle step should explain the manual download path');
lcfa_assert_contains('lcfa-checkbox lcfa-checkbox--card', $markup, 'backup checkbox should render inside a card-specific layout');

echo "PASS\n";
