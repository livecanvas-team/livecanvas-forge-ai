<?php

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/lcfa-codex-config-manager-' . getmypid();
$wp_root = $tmp . '/site';
$plugin_root = $wp_root . '/wp-content/plugins/livecanvas-forge-ai';
$home = $tmp . '/home';
$node_binary = $tmp . '/bin/node';
@mkdir($plugin_root . '/mcp/bin', 0777, true);
@mkdir($home, 0777, true);
@mkdir(dirname($node_binary), 0777, true);
file_put_contents($wp_root . '/wp-load.php', '<?php');
file_put_contents($plugin_root . '/mcp/bin/livecanvas-forge-mcp.js', '#!/usr/bin/env node');
file_put_contents($node_binary, "#!/usr/bin/env sh\necho v99.0.0\n");
chmod($node_binary, 0777);

define('ABSPATH', $wp_root . '/');
define('LCFA_DIR', $plugin_root . '/');
define('LCFA_VERSION', 'test');
putenv('HOME=' . $home);
putenv('LCFA_NODE_BINARY=' . $node_binary);

function __($text, $domain = null) { return $text; }
function trailingslashit($value) { return rtrim((string) $value, '/') . '/'; }
function untrailingslashit($value) { return rtrim((string) $value, '/'); }
function home_url($path = '') { return 'http://example.test/' . ltrim((string) $path, '/'); }
function rest_url($path = '') { return 'http://example.test/wp-json/' . ltrim((string) $path, '/'); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function wp_mkdir_p($path) { return @mkdir((string) $path, 0777, true) || is_dir((string) $path); }

final class LCFA_Settings {
    public static function get_connections(): array {
        return [
            'mcp_token'          => 'new-token',
            'mcp_host'           => '127.0.0.1',
            'mcp_port'           => '7681',
            'workspace_root'     => untrailingslashit(ABSPATH),
            'codex_config_scope' => 'project',
        ];
    }

    public static function get_mcp_endpoint(): string {
        return 'ws://127.0.0.1:7681';
    }

    public static function sanitize_codex_config_scope(string $scope): string {
        return in_array($scope, ['project', 'global'], true) ? $scope : 'project';
    }

    public static function get_site_fingerprint(): string {
        return 'site-fp-test';
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-codex-config-manager.php';

function lcfa_config_assert_true($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function lcfa_config_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . "\nMissing: " . $needle . "\n");
        exit(1);
    }
}

$manager = new LCFA_Codex_Config_Manager();
$expected = $manager->get_expected_config(LCFA_Settings::get_connections());
lcfa_config_assert_contains('command = "' . $node_binary . '"', $expected['snippet'], 'expected TOML should use an explicit Node binary when LCFA_NODE_BINARY is available');
lcfa_config_assert_contains($plugin_root . '/mcp/bin/livecanvas-forge-mcp.js', $expected['snippet'], 'expected TOML should point to the current plugin MCP script');
lcfa_config_assert_contains('LCFA_MCP_TOKEN = "new-token"', $expected['snippet'], 'expected TOML should contain the current MCP token');
lcfa_config_assert_contains('LCFA_SITE_FINGERPRINT = "site-fp-test"', $expected['snippet'], 'expected TOML should contain the current site fingerprint');
lcfa_config_assert_contains('LCFA_WP_ROOT = "' . untrailingslashit(ABSPATH) . '"', $expected['snippet'], 'expected TOML should contain the current WordPress root');
lcfa_config_assert_true(($expected['config_scope'] ?? '') === 'project', 'manager should default to project-scoped Codex config');

$config_path = $wp_root . '/.codex/config.toml';
@mkdir(dirname($config_path), 0777, true);
file_put_contents($config_path, implode("\n", [
    '[profile.default]',
    'model = "gpt-5.3-codex"',
    '',
    '[mcp_servers.livecanvas-forge]',
    'command = "node"',
    'args = ["/old/path/mcp/bin/livecanvas-forge-mcp.js", "--transport=stdio"]',
    '',
    '[mcp_servers.livecanvas-forge.env]',
    'LCFA_MCP_TOKEN = "old-token"',
    'LCFA_WP_ROOT = "/old/path"',
]));

$stale = $manager->inspect(LCFA_Settings::get_connections());
lcfa_config_assert_true(empty($stale['synced']), 'manager should detect stale Codex config values before sync');

$sync = $manager->sync(LCFA_Settings::get_connections());
lcfa_config_assert_true(!empty($sync['ok']), 'manager should sync Codex config');
lcfa_config_assert_true(is_file($sync['backup_path'] ?? ''), 'manager should create a backup before overwriting config');

$contents = file_get_contents($config_path);
lcfa_config_assert_contains('[profile.default]', $contents, 'manager should preserve unrelated TOML sections');
lcfa_config_assert_contains($plugin_root . '/mcp/bin/livecanvas-forge-mcp.js', $contents, 'manager should replace stale script path');
lcfa_config_assert_contains('LCFA_MCP_TOKEN = "new-token"', $contents, 'manager should replace stale token');
lcfa_config_assert_contains('LCFA_SITE_FINGERPRINT = "site-fp-test"', $contents, 'manager should write the site fingerprint');
lcfa_config_assert_true(strpos($contents, 'old-token') === false, 'manager should remove stale token from livecanvas-forge section');

$synced = $manager->inspect(LCFA_Settings::get_connections());
lcfa_config_assert_true(!empty($synced['synced']), 'manager should report synced after writing expected config');

$global_expected = $manager->get_expected_config(array_merge(LCFA_Settings::get_connections(), [
    'codex_config_scope' => 'global',
]));
lcfa_config_assert_true(($global_expected['config_scope'] ?? '') === 'global', 'manager should still support explicit global Codex config');
lcfa_config_assert_true(($global_expected['config_path'] ?? '') === $home . '/.codex/config.toml', 'global Codex config should resolve to HOME when explicitly selected');

echo "PASS\n";
