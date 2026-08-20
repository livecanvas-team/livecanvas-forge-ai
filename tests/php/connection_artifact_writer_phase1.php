<?php

declare(strict_types=1);

error_reporting(E_ALL);

$tmp = sys_get_temp_dir() . '/lcfa-artifact-writer-' . getmypid() . '-' . bin2hex(random_bytes(3));
$workspace_root = $tmp . '/project';
$wordpress_root = $workspace_root . '/app/public';
@mkdir($wordpress_root . '/wp-content', 0777, true);

define('ABSPATH', $wordpress_root . '/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');

function __(string $text, string $domain = ''): string {
    return $text;
}

function sanitize_key(string $value): string {
    return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($value));
}

function wp_normalize_path(string $value): string {
    return str_replace('\\', '/', $value);
}

function wp_json_encode($value, int $flags = 0): string {
    return (string) json_encode($value, $flags);
}

function wp_mkdir_p(string $path): bool {
    return @mkdir($path, 0755, true) || is_dir($path);
}

function lcfa_writer_assert_true(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function lcfa_writer_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

require LCFA_DIR . 'includes/class-lcfa-connection-artifact-writer.php';

$writer = new LCFA_Connection_Artifact_Writer();

$cursor_path = $workspace_root . '/.cursor/mcp.json';
@mkdir(dirname($cursor_path), 0777, true);
file_put_contents($cursor_path, json_encode([
    'projectSetting' => 'keep-me',
    'mcpServers' => [
        'sentinel' => [
            'type' => 'stdio',
            'command' => '/usr/bin/true',
        ],
        'livecanvas-forge' => [
            'command' => '/old/path/node',
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$cursor_generated = json_encode([
    'mcpServers' => [
        'livecanvas-forge' => [
            'type' => 'stdio',
            'command' => '/opt/homebrew/bin/node',
            'args' => [$wordpress_root . '/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js'],
            'env' => [
                'LCFA_WP_ROOT' => $wordpress_root,
                'LCFA_AGENT' => 'cursor',
            ],
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

$cursor_result = $writer->write([
    'path' => $cursor_path,
    'type' => 'json',
    'client' => 'cursor',
    'server_name' => 'livecanvas-forge',
    'content' => $cursor_generated,
], $workspace_root, $wordpress_root);

lcfa_writer_assert_true(!empty($cursor_result['ok']), 'Cursor config should be written successfully');
lcfa_writer_assert_true(is_file((string) ($cursor_result['backup_path'] ?? '')), 'Cursor merge should create an automatic backup');
lcfa_writer_assert_same('0600', $cursor_result['mode'] ?? '', 'Cursor config should be written with mode 0600');
lcfa_writer_assert_same('0600', substr(sprintf('%o', (int) fileperms((string) $cursor_result['backup_path'])), -4), 'Cursor backup should use mode 0600');
$cursor_merged = json_decode((string) file_get_contents($cursor_path), true);
lcfa_writer_assert_same('keep-me', $cursor_merged['projectSetting'] ?? '', 'Cursor merge should preserve unrelated top-level settings');
lcfa_writer_assert_same('/usr/bin/true', $cursor_merged['mcpServers']['sentinel']['command'] ?? '', 'Cursor merge should preserve unrelated MCP servers');
lcfa_writer_assert_same('/opt/homebrew/bin/node', $cursor_merged['mcpServers']['livecanvas-forge']['command'] ?? '', 'Cursor merge should replace only livecanvas-forge');
lcfa_writer_assert_true(strpos((string) file_get_contents($cursor_path), 'LCFA_MCP_TOKEN') === false, 'Cursor config should not contain a static token');

$opencode_path = $workspace_root . '/opencode.json';
file_put_contents($opencode_path, json_encode([
    'theme' => 'sentinel-theme',
    'mcp' => [
        'sentinel' => [
            'type' => 'local',
            'command' => ['/usr/bin/true'],
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
$opencode_generated = json_encode([
    '$schema' => 'https://opencode.ai/config.json',
    'mcp' => [
        'livecanvas-forge' => [
            'type' => 'local',
            'command' => ['/opt/homebrew/bin/node', $wordpress_root . '/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js'],
            'enabled' => true,
            'environment' => ['LCFA_WP_ROOT' => $wordpress_root],
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
$opencode_result = $writer->write([
    'path' => $opencode_path,
    'type' => 'json',
    'client' => 'opencode',
    'server_name' => 'livecanvas-forge',
    'content' => $opencode_generated,
], $workspace_root, $wordpress_root);

lcfa_writer_assert_true(!empty($opencode_result['ok']), 'OpenCode config should be safely merged at the agent project root');
$opencode_merged = json_decode((string) file_get_contents($opencode_path), true);
lcfa_writer_assert_same('sentinel-theme', $opencode_merged['theme'] ?? '', 'OpenCode merge should preserve unrelated settings');
lcfa_writer_assert_same('/usr/bin/true', $opencode_merged['mcp']['sentinel']['command'][0] ?? '', 'OpenCode merge should preserve unrelated MCP servers');
lcfa_writer_assert_true(!empty($opencode_merged['mcp']['livecanvas-forge']['enabled']), 'OpenCode merge should add livecanvas-forge');

$public_opencode = $writer->write([
    'path' => $wordpress_root . '/opencode.json',
    'type' => 'json',
    'client' => 'opencode',
    'server_name' => 'livecanvas-forge',
    'content' => $opencode_generated,
], $workspace_root, $wordpress_root);
lcfa_writer_assert_true(empty($public_opencode['ok']), 'OpenCode config inside the public WordPress root must be blocked');
lcfa_writer_assert_same('public_opencode_config', $public_opencode['code'] ?? '', 'public OpenCode rejection should expose the security reason');
lcfa_writer_assert_true(!is_file($wordpress_root . '/opencode.json'), 'blocked public OpenCode config should not be created');

$relative_result = $writer->write([
    'path' => 'relative-project/.cursor/mcp.json',
    'type' => 'json',
    'client' => 'cursor',
    'server_name' => 'livecanvas-forge',
    'content' => $cursor_generated,
], 'relative-project', $wordpress_root);
lcfa_writer_assert_true(empty($relative_result['ok']), 'relative project paths must be rejected');
lcfa_writer_assert_same('path_not_absolute', $relative_result['code'] ?? '', 'relative path rejection should expose the security reason');

$outside_root = $tmp . '/outside';
@mkdir($outside_root, 0777, true);
$escape_link = $workspace_root . '/escape-link';
if (@symlink($outside_root, $escape_link)) {
    $symlink_escape = $writer->write([
        'path' => $escape_link . '/mcp.json',
        'type' => 'json',
        'client' => 'cursor',
        'server_name' => 'livecanvas-forge',
        'content' => $cursor_generated,
    ], $workspace_root, $wordpress_root);
    lcfa_writer_assert_true(empty($symlink_escape['ok']), 'a symlink must not escape the configured workspace root');
    lcfa_writer_assert_same('outside_workspace', $symlink_escape['code'] ?? '', 'symlink escape rejection should expose the containment reason');
    lcfa_writer_assert_true(!is_file($outside_root . '/mcp.json'), 'a rejected symlink escape must not write outside the workspace');

    $public_link = $workspace_root . '/public-link';
    if (@symlink($wordpress_root, $public_link)) {
        $public_alias = $writer->write([
            'path' => $public_link . '/opencode.json',
            'type' => 'json',
            'client' => 'opencode',
            'server_name' => 'livecanvas-forge',
            'content' => $opencode_generated,
        ], $workspace_root, $wordpress_root);
        lcfa_writer_assert_true(empty($public_alias['ok']), 'a symlink alias must not bypass the public WordPress root guard');
        lcfa_writer_assert_same('public_opencode_config', $public_alias['code'] ?? '', 'public-root alias rejection should expose the security reason');
    }
}

$invalid_path = $workspace_root . '/invalid.json';
file_put_contents($invalid_path, "{invalid-json\n");
$invalid_hash = hash_file('sha256', $invalid_path);
$invalid_result = $writer->write([
    'path' => $invalid_path,
    'type' => 'json',
    'client' => 'cursor',
    'server_name' => 'livecanvas-forge',
    'content' => $cursor_generated,
], $workspace_root, $wordpress_root);
lcfa_writer_assert_true(empty($invalid_result['ok']), 'invalid existing JSON should stop the merge');
lcfa_writer_assert_same('existing_json_invalid', $invalid_result['code'] ?? '', 'invalid JSON should expose a specific error');
lcfa_writer_assert_same($invalid_hash, hash_file('sha256', $invalid_path), 'failed JSON merge should leave the original file unchanged');

$codex_path = $workspace_root . '/.codex/config.toml';
@mkdir(dirname($codex_path), 0777, true);
file_put_contents($codex_path, implode("\n", [
    '[profile.sentinel]',
    'model = "keep-me"',
    '',
    '[mcp_servers.livecanvas-forge]',
    'command = "/old/node"',
    '',
    '[mcp_servers.livecanvas-forge.env]',
    'LCFA_MCP_TOKEN = "legacy-token"',
]) . "\n");
$codex_result = $writer->write([
    'path' => $codex_path,
    'type' => 'toml',
    'client' => 'codex',
    'server_name' => 'livecanvas-forge',
    'content' => implode("\n", [
        '[mcp_servers.livecanvas-forge]',
        'command = "/opt/homebrew/bin/node"',
        'args = ["' . $wordpress_root . '/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js"]',
        '',
        '[mcp_servers.livecanvas-forge.env]',
        'LCFA_AGENT = "codex"',
        'LCFA_WP_ROOT = "' . $wordpress_root . '"',
    ]) . "\n",
], $workspace_root, $wordpress_root);

lcfa_writer_assert_true(!empty($codex_result['ok']), 'Codex TOML should be safely merged');
$codex_merged = (string) file_get_contents($codex_path);
lcfa_writer_assert_true(strpos($codex_merged, '[profile.sentinel]') !== false, 'Codex merge should preserve unrelated TOML sections');
lcfa_writer_assert_true(strpos($codex_merged, 'model = "keep-me"') !== false, 'Codex merge should preserve unrelated TOML values');
lcfa_writer_assert_true(strpos($codex_merged, '/opt/homebrew/bin/node') !== false, 'Codex merge should install the generated server');
lcfa_writer_assert_true(strpos($codex_merged, 'legacy-token') === false, 'Codex merge should remove a legacy static token from the replaced server');

$claude_config_root = $tmp . '/Library/Application Support/Claude';
@mkdir($claude_config_root, 0777, true);
$claude_config_path = $claude_config_root . '/claude_desktop_config.json';
file_put_contents($claude_config_path, json_encode([
    'coworkUserFilesPath' => '/keep/cowork',
    'preferences' => [
        'locale' => 'it-IT',
        'theme' => 'dark',
    ],
    'mcpServers' => [
        'sentinel' => [
            'type' => 'stdio',
            'command' => '/usr/bin/true',
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
$claude_generated = json_encode([
    'mcpServers' => [
        'livecanvas-forge' => [
            'type' => 'stdio',
            'command' => '/opt/homebrew/bin/node',
            'args' => [$wordpress_root . '/wp-content/plugins/livecanvas-forge-ai/mcp/bin/livecanvas-forge-mcp.js', '--transport=stdio'],
            'env' => [
                'LCFA_AGENT' => 'claude',
                'LCFA_AGENT_WORKSPACE_ROOT' => $workspace_root,
                'LCFA_WP_ROOT' => $wordpress_root,
            ],
        ],
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
$claude_result = $writer->write([
    'path' => $claude_config_path,
    'type' => 'json',
    'client' => 'claude-desktop',
    'server_name' => 'livecanvas-forge',
    'content' => $claude_generated,
], $claude_config_root, $wordpress_root);

lcfa_writer_assert_true(!empty($claude_result['ok']), 'Claude Desktop app config should be safely merged when the internally resolved config directory is writable');
lcfa_writer_assert_true(is_file((string) ($claude_result['backup_path'] ?? '')), 'Claude Desktop merge should back up an existing app config');
lcfa_writer_assert_same('0600', $claude_result['mode'] ?? '', 'Claude Desktop app config should use mode 0600');
$claude_merged = json_decode((string) file_get_contents($claude_config_path), true);
lcfa_writer_assert_same('/keep/cowork', $claude_merged['coworkUserFilesPath'] ?? '', 'Claude Desktop merge should preserve unrelated top-level app settings');
lcfa_writer_assert_same('dark', $claude_merged['preferences']['theme'] ?? '', 'Claude Desktop merge should preserve the preferences block');
lcfa_writer_assert_same('/usr/bin/true', $claude_merged['mcpServers']['sentinel']['command'] ?? '', 'Claude Desktop merge should preserve unrelated MCP servers');
lcfa_writer_assert_same('/opt/homebrew/bin/node', $claude_merged['mcpServers']['livecanvas-forge']['command'] ?? '', 'Claude Desktop merge should add only livecanvas-forge');
lcfa_writer_assert_true(strpos((string) file_get_contents($claude_config_path), 'LCFA_MCP_TOKEN') === false, 'Claude Desktop app config should not contain a static MCP token');

echo "PASS\n";
