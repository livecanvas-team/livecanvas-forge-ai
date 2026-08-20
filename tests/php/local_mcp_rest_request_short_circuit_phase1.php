<?php

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/lcfa-rest-request-short-circuit/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');
define('LCFA_VERSION', 'test');
define('REST_REQUEST', true);

$GLOBALS['lcfa_rest_request_loopback_calls'] = 0;

function __(string $text, string $domain = ''): string { return $text; }
function get_transient(string $key) { return false; }
function set_transient(string $key, $value, int $expiration = 0): bool { return true; }
function home_url(string $path = ''): string { return 'http://example.test/' . ltrim($path, '/'); }
function rest_url(string $path = ''): string { return 'http://example.test/wp-json/' . ltrim($path, '/'); }
function untrailingslashit(string $value): string { return rtrim($value, '/\\'); }
function apply_filters(string $hook, $value) { return $hook === 'lcfa_local_node_binary' ? '/bin/true' : $value; }
function wp_remote_get(string $url, array $args = []) {
    $GLOBALS['lcfa_rest_request_loopback_calls']++;
    return ['response' => ['code' => 200]];
}

final class LCFA_Environment {
    public function get_snapshot(): array {
        return [
            'site_mode'        => 'local',
            'windpress_active' => true,
        ];
    }
}

function lcfa_rest_request_bridge_assert(bool $condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-local-mcp-bridge.php';

$status = (new LCFA_Local_MCP_Bridge(new LCFA_Environment()))->get_status();
lcfa_rest_request_bridge_assert(!empty($status['available']), 'An authenticated REST request should prove that the local MCP bridge is reachable.');
lcfa_rest_request_bridge_assert(!empty($status['rest_reachable']), 'REST reachability should be true while serving the current MCP request.');
lcfa_rest_request_bridge_assert($GLOBALS['lcfa_rest_request_loopback_calls'] === 0, 'MCP status must not start a nested self-loopback from inside a REST request.');
lcfa_rest_request_bridge_assert(strpos((string) ($status['rest_message'] ?? ''), 'additional self-loopback probe is unnecessary') !== false, 'REST status should explain why the nested loopback was skipped.');

echo "PASS\n";
