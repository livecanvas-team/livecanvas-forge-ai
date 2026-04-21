<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');

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

function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): string {
    $markup = '<input type="hidden" name="' . $name . '" value="test-nonce">';

    if ($display) {
        echo $markup;
    }

    return $markup;
}

function sanitize_html_class(string $value): string {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?: '';
}

function lcfa_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Needle: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_not_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Unexpected needle: ' . $needle . PHP_EOL);
        exit(1);
    }
}

require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();
$render_method = new ReflectionMethod('LCFA_Admin', 'render_preflight_step');

$picostrap_snapshot = [
    'livecanvas_installed'       => true,
    'livecanvas_active'          => true,
    'livecanvas_license_active'  => true,
    'livecanvas_menu_slug'       => 'livecanvas',
    'detected_framework'         => 'picostrap',
    'windpress_installed'        => false,
    'windpress_active'           => false,
    'picostrap_candidates'       => [
        [
            'stylesheet' => 'picostrap5-child-base',
            'name'       => 'Picostrap 5 Child Base',
        ],
    ],
    'picowind_candidates'        => [],
];

ob_start();
$render_method->invoke($admin, $picostrap_snapshot);
$picostrap_output = (string) ob_get_clean();

lcfa_assert_contains('LiveCanvas plugin and license active', $picostrap_output, 'preflight should verify LiveCanvas and its license as one meaningful check');
lcfa_assert_contains('Detected LiveCanvas themes', $picostrap_output, 'preflight should summarize detected LiveCanvas theme families');
lcfa_assert_contains('picostrap5-child-base', $picostrap_output, 'preflight should list the detected Picostrap theme');
lcfa_assert_not_contains('LiveCanvas admin menu detected', $picostrap_output, 'preflight should stop showing the old admin-menu implementation check');
lcfa_assert_not_contains('WindPress active for Picowind', $picostrap_output, 'preflight should not show the WindPress requirement when no Picowind theme is detected');

$picowind_snapshot = $picostrap_snapshot;
$picowind_snapshot['detected_framework'] = 'picowind';
$picowind_snapshot['picowind_candidates'] = [
    [
        'stylesheet' => 'picowind-child',
        'name'       => 'Picowind Child',
    ],
];
$picowind_snapshot['windpress_installed'] = true;
$picowind_snapshot['windpress_active'] = false;

ob_start();
$render_method->invoke($admin, $picowind_snapshot);
$picowind_output = (string) ob_get_clean();

lcfa_assert_contains('picowind-child', $picowind_output, 'preflight should list the detected Picowind theme');
lcfa_assert_contains('WindPress active for Picowind', $picowind_output, 'preflight should make WindPress explicit when Picowind is detected');
lcfa_assert_contains('Picowind is installed, so WindPress must be installed and active before continuing.', $picowind_output, 'preflight should block Picowind without active WindPress');

echo "PASS\n";
