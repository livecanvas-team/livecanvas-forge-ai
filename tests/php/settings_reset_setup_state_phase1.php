<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');

$GLOBALS['lcfa_test_options'] = [];
$GLOBALS['lcfa_test_transients_deleted'] = [];

function get_option(string $key, $default = false) {
    return $GLOBALS['lcfa_test_options'][$key] ?? $default;
}

function update_option(string $key, $value): bool {
    $GLOBALS['lcfa_test_options'][$key] = $value;
    return true;
}

function delete_option(string $key): bool {
    unset($GLOBALS['lcfa_test_options'][$key]);
    return true;
}

function wp_parse_args($args, $defaults = []): array {
    return array_merge($defaults, is_array($args) ? $args : []);
}

function sanitize_text_field(string $value): string {
    return $value;
}

function sanitize_textarea_field(string $value): string {
    return $value;
}

function esc_url_raw(string $value): string {
    return $value;
}

function sanitize_key(string $value): string {
    return strtolower(preg_replace('/[^a-zA-Z0-9_\\-]/', '', $value) ?? '');
}

function absint($value): int {
    return abs((int) $value);
}

function rest_url(string $path = ''): string {
    return 'http://example.test/wp-json/' . ltrim($path, '/');
}

function wp_generate_password(int $length = 12, bool $special_chars = false, bool $extra_special_chars = false): string {
    return 'fresh-token-for-reset-state-123456';
}

function get_current_user_id(): int {
    return 7;
}

function delete_transient(string $key): bool {
    $GLOBALS['lcfa_test_transients_deleted'][] = $key;
    return true;
}

require dirname(__DIR__, 2) . '/includes/class-lcfa-settings.php';

function lcfa_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$GLOBALS['lcfa_test_options'][LCFA_Settings::OPTION_KEY] = [
    'completed' => true,
    'framework' => 'picowind',
    'site_mode' => 'local',
    'ai_tool' => 'codex',
    'permission_profile' => 'advanced_templates',
    'allow_file_fallback' => true,
    'last_completed_step' => 6,
];

$GLOBALS['lcfa_test_options'][LCFA_Settings::CONNECTIONS_OPTION_KEY] = [
    'transport' => 'mcp',
    'preferred_client' => 'codex',
    'workspace_root' => '/Users/example/project',
    'connection_status' => 'ready',
    'connection_mode' => 'local',
    'connection_last_verified_at' => '2026-04-15 12:00:00',
    'connection_last_error' => '',
    'connection_last_bundle_hash' => 'abc123',
    'connection_current_step' => 'ready',
    'mcp_token' => 'old-token',
];

LCFA_Settings::reset_setup_state();

$settings = $GLOBALS['lcfa_test_options'][LCFA_Settings::OPTION_KEY] ?? [];
$connections = $GLOBALS['lcfa_test_options'][LCFA_Settings::CONNECTIONS_OPTION_KEY] ?? [];
$deleted_transients = $GLOBALS['lcfa_test_transients_deleted'];

lcfa_assert_same(false, $settings['completed'] ?? null, 'reset_setup_state should clear the completed flag');
lcfa_assert_same('', $settings['framework'] ?? null, 'reset_setup_state should clear the selected framework');
lcfa_assert_same('', $settings['ai_tool'] ?? null, 'reset_setup_state should clear the selected coding agent');
lcfa_assert_same(0, $settings['last_completed_step'] ?? null, 'reset_setup_state should clear step progress');

lcfa_assert_same('', $connections['preferred_client'] ?? null, 'reset_setup_state should clear the preferred client');
lcfa_assert_same('', $connections['workspace_root'] ?? null, 'reset_setup_state should clear the workspace root');
lcfa_assert_same('', $connections['connection_status'] ?? null, 'reset_setup_state should clear the connection status');
lcfa_assert_same('', $connections['connection_current_step'] ?? null, 'reset_setup_state should clear the connection wizard step');
lcfa_assert_same('fresh-token-for-reset-state-123456', $connections['mcp_token'] ?? null, 'reset_setup_state should rotate the MCP token');

lcfa_assert_same([
    'lcfa_command_result_7',
    'lcfa_command_suggestion_7',
    'lcfa_connection_test_7',
], $deleted_transients, 'reset_setup_state should clear transient runtime feedback for the current user');

echo "PASS\n";
