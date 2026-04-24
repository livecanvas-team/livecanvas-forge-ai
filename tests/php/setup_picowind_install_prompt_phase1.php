<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('LCFA_VERSION', 'test-version');
define('WP_PLUGIN_DIR', '/tmp/lcfa-tests/wp-content/plugins');

final class LCFA_Environment {
    public function find_plugin_file_by_slug(string $slug): string {
        return '';
    }
}

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

function esc_attr__(string $text, string $domain = ''): string {
    return $text;
}

function esc_url(string $value): string {
    return $value;
}

function checked($checked, $current = true, bool $display = true): string {
    return $checked == $current ? ' checked="checked"' : '';
}

function admin_url(string $path = ''): string {
    return 'http://example.test/wp-admin/' . ltrim($path, '/');
}

function plugins_url(string $path = '', string $plugin = ''): string {
    return 'http://example.test/wp-content/plugins/' . ltrim($path, '/');
}

function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): string {
    $markup = '<input type="hidden" name="' . $name . '" value="test-nonce">';

    if ($display) {
        echo $markup;
    }

    return $markup;
}

function wp_list_pluck(array $list, string $field): array {
    return array_map(static fn(array $item): string => (string) ($item[$field] ?? ''), $list);
}

function sanitize_html_class(string $value): string {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?: '';
}

function lcfa_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin = (new ReflectionClass('LCFA_Admin'))->newInstanceWithoutConstructor();
(new ReflectionProperty('LCFA_Admin', 'environment'))->setValue($admin, new LCFA_Environment());
$render_method = new ReflectionMethod('LCFA_Admin', 'render_framework_step');

$snapshot = [
    'detected_framework'   => 'picostrap',
    'picostrap_candidates' => [
        [
            'stylesheet' => 'picostrap5-child-base',
            'name'       => 'Picostrap 5 Child Base',
        ],
    ],
    'picowind_candidates'  => [],
];

ob_start();
$render_method->invoke($admin, ['framework' => ''], $snapshot);
$markup = (string) ob_get_clean();

lcfa_assert_contains(
    'Selecting Picowind installs the latest Picowind release from GitHub before the wizard switches the stack.',
    $markup,
    'framework step should explain that missing Picowind can be installed from the latest GitHub release'
);
lcfa_assert_contains(
    'https://github.com/livecanvas-team/picowind/releases/latest',
    $markup,
    'framework step should expose the latest Picowind release source'
);

echo "PASS\n";
