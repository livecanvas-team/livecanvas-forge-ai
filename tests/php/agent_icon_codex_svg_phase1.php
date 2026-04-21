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

function esc_attr(string $value): string {
    return $value;
}

function esc_html(string $value): string {
    return $value;
}

function esc_html__(string $text, string $domain = ''): string {
    return $text;
}

function esc_url(string $value): string {
    return $value;
}

function plugins_url(string $path = '', string $plugin = ''): string {
    return 'http://example.test/wp-content/plugins/' . ltrim($path, '/');
}

final class LCFA_Environment {
    public function find_plugin_file_by_slug(string $slug): ?string {
        return $slug === 'livecanvas-forge-ai' ? 'livecanvas-forge-ai/livecanvas-forge-ai.php' : null;
    }
}

final class LCFA_Installer {}
final class LCFA_Inventory {}
final class LCFA_Theme_Files_Bridge {}
final class LCFA_Connection_Tester {}
final class LCFA_Remote_Client {}
final class LCFA_Context_Builder {}
final class LCFA_Connection_Onboarding {}
final class LCFA_Connection_Wizard_Presenter {}
final class LCFA_Admin_Hero_Presenter {}
final class LCFA_Command_Deck {}
final class LCFA_Prompt_Suggester {}
final class LCFA_Genesis_Planner {}

require LCFA_DIR . 'includes/class-lcfa-admin.php';

function lcfa_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();

$environment_property = new ReflectionProperty('LCFA_Admin', 'environment');
$environment_property->setValue($admin, new LCFA_Environment());

$method = new ReflectionMethod('LCFA_Admin', 'get_agent_icon_url');

$expectations = [
    'codex' => 'assets/agent-icons/codex-color.svg',
    'opencode' => 'assets/agent-icons/opencode.svg',
    'cursor' => 'assets/agent-icons/cursor.svg',
    'claude-code' => 'assets/agent-icons/claude-color.svg',
];

foreach ($expectations as $client => $expected_path) {
    $url = (string) $method->invoke($admin, $client);
    lcfa_assert_contains($expected_path, $url, sprintf('%s should resolve to the SVG icon asset', $client));
}

echo "PASS\n";
