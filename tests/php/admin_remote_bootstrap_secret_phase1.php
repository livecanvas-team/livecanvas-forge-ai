<?php

declare(strict_types=1);

require_once __DIR__ . '/reflection-compat.php';

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'https://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('LCFA_VERSION', '0.2.0-beta.2');
define('LCFA_MCP_PACKAGE_SPEC', '@livecanvas/ai-bridge-mcp@0.2.0-beta.2');
define('WP_PLUGIN_DIR', '/tmp/lcfa-tests/wp-content/plugins');

function __(string $text, string $domain = ''): string {
    return $text;
}

function trailingslashit(string $value): string {
    return rtrim($value, "/\\") . '/';
}

function untrailingslashit(string $value): string {
    return rtrim($value, "/\\");
}

function home_url(string $path = '/'): string {
    return 'https://remote.example' . ($path === '/' ? '/' : '/' . ltrim($path, '/'));
}

function rest_url(string $path = ''): string {
    return 'https://remote.example/wp-json/' . ltrim($path, '/');
}

final class LCFA_Settings {
    public static function get_mcp_endpoint(): string {
        return 'wss://remote.example/mcp';
    }

    public static function get_site_fingerprint(): string {
        return 'remote-fingerprint';
    }
}

final class LCFA_Environment {
    public function find_plugin_file_by_slug(string $slug): string {
        return '';
    }
}

function lcfa_remote_bootstrap_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require LCFA_DIR . 'includes/class-lcfa-admin-hero-presenter.php';
require LCFA_DIR . 'includes/class-lcfa-admin.php';

$reflection = new ReflectionClass('LCFA_Admin');
$admin = $reflection->newInstanceWithoutConstructor();
$method = lcfa_test_reflection_method('LCFA_Admin', 'get_lightweight_bootstrap_payload');

$payload = $method->invoke($admin, [
    'mcp_token' => 'must-not-leak',
    'mcp_server_command' => 'node legacy-bridge.js',
    'transport' => 'rest',
], [
    'site_mode' => 'remote',
    'detected_framework' => 'picowind',
    'current_theme_stylesheet' => 'picowind-child',
]);

$encoded = json_encode($payload, JSON_UNESCAPED_SLASHES) ?: '';
lcfa_remote_bootstrap_assert(strpos($encoded, 'must-not-leak') === false, 'remote admin bootstrap must not expose the legacy MCP token');
lcfa_remote_bootstrap_assert(strpos($encoded, 'LCFA_MCP_TOKEN=') === false, 'remote admin bootstrap must not generate a legacy token environment variable');
lcfa_remote_bootstrap_assert(strpos($encoded, 'node legacy-bridge.js') === false, 'remote admin bootstrap must not reuse the local bridge command');

foreach (['codex', 'opencode', 'claude', 'cursor'] as $client) {
    $configuration = (array) ($payload['clients'][$client] ?? []);
    $environment = (array) ($configuration['env'] ?? []);

    lcfa_remote_bootstrap_assert(($configuration['command'] ?? '') === 'npx -y @livecanvas/ai-bridge-mcp@0.2.0-beta.2', $client . ' should use the pinned secure MCP package');
    lcfa_remote_bootstrap_assert(in_array('LCFA_AGENT=' . $client, $environment, true), $client . ' should identify itself during pairing');
    lcfa_remote_bootstrap_assert(in_array('LCFA_PAIRING_SCOPES=read,preview', $environment, true), $client . ' should default to read and preview scopes');
}

echo "PASS\n";
