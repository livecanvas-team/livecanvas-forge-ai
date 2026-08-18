<?php

declare(strict_types=1);

require_once __DIR__ . '/reflection-compat.php';

error_reporting(E_ALL);

define('ABSPATH', '/tmp/lcfa-tests/');
define('LCFA_DIR', dirname(__DIR__, 2) . '/');

function __(string $text, string $domain = ''): string {
    return $text;
}

function sanitize_key(string $value): string {
    return strtolower((string) preg_replace('/[^a-zA-Z0-9_\-]/', '', $value));
}

function lcfa_setup_access_assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

final class LCFA_Settings {
    public static function sanitize_power_mode(string $mode): string {
        return in_array($mode, ['auto', 'enabled', 'disabled'], true) ? $mode : 'auto';
    }

    public static function get_full_access_mcp_write_abilities(): array {
        return [
            'livecanvas-forge-ai/apply-page-upsert',
            'livecanvas-forge-ai/theme-file-write',
            'livecanvas-forge-ai/media-upload',
            'livecanvas-forge-ai/cache-flush',
            'livecanvas-forge-ai/seo-tools',
        ];
    }
}

require LCFA_DIR . 'includes/class-lcfa-admin.php';

$admin = (new ReflectionClass('LCFA_Admin'))->newInstanceWithoutConstructor();
$apply_access = lcfa_test_reflection_method('LCFA_Admin', 'apply_setup_access_choice');

$configure_and_build = $apply_access->invoke($admin, [
    'power_mode' => 'auto',
    'mcp_write_abilities_enabled' => false,
    'connection_status' => 'ready',
    'connection_last_bundle_hash' => 'old-bundle',
    'connection_last_verified_at' => '2026-08-18T08:00:00Z',
    'connection_current_step' => 'ready',
], true);

lcfa_setup_access_assert_same('enabled', $configure_and_build['power_mode'] ?? '', 'Configure and build should enable Power Mode on remote and local sites.');
lcfa_setup_access_assert_same(true, $configure_and_build['mcp_enabled'] ?? null, 'Configure and build should keep MCP enabled.');
lcfa_setup_access_assert_same(true, $configure_and_build['mcp_write_abilities_enabled'] ?? null, 'Configure and build should enable MCP write abilities.');
lcfa_setup_access_assert_same(LCFA_Settings::get_full_access_mcp_write_abilities(), $configure_and_build['mcp_public_write_abilities'] ?? [], 'Configure and build should enable the complete write allowlist.');
lcfa_setup_access_assert_same(true, $configure_and_build['mcp_public_write_abilities_configured'] ?? null, 'Configure and build should persist an explicit administrator choice.');
lcfa_setup_access_assert_same('needs_attention', $configure_and_build['connection_status'] ?? '', 'Changing access should invalidate an existing connection.');
lcfa_setup_access_assert_same('generate_bundle', $configure_and_build['connection_current_step'] ?? '', 'Changing access should return onboarding to bundle generation.');
lcfa_setup_access_assert_same('', $configure_and_build['connection_last_bundle_hash'] ?? 'missing', 'Changing access should invalidate the old setup bundle.');

$inspect_only = $apply_access->invoke($admin, [
    'power_mode' => 'enabled',
    'mcp_write_abilities_enabled' => true,
    'connection_status' => '',
], false);

lcfa_setup_access_assert_same('disabled', $inspect_only['power_mode'] ?? '', 'Inspect only should explicitly disable Power Mode, including on local sites.');
lcfa_setup_access_assert_same(false, $inspect_only['mcp_write_abilities_enabled'] ?? null, 'Inspect only should disable MCP write abilities.');
lcfa_setup_access_assert_same([], $inspect_only['mcp_public_write_abilities'] ?? ['unexpected'], 'Inspect only should expose no public write abilities.');
lcfa_setup_access_assert_same(true, $inspect_only['mcp_public_write_abilities_configured'] ?? null, 'Inspect only should remain an explicit administrator choice.');

echo "PASS\n";
