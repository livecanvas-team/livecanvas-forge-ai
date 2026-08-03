<?php

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/lcfa-remote-short-circuit/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_VERSION', 'test');

$GLOBALS['lcfa_remote_loopback_calls'] = 0;

function __(string $text, string $domain = ''): string { return $text; }
function get_transient(string $key) { return false; }
function set_transient(string $key, $value, int $expiration = 0): bool { return true; }
function home_url(string $path = ''): string { return 'https://remote.example/' . ltrim($path, '/'); }
function rest_url(string $path = ''): string { return 'https://remote.example/wp-json/' . ltrim($path, '/'); }
function untrailingslashit(string $value): string { return rtrim($value, '/\\'); }
function wp_remote_get(string $url, array $args = []) {
    $GLOBALS['lcfa_remote_loopback_calls']++;
    return ['response' => ['code' => 200]];
}

final class LCFA_Environment {
    public function get_snapshot(): array {
        return [
            'site_mode'        => 'remote',
            'windpress_active' => true,
        ];
    }
}

function lcfa_remote_bridge_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-local-mcp-bridge.php';

$status = (new LCFA_Local_MCP_Bridge(new LCFA_Environment()))->get_status();
lcfa_remote_bridge_assert(empty($status['available']), 'Remote sites must not expose the local MCP bridge.');
lcfa_remote_bridge_assert(empty($status['local_site']), 'Remote site status should be explicit.');
lcfa_remote_bridge_assert($GLOBALS['lcfa_remote_loopback_calls'] === 0, 'Remote status checks must not perform a REST loopback probe.');
lcfa_remote_bridge_assert(($status['node_binary'] ?? null) === '', 'Remote status checks must not resolve or execute Node.js.');

echo "PASS\n";
