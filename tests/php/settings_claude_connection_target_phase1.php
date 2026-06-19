<?php

declare(strict_types=1);

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');

$GLOBALS['lcfa_options'] = [];

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

function esc_url_raw(string $value): string {
    return $value;
}

function rest_url(string $path = ''): string {
    return 'http://example.test/wp-json/' . ltrim($path, '/');
}

function absint($value): int {
    return (int) $value;
}

function wp_generate_password(int $length = 32, bool $special_chars = false, bool $extra_special_chars = false): string {
    return str_repeat('a', $length);
}

function get_option(string $key, $default = []) {
    return $GLOBALS['lcfa_options'][$key] ?? $default;
}

function update_option(string $key, $value): bool {
    $GLOBALS['lcfa_options'][$key] = $value;

    return true;
}

function wp_parse_args($args, $defaults = []) {
    return array_merge($defaults, is_array($args) ? $args : []);
}

function wp_json_encode($value, int $flags = 0): string {
    return (string) json_encode($value, $flags);
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

require LCFA_DIR . 'includes/class-lcfa-settings.php';

$defaults = LCFA_Settings::connection_defaults();

lcfa_assert_true(array_key_exists('claude_connection_target', $defaults), 'connection defaults should expose claude_connection_target');
lcfa_assert_same('', $defaults['claude_connection_target'], 'claude_connection_target should default to an empty string');
lcfa_assert_true(array_key_exists('codex_model', $defaults), 'connection defaults should expose a Codex model default');
lcfa_assert_true(array_key_exists('codex_speed', $defaults), 'connection defaults should expose a Codex speed default');
lcfa_assert_true(array_key_exists('codex_reasoning_effort', $defaults), 'connection defaults should expose a Codex intelligence default');
lcfa_assert_true(array_key_exists('mcp_write_abilities_enabled', $defaults), 'connection defaults should expose the MCP write ability opt-in flag');
lcfa_assert_same(true, $defaults['mcp_write_abilities_enabled'], 'MCP write abilities should default to enabled for paired agents');
lcfa_assert_true(array_key_exists('mcp_public_write_abilities', $defaults), 'connection defaults should expose the MCP write ability allowlist');
lcfa_assert_same(LCFA_Settings::get_default_mcp_write_abilities(), $defaults['mcp_public_write_abilities'], 'MCP write ability allowlist should default to curated write abilities');
lcfa_assert_same('gpt-5.3-codex-spark', $defaults['codex_model'], 'Codex model should default to the fast frontend model');
lcfa_assert_same('balanced', $defaults['codex_speed'], 'Codex speed should default to balanced');
lcfa_assert_same('medium', $defaults['codex_reasoning_effort'], 'Codex intelligence should default to medium');

$sanitized_legacy = LCFA_Settings::sanitize_connections([
    'preferred_client'         => 'claude-code',
    'claude_connection_target' => 'desktop_app',
]);

lcfa_assert_same('claude', $sanitized_legacy['preferred_client'] ?? '', 'legacy claude-code should normalize to claude');
lcfa_assert_same('cli', $sanitized_legacy['claude_connection_target'] ?? '', 'legacy claude-code should force the cli target');

$sanitized_desktop = LCFA_Settings::sanitize_connections([
    'preferred_client'         => 'claude',
    'claude_connection_target' => 'desktop_app',
]);

lcfa_assert_same('claude', $sanitized_desktop['preferred_client'] ?? '', 'claude should remain the canonical preferred_client');
lcfa_assert_same('desktop_app', $sanitized_desktop['claude_connection_target'] ?? '', 'claude desktop target should be preserved');

$sanitized_codex = LCFA_Settings::sanitize_connections([
    'preferred_client' => 'codex',
    'codex_model' => 'gpt-5.4-mini',
    'codex_speed' => 'fast',
    'codex_reasoning_effort' => 'low',
    'mcp_write_abilities_enabled' => '1',
]);
lcfa_assert_same('gpt-5.4-mini', $sanitized_codex['codex_model'] ?? '', 'Codex model defaults should be saved from Connections');
lcfa_assert_same('fast', $sanitized_codex['codex_speed'] ?? '', 'Codex speed defaults should be saved from Connections');
lcfa_assert_same('low', $sanitized_codex['codex_reasoning_effort'] ?? '', 'Codex intelligence defaults should be saved from Connections');
lcfa_assert_same(true, $sanitized_codex['mcp_write_abilities_enabled'] ?? null, 'MCP write ability opt-in should sanitize to boolean true');
lcfa_assert_same(array_keys(LCFA_Settings::get_mcp_write_ability_options()), $sanitized_codex['mcp_public_write_abilities'] ?? [], 'legacy write opt-in should default to all curated write abilities when no allowlist was submitted');

$sanitized_write_allowlist = LCFA_Settings::sanitize_connections([
    'preferred_client' => 'codex',
    'mcp_write_abilities_enabled' => '1',
    'mcp_public_write_abilities_submitted' => '1',
    'mcp_public_write_abilities' => [
        'livecanvas-forge-ai/apply-page-upsert',
        'livecanvas-forge-ai/apply-command',
        'livecanvas-forge-ai/restore-audit-rollback',
    ],
]);
lcfa_assert_same([
    'livecanvas-forge-ai/apply-page-upsert',
    'livecanvas-forge-ai/restore-audit-rollback',
], $sanitized_write_allowlist['mcp_public_write_abilities'] ?? [], 'MCP write allowlist should keep only curated dedicated write abilities');
lcfa_assert_same(true, $sanitized_write_allowlist['mcp_public_write_abilities_configured'] ?? null, 'submitted write allowlist should be marked configured');

$sanitized_empty_write_allowlist = LCFA_Settings::sanitize_connections([
    'preferred_client' => 'codex',
    'mcp_write_abilities_enabled' => '1',
    'mcp_public_write_abilities_submitted' => '1',
]);
lcfa_assert_same([], $sanitized_empty_write_allowlist['mcp_public_write_abilities'] ?? ['not-empty'], 'submitted empty write allowlist should expose no write abilities');
lcfa_assert_same(true, $sanitized_empty_write_allowlist['mcp_public_write_abilities_configured'] ?? null, 'submitted empty write allowlist should be preserved as an explicit admin configuration');

$GLOBALS['lcfa_options'][LCFA_Settings::CONNECTIONS_OPTION_KEY] = array_merge(
    LCFA_Settings::connection_defaults(),
    [
        'preferred_client'         => 'claude-code',
        'claude_connection_target' => '',
    ]
);

$normalized = LCFA_Settings::get_connections();

lcfa_assert_same('claude', $normalized['preferred_client'] ?? '', 'get_connections should normalize legacy claude-code values on read');
lcfa_assert_same('cli', $normalized['claude_connection_target'] ?? '', 'get_connections should infer the cli target for legacy claude-code values');
lcfa_assert_same(true, $normalized['mcp_write_abilities_enabled'] ?? null, 'get_connections should keep default write abilities enabled');
lcfa_assert_same(LCFA_Settings::get_default_mcp_write_abilities(), $normalized['mcp_public_write_abilities'] ?? [], 'get_connections should keep the default write allowlist');

$GLOBALS['lcfa_options'][LCFA_Settings::CONNECTIONS_OPTION_KEY] = array_merge(
    LCFA_Settings::connection_defaults(),
    [
        'mcp_write_abilities_enabled' => false,
        'mcp_public_write_abilities' => [],
        'mcp_public_write_abilities_configured' => false,
    ]
);

$migrated_write_defaults = LCFA_Settings::get_connections();

lcfa_assert_same(true, $migrated_write_defaults['mcp_write_abilities_enabled'] ?? null, 'get_connections should migrate unconfigured legacy write settings to enabled');
lcfa_assert_same(LCFA_Settings::get_default_mcp_write_abilities(), $migrated_write_defaults['mcp_public_write_abilities'] ?? [], 'get_connections should migrate unconfigured legacy write allowlist to defaults');

echo "PASS\n";
