<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('WP_PLUGIN_DIR', '/Users/commander/Studio/consultala/wp-content/plugins');

function __(string $text, string $domain = ''): string {
    return $text;
}

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_false(bool $condition, string $message): void {
    if ($condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Needle: ' . $needle . PHP_EOL);
        exit(1);
    }
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

function sanitize_html_class(string $value): string {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?: '';
}

function plugins_url(string $path = '', string $plugin = ''): string {
    return 'http://example.test/wp-content/plugins/' . ltrim($path, '/');
}

final class LCFA_Environment {
    public function find_plugin_file_by_slug(string $slug): string {
        if ($slug === 'livecanvas') {
            return 'livecanvas/livecanvas.php';
        }

        return '';
    }
}

require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

$presenter = new LCFA_Admin_Hero_Presenter();

$hero = $presenter->build('connections', [
    'current_theme_name' => 'Picowind Child',
    'current_theme_stylesheet' => 'picowind-child',
    'current_theme_template' => 'picowind',
    'detected_framework' => 'picowind',
    'framework_slug' => 'daisyui-5',
    'livecanvas_active' => true,
    'windpress_active' => true,
    'acf_active' => false,
    'tangible_available' => true,
], [
    'site_mode' => 'local',
    'preferred_client' => 'codex',
]);

lcfa_assert_same('Connections', $hero['title'] ?? '', 'hero presenter should keep the approved tab title');
lcfa_assert_same('connections', $hero['tab'] ?? '', 'hero presenter should keep the current tab key');
lcfa_assert_true(count($hero['marks'] ?? []) >= 2, 'hero presenter should expose compact stack marks');
lcfa_assert_true(count($hero['chips'] ?? []) >= 4, 'hero presenter should expose compact stack chips');
lcfa_assert_same('daisyui-5', $hero['chips'][3]['value'] ?? '', 'hero presenter should surface editor config as a chip');
lcfa_assert_true(count($hero['details'] ?? []) >= 3, 'hero presenter should move technical facts into details');
lcfa_assert_false(in_array('LiveCanvas', array_column((array) ($hero['marks'] ?? []), 'label'), true), 'hero presenter should stop repeating a dedicated LiveCanvas mark');

$setup_hero = $presenter->build('setup', [
    'current_theme_name' => 'Picowind Child',
    'current_theme_stylesheet' => 'picowind-child',
    'current_theme_template' => 'picowind',
    'detected_framework' => 'picowind',
    'framework_slug' => 'daisyui-5',
    'livecanvas_active' => true,
    'windpress_active' => true,
    'acf_active' => false,
    'tangible_available' => true,
], [
    'site_mode' => 'local',
    'preferred_client' => 'codex',
]);

lcfa_assert_same('Bridge Setup', $setup_hero['title'] ?? '', 'setup hero should keep the approved tab title');
lcfa_assert_same('LiveCanvas AI Bridge prepares the site, verifies the stack, and gets your coding agent ready for guided page changes.', $setup_hero['subtitle'] ?? '', 'setup hero subtitle should explain what AI Bridge actually does');

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();

$environment_property = new ReflectionProperty('LCFA_Admin', 'environment');
$environment_property->setValue($admin, new LCFA_Environment());

$hero_presenter_property = new ReflectionProperty('LCFA_Admin', 'admin_hero_presenter');
$hero_presenter_property->setValue($admin, $presenter);

$render_method = new ReflectionMethod('LCFA_Admin', 'render_page_header');

ob_start();
$render_method->invoke($admin, 'connections', [
    'current_theme_name' => 'Picowind Child',
    'current_theme_stylesheet' => 'picowind-child',
    'current_theme_template' => 'picowind',
    'detected_framework' => 'picowind',
    'framework_slug' => 'daisyui-5',
    'livecanvas_active' => true,
    'windpress_active' => true,
    'acf_active' => false,
    'tangible_available' => true,
], [
    'site_mode' => 'local',
    'preferred_client' => 'codex',
]);
$output = (string) ob_get_clean();

lcfa_assert_contains('lcfa-hero-main', $output, 'hero should expose a dedicated main grid');
lcfa_assert_contains('lcfa-hero-stack', $output, 'hero should expose a compact logo row');
lcfa_assert_contains('lcfa-hero-chip', $output, 'hero should render compact chips');
lcfa_assert_contains('lcfa-hero-details', $output, 'hero should render inline details');
lcfa_assert_true(strpos($output, 'lcfa-hero-mark-label') === false, 'hero marks should render as icon-only badges without visible labels');
lcfa_assert_contains('aria-label="Details"', $output, 'hero details toggle should stay accessible after switching to an icon-only control');
lcfa_assert_true(strpos($output, 'Stack snapshot') === false, 'hero should stop rendering stack snapshot copy');
lcfa_assert_contains('lcfa-kicker-brand', $output, 'hero kicker should render the LiveCanvas brand mark');
lcfa_assert_contains('lcfa-logo-livecanvas', $output, 'hero kicker should use the full LiveCanvas logo');
lcfa_assert_false(strpos($output, 'lcfa-logo-livecanvas-micro') !== false, 'hero stack should stop rendering the separate LiveCanvas micro logo');

echo "PASS\n";
