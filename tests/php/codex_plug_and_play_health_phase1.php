<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$local_bridge = (string) file_get_contents($root . '/includes/class-lcfa-local-mcp-bridge.php');
$rest_api = (string) file_get_contents($root . '/includes/class-lcfa-rest-api.php');
$connection_tester = (string) file_get_contents($root . '/includes/class-lcfa-connection-tester.php');

function lcfa_codex_health_assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . "\nMissing: " . $needle . "\n");
        exit(1);
    }
}

function lcfa_codex_health_assert_not_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . "\nUnexpected: " . $needle . "\n");
        exit(1);
    }
}

$probe_start = strpos($local_bridge, 'private function probe_rest_loopback');
$probe_end = strpos($local_bridge, 'private function build_command', $probe_start);
$probe_body = $probe_start !== false && $probe_end !== false
    ? substr($local_bridge, $probe_start, $probe_end - $probe_start)
    : '';
$health_start = strpos($rest_api, 'public function get_mcp_health');
$health_end = strpos($rest_api, 'public function get_mcp_local_status', $health_start);
$health_body = $health_start !== false && $health_end !== false
    ? substr($rest_api, $health_start, $health_end - $health_start)
    : '';

lcfa_codex_health_assert_contains("rest_url('lcfa/v1/mcp/health')", $probe_body, 'local MCP REST loopback should use the atomic health endpoint');
lcfa_codex_health_assert_not_contains("rest_url('lcfa/v1/mcp/status')", $probe_body, 'local MCP REST loopback must not call /mcp/status because that route computes bridge status');
lcfa_codex_health_assert_contains("register_rest_route('lcfa/v1', '/mcp/health'", $rest_api, 'REST API should expose a dedicated MCP health endpoint');
lcfa_codex_health_assert_contains('public function get_mcp_health()', $rest_api, 'REST API should implement a health callback that returns atomic fields');
lcfa_codex_health_assert_not_contains('get_status()', $health_body, 'MCP health must not call local bridge get_status');
lcfa_codex_health_assert_not_contains('get_mcp_status()', $health_body, 'MCP health must not call the aggregate MCP status builder');
lcfa_codex_health_assert_contains("'permission_callback' => [\$this, 'can_mcp_health']", $rest_api, 'MCP health endpoint should require the MCP token instead of admin readiness');
lcfa_codex_health_assert_contains('has_valid_mcp_token($request) || $this->has_valid_mcp_session($request, \'read\')', $rest_api, 'MCP health permission should validate the current MCP token or AI Bridge session');
lcfa_codex_health_assert_contains("'mcp_script'", $rest_api, 'MCP health should report the expected local script path');
lcfa_codex_health_assert_contains("'script_exists'", $rest_api, 'MCP health should report whether the local MCP script is readable');
lcfa_codex_health_assert_contains("trailingslashit(\$url) . 'mcp/health'", $connection_tester, 'local REST smoke test should probe the atomic health endpoint');
lcfa_codex_health_assert_not_contains("trailingslashit(\$url) . 'mcp/status'", $connection_tester, 'local REST smoke test should avoid the recursive status endpoint');

echo "PASS\n";
