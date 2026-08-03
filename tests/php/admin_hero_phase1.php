<?php

declare(strict_types=1);

require_once __DIR__ . '/reflection-compat.php';

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
    'stack_capabilities' => [
        'status' => 'supported',
        'profile_version' => '2026.08.1',
        'missing_capabilities' => [],
    ],
], [
    'site_mode' => 'local',
    'preferred_client' => 'codex',
]);

lcfa_assert_same('Connect a Coding Agent', $hero['title'] ?? '', 'connections hero should name the user goal');
lcfa_assert_same('connections', $hero['tab'] ?? '', 'hero presenter should keep the current tab key');
lcfa_assert_true(count($hero['marks'] ?? []) >= 2, 'hero presenter should expose compact stack marks');
lcfa_assert_true(count($hero['chips'] ?? []) >= 4, 'hero presenter should expose compact stack chips');
lcfa_assert_same('daisyui-5', $hero['chips'][3]['value'] ?? '', 'hero presenter should surface editor config as a chip');
lcfa_assert_true(in_array('Compatibility', array_column((array) ($hero['chips'] ?? []), 'label'), true), 'hero presenter should expose stack compatibility as a compact chip');
lcfa_assert_true(in_array('2026.08.1', array_column((array) ($hero['details'] ?? []), 'value'), true), 'hero details should expose the active compatibility profile');
lcfa_assert_true(count($hero['details'] ?? []) >= 3, 'hero presenter should move technical facts into details');
lcfa_assert_false(in_array('LiveCanvas', array_column((array) ($hero['marks'] ?? []), 'label'), true), 'hero presenter should stop repeating a dedicated LiveCanvas mark');

$picostrap_hero = $presenter->build('connections', [
    'current_theme_name' => 'Picostrap Child',
    'current_theme_stylesheet' => 'picostrap5-child-base',
    'current_theme_template' => 'picostrap5',
    'detected_framework' => 'picostrap',
    'framework_slug' => 'bootstrap-5.3',
    'livecanvas_active' => true,
    'windpress_installed' => true,
    'windpress_active' => true,
    'acf_active' => false,
    'tangible_available' => true,
], [
    'site_mode' => 'local',
    'preferred_client' => 'opencode',
]);
$picostrap_marks = array_column((array) ($picostrap_hero['marks'] ?? []), 'label');
lcfa_assert_true(in_array('Bootstrap', $picostrap_marks, true), 'Picostrap hero should identify Bootstrap as the active frontend stack.');
lcfa_assert_false(in_array('WindPress', $picostrap_marks, true), 'Picostrap hero should not present WindPress as part of the active frontend stack.');

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

lcfa_assert_same('Get Started', $setup_hero['title'] ?? '', 'setup hero should use the primary onboarding label');
lcfa_assert_same('Check the LiveCanvas stack, confirm this project, then connect a coding agent.', $setup_hero['subtitle'] ?? '', 'setup hero subtitle should state the three onboarding stages');

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();

$environment_property = lcfa_test_reflection_property('LCFA_Admin', 'environment');
$environment_property->setValue($admin, new LCFA_Environment());

$hero_presenter_property = lcfa_test_reflection_property('LCFA_Admin', 'admin_hero_presenter');
$hero_presenter_property->setValue($admin, $presenter);

$render_method = lcfa_test_reflection_method('LCFA_Admin', 'render_page_header');

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
lcfa_assert_contains('lcfa-hero-chip', $output, 'hero should render compact project facts');
lcfa_assert_contains('aria-label="Project context"', $output, 'hero project facts should be grouped semantically');
lcfa_assert_contains('<dt>Mode</dt>', $output, 'hero project facts should expose labels instead of button-like text');
lcfa_assert_contains('<dd>local</dd>', $output, 'hero project facts should expose the current value separately');
lcfa_assert_false(strpos($output, '<span class="lcfa-hero-chip ') !== false, 'hero project facts should not use pill markup');
lcfa_assert_contains('lcfa-hero-details', $output, 'hero should render inline details');
lcfa_assert_true(strpos($output, 'lcfa-hero-mark-label') === false, 'hero marks should render as icon-only badges without visible labels');
lcfa_assert_contains('aria-label="Details"', $output, 'hero details toggle should stay accessible after switching to an icon-only control');
lcfa_assert_true(strpos($output, 'Stack snapshot') === false, 'hero should stop rendering stack snapshot copy');
lcfa_assert_contains('lcfa-product-id__brand', $output, 'product identity should render the LiveCanvas brand mark');
lcfa_assert_contains('lcfa-logo-livecanvas', $output, 'hero kicker should use the full LiveCanvas logo');
lcfa_assert_false(strpos($output, 'lcfa-logo-livecanvas-micro') !== false, 'hero stack should stop rendering the separate LiveCanvas micro logo');

$admin_v2_css = (string) file_get_contents(LCFA_DIR . 'assets/admin-v2.css');
lcfa_assert_contains('.lcfa-hero-details-panel:not([open]) > .lcfa-hero-details', $admin_v2_css, 'closed hero details should not occupy layout space');
lcfa_assert_contains('.lcfa-hero-details-panel[open]', $admin_v2_css, 'open hero details should have an explicit responsive width');
lcfa_assert_contains('.lcfa-connection-summary', $admin_v2_css, 'connection state should have a semantic summary treatment');
lcfa_assert_contains('.lcfa-visual-help__flow', $admin_v2_css, 'coding-agent guidance should use a connected process treatment');
lcfa_assert_contains('.lcfa-agent-setup-details', $admin_v2_css, 'manual technical setup should remain progressively disclosed');

echo "PASS\n";
