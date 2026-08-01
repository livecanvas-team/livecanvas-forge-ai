<?php

declare(strict_types=1);

define('ABSPATH', '/tmp/lcfa-oauth-bundle/');

function __(string $text, string $domain = ''): string {
    return $text;
}

function sanitize_key(string $value): string {
    return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($value));
}

function esc_url_raw(string $value): string {
    return trim($value);
}

function wp_parse_url(string $url, int $component = -1) {
    return parse_url($url, $component);
}

function wp_normalize_path(string $path): string {
    return str_replace('\\', '/', $path);
}

function trailingslashit(string $value): string {
    return rtrim($value, '/\\') . '/';
}

function untrailingslashit(string $value): string {
    return rtrim($value, '/\\');
}

function wp_json_encode($value, int $flags = 0, int $depth = 512): string {
    return json_encode($value, $flags, $depth) ?: '';
}

function oauth_bundle_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function oauth_bundle_assert_contains(string $needle, string $haystack, string $message): void {
    oauth_bundle_assert_true(strpos($haystack, $needle) !== false, $message . ' Missing: ' . $needle);
}

function oauth_bundle_assert_not_contains(string $needle, string $haystack, string $message): void {
    oauth_bundle_assert_true(strpos($haystack, $needle) === false, $message . ' Unexpected: ' . $needle);
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-connection-bundle-builder.php';

$url = 'https://example.com/wp-json/livecanvas-forge-ai/mcp';
$builder = new LCFA_Connection_Bundle_Builder();
$bundle = $builder->build([
    'client' => 'codex',
    'mode' => 'remote',
    'workspace_root' => '',
    'common' => [
        'connection_strategy' => 'oauth-direct',
        'mcp_url' => $url,
        'oauth_resource' => $url,
        'site_fingerprint' => 'a1b2c3d4e5f60708',
        'remote_site_url' => 'https://example.com/',
    ],
    'client_payload' => [
        'url' => $url,
        'command' => '',
        'env' => [],
    ],
]);

oauth_bundle_assert_true(($bundle['server_name'] ?? '') === 'livecanvas-example-com-a1b2c3d4', 'Codex server name should include host and site fingerprint');
oauth_bundle_assert_true(($bundle['connection_strategy'] ?? '') === 'oauth-direct', 'bundle should preserve Direct OAuth strategy');
oauth_bundle_assert_true(($bundle['environment'] ?? []) === [], 'Direct OAuth bundle must not include environment credentials');
oauth_bundle_assert_true(($bundle['command'] ?? []) === [], 'Direct OAuth bundle must not start an npm proxy');

$toml = (string) ($bundle['codex_config_snippet'] ?? '');
oauth_bundle_assert_contains('[mcp_servers.livecanvas-example-com-a1b2c3d4]', $toml, 'TOML should use the site-specific server name');
oauth_bundle_assert_contains('url = "' . $url . '"', $toml, 'TOML should contain only the remote MCP URL');
oauth_bundle_assert_contains('oauth_resource = "' . $url . '"', $toml, 'TOML should pin the OAuth resource audience');
oauth_bundle_assert_contains('default_tools_approval_mode = "writes"', $toml, 'Direct OAuth should allow read-only tools and prompt before writes');
oauth_bundle_assert_not_contains('command =', $toml, 'Direct OAuth TOML must not start an executable');
oauth_bundle_assert_not_contains('[mcp_servers.livecanvas-example-com-a1b2c3d4.env]', $toml, 'Direct OAuth TOML must not contain an env table');
oauth_bundle_assert_not_contains('WP_API_PASSWORD', $toml, 'Direct OAuth TOML must not contain a WordPress credential');
oauth_bundle_assert_not_contains('@livecanvas/ai-bridge-mcp', $toml, 'Direct OAuth TOML must not require the pairing proxy package');

$shortcut = (string) ($bundle['shortcut_command'] ?? '');
oauth_bundle_assert_contains('mcp add livecanvas-example-com-a1b2c3d4 --url', $shortcut, 'Codex shortcut should register a URL server');
oauth_bundle_assert_contains('--oauth-resource', $shortcut, 'Codex shortcut should pin the OAuth resource');
oauth_bundle_assert_contains('mcp login --scopes mcp livecanvas-example-com-a1b2c3d4', $shortcut, 'Codex shortcut should explain the one-time OAuth login');
oauth_bundle_assert_true(($bundle['agent_start_tool'] ?? '') === 'livecanvas-forge-ai/get-connection-handoff', 'Direct OAuth should use the namespaced handoff ability');

echo "PASS\n";
