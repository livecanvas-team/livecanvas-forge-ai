<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_URL', 'http://example.test/wp-content/plugins/livecanvas-forge-ai/');
define('WP_CONTENT_DIR', '/Users/commander/Studio/consultala/wp-content');
define('WP_PLUGIN_DIR', '/Users/commander/Studio/consultala/wp-content/plugins');

function __(string $text, string $domain = ''): string {
    return $text;
}

function sanitize_key(string $value): string {
    $value = strtolower($value);

    return (string) preg_replace('/[^a-z0-9_\-]/', '', $value);
}

function sanitize_text_field($value): string {
    return trim((string) $value);
}

function sanitize_textarea_field($value): string {
    return trim((string) $value);
}

function sanitize_file_name(string $value): string {
    return (string) preg_replace('/[^A-Za-z0-9\.\-_]/', '-', $value);
}

function trailingslashit(string $value): string {
    return rtrim($value, '/\\') . '/';
}

function untrailingslashit(string $value): string {
    return rtrim($value, '/\\');
}

function wp_normalize_path(string $value): string {
    return str_replace('\\', '/', $value);
}

function wp_json_encode($value, int $flags = 0, int $depth = 512): string {
    return json_encode($value, $flags, $depth) ?: '';
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

require LCFA_DIR . 'includes/class-lcfa-connection-bundle-builder.php';

$builder = new LCFA_Connection_Bundle_Builder();

$desktop_bundle = $builder->build([
    'client'                    => 'claude',
    'claude_connection_target'  => 'desktop_app',
    'mode'                      => 'local',
    'workspace_root'            => '/Users/commander/Studio/consultala',
    'common'                    => [
        'rest_base' => 'http://localhost:8887/wp-json/lcfa/v1/',
        'mcp_token' => 'desktop-token',
        'wp_root'   => '/wordpress',
    ],
    'client_payload' => [
        'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --agent=claude',
        'env'     => [
            'LCFA_REST_BASE=http://localhost:8887/wp-json/lcfa/v1/',
            'LCFA_MCP_ENDPOINT=ws://127.0.0.1:7681',
            'LCFA_MCP_TOKEN=desktop-token',
            'LCFA_WP_ROOT=/wordpress',
        ],
    ],
]);

lcfa_assert_same('claude', $desktop_bundle['client'] ?? '', 'Claude desktop bundle should preserve the canonical Claude client');
lcfa_assert_same('livecanvas-forge.claude-desktop.json', $desktop_bundle['download_files'][0]['name'] ?? '', 'Desktop local bundle should download a Claude Desktop JSON config');
lcfa_assert_same('Claude Desktop config', $desktop_bundle['shortcut_title'] ?? '', 'Desktop local bundle should advertise the Desktop config block');
lcfa_assert_same('/Users/commander/Studio/consultala/livecanvas-forge.claude-desktop.json', $desktop_bundle['workspace_files'][0]['path'] ?? '', 'Desktop local bundle should expose a Claude Desktop config file in the workspace');
lcfa_assert_true(strpos((string) ($desktop_bundle['download_files'][0]['content'] ?? ''), '"mcpServers"') !== false, 'Desktop local bundle should serialize a Claude Desktop config JSON block');
lcfa_assert_true(strpos((string) ($desktop_bundle['copy_command_string'] ?? ''), '"type": "stdio"') !== false, 'Desktop local bundle should prefer copying the Claude Desktop JSON snippet');
lcfa_assert_same('get_connection_handoff', $desktop_bundle['agent_start_tool'] ?? '', 'Desktop local bundle should start with the local connection handoff tool');
lcfa_assert_same('get_agent_handoff_package', $desktop_bundle['handoff_package_tool'] ?? '', 'Desktop local bundle should expose the full handoff package tool');
lcfa_assert_true(strpos((string) ($desktop_bundle['agent_start_prompt'] ?? ''), 'get_connection_handoff') !== false, 'Desktop local bundle should include the lightweight first handoff prompt');
lcfa_assert_true(strpos((string) ($desktop_bundle['agent_start_prompt'] ?? ''), 'get_agent_handoff_package') !== false, 'Desktop local bundle should mention the full handoff package follow-up');

$cli_bundle = $builder->build([
    'client'                    => 'claude',
    'claude_connection_target'  => 'cli',
    'mode'                      => 'local',
    'workspace_root'            => '/Users/commander/Studio/consultala',
    'common'                    => [
        'rest_base' => 'http://localhost:8887/wp-json/lcfa/v1/',
        'mcp_token' => 'cli-token',
        'wp_root'   => '/wordpress',
    ],
    'client_payload' => [
        'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --agent=claude',
        'env'     => [
            'LCFA_REST_BASE=http://localhost:8887/wp-json/lcfa/v1/',
            'LCFA_MCP_ENDPOINT=ws://127.0.0.1:7681',
            'LCFA_MCP_TOKEN=cli-token',
            'LCFA_WP_ROOT=/wordpress',
        ],
    ],
]);

lcfa_assert_same('livecanvas-forge.claude-cli.sh', $cli_bundle['download_files'][0]['name'] ?? '', 'CLI local bundle should download a Claude CLI shell helper');
lcfa_assert_same('Claude CLI shortcut', $cli_bundle['shortcut_title'] ?? '', 'CLI local bundle should advertise the CLI shortcut explicitly');
lcfa_assert_same('/Users/commander/Studio/consultala/livecanvas-forge.claude-cli.sh', $cli_bundle['workspace_files'][0]['path'] ?? '', 'CLI local bundle should expose a Claude CLI helper in the workspace');
lcfa_assert_true(strpos((string) ($cli_bundle['shortcut_command'] ?? ''), 'claude mcp add --transport stdio') !== false, 'CLI bundle should expose the Claude CLI registration shortcut');
lcfa_assert_true(strpos((string) ($cli_bundle['download_files'][0]['content'] ?? ''), 'get_connection_handoff') !== false, 'CLI helper should print the first connection handoff prompt for the agent operator');

$remote_desktop_bundle = $builder->build([
    'client'                    => 'claude',
    'claude_connection_target'  => 'desktop_app',
    'mode'                      => 'remote',
    'workspace_root'            => '',
    'common'                    => [
        'rest_base' => 'https://example.com/wp-json/lcfa/v1/',
        'mcp_token' => 'remote-token',
        'wp_root'   => '/wordpress',
    ],
    'client_payload' => [
        'command' => 'node wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js --transport=stdio --agent=claude',
        'env'     => [
            'LCFA_REST_BASE=https://example.com/wp-json/lcfa/v1/',
            'LCFA_MCP_TOKEN=remote-token',
        ],
    ],
]);

lcfa_assert_same('livecanvas-forge.claude-desktop.txt', $remote_desktop_bundle['download_files'][0]['name'] ?? '', 'Remote Claude Desktop bundle should ship a reference file, not a ready-made JSON config');
lcfa_assert_same('Claude Desktop reference', $remote_desktop_bundle['shortcut_title'] ?? '', 'Remote Claude Desktop bundle should expose a reference block title');
lcfa_assert_true(strpos((string) ($remote_desktop_bundle['download_files'][0]['content'] ?? ''), '"mcpServers"') === false, 'Remote Claude Desktop copy should stay conservative and avoid a ready-to-paste JSON config');
lcfa_assert_true(strpos((string) ($remote_desktop_bundle['download_files'][0]['content'] ?? ''), 'localhost') === false, 'Remote Claude Desktop copy should not suggest localhost values');
lcfa_assert_true(strpos((string) ($remote_desktop_bundle['download_files'][0]['content'] ?? ''), 'First agent prompt') !== false, 'Remote Claude reference should include the first handoff prompt');

echo "PASS\n";
