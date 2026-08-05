<?php

declare(strict_types=1);

require_once __DIR__ . '/reflection-compat.php';

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('LCFA_VERSION', 'test-version');
define('WP_PLUGIN_DIR', '/tmp/lcfa-tests/wp-content/plugins');

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

function selected($selected, $current = true, bool $display = true): string {
    return $selected == $current ? ' selected="selected"' : '';
}

function checked($checked, $current = true, bool $display = true): string {
    return $checked == $current ? ' checked="checked"' : '';
}

function sanitize_key(string $value): string {
    return strtolower(preg_replace('/[^a-zA-Z0-9_\-]/', '', $value) ?? '');
}

function get_bloginfo(string $show = ''): string {
    return $show === 'version' ? '7.0.2' : '';
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

require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin = (new ReflectionClass('LCFA_Admin'))->newInstanceWithoutConstructor();
$render_method = lcfa_test_reflection_method('LCFA_Admin', 'render_streamlined_profile_step');

$settings = [
    'framework'           => 'picostrap',
    'site_mode'           => 'remote',
    'ai_tool'             => 'codex',
    'permission_profile'  => 'advanced_templates',
    'allow_file_fallback' => true,
];
$snapshot = [
    'detected_framework'      => 'unknown',
    'site_mode'               => 'remote',
    'current_theme_name'      => 'Twenty Twenty-Five',
    'current_theme_stylesheet'=> 'twentytwentyfive',
    'wordpress_version'       => '7.0.2',
    'picostrap_candidates'    => [],
    'picowind_candidates'     => [],
    'windpress_installed'     => false,
    'windpress_active'        => false,
];

ob_start();
$render_method->invoke($admin, $settings, $snapshot);
$markup = (string) ob_get_clean();

lcfa_assert_contains('Check your project settings', $markup, 'streamlined setup should use a direct, plain-language heading');
lcfa_assert_contains('class="lcfa-select-control"><select', $markup, 'every setup select should use the visible select control wrapper');
lcfa_assert_contains('data-lcfa-profile-framework', $markup, 'framework selection should update its explanation');
lcfa_assert_contains('installs and activates WindPress from WordPress.org first', $markup, 'Picowind should explain the automatic dependency order');
lcfa_assert_contains('Let the coding agent make changes', $markup, 'write permission should use plain language');
lcfa_assert_contains('keeps a backup so you can undo them', $markup, 'write permission should explain rollback without technical jargon');
lcfa_assert_not_contains('Allow guarded write tools', $markup, 'technical write-policy wording should not be shown in the streamlined setup');

echo "PASS\n";
