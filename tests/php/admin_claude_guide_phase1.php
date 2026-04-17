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

function esc_attr__(string $text, string $domain = ''): string {
    return $text;
}

function esc_url(string $value): string {
    return $value;
}

function checked($checked, $current = true, bool $display = true): string {
    return $checked == $current ? ' checked="checked"' : '';
}

function sanitize_html_class(string $value): string {
    return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?: '';
}

function admin_url(string $path = ''): string {
    return 'http://example.test/wp-admin/' . ltrim($path, '/');
}

function wp_nonce_field(string $action = '', string $name = '_wpnonce', bool $referer = true, bool $display = true): void {
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

final class LCFA_Environment {
    public function find_plugin_file_by_slug(string $slug): string {
        return '';
    }
}

require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin_reflection = new ReflectionClass('LCFA_Admin');
$admin = $admin_reflection->newInstanceWithoutConstructor();

$environment_property = new ReflectionProperty('LCFA_Admin', 'environment');
$environment_property->setValue($admin, new LCFA_Environment());

$method = new ReflectionMethod('LCFA_Admin', 'render_agent_connection_guide');

$mcp_bootstrap = [
    'common'  => [
        'filesystem_mode' => 'local-theme-access',
    ],
    'clients' => [
        'codex' => [
            'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio',
            'env'     => [
                'LCFA_REST_BASE=http://localhost:8887/wp-json/lcfa/v1/',
                'LCFA_MCP_TOKEN=test-token',
            ],
        ],
        'claude' => [
            'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --agent=claude',
            'env'     => [
                'LCFA_REST_BASE=http://localhost:8887/wp-json/lcfa/v1/',
                'LCFA_MCP_ENDPOINT=ws://127.0.0.1:7681',
                'LCFA_MCP_TOKEN=test-token',
                'LCFA_WP_ROOT=/Users/commander/Studio/consultala',
            ],
        ],
    ],
];

ob_start();
$method->invoke(
    $admin,
    $mcp_bootstrap,
    [
        'site_mode' => 'local',
        'claude_connection_target' => 'desktop_app',
    ],
    [
        'site_mode' => 'local',
    ],
    'claude',
    [
        'filesystem_mode' => 'local-theme-access',
        'local_bridge' => [
            'available' => true,
        ],
    ],
    true
);
$markup = (string) ob_get_clean();

lcfa_assert_contains('>Claude<', $markup, 'guide tabs should show Claude as the top-level client');
lcfa_assert_contains('Desktop App', $markup, 'Claude guide should expose the Desktop App mode');
lcfa_assert_contains('Command Line Interface', $markup, 'Claude guide should expose the CLI mode');
lcfa_assert_not_contains('>Claude Code<', $markup, 'guide tabs should not use Claude Code as the top-level client label');
lcfa_assert_contains('/Users/commander/Studio/consultala/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js', $markup, 'Claude Desktop guide should render an absolute MCP script path for Desktop App');
lcfa_assert_contains('Merge the JSON block under mcpServers inside your existing Claude Desktop config.', $markup, 'Claude Desktop guide should explain that the JSON must be merged into the existing config');
lcfa_assert_contains('Do not paste it as a second top-level JSON object or replace your preferences block.', $markup, 'Claude Desktop guide should warn against invalid top-level JSON merges');
lcfa_assert_contains('Open Claude Desktop config', $markup, 'Claude Desktop guide should render an explicit action checklist above the JSON snippet');
lcfa_assert_contains('If mcpServers already exists, paste only the livecanvas-forge entry inside it.', $markup, 'Claude Desktop guide should explain the copy path when mcpServers already exists');
lcfa_assert_contains('If mcpServers does not exist, paste the full mcpServers block from below.', $markup, 'Claude Desktop guide should explain the copy path when mcpServers is missing');
lcfa_assert_contains('Save the file and reopen Claude Desktop.', $markup, 'Claude Desktop guide should finish the checklist with the reopen step');

echo "PASS\n";
