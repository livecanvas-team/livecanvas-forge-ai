<?php

declare(strict_types=1);

define('ABSPATH', sys_get_temp_dir() . '/lcfa-wp7-mcp-adapter/');

function rest_url(string $path = ''): string {
    return 'https://example.test/wp-json/' . ltrim($path, '/');
}

function home_url(string $path = ''): string {
    return 'https://example.test/' . ltrim($path, '/');
}

function trailingslashit(string $value): string {
    return rtrim($value, '/\\') . '/';
}

function untrailingslashit(string $value): string {
    return rtrim($value, '/\\');
}

eval('namespace WP\\MCP\\Core { class McpAdapter {} }');
eval('namespace WP\\MCP\\Transport { class HttpTransport {} }');
eval('namespace WP\\MCP\\Infrastructure\\ErrorHandling { class ErrorLogMcpErrorHandler {} }');
eval('namespace WP\\MCP\\Infrastructure\\Observability { class NullMcpObservabilityHandler {} }');

require dirname(__DIR__, 2) . '/includes/class-lcfa-environment.php';

function lcfa_wp7_mcp_assert_true($condition, string $message): void {
    if (!$condition) {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }
}

function lcfa_wp7_mcp_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . "\n");
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . "\n");
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . "\n");
        exit(1);
    }
}

$environment = new LCFA_Environment();
$status = $environment->get_mcp_adapter_status();

lcfa_wp7_mcp_assert_true(!empty($status['available']), 'MCP Adapter status should be available when required adapter classes exist');
lcfa_wp7_mcp_assert_same('livecanvas-forge-ai', $status['custom_server']['id'] ?? '', 'MCP Adapter status should expose the AI Bridge custom server ID');
lcfa_wp7_mcp_assert_same('livecanvas-forge-ai', $status['custom_server']['namespace'] ?? '', 'MCP Adapter status should expose the AI Bridge custom server namespace');
lcfa_wp7_mcp_assert_same('mcp', $status['custom_server']['route'] ?? '', 'MCP Adapter status should expose the AI Bridge custom server route');
lcfa_wp7_mcp_assert_same('https://example.test/wp-json/livecanvas-forge-ai/mcp', $status['custom_server']['url'] ?? '', 'MCP Adapter status should expose the remote-connectable AI Bridge MCP URL');
lcfa_wp7_mcp_assert_same('@livecanvas/ai-bridge-mcp', $status['remote_proxy']['package'] ?? '', 'MCP Adapter status should document the secure AI Bridge remote proxy package');
lcfa_wp7_mcp_assert_true(in_array('LCFA_SITE_FINGERPRINT', $status['remote_proxy']['env'] ?? [], true), 'MCP Adapter status should document the site fingerprint environment variable');
lcfa_wp7_mcp_assert_same('ai_bridge_pairing', $status['remote_proxy']['auth'] ?? '', 'MCP Adapter status should document secure pairing auth');

echo "PASS\n";
